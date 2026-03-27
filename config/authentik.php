<?php

return [
    'issuer' => rtrim((string) env('AUTHENTIK_ISSUER', ''), '/'),
    'audience' => (string) env('AUTHENTIK_AUDIENCE', ''),
    'leeway_seconds' => (int) env('AUTHENTIK_LEEWAY_SECONDS', 60),
    'discovery_cache_seconds' => (int) env('AUTHENTIK_DISCOVERY_CACHE_SECONDS', 3600),
    'jwks_cache_seconds' => (int) env('AUTHENTIK_JWKS_CACHE_SECONDS', 3600),
];

