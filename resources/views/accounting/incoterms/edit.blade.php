@extends('layouts.app')
@section('title', 'Edit ' . $incoterm->code)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <a href="{{ route('accounting.incoterms.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Incoterms</a>
            <a href="{{ route('accounting.incoterms.show', $incoterm) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $incoterm->code }}</a>
            <span class="text-sm font-semibold text-gray-800">Edit</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a href="{{ route('accounting.incoterms.show', $incoterm) }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Cancel</a>
                <button type="submit" form="main-form" class="px-3 py-1.5 text-sm font-medium text-white bg-purple-600 rounded hover:bg-purple-700">Save</button>
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @if($errors->any())
        <div class="mb-4 px-3 py-2 bg-red-50 border border-red-200 text-sm text-red-700 rounded">
            @foreach($errors->all() as $err)<p>{{ $err }}</p>@endforeach
        </div>
        @endif

        <form id="main-form" method="POST" action="{{ route('accounting.incoterms.update', $incoterm) }}">
            @csrf @method('PUT')
            @include('accounting.incoterms._form')
        </form>
    </div>
</div>
@endsection
