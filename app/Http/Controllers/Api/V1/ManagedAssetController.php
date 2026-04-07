<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ScanExtensionJob;
use App\Models\Asset;
use App\Models\AssetSubmission;
use App\Models\AssetVersion;
use App\Services\InputSanitizationException;
use App\Services\InputSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ManagedAssetController extends Controller
{
    public function __construct(
        private readonly InputSanitizer $sanitizer,
    ) {}
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $assets = Asset::query()
            ->where('owner_user_id', $user->id)
            ->with(['versions' => fn ($query) => $query->orderByDesc('id')])
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => collect($assets->items())->map(fn (Asset $asset): array => $this->transformManagedAsset($asset))->values(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    public function show(Request $request, Asset $asset): JsonResponse
    {
        $this->abortIfNotOwner($request, $asset);

        $asset->load(['versions' => fn ($query) => $query->orderByDesc('id')]);

        return response()->json([
            'data' => $this->transformManagedAsset($asset),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:wallpaper,theme,widget,animation,collection,midori-update'],
            'slug' => ['required', 'string', 'max:180', 'alpha_dash', 'unique:assets,slug'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'author' => ['nullable', 'string', 'max:180'],
            'license' => ['nullable', 'string', 'max:128'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'status' => ['nullable', 'string', 'in:draft,published'],
        ]);

        try {
            $validated = $this->sanitizer->sanitizeFields($validated, [
                'name' => 180,
                'description' => 5000,
                'author' => 180,
            ]);
        } catch (InputSanitizationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $status = $validated['status'] ?? 'draft';

        $asset = Asset::query()->create([
            'owner_user_id' => $user->id,
            'type' => $validated['type'],
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'author' => $validated['author'] ?? ($user->name ?: $user->email),
            'license' => $validated['license'] ?? null,
            'tags' => $validated['tags'] ?? [],
            'status' => $status,
            'approval_status' => 'pending',
            'published_at' => $status === 'published' ? now() : null,
        ]);

        if ($status === 'published') {
            AssetSubmission::query()->create([
                'asset_id' => $asset->id,
                'submitted_by' => $user->id,
                'status' => 'pending',
                'submitted_at' => now(),
            ]);
        }

        return response()->json([
            'data' => $this->transformManagedAsset($asset),
        ], 201);
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $this->abortIfNotOwner($request, $asset);

        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'in:wallpaper,theme,widget,animation,collection,midori-update'],
            'slug' => ['sometimes', 'string', 'max:180', 'alpha_dash', 'unique:assets,slug,'.$asset->id],
            'name' => ['sometimes', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'author' => ['nullable', 'string', 'max:180'],
            'license' => ['nullable', 'string', 'max:128'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'status' => ['sometimes', 'string', 'in:draft,published'],
        ]);

        try {
            $validated = $this->sanitizer->sanitizeFields($validated, [
                'name' => 180,
                'description' => 5000,
                'author' => 180,
            ]);
        } catch (InputSanitizationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (array_key_exists('status', $validated)) {
            $validated['published_at'] = $validated['status'] === 'published'
                ? ($asset->published_at ?? now())
                : null;

            if ($validated['status'] === 'published' && $asset->status !== 'published') {
                $validated['approval_status'] = 'pending';

                AssetSubmission::query()->create([
                    'asset_id' => $asset->id,
                    'submitted_by' => $request->user()->id,
                    'status' => 'pending',
                    'submitted_at' => now(),
                ]);
            }
        }

        $asset->fill($validated);
        $asset->save();
        $asset->load(['versions' => fn ($query) => $query->orderByDesc('id')]);

        return response()->json([
            'data' => $this->transformManagedAsset($asset),
        ]);
    }

    public function storeVersion(Request $request, Asset $asset): JsonResponse
    {
        $this->abortIfNotOwner($request, $asset);

        $validated = $request->validate([
            'version' => ['required', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'in:draft,published'],
            'min_app_version' => ['nullable', 'string', 'max:32'],
            'max_app_version' => ['nullable', 'string', 'max:32'],
            'browsers' => ['nullable', 'array'],
            'browsers.*' => ['string', 'max:32'],
            'manifest' => ['required', 'array'],
            'file' => ['nullable', 'file', 'max:51200'],
            'file_path' => ['nullable', 'string', 'max:1024'],
            'file_disk' => ['nullable', 'string', 'max:64'],
            'checksum' => ['nullable', 'string', 'max:128'],
        ]);

        $uploaded = $request->file('file');
        $diskName = $validated['file_disk'] ?? 'local';

        [$filePath, $checksum, $sizeBytes] = $this->resolveVersionFileData($asset, $validated, $uploaded, $diskName);

        $status = $validated['status'] ?? ($asset->status === 'published' ? 'published' : 'draft');

        $version = AssetVersion::query()->updateOrCreate(
            [
                'asset_id' => $asset->id,
                'version' => $validated['version'],
            ],
            [
                'status' => $status,
                'min_app_version' => $validated['min_app_version'] ?? null,
                'max_app_version' => $validated['max_app_version'] ?? null,
                'browsers' => $validated['browsers'] ?? ['chrome', 'firefox', 'midori'],
                'manifest' => $validated['manifest'],
                'file_disk' => $diskName,
                'file_path' => $filePath,
                'checksum' => $checksum,
                'size_bytes' => $sizeBytes,
                'scan_status' => 'pending',
                'published_at' => $status === 'published' ? now() : null,
            ],
        );

        ScanExtensionJob::dispatch($version->id);

        return response()->json([
            'data' => $this->transformVersion($version),
        ], 201);
    }

    private function abortIfNotOwner(Request $request, Asset $asset): void
    {
        $userId = (int) $request->user()->id;

        if ((int) $asset->owner_user_id !== $userId) {
            abort(403, 'Forbidden.');
        }
    }

    private function resolveVersionFileData(Asset $asset, array $validated, ?UploadedFile $uploaded, string $diskName): array
    {
        if (! $uploaded instanceof UploadedFile && ! isset($validated['file_path'])) {
            abort(422, 'Either file or file_path is required.');
        }

        $disk = Storage::disk($diskName);

        if ($uploaded instanceof UploadedFile) {
            $extension = $uploaded->getClientOriginalExtension();
            $suffix = $extension !== '' ? '.'.$extension : '';
            $storedPath = $disk->putFileAs(
                'assets/'.$asset->id.'/versions',
                $uploaded,
                $validated['version'].'-'.Str::random(8).$suffix,
            );

            $sizeBytes = $disk->size($storedPath);
            $checksum = hash_file('sha256', $uploaded->getRealPath());

            return [$storedPath, $checksum, $sizeBytes];
        }

        $filePath = $validated['file_path'];
        $checksum = $validated['checksum'] ?? null;
        $sizeBytes = $disk->exists($filePath) ? $disk->size($filePath) : null;

        return [$filePath, $checksum, $sizeBytes];
    }

    private function transformManagedAsset(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'owner_user_id' => $asset->owner_user_id,
            'type' => $asset->type,
            'slug' => $asset->slug,
            'name' => $asset->name,
            'description' => $asset->description,
            'author' => $asset->author,
            'license' => $asset->license,
            'tags' => $asset->tags ?? [],
            'status' => $asset->status,
            'published_at' => $asset->published_at?->toISOString(),
            'versions' => $asset->relationLoaded('versions')
                ? $asset->versions->map(fn (AssetVersion $version): array => $this->transformVersion($version))->values()
                : [],
            'created_at' => $asset->created_at?->toISOString(),
            'updated_at' => $asset->updated_at?->toISOString(),
        ];
    }

    private function transformVersion(AssetVersion $version): array
    {
        return [
            'id' => $version->id,
            'asset_id' => $version->asset_id,
            'version' => $version->version,
            'status' => $version->status,
            'min_app_version' => $version->min_app_version,
            'max_app_version' => $version->max_app_version,
            'browsers' => $version->browsers ?? [],
            'manifest' => $version->manifest,
            'file_disk' => $version->file_disk,
            'file_path' => $version->file_path,
            'checksum' => $version->checksum,
            'size_bytes' => $version->size_bytes,
            'published_at' => $version->published_at?->toISOString(),
            'created_at' => $version->created_at?->toISOString(),
            'updated_at' => $version->updated_at?->toISOString(),
        ];
    }
}
