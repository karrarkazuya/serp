@extends('layouts.app')
@section('title', __('workflow.groups_title'))

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-3 px-4 py-2 border-b border-gray-200 shrink-0">
        @can('create', \App\Models\Workflow\Group::class)
        <a href="{{ route('workflow.config.groups.create') }}" class="px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm">{{ __('common.new') }}</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700">{{ __('workflow.groups_title') }}</span>
        <x-search
            :model="\App\Models\Workflow\Group::class"
            :action="route('workflow.config.groups.index')"
        />
    </div>
    @if(session('success'))
    <div class="mx-4 mt-3 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded">{{ session('success') }}</div>
    @endif

    @if(isset($groupedRows))
    <x-list :grouped="true" :empty-text="__('workflow.no_groups')">
        <x-slot:columns>
            <x-sortable-th column="name"   :label="__('common.name')"   class="px-4 py-2" :default="true" />
            <x-sortable-th column="active" :label="__('common.status')" class="px-3 py-2" />
            <th class="px-3 py-2"></th>
        </x-slot:columns>

        @forelse($groupedRows as $grp)
        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="divide-y divide-gray-100">
            <tr class="bg-gray-50 border-y border-gray-200 cursor-pointer select-none" @click="open = !open">
                <td colspan="99" class="px-4 py-2.5">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                        <svg class="w-3.5 h-3.5 transition-transform shrink-0 text-gray-400" :class="open ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ $grp['label'] }}
                        <span class="ms-1 text-xs text-gray-400 font-normal">({{ $grp['count'] }})</span>
                    </div>
                </td>
            </tr>
            @foreach($grp['items'] as $group)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('workflow.config.groups.show', $group) }}'">
                <td class="px-4 py-2 font-medium text-gray-900">{{ $group->name }}</td>
                <td class="px-3 py-2">
                    <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $group->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $group->active ? __('common.active') : __('common.inactive') }}
                    </span>
                </td>
                <td class="px-3 py-2 text-end" onclick="event.stopPropagation()">
                    @can('update', $group)
                    <a href="{{ route('workflow.config.groups.edit', $group) }}" class="text-xs text-purple-600 hover:text-purple-700 me-3">{{ __('common.edit') }}</a>
                    @endcan
                    @can('delete', $group)
                    <form method="POST" action="{{ route('workflow.config.groups.delete', $group) }}" class="inline" @submit.prevent="$dispatch('confirm-delete', { message: @js(__('common.confirm_delete')), form: $el })">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-600 hover:text-red-700">{{ __('common.delete') }}</button>
                    </form>
                    @endcan
                </td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('workflow.no_groups') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$workflowGroups" :empty-text="__('workflow.no_groups')">
        <x-slot:columns>
            <x-sortable-th column="name"   :label="__('common.name')"   class="px-4 py-2" :default="true" />
            <x-sortable-th column="active" :label="__('common.status')" class="px-3 py-2" />
            <th class="px-3 py-2"></th>
        </x-slot:columns>

        @foreach($workflowGroups as $group)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('workflow.config.groups.show', $group) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $group->name }}</td>
            <td class="px-3 py-2">
                <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $group->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $group->active ? __('common.active') : __('common.inactive') }}
                </span>
            </td>
            <td class="px-3 py-2 text-end" onclick="event.stopPropagation()">
                @can('update', $group)
                <a href="{{ route('workflow.config.groups.edit', $group) }}" class="text-xs text-purple-600 hover:text-purple-700 me-3">{{ __('common.edit') }}</a>
                @endcan
                @can('delete', $group)
                <form method="POST" action="{{ route('workflow.config.groups.delete', $group) }}" class="inline" @submit.prevent="$dispatch('confirm-delete', { message: @js(__('common.confirm_delete')), form: $el })">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-600 hover:text-red-700">{{ __('common.delete') }}</button>
                </form>
                @endcan
            </td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
