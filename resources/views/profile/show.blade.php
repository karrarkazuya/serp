@extends('layouts.app')
@section('title', __('profile.title'))

@section('content')
<div class="flex flex-col h-full overflow-y-auto bg-gray-50">

    {{-- Page header --}}
    <div class="bg-white border-b border-gray-200 px-6 py-4 shrink-0">
        <h1 class="text-xl font-semibold text-gray-800">{{ __('profile.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('profile.subtitle') }}</p>
    </div>

    <div class="flex-1 px-6 py-6 max-w-5xl mx-auto w-full">

        {{-- Profile hero card --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6 flex items-center gap-5">
            {{-- Avatar --}}
            <div class="w-16 h-16 rounded-full bg-[#714B67] flex items-center justify-center text-xl font-bold text-white shrink-0 select-none">
                {{ $user->initials }}
            </div>

            {{-- Info --}}
            <div class="flex-1 min-w-0">
                <h2 class="text-xl font-bold text-gray-900 truncate">{{ $user->name }}</h2>
                @if($user->job_position)
                    <p class="text-sm text-gray-500 mt-0.5">{{ $user->job_position }}</p>
                @endif
                <div class="flex flex-wrap items-center gap-3 mt-2">
                    <span class="flex items-center gap-1.5 text-xs text-gray-500">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        {{ $user->email }}
                    </span>
                    @if($user->phone)
                    <span class="flex items-center gap-1.5 text-xs text-gray-500">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        {{ $user->phone }}
                    </span>
                    @endif
                    <span class="flex items-center gap-1.5 text-xs text-gray-500">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        {{ __('common.member_since') }} {{ $user->created_at->format('M Y') }}
                    </span>
                </div>
            </div>

            {{-- Role badges --}}
            <div class="hidden sm:flex flex-col items-end gap-1.5 shrink-0">
                @forelse($user->roles as $role)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                        {{ $role->name }}
                    </span>
                @empty
                    <span class="text-xs text-gray-400">{{ __('profile.no_roles') }}</span>
                @endforelse
            </div>
        </div>

        {{-- Two-column cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Personal Information --}}
            <div class="bg-white rounded-xl border border-gray-200 flex flex-col">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        {{ __('profile.personal_info') }}
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">{{ __('profile.personal_info_desc') }}</p>
                </div>

                <form method="POST" action="{{ route('profile.update') }}" class="flex flex-col flex-1">
                    @csrf @method('PUT')

                    <div class="px-6 py-5 flex-1 space-y-4">

                        @if(session('profile_success'))
                        <div class="flex items-center gap-2 px-3 py-2 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ session('profile_success') }}
                        </div>
                        @endif

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('profile.full_name') }} <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                                   class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors
                                          {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('profile.email') }} <span class="text-red-500">*</span></label>
                            @if(auth()->user()->hasPermission('users.write') || auth()->user()->hasPermission('users.create'))
                                <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                                       class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors
                                              {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                                @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            @else
                                <div class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-500 select-none">
                                    {{ $user->email }}
                                </div>
                            @endif
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('profile.phone') }}</label>
                            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('profile.job_position') }}</label>
                            <div class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-500 select-none">
                                {{ $user->job_position ?: '—' }}
                            </div>
                        </div>

                        @if($user->defaultCompany)
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('profile.default_company') }}</label>
                            <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700">
                                <div class="w-5 h-5 rounded bg-purple-100 flex items-center justify-center text-[10px] font-bold text-purple-700 shrink-0">
                                    {{ strtoupper(substr($user->defaultCompany->name, 0, 2)) }}
                                </div>
                                {{ $user->defaultCompany->name }}
                            </div>
                        </div>
                        @endif
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 rounded-b-xl">
                        <button type="submit"
                                class="px-5 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                            {{ __('profile.save_changes') }}
                        </button>
                    </div>
                </form>
            </div>

            {{-- Change Password --}}
            <div class="bg-white rounded-xl border border-gray-200 flex flex-col"
                 x-data="{
                     password: '',
                     show: false,
                     showCurrent: false,
                     get hasLength()    { return this.password.length >= 8 },
                     get hasUpper()     { return /[A-Z]/.test(this.password) },
                     get hasLower()     { return /[a-z]/.test(this.password) },
                     get hasNumber()    { return /[0-9]/.test(this.password) },
                     get hasSymbol()    { return /[^A-Za-z0-9]/.test(this.password) },
                     get strength()     { return [this.hasLength, this.hasUpper, this.hasLower, this.hasNumber, this.hasSymbol].filter(Boolean).length },
                     get strengthLabel(){
                         if (this.strength <= 1) return '{{ __('profile.strength_very_weak') }}';
                         if (this.strength === 2) return '{{ __('profile.strength_weak') }}';
                         if (this.strength === 3) return '{{ __('profile.strength_fair') }}';
                         if (this.strength === 4) return '{{ __('profile.strength_strong') }}';
                         return '{{ __('profile.strength_very_strong') }}';
                     },
                     get strengthColor(){
                         if (this.strength <= 1) return 'bg-red-500';
                         if (this.strength === 2) return 'bg-orange-400';
                         if (this.strength === 3) return 'bg-yellow-400';
                         if (this.strength === 4) return 'bg-blue-500';
                         return 'bg-green-500';
                     }
                 }">

                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        {{ __('profile.change_password') }}
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">{{ __('profile.change_password_desc') }}</p>
                </div>

                <form method="POST" action="{{ route('profile.password') }}" class="flex flex-col flex-1">
                    @csrf @method('PUT')

                    <div class="px-6 py-5 flex-1 space-y-4">

                        @if(session('password_success'))
                        <div class="flex items-center gap-2 px-3 py-2 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ session('password_success') }}
                        </div>
                        @endif

                        {{-- Current password --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('profile.current_password') }} <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input :type="showCurrent ? 'text' : 'password'" name="current_password"
                                       class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors
                                              {{ $errors->has('current_password') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                                <button type="button" @click="showCurrent = !showCurrent"
                                        class="absolute end-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <svg x-show="!showCurrent" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg x-show="showCurrent" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                </button>
                            </div>
                            @error('current_password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>

                        {{-- New password with strength --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('profile.new_password') }} <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input :type="show ? 'text' : 'password'" name="password"
                                       x-model="password"
                                       class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors
                                              {{ $errors->has('password') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                                <button type="button" @click="show = !show"
                                        class="absolute end-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                </button>
                            </div>
                            @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

                            <div x-show="password.length > 0" class="mt-2 space-y-2" style="display:none">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-300"
                                             :class="strengthColor"
                                             :style="'width: ' + (strength * 20) + '%'"></div>
                                    </div>
                                    <span class="text-xs font-medium min-w-[70px] text-end"
                                          :class="{
                                              'text-red-500':    strength <= 1,
                                              'text-orange-500': strength === 2,
                                              'text-yellow-600': strength === 3,
                                              'text-blue-600':   strength === 4,
                                              'text-green-600':  strength === 5
                                          }"
                                          x-text="strengthLabel"></span>
                                </div>

                                <ul class="grid grid-cols-1 gap-1">
                                    <li class="flex items-center gap-1.5 text-xs" :class="hasLength ? 'text-green-600' : 'text-gray-400'">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path x-show="hasLength" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            <circle x-show="!hasLength" cx="12" cy="12" r="3" fill="currentColor" style="display:none"/>
                                        </svg>
                                        {{ __('profile.req_8_chars') }}
                                    </li>
                                    <li class="flex items-center gap-1.5 text-xs" :class="hasUpper ? 'text-green-600' : 'text-gray-400'">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path x-show="hasUpper" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            <circle x-show="!hasUpper" cx="12" cy="12" r="3" fill="currentColor" style="display:none"/>
                                        </svg>
                                        {{ __('profile.req_uppercase') }}
                                    </li>
                                    <li class="flex items-center gap-1.5 text-xs" :class="hasLower ? 'text-green-600' : 'text-gray-400'">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path x-show="hasLower" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            <circle x-show="!hasLower" cx="12" cy="12" r="3" fill="currentColor" style="display:none"/>
                                        </svg>
                                        {{ __('profile.req_lowercase') }}
                                    </li>
                                    <li class="flex items-center gap-1.5 text-xs" :class="hasNumber ? 'text-green-600' : 'text-gray-400'">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path x-show="hasNumber" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            <circle x-show="!hasNumber" cx="12" cy="12" r="3" fill="currentColor" style="display:none"/>
                                        </svg>
                                        {{ __('profile.req_number') }}
                                    </li>
                                    <li class="flex items-center gap-1.5 text-xs" :class="hasSymbol ? 'text-green-600' : 'text-gray-400'">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path x-show="hasSymbol" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            <circle x-show="!hasSymbol" cx="12" cy="12" r="3" fill="currentColor" style="display:none"/>
                                        </svg>
                                        {{ __('profile.req_symbol') }}
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {{-- Confirm password --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('profile.confirm_password') }} <span class="text-red-500">*</span></label>
                            <input type="password" name="password_confirmation"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors">
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 rounded-b-xl">
                        <button type="submit"
                                class="px-5 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                            {{ __('profile.update_password') }}
                        </button>
                    </div>
                </form>
            </div>

        </div>

        {{-- Language & Region --}}
        <div class="bg-white rounded-xl border border-gray-200 mt-6">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
                    </svg>
                    {{ __('profile.language') }}
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ __('profile.language_desc') }}</p>
            </div>

            <div class="px-6 py-5">
                @if(session('language_success'))
                <div class="flex items-center gap-2 px-3 py-2 mb-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ session('language_success') }}
                </div>
                @endif

                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('profile.language') }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="language" value="en">
                        <button type="submit"
                                class="flex items-center gap-2.5 px-4 py-2.5 rounded-lg border-2 text-sm font-medium transition-colors
                                       {{ ($user->language ?? 'en') === 'en'
                                            ? 'border-[#714B67] bg-[#714B67]/5 text-[#714B67]'
                                            : 'border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-gray-50' }}">
                            <span class="text-base leading-none">🇺🇸</span>
                            {{ __('profile.lang_en') }}
                            @if(($user->language ?? 'en') === 'en')
                            <svg class="w-4 h-4 text-[#714B67]" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            @endif
                        </button>
                    </form>

                    <form method="POST" action="{{ route('profile.language') }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="language" value="ar">
                        <button type="submit"
                                class="flex items-center gap-2.5 px-4 py-2.5 rounded-lg border-2 text-sm font-medium transition-colors
                                       {{ ($user->language ?? 'en') === 'ar'
                                            ? 'border-[#714B67] bg-[#714B67]/5 text-[#714B67]'
                                            : 'border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-gray-50' }}">
                            <span class="text-base leading-none">🇸🇦</span>
                            {{ __('profile.lang_ar') }}
                            @if(($user->language ?? 'en') === 'ar')
                            <svg class="w-4 h-4 text-[#714B67]" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            @endif
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Companies & roles info --}}
        @if($user->companies->isNotEmpty() || $user->roles->isNotEmpty())
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">

            @if($user->companies->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">{{ __('profile.assigned_companies') }}</h3>
                <div class="space-y-2.5">
                    @foreach($user->companies as $company)
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">
                            {{ strtoupper(substr($company->name, 0, 2)) }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $company->name }}</p>
                            @if($company->city)
                                <p class="text-xs text-gray-400">{{ $company->city }}</p>
                            @endif
                        </div>
                        @if($user->company_id === $company->id)
                        <span class="ms-auto text-xs text-purple-600 font-medium">{{ __('common.default') }}</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if($user->roles->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">{{ __('profile.assigned_roles') }}</h3>
                <div class="space-y-2.5">
                    @foreach($user->roles as $role)
                    <div class="flex items-start gap-3">
                        <div class="w-7 h-7 rounded-lg bg-purple-100 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $role->name }}</p>
                            @if($role->description)
                                <p class="text-xs text-gray-400">{{ $role->description }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
        @endif

        {{-- Meta --}}
        <div class="mt-6 text-xs text-gray-400 flex gap-6">
            <span>{{ __('common.account_created') }} {{ $user->created_at->format('M d, Y') }}</span>
            <span>{{ __('common.last_updated') }} {{ $user->updated_at->diffForHumans() }}</span>
        </div>

    </div>
</div>
@endsection
