<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ApiV1AuthentikJwtTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_requires_bearer_token(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_me_accepts_valid_authentik_jwt(): void
    {
        Cache::flush();

        config()->set('authentik.issuer', 'https://auth.example');
        config()->set('authentik.audience', 'midori-newtab');
        config()->set('authentik.discovery_cache_seconds', 3600);
        config()->set('authentik.jwks_cache_seconds', 3600);
        config()->set('authentik.leeway_seconds', 0);

        [$jwt, $jwksUri] = $this->makeJwtAndJwks('https://auth.example', 'midori-newtab');

        Http::fake([
            'https://auth.example/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://auth.example',
                'jwks_uri' => $jwksUri,
            ], 200),
            $jwksUri => Http::response($this->jwksPayload, 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$jwt)->getJson('/api/v1/me');

        $response
            ->assertStatus(200)
            ->assertJsonPath('email', 'user@example.com')
            ->assertJsonPath('name', 'User Example')
            ->assertJsonPath('sub', $this->sub);
    }

    private array $jwksPayload = [];

    private string $sub = '';

    private function makeJwtAndJwks(string $issuer, string $audience): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        $details = openssl_pkey_get_details($privateKey);

        $kid = 'test-kid-1';
        $jwksUri = $issuer.'/jwks';

        $this->jwksPayload = [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => $kid,
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'n' => $this->base64UrlEncode($details['rsa']['n']),
                    'e' => $this->base64UrlEncode($details['rsa']['e']),
                ],
            ],
        ];

        $this->sub = (string) Str::uuid();

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $kid,
        ];

        $payload = [
            'iss' => $issuer,
            'aud' => $audience,
            'sub' => $this->sub,
            'email' => 'user@example.com',
            'name' => 'User Example',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signed = $encodedHeader.'.'.$encodedPayload;

        openssl_sign($signed, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $jwt = $signed.'.'.$this->base64UrlEncode($signature);

        return [$jwt, $jwksUri];
    }

    private function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}

