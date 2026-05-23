@extends('layouts.app')
@section('title', $accountGroup->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Accounting\AccountingAccountGroup::class)
        @php $newHref = route('accounting.account-groups.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('accounting.account-groups.show', $prevId) : null"
        :next-href="$nextId ? route('accounting.account-groups.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <a href="{{ route('accounting.account-groups.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.account_groups') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $accountGroup->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $accountGroup)
                <a href="{{ route('accounting.account-groups.edit', $accountGroup) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_edit') }}</a>
                @endcan
                @can('delete', $accountGroup)
                <form method="POST" action="{{ route('accounting.account-groups.delete', $accountGroup) }}" x-data="{ confirming: false }">
                    @csrf @method('DELETE')
                    <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">{{ __('accounting.btn_delete') }}</button>
                    <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                        <span class="text-xs text-red-600">{{ __('accounting.confirm_delete_group') }}</span>
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
            <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $accountGroup->name }}</h1>

            @foreach([
                [__('accounting.field_name'),        $accountGroup->name],
                [__('accounting.field_code_start'),  $accountGroup->code_prefix_start ?: '—'],
                [__('accounting.field_code_end'),    $accountGroup->code_prefix_end ?: '—'],
                [__('accounting.col_parent'),        $accountGroup->parent?->name ?? '—'],
                [__('accounting.field_company'),     $accountGroup->company?->name ?? '—'],
                ['Created by',                       $accountGroup->creator?->name ?? '—'],
                ['Updated by',                       $accountGroup->updater?->name ?? '—'],
            ] as [$label, $value])
            <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                <span class="w-36 shrink-0 text-sm font-medium text-gray-500">{{ $label }}</span>
                <span class="flex-1 text-sm text-gray-800">{{ $value }}</span>
            </div>
            @endforeach

            @if($accountGroup->children->isNotEmpty())
            <div class="mt-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">{{ __('accounting.section_sub_groups') }}</h2>
                <ul class="space-y-1">
                    @foreach($accountGroup->children as $child)
                    <li>
                        <a href="{{ route('accounting.account-groups.show', $child) }}" class="text-sm text-purple-600 hover:underline">{{ $child->name }}</a>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>

        <x-chatter
            model-type="App\Models\Accounting\AccountingAccountGroup"
            :model-id="$accountGroup->id"
            :can-comment="auth()->user()->can('comment', $accountGroup)"
        />
    </div>
</div>
@endsection
