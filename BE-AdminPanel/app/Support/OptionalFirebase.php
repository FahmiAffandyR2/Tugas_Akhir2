<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class OptionalFirebase
{
    public static function auth()
    {
        return app('firebase.auth');
    }

    public static function messaging()
    {
        return app('firebase.messaging');
    }

    public static function unavailableResponse(\Throwable $exception)
    {
        Log::warning('Firebase is unavailable', ['error' => $exception->getMessage()]);

        return response()->json([
            'message' => 'Firebase is not configured. Set FIREBASE_CREDENTIALS to enable this feature.',
        ], 503);
    }
}
