<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MarketplaceApprovePublishedAssetsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_approves_only_admin_owned_assets_by_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $developer = User::factory()->create(['role' => 'developer']);

        $adminAsset = Asset::query()->create([
            'owner_user_id' => $admin->id,
            'type' => 'wallpaper',
            'slug' => 'admin-wallpaper',
            'name' => 'Admin Wallpaper',
            'status' => 'published',
            'approval_status' => 'pending',
            'published_at' => now(),
        ]);

        $developerAsset = Asset::query()->create([
            'owner_user_id' => $developer->id,
            'type' => 'theme',
            'slug' => 'developer-theme',
            'name' => 'Developer Theme',
            'status' => 'published',
            'approval_status' => 'pending',
            'published_at' => now(),
        ]);

        $this->artisan('marketplace:approve-published-assets')
            ->expectsOutput('Approved 1 published assets (admin owners only).')
            ->assertExitCode(0);

        $this->assertSame('approved', $adminAsset->fresh()->approval_status);
        $this->assertSame('pending', $developerAsset->fresh()->approval_status);
    }

    public function test_command_can_approve_all_with_flag(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $developer = User::factory()->create(['role' => 'developer']);

        $adminAsset = Asset::query()->create([
            'owner_user_id' => $admin->id,
            'type' => 'wallpaper',
            'slug' => 'admin-wallpaper-all',
            'name' => 'Admin Wallpaper All',
            'status' => 'published',
            'approval_status' => 'pending',
            'published_at' => now(),
        ]);

        $developerAsset = Asset::query()->create([
            'owner_user_id' => $developer->id,
            'type' => 'theme',
            'slug' => 'developer-theme-all',
            'name' => 'Developer Theme All',
            'status' => 'published',
            'approval_status' => 'pending',
            'published_at' => now(),
        ]);

        $this->artisan('marketplace:approve-published-assets --all')
            ->expectsOutput('Approved 2 published assets (all owners).')
            ->assertExitCode(0);

        $this->assertSame('approved', $adminAsset->fresh()->approval_status);
        $this->assertSame('approved', $developerAsset->fresh()->approval_status);
    }
}
