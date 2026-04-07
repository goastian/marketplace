@extends('layouts.app')

@section('title', $asset->name . ' · Midori Marketplace')
@section('meta_description', Str::limit($asset->description, 160))
@section('og_title', $asset->name)
@section('og_description', Str::limit($asset->description, 160))
@section('og_type', 'product')

@section('json_ld')
{
    "@context": "https://schema.org",
    "@type": "SoftwareApplication",
    "name": "{{ e($asset->name) }}",
    "description": "{{ e(Str::limit($asset->description, 300)) }}",
    "author": {
        "@type": "Person",
        "name": "{{ e($asset->author) }}"
    },
    "applicationCategory": "BrowserExtension",
    "operatingSystem": "Windows, macOS, Linux",
    "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "USD"
    }
    @if($avgRating && $reviewCount > 0)
    ,
    "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "{{ $avgRating }}",
        "reviewCount": "{{ $reviewCount }}",
        "bestRating": "5",
        "worstRating": "1"
    }
    @endif
}
@endsection

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-400 mb-6">
        <a href="{{ route('extensions.index', ['locale' => app()->getLocale()]) }}" class="hover:text-teal-600">{{ __('messages.extensions') }}</a>
        <span class="mx-1">/</span>
        <a href="{{ route('extensions.index', ['locale' => app()->getLocale(), 'type' => $asset->type]) }}" class="hover:text-teal-600">{{ __("messages.type_{$asset->type}") }}</a>
        <span class="mx-1">/</span>
        <span class="text-gray-600">{{ $asset->name }}</span>
    </nav>

    {{-- Header --}}
    <div class="bg-white rounded-2xl shadow-lg p-8 mb-8">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full mb-3
                    {{ $asset->type === 'theme' ? 'bg-teal-100 text-teal-700' : '' }}
                    {{ $asset->type === 'wallpaper' ? 'bg-blue-100 text-blue-700' : '' }}
                    {{ $asset->type === 'widget' ? 'bg-cyan-100 text-cyan-700' : '' }}
                    {{ $asset->type === 'animation' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                    {{ __("messages.type_{$asset->type}") }}
                </span>
                @if($asset->is_official)
                    <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-700 mb-3">Official</span>
                @endif
                <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $asset->name }}</h1>
                <p class="text-gray-500 mb-4">{{ __('messages.by') }} {{ $asset->author }}</p>
            </div>

            @if($avgRating)
                <div class="text-center">
                    <div class="text-3xl font-bold text-amber-500">{{ $avgRating }}</div>
                    <div class="text-sm text-gray-400">{{ $reviewCount }} {{ __('messages.reviews') }}</div>
                </div>
            @endif
        </div>

        @if($asset->description)
            <div class="prose prose-gray max-w-none mt-4">
                {{ $asset->description }}
            </div>
        @endif

        @if($asset->tags && count($asset->tags) > 0)
            <div class="flex flex-wrap gap-2 mt-4">
                @foreach($asset->tags as $tag)
                    <span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded-full">{{ $tag }}</span>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Versions --}}
    @if($asset->publishedVersions->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-lg p-8 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">{{ __('messages.versions') }}</h2>
            <div class="space-y-3">
                @foreach($asset->publishedVersions as $version)
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                        <div>
                            <span class="font-mono font-semibold">{{ $version->version }}</span>
                            @if($version->browsers)
                                <span class="text-xs text-gray-400 ml-2">{{ implode(', ', $version->browsers) }}</span>
                            @endif
                        </div>
                        <span class="text-xs text-gray-400">{{ $version->published_at?->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Reviews --}}
    <div class="bg-white rounded-2xl shadow-lg p-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4">{{ __('messages.reviews') }}</h2>

        @if($reviews->isEmpty())
            <p class="text-gray-400">{{ __('messages.no_reviews') }}</p>
        @else
            <div class="space-y-6">
                @foreach($reviews as $review)
                    <div class="border-b border-gray-100 pb-4 last:border-0">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-medium text-gray-900">{{ $review->user?->name ?? 'Anonymous' }}</span>
                            <div class="flex items-center gap-1">
                                @for($i = 1; $i <= 5; $i++)
                                    <span class="{{ $i <= $review->rating ? 'text-amber-400' : 'text-gray-200' }}">&#9733;</span>
                                @endfor
                            </div>
                        </div>
                        @if($review->title)
                            <h4 class="font-semibold text-gray-800 text-sm">{{ $review->title }}</h4>
                        @endif
                        @if($review->body)
                            <p class="text-sm text-gray-600 mt-1">{{ $review->body }}</p>
                        @endif
                        <p class="text-xs text-gray-400 mt-2">{{ $review->created_at?->diffForHumans() }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $reviews->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
