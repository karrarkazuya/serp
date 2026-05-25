@extends('layouts.app')
@section('title', $subtype->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.request-subtypes.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.subtypes_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $subtype->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $subtype)
                <a href="{{ route('employees.request-subtypes.edit', $subtype) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
                @if($subtype->active)
                <form method="POST" action="{{ route('employees.request-subtypes.archive', $subtype) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">{{ __('common.archive') }}</button>
                </form>
                @else
                <form method="POST" action="{{ route('employees.request-subtypes.unarchive', $subtype) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50">{{ __('common.restore') }}</button>
                </form>
                @endif
                @endcan
                @can('delete', $subtype)
                <div x-data="{ confirming: false }">
                    <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
                    <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                        <span class="text-xs text-red-600">{{ __('common.are_you_sure') }}</span>
                        <form method="POST" action="{{ route('employees.request-subtypes.delete', $subtype) }}">@csrf @method('DELETE')<button class="px-2 py-1 text-xs bg-red-600 text-white rounded">{{ __('common.yes') }}</button></form>
                        <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border rounded">{{ __('common.cancel') }}</button>
                    </div>
                </div>
                @endcan
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-4">
                <h1 class="text-2xl font-bold text-gray-900">{{ $subtype->name }}</h1>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700">{{ __('employees.' . $subtype->type) }}</span>
                @if(!$subtype->active)<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ __('common.archived') }}</span>@endif
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-8 gap-y-3">
                @foreach([
                    [__('common.company'),                       $subtype->company?->name ?? '—'],
                    [__('employees.subtype_factor'),             $subtype->type === 'overtime' ? number_format((float) $subtype->factor, 2) . 'x' : '—'],
                    [__('employees.subtype_cuts_salary'),        $subtype->cuts_salary  ? __('common.yes') : __('common.no')],
                    [__('employees.subtype_cuts_balance'),       $subtype->cuts_balance ? __('common.yes') : __('common.no')],
                    [__('employees.subtype_requires_title'),     $subtype->requires_title       ? __('common.yes') : __('common.no')],
                    [__('employees.subtype_requires_description'), $subtype->requires_description ? __('common.yes') : __('common.no')],
                    [__('employees.subtype_requires_attachment'), $subtype->requires_attachment  ? __('common.yes') : __('common.no')],
                ] as [$label, $value])
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ $label }}</p>
                    <p class="text-sm text-gray-800 mt-0.5">{{ $value }}</p>
                </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\RequestSubtype"
                :model-id="$subtype->id"
                :can-comment="auth()->user()->can('update', $subtype)"
                :comment-url="route('employees.request-subtypes.comment', $subtype)"
            />
        </div>
    </div>
</div>
@endsection
