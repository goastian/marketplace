<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        Log::info('marketplace.auth.me.response', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'request_id' => $request->header('X-Request-Id'),
            'user_id' => $user?->id,
            'auth_sub' => $user?->auth_sub,
            'role' => $user?->role,
        ]);

        $payload = [
            'id' => $user?->id,
            'sub' => $user?->auth_sub,
            'email' => $user?->email,
            'name' => $user?->name,
        ];

        return response()->json([
            // Keep legacy top-level keys and expose data for SPA parity.
            ...$payload,
            'data' => $payload,
        ]);
    }
}

