@extends('layouts.app')
@section('title', __('settings.title'))
@include('settings._sidebar')

@section('content')
<div class="flex flex-col h-full bg-gray-50">

    {{-- Top bar --}}
    <div class="bg-white border-b border-gray-200 px-6 py-3 shrink-0 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-sm font-semibold text-gray-900">{{ __('settings.general_settings') }}</h1>
            <p class="text-xs text-gray-400 mt-0.5">{{ __('settings.general_settings_desc') }}</p>
        </div>
        <button form="settings-form" type="submit"
                class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded-lg shadow-sm transition-colors">
            {{ __('settings.save_settings') }}
        </button>
    </div>

    <div class="flex-1 overflow-y-auto">

        @if(session('success'))
        <div class="mx-6 mt-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded-xl px-4 py-3 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
        @endif

        <form id="settings-form" method="POST" action="{{ route('settings.update') }}">
            @csrf @method('PUT')

            @if($errors->any())
            <div class="mx-6 mt-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
                <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
            @endif

            @php
                $settingMap = $settings->keyBy('key');
                $val = fn ($key) => old($key, $settingMap->get($key)?->value ?? '');
            @endphp

            {{-- ── Company Information ── --}}
            <div class="flex gap-0 border-b border-gray-200 bg-white mt-4 mx-6 rounded-t-xl overflow-hidden">
                {{-- Left: section label --}}
                <div class="w-72 shrink-0 px-6 py-6 bg-gray-50/60 border-r border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-800">{{ __('settings.company_info') }}</h2>
                    <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">{{ __('settings.company_info_desc') }}</p>
                </div>
                {{-- Right: fields --}}
                <div class="flex-1 px-8 py-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ __('settings.company_name') }}</label>
                            <input type="text" name="company_name" value="{{ $val('company_name') }}"
                                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-[#714B67] focus:border-[#714B67] transition-colors placeholder-gray-300">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ __('common.email') }}</label>
                                <input type="email" name="company_email" value="{{ $val('company_email') }}"
                                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-[#714B67] focus:border-[#714B67] transition-colors placeholder-gray-300">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ __('common.phone') }}</label>
                                <input type="text" name="company_phone" value="{{ $val('company_phone') }}"
                                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-[#714B67] focus:border-[#714B67] transition-colors placeholder-gray-300">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ __('contacts.website') }}</label>
                            <input type="url" name="company_website" value="{{ $val('company_website') }}" placeholder="https://"
                                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-[#714B67] focus:border-[#714B67] transition-colors placeholder-gray-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ __('settings.address') }}</label>
                            <textarea name="company_address" rows="3"
                                      class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-[#714B67] focus:border-[#714B67] transition-colors resize-none">{{ $val('company_address') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Localization ── --}}
            <div class="flex gap-0 border-b border-gray-200 bg-white mx-6 overflow-hidden">
                <div class="w-72 shrink-0 px-6 py-6 bg-gray-50/60 border-r border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-800">{{ __('settings.localization') }}</h2>
                    <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">{{ __('settings.localization_desc') }}</p>
                </div>
                <div class="flex-1 px-8 py-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ __('settings.language') }}</label>
                            <select name="language"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-[#714B67] focus:border-[#714B67] transition-colors">
                                <option value="en" {{ $val('language') === 'en' ? 'selected' : '' }}>English</option>
                                <option value="ar" {{ $val('language') === 'ar' ? 'selected' : '' }}>العربية</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ __('settings.timezone') }}</label>
                            <select name="timezone"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-[#714B67] focus:border-[#714B67] transition-colors">
                                @foreach(timezone_identifiers_list() as $tz)
                                <option value="{{ $tz }}" {{ $val('timezone') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Security ── --}}
            <div class="flex gap-0 bg-white mx-6 rounded-b-xl overflow-hidden mb-6">
                <div class="w-72 shrink-0 px-6 py-6 bg-gray-50/60 border-r border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-800">{{ __('settings.security') }}</h2>
                    <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">{{ __('settings.security_desc') }}</p>
                </div>
                <div class="flex-1 px-8 py-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between py-2">
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ __('settings.require_strong_passwords') }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ __('settings.require_strong_passwords_desc') }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="require_strong_passwords" value="1" class="sr-only peer"
                                       {{ $val('require_strong_passwords') ? 'checked' : '' }}>
                                <div class="w-10 h-5 bg-gray-200 rounded-full peer peer-checked:bg-[#714B67] transition-colors
                                            after:content-[''] after:absolute after:top-0.5 after:start-0.5 after:bg-white
                                            after:rounded-full after:h-4 after:w-4 after:transition-all
                                            peer-checked:after:translate-x-5"></div>
                            </label>
                        </div>
                        <div class="border-t border-gray-100 pt-4">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ __('settings.session_timeout') }}</label>
                            <input type="number" name="session_timeout" value="{{ $val('session_timeout') ?: 120 }}" min="5" max="1440"
                                   class="w-32 px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-[#714B67] focus:border-[#714B67] transition-colors">
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>
@endsection
