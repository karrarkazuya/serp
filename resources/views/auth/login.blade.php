@extends('layouts.auth')
@section('title', 'Sign In')

@section('content')
<div class="min-h-screen flex">

    {{-- Left panel - purple brand --}}
    <div class="hidden lg:flex lg:w-1/2 bg-[#714B67] flex-col items-center justify-center px-16">
        <div class="text-center">
            <div class="w-20 h-20 bg-white/10 rounded-2xl flex items-center justify-center mx-auto mb-6">
                <span class="text-white font-bold text-3xl">S</span>
            </div>
            <h1 class="text-4xl font-bold text-white mb-3">S-ERP</h1>
            <p class="text-white/70 text-lg">Business management made simple</p>

            <div class="mt-12 grid grid-cols-2 gap-4 text-left">
                @foreach([
                    ['icon' => '👥', 'title' => 'Contacts', 'desc' => 'Manage your customers & partners'],
                    ['icon' => '⚙️', 'title' => 'Settings', 'desc' => 'Control roles & permissions'],
                    ['icon' => '📋', 'title' => 'Activity Log', 'desc' => 'Full audit trail on all records'],
                    ['icon' => '🔒', 'title' => 'Security', 'desc' => 'Granular access control'],
                ] as $feature)
                <div class="bg-white/10 rounded-xl p-4">
                    <div class="text-2xl mb-2">{{ $feature['icon'] }}</div>
                    <div class="text-white font-semibold text-sm">{{ $feature['title'] }}</div>
                    <div class="text-white/60 text-xs mt-0.5">{{ $feature['desc'] }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Right panel - login form --}}
    <div class="flex-1 flex items-center justify-center px-8 py-12 bg-gray-50">
        <div class="w-full max-w-sm">

            {{-- Logo (mobile) --}}
            <div class="lg:hidden text-center mb-8">
                <div class="w-16 h-16 bg-[#714B67] rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <span class="text-white font-bold text-2xl">S</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">S-ERP</h1>
            </div>

            <h2 class="text-2xl font-bold text-gray-800 mb-1">Welcome back</h2>
            <p class="text-gray-500 text-sm mb-8">Sign in to your account to continue</p>

            <form method="POST" action="{{ route('login.post') }}" class="space-y-5">
                @csrf

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" autofocus
                           class="w-full px-4 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-shadow
                                  {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white' }}">
                    @error('email')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                    <div class="relative" x-data="{ show: false }">
                        <input id="password" :type="show ? 'text' : 'password'" name="password" autocomplete="current-password"
                               class="w-full px-4 py-2.5 pr-10 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-shadow
                                      {{ $errors->has('password') ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white' }}">
                        <button type="button" @click="show = !show"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Remember me --}}
                <div class="flex items-center">
                    <input id="remember" type="checkbox" name="remember"
                           class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                    <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                </div>

                {{-- Submit --}}
                <button type="submit"
                        class="w-full bg-[#714B67] hover:bg-[#5c3d55] text-white font-semibold py-2.5 px-4 rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                    Sign in
                </button>
            </form>

            <p class="mt-8 text-center text-xs text-gray-400">
                S-ERP &copy; {{ date('Y') }} — Powered by Laravel
            </p>
        </div>
    </div>
</div>
@endsection
