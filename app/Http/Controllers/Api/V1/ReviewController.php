<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetReview;
use App\Services\InputSanitizationException;
use App\Services\InputSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReviewController extends Controller
{
    public function __construct(
        private readonly InputSanitizer $sanitizer,
    ) {}

    /**
     * List approved reviews for a public asset.
     */
    public function index(Asset $asset): JsonResponse
    {
        if ($asset->status !== 'published') {
            abort(404);
        }

        $reviews = AssetReview::query()
            ->where('asset_id', $asset->id)
            ->where('status', 'approved')
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(20);

        $avg = AssetReview::where('asset_id', $asset->id)
            ->where('status', 'approved')
            ->avg('rating');

        return response()->json([
            'data' => collect($reviews->items())->map(fn (AssetReview $r) => $this->transform($r))->values(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'average_rating' => $avg ? round((float) $avg, 2) : null,
            ],
        ]);
    }

    /**
     * Create a review for an asset (authenticated user).
     */
    public function store(Request $request, Asset $asset): JsonResponse
    {
        if ($asset->status !== 'published') {
            abort(404);
        }

        $user = $request->user();

        $existing = AssetReview::where('asset_id', $asset->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You have already reviewed this asset.'], 409);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $validated = $this->sanitizer->sanitizeFields($validated, [
                'title' => 255,
                'body' => 2000,
            ]);
        } catch (InputSanitizationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $review = AssetReview::query()->create([
            'asset_id' => $asset->id,
            'user_id' => $user->id,
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json(['data' => $this->transform($review)], 201);
    }

    /**
     * Update own review.
     */
    public function update(Request $request, AssetReview $review): JsonResponse
    {
        if ((int) $review->user_id !== (int) $request->user()->id) {
            abort(403, 'Forbidden.');
        }

        $validated = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $validated = $this->sanitizer->sanitizeFields($validated, [
                'title' => 255,
                'body' => 2000,
            ]);
        } catch (InputSanitizationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Re-set to pending after edit so admin can re-review.
        $validated['status'] = 'pending';

        $review->update($validated);

        return response()->json(['data' => $this->transform($review->fresh())]);
    }

    /**
     * Delete own review.
     */
    public function destroy(Request $request, AssetReview $review): JsonResponse
    {
        if ((int) $review->user_id !== (int) $request->user()->id) {
            abort(403, 'Forbidden.');
        }

        $review->delete();

        return response()->json(null, 204);
    }

    private function transform(AssetReview $review): array
    {
        return [
            'id' => $review->id,
            'asset_id' => $review->asset_id,
            'user' => $review->relationLoaded('user') && $review->user ? [
                'id' => $review->user->id,
                'name' => $review->user->name,
            ] : null,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'status' => $review->status,
            'created_at' => $review->created_at?->toISOString(),
            'updated_at' => $review->updated_at?->toISOString(),
        ];
    }
}
