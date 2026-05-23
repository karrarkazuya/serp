@extends('layouts.app')
@section('title', 'New Transfer')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.transfers.store') }}" class="flex flex-col h-full">
        @csrf
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('inventory.transfers.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Transfers</a>
                <span class="text-sm font-semibold text-gray-800">New</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.transfers.index') }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Discard</a>
                    <button type="submit" class="px-3 py-1.5 text-sm font-semibold text-white bg-[#714B67] hover:bg-[#5c3d55] rounded">Save</button>
                </div>
            </x-slot:actions>
        </x-toolbar>

        <div class="flex-1 overflow-y-auto">
            <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm p-6">
                @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
                @endif

                <div class="grid grid-cols-2 gap-x-8">
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">Operation Type</label>
                            <x-relation-dropdown table="inventory_operation_types" field="name" name="operation_type_id" relation="many2one"
                                :selected="old('operation_type_id')" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">Source Location</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="location_src_id" relation="many2one"
                                :selected="old('location_src_id')" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">Destination Location</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="location_dest_id" relation="many2one"
                                :selected="old('location_dest_id')" class="flex-1" compact />
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">Scheduled Date</label>
                            <input type="datetime-local" name="scheduled_date" value="{{ old('scheduled_date', now()->format('Y-m-d\TH:i')) }}"
                                   class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">Source Document</label>
                            <input type="text" name="origin" value="{{ old('origin') }}" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="-">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">Company</label>
                            <x-relation-dropdown table="companies" field="name" name="company_id" relation="many2one"
                                :selected="old('company_id', $defaultCompanyId ?? null)" class="flex-1" compact />
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                        <label class="w-40 shrink-0 text-sm text-gray-500 pt-0.5">Note</label>
                        <textarea name="note" rows="2" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 resize-none" placeholder="-">{{ old('note') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
