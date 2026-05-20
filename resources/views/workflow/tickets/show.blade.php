@extends('layouts.app')
@section('title', $ticket->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">

    {{-- Top bar --}}
    <div class="bg-white border-b border-gray-200 px-5 py-2 flex items-center gap-3 shrink-0">
        <div class="flex items-center gap-2 shrink-0">
            @can('create', \App\Models\Workflow\Ticket::class)
            <a href="{{ route('workflow.tickets.create') }}" class="px-3 py-1.5 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-medium rounded">New</a>
            @endcan
            <div>
                <div class="flex items-center gap-1 text-xs text-purple-600">
                    <a href="{{ route('workflow.tickets.index') }}" class="hover:text-purple-700">Tickets</a>
                    @if($ticket->procedure)
                    <span class="text-gray-300">/</span>
                    <a href="{{ route('workflow.procedures.show', $ticket->procedure) }}" class="hover:text-purple-700 truncate max-w-36">{{ $ticket->procedure->name }}</a>
                    @endif
                </div>
                <span class="text-sm font-semibold text-gray-800 leading-tight block truncate max-w-xs">{{ $ticket->name }}</span>
            </div>
        </div>

        <div class="ml-auto flex items-center gap-2 shrink-0">
            @can('update', $ticket)
            @if($ticket->state === 'pending')
            @php $pathRequired = $ticket->has_path_choice && $ticket->path_choice_required && !$ticket->path_chosen_id; @endphp
            <form method="POST" action="{{ route('workflow.tickets.resolve', $ticket) }}">@csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm font-medium rounded border
                               {{ $pathRequired ? 'text-gray-400 border-gray-200 cursor-not-allowed' : 'text-green-700 border-green-300 hover:bg-green-50' }}"
                        @if($pathRequired) title="Select a path before completing" @endif>
                    Mark Completed
                </button>
            </form>
            @if($ticket->procedure_id && $ticket->previous_ticket_id)
            <div x-data="{ open: false }" class="relative" @click.outside="open=false">
                <button @click="open=!open" class="px-3 py-1.5 text-sm text-red-700 border border-red-300 rounded hover:bg-red-50">Return</button>
                <div x-show="open" x-transition style="display:none"
                     class="absolute right-0 top-full mt-1.5 w-80 bg-white rounded-xl shadow-xl border border-gray-200 z-30 p-4">
                    <form method="POST" action="{{ route('workflow.tickets.close', $ticket) }}">
                        @csrf @method('PATCH')
                        @if($previousChain->count() > 1)
                        <div class="mb-3">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Return to <span class="text-red-500">*</span></label>
                            <select name="return_to_ticket_id"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-red-300">
                                @foreach($previousChain as $prev)
                                <option value="{{ $prev->id }}">{{ $prev->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="mb-0">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Reason <span class="text-red-500">*</span></label>
                            <textarea name="return_reason" required rows="3"
                                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-300 resize-none"
                                      placeholder="Explain why this ticket is being returned..."></textarea>
                        </div>
                        <button type="submit"
                                class="mt-2 w-full px-3 py-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg">
                            Confirm Return
                        </button>
                    </form>
                </div>
            </div>
            @else
            <form method="POST" action="{{ route('workflow.tickets.close', $ticket) }}">@csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Close</button>
            </form>
            @endif
            @elseif(in_array($ticket->state, ['completed', 'closed']))
            <form method="POST" action="{{ route('workflow.tickets.reopen', $ticket) }}">@csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-blue-700 border border-blue-200 rounded hover:bg-blue-50">Reopen</button>
            </form>
            @endif
            @if($ticket->state !== 'pending')
            @if($ticket->active)
            <form method="POST" action="{{ route('workflow.tickets.archive', $ticket) }}">@csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">Archive</button>
            </form>
            @else
            <form method="POST" action="{{ route('workflow.tickets.unarchive', $ticket) }}">@csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50">Restore</button>
            </form>
            @endif
            @endif
            @if($ticket->sharedLink?->enabled)
            <div x-data="{ open: false, copied: false }" class="relative" @click.outside="open=false">
                <button @click="open=!open"
                        class="px-3 py-1.5 text-sm border rounded flex items-center gap-1.5 text-green-700 border-green-300 bg-green-50 hover:bg-green-100">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    Sharing On
                </button>
                <div x-show="open" x-transition style="display:none"
                     class="absolute right-0 top-full mt-1.5 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-30 p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Share Link</p>
                    <div class="flex items-center gap-2 mb-3">
                        <input type="text" readonly value="{{ $ticket->sharedLink->shareUrl() }}"
                               class="flex-1 text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-gray-50 text-gray-600 select-all min-w-0">
                        <button @click="navigator.clipboard.writeText('{{ $ticket->sharedLink->shareUrl() }}'); copied=true; setTimeout(()=>copied=false,2000)"
                                class="shrink-0 px-2.5 py-1.5 text-xs border rounded-lg transition-colors"
                                :class="copied ? 'border-green-300 text-green-700 bg-green-50' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                            <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('workflow.share.ticket.toggle', $ticket) }}">@csrf
                        <button type="submit" class="w-full py-1.5 text-xs text-red-600 border border-red-200 rounded-lg hover:bg-red-50">
                            Turn off sharing
                        </button>
                    </form>
                </div>
            </div>
            @else
            <form method="POST" action="{{ route('workflow.share.ticket.toggle', $ticket) }}">@csrf
                <button class="px-3 py-1.5 text-sm border rounded flex items-center gap-1.5 text-gray-500 border-gray-200 hover:bg-gray-50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    Share
                </button>
            </form>
            @endif
            @endcan
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-5">

        @if(!$ticket->active)
        <div class="mb-4 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2">This ticket is archived.</div>
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
            <div class="relative flex-1 min-w-0 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden"
                 x-data="{ tab: @js($defaultTab) }">
                <x-state-ribbon :state="$ticket->state" />

                {{-- Header --}}
                <div class="px-7 pt-6 pb-5 border-b border-gray-100">

                    {{-- Status row --}}
                    <div class="flex items-center gap-2 mb-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $ticket->stateColor() }}">{{ $ticket->stateLabel() }}</span>
                        @if($ticket->isOverdue())<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-600">Overdue</span>@endif
                        <span class="ml-auto text-xs text-gray-300 font-mono">#{{ $ticket->id }}</span>
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
                                <button type="submit" class="px-3 py-1 text-xs bg-[#714B67] text-white rounded hover:bg-[#5c3d55]">Save</button>
                                <button type="button" @click="editing=false" class="px-3 py-1 text-xs border border-gray-200 rounded hover:bg-gray-50">Cancel</button>
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
                                <button type="submit" class="px-3 py-1 text-xs bg-[#714B67] text-white rounded hover:bg-[#5c3d55]">Save</button>
                                <button type="button" @click="editing=false" class="px-3 py-1 text-xs border border-gray-200 rounded hover:bg-gray-50">Cancel</button>
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
                                {{ $ticket->assignedDepartment?->name ?? 'Department' }}
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-transition style="display:none"
                                 class="absolute left-0 top-full mt-1.5 w-64 bg-white rounded-xl shadow-xl border border-gray-100 z-30 p-3">
                                <form method="POST" action="{{ route('workflow.tickets.save-field', $ticket) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="field" value="assigned_to_department_id">
                                    <div class="mb-3">
                                        <x-relation-dropdown
                                            table="workflow_departments"
                                            field="name"
                                            name="value"
                                            label=""
                                            :selected="$ticket->assigned_to_department_id ? [$ticket->assigned_to_department_id] : []"
                                            relation="many2one"
                                            :compact="true"
                                        />
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" class="flex-1 py-1.5 text-xs bg-[#714B67] text-white rounded-lg hover:bg-[#5c3d55]">Save</button>
                                        <button type="button" @click="open=false" class="flex-1 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        @else
                        <span class="text-xs px-3 py-1.5 rounded-full bg-gray-50 border border-gray-200 text-gray-500">
                            {{ $ticket->assignedDepartment?->name ?? 'No department' }}
                        </span>
                        @endcan

                        {{-- Assigned To chip --}}
                        @can('update', $ticket)
                        <div x-data="{ open: false }" class="relative" @click.outside="open=false">
                            <button @click="open=!open"
                                    class="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full border transition-colors
                                           {{ $ticket->assignedUser ? 'bg-blue-50 border-blue-200 text-blue-700 hover:bg-blue-100' : 'bg-gray-50 border-gray-200 text-gray-500 hover:bg-gray-100' }}">
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ $ticket->assignedUser?->name ?? 'Unassigned' }}
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
                                        <button type="submit" class="flex-1 py-1.5 text-xs bg-[#714B67] text-white rounded-lg hover:bg-[#5c3d55]">Save</button>
                                        <button type="button" @click="open=false" class="flex-1 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
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
                                Assign to me
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
                                Unassign
                            </button>
                        </form>
                        @endif

                        @else
                        <span class="text-xs px-3 py-1.5 rounded-full bg-gray-50 border border-gray-200 text-gray-500">
                            {{ $ticket->assignedUser?->name ?? 'Unassigned' }}
                        </span>
                        @endcan

                        {{-- Priority chip --}}
                        @can('update', $ticket)
                        @php $priorityColors = ['1'=>'bg-gray-50 border-gray-200 text-gray-500','2'=>'bg-amber-50 border-amber-200 text-amber-700','3'=>'bg-red-50 border-red-200 text-red-700']; @endphp
                        <form method="POST" action="{{ route('workflow.tickets.save-field', $ticket) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="field" value="priority">
                            <div class="relative">
                                <select name="value" onchange="this.form.submit()"
                                        class="appearance-none text-xs pl-3 pr-7 py-1.5 rounded-full border cursor-pointer focus:outline-none transition-colors
                                               {{ $priorityColors[$ticket->priority] ?? 'bg-gray-50 border-gray-200 text-gray-500' }}">
                                    @foreach(['1'=>'Normal','2'=>'Medium','3'=>'High'] as $v=>$l)
                                    <option value="{{ $v }}" {{ $ticket->priority==$v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                                <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-2.5 h-2.5 text-current opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </form>
                        @else
                        <span class="text-xs px-3 py-1.5 rounded-full bg-gray-50 border border-gray-200 text-gray-500">{{ $ticket->priorityLabel() }}</span>
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
                                {{ $ticket->path_choice_question ?: 'Select a path to continue' }}
                                @if($ticket->path_choice_required)<span class="text-red-500 ml-0.5">*</span>@endif
                            </p>
                            @if($ticket->path_chosen_id)
                            @php $chosen = $pathChoices->firstWhere('id', $ticket->path_chosen_id); @endphp
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full bg-green-100 text-green-800 border border-green-200">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    {{ $chosen?->name ?? 'Path selected' }}
                                </span>
                                @can('act', $ticket)
                                @if($ticket->state === 'pending')
                                <span class="text-xs text-amber-600">(you can still change your selection below)</span>
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
                            Sub-procedures
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
                                'completed' => 'Completed',
                                'closed'    => 'Cancelled',
                                'pending'   => 'In Progress',
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
                                    {{ $proc?->state === 'closed' ? 'Restart' : 'Start' }}
                                </button>
                            </form>
                            @endif
                            @endcan
                        </div>
                        @endforeach
                    </div>
                    @if($ticket->procedures_required && !$ticket->hasAllProcedureLinesCompleted())
                    <p class="mt-2 text-xs text-red-500">All sub-procedures must be completed before this ticket can be completed.</p>
                    @endif
                </div>
                @endif

                {{-- Tab bar --}}
                <div class="flex border-b border-gray-100 px-7">
                    @if($ticketInputDefs && $ticketInputDefs->isNotEmpty())
                    <button @click="tab='fields'"
                            :class="tab==='fields' ? 'border-b-2 border-[#714B67] text-[#714B67] font-semibold' : 'text-gray-400 hover:text-gray-600'"
                            class="px-4 py-3 text-sm transition-colors -mb-px flex items-center gap-1.5">
                        Fields
                        <span class="text-xs bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5 font-normal">{{ $ticketInputDefs->count() }}</span>
                    </button>
                    @endif
                    <button @click="tab='details'"
                            :class="tab==='details' ? 'border-b-2 border-[#714B67] text-[#714B67] font-semibold' : 'text-gray-400 hover:text-gray-600'"
                            class="px-4 py-3 text-sm transition-colors -mb-px">
                        Details
                    </button>
                    <button @click="tab='viewers'"
                            :class="tab==='viewers' ? 'border-b-2 border-[#714B67] text-[#714B67] font-semibold' : 'text-gray-400 hover:text-gray-600'"
                            class="px-4 py-3 text-sm transition-colors -mb-px flex items-center gap-1.5">
                        Viewers
                        <span class="text-xs bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5 font-normal">{{ $ticket->viewers->count() }}</span>
                    </button>
                    <button @click="tab='activity'"
                            :class="tab==='activity' ? 'border-b-2 border-[#714B67] text-[#714B67] font-semibold' : 'text-gray-400 hover:text-gray-600'"
                            class="px-4 py-3 text-sm transition-colors -mb-px">
                        Activity
                    </button>
                </div>

                {{-- Fields tab --}}
                @if($ticketInputDefs && $ticketInputDefs->isNotEmpty())
                <div x-show="tab==='fields'" style="display:none">
                    @can('update', $ticket)
                    <form method="POST" action="{{ route('workflow.tickets.save-inputs', $ticket) }}">
                        @csrf @method('PATCH')
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-100">
                                    <th class="px-7 py-2.5 text-left text-xs font-medium text-gray-400 uppercase tracking-wide w-52">Field</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Value</th>
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
                                            <option value="">— Select —</option>
                                            @foreach($templateInput->options as $opt)
                                            <option value="{{ $opt->id }}" {{ $inp?->value_select_id == $opt->id ? 'selected' : '' }}>{{ $opt->name }}</option>
                                            @endforeach
                                        </select>
                                        @elseif($templateInput->type === 'boolean')
                                        <select name="inputs[{{ $templateInput->id }}]" class="text-sm border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#714B67]">
                                            <option value="0" {{ !$inp?->value_boolean ? 'selected' : '' }}>No</option>
                                            <option value="1" {{ $inp?->value_boolean ? 'selected' : '' }}>Yes</option>
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
                            <button type="submit" class="px-4 py-1.5 text-sm bg-[#714B67] hover:bg-[#5c3d55] text-white rounded-lg font-medium">Save Fields</button>
                        </div>
                    </form>
                    @else
                    <table class="w-full">
                        <tbody class="divide-y divide-gray-50">
                            @foreach($ticketInputDefs->sortBy('sort_order') as $templateInput)
                            @php $inp = $ticket->inputs->firstWhere('template_input_id', $templateInput->id); @endphp
                            <tr>
                                <td class="px-7 py-3 text-sm text-gray-500 w-52">{{ $templateInput->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-800 font-medium">{{ $inp?->getResultValue() ?: '—' }}</td>
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
                            <dt class="text-xs font-semibold text-red-500 uppercase tracking-wide mb-1">Return Reason</dt>
                            <dd class="text-sm text-red-800 bg-red-50 border border-red-200 rounded-lg px-3 py-2 leading-relaxed">{{ $ticket->return_reason }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">Template</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $ticket->template?->name ?? '—' }}</dd>
                        </div>
                        @if($ticket->resolve_deadline)
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">Deadline</dt>
                            <dd class="text-sm font-medium {{ $ticket->isOverdue() ? 'text-red-600' : 'text-gray-800' }}">{{ $ticket->resolve_deadline->format('M j, Y H:i') }}</dd>
                        </div>
                        @endif
                        @if($ticket->resolve_duration)
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">Duration</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $ticket->resolve_duration }} h</dd>
                        </div>
                        @endif
                        @if($ticket->resolve_deadline_passed)
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">SLA Passed</dt>
                            <dd class="text-sm font-medium text-red-600">{{ $ticket->resolve_deadline_passed }} h</dd>
                        </div>
                        @endif
                        @if($ticket->resolve_max_duration)
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">SLA Limit</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $ticket->resolve_max_duration }} h</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">Created by</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $ticket->createdByUser?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">Created</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $ticket->created_at->format('M j, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">Last updated</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ $ticket->updated_at->diffForHumans() }}</dd>
                        </div>
                    </dl>

                    {{-- Share link --}}
                    @if($ticket->sharedLink?->enabled)
                    @can('update', $ticket)
                    <div class="mt-6 pt-6 border-t border-gray-100">
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Share Link</div>
                        <div x-data="{ copied: false }" class="flex items-center gap-2 mb-3">
                            <input type="text" readonly value="{{ $ticket->sharedLink->shareUrl() }}"
                                   class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-gray-50 text-gray-600 select-all">
                            <button @click="navigator.clipboard.writeText('{{ $ticket->sharedLink->shareUrl() }}'); copied=true; setTimeout(()=>copied=false,2000)"
                                    class="px-3 py-1.5 text-sm border rounded-lg transition-colors shrink-0"
                                    :class="copied ? 'border-green-300 text-green-700 bg-green-50' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                                <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                            </button>
                        </div>
                        <form method="POST" action="{{ route('workflow.share.ticket.message', $ticket) }}">
                            @csrf @method('PATCH')
                            <textarea name="message" rows="2" placeholder="Message for recipient (optional)"
                                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#714B67] resize-none mb-2">{{ $ticket->sharedLink->message }}</textarea>
                            <button type="submit" class="px-3 py-1.5 text-sm bg-[#714B67] hover:bg-[#5c3d55] text-white rounded-lg">Save Message</button>
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
                                @if($viewer->id===$ticket->created_by_user_id)<div class="text-xs text-gray-400">Creator</div>
                                @elseif($viewer->id===$ticket->assigned_to_user_id)<div class="text-xs text-gray-400">Assignee</div>@endif
                            </div>
                            @can('update', $ticket)
                            @if($viewer->id !== $ticket->created_by_user_id)
                            <form method="POST" action="{{ route('workflow.tickets.remove-viewer', [$ticket, $viewer]) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-gray-300 hover:text-red-400 transition-colors p-1" title="Remove">
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
                            Add person
                        </button>
                        <div x-show="adding" x-transition style="display:none" class="mt-3 relative z-30">
                            <form method="POST" action="{{ route('workflow.tickets.add-viewer', $ticket) }}">
                                @csrf
                                @php $existingViewerIds = $ticket->viewers->pluck('id')->toArray(); @endphp
                                <x-relation-dropdown table="users" field="name" name="user_id" label="" :selected="[]" relation="many2one" :except="$existingViewerIds" :compact="true"/>
                                <button type="submit" class="mt-2 px-4 py-1.5 text-sm bg-[#714B67] hover:bg-[#5c3d55] text-white rounded-lg font-medium">Add</button>
                            </form>
                        </div>
                    </div>
                    @endcan
                </div>

                {{-- Activity tab --}}
                <div x-show="tab==='activity'" style="display:none">
                    <x-chatter
                        :model="$ticket"
                        :messages="$messages"
                        :comment-url="route('workflow.tickets.comment', $ticket)"
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
                        <span class="text-sm font-semibold text-gray-800">Chat</span>
                        @if(!empty($chatGrouped))
                        <span class="text-xs text-gray-400">{{ count($chatGrouped) }} {{ Str::plural('message', count($chatGrouped)) }}</span>
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
                                        <span class="text-xs font-bold {{ $isOwn ? 'text-[#714B67]' : 'text-gray-800' }}">{{ $msg->user?->name ?? 'Deleted User' }}</span>
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
                                        <a href="{{ route('workflow.tickets.chat.file', [$ticket, $f]) }}" target="_blank"
                                           class="block rounded-lg overflow-hidden border border-gray-100 hover:border-[#714B67]/30 transition-colors w-fit">
                                            <img src="{{ route('workflow.tickets.chat.file', [$ticket, $f]) }}" alt="{{ $f->original_name }}" class="max-w-full max-h-40 object-cover block">
                                        </a>
                                        @else
                                        <a href="{{ route('workflow.tickets.chat.file', [$ticket, $f]) }}"
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
                            <p class="text-sm font-medium text-gray-400">No messages yet</p>
                            <p class="text-xs text-gray-300 mt-0.5">Start the conversation below</p>
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
                                <button type="button" @click="clear()" class="text-xs text-gray-400 hover:text-red-400 ml-auto">✕</button>
                            </div>
                            <input type="file" name="files[]" multiple x-ref="fi" @change="handleFiles"
                                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" class="hidden">
                            <div class="flex items-end gap-1.5 bg-gray-50 border border-gray-200 rounded-xl px-2 py-1.5
                                        focus-within:bg-white focus-within:border-[#714B67]/40 focus-within:ring-2 focus-within:ring-[#714B67]/10 transition-all">
                                <button type="button" @click="$refs.fi.click()"
                                        class="shrink-0 w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-[#714B67] hover:bg-gray-100 transition-colors self-end mb-0.5"
                                        title="Attach file (images, PDF, Office docs — max 10 MB)">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                </button>
                                <textarea name="body" rows="1" placeholder="Message…"
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const chat = document.getElementById('ticket-chat-messages');
        if (chat) chat.scrollTop = chat.scrollHeight;
    });
</script>
@endsection
