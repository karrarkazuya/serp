@extends('layouts.app')
@section('title', $badge->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('employees.badges.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Badges</a>
            <span class="text-sm font-semibold text-gray-800">{{ $badge->name }}</span>
        </div>

        <div class="ms-auto flex items-center gap-2">
            @can('update', $badge)
            <a href="{{ route('employees.badges.edit', $badge) }}"
               class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Edit</a>

            @if($badge->active)
            <form method="POST" action="{{ route('employees.badges.archive', $badge) }}">
                @csrf @method('PATCH')
                <button type="submit" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Archive</button>
            </form>
            @else
            <form method="POST" action="{{ route('employees.badges.unarchive', $badge) }}">
                @csrf @method('PATCH')
                <button type="submit" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Restore</button>
            </form>
            @endif
            @endcan

            @can('delete', $badge)
            <div x-data="{ confirming: false }">
                <button type="button" x-show="!confirming" @click="confirming = true"
                        class="px-3 py-1.5 text-sm text-red-600 bg-white border border-red-200 rounded hover:bg-red-50">Delete</button>
                <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                    <span class="text-xs text-red-600">Are you sure?</span>
                    <form method="POST" action="{{ route('employees.badges.delete', $badge) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">Yes</button>
                    </form>
                    <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border border-gray-300 rounded">Cancel</button>
                </div>
            </div>
            @endcan
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        @if(session('success'))
            <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-6">{{ $badge->name }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Name</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $badge->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</p>
                    <p class="text-sm mt-0.5">
                        @if($badge->active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Active</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">Archived</span>
                        @endif
                    </p>
                </div>
                @if($badge->description)
                <div class="sm:col-span-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Description</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $badge->description }}</p>
                </div>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\Badge"
                :model-id="$badge->id"
                :can-comment="auth()->user()->can('comment', $badge)"
                :comment-url="route('employees.badges.comment', $badge)"
            />
        </div>
    </div>
</div>
@endsection
