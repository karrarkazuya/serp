@extends('layouts.app')
@section('title', $position->organizational_structure ?? __('employees.positions_title'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.positions.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.positions_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $position->organizational_structure ?? __('employees.positions_title') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
        <div class="flex items-center gap-2">
            @can('update', \App\Models\Employees\Employee::class)
            <a href="{{ route('employees.positions.edit', $position) }}"
               class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
            @endcan

            @can('update', \App\Models\Employees\Employee::class)
            <form method="POST" action="{{ $position->active ? route('employees.positions.archive', $position) : route('employees.positions.unarchive', $position) }}">
                @csrf @method('PATCH')
                <button type="submit" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">
                    {{ $position->active ? __('common.archive') : __('common.unarchive') }}
                </button>
            </form>
            @endcan

            @can('delete', \App\Models\Employees\Employee::class)
            <div x-data="{ confirming: false }">
                <button type="button" x-show="!confirming" @click="confirming = true"
                        class="px-3 py-1.5 text-sm text-red-600 bg-white border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
                <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                    <span class="text-xs text-red-600">{{ __('common.are_you_sure') }}</span>
                    <form method="POST" action="{{ route('employees.positions.delete', $position) }}">
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
        {{-- Position details card --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            @if(!$position->active)
                <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-700 font-medium">{{ __('common.archived') }}</div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.affective_date') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $position->affective_date?->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.data_status') }}</p>
                    @if($position->data_status)
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold mt-0.5 {{ $position->data_status === 'current' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ $position->data_status === 'current' ? __('employees.data_status_current') : __('employees.data_status_previous') }}
                    </span>
                    @else
                        <p class="text-sm text-gray-400 mt-0.5">—</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.organizational_structure') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $position->organizational_structure ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.assignment_type') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $position->assignment_type ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.financial_specialization') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">
                        {{ $position->financial_specialization ? number_format($position->financial_specialization, 2) : '—' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Employees card --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <form method="POST" action="{{ route('employees.positions.employees.sync', $position) }}">
                @csrf @method('PUT')

                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-700">{{ __('employees.doc_employee') }}</h3>
                    @can('update', \App\Models\Employees\Employee::class)
                    <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">
                        {{ __('common.save_short') }}
                    </button>
                    @endcan
                </div>

                <x-relation-dropdown
                    name="employee_ids"
                    table="hr_employees"
                    field="name"
                    relation="many2many"
                    :selected="$position->employees->pluck('id')->all()"
                    placeholder="{{ __('employees.select_employee') }}"
                    compact
                />
            </form>
        </div>

        {{-- Chatter --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\EmployeePosition"
                :model-id="$position->id"
                :can-comment="auth()->user()->can('update', \App\Models\Employees\Employee::class)"
                :comment-url="route('employees.positions.comment', $position)"
            />
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm text-xs text-gray-400 px-5 py-3 flex gap-6">
            @if($position->creator)
            <span>{{ __('common.created_by') }}: <span class="text-gray-600">{{ $position->creator->name }}</span></span>
            @endif
            @if($position->created_at)
            <span>{{ __('common.created_at') }}: <span class="text-gray-600">{{ $position->created_at->format('d M Y') }}</span></span>
            @endif
            @if($position->updater)
            <span>{{ __('common.updated_by') }}: <span class="text-gray-600">{{ $position->updater->name }}</span></span>
            @endif
        </div>
    </div>
</div>
@endsection
