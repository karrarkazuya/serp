@extends('layouts.app')
@section('title', 'Incoterms')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar :new-href="auth()->user()->hasPermission('accounting.create') ? route('accounting.incoterms.create') : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Incoterms</span>
        </x-slot:breadcrumb>
        <x-slot:search>
            <x-search :model="\App\Models\Accounting\AccountingIncoterm::class" />
        </x-slot:search>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @if(session('success'))
        <div class="mb-4 px-3 py-2 bg-green-50 border border-green-200 text-sm text-green-700 rounded">{{ session('success') }}</div>
        @endif

        <x-list :paginator="$incoterms" empty-text="No incoterms found.">
            <x-slot:columns>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Code</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
            </x-slot:columns>

            @foreach($incoterms as $incoterm)
            <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('accounting.incoterms.show', $incoterm) }}'">
                <td class="px-4 py-3 text-sm font-mono font-semibold text-[#71639e]">{{ $incoterm->code }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ $incoterm->name }}</td>
            </tr>
            @endforeach
        </x-list>
    </div>
</div>
@endsection
