<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Models\NotificationTemplate;
use App\Models\AuthSetting;
use Illuminate\Http\Request;
use App\Repository\ComplaintRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Repository\NotificationRepositoryInterface;

class NotificationController extends Controller
{
    private $complaintRepository;
    private $userRepository;
    private $notificationRepository;

    public function __construct(
        ComplaintRepositoryInterface $complaintRepository,
        UserRepositoryInterface $userRepository,
        NotificationRepositoryInterface $notificationRepository
    ) {
        $this->complaintRepository = $complaintRepository;
        $this->userRepository = $userRepository;
        $this->notificationRepository = $notificationRepository;
    }

    public function index()
    {
        $unResolvedComplaints = $this->complaintRepository->allWhere(['*'], [], ['status' => 0], false);
        $unResolvedComplaintsCount = $unResolvedComplaints->count();

        $driversUnderReview = $this->userRepository->allWhere(['*'], [], ['status_id' => 4, 'role' => 2], false);
        $driversUnderReviewCount = $driversUnderReview->count();

        $adminUser = $this->userRepository->allWhere(['*'], [], ['role' => 0], false)->first();

        $secure_key = null;
        $authSetting = AuthSetting::first();
        if (!($authSetting == null || $authSetting->secure_key == null
            || $authSetting->u1 == null
            || $authSetting->u2 == null
            || $authSetting->u3 == null)) {
            $secure_key = $authSetting->secure_key;
        }

        return response()->json([
            'unResolvedComplaintsCount' => $unResolvedComplaintsCount,
            'driversUnderReviewCount' => $driversUnderReviewCount,
            'adminName' => $adminUser->name,
            'adminAvatar' => $adminUser->avatar,
            'secureKey' => $secure_key
        ]);
    }

    public function listAll(Request $request)
    {
        $user = $request->user();
        $notifications = $this->notificationRepository->allWhere(['*'], [], ['user_id' => $user->id], true);

        return response()->json([
            'notifications' => $notifications
        ]);
    }

    public function markAllAsSeen(Request $request)
    {
        $user = $request->user();
        $this->notificationRepository->bulkUpdate(['seen' => 1], ['user_id' => $user->id]);
        return response()->json([
            'message' => 'success'
        ]);
    }

    public function markAsSeen(Request $request)
    {
        $request->validate([
            'id' => 'required|integer'
        ]);
        $user = $request->user();
        $notificationId = $request->id;
        $this->notificationRepository->update($notificationId, ['seen' => 1]);
        return response()->json([
            'message' => 'success'
        ]);
    }

    public function history(Request $request)
    {
        $query = Notification::with('user:id,name,email');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(50);
        return response()->json($notifications);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*' => 'integer|exists:users,id',
            'message' => 'required|string|max:1000',
            'template_id' => 'nullable|integer|exists:notification_templates,id',
        ]);

        $template = null;
        if (!empty($validated['template_id'])) {
            $template = NotificationTemplate::find($validated['template_id']);
        }

        $sent = 0;
        foreach ($validated['recipient_ids'] as $userId) {
            $message = $validated['message'];
            if ($template) {
                $user = User::find($userId);
                $message = $template->render([
                    'nama' => $user->name ?? '',
                    'email' => $user->email ?? '',
                ]);
            }

            Notification::create([
                'user_id' => $userId,
                'message' => $message,
                'seen' => 0,
            ]);
            $sent++;
        }

        return response()->json([
            'message' => "{$sent} notifikasi berhasil dikirim.",
            'sent' => $sent,
        ]);
    }

    public function recipients()
    {
        $drivers = User::where('role', 2)->where('status_id', 1)
            ->select('id', 'name', 'email')
            ->get()
            ->map(fn($d) => ['id' => $d->id, 'name' => $d->name, 'email' => $d->email, 'type' => 'Driver']);

        $customers = User::where('role', 1)->where('status_id', 1)
            ->select('id', 'name', 'email')
            ->get()
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'email' => $c->email, 'type' => 'Customer']);

        return response()->json([
            'drivers' => $drivers,
            'customers' => $customers,
        ]);
    }
}
