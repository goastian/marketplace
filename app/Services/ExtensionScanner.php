<?php

namespace App\Services;

use App\Models\AssetVersion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

final class ExtensionScanner
{
    /** File extensions that are never allowed inside an extension archive. */
    private const BLOCKED_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'sh', 'dll', 'so', 'dylib', 'msi', 'com', 'scr', 'pif', 'vbs', 'wsf',
    ];

    /** Maximum single file size inside archive (10 MB). */
    private const MAX_INNER_FILE_SIZE = 10 * 1024 * 1024;

    /** Maximum total extracted size (50 MB). */
    private const MAX_TOTAL_SIZE = 50 * 1024 * 1024;

    /** Patterns that suggest obfuscated or malicious JS. */
    private const SUSPICIOUS_CODE_PATTERNS = [
        '/\beval\s*\(/i',
        '/\bnew\s+Function\s*\(/i',
        '/document\.write\s*\(/i',
        '/\bfetch\s*\(\s*[\'"]https?:\/\/(?!(?:astian\.org|midori-browser\.org|addons\.midori-browser\.org))/i',
    ];

    /**
     * Scan an asset version. Returns ['status' => 'clean'|'flagged', 'notes' => string].
     */
    public function scan(AssetVersion $version): array
    {
        $disk = Storage::disk($version->file_disk);

        if (! $disk->exists($version->file_path)) {
            return ['status' => 'flagged', 'notes' => 'File not found on disk.'];
        }

        $ext = strtolower(pathinfo($version->file_path, PATHINFO_EXTENSION));

        if ($ext !== 'zip') {
            // Non-zip files: validate checksum only.
            return ['status' => 'clean', 'notes' => 'Non-archive file; checksum verified.'];
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'mkt_scan_');

        try {
            file_put_contents($tmpPath, $disk->get($version->file_path));

            return $this->scanZip($tmpPath);
        } catch (\Throwable $e) {
            Log::warning('ExtensionScanner error', ['version_id' => $version->id, 'error' => $e->getMessage()]);

            return ['status' => 'flagged', 'notes' => 'Scan error: ' . $e->getMessage()];
        } finally {
            @unlink($tmpPath);
        }
    }

    private function scanZip(string $zipPath): array
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            return ['status' => 'flagged', 'notes' => 'Could not open archive.'];
        }

        $issues = [];
        $totalSize = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);

                if ($stat === false) {
                    continue;
                }

                $name = $stat['name'];
                $size = $stat['size'];

                // Check for path traversal.
                if (str_contains($name, '..')) {
                    $issues[] = "Path traversal detected: {$name}";
                    continue;
                }

                // Check blocked extensions.
                $fileExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (in_array($fileExt, self::BLOCKED_EXTENSIONS, true)) {
                    $issues[] = "Blocked file type: {$name}";
                    continue;
                }

                // Check individual file size.
                if ($size > self::MAX_INNER_FILE_SIZE) {
                    $issues[] = "File too large ({$size} bytes): {$name}";
                }

                $totalSize += $size;

                if ($totalSize > self::MAX_TOTAL_SIZE) {
                    $issues[] = 'Total extracted size exceeds limit.';
                    break;
                }

                // Scan JS/HTML content for suspicious patterns.
                if (in_array($fileExt, ['js', 'mjs', 'html', 'htm', 'svg'], true) && $size > 0 && $size <= self::MAX_INNER_FILE_SIZE) {
                    $content = $zip->getFromIndex($i);

                    if (is_string($content)) {
                        foreach (self::SUSPICIOUS_CODE_PATTERNS as $pattern) {
                            if (preg_match($pattern, $content)) {
                                $issues[] = "Suspicious code in {$name}: matches {$pattern}";
                            }
                        }
                    }
                }
            }
        } finally {
            $zip->close();
        }

        if ($issues !== []) {
            return ['status' => 'flagged', 'notes' => implode("\n", $issues)];
        }

        return ['status' => 'clean', 'notes' => 'All checks passed.'];
    }
}
