<?php

namespace App\Http\Middleware;

use App\Authentik\AuthentikJwtValidator;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AuthentikJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $claims = app(AuthentikJwtValidator::class)->validate($token);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $sub = (string) ($claims['sub'] ?? '');
        $email = (string) ($claims['email'] ?? '');
        $name = (string) ($claims['name'] ?? '');

        if ($sub === '' || $email === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::query()->updateOrCreate(
            ['auth_sub' => $sub],
            [
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
                'password' => Str::random(64),
                'role' => $this->resolveRole($claims),
            ],
        );

        Auth::setUser($user);

        return $next($request);
    }

    private function resolveRole(array $claims): string
    {
        $groups = (array) ($claims['groups'] ?? []);
        $roleMap = config('authentik-oidc.role_map', []);

        foreach ($roleMap as $group => $role) {
            if (in_array($group, $groups, true)) {
                return $role;
            }
        }

        return 'user';
    }
}

