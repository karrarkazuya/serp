@extends('layouts.app')
@section('title', 'New Unit of Measure')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.config.uoms.store') }}" class="flex flex-col h-full">
        @csrf
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('inventory.config.uoms.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Units of Measure</a>
                <span class="text-sm font-semibold text-gray-800">New</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.config.uoms.index') }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Discard</a>
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
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="Unit Name"
                           class="w-full text-2xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent {{ $errors->has('name') ? 'border-red-400' : 'border-gray-200' }}">
                </div>

                <div class="grid grid-cols-2 gap-x-8">
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-32 shrink-0 text-sm text-gray-500">Category</label>
                            <select name="uom_category_id" required class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                                <option value="">Select category</option>
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('uom_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-32 shrink-0 text-sm text-gray-500">Symbol</label>
                            <input type="text" name="symbol" value="{{ old('symbol') }}" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5" placeholder="-">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-32 shrink-0 text-sm text-gray-500">Type</label>
                            <select name="uom_type" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                                @foreach(['reference' => 'Reference Unit', 'bigger' => 'Bigger than reference', 'smaller' => 'Smaller than reference'] as $k => $v)
                                <option value="{{ $k }}" {{ old('uom_type', 'reference') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-32 shrink-0 text-sm text-gray-500">Ratio</label>
                            <input type="number" name="ratio" value="{{ old('ratio', 1) }}" step="0.000001" min="0.000001" required class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-32 shrink-0 text-sm text-gray-500">Rounding</label>
                            <input type="number" name="rounding" value="{{ old('rounding', 0.01) }}" step="0.000001" min="0.000001" required class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
