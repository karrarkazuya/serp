@extends('layouts.app')
@section('title', 'Edit: ' . $location->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.config.locations.update', $location) }}" class="flex flex-col h-full">
        @csrf @method('PUT')
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('inventory.config.locations.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Locations</a>
                <a href="{{ route('inventory.config.locations.show', $location) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $location->complete_name }}</a>
                <span class="text-sm font-semibold text-gray-800">Edit</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.config.locations.show', $location) }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Discard</a>
                    <button type="submit" class="px-3 py-1.5 text-sm font-semibold text-white bg-[#714B67] hover:bg-[#5c3d55] rounded">Save</button>
                </div>
            </x-slot:actions>
        </x-toolbar>
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm p-6">
                @include('inventory.configuration.locations._form')
            </div>
        </div>
    </form>
</div>
@endsection
