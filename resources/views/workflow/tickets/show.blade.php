@extends('layouts.app')
@section('title', $ticket->name)

@section('content')
<style>
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: none; }
}
.ticket-card { animation: fadeSlideIn 0.3s ease both; }
</style>
<div class="flex flex-col h-full bg-gray-50"
     x-data="{
         dirty: false,
         showSaveConfirm: false,
         async saveAndComplete() {
             const form = document.getElementById('ticket-fields-form');
             if (form) {
                 await fetch(form.action, { method: 'POST', body: new FormData(form) });
             }
             this.$refs.completeForm.submit();
             this.showSaveConfirm = false;
         }
     }"
     @fields-changed.window="dirty = true">

    {{-- Top bar --}}
    <div class="bg-white border-b border-gray-200 px-5 py-2 flex items-center gap-3 shrink-0">
        @can('create', \App\Models\Workflow\Ticket::class)
        <a href="{{ route('workflow.tickets.create') }}" class="shrink-0 px-3 py-1.5 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-medium rounded">{{ __('common.new') }}</a>
        @endcan

        <div class="shrink-0">
            <div class="flex items-center gap-1 text-xs text-purple-600">
                <a href="{{ route('workflow.tickets.index') }}" class="hover:text-purple-700">{{ __('workflow.tickets_title') }}</a>
                @if($ticket->procedure)
                <span class="text-gray-300">/</span>
                <a href="{{ route('workflow.procedures.show', $ticket->procedure) }}" class="hover:text-purple-700 truncate max-w-36">{{ $ticket->procedure->name }}</a>
                @endif
            </div>
            <span class="text-sm font-semibold text-gray-800 leading-tight block truncate max-w-xs">{{ $ticket->name }}</span>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            @can('update', $ticket)
            @if($ticket->state === 'pending')
            @php $pathRequired = $ticket->has_path_choice && $ticket->path_choice_required && !$ticket->path_chosen_id; @endphp
            <form x-ref="completeForm" method="POST" action="{{ route('workflow.tickets.resolve', $ticket) }}">@csrf @method('PATCH')
                <button type="button"
                        @if(!$pathRequired) @click="dirty ? showSaveConfirm = true : $refs.completeForm.submit()" @endif
                        class="px-3 py-1.5 text-sm font-medium rounded border
                               {{ $pathRequired ? 'text-gray-400 border-gray-200 cursor-not-allowed' : 'text-green-700 border-green-300 hover:bg-green-50' }}"
                        @if($pathRequired) title="{{ __('workflow.select_path_before_completing') }}" @endif>
                    {{ __('workflow.mark_completed') }}
                </button>
            </form>
            @if($ticket->procedure_id && $ticket->previous_ticket_id)
            <div x-data="{ open: false }" class="relative" @click.outside="open=false">
                <button @click="open=!open" class="px-3 py-1.5 text-sm text-red-700 border border-red-300 rounded hover:bg-red-50">{{ __('workflow.return') }}</button>
                <div x-show="open" x-transition style="display:none"
                     class="absolute right-0 top-full mt-1.5 w-80 bg-white rounded-xl shadow-xl border border-gray-200 z-30 p-4">
                    <form method="POST" action="{{ route('workflow.tickets.close', $ticket) }}">
                        @csrf @method('PATCH')
                        @if($previousChain->count() > 1)
                        <div class="mb-3">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">{{ __('workflow.return_to') }} <span class="text-red-500">*</span></label>
                            <select name="return_to_ticket_id"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-red-300">
                                @foreach($previousChain as $prev)
                                <option value="{{ $prev->id }}">{{ $prev->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="mb-0">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">{{ __('workflow.return_reason') }} <span class="text-red-500">*</span></label>
                            <textarea name="return_reason" required rows="3"
                                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-300 resize-none"
                                      placeholder="{{ __('workflow.return_reason_placeholder') }}"></textarea>
                        </div>
                        <button type="submit"
                                class="mt-2 w-full px-3 py-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg">
                            {{ __('workflow.confirm_return') }}
                        </button>
                    </form>
                </div>
            </div>
            @else
            <form method="POST" action="{{ route('workflow.tickets.close', $ticket) }}">@csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">{{ __('workflow.close') }}</button>
            </form>
            @endif
            @elseif(in_array($ticket->state, ['completed', 'closed']))
            <form method="POST" action="{{ route('workflow.tickets.reopen', $ticket) }}">@csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-blue-700 border border-blue-200 rounded hover:bg-blue-50">{{ __('workflow.reopen') }}</button>
            </form>
            @endif
            @if($ticket->state !== 'pending')
            @if($ticket->active)
            <form method="POST" action="{{ route('workflow.tickets.archive', $ticket) }}">@csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">{{ __('common.archive') }}</button>
            </form>
            @else
            <form method="POST" action="{{ route('workflow.tickets.unarchive', $ticket) }}">@csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50">{{ __('workflow.restore') }}</button>
            </form>
            @endif
            @endif
            @if($ticket->sharedLink?->enabled)
            <div x-data="{ open: false, copied: false }" class="relative" @click.outside="open=false">
                <button @click="open=!open"
                        class="px-3 py-1.5 text-sm border rounded flex items-center gap-1.5 text-green-700 border-green-300 bg-green-50 hover:bg-green-100">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    {{ __('workflow.sharing_on') }}
                </button>
                <div x-show="open" x-transition style="display:none"
                     class="absolute right-0 top-full mt-1.5 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-30 p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ __('workflow.share_link') }}</p>
                    <div class="flex items-center gap-2 mb-3">
                        <input type="text" readonly value="{{ $ticket->sharedLink->shareUrl() }}"
                               class="flex-1 text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-gray-50 text-gray-600 select-all min-w-0">
                        <button @click="navigator.clipboard.writeText('{{ $ticket->sharedLink->shareUrl() }}'); copied=true; setTimeout(()=>copied=false,2000)"
                                class="shrink-0 px-2.5 py-1.5 text-xs border rounded-lg transition-colors"
                                :class="copied ? 'border-green-300 text-green-700 bg-green-50' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                            <span x-text="copied ? @js(__('workflow.copied')) : @js(__('workflow.copy'))"></span>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('workflow.share.ticket.toggle', $ticket) }}">@csrf
                        <button type="submit" class="w-full py-1.5 text-xs text-red-600 border border-red-200 rounded-lg hover:bg-red-50">
                            {{ __('workflow.turn_off_sharing') }}
                        </button>
                    </form>
                </div>
            </div>
            @else
            <form method="POST" action="{{ route('workflow.share.ticket.toggle', $ticket) }}">@csrf
                <button class="px-3 py-1.5 text-sm border rounded flex items-center gap-1.5 text-gray-500 border-gray-200 hover:bg-gray-50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    {{ __('workflow.share') }}
                </button>
            </form>
            @endif
            @endcan
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-5">

        @if(!$ticket->active)
        <div class="mb-4 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2">{{ __('workflow.ticket_archived') }}</div>
        @endif
        @if(session('success'))
        <div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-4 py-2">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-2">{{ session('error') }}</div>
        @endif

        <div class="flex gap-5 items-start">

            {{-- ─── LEFT: main card ─── --}}
            @php
                $ticketInputDefs = $ticket->procedureStep?->inputs ?? $ticket->template?->inputs;
                $defaultTab = ($ticketInputDefs && $ticketInputDefs->isNotEmpty()) ? 'fields' : 'activity';
            @endphp
            <div class="ticket-card relative flex-1 min-w-0 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden"
                 x-data="{ tab: @js($defaultTab) }">

                {{-- Header --}}
                <div class="px-7 pt-6 pb-5 border-b border-gray-100">

                    {{-- Status row --}}
                    <div class="flex items-center gap-2 mb-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $ticket->stateColor() }}">{{ $ticket->stateLabel() }}</span>
                        @if($ticket->isOverdue())<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-600">{{ __('workflow.overdue_label') }}</span>@endif
                        <span class="ms-auto text-xs text-gray-300 font-mono">#{{ $ticket->id }}</span>
                    </div>

                    {{-- Editable title --}}
                    @can('update', $ticket)
                    <div x-data="{ editing: false, val: @js($ticket->name) }" class="mb-2">
                        <h1 x-show="!editing" @click="editing=true"
                            class="text-2xl font-bold text-gray-900 cursor-pointer hover:text-[#714B67] transition-colors leading-snug">{{ $ticket->name }}</h1>
                        <form x-show="editing" method="POST" action="{{ route('workflow.tickets.save-field', $ticket) }}" style="display:none">
                            @csrf @method('PATCH')
                            <input type="hidden" name="field" value="name">
                            <input type="text" name="value" x-model="val" @keydown.escape="editing=false"
                                   class="w-full text-2xl font-bold text-gray-900 border-0 border-b-2 border-[#714B67] focus:outline-none bg-transparent pb-0.5">
                            <div class="flex gap-2 mt-2">
                                <button type="submit" class="px-3 py-1 text-xs bg-[#714B67] text-white rounded hover:bg-[#5c3d55]">{{ __('common.save') }}</button>
                                <button type="button" @click="editing=false" class="px-3 py-1 text-xs border border-gray-200 rounded hover:bg-gray-50">{{ __('common.cancel') }}</button>
                            </div>
                        </form>
                    </div>
                    @else
                    <h1 class="text-2xl font-bold text-gray-900 mb-2 leading-snug">{{ $ticket->name }}</h1>
                    @endcan

                    {{-- Editable description --}}
                    @can('update', $ticket)
                    <div x-data="{ editing: false, val: @js($ticket->description ?? '') }" class="mb-4">
                        <div x-show="!editing" @click="editing=true"
                             class="text-sm text-gray-400 cursor-pointer hover:text-gray-600 transition-colors leading-relaxed min-h-5"
                             x-text="val || 'Add a description…'"></div>
                        <form x-show="editing" method="POST" action="{{ route('workflow.tickets.save-field', $ticket) }}" style="display:none">
                            @csrf @method('PATCH')
                            <input type="hidden" name="field" value="description">
                            <textarea name="value" x-model="val" rows="2" @keydown.escape="editing=false"
                                      class="w-full text-sm text-gray-700 border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#714B67] resize-none"></textarea>
                            <div class="flex gap-2 mt-2">
                                <button type="submit" class="px-3 py-1 text-xs bg-[#714B67] text-white rounded hover:bg-[#5c3d55]">{{ __('common.save') }}</button>
                                <button type="button" @click="editing=false" class="px-3 py-1 text-xs border border-gray-200 rounded hover:bg-gray-50">{{ __('common.cancel') }}</button>
                            </div>
                        </form>
                    </div>
                    @elseif($ticket->description)
                    <p class="text-sm text-gray-400 mb-4 leading-relaxed">{{ $ticket->description }}</p>
                    @else
                    <div class="mb-4"></div>
                    @endcan

                    {{-- Meta chips --}}
                    <div class="flex flex-wrap items-center gap-2">

                        {{-- Department chip --}}
                        @can('update', $ticket)
                        <div x-data="{ open: false }" class="relative" @click.outside="open=false">
                            <button @click="open=!open"
                                    class="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full border transition-colors
                                           {{ $ticket->assignedDepartment ? 'bg-purple-50 border-purple-200 text-purple-700 hover:bg-purple-100' : 'bg-gray-50 border-gray-200 text-gray-500 hover:bg-gray-100' }}">
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                {{ $ticket->assignedDepartment?->name ?? __('workflow.department_label') }}
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-transition style="display:none"
                                 class="absolute left-0 top-full mt-1.5 w-64 bg-white rounded-xl shadow-xl border border-gray-100 z-30 p-3">
                                <form method="POST" action="{{ route('workflow.tickets.save-field', $ticket) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="field" value="assigned_to_department_id">
                                    <div class="mb-3">
                                        <x-relation-dropdown
                                            table="hr_departments"
                                            field="name"
                                            name="value"
                                            label=""
                                            :selected="$ticket->assigned_to_department_id ? [$ticket->assigned_to_department_id] : []"
                                            relation="many2one"
                                            :compact="true"
                                        />
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" class="flex-1 py-1.5 text-xs bg-[#714B67] text-white rounded-lg hover:bg-[#5c3d55]">{{ __('common.save_short') }}</button>
                                        <button type="button" @click="open=false" class="flex-1 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50">{{ __('common.cancel') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        @else
                        <span class="text-xs px-3 py-1.5 rounded-full bg-gray-50 border border-gray-200 text-gray-500">
                            {{ $ticket->assignedDepartment?->name ?? __('workflow.no_department') }}
                        </span>
                        @endcan

                        {{-- Assigned To chip --}}
                        @can('update', $ticket)
                        <div x-data="{ open: false }" class="relative" @click.outside="open=false">
                            <button @click="open=!open"
                                    class="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full border transition-colors
                                           {{ $ticket->assignedUser ? 'bg-blue-50 border-blue-200 text-blue-700 hover:bg-blue-100' : 'bg-gray-50 border-gray-200 text-gray-500 hover:bg-gray-100' }}">
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ $ticket->assignedUser?->name ?? __('workflow.unassigned') }}
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-transition style="display:none"
                                 class="absolute left-0 top-full mt-1.5 w-64 bg-white rounded-xl shadow-xl border border-gray-100 z-30 p-3">
                                <form method="POST" action="{{ route('workflow.tickets.save-field', $ticket) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="field" value="assigned_to_user_id">
                                    <div class="mb-3">
                                        <x-relation-dropdown
                                            table="users"
                                            field="name"
                                            name="value"
                                            label=""
                                            :selected="$ticket->assigned_to_user_id ? [$ticket->assigned_to_user_id] : []"
                                            relation="many2one"
                                            :compact="true"
                                            lookup-url-override="{{ route('workflow.tickets.viewers-lookup', $ticket) }}"
                                        />
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" class="flex-1 py-1.5 text-xs bg-[#714B67] text-white rounded-lg hover:bg-[#5c3d55]">{{ __('common.save_short') }}</button>
                                        <button type="button" @click="open=false" class="flex-1 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50">{{ __('common.cancel') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        @if($ticket->assigned_to_user_id !== auth()->id())
                        <form method="POST" action="{{ route('workflow.tickets.save-field', $ticket) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="field" value="assigned_to_user_id">
                            <input type="hidden" name="value" value="{{ auth()->id() }}">
                            <button type="submit"
                                    class="flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-full border border-dashed border-gray-300 text-gray-400 hover:border-[#714B67] hover:text-[#714B67] transition-colors">
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ __('workflow.assign_to_me') }}
                            </button>
                        </form>
                        @endif

                        @if($ticket->assigned_to_user_id)
                        <form method="POST" action="{{ route('workflow.tickets.save-field', $ticket) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="field" value="assigned_to_user_id">
                            <input type="hidden" name="value" value="">
                            <button type="submit"
                                    class="text-xs px-2.5 py-1.5 rounded-full border border-dashed border-gray-300 text-gray-400 hover:border-red-300 hover:text-red-400 transition-colors">
                                {{ __('workflow.unassign') }}
                            </button>
                        </form>
                        @endif

                        @else
                        <span class="text-xs px-3 py-1.5 rounded-full bg-gray-50 border border-gray-200 text-gray-500">
                            {{ $ticket->assignedUser?->name ?? __('workflow.unassigned') }}
                        </span>
                        @endcan

                        {{-- Priority stars --}}
                        @can('update', $ticket)
                        <div x-data="{ hover: 0, cur: {{ (int)$ticket->priority }} }" class="flex items-center gap-0.5">
                            <form id="pf-{{ $ticket->id }}" method="POST" action="{{ route('workflow.tickets.save-field', $ticket) }}" style="display:none">
                                @csrf @method('PATCH')
                                <input type="hidden" name="field" value="priority">
                                <input type="hidden" name="value" id="pv-{{ $ticket->id }}" value="{{ $ticket->priority }}">
                            </form>
                            @foreach([1, 2, 3] as $star)
                            <button type="button"
                                    @mouseover="hover={{ $star }}"
                                    @mouseleave="hover=0"
                                    @click="cur={{ $star }}; document.getElementById('pv-{{ $ticket->id }}').value={{ $star }}; document.getElementById('pf-{{ $ticket->id }}').submit()"
                                    class="p-0.5 transition-transform hover:scale-125"
                                    title="{{ ['', __('workflow.priority_normal'), __('workflow.priority_medium'), __('workflow.priority_high')][$star] }}">
                                <svg class="w-4 h-4 transition-colors"
                                     :class="(hover > 0 ? hover >= {{ $star }} : cur >= {{ $star }}) ? 'text-amber-400' : 'text-gray-200'"
                                     fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                            </button>
                            @endforeach
                        </div>
                        @else
                        <div class="flex items-center gap-0.5">
                            @foreach([1, 2, 3] as $star)
                            <svg class="w-4 h-4 {{ $ticket->priority >= $star ? 'text-amber-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            @endforeach
                        </div>
                        @endcan

                        {{-- Deadline chip (read-only) --}}
                        @if($ticket->resolve_deadline)
                        <span class="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full border {{ $ticket->isOverdue() ? 'bg-red-50 border-red-200 text-red-600' : 'bg-gray-50 border-gray-200 text-gray-500' }}">
                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ $ticket->resolve_deadline->format('M j, Y') }}
                        </span>
                        @endif
                    </div>
                </div>

                {{-- Path choice panel --}}
                @if($ticket->has_path_choice && $ticket->procedure_id)
                @php $pathChoices = $ticket->pathChoices; @endphp
                <div class="px-7 py-4 border-b border-gray-100 bg-amber-50">
                    <div class="flex items-start gap-3">
                        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-amber-800 mb-1">
                                {{ $ticket->path_choice_question ?: __('workflow.select_path_to_continue') }}
                                @if($ticket->path_choice_required)<span class="text-red-500 ml-0.5">*</span>@endif
                            </p>
                            @if($ticket->path_chosen_id)
                            @php $chosen = $pathChoices->firstWhere('id', $ticket->path_chosen_id); @endphp
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full bg-green-100 text-green-800 border border-green-200">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    {{ $chosen?->name ?? __('workflow.path_selected') }}
                                </span>
                                @can('act', $ticket)
                                @if($ticket->state === 'pending')
                                <span class="text-xs text-amber-600">{{ __('workflow.can_change_selection') }}</span>
                                @endif
                                @endcan
                            </div>
                            @endif
                            @can('act', $ticket)
                            @if($ticket->state === 'pending')
                            <div class="flex flex-wrap gap-2 mt-2">
                                @foreach($pathChoices as $choice)
                                <form method="POST" action="{{ route('workflow.procedures.tickets.path', [$ticket->procedure, $ticket]) }}">
                                    @csrf
                                    <input type="hidden" name="path_id" value="{{ $choice->id }}">
                                    <button type="submit"
                                            class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors
                                                   {{ $ticket->path_chosen_id === $choice->id
                                                       ? 'bg-[#714B67] text-white border-[#714B67]'
                                                       : 'bg-white text-gray-700 border-gray-300 hover:border-[#714B67] hover:text-[#714B67]' }}">
                                        {{ $choice->name }}
                                    </button>
                                </form>
                                @endforeach
                            </div>
                            @endif
                            @endcan
                        </div>
                    </div>
                </div>
                @endif

                {{-- Sub-procedures panel --}}
                @if($ticket->has_procedures && $ticket->procedureLines->isNotEmpty())
                <div class="px-7 py-4 border-b border-gray-100">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            {{ __('workflow.sub_procedures') }}
                            @if($ticket->procedures_required)<span class="text-red-400 ml-0.5">*</span>@endif
                        </span>
                    </div>
                    <div class="flex flex-col gap-2">
                        @foreach($ticket->procedureLines as $line)
                        @php
                            $proc       = $line->procedure;
                            $canStart   = !$proc || $proc->state === 'closed';
                            $stateColor = match($proc?->state) {
                                'completed' => 'bg-green-100 text-green-700',
                                'closed'    => 'bg-red-100 text-red-600',
                                'pending'   => 'bg-blue-100 text-blue-700',
                                default     => 'bg-gray-100 text-gray-500',
                            };
                            $stateLabel = match($proc?->state) {
                                'completed' => __('workflow.completed_label'),
                                'closed'    => __('workflow.cancelled_label'),
                                'pending'   => __('workflow.in_progress_label'),
                                default     => null,
                            };
                        @endphp
                        <div class="flex items-center gap-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                            <div class="flex-1 min-w-0">
                                <span class="text-sm font-medium text-gray-800">{{ $line->name }}</span>
                            </div>
                            @if($proc && $stateLabel)
                            <a href="{{ route('workflow.procedures.show', $proc) }}"
                               class="shrink-0 inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full {{ $stateColor }} hover:opacity-80 transition-opacity">
                                {{ $stateLabel }}
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                            @endif
                            @can('act', $ticket)
                            @if($canStart && $ticket->state === 'pending')
                            <form method="POST" action="{{ route('workflow.tickets.sub-procedures.start', [$ticket, $line]) }}">
                                @csrf
                                <button type="submit"
                                        class="shrink-0 px-2.5 py-1 text-xs font-medium text-[#714B67] border border-[#714B67] rounded-lg hover:bg-[#714B67] hover:text-white transition-colors">
                                    {{ $proc?->state === 'closed' ? __('workflow.restart') : __('workflow.start') }}
                                </button>
                            </form>
                            @endif
                            @endcan
                        </div>
                        @endforeach
                    </div>
                    @if($ticket->procedures_required && !$ticket->hasAllProcedureLinesCompleted())
                    <p class="mt-2 text-xs text-red-500">{{ __('workflow.all_sub_procedures_required') }}</p>
                    @endif
                </div>
                @endif

                {{-- Tab bar --}}
                <div class="flex border-b border-gray-100 px-7">
                    @if($ticketInputDefs && $ticketInputDefs->isNotEmpty())
                    <button @click="tab='fields'"
                            :class="tab==='fields' ? 'border-b-2 border-[#714B67] text-[#714B67] font-semibold' : 'text-gray-400 hover:text-gray-600'"
                            class="px-4 py-3 text-sm transition-colors -mb-px flex items-center gap-1.5">
                        {{ __('workflow.tab_fields') }}
                        <span class="text-xs bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5 font-normal">{{ $ticketInputDefs->count() }}</span>
                    </button>
                    @endif
                    <button @click="tab='details'"
                            :class="tab==='details' ? 'border-b-2 border-[#714B67] text-[#714B67] font-semibold' : 'text-gray-400 hover:text-gray-600'"
                            class="px-4 py-3 text-sm transition-colors -mb-px">
                        {{ __('workflow.tab_details') }}
                    </button>
                    <button @click="tab='viewers'"
                            :class="tab==='viewers' ? 'border-b-2 border-[#714B67] text-[#714B67] font-semibold' : 'text-gray-400 hover:text-gray-600'"
                            class="px-4 py-3 text-sm transition-colors -mb-px flex items-center gap-1.5">
                        {{ __('workflow.tab_viewers') }}
                        <span class="text-xs bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5 font-normal">{{ $ticket->viewers->count() }}</span>
                    </button>
                    <button @click="tab='activity'"
                            :class="tab==='activity' ? 'border-b-2 border-[#714B67] text-[#714B67] font-semibold' : 'text-gray-400 hover:text-gray-600'"
                            class="px-4 py-3 text-sm transition-colors -mb-px">
                        {{ __('workflow.tab_activity') }}
                    </button>
                </div>

                {{-- Fields tab --}}
                @if($ticketInputDefs && $ticketInputDefs->isNotEmpty())
                <div x-show="tab==='fields'" style="display:none">
                    @can('update', $ticket)
                    <form id="ticket-fields-form" method="POST" action="{{ route('workflow.tickets.save-inputs', $ticket) }}"
                          enctype="multipart/form-data"
                          @change="$dispatch('fields-changed')" @input="$dispatch('fields-changed')">
                        @csrf @method('PATCH')
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-100">
                                    <th class="px-7 py-2.5 text-left text-xs font-medium text-gray-400 uppercase tracking-wide w-52">{{ __('workflow.field_name_col') }}</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('workflow.value_col') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($ticketInputDefs->sortBy('sort_order') as $templateInput)
                                @php $inp = $ticket->inputs->firstWhere('template_input_id', $templateInput->id); @endphp
                                <tr>
                                    <td class="px-7 py-3 text-sm text-gray-600">
                                        {{ $templateInput->name }}@if($templateInput->is_required)<span class="text-red-400 ml-0.5">*</span>@endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($templateInput->type === 'label')
                                        <span class="text-sm text-gray-300">—</span>
                                        @elseif($templateInput->type === 'select')
                                        <select name="inputs[{{ $templateInput->id }}]" class="text-sm border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#714B67] min-w-36">
                                            <option value="">{{ __('workflow.select_option') }}</option>
                                            @foreach($templateInput->options as $opt)
                                            <option value="{{ $opt->id }}" {{ $inp?->value_select_id == $opt->id ? 'selected' : '' }}>{{ $opt->name }}</option>
                                            @endforeach
                                        </select>
                                        @elseif($templateInput->type === 'boolean')
                                        <select name="inputs[{{ $templateInput->id }}]" class="text-sm border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#714B67]">
                                            <option value="0" {{ !$inp?->value_boolean ? 'selected' : '' }}>{{ __('common.no') }}</option>
                                            <option value="1" {{ $inp?->value_boolean ? 'selected' : '' }}>{{ __('common.yes') }}</option>
                                        </select>
                                        @elseif($templateInput->type === 'date')
                                        <input type="date" name="inputs[{{ $templateInput->id }}]" value="{{ $inp?->value_date?->format('Y-m-d') }}"
                                               class="text-sm border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#714B67]">
                                        @elseif($templateInput->type === 'datetime')
                                        <input type="datetime-local" name="inputs[{{ $templateInput->id }}]" value="{{ $inp?->value_datetime?->format('Y-m-d\TH:i') }}"
                                               class="text-sm border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#714B67]">
                                        @elseif($templateInput->type === 'int')
                                        <input type="number" name="inputs[{{ $templateInput->id }}]" value="{{ $inp?->value_int }}"
                                               class="text-sm border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#714B67] w-32" placeholder="—">
                                        @elseif($templateInput->type === 'float')
                                        <input type="number" step="any" name="inputs[{{ $templateInput->id }}]" value="{{ $inp?->value_float }}"
                                               class="text-sm border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#714B67] w-36" placeholder="—">
                                        @elseif($templateInput->type === 'textarea')
                                        <textarea name="inputs[{{ $templateInput->id }}]" rows="3"
                                                  class="text-sm border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#714B67] w-full resize-y"
                                                  placeholder="—">{{ $inp?->value_text }}</textarea>
                                        @elseif($templateInput->type === 'multiselect')
                                        <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                                            @foreach($templateInput->options as $opt)
                                            <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                                                <input type="checkbox"
                                                       name="inputs[{{ $templateInput->id }}][]"
                                                       value="{{ $opt->id }}"
                                                       {{ $inp && $inp->selectedOptions->contains('id', $opt->id) ? 'checked' : '' }}
                                                       class="rounded border-gray-300 text-[#714B67] focus:ring-[#714B67]">
                                                {{ $opt->name }}
                                            </label>
                                            @endforeach
                                        </div>
                                        @elseif($templateInput->type === 'file')
                                        @php $isImage = str_starts_with($inp?->value_file_mime ?? '', 'image/'); @endphp
                                        @if($inp?->value_file_path)
                                        <div x-data="{
                                            preview: null,
                                            newFileName: null,
                                            markedForDeletion: false,
                                            onFileChange(e) {
                                                const file = e.target.files[0];
                                                if (!file) { this.newFileName = null; this.preview = null; return; }
                                                this.newFileName = file.name;
                                                this.markedForDeletion = false;
                                                if (file.type.startsWith('image/')) {
                                                    const reader = new FileReader();
                                                    reader.onload = (ev) => { this.preview = ev.target.result; };
                                                    reader.readAsDataURL(file);
                                                } else {
                                                    this.preview = null;
                                                }
                                            }
                                        }" class="flex flex-col gap-1.5">
                                            <input type="hidden" name="inputs_delete[{{ $templateInput->id }}]" :value="markedForDeletion ? '1' : '0'">
                                            {{-- Marked for deletion state --}}
                                            <div x-show="markedForDeletion" style="display:none" class="flex items-center gap-2 py-1">
                                                <span class="text-xs text-red-500 italic">{{ __('workflow.will_be_removed_on_save') }}</span>
                                                <button type="button" @click="markedForDeletion = false"
                                                        class="text-xs text-[#714B67] underline hover:text-[#5c3d55]">{{ __('workflow.undo') }}</button>
                                            </div>
                                            {{-- Normal state --}}
                                            <div x-show="!markedForDeletion" class="flex items-start gap-3">
                                                @if($isImage)
                                                <div class="shrink-0">
                                                    <button type="button"
                                                            x-show="!newFileName || preview"
                                                            @click="$dispatch('lightbox-open', { url: preview || '{{ route('workflow.tickets.input-file', [$ticket, $inp]) }}', name: newFileName || @js($inp->value_file_name) })"
                                                            :class="preview ? 'border-amber-300 ring-2 ring-amber-200' : 'border-gray-200 hover:border-[#714B67]/50'"
                                                            class="rounded-lg overflow-hidden border transition-colors focus:outline-none">
                                                        <img :src="preview || '{{ route('workflow.tickets.input-file', [$ticket, $inp]) }}'"
                                                             :alt="newFileName || @js($inp->value_file_name)"
                                                             class="w-24 h-24 object-cover block">
                                                    </button>
                                                    <div x-show="newFileName && !preview" style="display:none"
                                                         class="w-24 h-24 rounded-lg border-2 border-amber-300 bg-gray-50 flex items-center justify-center text-gray-400">
                                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                    </div>
                                                </div>
                                                @endif
                                                <div class="flex flex-col gap-1.5 min-w-0">
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        @if($isImage)
                                                        <span class="text-xs text-gray-500 truncate max-w-xs" x-text="newFileName || @js($inp->value_file_name)"></span>
                                                        @else
                                                        <a href="{{ route('workflow.tickets.input-file', [$ticket, $inp]) }}" target="_blank"
                                                           x-show="!newFileName"
                                                           class="inline-flex items-center gap-1.5 text-xs text-purple-600 hover:underline truncate max-w-xs">
                                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                            {{ $inp->value_file_name }}
                                                        </a>
                                                        <span x-show="newFileName" style="display:none"
                                                              class="text-xs text-gray-500 truncate max-w-xs" x-text="newFileName"></span>
                                                        @endif
                                                        <span x-show="newFileName" style="display:none"
                                                              class="text-xs bg-amber-100 text-amber-600 border border-amber-200 px-1.5 py-0.5 rounded font-medium shrink-0">{{ __('workflow.unsaved') }}</span>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2">
                                                        <label class="px-2.5 py-1 text-xs font-medium text-[#714B67] border border-[#714B67]/40 rounded-lg hover:bg-[#714B67]/5 cursor-pointer transition-colors">
                                                            {{ __('workflow.replace') }}
                                                            <input type="file" name="inputs[{{ $templateInput->id }}]" class="sr-only" x-ref="replaceFile"
                                                                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.odt,.ods"
                                                                   @change="onFileChange($event)">
                                                        </label>
                                                        <button type="button"
                                                                @click="markedForDeletion = true; newFileName = null; preview = null; $refs.replaceFile.value = ''"
                                                                class="px-2.5 py-1 text-xs font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                                                            {{ __('workflow.delete_file') }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @else
                                        <div class="flex flex-col gap-1.5">
                                            <input type="file"
                                                   name="inputs[{{ $templateInput->id }}]"
                                                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.odt,.ods"
                                                   class="text-sm text-gray-600 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                                            <span class="text-xs text-gray-400">{{ __('workflow.file_max_info') }}</span>
                                        </div>
                                        @endif
                                        @else
                                        <input type="text" name="inputs[{{ $templateInput->id }}]" value="{{ $inp?->value_char }}"
                                               class="text-sm border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#714B67] min-w-48" placeholder="—">
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="px-7 py-3 bg-gray-50 border-t border-gray-100 flex justify-end">
                            <button type="submit" class="px-4 py-1.5 text-sm bg-[#714B67] hover:bg-[#5c3d55] text-white rounded-lg font-medium">{{ __('workflow.save_fields') }}</button>
                        </div>
                    </form>
                    @else
                    <table class="w-full">
                        <tbody class="divide-y divide-gray-50">
                            @foreach($ticketInputDefs->sortBy('sort_order') as $templateInput)
                            @php $inp = $ticket->inputs->firstWhere('template_input_id', $templateInput->id); @endphp
                            <tr>
                                <td class="px-7 py-3 text-sm text-gray-500 w-52">{{ $templateInput->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-800 font-medium">
                                    @if($templateInput->type === 'file' && $inp?->value_file_path)
                                        @php $roIsImage = str_starts_with($inp->value_file_mime ?? '', 'image/'); @endphp
                                        @if($roIsImage)
                                        <button type="button"
                                                @click="$dispatch('lightbox-open', { url: '{{ route('workflow.tickets.input-file', [$ticket, $inp]) }}', name: @js($inp->value_file_name) })"
                                                class="block rounded-lg overflow-hidden border border-gray-200 hover:border-[#714B67]/50 transition-colors focus:outline-none focus:ring-2 focus:ring-[#714B67]/30">
                                            <img src="{{ route('workflow.tickets.input-file', [$ticket, $inp]) }}"
                                                 alt="{{ $inp->value_file_name }}"
                                                 class="w-24 h-24 object-cover block">
                                        </button>
                                        @else
                                        <a href="{{ route('workflow.tickets.input-file', [$ticket, $inp]) }}"
                                           class="text-purple-600 hover:underline" target="_blank">{{ $inp->value_file_name }}</a>
                                        @endif
                                    @elseif($templateInput->type === 'textarea' && $inp?->value_text)
                                        <span class="whitespace-pre-wrap">{{ $inp->value_text }}</span>
                                    @else
                                        {{ $inp?->getResultValue() ?: '—' }}
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endcan
                </div>
                @endif

                {{-- Details tab --}}
                <div x-show="tab==='details'" style="display:none" class="px-7 py-6">
                    <dl class="grid grid-cols-2 gap-x-8 gap-y-5">
                        @if($ticket->return_reason)
                        <div class="col-span-2">
                            <dt class="text-xs font-semibold text-red-500 uppercase tracking-wide mb-1">{{ __('workflow.return_reason_label') }}</dt>
                            <dd class="text-sm text-red-800 bg-red-50 border border-red-200 rounded-lg px-3 py-2 leading-relaxed">{{ $ticket->return_reason }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">{{ __('workflow.details_template') }}</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $ticket->template?->name ?? '—' }}</dd>
                        </div>
                        @if($ticket->resolve_deadline)
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">{{ __('workflow.details_deadline') }}</dt>
                            <dd class="text-sm font-medium {{ $ticket->isOverdue() ? 'text-red-600' : 'text-gray-800' }}">{{ $ticket->resolve_deadline->format('M j, Y H:i') }}</dd>
                        </div>
                        @endif
                        @if($ticket->resolve_duration)
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">{{ __('workflow.details_duration') }}</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $ticket->resolve_duration }} h</dd>
                        </div>
                        @endif
                        @if($ticket->resolve_deadline_passed)
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">{{ __('workflow.details_sla_passed') }}</dt>
                            <dd class="text-sm font-medium text-red-600">{{ $ticket->resolve_deadline_passed }} h</dd>
                        </div>
                        @endif
                        @if($ticket->resolve_max_duration)
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">{{ __('workflow.details_sla_limit') }}</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $ticket->resolve_max_duration }} h</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">{{ __('workflow.details_created_by') }}</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $ticket->createdByUser?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">{{ __('workflow.details_created') }}</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $ticket->created_at->format('M j, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">{{ __('workflow.details_last_updated') }}</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $ticket->updated_at->diffForHumans() }}</dd>
                        </div>
                    </dl>

                    {{-- Share link --}}
                    @if($ticket->sharedLink?->enabled)
                    @can('update', $ticket)
                    <div class="mt-6 pt-6 border-t border-gray-100">
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('workflow.share_link') }}</div>
                        <div x-data="{ copied: false }" class="flex items-center gap-2 mb-3">
                            <input type="text" readonly value="{{ $ticket->sharedLink->shareUrl() }}"
                                   class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-gray-50 text-gray-600 select-all">
                            <button @click="navigator.clipboard.writeText('{{ $ticket->sharedLink->shareUrl() }}'); copied=true; setTimeout(()=>copied=false,2000)"
                                    class="px-3 py-1.5 text-sm border rounded-lg transition-colors shrink-0"
                                    :class="copied ? 'border-green-300 text-green-700 bg-green-50' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                                <span x-text="copied ? @js(__('workflow.copied')) : @js(__('workflow.copy'))"></span>
                            </button>
                        </div>
                        <form method="POST" action="{{ route('workflow.share.ticket.message', $ticket) }}">
                            @csrf @method('PATCH')
                            <textarea name="message" rows="2" placeholder="{{ __('workflow.message_for_recipient') }}"
                                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#714B67] resize-none mb-2">{{ $ticket->sharedLink->message }}</textarea>
                            <button type="submit" class="px-3 py-1.5 text-sm bg-[#714B67] hover:bg-[#5c3d55] text-white rounded-lg">{{ __('workflow.save_message') }}</button>
                        </form>
                    </div>
                    @endcan
                    @endif
                </div>

                {{-- Viewers tab --}}
                <div x-show="tab==='viewers'" style="display:none" class="px-7 py-5">
                    <div class="divide-y divide-gray-50">
                        @foreach($ticket->viewers->sortBy('name') as $viewer)
                        <div class="flex items-center gap-3 py-2.5">
                            <div class="w-8 h-8 rounded-full bg-[#714B67] flex items-center justify-center text-white text-sm font-bold shrink-0">{{ strtoupper(substr($viewer->name,0,1)) }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-800 truncate">{{ $viewer->name }}</div>
                                @if($viewer->id===$ticket->created_by_user_id)<div class="text-xs text-gray-400">{{ __('workflow.creator') }}</div>
                                @elseif($viewer->id===$ticket->assigned_to_user_id)<div class="text-xs text-gray-400">{{ __('workflow.assignee') }}</div>@endif
                            </div>
                            @can('update', $ticket)
                            @if($viewer->id !== $ticket->created_by_user_id)
                            <form method="POST" action="{{ route('workflow.tickets.remove-viewer', [$ticket, $viewer]) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-gray-300 hover:text-red-400 transition-colors p-1" title="{{ __('workflow.remove') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </form>
                            @endif
                            @endcan
                        </div>
                        @endforeach
                    </div>
                    @can('update', $ticket)
                    <div class="mt-4 pt-4 border-t border-gray-100" x-data="{ adding: false }">
                        <button type="button" @click="adding=!adding"
                                class="flex items-center gap-1.5 text-sm text-[#714B67] hover:text-[#5c3d55] font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            {{ __('workflow.add_person') }}
                        </button>
                        <div x-show="adding" x-transition style="display:none" class="mt-3 relative z-30">
                            <form method="POST" action="{{ route('workflow.tickets.add-viewer', $ticket) }}">
                                @csrf
                                @php $existingViewerIds = $ticket->viewers->pluck('id')->toArray(); @endphp
                                <x-relation-dropdown table="users" field="name" name="user_id" label="" :selected="[]" relation="many2one" :except="$existingViewerIds" :compact="true"/>
                                <button type="submit" class="mt-2 px-4 py-1.5 text-sm bg-[#714B67] hover:bg-[#5c3d55] text-white rounded-lg font-medium">{{ __('workflow.add_viewer_btn') }}</button>
                            </form>
                        </div>
                    </div>
                    @endcan
                </div>

                {{-- Activity tab --}}
                <div x-show="tab==='activity'" style="display:none">
                    <x-chatter
                        model-type="App\Models\Workflow\Ticket"
                        :model-id="$ticket->id"
                        :can-comment="\Illuminate\Support\Facades\Auth::user()->can('comment', $ticket)"
                    />
                </div>

            </div>
            {{-- END LEFT --}}

            {{-- ─── RIGHT: chat + viewers ─── --}}
            <div class="w-80 shrink-0 flex flex-col gap-4">

                {{-- Chat --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col" style="height:500px">
                    <div class="px-5 py-3 border-b border-gray-100 shrink-0 flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-800">{{ __('workflow.chat_label') }}</span>
                        @if(!empty($chatGrouped))
                        <span class="text-xs text-gray-400">{{ count($chatGrouped) }} {{ __('workflow.messages_label') }}</span>
                        @endif
                    </div>

                    <div class="flex-1 overflow-y-auto" id="ticket-chat-messages">
                        @forelse($chatGrouped as $item)
                        @php $msg = $item['message']; $isOwn = $msg->user_id === auth()->id(); @endphp

                        @if($item['show_date'])
                        <div class="flex items-center gap-2 px-5 py-3">
                            <div class="flex-1 h-px bg-gray-100"></div>
                            <span class="text-xs text-gray-400 shrink-0">{{ $item['date_label'] }}</span>
                            <div class="flex-1 h-px bg-gray-100"></div>
                        </div>
                        @endif

                        <div class="px-5 {{ $item['show_header'] ? 'pt-3' : 'pt-0.5' }} pb-0">
                            <div class="flex items-start gap-2.5">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5
                                            {{ $isOwn ? 'bg-[#714B67] text-white' : 'bg-gray-200 text-gray-600' }}"
                                     style="{{ !$item['show_header'] ? 'visibility:hidden' : '' }}">
                                    {{ $msg->user ? strtoupper(substr($msg->user->name, 0, 1)) : '?' }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    @if($item['show_header'])
                                    <div class="flex items-baseline gap-2 mb-0.5">
                                        <span class="text-xs font-bold {{ $isOwn ? 'text-[#714B67]' : 'text-gray-800' }}">{{ $msg->user?->name ?? __('workflow.deleted_user') }}</span>
                                        <span class="text-xs text-gray-400">{{ $msg->created_at->format('g:i A') }}</span>
                                    </div>
                                    @endif
                                    @if($msg->body)
                                    <p class="text-sm text-gray-700 leading-relaxed">{{ $msg->body }}</p>
                                    @endif
                                    @if($msg->files->isNotEmpty())
                                    <div class="mt-1.5 flex flex-col gap-1.5">
                                        @foreach($msg->files as $f)
                                        @if($f->isImage())
                                        <a href="{{ route('files.serve', $f->path) }}" target="_blank"
                                           class="block rounded-lg overflow-hidden border border-gray-100 hover:border-[#714B67]/30 transition-colors w-fit">
                                            <img src="{{ route('files.serve', $f->path) }}" alt="{{ $f->original_name }}" class="max-w-full max-h-40 object-cover block">
                                        </a>
                                        @else
                                        <a href="{{ route('files.serve', $f->path) }}"
                                           class="inline-flex items-center gap-2 px-2.5 py-1.5 bg-gray-50 hover:bg-[#714B67]/5 border border-gray-200 hover:border-[#714B67]/30 rounded-lg transition-colors">
                                            <svg class="w-3.5 h-3.5 text-[#714B67] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                            <span class="text-xs text-gray-700 truncate max-w-32">{{ $f->original_name }}</span>
                                            <span class="text-xs text-gray-400 shrink-0">{{ $f->humanSize() }}</span>
                                        </a>
                                        @endif
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="h-full flex flex-col items-center justify-center text-center px-5 py-8">
                            <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            </div>
                            <p class="text-sm font-medium text-gray-400">{{ __('workflow.no_messages_yet') }}</p>
                            <p class="text-xs text-gray-300 mt-0.5">{{ __('workflow.start_conversation') }}</p>
                        </div>
                        @endforelse
                    </div>

                    @can('update', $ticket)
                    <div class="shrink-0 border-t border-gray-100 px-4 py-3 bg-white"
                         x-data="{
                             previews:[],
                             allowed:['image/jpeg','image/png','image/gif','image/webp','application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','text/plain','text/csv'],
                             handleFiles(e){
                                 this.previews=[];
                                 Array.from(e.target.files).forEach(f=>{
                                     if(!this.allowed.includes(f.type)||f.size>10485760)return;
                                     this.previews.push({name:f.name});
                                 });
                             },
                             clear(){this.previews=[];this.$refs.fi.value='';}
                         }">
                        <form method="POST" action="{{ route('workflow.tickets.chat.store', $ticket) }}" enctype="multipart/form-data">
                            @csrf
                            <div x-show="previews.length>0" x-transition style="display:none" class="flex flex-wrap gap-1 mb-2">
                                <template x-for="(f,i) in previews" :key="i">
                                    <span class="text-xs bg-purple-50 text-purple-700 border border-purple-200 rounded px-2 py-0.5 truncate max-w-28" x-text="f.name"></span>
                                </template>
                                <button type="button" @click="clear()" class="text-xs text-gray-400 hover:text-red-400  ms-auto">✕</button>
                            </div>
                            <input type="file" name="files[]" multiple x-ref="fi" @change="handleFiles"
                                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" class="hidden">
                            <div class="flex items-end gap-1.5 bg-gray-50 border border-gray-200 rounded-xl px-2 py-1.5
                                        focus-within:bg-white focus-within:border-[#714B67]/40 focus-within:ring-2 focus-within:ring-[#714B67]/10 transition-all">
                                <button type="button" @click="$refs.fi.click()"
                                        class="shrink-0 w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-[#714B67] hover:bg-gray-100 transition-colors self-end mb-0.5"
                                        title="{{ __('workflow.attach_file_hint') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                </button>
                                <textarea name="body" rows="1" placeholder="{{ __('workflow.message_placeholder') }}"
                                          class="flex-1 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 resize-none leading-relaxed py-1 text-gray-800 placeholder-gray-400 min-w-0"
                                          @keydown.enter.prevent.exact="$el.closest('form').requestSubmit()"
                                          @input="$el.style.height='auto';$el.style.height=Math.min($el.scrollHeight,96)+'px'"></textarea>
                                <button type="submit"
                                        class="shrink-0 w-7 h-7 flex items-center justify-center rounded-lg bg-[#714B67] hover:bg-[#5c3d55] text-white transition-colors self-end mb-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                </button>
                            </div>
                        </form>
                    </div>
                    @endcan
                </div>


            </div>
            {{-- END RIGHT --}}

        </div>
    </div>
</div>

{{-- Unsaved fields confirm modal --}}
<div x-show="showSaveConfirm" x-transition style="display:none"
     class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50"
     @click.self="showSaveConfirm = false">
    <div class="bg-white rounded-xl shadow-2xl border border-gray-100 w-96 mx-4 p-6">
        <div class="flex items-start gap-3 mb-5">
            <div class="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-900">{{ __('workflow.unsaved_field_changes') }}</h3>
                <p class="text-sm text-gray-500 mt-0.5 leading-relaxed">{{ __('workflow.unsaved_fields_body') }}</p>
            </div>
        </div>
        <div class="flex flex-col gap-2">
            <button type="button" @click="saveAndComplete()"
                    class="w-full px-4 py-2 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded-lg transition-colors">
                {{ __('workflow.save_fields_and_complete') }}
            </button>
            <button type="button" @click="$refs.completeForm.submit(); showSaveConfirm = false"
                    class="w-full px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                {{ __('workflow.complete_without_saving') }}
            </button>
            <button type="button" @click="showSaveConfirm = false"
                    class="w-full px-4 py-2 text-sm text-gray-400 hover:text-gray-600 transition-colors">
                {{ __('common.cancel') }}
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const chat = document.getElementById('ticket-chat-messages');
        if (chat) chat.scrollTop = chat.scrollHeight;
    });
</script>

{{-- Image lightbox --}}
<div x-data="{ open: false, url: '', name: '' }"
     @lightbox-open.window="open = true; url = $event.detail.url; name = $event.detail.name"
     x-show="open"
     x-transition.opacity
     style="display:none"
     class="fixed inset-0 bg-black/85 backdrop-blur-sm flex items-center justify-center z-50 p-6"
     @click.self="open = false"
     @keydown.escape.window="open = false">
    <div class="flex flex-col items-center gap-3 max-w-5xl max-h-full w-full">
        <div class="flex items-center justify-between w-full">
            <span class="text-white/80 text-sm truncate max-w-sm" x-text="name"></span>
            <div class="flex items-center gap-2 shrink-0">
                <a :href="url" :download="name"
                   class="px-3 py-1.5 text-xs font-medium text-white border border-white/30 rounded-lg hover:bg-white/10 transition-colors">
                    {{ __('workflow.download') }}
                </a>
                <button @click="open = false"
                        class="w-8 h-8 flex items-center justify-center text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
        <img :src="url" :alt="name" class="max-w-full max-h-[80vh] object-contain rounded-xl shadow-2xl">
    </div>
</div>
@endsection
