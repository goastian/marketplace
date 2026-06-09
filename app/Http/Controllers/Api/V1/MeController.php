<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

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

