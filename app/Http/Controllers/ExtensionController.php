<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetReview;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ExtensionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Asset::query()
            ->where('status', 'published')
            ->where('approval_status', 'approved')
            ->with('publishedVersions')
            ->orderByDesc('published_at');

        $type = $request->query('type');
        if (is_string($type) && $type !== '') {
            $query->where('type', $type);
        }

        $search = $request->query('q');
        if (is_string($search) && $search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $assets = $query->paginate(24);

        return view('extensions.index', [
            'assets' => $assets,
            'currentType' => $type,
            'search' => $search,
        ]);
    }

    public function show(string $slug): View
    {
        $asset = Asset::where('slug', $slug)
            ->where('status', 'published')
            ->where('approval_status', 'approved')
            ->with('publishedVersions')
            ->firstOrFail();

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

        return view('extensions.show', [
            'asset' => $asset,
            'reviews' => $reviews,
            'avgRating' => $avgRating ? round((float) $avgRating, 1) : null,
            'reviewCount' => $reviewCount,
        ]);
    }
}
