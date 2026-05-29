@php
    $fv        = fn ($f, $d = '') => old($f, $role?->{$f} ?? $d);
    $permsFlat = $permissions->flatten()->map(function ($p) {
        return ['id' => $p->id, 'name' => $p->name, 'key' => $p->key, 'module' => $p->module];
    })->values()->all();
    $initialIds = array_values(array_map('intval', old('permissions', $assignedIds ?? [])));
    $isSystem   = $role && $role->isSystem();
@endphp

@if($isSystem)
<div class="shrink-0 mx-5 mt-4 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-700">
    {{ __('settings.system_role_locked') }}
</div>
@endif

{{-- Data injected into window before Alpine boots --}}
<script>
window._rpAll      = @json($permsFlat);
window._rpAssigned = @json($initialIds).map(Number);

window.rolePicker = function () {
    return {
        all:          [],
        assignedIds:  [],
        search:       '',
        dragging:     null,
        draggingFrom: '',
        overZone:     '',

        init() {
            this.all         = window._rpAll        || [];
            this.assignedIds = (window._rpAssigned  || []).slice();
        },

        available()  { return this.all.filter(p => !this.assignedIds.includes(p.id)); },
        assigned()   { return this.all.filter(p =>  this.assignedIds.includes(p.id)); },
        filtered() {
            var av = this.available(), q = this.search.trim().toLowerCase();
            return q ? av.filter(p => p.name.toLowerCase().includes(q) || p.key.toLowerCase().includes(q)) : av;
        },
        grouped() {
            var m = {};
            this.filtered().forEach(p => { (m[p.module] = m[p.module] || []).push(p); });
            return Object.keys(m).sort().map(mod => ({ module: mod, perms: m[mod] }));
        },

        assign(p)       { if (!this.assignedIds.includes(p.id)) this.assignedIds.push(p.id); },
        unassign(p)     { this.assignedIds = this.assignedIds.filter(id => id !== p.id); },
        assignModule(mod) {
            this.all.filter(p => p.module === mod && !this.assignedIds.includes(p.id))
                    .forEach(p => this.assignedIds.push(p.id));
        },
        assignAll()  { this.assignedIds = this.all.map(p => p.id); },
        clearAll()   { this.assignedIds = []; },

        dragStart(p, from, e) { this.dragging = p; this.draggingFrom = from; e.dataTransfer.effectAllowed = 'move'; },
        dragEnd()             { this.dragging = null; this.draggingFrom = ''; this.overZone = ''; },
        drop(zone) {
            if (!this.dragging) return;
            if (zone === 'assigned'  && this.draggingFrom === 'available') this.assign(this.dragging);
            if (zone === 'available' && this.draggingFrom === 'assigned')  this.unassign(this.dragging);
            this.dragEnd();
        },
    };
};
</script>

{{-- Errors --}}
@if($errors->any())
<div class="shrink-0 mx-5 mt-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-600">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

{{-- ── Role Details ── --}}
<div class="shrink-0 bg-white border-b border-gray-200 px-5 py-3">
    <div class="flex items-center gap-4 flex-wrap">
        <div>
            <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">{{ __('settings.role_name') }} <span class="text-red-400">*</span></label>
            <input type="text" name="name" value="{{ $fv('name') }}" required placeholder="e.g. Sales Manager"
                   class="w-44 px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#714B67]/25 focus:border-[#714B67] transition-colors {{ $errors->has('name') ? 'border-red-400' : '' }}">
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">{{ __('settings.role_key') }} <span class="text-red-400">*</span></label>
            <input type="text" name="key" value="{{ $fv('key') }}" required placeholder="sales_manager"
                   class="w-40 px-3 py-1.5 text-sm font-mono border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#714B67]/25 focus:border-[#714B67] transition-colors {{ $errors->has('key') ? 'border-red-400' : '' }}"
                   {{ $isSystem ? 'readonly' : '' }}>
        </div>
        <div class="flex-1 min-w-52">
            <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">{{ __('common.description') }}</label>
            <input type="text" name="description" value="{{ $fv('description') }}" placeholder="Optional description…"
                   class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#714B67]/25 focus:border-[#714B67] transition-colors">
        </div>
        <div class="flex items-center gap-2 pt-4">
            <input type="hidden" name="active" value="0">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" id="active" name="active" value="1"
                       {{ $fv('active', $role?->active ? '1' : '0') == '1' ? 'checked' : '' }}
                       {{ $isSystem ? 'disabled' : '' }}
                       class="sr-only peer">
                <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:bg-[#714B67] transition-colors
                            after:content-[''] after:absolute after:top-0.5 after:inset-s-0.5 after:bg-white
                            after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
            </label>
            <label for="active" class="text-sm text-gray-600 cursor-pointer select-none">{{ __('settings.user_active') }}</label>
        </div>
    </div>
</div>

{{-- ── Permission Picker ── --}}
<div x-data="rolePicker()" class="flex-1 min-h-0 flex overflow-hidden">

    <template x-for="id in assignedIds" :key="id">
        <input type="hidden" name="permissions[]" :value="id">
    </template>

    {{-- LEFT: Available --}}
    <div class="flex-1 min-w-0 flex flex-col border-r border-gray-200"
         @dragover.prevent="overZone = 'available'"
         @dragleave.self="overZone = ''"
         @drop.prevent="drop('available')"
         :class="overZone === 'available' && draggingFrom === 'assigned' ? 'bg-[#714B67]/5' : 'bg-gray-50'">

        {{-- Header --}}
        <div class="shrink-0 flex items-center gap-3 px-4 py-2.5 bg-white border-b border-gray-200">
            <span class="text-sm font-semibold text-gray-700">{{ __('settings.available_permissions') }}</span>
            <span class="text-xs text-gray-400 tabular-nums" x-text="'(' + filtered().length + ')'"></span>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input x-model="search" type="text" placeholder="{{ __('common.search') }}…"
                           class="pl-8 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg bg-white w-44 focus:outline-none focus:ring-2 focus:ring-[#714B67]/25 focus:border-[#714B67] transition-colors">
                </div>
                <button type="button" @click="assignAll()"
                        class="text-xs font-medium text-[#714B67] hover:text-[#5c3d55] transition-colors whitespace-nowrap">
                    {{ __('settings.add_all') }} →
                </button>
            </div>
        </div>

        {{-- List --}}
        <div class="flex-1 overflow-y-auto p-3 space-y-3">
            <template x-if="filtered().length === 0">
                <div class="py-16 text-center">
                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-gray-400" x-text="search ? '{{ __('common.no_results') }}' : '{{ __('settings.all_assigned') }}'"></p>
                </div>
            </template>

            <template x-for="group in grouped()" :key="group.module">
                <div>
                    <div class="flex items-center gap-2 mb-1.5 px-1">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest" x-text="group.module"></span>
                        <span class="text-[10px] text-gray-400 bg-gray-200 px-1.5 rounded-full" x-text="group.perms.length"></span>
                        <button type="button" @click="assignModule(group.module)"
                                class="ms-auto text-[10px] font-medium text-[#714B67]/60 hover:text-[#714B67] transition-colors">
                            + {{ __('settings.add_all') }}
                        </button>
                    </div>
                    <div class="space-y-1">
                        <template x-for="perm in group.perms" :key="perm.id">
                            <div draggable="true"
                                 @dragstart="dragStart(perm, 'available', $event)"
                                 @dragend="dragEnd()"
                                 @click="assign(perm)"
                                 :class="dragging && dragging.id === perm.id ? 'opacity-40' : ''"
                                 class="group flex items-center gap-2.5 px-3 py-2 bg-white border border-gray-100 rounded-lg cursor-grab active:cursor-grabbing hover:border-[#714B67]/30 hover:bg-[#714B67]/5 hover:shadow-sm transition-all select-none">
                                <svg class="w-3 h-3 text-gray-300 group-hover:text-[#714B67]/40 shrink-0 transition-colors" fill="currentColor" viewBox="0 0 16 16">
                                    <circle cx="5" cy="4" r="1.2"/><circle cx="5" cy="8" r="1.2"/><circle cx="5" cy="12" r="1.2"/>
                                    <circle cx="11" cy="4" r="1.2"/><circle cx="11" cy="8" r="1.2"/><circle cx="11" cy="12" r="1.2"/>
                                </svg>
                                <span class="flex-1 text-xs font-medium text-gray-700" x-text="perm.name"></span>
                                <code class="text-[10px] text-gray-400 font-mono group-hover:text-[#714B67]/60 transition-colors shrink-0" x-text="perm.key"></code>
                                <svg class="w-3 h-3 text-gray-300 group-hover:text-[#714B67]/50 group-hover:translate-x-0.5 shrink-0 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- RIGHT: Assigned --}}
    <div class="w-72 shrink-0 flex flex-col bg-white"
         @dragover.prevent="overZone = 'assigned'"
         @dragleave.self="overZone = ''"
         @drop.prevent="drop('assigned')"
         :class="overZone === 'assigned' && draggingFrom === 'available' ? 'bg-[#714B67]/5 ring-2 ring-inset ring-[#714B67]/20' : ''">

        <div class="shrink-0 flex items-center gap-2 px-4 py-2.5 border-b border-gray-200">
            <span class="text-sm font-semibold text-gray-700">{{ __('settings.assigned') }}</span>
            <span class="inline-flex items-center justify-center text-[10px] font-bold text-white bg-[#714B67] rounded-full min-w-5 h-5 px-1.5"
                  x-text="assignedIds.length"></span>
            <button type="button" @click="clearAll()" x-show="assignedIds.length > 0"
                    class="ms-auto text-xs text-gray-400 hover:text-red-500 font-medium transition-colors">
                {{ __('settings.clear_all') }}
            </button>
        </div>

        <div x-show="assignedIds.length === 0"
             class="flex-1 flex flex-col items-center justify-center gap-3 p-8 text-center">
            <div class="w-12 h-12 rounded-2xl border-2 border-dashed flex items-center justify-center transition-colors"
                 :class="overZone === 'assigned' ? 'border-[#714B67]/40 bg-[#714B67]/10' : 'border-gray-200'">
                <svg class="w-5 h-5 transition-colors" :class="overZone === 'assigned' ? 'text-[#714B67]/50' : 'text-gray-300'"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <p class="text-xs font-semibold text-gray-400">{{ __('settings.no_permissions_assigned') }}</p>
            <p class="text-[11px] text-gray-300">{{ __('settings.drag_or_click_hint') }}</p>
        </div>

        <div x-show="assignedIds.length > 0" class="flex-1 overflow-y-auto p-2 space-y-0.5">
            <template x-for="perm in assigned()" :key="perm.id">
                <div draggable="true"
                     @dragstart="dragStart(perm, 'assigned', $event)"
                     @dragend="dragEnd()"
                     :class="dragging && dragging.id === perm.id ? 'opacity-40' : ''"
                     class="group flex items-center gap-2 px-2.5 py-2 rounded-lg border border-transparent hover:border-gray-100 hover:bg-gray-50 cursor-grab active:cursor-grabbing transition-all select-none">
                    <svg class="w-3 h-3 text-gray-300 group-hover:text-gray-400 shrink-0" fill="currentColor" viewBox="0 0 16 16">
                        <circle cx="5" cy="4" r="1.2"/><circle cx="5" cy="8" r="1.2"/><circle cx="5" cy="12" r="1.2"/>
                        <circle cx="11" cy="4" r="1.2"/><circle cx="11" cy="8" r="1.2"/><circle cx="11" cy="12" r="1.2"/>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-medium text-gray-700 truncate" x-text="perm.name"></div>
                        <div class="text-[10px] text-gray-400 font-mono truncate" x-text="perm.key"></div>
                    </div>
                    <button type="button" @click.stop="unassign(perm)"
                            class="w-5 h-5 rounded flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 transition-all shrink-0 opacity-0 group-hover:opacity-100">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>
