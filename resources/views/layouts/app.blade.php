<!DOCTYPE html>
@php($locale = app()->getLocale())
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'S-ERP') }} — @yield('title', 'Dashboard')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|cairo:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full overflow-x-hidden bg-gray-100 font-sans antialiased" x-data="{ sidebarOpen: false }">
    @php($isAppLauncher = request()->routeIs('dashboard', 'home'))

    {{-- Top navbar --}}
    @unless($isAppLauncher)
        @include('components.navbar')
    @endunless

    <div class="flex min-w-0 {{ $isAppLauncher ? 'h-screen' : 'h-[calc(100vh-52px)]' }}">

        {{-- Sidebar --}}
        @hasSection('sidebar')
            @include('components.sidebar')
        @endif

        {{-- Main content --}}
        <main class="min-w-0 flex-1 overflow-y-auto">

            {{-- Flash messages --}}
            <div x-data="{ show: false, message: '', type: 'success', timer: null }"
                 @notify.window="
                    message = $event.detail.message;
                    type = $event.detail.type || 'success';
                    show = true;
                    clearTimeout(timer);
                    timer = setTimeout(() => show = false, 4000);
                 "
                 x-show="show"
                 x-transition
                 class="fixed {{ $isAppLauncher ? 'top-4' : 'top-14' }} end-4 z-50 flex items-center gap-2 text-white px-4 py-3 rounded-lg shadow-lg text-sm font-medium transition-all"
                 :class="type === 'error' ? 'bg-red-600' : 'bg-green-600'"
                 style="display:none">
                <svg x-show="type !== 'error'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <svg x-show="type === 'error'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <span x-text="message"></span>
            </div>

            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                     class="fixed {{ $isAppLauncher ? 'top-4' : 'top-14' }} end-4 z-40 flex items-center gap-2 bg-green-600 text-white px-4 py-3 rounded-lg shadow-lg text-sm font-medium transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                     class="fixed {{ $isAppLauncher ? 'top-4' : 'top-14' }} end-4 z-40 flex items-center gap-2 bg-red-600 text-white px-4 py-3 rounded-lg shadow-lg text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

</body>
</html>
