@extends('layouts.app')
@section('title', 'Workflow Departments')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-3 px-4 py-2 border-b border-gray-200 shrink-0">
        @can('create', \App\Models\Workflow\Department::class)
        <a href="{{ route('workflow.config.departments.create') }}" class="px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm">New</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700">Departments</span>
        <x-search
            :model="\App\Models\Workflow\Department::class"
            :action="route('workflow.config.departments.index')"
        />
    </div>
    @if(session('success'))
    <div class="mx-4 mt-3 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded">{{ session('success') }}</div>
    @endif
    <x-list :paginator="$departments" empty-text="No departments yet.">
        <x-slot:columns>
            <x-sortable-th column="name"    label="Name"    class="px-4 py-2" :default="true" />
            <x-sortable-th column="company" label="Company" class="px-3 py-2" />
            <x-sortable-th column="active"  label="Status"  class="px-3 py-2" />
            <th class="px-3 py-2"></th>
        </x-slot:columns>

        @foreach($departments as $dept)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('workflow.config.departments.show', $dept) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $dept->name }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $dept->company?->name ?? '—' }}</td>
            <td class="px-3 py-2">
                <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $dept->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $dept->active ? 'Active' : 'Inactive' }}
                </span>
            </td>
            <td class="px-3 py-2 text-right" onclick="event.stopPropagation()">
                @can('update', $dept)
                <a href="{{ route('workflow.config.departments.edit', $dept) }}" class="text-xs text-purple-600 hover:text-purple-700 mr-3">Edit</a>
                @endcan
                @can('delete', $dept)
                <form method="POST" action="{{ route('workflow.config.departments.delete', $dept) }}" class="inline" onsubmit="return confirm('Delete?')">
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
