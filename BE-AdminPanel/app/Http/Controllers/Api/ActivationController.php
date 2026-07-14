<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuthSetting;
use Validator;
use DB;

class ActivationController extends Controller
{
    public function load(Request $request)
    {
        //check if already activated
        $authSetting = AuthSetting::first();
        if(!($authSetting == null || $authSetting->secure_key == null
        || $authSetting->u1 == null
        || $authSetting->u2 == null
        || $authSetting->u3 == null))
        {
            //already activated
            return response()->json(['secure_key' => $authSetting->secure_key]);
        }
        //not activated
        return response()->json(['secure_key' => null]);
    }
    //
    public function activate(Request $request)
    {
        //validate request
        $validator = Validator::make($request->all(), [
            'activationCode' => 'required'
        ]);

        if ($validator->fails()) {
            //pass validator errors as errors object for ajax response
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            AuthSetting::query()->delete();
            AuthSetting::create([
                'secure_key' => trim($request->activationCode),
                'u1' => 'local',
                'u2' => 'local',
                'u3' => 'local',
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'activated successfully']);
    }
}
