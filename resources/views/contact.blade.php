@extends('layouts.app')

@section('title', __('messages.contact') . ' · Midori Marketplace')
@section('meta_description', __('messages.contact_description'))

@section('content')
<div class="max-w-2xl mx-auto mt-12 px-4">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ __('messages.contact') }}</h1>

    <div id="contact-success" class="hidden bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 text-sm">
        {{ __('messages.contact_success') }}
    </div>

    <form id="contact-form" class="bg-white rounded-2xl shadow-lg p-8 space-y-6" method="POST" action="{{ route('contact.store', ['locale' => app()->getLocale()]) }}">
        @csrf
        <input type="hidden" name="recaptcha_token" id="recaptcha_token">

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.name') }}</label>
            <input type="text" name="name" id="name" required maxlength="180"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-teal-500 focus:border-teal-500">
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.email') }}</label>
            <input type="email" name="email" id="email" required maxlength="255"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-teal-500 focus:border-teal-500">
        </div>

        <div>
            <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.subject') }}</label>
            <input type="text" name="subject" id="subject" required maxlength="255"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-teal-500 focus:border-teal-500">
        </div>

        <div>
            <label for="message" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.message') }}</label>
            <textarea name="message" id="message" rows="5" required maxlength="3000"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-teal-500 focus:border-teal-500"></textarea>
        </div>

        <button type="submit"
                class="w-full px-6 py-3 bg-teal-600 text-white font-semibold rounded-lg hover:bg-teal-700 transition-colors">
            {{ __('messages.send') }}
        </button>
    </form>
</div>

@push('head')
@if(config('services.recaptcha.site_key'))
<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
@endif
@endpush

@push('scripts')
<script>
document.getElementById('contact-form')?.addEventListener('submit', function(e) {
    const siteKey = '{{ config("services.recaptcha.site_key", "") }}';
    if (!siteKey) return;
    e.preventDefault();
    grecaptcha.ready(function() {
        grecaptcha.execute(siteKey, {action: 'contact'}).then(function(token) {
            document.getElementById('recaptcha_token').value = token;
            e.target.submit();
        });
    });
});
</script>
@endpush
@endsection
