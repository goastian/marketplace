<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class AuthController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('oidc_state', $state);

        $query = http_build_query([
            'client_id' => config('authentik-oidc.client_id'),
            'redirect_uri' => url(config('authentik-oidc.redirect_uri')),
            'response_type' => 'code',
            'scope' => config('authentik-oidc.scopes'),
            'state' => $state,
        ]);

        return redirect(config('authentik-oidc.authorize_url') . '?' . $query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->query('state');
        $sessionState = $request->session()->pull('oidc_state');

        if (! is_string($state) || ! hash_equals((string) $sessionState, $state)) {
            return redirect('/')->with('error', 'Invalid state.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return redirect('/')->with('error', 'Authorization failed.');
        }

        $tokenResponse = Http::asForm()->post(config('authentik-oidc.token_url'), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => url(config('authentik-oidc.redirect_uri')),
            'client_id' => config('authentik-oidc.client_id'),
            'client_secret' => config('authentik-oidc.client_secret'),
        ]);

        if (! $tokenResponse->successful()) {
            return redirect('/')->with('error', 'Token exchange failed.');
        }

        $tokens = $tokenResponse->json();
        $accessToken = $tokens['access_token'] ?? null;

        if (! is_string($accessToken)) {
            return redirect('/')->with('error', 'No access token received.');
        }

        // Fetch userinfo.
        $userinfoResponse = Http::withToken($accessToken)
            ->get(config('authentik-oidc.userinfo_url'));

        if (! $userinfoResponse->successful()) {
            return redirect('/')->with('error', 'Could not fetch user info.');
        }

        $userinfo = $userinfoResponse->json();

        $sub = (string) ($userinfo['sub'] ?? '');
        $email = (string) ($userinfo['email'] ?? '');
        $name = (string) ($userinfo['name'] ?? '');
        $groups = (array) ($userinfo['groups'] ?? []);

        if ($sub === '' || $email === '') {
            return redirect('/')->with('error', 'Incomplete user info.');
        }

        $role = $this->resolveRole($groups);

        $user = User::query()->updateOrCreate(
            ['auth_sub' => $sub],
            [
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
                'password' => Str::random(64),
                'role' => $role,
            ],
        );

        // Store tokens in encrypted session.
        $request->session()->put('oidc_access_token', $accessToken);
        $request->session()->put('oidc_refresh_token', $tokens['refresh_token'] ?? null);

        Auth::login($user);
        $request->session()->regenerate();

        AuditLog::record('login', $user->id, 'User', $user->id, null, $request->ip());

        return redirect()->intended($role === 'admin' ? '/admin' : '/');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            AuditLog::record('logout', $user->id, 'User', $user->id, null, $request->ip());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $endSessionUrl = config('authentik-oidc.end_session_url');

        if (is_string($endSessionUrl) && $endSessionUrl !== '') {
            return redirect($endSessionUrl . '?' . http_build_query(['redirect_uri' => url('/')]));
        }

        return redirect('/');
    }

    private function resolveRole(array $groups): string
    {
        $roleMap = config('authentik-oidc.role_map', []);

        foreach ($roleMap as $group => $role) {
            if (in_array($group, $groups, true)) {
                return $role;
            }
        }

        return 'user';
    }
}
