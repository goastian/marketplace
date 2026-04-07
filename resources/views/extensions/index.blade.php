@extends('layouts.app')

@section('title', __('messages.extensions') . ' · Midori Marketplace')
@section('meta_description', __('messages.extensions_description'))

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ __('messages.extensions') }}</h1>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-3 mb-8">
        <a href="{{ route('extensions.index', ['locale' => app()->getLocale()]) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium {{ !$currentType ? 'bg-teal-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            {{ __('messages.all') }}
        </a>
        @foreach(['theme', 'wallpaper', 'widget', 'animation'] as $type)
            <a href="{{ route('extensions.index', ['locale' => app()->getLocale(), 'type' => $type]) }}"
               class="px-4 py-2 rounded-lg text-sm font-medium {{ $currentType === $type ? 'bg-teal-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                {{ __("messages.type_{$type}") }}
            </a>
        @endforeach
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('extensions.index', ['locale' => app()->getLocale()]) }}" class="mb-8">
        @if($currentType)
            <input type="hidden" name="type" value="{{ $currentType }}">
        @endif
        <div class="flex">
            <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="{{ __('messages.search_placeholder') }}"
                   class="flex-1 border border-gray-300 rounded-l-lg px-4 py-2 focus:ring-teal-500 focus:border-teal-500">
            <button type="submit" class="px-6 py-2 bg-teal-600 text-white rounded-r-lg hover:bg-teal-700 font-medium">
                {{ __('messages.search') }}
            </button>
        </div>
    </form>

    {{-- Grid --}}
    @if($assets->isEmpty())
        <p class="text-gray-500 text-center py-12">{{ __('messages.no_extensions') }}</p>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($assets as $asset)
                <a href="{{ route('extensions.show', ['locale' => app()->getLocale(), 'slug' => $asset->slug]) }}"
                   class="bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow p-5 flex flex-col">
                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full mb-3
                        {{ $asset->type === 'theme' ? 'bg-teal-100 text-teal-700' : '' }}
                        {{ $asset->type === 'wallpaper' ? 'bg-blue-100 text-blue-700' : '' }}
                        {{ $asset->type === 'widget' ? 'bg-cyan-100 text-cyan-700' : '' }}
                        {{ $asset->type === 'animation' ? 'bg-emerald-100 text-emerald-700' : '' }}
                        w-fit">
                        {{ __("messages.type_{$asset->type}") }}
                    </span>
                    <h3 class="font-semibold text-gray-900 mb-1">{{ $asset->name }}</h3>
                    <p class="text-sm text-gray-500 mb-3 line-clamp-2 flex-1">{{ Str::limit($asset->description, 120) }}</p>
                    <div class="text-xs text-gray-400">
                        {{ $asset->author }} &middot; {{ $asset->publishedVersions->first()?->version ?? '' }}
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $assets->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
