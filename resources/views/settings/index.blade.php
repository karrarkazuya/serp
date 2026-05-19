@extends('layouts.app')
@section('title', __('settings.title'))
@include('settings._sidebar')

@section('content')
<div class="p-6 max-w-3xl">
    <h1 class="text-xl font-bold text-gray-800 mb-6">{{ __('settings.general') }}</h1>

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf @method('PUT')

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-600">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('settings.company_info') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @php
                    $settingMap = $settings->keyBy('key');
                    $val = fn($key) => old($key, $settingMap->get($key)?->value ?? '');
                @endphp

                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.company_name') }}</label>
                    <input type="text" name="company_name" value="{{ $val('company_name') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.email') }}</label>
                    <input type="email" name="company_email" value="{{ $val('company_email') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.phone') }}</label>
                    <input type="text" name="company_phone" value="{{ $val('company_phone') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('contacts.website') }}</label>
                    <input type="url" name="company_website" value="{{ $val('company_website') }}" placeholder="https://"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.address') }}</label>
                    <textarea name="company_address" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 resize-none">{{ $val('company_address') }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                {{ __('settings.save_settings') }}
            </button>
        </div>
    </form>
</div>
@endsection
