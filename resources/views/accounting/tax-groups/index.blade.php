@extends('layouts.app')
@section('title', 'Tax Groups')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar :new-href="auth()->user()->hasPermission('accounting.create') ? route('accounting.tax-groups.create') : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Tax Groups</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @if(session('success'))
        <div class="mb-4 px-3 py-2 bg-green-50 border border-green-200 text-sm text-green-700 rounded">{{ session('success') }}</div>
        @endif

        <x-search :model="\App\Models\Accounting\AccountingTaxGroup::class" />

        <x-list :paginator="$groups" empty-text="No tax groups found.">
            <x-slot:columns>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Sequence</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Company</th>
            </x-slot:columns>

            @foreach($groups as $group)
            <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('accounting.tax-groups.show', $group) }}'">
                <td class="px-4 py-3 text-sm font-semibold text-[#71639e]">{{ $group->name }}</td>
                <td class="px-4 py-3 text-sm tabular-nums text-gray-500">{{ $group->sequence }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ $group->company?->name ?? '—' }}</td>
            </tr>
            @endforeach
        </x-list>
    </div>
</div>
@endsection
