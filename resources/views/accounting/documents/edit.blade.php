@extends('layouts.app')
@section('title', 'Edit ' . $config['singular'])

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <a href="{{ route($config['routes']['index']) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $config['title'] }}</a>
            <a href="{{ route($config['routes']['show'], $document) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $document->name ?: __('accounting.status_draft') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.btn_edit') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @if(auth()->user()->hasPermission('accounting.post'))
                <button form="document-form" type="submit" name="action" value="post" class="px-4 py-1.5 text-sm font-medium text-white bg-[#71639e] hover:bg-[#5c527f] rounded shadow-sm">{{ __('accounting.btn_confirm') }}</button>
                @endif
                <button form="document-form" type="submit" name="action" value="save" class="px-3 py-1.5 text-sm text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_save') }}</button>
                <a href="{{ route($config['routes']['show'], $document) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_cancel') }}</a>
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="document-form" method="POST" action="{{ route($config['routes']['update'], $document) }}">
                @csrf
                @method('PUT')
                @include('accounting.documents._form', [
                    'document' => $document,
                    'config' => $config,
                    'defaultCompanyId' => $defaultCompanyId ?? null,
                    'defaults' => $defaults ?? [],
                ])
            </form>
        </div>
    </div>
</div>
@endsection
