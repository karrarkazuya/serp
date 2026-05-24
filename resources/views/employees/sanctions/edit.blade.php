@extends('layouts.app')
@section('title', __('employees.sanctions_title'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.sanctions.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.sanctions_title') }}</a>
            <div class="flex items-center gap-1">
                <a href="{{ route('employees.sanctions.show', $sanction) }}" class="text-sm font-semibold text-gray-800 hover:text-purple-700">{{ $sanction->name ?? __('employees.sanctions_title') }}</a>
                <span class="text-xs text-gray-400">/</span>
                <span class="text-sm text-gray-500">{{ __('common.edit') }}</span>
            </div>
        </x-slot:breadcrumb>
        <x-slot:actions>
        <div class="flex items-center gap-2">
            <a href="{{ route('employees.sanctions.show', $sanction) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.cancel') }}</a>
            <button form="sanction-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save_short') }}</button>
        </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <form id="sanction-form" method="POST" action="{{ route('employees.sanctions.update', $sanction) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                @include('employees.sanctions._form', ['sanction' => $sanction, 'preselectedEmployee' => null])
            </form>
        </div>
    </div>
</div>
@endsection
