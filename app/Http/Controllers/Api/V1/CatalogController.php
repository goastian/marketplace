<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

final class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $hasApprovalStatusColumn = $this->hasApprovalStatusColumn();

        Log::info('marketplace.catalog.index.start', $this->catalogContext($request) + [
            'type' => $request->query('type'),
            'search' => $request->query('q'),
            'tags' => $request->query('tags'),
            'page' => $request->integer('page', 1),
            'per_page' => $request->integer('per_page', 20),
            'has_approval_status_column' => $hasApprovalStatusColumn,
        ]);

        $query = Asset::query()
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if ($hasApprovalStatusColumn) {
            $query->where('approval_status', 'approved');
        }

        $type = $request->query('type');

        if (is_string($type) && $type !== '') {
            $query->where('type', $type);
        }

        $search = $request->query('q');

        if (is_string($search) && $search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%');
            });
        }

        $tags = $request->query('tags');

        if (is_string($tags) && $tags !== '') {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }

        if (is_array($tags) && $tags !== []) {
            $query->where(function ($inner) use ($tags) {
                foreach ($tags as $tag) {
                    if (is_string($tag) && $tag !== '') {
                        $inner->orWhere('tags', 'like', '%"'.$tag.'"%');
                    }
                }
            });
        }

        $perPage = max(1, min((int) $request->integer('per_page', 20), 50));
        $assets = $query->with(['publishedVersions'])->paginate($perPage);

        $assetItems = collect($assets->items());
        $typesInPage = $assetItems
            ->groupBy(fn (Asset $asset): string => (string) $asset->type)
            ->map(fn ($group): int => $group->count())
            ->toArray();

        Log::info('marketplace.catalog.index.success', $this->catalogContext($request) + [
            'total' => $assets->total(),
            'current_page' => $assets->currentPage(),
            'last_page' => $assets->lastPage(),
            'returned_count' => $assetItems->count(),
            'types_in_page' => $typesInPage,
        ]);

        return response()->json([
            'data' => $assetItems->map(fn (Asset $asset): array => $this->transformAssetSummary($asset))->values(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    public function show(Asset $asset): JsonResponse
    {
        Log::info('marketplace.catalog.show.start', [
            'asset_id' => $asset->id,
            'slug' => $asset->slug,
            'status' => $asset->status,
            'ip' => request()->ip(),
        ]);

        if ($asset->status !== 'published') {
            Log::warning('marketplace.catalog.show.not_published', [
                'asset_id' => $asset->id,
                'slug' => $asset->slug,
                'status' => $asset->status,
            ]);
            abort(404);
        }

        if ($this->hasApprovalStatusColumn() && $asset->approval_status !== 'approved') {
            Log::warning('marketplace.catalog.show.not_approved', [
                'asset_id' => $asset->id,
                'slug' => $asset->slug,
                'approval_status' => $asset->approval_status,
            ]);
            abort(404);
        }

        $asset->load(['publishedVersions']);

        Log::info('marketplace.catalog.show.success', [
            'asset_id' => $asset->id,
            'slug' => $asset->slug,
            'versions_count' => $asset->publishedVersions->count(),
        ]);

        return response()->json([
            'data' => $this->transformAssetDetail($asset),
        ]);
    }

    public function download(Request $request, Asset $asset): RedirectResponse
    {
        Log::info('marketplace.catalog.download.start', $this->catalogContext($request) + [
            'asset_id' => $asset->id,
            'slug' => $asset->slug,
            'requested_version' => $request->query('version'),
        ]);

        if ($asset->status !== 'published') {
            Log::warning('marketplace.catalog.download.not_published', [
                'asset_id' => $asset->id,
                'slug' => $asset->slug,
                'status' => $asset->status,
            ]);
            abort(404);
        }

        $version = $this->resolvePublishedVersion($request, $asset);

        if (! $version) {
            Log::warning('marketplace.catalog.download.version_not_found', [
                'asset_id' => $asset->id,
                'slug' => $asset->slug,
                'requested_version' => $request->query('version'),
            ]);
            abort(404);
        }

        $signedUrl = URL::temporarySignedRoute(
            'api.v1.assets.download.signed',
            now()->addMinutes(10),
            [
                'asset' => $asset->id,
                'assetVersion' => $version->id,
            ],
        );

        Log::info('marketplace.catalog.download.redirect_signed', [
            'asset_id' => $asset->id,
            'slug' => $asset->slug,
            'asset_version_id' => $version->id,
            'version' => $version->version,
        ]);

        return redirect()->to($signedUrl);
    }

    public function downloadSigned(Asset $asset, AssetVersion $assetVersion): Response
    {
        if ($asset->status !== 'published' || $assetVersion->status !== 'published') {
            Log::warning('marketplace.catalog.download_signed.invalid_status', [
                'asset_id' => $asset->id,
                'asset_status' => $asset->status,
                'asset_version_id' => $assetVersion->id,
                'asset_version_status' => $assetVersion->status,
            ]);
            abort(404);
        }

        if ($assetVersion->asset_id !== $asset->id) {
            Log::warning('marketplace.catalog.download_signed.asset_mismatch', [
                'asset_id' => $asset->id,
                'asset_version_id' => $assetVersion->id,
                'asset_version_asset_id' => $assetVersion->asset_id,
            ]);
            abort(404);
        }

        $disk = Storage::disk($assetVersion->file_disk);

        if (! $disk->exists($assetVersion->file_path)) {
            Log::warning('marketplace.catalog.download_signed.file_not_found', [
                'asset_id' => $asset->id,
                'asset_version_id' => $assetVersion->id,
                'disk' => $assetVersion->file_disk,
                'path' => $assetVersion->file_path,
            ]);
            abort(404, 'Asset file not found.');
        }

        $filename = basename($assetVersion->file_path);

        Log::info('marketplace.catalog.download_signed.success', [
            'asset_id' => $asset->id,
            'asset_version_id' => $assetVersion->id,
            'disk' => $assetVersion->file_disk,
            'path' => $assetVersion->file_path,
            'filename' => $filename,
        ]);

        return $disk->download($assetVersion->file_path, $filename, [
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function catalogContext(Request $request): array
    {
        return [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'request_id' => $request->header('X-Request-Id'),
        ];
    }

    private function resolvePublishedVersion(Request $request, Asset $asset): ?AssetVersion
    {
        $requestedVersion = $request->query('version');

        $query = $asset->versions()
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if (is_string($requestedVersion) && $requestedVersion !== '') {
            $query->where('version', $requestedVersion);
        }

        $resolved = $query->first();

        if (! $resolved instanceof AssetVersion) {
            return null;
        }

        return $resolved;
    }

    private function transformAssetSummary(Asset $asset): array
    {
        $latestVersion = $asset->publishedVersions->first();

        return [
            'id' => $asset->id,
            'slug' => $asset->slug,
            'type' => $asset->type,
            'name' => $asset->name,
            'description' => $asset->description,
            'author' => $asset->author,
            'license' => $asset->license,
            'tags' => $asset->tags ?? [],
            'status' => $asset->status,
            'published_at' => $asset->published_at?->toISOString(),
            'latest_version' => $latestVersion ? $this->transformVersion($latestVersion) : null,
        ];
    }

    private function transformAssetDetail(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'slug' => $asset->slug,
            'type' => $asset->type,
            'name' => $asset->name,
            'description' => $asset->description,
            'author' => $asset->author,
            'license' => $asset->license,
            'tags' => $asset->tags ?? [],
            'status' => $asset->status,
            'published_at' => $asset->published_at?->toISOString(),
            'versions' => $asset->publishedVersions->map(fn (AssetVersion $version): array => $this->transformVersion($version))->values(),
        ];
    }

    private function transformVersion(AssetVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'status' => $version->status,
            'min_app_version' => $version->min_app_version,
            'max_app_version' => $version->max_app_version,
            'browsers' => $version->browsers ?? [],
            'manifest' => $version->manifest,
            'checksum' => $version->checksum,
            'size_bytes' => $version->size_bytes,
            'published_at' => $version->published_at?->toISOString(),
        ];
    }

    private function hasApprovalStatusColumn(): bool
    {
        static $cached = null;

        if (is_bool($cached)) {
            return $cached;
        }

        $cached = Schema::hasColumn('assets', 'approval_status');

        return $cached;
    }
}
