<?php

namespace App\Authentik;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class AuthentikDiscovery
{
    public function getOpenIdConfiguration(): array
    {
        $issuer = rtrim((string) config('authentik.issuer'), '/');
        if ($issuer === '') {
            throw new \RuntimeException('AUTHENTIK_ISSUER is not configured.');
        }

        $cacheKey = 'authentik.oidc.openid_configuration.'.sha1($issuer);
        $ttlSeconds = max(60, (int) config('authentik.discovery_cache_seconds'));

        return Cache::remember($cacheKey, $ttlSeconds, function () use ($issuer) {
            $url = $issuer.'/.well-known/openid-configuration';
            $response = Http::timeout(8)->acceptJson()->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException('Failed to fetch OIDC discovery document.');
            }

            $data = $response->json();
            if (! is_array($data)) {
                throw new \RuntimeException('Invalid OIDC discovery document.');
            }

            return $data;
        });
    }

    public function getJwksUri(): string
    {
        $config = $this->getOpenIdConfiguration();
        $jwksUri = (string) ($config['jwks_uri'] ?? '');

        if ($jwksUri === '') {
            throw new \RuntimeException('OIDC discovery document is missing jwks_uri.');
        }

        return $jwksUri;
    }

    public function getJwks(): array
    {
        $jwksUri = $this->getJwksUri();
        $cacheKey = 'authentik.oidc.jwks.'.sha1($jwksUri);
        $ttlSeconds = max(60, (int) config('authentik.jwks_cache_seconds'));

        return Cache::remember($cacheKey, $ttlSeconds, function () use ($jwksUri) {
            $response = Http::timeout(8)->acceptJson()->get($jwksUri);

            if (! $response->successful()) {
                throw new \RuntimeException('Failed to fetch JWKS.');
            }

            $data = $response->json();
            if (! is_array($data) || ! isset($data['keys']) || ! is_array($data['keys'])) {
                throw new \RuntimeException('Invalid JWKS payload.');
            }

            return $data;
        });
    }
}

