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
    <x-list :paginator="$groups" :empty-text="__('workflow.no_groups')">
        <x-slot:columns>
            <x-sortable-th column="name"   :label="__('common.name')"   class="px-4 py-2" :default="true" />
            <x-sortable-th column="active" :label="__('common.status')" class="px-3 py-2" />
            <th class="px-3 py-2"></th>
        </x-slot:columns>

        @foreach($groups as $group)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('workflow.config.groups.show', $group) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $group->name }}</td>
            <td class="px-3 py-2">
                <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $group->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $group->active ? __('common.active') : __('common.inactive') }}
                </span>
            </td>
            <td class="px-3 py-2 text-right" onclick="event.stopPropagation()">
                @can('update', $group)
                <a href="{{ route('workflow.config.groups.edit', $group) }}" class="text-xs text-purple-600 hover:text-purple-700 mr-3">{{ __('common.edit') }}</a>
                @endcan
                @can('delete', $group)
                <form method="POST" action="{{ route('workflow.config.groups.delete', $group) }}" class="inline" @submit.prevent="$dispatch('confirm-delete', { message: 'Are you sure you want to delete this group?', form: $el })">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-600 hover:text-red-700">{{ __('common.delete') }}</button>
                </form>
                @endcan
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
