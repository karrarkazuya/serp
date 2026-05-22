@extends('layouts.app')
@section('title', $location->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.work-locations.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.locations_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $location->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        @can('update', $location)
        <a href="{{ route('employees.work-locations.edit', $location) }}"
           class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
        @endcan

        @can('delete', $location)
        <div x-data="{ confirming: false }">
            <button type="button" x-show="!confirming" @click="confirming = true"
                    class="px-3 py-1.5 text-sm text-red-600 bg-white border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
            <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                <span class="text-xs text-red-600">{{ __('common.are_you_sure') }}</span>
                <form method="POST" action="{{ route('employees.work-locations.delete', $location) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">{{ __('common.yes') }}</button>
                </form>
                <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border border-gray-300 rounded">{{ __('common.cancel') }}</button>
            </div>
        </div>
        @endcan
    </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('common.name') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $location->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('common.company') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $location->company?->name ?? '—' }}</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.location_address') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $location->address ?? '—' }}</p>
                </div>
                @if($location->latitude || $location->longitude)
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.coordinates') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $location->latitude }}, {{ $location->longitude }}</p>
                </div>
                @endif
            </div>
        </div>

        @if($location->employees->isNotEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">{{ __('common.employees') }} ({{ $location->employees->count() }})</h3>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($location->employees as $emp)
                <li class="px-5 py-3 hover:bg-purple-50/30">
                    <a href="{{ route('employees.show', $emp) }}" class="flex items-center gap-3">
                        @if($emp->avatar_url)
                            <img src="{{ $emp->avatar_url }}" alt="{{ $emp->name }}" class="w-8 h-8 rounded-full object-cover shrink-0">
                        @else
                            <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">{{ strtoupper(substr($emp->name, 0, 2)) }}</div>
                        @endif
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $emp->name }}</p>
                            <p class="text-xs text-gray-400">{{ $emp->department?->name }}</p>
                        </div>
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\WorkLocation"
                :model-id="$location->id"
                :can-comment="auth()->user()->can('comment', $location)"
                :comment-url="route('employees.work-locations.comment', $location)"
            />
        </div>
    </div>
</div>
@endsection
