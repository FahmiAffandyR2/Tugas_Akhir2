<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function index()
    {
        $templates = NotificationTemplate::orderBy('name')->get();
        return response()->json($templates);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:push,sms,email',
            'subject' => 'nullable|string|max:200',
            'body' => 'required|string',
            'variables' => 'nullable|array',
            'is_active' => 'required|boolean',
        ]);

        $template = NotificationTemplate::create($validated);

        return response()->json([
            'message' => 'Template notifikasi berhasil dibuat.',
            'template' => $template,
        ], 201);
    }

    public function update(Request $request, NotificationTemplate $notificationTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:push,sms,email',
            'subject' => 'nullable|string|max:200',
            'body' => 'required|string',
            'variables' => 'nullable|array',
            'is_active' => 'required|boolean',
        ]);

        $notificationTemplate->update($validated);

        return response()->json([
            'message' => 'Template notifikasi berhasil diperbarui.',
            'template' => $notificationTemplate,
        ]);
    }

    public function destroy(NotificationTemplate $notificationTemplate)
    {
        $notificationTemplate->delete();
        return response()->json(['message' => 'Template notifikasi berhasil dihapus.']);
    }

    public function preview(Request $request, NotificationTemplate $notificationTemplate)
    {
        $data = $request->input('data', []);
        return response()->json([
            'rendered' => $notificationTemplate->render($data),
        ]);
    }
}
