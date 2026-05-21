@extends('layouts.app')
@section('title', __('workflow.managers_title'))

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-3 px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        <span class="text-xl font-semibold text-gray-700">{{ __('workflow.managers_title') }}</span>
        <span class="text-xs text-gray-400">//to do manager create/edit actions</span>
        <x-search
            :model="\App\Models\Workflow\Manager::class"
            :action="route('workflow.config.managers.index')"
        />
    </div>

    <x-list :paginator="$managers" :empty-text="__('workflow.no_managers')">
        <x-slot:columns>
            <x-sortable-th column="workflow_user" :label="__('workflow.user_col')"  class="px-4 py-2.5" :default="true" />
            <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('workflow.departments_title') }}</th>
            <x-sortable-th column="active"        :label="__('common.status')"      class="px-3 py-2.5" />
        </x-slot:columns>

        @foreach($managers as $manager)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('workflow.config.managers.show', $manager) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $manager->workflowUser?->user?->name }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $manager->departments->pluck('name')->join(', ') ?: '—' }}</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $manager->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $manager->active ? __('common.active') : __('common.inactive') }}
                </span>
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
