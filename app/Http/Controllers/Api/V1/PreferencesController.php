<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SyncRevision;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PreferencesController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $preference = UserPreference::query()
            ->where('user_id', $user->id)
            ->first();

        $rev = SyncRevision::query()
            ->where('user_id', $user->id)
            ->where('scope', 'preferences')
            ->max('rev');

        return response()->json([
            'rev' => $rev ? (int) $rev : 0,
            'data' => $preference?->data ?? [],
            'updated_at' => $preference?->updated_at?->toISOString(),
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $user = $request->user();
        $payload = $request->json()->all();
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        if (! is_array($data)) {
            return response()->json(['message' => 'Invalid payload.'], 422);
        }

        $result = DB::transaction(function () use ($user, $data) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $preference = UserPreference::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['data' => $data],
            );

            $lastRev = (int) SyncRevision::query()
                ->where('user_id', $user->id)
                ->max('rev');

            $rev = $lastRev + 1;

            SyncRevision::query()->create([
                'user_id' => $user->id,
                'rev' => $rev,
                'scope' => 'preferences',
                'payload' => $data,
            ]);

            return [$preference, $rev];
        });

        $preference = $result[0];
        $rev = $result[1];

        return response()->json([
            'rev' => (int) $rev,
            'data' => $preference->data,
            'updated_at' => $preference->updated_at?->toISOString(),
        ]);
    }
}
