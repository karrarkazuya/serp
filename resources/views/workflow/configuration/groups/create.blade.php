@extends('layouts.app')
@section('title', 'New Group')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('workflow.config.groups.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Groups</a>
            <span class="text-sm font-semibold text-gray-800">New Group</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('workflow.config.groups.index') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Discard</a>
            <button form="group-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">Save</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="group-form" method="POST" action="{{ route('workflow.config.groups.store') }}">
                @csrf

                @if($errors->any())
                <div class="px-6 pt-4 pb-0">
                    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                        <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
                            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                </div>
                @endif

                <div class="p-6">
                    <div class="mb-6">
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="Group Name"
                               class="w-full text-3xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 border-gray-200 focus:outline-none focus:border-purple-500 pb-1 bg-transparent">
                    </div>

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-32 shrink-0 text-sm text-gray-500">Active</label>
                        <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                            <input type="checkbox" name="active" value="1" checked class="rounded border-gray-300 text-purple-600">
                            <span>Active</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
