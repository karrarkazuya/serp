@extends('layouts.app')
@section('title', $tax->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Accounting\AccountTax::class)
        @php $newHref = route('accounting.taxes.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('accounting.taxes.show', $prevId) : null"
        :next-href="$nextId ? route('accounting.taxes.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <a href="{{ route('accounting.taxes.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.taxes') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $tax->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $tax)
                <a href="{{ route('accounting.taxes.edit', $tax) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_edit') }}</a>
                @if($tax->active)
                <form method="POST" action="{{ route('accounting.taxes.archive', $tax) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_archive') }}</button>
                </form>
                @else
                <form method="POST" action="{{ route('accounting.taxes.unarchive', $tax) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">{{ __('accounting.btn_restore') }}</button>
                </form>
                @endif
                @endcan
                @can('delete', $tax)
                <form method="POST" action="{{ route('accounting.taxes.delete', $tax) }}" x-data="{ confirming: false }">
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
            <div class="flex items-center gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">{{ $tax->name }}</h1>
                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $tax->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $tax->active ? __('accounting.status_active') : __('accounting.status_archived') }}
                </span>
            </div>

            @foreach([
                [__('accounting.col_type'),          $tax->amount_type_label],
                [__('accounting.col_rate_amount'),   $tax->amount_type === 'percent' ? number_format((float)$tax->amount, 2).'%' : number_format((float)$tax->amount, 2)],
                [__('accounting.col_applies_to'),    $tax->type_tax_use_label],
                [__('accounting.col_tax_account'),   $tax->account?->display_name ?? '—'],
                [__('accounting.price_inclusive'),   $tax->include_base_amount ? __('accounting.tax_yes_included') : __('accounting.tax_no_added_on_top')],
                [__('common.description'),           $tax->description ?: '—'],
                [__('accounting.col_company'),       $tax->company?->name ?? '—'],
                [__('common.created_by'),            $tax->creator?->name ?? '—'],
                [__('common.last_updated_by'),       $tax->updater?->name ?? '—'],
            ] as [$label, $value])
            <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                <span class="w-36 shrink-0 text-sm font-medium text-gray-500">{{ $label }}</span>
                <span class="flex-1 text-sm text-gray-800">{{ $value }}</span>
            </div>
            @endforeach
        </div>

        <x-chatter
            model-type="App\Models\Accounting\AccountTax"
            :model-id="$tax->id"
            :can-comment="auth()->user()->can('comment', $tax)"
        />
    </div>
</div>
@endsection
