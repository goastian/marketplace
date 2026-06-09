<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiV1AdminCatalogDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_catalog_diagnostics(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Asset::query()->create([
            'type' => 'wallpaper',
            'slug' => 'alpha-wallpaper',
            'name' => 'Alpha Wallpaper',
            'status' => 'published',
            'approval_status' => 'approved',
            'published_at' => now(),
        ]);

        Asset::query()->create([
            'type' => 'wallpaper',
            'slug' => 'beta-wallpaper',
            'name' => 'Beta Wallpaper',
            'status' => 'published',
            'approval_status' => 'pending',
            'published_at' => now(),
        ]);

        Asset::query()->create([
            'type' => 'theme',
            'slug' => 'gamma-theme',
            'name' => 'Gamma Theme',
            'status' => 'draft',
            'approval_status' => 'pending',
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson('/api/v1/admin/catalog-diagnostics?type=wallpaper');

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.has_approval_status_column', true)
            ->assertJsonPath('data.totals.all_assets_count', 3)
            ->assertJsonPath('data.totals.published_count', 2)
            ->assertJsonPath('data.totals.approved_published_count', 1)
            ->assertJsonPath('data.requested_type_diagnostics.requested_type', 'wallpaper')
            ->assertJsonPath('data.requested_type_diagnostics.type_any_status_count', 2)
            ->assertJsonPath('data.requested_type_diagnostics.type_published_count', 2)
            ->assertJsonPath('data.requested_type_diagnostics.type_approved_published_count', 1)
            ->assertJsonPath('data.catalog_query.total', 1)
            ->assertJsonPath('data.catalog_query.sample.0.slug', 'alpha-wallpaper');
    }

    public function test_non_admin_cannot_fetch_catalog_diagnostics(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        $this
            ->actingAs($user)
            ->getJson('/api/v1/admin/catalog-diagnostics')
            ->assertStatus(403);
    }
}
