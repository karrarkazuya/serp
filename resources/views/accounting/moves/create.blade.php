@extends('layouts.app')
@section('title', 'New Journal Entry')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <a href="{{ route('accounting.moves.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Journal Entries</a>
            <span class="text-sm font-semibold text-gray-800">New Entry</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @if(auth()->user()->hasPermission('accounting.post'))
                <button form="move-form" type="submit" name="action" value="post" class="px-4 py-1.5 text-sm font-medium text-white bg-[#71639e] hover:bg-[#5c527f] rounded shadow-sm">Post</button>
                @endif
                <button form="move-form" type="submit" name="action" value="save" class="px-3 py-1.5 text-sm text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Save Draft</button>
                <a href="{{ route('accounting.moves.index') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Discard</a>
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="move-form" method="POST" action="{{ route('accounting.moves.store') }}">
                @csrf
                @include('accounting.moves._form', [
                    'move' => null,
                    'defaultCompanyId' => $defaultCompanyId ?? null,
                    'preselectedJournalId' => $preselectedJournalId ?? null,
                ])
            </form>
        </div>
    </div>
</div>
@endsection
