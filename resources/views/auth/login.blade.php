@extends('layouts.app')

@section('title', __('messages.login') . ' · Midori Marketplace')
@section('meta_description', __('messages.login_description'))

@section('content')
<div class="max-w-md mx-auto mt-24 px-4">
    <div class="bg-white rounded-2xl shadow-lg p-8 text-center">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ __('messages.login_title') }}</h1>
        <p class="text-gray-500 mb-8">{{ __('messages.login_subtitle') }}</p>

        <a href="{{ route('login') }}"
           class="inline-flex items-center justify-center w-full px-6 py-3 bg-teal-600 text-white font-semibold rounded-lg hover:bg-teal-700 transition-colors">
            {{ __('messages.login_with_astian') }}
        </a>

        <p class="mt-6 text-sm text-gray-400">
            {{ __('messages.no_account') }}
            <a href="{{ config('authentik-oidc.authorize_url') }}?{{ http_build_query(['client_id' => config('authentik-oidc.client_id'), 'response_type' => 'code', 'scope' => 'openid email profile', 'redirect_uri' => url(config('authentik-oidc.redirect_uri'))]) }}"
               class="text-teal-600 hover:underline">
                {{ __('messages.create_account') }}
            </a>
        </p>
    </div>
</div>
@endsection
