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
<body class="h-full overflow-x-hidden bg-gray-100 font-sans antialiased"
      x-data="{ sidebarOpen: false, confirmOpen: false, confirmMessage: '', confirmForm: null }"
      @confirm-delete.window="confirmMessage = $event.detail.message; confirmForm = $event.detail.form; confirmOpen = true">
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

    {{-- Global confirm modal --}}
    <div x-show="confirmOpen" x-transition style="display:none"
         class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-[200]"
         @click.self="confirmOpen = false">
        <div class="bg-white rounded-xl shadow-2xl border border-gray-100 w-96 mx-4 p-6">
            <div class="flex items-start gap-3 mb-5">
                <div class="w-9 h-9 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Confirm</h3>
                    <p class="text-sm text-gray-500 mt-0.5 leading-relaxed" x-text="confirmMessage"></p>
                </div>
            </div>
            <div class="flex gap-2 justify-end">
                <button type="button" @click="confirmOpen = false"
                        class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="button" @click="if (confirmForm) confirmForm.submit(); confirmOpen = false"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                    Confirm
                </button>
            </div>
        </div>
    </div>

</body>
</html>
