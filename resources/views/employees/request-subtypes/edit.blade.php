@extends('layouts.app')
@section('title', __('common.edit') . ' — ' . $subtype->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.request-subtypes.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.subtypes_title') }}</a>
            <a href="{{ route('employees.request-subtypes.show', $subtype) }}" class="text-sm font-semibold text-gray-800 hover:text-purple-700">{{ $subtype->name }}</a>
            <span class="text-xs text-gray-400">/</span>
            <span class="text-sm text-gray-500">{{ __('common.edit') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a href="{{ route('employees.request-subtypes.show', $subtype) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.cancel') }}</a>
                <button form="subtype-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save_short') }}</button>
            </div>
        </x-slot:actions>
    </x-toolbar>
    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm p-6">
            @if($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            <form id="subtype-form" method="POST" action="{{ route('employees.request-subtypes.update', $subtype) }}">
                @csrf @method('PUT')
                @include('employees.request-subtypes._form', ['subtype' => $subtype])
            </form>
        </div>
    </div>
</div>
@endsection
