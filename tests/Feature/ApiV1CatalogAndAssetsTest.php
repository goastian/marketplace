<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ApiV1CatalogAndAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_and_detail_only_return_published_assets(): void
    {
        $published = Asset::query()->create([
            'type' => 'theme',
            'slug' => 'nebula-theme',
            'name' => 'Nebula Theme',
            'description' => 'Theme for testing catalog.',
            'author' => 'Midori Team',
            'license' => 'MIT',
            'tags' => ['space', 'dark'],
            'status' => 'published',
            'published_at' => now(),
        ]);

        AssetVersion::query()->create([
            'asset_id' => $published->id,
            'version' => '1.0.0',
            'status' => 'published',
            'browsers' => ['chrome', 'firefox'],
            'manifest' => ['name' => 'Nebula Theme'],
            'file_disk' => 'local',
            'file_path' => 'assets/test/nebula-theme-1.0.0.json',
            'published_at' => now(),
        ]);

        $draft = Asset::query()->create([
            'type' => 'widget',
            'slug' => 'draft-widget',
            'name' => 'Draft Widget',
            'status' => 'draft',
            'tags' => ['draft'],
        ]);

        AssetVersion::query()->create([
            'asset_id' => $draft->id,
            'version' => '0.1.0',
            'status' => 'draft',
            'browsers' => ['chrome'],
            'manifest' => ['name' => 'Draft Widget'],
            'file_disk' => 'local',
            'file_path' => 'assets/test/draft-widget-0.1.0.json',
        ]);

        $catalog = $this->getJson('/api/v1/catalog?type=theme&tags=space');

        $catalog
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'nebula-theme')
            ->assertJsonPath('data.0.latest_version.version', '1.0.0');

        $detail = $this->getJson('/api/v1/assets/nebula-theme');

        $detail
            ->assertStatus(200)
            ->assertJsonPath('data.slug', 'nebula-theme')
            ->assertJsonPath('data.versions.0.version', '1.0.0');

        $this->getJson('/api/v1/assets/draft-widget')->assertStatus(404);
    }

    public function test_download_endpoint_redirects_to_signed_url_and_streams_file(): void
    {
        Storage::fake('local');

        $asset = Asset::query()->create([
            'type' => 'wallpaper',
            'slug' => 'aurora-wallpaper',
            'name' => 'Aurora Wallpaper',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $filePath = 'assets/test/aurora-wallpaper-1.2.0.zip';
        Storage::disk('local')->put($filePath, 'binary-content');

        AssetVersion::query()->create([
            'asset_id' => $asset->id,
            'version' => '1.2.0',
            'status' => 'published',
            'browsers' => ['chrome', 'firefox', 'midori'],
            'manifest' => ['name' => 'Aurora Wallpaper'],
            'file_disk' => 'local',
            'file_path' => $filePath,
            'published_at' => now(),
        ]);

        $redirect = $this->get('/api/v1/assets/aurora-wallpaper/download?version=1.2.0');

        $redirect->assertStatus(302);

        $downloadUrl = $redirect->headers->get('Location');

        $this->assertIsString($downloadUrl);
        $this->assertStringContainsString('signature=', $downloadUrl);

        $download = $this->get($downloadUrl);

        $download
            ->assertStatus(200)
            ->assertHeader('content-disposition');

        $this->assertStringContainsString('aurora-wallpaper-1.2.0.zip', (string) $download->headers->get('content-disposition'));
    }

    public function test_authenticated_asset_management_flow_works(): void
    {
        [$jwt, $jwksUri] = $this->fakeAuthentik();

        $created = $this
            ->withHeader('Authorization', 'Bearer '.$jwt)
            ->postJson('/api/v1/me/assets', [
                'type' => 'theme',
                'slug' => 'mint-theme',
                'name' => 'Mint Theme',
                'description' => 'Theme from authenticated endpoint.',
                'tags' => ['mint', 'light'],
            ]);

        $created
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'mint-theme')
            ->assertJsonPath('data.status', 'draft');

        $assetId = (int) $created->json('data.id');

        $updated = $this
            ->withHeader('Authorization', 'Bearer '.$jwt)
            ->putJson('/api/v1/me/assets/'.$assetId, [
                'status' => 'published',
                'license' => 'MIT',
            ]);

        $updated
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.license', 'MIT');

        $version = $this
            ->withHeader('Authorization', 'Bearer '.$jwt)
            ->post('/api/v1/me/assets/'.$assetId.'/versions', [
                'version' => '1.0.0',
                'manifest' => ['name' => 'Mint Theme', 'entry' => 'index.json'],
                'file' => UploadedFile::fake()->createWithContent('mint-theme-1.0.0.json', '{"hello": "world"}'),
            ]);

        $version
            ->assertStatus(201)
            ->assertJsonPath('data.version', '1.0.0')
            ->assertJsonPath('data.status', 'published');

        $list = $this
            ->withHeader('Authorization', 'Bearer '.$jwt)
            ->getJson('/api/v1/me/assets');

        $list
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'mint-theme')
            ->assertJsonPath('data.0.versions.0.version', '1.0.0');

        $publicDetail = $this->getJson('/api/v1/assets/mint-theme');

        $publicDetail
            ->assertStatus(200)
            ->assertJsonPath('data.versions.0.version', '1.0.0');
    }

    private array $jwksPayload = [];

    private function fakeAuthentik(): array
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

        return [$jwt, $jwksUri];
    }

    private function makeJwtAndJwks(string $issuer, string $audience): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        $details = openssl_pkey_get_details($privateKey);

        $kid = 'test-kid-catalog';
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
            'email' => 'manager@example.com',
            'name' => 'Manager Example',
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
