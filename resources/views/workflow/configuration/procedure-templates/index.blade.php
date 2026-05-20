@extends('layouts.app')
@section('title', 'Procedure Templates')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-3 px-4 py-2 border-b border-gray-200 shrink-0">
        @can('create', \App\Models\Workflow\ProcedureTemplate::class)
        <a href="{{ route('workflow.config.procedure-templates.create') }}" class="px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm">New</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700">Procedure Templates</span>
        <x-search
            :model="\App\Models\Workflow\ProcedureTemplate::class"
            :action="route('workflow.config.procedure-templates.index')"
        />
    </div>
    @if(session('success'))
    <div class="mx-4 mt-3 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded">{{ session('success') }}</div>
    @endif
    <x-list :paginator="$templates" empty-text="No procedure templates yet.">
        <x-slot:columns>
            <x-sortable-th column="name"    label="Name"          class="px-4 py-2" :default="true" />
            <x-sortable-th column="group"   label="Default Group" class="px-3 py-2" />
            <x-sortable-th column="sla"     label="SLA (hrs)"     class="px-3 py-2" />
            <x-sortable-th column="enabled" label="Enabled"       class="px-3 py-2" />
            <th class="px-3 py-2"></th>
        </x-slot:columns>

        @foreach($templates as $tpl)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('workflow.config.procedure-templates.show', $tpl) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $tpl->name }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $tpl->defaultGroup?->name ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $tpl->resolve_max_duration ?? '—' }}</td>
            <td class="px-3 py-2">
                <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $tpl->enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $tpl->enabled ? 'Yes' : 'No' }}
                </span>
            </td>
            <td class="px-3 py-2 text-right" onclick="event.stopPropagation()">
                @can('update', $tpl)
                <a href="{{ route('workflow.config.procedure-templates.edit', $tpl) }}" class="text-xs text-purple-600 hover:text-purple-700 mr-3">Edit</a>
                @endcan
                @can('delete', $tpl)
                <form method="POST" action="{{ route('workflow.config.procedure-templates.delete', $tpl) }}" class="inline" @submit.prevent="$dispatch('confirm-delete', { message: 'Are you sure you want to delete this?', form: $el })">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-600 hover:text-red-700">Delete</button>
                </form>
                @endcan
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
