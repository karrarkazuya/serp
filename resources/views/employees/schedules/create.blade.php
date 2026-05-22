@extends('layouts.app')
@section('title', __('employees.new_schedule'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.schedules.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.schedules_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('employees.new_schedule') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        <a href="{{ route('employees.schedules.index') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.cancel') }}</a>
        <button form="schedule-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save_short') }}</button>
    </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm p-6">
            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            @include('employees.schedules._form', ['schedule' => null])
        </div>
    </div>
</div>
@endsection
