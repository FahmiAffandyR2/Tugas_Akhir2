<?php

namespace App\Http\Controllers\Api;

use App\Models\Place;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use App\Http\Requests\RegisterRequest;
use App\Repository\UserRepositoryInterface;
use App\Repository\DriverInformationRepositoryInterface;
use App\Repository\DriverDocumentRepositoryInterface;
use App\Support\OptionalFirebase;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Illuminate\Support\Facades\Auth as LaravelAuth;
use DB;

use Validator;
use App\Traits\UserUtils;
use App\Traits\AuthSec;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{
    use AuthSec;
    use UserUtils;
    private $auth;
    private $userRepository;
    private $driverInformationRepository;
    private $driverDocumentRepository;
    public function __construct(
        UserRepositoryInterface $userRepository,
        DriverInformationRepositoryInterface $driverInformationRepository,
        DriverDocumentRepositoryInterface $driverDocumentRepository
    ) {
        $this->userRepository = $userRepository;
        $this->driverInformationRepository = $driverInformationRepository;
        $this->driverDocumentRepository = $driverDocumentRepository;
    }

    //resetPassword
    public function resetPassword(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
        ]);

        if (!$request->filled('token')) {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
            ], [
                'email.exists' => 'Email tidak terdaftar.',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $status = Password::sendResetLink($request->only('email'));

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json(['message' => 'Link reset password telah dikirim ke email Anda.']);
            }

            return response()->json(['message' => __($status)], 422);
        }

        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password berhasil diubah. Silakan login kembali.']);
        }

        return response()->json(['message' => __($status)], 422);
    }

    public function loginViaToken(Request $request)
    {
        Log::info('loginViaToken');
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'device_name' => 'required',
        ]);

        if ($validator->fails()) {
            //pass validator errors as errors object for ajax response
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            //authenticate user using firebase token
            $verifiedIdToken = OptionalFirebase::auth()->verifyIdToken($request->token);
        } catch (InvalidToken $e) {
            return response()->json(['errors' => ['authentication' => ['The token is invalid: ' . $e->getMessage()]]], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => ['authentication' => ['The token could not be parsed: ' . $e->getMessage()]]], 403);
        } catch (\Exception $e) {
            return OptionalFirebase::unavailableResponse($e);
        }

        //get user id
        $uid = $verifiedIdToken->claims()->get('sub');

        $user = $this->userRepository->findByWhere([['uid', '=', $uid]])->first();
        if ($user) {
            $user->request_delete_at = null;
            $user->save();
            //user exists
            if (($user->role == 1 && $user->status_id != 1) ||($user->role == 2 && $user->status_id == 3))  {
                //user is not active
                return response()->json(['errors' => ['authentication' => ['User is not active. Please contact the admin.']]], 403);
            }
            $user->tokens()->where('name', $request->device_name)->delete();
            if ($request->has('fcm_token')) {
                $user->fcm_token =  $request->fcm_token;
                $user->save();
            }
            //create token
            $tokenAbility = "";
            if ($user->role == 0)
                $tokenAbility = "admin";
            else if ($user->role == 1)
                $tokenAbility = "customer";
            else
                $tokenAbility = "driver";

            $token = $this->createToken($user, $request->device_name, [$tokenAbility]);
            if($user->role == 1 || $user->role == 2)
            {
                $token = $this->get_sec_id($token);
                if($token == null)
                {
                    return response()->json(['errors' => ['authentication' => ['Error in generating token']]], 403);
                }
            }
            $driverInformation = null;
            if($user->role == 2)
            {
                $user_id = $user->id;
                //get driver information
                $driverInformation = $this->driverInformationRepository->findByWhere(['user_id' => $user_id])->first();
                // if(!$driverInformation)
                // {
                //     return response()->json(['error' => ['Driver information does not exist']], 422);
                // }
                if($driverInformation)
                {
                    //get driver documents
                    $driverDocuments = $this->driverDocumentRepository->findByWhere(['driver_information_id' => $driverInformation->id]);
                    $driverInformation->documents = $driverDocuments;
                }

                //get user info
                $userInfo = $this->userRepository->findById($user_id);
            }
            //return token and user data
            return response()->json([
                'token' => $token,
                'user_data' => $user,
                'driver_data' => $driverInformation,
                'admin' => ($user->role == 0)
            ]);
        } else {

            //validate the request. Make sure it contains role
            $validator = Validator::make($request->all(), [
                'role' => 'required|integer',
            ]);

            if ($request->role == 0) {
                return response()->json(['errors' => ['authentication' => ['User does not exist']]], 403);
            }
            //create user
            $user = new User();
            $user->uid = $uid;
            $user->role = $request->role;
            $user->status_id = 1;
            $user->name = "";
            if ($request->has('fcm_token')) {
                $user->fcm_token =  $request->fcm_token;
            }
            $user->save();
            //create token
            $token = $this->createToken($user, $request->device_name, ["customer"]);
            //return token and user data
            return response()->json([
                'token' => $token,
                'user_data' => $user
            ]);
        }
    }
    public function login(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string',
            'portal' => 'required|in:internal,customer,all',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            if (LaravelAuth::attempt($request->only('email', 'password'))) {
                /** @var User $user */
                $user = LaravelAuth::user();

                $role = (int) $user->role;
                if ($request->portal === 'customer' && $role !== 1) {
                    LaravelAuth::logout();
                    return response(['message' => 'Akun ini bukan akun customer. Silakan gunakan portal internal.'], 403);
                }
                if ($request->portal === 'internal' && !in_array($role, [0, 2], true)) {
                    LaravelAuth::logout();
                    return response(['message' => 'Akun customer harus masuk melalui portal customer.'], 403);
                }

                if (!in_array($role, [0, 1, 2], true)) {
                    LaravelAuth::logout();
                    return response(['message' => 'Akun tidak memiliki role yang valid.'], 403);
                }

                if ($role === 2 && (int) $user->status_id === 3) {
                    LaravelAuth::logout();
                    return response(['message' => 'Akun driver sedang ditangguhkan. Hubungi Super Admin.'], 403);
                }

                $ability = $role === 0
                    ? 'admin'
                    : ($role === 2 ? 'driver' : 'customer');
                $tokenPortal = $request->portal === 'all'
                    ? ($role === 1 ? 'customer' : 'internal')
                    : $request->portal;
                $tokenName = $tokenPortal . '-' . ($request->device_name ?? 'web');
                $token = $this->createToken($user, $tokenName, [$ability]);

                if (config('auth.must_verify_email') && !$user->hasVerifiedEmail()) {
                    return response([
                        'message' => 'Email must be verified.'
                    ], 401);
                }

                return response([
                    'message' => 'success',
                    'token' => $token,
                    'user_data' => $user,
                    'admin' => ((int) $user->role === 0)
                ]);
            }
        } catch (\Exception $e) {
            return response([
                'message' => 'Internal error, please try again later.' //$e->getMessage()
            ], 400);
        }

        return response([
            'message' => 'Email atau password salah.'
        ], 401);
    }

    public function user()
    {
        //return the user object
        return response()->json(LaravelAuth::user());
    }

    public function createCustomer(Request $request)
    {
        return $this->createCustomerDriver($request, 1);
    }

    public function createDriver(Request $request)
    {
        return $this->createCustomerDriver($request, 2);
    }

    /**
     * Register a driver from the web PWA using email and password.
     * The role and initial status are controlled by the server.
     */
    public function registerDriver(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
        ]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'tel_number' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $driver = User::create([
                'name' => $request->name,
                'email' => strtolower($request->email),
                'password' => Hash::make($request->password),
                'tel_number' => $request->tel_number,
                'role' => 2,
                'status_id' => 1,
                'uid' => 'driver-web-' . (string) \Illuminate\Support\Str::uuid(),
            ]);

            // This application does not currently provide an email verification
            // delivery flow for password-based driver registration.
            $driver->email_verified_at = now();
            $driver->save();
            $this->storeAvatar($driver);

            DB::commit();

            return response()->json([
                'message' => 'Driver berhasil dibuat. Driver dapat login menggunakan email dan password tersebut.',
                'user' => [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'email' => $driver->email,
                    'role' => (int) $driver->role,
                    'status_id' => (int) $driver->status_id,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Driver web registration failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Registrasi driver gagal. Silakan coba kembali.',
            ], 500);
        }
    }

    /**
     * Register a customer for the separate customer portal.
     * Customer role and active status are always assigned by the server.
     */
    public function registerCustomer(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
        ]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'tel_number' => 'nullable|string|max:30',
        ], [
            'email.unique' => 'Email tersebut sudah terdaftar.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $customer = User::create([
                'name' => trim($request->name),
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'tel_number' => $request->tel_number,
                'role' => 1,
                'status_id' => 1,
                'uid' => 'customer-web-' . (string) \Illuminate\Support\Str::uuid(),
            ]);

            // Password registration currently has no email delivery flow.
            $customer->email_verified_at = now();
            $customer->save();
            $this->storeAvatar($customer);

            DB::commit();

            return response()->json([
                'message' => 'Registrasi berhasil. Silakan login.',
                'user' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'role' => (int) $customer->role,
                    'status_id' => (int) $customer->status_id,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Customer web registration failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Registrasi customer gagal. Silakan coba kembali.',
            ], 500);
        }
    }

    private function createCustomerDriver(Request $request, Int $role)
    {
        $uid = $this->getUserUid($request, $role);
        if ($uid instanceof \Illuminate\Http\JsonResponse) {
            return $uid;
        }

        $user_exist = false;

        // check if user already exists and he is a customer
        $user = User::where('role', $role)->where('uid', $uid)->first();
        if($user)
        {
            $user_exist = true;
        }

        try{
            if($user_exist)
            {
                //user exists
                return $this->existedUserData($request, $user);
            }
            else{
                //user does not exist
                return $this->newUserData($uid, $request, $role);
            }
        }
        catch(UserNotFound $e)
        {
            //user not found
            return response()->json(['errors' => ['authentication' => ['User not found: '.$e->getMessage()]]], 403);
        }
    }

    private function getUserUid($request, $role)
    {
        $validators = [
            'token' => 'required|string',
            'name' => 'required|string',
            'device_name' => 'required',
        ];

        $validators = $role == 0 ? array_merge($validators, ['role' => 'required']) : $validators;

        $validator = Validator::make($request->all(), $validators);

        if ($validator->fails()) {
            //pass validator errors as errors object for ajax response
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $verifiedIdToken = OptionalFirebase::auth()->verifyIdToken($request->token);
        } catch (InvalidToken $e) {
            Log::info('getUserUid error', ['error' => $e->getMessage()]);
            return response()->json(['errors' => ['authentication' => ['The token is invalid: '.$e->getMessage()]]], 403);
        } catch (\InvalidArgumentException $e) {
            Log::info('getUserUid error', ['error' => $e->getMessage()]);
            return response()->json(['errors' => ['authentication' => ['The token could not be parsed: '.$e->getMessage()]]], 403);
        } catch (\Exception $e) {
            return OptionalFirebase::unavailableResponse($e);
        }
        //get user id
        $uid = $verifiedIdToken->claims()->get('sub');
        return $uid;
    }
    private function newUserData($uid, $request, $role)
    {
        //create a transaction for all operations
        DB::beginTransaction();
        try {
            $authUser = OptionalFirebase::auth()->getUser($uid);
            //log user
            Log::info('User logged in', ['user_data' => $authUser]);
            $localUser = User::create([
                'name' => $request->name,
                'email' => $authUser->email,
                'password' => $authUser->passwordHash!=null? $authUser->passwordHash : "",
                'uid' => $authUser->uid,
                'role' => $role,
                'status_id' => ($role==0 || $role==1) ? 1 : 2,
            ]);

            if($role == 1)
            {
                // $customer = \Stripe\Customer::create(['email' => $authUser->email]);
                // $localUser->stripe_id = $customer->id;
            }

            if($request->has('fcm_token'))
            {
                $localUser->fcm_token =  $request->fcm_token;
            }

            $this->storeAvatar($localUser);
            $localUser->save();

            if($role==0)
                $tokenAbility = "admin";
            else if($role==1)
                $tokenAbility = "customer";
            else
                $tokenAbility = "driver";

            $token = $this->createToken($localUser, $request->device_name, [$tokenAbility]);
            if($localUser->role == 1 || $localUser->role == 2)
            {
                $token = $this->get_sec_id($token);
                if($token == null)
                {
                    return response()->json(['errors' => ['authentication' => ['Error in generating token']]], 403);
                }
            }
            //add home and work places for customer
            if($role == 1)
            {
                //add home and work places
                $home = new Place();
                $home->user_id = $localUser->id;
                $home->name = "Home";
                $home->type = 1;
                $home->favorite = 1;
                $home->latitude = 0;
                $home->longitude = 0;
                $home->address = "";
                $home->save();

                $work = new Place();
                $work->user_id = $localUser->id;
                $work->name = "Work";
                $work->type = 2;
                $work->favorite = 1;
                $work->latitude = 0;
                $work->longitude = 0;
                $work->address = "";
                $work->save();
            }

            DB::commit();
            return response()->json(['token' => $token,
            'user_data' => $localUser, 'admin' => ($localUser->role==0),
            ]);
        } catch (\Exception $e) {
            // delete firebase user if any error
            try {
                OptionalFirebase::auth()->deleteUser($uid);
            } catch (\Exception $firebaseException) {
                Log::warning('Firebase user cleanup failed', ['error' => $firebaseException->getMessage()]);
            }
            //rollback transaction
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
    private function existedUserData($request, $user)
    {
        //user exists
        if (($user->role == 1 && $user->status_id != 1) ||($user->role == 2 && $user->status_id == 3))
        {
            //user is not active
            return response()->json(['errors' => ['authentication' => ['User is not active. Please contact the admin.']]], 403);
        }
        //delete all tokens for this device
        $user->tokens()->where('name', $request->device_name)->delete();
        if($request->has('fcm_token'))
        {
            $user->fcm_token =  $request->fcm_token;
            $user->save();
        }
        //create token
        $tokenAbility = "";
        if($user->role==0)
            $tokenAbility = "admin";
        else if($user->role==1)
            $tokenAbility = "customer";
        else
            $tokenAbility = "driver";

        //create token
        $token = $this->createToken($user, $request->device_name, [$tokenAbility]);
        if($user->role == 1 || $user->role == 2)
        {
            $token = $this->get_sec_id($token);
            if($token == null)
            {
                return response()->json(['errors' => ['authentication' => ['Error in generating token']]], 403);
            }
        }
        return response()->json(['token' => $token, 'user_data' => $user, 'admin' => ($user->role == 0)]);
    }

    //verifyUser
    public function verifyUser(Request $request)
    {
        //get the auth user
        $user = $request->user();

        if (($user->role == 1 && $user->status_id != 1) ||($user->role == 2 && $user->status_id == 3))
        {
            return response()->json(['errors' => ['authentication' => ['User is not active. Please contact the admin.']]], 403);
        }

        return new UserResource($user);
    }
}
