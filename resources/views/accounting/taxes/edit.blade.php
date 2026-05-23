@extends('layouts.app')
@section('title', 'Edit Tax')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('accounting.taxes.update', $tax) }}">
        @csrf @method('PUT')
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
                <a href="{{ route('accounting.taxes.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Taxes</a>
                <a href="{{ route('accounting.taxes.show', $tax) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $tax->name }}</a>
                <span class="text-sm font-semibold text-gray-800">Edit</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('accounting.taxes.show', $tax) }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-[#71639e] hover:bg-[#5c527f] rounded">Save</button>
                </div>
            </x-slot:actions>
        </x-toolbar>

        <div class="flex-1 overflow-y-auto p-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 max-w-2xl">
                @include('accounting.taxes._form')
            </div>
        </div>
    </form>
</div>
@endsection
