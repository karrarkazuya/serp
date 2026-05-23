@extends('layouts.app')
@section('title', 'New Route')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.config.routes.store') }}" class="flex flex-col h-full">
        @csrf
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('inventory.config.routes.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Routes</a>
                <span class="text-sm font-semibold text-gray-800">New</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.config.routes.index') }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Discard</a>
                    <button type="submit" class="px-3 py-1.5 text-sm font-semibold text-white bg-[#714B67] hover:bg-[#5c3d55] rounded">Save</button>
                </div>
            </x-slot:actions>
        </x-toolbar>
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm p-6">
                @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
                @endif

                <div class="mb-5">
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="Route Name"
                           class="w-full text-2xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent {{ $errors->has('name') ? 'border-red-400' : 'border-gray-200' }}">
                </div>

                <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                    <label class="w-40 shrink-0 text-sm text-gray-500">Company</label>
                    <x-relation-dropdown table="companies" field="name" name="company_id" relation="many2one" :selected="old('company_id', $defaultCompanyId ?? null)" class="flex-1" compact />
                </div>
                <p class="mt-3 text-xs text-gray-400">Rules can be added after saving.</p>
            </div>
        </div>
    </form>
</div>
@endsection
