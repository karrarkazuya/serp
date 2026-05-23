@extends('layouts.app')
@section('title', $picking->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @php
        [$listRoute, $listLabel, $createRoute] = match($picking->operationType?->code) {
            'incoming' => [route('inventory.receipts.index'), __('inventory.receipts'), route('inventory.receipts.create')],
            'outgoing' => [route('inventory.deliveries.index'), __('inventory.deliveries'), route('inventory.deliveries.create')],
            'internal' => [route('inventory.internal-transfers.index'), __('inventory.internal_transfers'), route('inventory.internal-transfers.create')],
            default    => [route('inventory.transfers.index'), __('inventory.transfers'), route('inventory.transfers.create')],
        };
    @endphp
    @can('create', \App\Models\Inventory\Picking::class)
        @php $newHref = $createRoute; @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('inventory.transfers.show', $prevId) : null"
        :next-href="$nextId ? route('inventory.transfers.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ $listRoute }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $listLabel }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $picking->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @if($picking->canEdit())
                    <a href="{{ route('inventory.transfers.edit', $picking) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.edit') }}</a>
                @endif
                @can('update', $picking)
                    {{-- Draft: "Check Availability" confirms + reserves in one click (matches Odoo) --}}
                    @if($picking->isDraft())
                    <form method="POST" action="{{ route('inventory.transfers.check-availability', $picking) }}">
                        @csrf
                        <button class="px-3 py-1.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded">{{ __('inventory.check_availability') }}</button>
                    </form>
                    @elseif($picking->isConfirmed())
                    {{-- Confirmed: offer Check Availability (to reserve) OR Validate directly (immediate transfer) --}}
                    <form method="POST" action="{{ route('inventory.transfers.check-availability', $picking) }}">
                        @csrf
                        <button class="px-3 py-1.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded">{{ __('inventory.check_availability') }}</button>
                    </form>
                    <form method="POST" action="{{ route('inventory.transfers.validate', $picking) }}" id="validate-form">
                        @csrf
                        <button class="px-3 py-1.5 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded">{{ __('inventory.validate') }}</button>
                    </form>
                    @elseif($picking->isAssigned())
                    <form method="POST" action="{{ route('inventory.transfers.validate', $picking) }}" id="validate-form">
                        @csrf
                        <button class="px-3 py-1.5 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded">{{ __('inventory.validate') }}</button>
                    </form>
                    @endif

                    @if(!$picking->isDone() && !$picking->isCancelled())
                    <form method="POST" action="{{ route('inventory.transfers.cancel', $picking) }}" x-data="{ confirming: false }">
                        @csrf
                        <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">{{ __('inventory.cancel') }}</button>
                        <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                            <span class="text-xs text-red-600">{{ __('inventory.are_you_sure') }}</span>
                            <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">{{ __('inventory.yes') }}</button>
                            <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border rounded">{{ __('inventory.no') }}</button>
                        </div>
                    </form>
                    @endif

                    @if($picking->isDone())
                    <form method="POST" action="{{ route('inventory.transfers.return', $picking) }}" x-data="{ confirming: false }">
                        @csrf
                        <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-gray-700 border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.return') }}</button>
                        <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                            <span class="text-xs text-gray-600">{{ __('inventory.are_you_sure') }}</span>
                            <button type="submit" class="px-2 py-1 text-xs bg-gray-700 text-white rounded">{{ __('inventory.yes') }}</button>
                            <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border rounded">{{ __('inventory.no') }}</button>
                        </div>
                    </form>
                    @endif
                @endcan
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            {{-- Status bar --}}
            <div class="px-6 py-3 border-b border-gray-100 flex items-center gap-6">
                @foreach([__('inventory.status_draft') => 'draft', __('inventory.status_confirmed') => 'confirmed', __('inventory.status_ready') => 'assigned', __('inventory.status_done') => 'done'] as $label => $state)
                <div class="flex items-center gap-2">
                    <div class="w-2.5 h-2.5 rounded-full {{ in_array($picking->state, array_slice(['draft','confirmed','assigned','done'], 0, array_search($state, ['draft','confirmed','assigned','done']) + 1)) ? 'bg-purple-600' : 'bg-gray-200' }}"></div>
                    <span class="text-xs {{ $picking->state === $state ? 'font-semibold text-gray-900' : 'text-gray-400' }}">{{ $label }}</span>
                </div>
                @endforeach
                @if($picking->isCancelled())
                <span class="text-xs font-semibold text-red-600">{{ __('inventory.status_cancelled') }}</span>
                @endif
            </div>

            <div class="p-6">
                <div class="grid grid-cols-2 gap-x-8 gap-y-0">
                    <div>
                        @foreach([
                            [__('inventory.operation_type'), $picking->operationType?->name],
                            [__('inventory.from'),           $picking->srcLocation?->complete_name],
                            [__('inventory.to'),             $picking->destLocation?->complete_name],
                            [__('inventory.scheduled_date'), $picking->scheduled_date?->format('M d, Y H:i')],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-40 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '-' }}</span>
                        </div>
                        @endforeach
                    </div>
                    <div>
                        @foreach([
                            [__('inventory.source_document'), $picking->origin],
                            [__('inventory.company'),         $picking->company?->name],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-40 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '-' }}</span>
                        </div>
                        @endforeach
                        @if($picking->note)
                        <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                            <span class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.note') }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $picking->note }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Operations table --}}
            <div class="border-t border-gray-200">
                <div class="px-6 py-3 text-sm font-semibold text-gray-700">{{ __('inventory.section_operations') }}</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-2 text-start text-xs font-semibold text-gray-500">{{ __('inventory.col_product') }}</th>
                                <th class="px-3 py-2 text-start text-xs font-semibold text-gray-500">{{ __('inventory.lot_serial') }}</th>
                                <th class="px-3 py-2 text-end text-xs font-semibold text-gray-500">{{ __('inventory.col_demand') }}</th>
                                @if($picking->isConfirmed() || $picking->isAssigned() || $picking->isDone())
                                <th class="px-3 py-2 text-end text-xs font-semibold text-gray-500">{{ __('inventory.col_done') }}</th>
                                @endif
                                <th class="px-3 py-2 text-start text-xs font-semibold text-gray-500">{{ __('inventory.col_uom') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($picking->moves as $move)
                            <tr class="border-b border-gray-100">
                                <td class="px-6 py-2 text-gray-800 font-medium">{{ $move->product?->name }}</td>
                                {{-- Lot/serial lives on move lines, not the move itself --}}
                                <td class="px-3 py-2 text-gray-600 text-xs">
                                    {{ $move->moveLines->pluck('lot.name')->filter()->implode(', ') ?: '-' }}
                                </td>
                                <td class="px-3 py-2 text-end text-gray-800">{{ number_format($move->product_qty, 2) }}</td>
                                @if($picking->isConfirmed() || $picking->isAssigned() || $picking->isDone())
                                <td class="px-3 py-2 text-end">
                                    @if($picking->isConfirmed() || $picking->isAssigned())
                                    {{-- Inputs are associated with #validate-form via the form= attribute --}}
                                    <input type="number" name="qty_done[{{ $move->id }}]" form="validate-form"
                                           value="{{ $move->moveLines->sum('qty_done') ?: $move->product_qty }}"
                                           step="0.001" min="0"
                                           class="w-20 text-end text-sm border border-gray-200 rounded px-1 py-0.5 focus:border-purple-400 focus:outline-none">
                                    @else
                                    {{ number_format($move->qty_done ?: $move->moveLines->sum('qty_done'), 2) }}
                                    @endif
                                </td>
                                @endif
                                <td class="px-3 py-2 text-gray-500">{{ $move->product?->uom?->name }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400 text-sm">{{ __('inventory.no_operations') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Validate footer: shown for confirmed (immediate transfer) and assigned --}}
                @if($picking->isConfirmed() || $picking->isAssigned())
                <div class="px-6 py-3 border-t border-gray-100 flex items-center gap-4">
                    <button form="validate-form" type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded">{{ __('inventory.validate') }}</button>
                    <label class="flex items-center gap-1.5 text-sm text-gray-500 cursor-pointer select-none">
                        <input type="checkbox" name="no_backorder" value="1" form="validate-form"
                               class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                        {{ __('inventory.no_backorder') }}
                    </label>
                </div>
                @endif
            </div>
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter model-type="App\Models\Inventory\Picking" :model-id="$picking->id"
                :can-comment="auth()->user()->can('comment', $picking)" />
        </div>

        <div class="px-4 pb-4 text-xs text-gray-400 flex gap-6">
            <span>Created: {{ $picking->created_at->format('M d, Y') }}{{ $picking->creator ? ' · ' . $picking->creator->name : '' }}</span>
        </div>
    </div>
</div>
@endsection
