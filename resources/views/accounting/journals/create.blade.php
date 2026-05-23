@extends('layouts.app')
@section('title', 'New Journal')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <a href="{{ route('accounting.journals.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Journals</a>
            <span class="text-sm font-semibold text-gray-800">New Journal</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a href="{{ route('accounting.journals.index') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Cancel</a>
                <button form="journal-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">Save</button>
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="journal-form" method="POST" action="{{ route('accounting.journals.store') }}">
                @csrf
                @include('accounting.journals._form', ['journal' => null, 'defaultCompanyId' => $defaultCompanyId ?? null])
            </form>
        </div>
    </div>
</div>
@endsection
