<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Midori Marketplace'))</title>
    <meta name="description" content="@yield('meta_description', 'Discover themes, wallpapers, widgets and animations for Midori Browser.')">

    {{-- Open Graph --}}
    <meta property="og:title" content="@yield('og_title', config('app.name', 'Midori Marketplace'))">
    <meta property="og:description" content="@yield('og_description', 'Discover themes, wallpapers, widgets and animations for Midori Browser.')">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @endhasSection

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', config('app.name', 'Midori Marketplace'))">
    <meta name="twitter:description" content="@yield('og_description', 'Discover themes, wallpapers, widgets and animations for Midori Browser.')">

    {{-- Canonical --}}
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Hreflang --}}
    @php $currentPath = request()->path(); @endphp
    <link rel="alternate" hreflang="en" href="{{ url(preg_replace('#^(en|es)/#', 'en/', $currentPath)) }}">
    <link rel="alternate" hreflang="es" href="{{ url(preg_replace('#^(en|es)/#', 'es/', $currentPath)) }}">
    <link rel="alternate" hreflang="x-default" href="{{ url(preg_replace('#^(en|es)/#', 'en/', $currentPath)) }}">

    {{-- Structured Data --}}
    @hasSection('json_ld')
        <script type="application/ld+json">@yield('json_ld')</script>
    @else
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@type": "WebSite",
            "name": "{{ config('app.name', 'Midori Marketplace') }}",
            "url": "{{ url('/') }}",
            "potentialAction": {
                "@type": "SearchAction",
                "target": "{{ url('/en/extensions?q={search_term_string}') }}",
                "query-input": "required name=search_term_string"
            }
        }
        </script>
    @endif

    {{-- Preconnect --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')
</head>
<body class="bg-gray-50 text-gray-900 antialiased min-h-screen flex flex-col">
    {{-- Navbar --}}
    <nav class="bg-white border-b border-gray-200 px-4 py-3">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <a href="{{ url('/') }}" class="text-xl font-bold text-teal-700">
                Midori Marketplace
            </a>

            <div class="flex items-center gap-4">
                <a href="{{ route('extensions.index', ['locale' => app()->getLocale()]) }}" class="text-gray-600 hover:text-teal-700 text-sm font-medium">
                    {{ __('messages.extensions') }}
                </a>
                <a href="{{ route('contact.page', ['locale' => app()->getLocale()]) }}" class="text-gray-600 hover:text-teal-700 text-sm font-medium">
                    {{ __('messages.contact') }}
                </a>
                <a href="{{ route('docs.page', ['locale' => app()->getLocale()]) }}" class="text-gray-600 hover:text-teal-700 text-sm font-medium">
                    {{ __('messages.docs') }}
                </a>

                @auth
                    @if(auth()->user()->isAdmin())
                        <a href="{{ url('/admin') }}" class="text-gray-600 hover:text-teal-700 text-sm font-medium">
                            Admin
                        </a>
                    @endif
                    <span class="text-sm text-gray-500">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-sm text-red-600 hover:text-red-800">
                            {{ __('messages.logout') }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition-colors">
                        {{ __('messages.login') }}
                    </a>
                @endauth

                {{-- Language switcher --}}
                @php $otherLocale = app()->getLocale() === 'es' ? 'en' : 'es'; @endphp
                <a href="{{ url(preg_replace('#^(en|es)#', $otherLocale, request()->path())) }}" class="text-xs text-gray-400 hover:text-gray-600 uppercase">
                    {{ $otherLocale }}
                </a>
            </div>
        </div>
    </nav>

    {{-- Flash messages --}}
    @if(session('error'))
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        </div>
    @endif

    {{-- Content --}}
    <main class="flex-1">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="bg-white border-t border-gray-200 px-4 py-8 mt-auto">
        <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2">Midori Marketplace</h4>
                    <a href="https://astian.org/midori-browser/" class="block text-sm text-gray-400 hover:text-gray-600" target="_blank" rel="noopener noreferrer">Download Midori</a>
                    <a href="{{ url('/') }}" class="block text-sm text-gray-400 hover:text-gray-600">Marketplace</a>
                </div>
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2">{{ __('messages.docs') }}</h4>
                    <a href="{{ route('docs.page', ['locale' => app()->getLocale()]) }}" class="block text-sm text-gray-400 hover:text-gray-600">{{ __('messages.docs') }}</a>
                </div>
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2">{{ __('messages.all') ?? 'All' }}</h4>
                    <a href="https://astian.org" class="block text-sm text-gray-400 hover:text-gray-600" target="_blank" rel="noopener noreferrer">Astian</a>
                </div>
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2">GitHub</h4>
                    <a href="https://github.com/goastian" class="block text-sm text-gray-400 hover:text-gray-600" target="_blank" rel="noopener noreferrer">GitHub</a>
                </div>
            </div>
            <div class="border-t border-gray-200 pt-4 text-center text-xs text-gray-400">
                &copy; {{ date('Y') }} Astian, Inc. Midori Marketplace.
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
