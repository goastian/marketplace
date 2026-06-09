<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovePublishedAssets extends Command
{
    protected $signature = 'marketplace:approve-published-assets
                            {--all : Approve all published pending assets, not only admin-owned}
                            {--dry-run : Show how many assets would be approved without writing changes}';

    protected $description = 'Approve published marketplace assets that are still pending approval';

    public function handle(): int
    {
        if (! Schema::hasColumn('assets', 'approval_status')) {
            $this->warn('Skipped: column assets.approval_status does not exist in this environment.');

            return self::SUCCESS;
        }

        $query = Asset::query()
            ->where('status', 'published')
            ->where('approval_status', 'pending');

        if (! $this->option('all')) {
            $query->whereExists(function ($subQuery): void {
                $subQuery->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'assets.owner_user_id')
                    ->where('users.role', 'admin');
            });
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No published pending assets found for the selected scope.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $scope = $this->option('all') ? 'all owners' : 'admin owners only';
            $this->info("Dry-run: {$count} assets would be approved ({$scope}).");

            return self::SUCCESS;
        }

        $updated = $query->update([
            'approval_status' => 'approved',
            'updated_at' => now(),
        ]);

        $scope = $this->option('all') ? 'all owners' : 'admin owners only';
        $this->info("Approved {$updated} published assets ({$scope}).");

        return self::SUCCESS;
    }
}
