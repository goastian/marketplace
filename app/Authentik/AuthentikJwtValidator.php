<?php

namespace App\Authentik;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class AuthentikJwtValidator
{
    public function __construct(
        private readonly AuthentikDiscovery $discovery,
    ) {
    }

    public function validate(string $token): array
    {
        [$encodedHeader, $encodedPayload, $encodedSignature] = Jwt::split($token);

        $header = Jwt::decodePart($encodedHeader);
        $payload = Jwt::decodePart($encodedPayload);

        $alg = (string) ($header['alg'] ?? '');
        if ($alg !== 'RS256') {
            throw new \RuntimeException('Unsupported JWT alg.');
        }

        $kid = (string) ($header['kid'] ?? '');
        if ($kid === '') {
            throw new \RuntimeException('Missing JWT kid.');
        }

        $issuerExpected = rtrim((string) config('authentik.issuer'), '/');
        if ($issuerExpected === '') {
            throw new \RuntimeException('AUTHENTIK_ISSUER is not configured.');
        }

        $audExpected = (string) config('authentik.audience');
        if ($audExpected === '') {
            throw new \RuntimeException('AUTHENTIK_AUDIENCE is not configured.');
        }

        $issuerToken = rtrim((string) ($payload['iss'] ?? ''), '/');
        if (! hash_equals($issuerExpected, $issuerToken)) {
            throw new \RuntimeException('Invalid JWT iss.');
        }

        if (! $this->audienceMatches($payload['aud'] ?? null, $audExpected)) {
            throw new \RuntimeException('Invalid JWT aud.');
        }

        $now = Carbon::now('UTC')->getTimestamp();
        $leeway = max(0, (int) config('authentik.leeway_seconds'));

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp <= 0 || ($now - $leeway) >= $exp) {
            throw new \RuntimeException('JWT expired.');
        }

        $nbf = (int) ($payload['nbf'] ?? 0);
        if ($nbf > 0 && ($now + $leeway) < $nbf) {
            throw new \RuntimeException('JWT not active yet.');
        }

        $jwk = $this->findJwkByKid($kid);
        $publicKeyPem = Jwk::rsaToPublicKeyPem($jwk);

        $signature = Base64Url::decode($encodedSignature);
        $signed = $encodedHeader.'.'.$encodedPayload;

        $verified = openssl_verify($signed, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw new \RuntimeException('Invalid JWT signature.');
        }

        $sub = (string) ($payload['sub'] ?? '');
        if ($sub === '' || ! Str::isUuid($sub) && strlen($sub) < 8) {
            throw new \RuntimeException('Invalid JWT sub.');
        }

        return $payload;
    }

    private function findJwkByKid(string $kid): array
    {
        $jwks = $this->discovery->getJwks();

        foreach ($jwks['keys'] as $key) {
            if (is_array($key) && (string) ($key['kid'] ?? '') === $kid) {
                return $key;
            }
        }

        throw new \RuntimeException('No matching JWK found for kid.');
    }

    private function audienceMatches(mixed $audClaim, string $audExpected): bool
    {
        if (is_string($audClaim)) {
            return hash_equals($audExpected, $audClaim);
        }

        if (is_array($audClaim)) {
            foreach ($audClaim as $aud) {
                if (is_string($aud) && hash_equals($audExpected, $aud)) {
                    return true;
                }
            }
        }

        return false;
    }
}
