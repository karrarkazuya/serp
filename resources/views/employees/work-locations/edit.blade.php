@extends('layouts.app')
@section('title', __('common.edit') . ' - ' . $location->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('employees.work-locations.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.locations_title') }}</a>
            <div class="flex items-center gap-1">
                <a href="{{ route('employees.work-locations.show', $location) }}" class="text-sm font-semibold text-gray-800 hover:text-purple-700">{{ $location->name }}</a>
                <span class="text-xs text-gray-400">/</span>
                <span class="text-sm text-gray-500">{{ __('common.edit') }}</span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('employees.work-locations.show', $location) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.cancel') }}</a>
            <button form="location-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save_short') }}</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm p-6">
            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <form id="location-form" method="POST" action="{{ route('employees.work-locations.update', $location) }}">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('common.name') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $location->name) }}"
                               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('common.company') }}</label>
                        <x-relation-dropdown
                            name="company_id"
                            table="companies"
                            :selected-id="old('company_id', $location->company_id)"
                            :selected-label="$location->company?->name"
                            placeholder="Select company..."
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.location_address') }}</label>
                        <input type="text" name="address" value="{{ old('address', $location->address) }}"
                               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.latitude') }}</label>
                        <input type="number" step="any" name="latitude" value="{{ old('latitude', $location->latitude) }}"
                               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.longitude') }}</label>
                        <input type="number" step="any" name="longitude" value="{{ old('longitude', $location->longitude) }}"
                               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
