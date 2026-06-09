<?php

namespace App\Http\Middleware;

use App\Authentik\AuthentikJwtValidator;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AuthentikJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        // If user is already authenticated via web session, allow through.
        if (Auth::check()) {
            Log::debug('marketplace.auth.jwt.session_authenticated', $this->requestContext($request) + [
                'user_id' => Auth::id(),
            ]);

            return $next($request);
        }

        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            Log::warning('marketplace.auth.jwt.missing_bearer_token', $this->requestContext($request));
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $claims = app(AuthentikJwtValidator::class)->validate($token);
        } catch (\Throwable $e) {
            Log::warning('marketplace.auth.jwt.invalid_token', $this->requestContext($request) + [
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $sub = (string) ($claims['sub'] ?? '');
        $email = (string) ($claims['email'] ?? '');
        $name = (string) ($claims['name'] ?? '');

        if ($sub === '' || $email === '') {
            Log::warning('marketplace.auth.jwt.missing_claims', $this->requestContext($request) + [
                'sub_present' => $sub !== '',
                'email_present' => $email !== '',
            ]);
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

        Log::info('marketplace.auth.jwt.authenticated', $this->requestContext($request) + [
            'user_id' => $user->id,
            'role' => $user->role,
            'auth_sub' => $sub,
        ]);

        return $next($request);
    }

    private function requestContext(Request $request): array
    {
        return [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'request_id' => $request->header('X-Request-Id'),
        ];
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

