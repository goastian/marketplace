<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class ExtensionController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->query('type');
        $search = $request->query('q');
        $hasApprovalStatusColumn = $this->hasApprovalStatusColumn();

        Log::info('marketplace.extensions.index.start', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'request_id' => $request->header('X-Request-Id'),
            'type' => $type,
            'search' => $search,
            'page' => $request->integer('page', 1),
            'has_approval_status_column' => $hasApprovalStatusColumn,
        ]);

        $query = Asset::query()
            ->where('status', 'published')
            ->with('publishedVersions')
            ->orderByDesc('published_at');

        if ($hasApprovalStatusColumn) {
            $query->where('approval_status', 'approved');
        }

        if (is_string($type) && $type !== '') {
            $query->where('type', $type);
        }

        if (is_string($search) && $search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $assets = $query->paginate(24);

        Log::info('marketplace.extensions.index.success', [
            'total' => $assets->total(),
            'current_page' => $assets->currentPage(),
            'last_page' => $assets->lastPage(),
            'returned_count' => count($assets->items()),
            'type' => $type,
            'search' => $search,
        ]);

        return view('extensions.index', [
            'assets' => $assets,
            'currentType' => $type,
            'search' => $search,
        ]);
    }

    public function show(string $slug): View
    {
        Log::info('marketplace.extensions.show.start', [
            'slug' => $slug,
            'path' => request()->path(),
            'method' => request()->method(),
            'ip' => request()->ip(),
            'request_id' => request()->header('X-Request-Id'),
        ]);

        $assetQuery = Asset::where('slug', $slug)
            ->where('status', 'published')
            ->with('publishedVersions');

        if ($this->hasApprovalStatusColumn()) {
            $assetQuery->where('approval_status', 'approved');
        }

        $asset = $assetQuery->firstOrFail();

        $reviews = AssetReview::where('asset_id', $asset->id)
            ->where('status', 'approved')
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(10);

        $avgRating = AssetReview::where('asset_id', $asset->id)
            ->where('status', 'approved')
            ->avg('rating');

        $reviewCount = AssetReview::where('asset_id', $asset->id)
            ->where('status', 'approved')
            ->count();

        Log::info('marketplace.extensions.show.success', [
            'slug' => $slug,
            'asset_id' => $asset->id,
            'asset_type' => $asset->type,
            'versions_count' => $asset->publishedVersions->count(),
            'reviews_count' => $reviewCount,
        ]);

        return view('extensions.show', [
            'asset' => $asset,
            'reviews' => $reviews,
            'avgRating' => $avgRating ? round((float) $avgRating, 1) : null,
            'reviewCount' => $reviewCount,
        ]);
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
