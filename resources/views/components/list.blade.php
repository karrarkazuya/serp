@if($grouped)
{{-- Grouped mode: slot provides <tbody x-data="{ open }"> blocks; no pagination --}}
<div class="flex-1 overflow-auto {{ $class }}">
    <table class="w-full text-sm border-collapse">
        @isset($columns)
        <thead>
            <tr class="border-b border-gray-200 bg-gray-50">
                {{ $columns }}
            </tr>
        </thead>
        @endisset
        {{ $slot }}
    </table>
</div>
@elseif($selectable)
{{-- Selectable wrapper — provides Alpine selection state shared with row checkboxes --}}
<div class="flex flex-col flex-1 min-h-0"
     x-data="{
         selected: [],
         selectAllPages: false,
         totalCount: {{ $totalCount }},
         actionsOpen: false,

         get selectionCount() {
             return this.selectAllPages ? this.totalCount : this.selected.length;
         },

         get pageCheckboxes() {
             return Array.from(this.$root.querySelectorAll('tbody .list-checkbox'));
         },

         get pageAllChecked() {
             const cbs = this.pageCheckboxes;
             return cbs.length > 0 && cbs.every(cb => this.selected.includes(cb.value));
         },

         get someCheckedNotAll() {
             const cbs = this.pageCheckboxes;
             return cbs.some(cb => this.selected.includes(cb.value)) && !this.pageAllChecked;
         },

         togglePage(checked) {
             const cbs = this.pageCheckboxes;
             if (checked) {
                 cbs.forEach(cb => {
                     if (!this.selected.includes(cb.value)) this.selected.push(cb.value);
                 });
             } else {
                 const pageIds = cbs.map(cb => cb.value);
                 this.selected = this.selected.filter(id => !pageIds.includes(id));
                 this.selectAllPages = false;
             }
         },

         clearSelection() {
             this.selected = [];
             this.selectAllPages = false;
         },

         openExport(mode) {
             this.actionsOpen = false;
             this.$dispatch('export:open', {
                 mode,
                 ids: this.selectAllPages ? [] : this.selected,
                 selectAllPages: this.selectAllPages,
             });
         },
     }"
     @click.outside="actionsOpen = false">

    {{-- Selection action bar --}}
    <div x-show="selected.length > 0"
         x-transition:enter="transition duration-150 ease-out"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="flex items-center gap-3 px-4 py-2 bg-purple-50 border-b border-purple-100 shrink-0 text-sm"
         style="display:none">

        <span class="font-semibold text-purple-900" x-text="`${selectionCount} selected`"></span>

        <template x-if="!selectAllPages && totalCount > selected.length && totalCount > 0">
            <button type="button"
                    @click="selectAllPages = true"
                    class="text-purple-600 hover:text-purple-800 font-medium underline-offset-2 hover:underline">
                Select all <span x-text="totalCount"></span>
            </button>
        </template>
        <template x-if="selectAllPages">
            <span class="text-purple-700 font-medium">
                All <span x-text="totalCount"></span> selected
            </span>
        </template>

        <div class="ms-auto flex items-center gap-2">
            @if($canExport)
            {{-- Actions dropdown — only rendered when caller confirms export permission --}}
            <div class="relative">
                <button type="button"
                        @click="actionsOpen = !actionsOpen"
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 rounded text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm transition-colors">
                    <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M11.49 2.17c-.38-1.56-2.6-1.56-2.98 0a1.53 1.53 0 01-2.29.95c-1.37-.84-2.94.73-2.1 2.1.54.89.06 2.05-.95 2.29-1.56.38-1.56 2.6 0 2.98 1.01.24 1.49 1.4.95 2.29-.84 1.37.73 2.94 2.1 2.1.89-.54 2.05-.06 2.29.95.38 1.56 2.6 1.56 2.98 0 .24-1.01 1.4-1.49 2.29-.95 1.37.84 2.94-.73 2.1-2.1-.54-.89-.06-2.05.95-2.29 1.56-.38 1.56-2.6 0-2.98a1.53 1.53 0 01-.95-2.29c.84-1.37-.73-2.94-2.1-2.1a1.53 1.53 0 01-2.29-.95zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                    </svg>
                    Actions
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <div x-show="actionsOpen"
                     x-transition
                     class="absolute inset-e-0 top-full mt-1 w-48 bg-white border border-gray-200 rounded shadow-lg z-30 py-1"
                     style="display:none">
                    <button type="button"
                            @click="openExport('selected')"
                            class="flex items-center gap-2 w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Export
                    </button>
                    <div class="border-t border-gray-100 my-1"></div>
                    <button type="button"
                            @click="openExport('all')"
                            class="flex items-center gap-2 w-full px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-50">
                        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Export All
                    </button>
                </div>
            </div>
            @endif

            {{-- Clear selection --}}
            <button type="button"
                    @click="clearSelection()"
                    class="w-7 h-7 flex items-center justify-center text-purple-400 hover:text-purple-700 hover:bg-purple-100 rounded transition-colors"
                    title="Clear selection">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="flex-1 overflow-auto {{ $class }}">
        <table class="w-full text-sm border-collapse">
            @isset($columns)
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="w-10 px-3 py-2 text-center shrink-0">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-purple-600 focus:ring-purple-500 cursor-pointer"
                               :checked="pageAllChecked"
                               @change="togglePage($event.target.checked)"
                               x-effect="$el.indeterminate = someCheckedNotAll">
                    </th>
                    {{ $columns }}
                </tr>
            </thead>
            @endisset
            <tbody class="divide-y divide-gray-100">
                @if($isEmpty)
                    <tr>
                        <td colspan="100" class="px-4 py-20 text-center text-sm text-gray-400">{{ $emptyText }}</td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>

    @if($hasPagination)
    <div class="bg-white border-t border-gray-200 px-6 py-3 shrink-0">
        {{ $paginator->withQueryString()->links() }}
    </div>
    @endif
</div>
@else
{{-- Non-selectable — original layout --}}
<div class="flex-1 overflow-auto {{ $class }}">
    <table class="w-full text-sm border-collapse">
        @isset($columns)
        <thead>
            <tr class="border-b border-gray-200 bg-gray-50">
                {{ $columns }}
            </tr>
        </thead>
        @endisset
        <tbody class="divide-y divide-gray-100">
            @if($isEmpty)
                <tr>
                    <td colspan="100" class="px-4 py-20 text-center text-sm text-gray-400">{{ $emptyText }}</td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>

@if($hasPagination)
<div class="bg-white border-t border-gray-200 px-6 py-3">
    {{ $paginator->withQueryString()->links() }}
</div>
@endif
@endif
