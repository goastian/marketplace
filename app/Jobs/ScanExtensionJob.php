<?php

namespace App\Jobs;

use App\Models\AssetVersion;
use App\Services\ExtensionScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScanExtensionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $assetVersionId,
    ) {}

    public function handle(ExtensionScanner $scanner): void
    {
        $version = AssetVersion::find($this->assetVersionId);

        if (! $version) {
            Log::warning('ScanExtensionJob: version not found', ['id' => $this->assetVersionId]);
            return;
        }

        $result = $scanner->scan($version);

        $version->update([
            'scan_status' => $result['status'],
            'scan_notes' => $result['notes'],
        ]);

        Log::info('ScanExtensionJob completed', [
            'version_id' => $version->id,
            'status' => $result['status'],
        ]);
    }
}
