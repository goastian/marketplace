<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetSubmission;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'total_assets' => Asset::count(),
            'published_assets' => Asset::where('status', 'published')->where('approval_status', 'approved')->count(),
            'pending_approval' => Asset::where('approval_status', 'pending')->count(),
            'draft_assets' => Asset::where('status', 'draft')->count(),
            'official_assets' => Asset::where('is_official', true)->count(),
            'pending_submissions' => AssetSubmission::where('status', 'pending')->count(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Asset::query()
            ->with(['versions', 'owner'])
            ->orderByDesc('id');

        $type = $request->query('type');
        if (is_string($type) && $type !== '') {
            $query->where('type', $type);
        }

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $approval = $request->query('approval_status');
        if (is_string($approval) && $approval !== '') {
            $query->where('approval_status', $approval);
        }

        $search = $request->query('q');
        if (is_string($search) && $search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%');
            });
        }

        $perPage = max(1, min((int) $request->integer('per_page', 20), 100));
        $assets = $query->paginate($perPage);

        return response()->json([
            'data' => collect($assets->items())->map(fn (Asset $a) => $this->transform($a))->values(),
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
        $asset->load(['versions', 'owner']);

        return response()->json(['data' => $this->transform($asset)]);
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
            'is_official' => true,
            'approval_status' => 'approved',
            'published_at' => $status === 'published' ? now() : null,
        ]);

        AuditLog::record('admin.asset.create', $user->id, 'Asset', $asset->id, null, $request->ip());

        return response()->json(['data' => $this->transform($asset)], 201);
    }

    public function approve(Request $request, Asset $asset): JsonResponse
    {
        $asset->update(['approval_status' => 'approved']);

        $submission = AssetSubmission::where('asset_id', $asset->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($submission) {
            $submission->update([
                'status' => 'approved',
                'reviewer_id' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        }

        AuditLog::record('admin.asset.approve', $request->user()->id, 'Asset', $asset->id, null, $request->ip());

        return response()->json(['data' => $this->transform($asset->fresh())]);
    }

    public function reject(Request $request, Asset $asset): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $asset->update(['approval_status' => 'rejected']);

        $submission = AssetSubmission::where('asset_id', $asset->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($submission) {
            $submission->update([
                'status' => 'rejected',
                'reviewer_id' => $request->user()->id,
                'reviewer_notes' => $validated['notes'] ?? null,
                'reviewed_at' => now(),
            ]);
        }

        AuditLog::record('admin.asset.reject', $request->user()->id, 'Asset', $asset->id, ['notes' => $validated['notes'] ?? null], $request->ip());

        return response()->json(['data' => $this->transform($asset->fresh())]);
    }

    public function requestRevision(Request $request, Asset $asset): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        $asset->update(['approval_status' => 'pending']);

        $submission = AssetSubmission::where('asset_id', $asset->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($submission) {
            $submission->update([
                'status' => 'revision_requested',
                'reviewer_id' => $request->user()->id,
                'reviewer_notes' => $validated['notes'],
                'reviewed_at' => now(),
            ]);
        }

        AuditLog::record('admin.asset.request_revision', $request->user()->id, 'Asset', $asset->id, ['notes' => $validated['notes']], $request->ip());

        return response()->json(['data' => $this->transform($asset->fresh())]);
    }

    private function transform(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'owner_user_id' => $asset->owner_user_id,
            'owner' => $asset->relationLoaded('owner') && $asset->owner ? [
                'id' => $asset->owner->id,
                'name' => $asset->owner->name,
                'email' => $asset->owner->email,
            ] : null,
            'type' => $asset->type,
            'slug' => $asset->slug,
            'name' => $asset->name,
            'description' => $asset->description,
            'author' => $asset->author,
            'license' => $asset->license,
            'tags' => $asset->tags ?? [],
            'status' => $asset->status,
            'is_official' => (bool) $asset->is_official,
            'approval_status' => $asset->approval_status,
            'published_at' => $asset->published_at?->toISOString(),
            'versions' => $asset->relationLoaded('versions')
                ? $asset->versions->map(fn ($v) => [
                    'id' => $v->id,
                    'version' => $v->version,
                    'status' => $v->status,
                    'scan_status' => $v->scan_status,
                    'published_at' => $v->published_at?->toISOString(),
                ])->values()
                : [],
            'created_at' => $asset->created_at?->toISOString(),
            'updated_at' => $asset->updated_at?->toISOString(),
        ];
    }
}
