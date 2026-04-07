@extends('layouts.app')

@section('title', __('messages.docs') . ' · Midori Marketplace')
@section('meta_description', __('messages.docs_description'))

@section('content')
<div class="max-w-4xl mx-auto mt-12 px-4 pb-16">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('messages.docs') }}</h1>
    <p class="text-gray-500 mb-10">{{ __('messages.docs_description') }}</p>

    {{-- Users Section --}}
    <section class="mb-12">
        <h2 class="text-2xl font-bold text-teal-700 mb-6 flex items-center gap-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            {{ __('messages.docs_for_users') }}
        </h2>

        <div class="grid gap-4 md:grid-cols-2">
            {{-- Install Themes --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('messages.docs_install_themes') }}</h3>
                <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
                    <li>{{ __('messages.docs_install_themes_step1') }}</li>
                    <li>{{ __('messages.docs_install_themes_step2') }}</li>
                    <li>{{ __('messages.docs_install_themes_step3') }}</li>
                    <li>{{ __('messages.docs_install_themes_step4') }}</li>
                </ol>
            </div>

            {{-- Install Wallpapers --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('messages.docs_install_wallpapers') }}</h3>
                <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
                    <li>{{ __('messages.docs_install_wallpapers_step1') }}</li>
                    <li>{{ __('messages.docs_install_wallpapers_step2') }}</li>
                    <li>{{ __('messages.docs_install_wallpapers_step3') }}</li>
                    <li>{{ __('messages.docs_install_wallpapers_step4') }}</li>
                </ol>
            </div>

            {{-- Install Widgets --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('messages.docs_install_widgets') }}</h3>
                <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
                    <li>{{ __('messages.docs_install_widgets_step1') }}</li>
                    <li>{{ __('messages.docs_install_widgets_step2') }}</li>
                    <li>{{ __('messages.docs_install_widgets_step3') }}</li>
                    <li>{{ __('messages.docs_install_widgets_step4') }}</li>
                </ol>
            </div>

            {{-- Install Animations --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('messages.docs_install_animations') }}</h3>
                <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
                    <li>{{ __('messages.docs_install_animations_step1') }}</li>
                    <li>{{ __('messages.docs_install_animations_step2') }}</li>
                    <li>{{ __('messages.docs_install_animations_step3') }}</li>
                </ol>
            </div>

            {{-- Manage Extensions --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 md:col-span-2">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('messages.docs_manage_extensions') }}</h3>
                <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                    <li>{{ __('messages.docs_manage_extensions_tip1') }}</li>
                    <li>{{ __('messages.docs_manage_extensions_tip2') }}</li>
                    <li>{{ __('messages.docs_manage_extensions_tip3') }}</li>
                </ul>
            </div>
        </div>
    </section>

    {{-- Developers Section --}}
    <section>
        <h2 class="text-2xl font-bold text-teal-700 mb-6 flex items-center gap-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
            {{ __('messages.docs_for_developers') }}
        </h2>

        {{-- Asset Contract Overview --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('messages.docs_asset_contract') }}</h3>
            <p class="text-sm text-gray-600 mb-3">{{ __('messages.docs_asset_contract_desc') }}</p>
            <div class="bg-gray-50 rounded-lg p-4 text-xs font-mono text-gray-700 overflow-x-auto">
<pre>{
  "schemaVersion": "1.0.0",
  "kind": "midori-asset",
  "type": "theme | wallpaper | widget | animation",
  "id": "my-awesome-extension",
  "slug": "my-awesome-extension",
  "name": "My Awesome Extension",
  "status": "published",
  "compatibility": { ... },
  "distribution": { ... },
  "payload": { ... }
}</pre>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            {{-- Create a Theme --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('messages.docs_create_theme') }}</h3>
                <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
                    <li>{{ __('messages.docs_create_theme_step1') }}</li>
                    <li>{{ __('messages.docs_create_theme_step2') }}</li>
                    <li>{{ __('messages.docs_create_theme_step3') }}</li>
                    <li>{{ __('messages.docs_create_theme_step4') }}</li>
                    <li>{{ __('messages.docs_create_theme_step5') }}</li>
                </ol>
            </div>

            {{-- Create a Wallpaper --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('messages.docs_create_wallpaper') }}</h3>
                <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
                    <li>{{ __('messages.docs_create_wallpaper_step1') }}</li>
                    <li>{{ __('messages.docs_create_wallpaper_step2') }}</li>
                    <li>{{ __('messages.docs_create_wallpaper_step3') }}</li>
                    <li>{{ __('messages.docs_create_wallpaper_step4') }}</li>
                    <li>{{ __('messages.docs_create_wallpaper_step5') }}</li>
                </ol>
            </div>

            {{-- Create a Widget --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('messages.docs_create_widget') }}</h3>
                <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
                    <li>{{ __('messages.docs_create_widget_step1') }}</li>
                    <li>{{ __('messages.docs_create_widget_step2') }}</li>
                    <li>{{ __('messages.docs_create_widget_step3') }}</li>
                    <li>{{ __('messages.docs_create_widget_step4') }}</li>
                    <li>{{ __('messages.docs_create_widget_step5') }}</li>
                </ol>
            </div>

            {{-- Create an Animation --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('messages.docs_create_animation') }}</h3>
                <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
                    <li>{{ __('messages.docs_create_animation_step1') }}</li>
                    <li>{{ __('messages.docs_create_animation_step2') }}</li>
                    <li>{{ __('messages.docs_create_animation_step3') }}</li>
                    <li>{{ __('messages.docs_create_animation_step4') }}</li>
                </ol>
            </div>
        </div>

        {{-- Submit to Marketplace --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('messages.docs_submit_marketplace') }}</h3>
            <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
                <li>{{ __('messages.docs_submit_step1') }}</li>
                <li>{{ __('messages.docs_submit_step2') }}</li>
                <li>{{ __('messages.docs_submit_step3') }}</li>
                <li>{{ __('messages.docs_submit_step4') }}</li>
                <li>{{ __('messages.docs_submit_step5') }}</li>
            </ol>
        </div>

        {{-- Guidelines --}}
        <div class="bg-teal-50 rounded-xl border border-teal-100 p-6 mt-4">
            <h3 class="text-lg font-semibold text-teal-800 mb-2">{{ __('messages.docs_guidelines') }}</h3>
            <ul class="list-disc list-inside text-sm text-teal-700 space-y-1">
                <li>{{ __('messages.docs_guideline1') }}</li>
                <li>{{ __('messages.docs_guideline2') }}</li>
                <li>{{ __('messages.docs_guideline3') }}</li>
                <li>{{ __('messages.docs_guideline4') }}</li>
                <li>{{ __('messages.docs_guideline5') }}</li>
            </ul>
        </div>
    </section>
</div>
@endsection
