<?php

namespace App\Console\Commands;

use App\Models\AssetVersion;
use App\Services\ExtensionScanner;
use Illuminate\Console\Command;

class ScanExtensions extends Command
{
    protected $signature = 'marketplace:scan-extensions {--id= : Scan a specific asset version ID} {--rescan : Re-scan already scanned versions}';

    protected $description = 'Scan extension archives for security issues';

    public function handle(ExtensionScanner $scanner): int
    {
        $id = $this->option('id');
        $rescan = $this->option('rescan');

        $query = AssetVersion::query();

        if ($id) {
            $query->where('id', $id);
        } elseif (! $rescan) {
            $query->where('scan_status', 'pending');
        }

        $versions = $query->get();

        if ($versions->isEmpty()) {
            $this->info('No versions to scan.');
            return self::SUCCESS;
        }

        $this->info("Scanning {$versions->count()} version(s)...");

        $flagged = 0;

        foreach ($versions as $version) {
            $result = $scanner->scan($version);

            $version->update([
                'scan_status' => $result['status'],
                'scan_notes' => $result['notes'],
            ]);

            $statusLabel = $result['status'] === 'flagged' ? '<fg=red>FLAGGED</>' : '<fg=green>clean</>';
            $this->line("  Version #{$version->id} ({$version->version}): {$statusLabel}");

            if ($result['status'] === 'flagged') {
                $flagged++;
                $this->line("    Notes: {$result['notes']}");
            }
        }

        $this->info("Done. {$flagged} flagged out of {$versions->count()}.");

        return $flagged > 0 ? self::FAILURE : self::SUCCESS;
    }
}
