<?php

return [
    'client_id' => env('AUTHENTIK_OIDC_CLIENT_ID', ''),
    'client_secret' => env('AUTHENTIK_OIDC_CLIENT_SECRET', ''),
    'redirect_uri' => env('AUTHENTIK_OIDC_REDIRECT_URI', '/auth/callback'),

    'scopes' => 'openid email profile groups',

    'authorize_url' => env('AUTHENTIK_OIDC_AUTHORIZE_URL', ''),
    'token_url' => env('AUTHENTIK_OIDC_TOKEN_URL', ''),
    'userinfo_url' => env('AUTHENTIK_OIDC_USERINFO_URL', ''),
    'end_session_url' => env('AUTHENTIK_OIDC_END_SESSION_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Group → role mapping
    |--------------------------------------------------------------------------
    | Maps Authentik group names to marketplace roles.
    | First match wins. Users not matching any group get 'user'.
    */
    'role_map' => [
        'marketplace-admins' => 'admin',
        'marketplace-developers' => 'developer',
    ],
];
