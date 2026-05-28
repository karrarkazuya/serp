@extends('layouts.app')
@section('title', $taxGroup->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Accounting\AccountingTaxGroup::class)
        @php $newHref = route('accounting.tax-groups.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('accounting.tax-groups.show', $prevId) : null"
        :next-href="$nextId ? route('accounting.tax-groups.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <a href="{{ route('accounting.tax-groups.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.tax_groups') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $taxGroup->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $taxGroup)
                <a href="{{ route('accounting.tax-groups.edit', $taxGroup) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_edit') }}</a>
                @endcan
                @can('delete', $taxGroup)
                <form method="POST" action="{{ route('accounting.tax-groups.delete', $taxGroup) }}" x-data="{ confirming: false }">
                    @csrf @method('DELETE')
                    <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">{{ __('accounting.btn_delete') }}</button>
                    <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                        <span class="text-xs text-red-600">{{ __('accounting.confirm_delete_generic') }}</span>
                        <button type="submit" class="px-2 py-1 text-xs font-medium text-white bg-red-600 rounded">{{ __('accounting.btn_yes') }}</button>
                        <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500">{{ __('accounting.btn_cancel') }}</button>
                    </div>
                </form>
                @endcan
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @if(session('success'))
        <div class="mb-4 px-3 py-2 bg-green-50 border border-green-200 text-sm text-green-700 rounded">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 max-w-2xl">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $taxGroup->name }}</h1>

            @foreach([
                [__('accounting.col_name'),     $taxGroup->name],
                [__('accounting.field_sequence'), $taxGroup->sequence],
                [__('accounting.col_company'),  $taxGroup->company?->name ?? '—'],
                [__('common.created_by'),       $taxGroup->creator?->name ?? '—'],
                [__('common.updated_by'),       $taxGroup->updater?->name ?? '—'],
            ] as [$label, $value])
            <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                <span class="w-36 shrink-0 text-sm font-medium text-gray-500">{{ $label }}</span>
                <span class="flex-1 text-sm text-gray-800">{{ $value }}</span>
            </div>
            @endforeach
        </div>

        <x-chatter
            model-type="App\Models\Accounting\AccountingTaxGroup"
            :model-id="$taxGroup->id"
            :can-comment="auth()->user()->can('comment', $taxGroup)"
        />
    </div>
</div>
@endsection
