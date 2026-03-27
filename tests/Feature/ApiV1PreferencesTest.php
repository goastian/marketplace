<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ApiV1PreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_put_and_get_preferences_roundtrip(): void
    {
        Cache::flush();

        config()->set('authentik.issuer', 'https://auth.example');
        config()->set('authentik.audience', 'midori-newtab');
        config()->set('authentik.leeway_seconds', 0);

        [$jwt, $jwksUri] = $this->makeJwtAndJwks('https://auth.example', 'midori-newtab');

        Http::fake([
            'https://auth.example/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://auth.example',
                'jwks_uri' => $jwksUri,
            ], 200),
            $jwksUri => Http::response($this->jwksPayload, 200),
        ]);

        $put = $this->withHeader('Authorization', 'Bearer '.$jwt)
            ->putJson('/api/v1/me/preferences', ['data' => ['foo' => 'bar']]);

        $put
            ->assertStatus(200)
            ->assertJsonPath('rev', 1)
            ->assertJsonPath('data.foo', 'bar');

        $get = $this->withHeader('Authorization', 'Bearer '.$jwt)
            ->getJson('/api/v1/me/preferences');

        $get
            ->assertStatus(200)
            ->assertJsonPath('rev', 1)
            ->assertJsonPath('data.foo', 'bar');
    }

    private array $jwksPayload = [];

    private function makeJwtAndJwks(string $issuer, string $audience): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        $details = openssl_pkey_get_details($privateKey);

        $kid = 'test-kid-2';
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

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $kid,
        ];

        $payload = [
            'iss' => $issuer,
            'aud' => $audience,
            'sub' => (string) Str::uuid(),
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

