{{--
  Export dialog component.

  Usage:
    <x-export
        :fields="$exportFields"
        :export-url="route('export')"
        model-key="contacts"
    />

  Opened by dispatching the export:open Alpine event:
    $dispatch('export:open', { mode: 'selected', ids: [1,2,3], selectAllPages: false })
    $dispatch('export:open', { mode: 'all', ids: [], selectAllPages: false })
--}}
<div
    x-data="{
        open: false,
        mode: 'selected',
        ids: [],
        selectAllPages: false,
        format: 'xlsx',
        importCompatible: false,
        fieldSearch: '',
        allFields: @js($fields),
        toExport: @js($defaultFields),
        queryString: '',

        init() {
            this.queryString = (() => {
                const p = new URLSearchParams(window.location.search);
                p.delete('page');
                return p.toString();
            })();

            window.addEventListener('export:open', (e) => {
                this.mode           = e.detail.mode || 'selected';
                this.ids            = e.detail.ids || [];
                this.selectAllPages = e.detail.selectAllPages || false;
                this.open           = true;
            });
        },

        get availableFields() {
            const exportedKeys = this.toExport.map(f => f.key);
            return this.allFields.filter(f =>
                !exportedKeys.includes(f.key) &&
                (this.fieldSearch === '' || f.label.toLowerCase().includes(this.fieldSearch.toLowerCase()))
            );
        },

        addField(field) {
            if (!this.toExport.find(f => f.key === field.key)) {
                this.toExport.push(field);
            }
        },

        removeField(key) {
            this.toExport = this.toExport.filter(f => f.key !== key);
        },

        moveUp(index) {
            if (index === 0) return;
            [this.toExport[index - 1], this.toExport[index]] = [this.toExport[index], this.toExport[index - 1]];
            this.toExport = [...this.toExport];
        },

        moveDown(index) {
            if (index >= this.toExport.length - 1) return;
            [this.toExport[index], this.toExport[index + 1]] = [this.toExport[index + 1], this.toExport[index]];
            this.toExport = [...this.toExport];
        },

        doExport() {
            if (this.toExport.length === 0) return;
            this.$refs.exportForm.submit();
            this.open = false;
        },
    }"
    @keydown.escape.window="open = false">

    {{-- Hidden POST form — submitted on Export click --}}
    <form x-ref="exportForm" method="POST" action="{{ $exportUrl }}" style="display:none">
        @csrf
        <input type="hidden" name="model" value="{{ $modelKey }}">
        <input type="hidden" name="format" :value="format">
        <input type="hidden" name="import_compatible" value="0">
        <input type="hidden" name="select_all" :value="selectAllPages ? '1' : '0'">
        <input type="hidden" name="query_string" :value="queryString">
        <template x-for="id in (mode === 'selected' && !selectAllPages ? ids : [])">
            <input type="hidden" name="ids[]" :value="id">
        </template>
        <template x-for="field in toExport">
            <input type="hidden" name="fields[]" :value="field.key">
        </template>
    </form>

    {{-- Modal overlay --}}
    <div x-show="open"
         x-transition.opacity
         class="fixed inset-0 z-200 bg-black/40 flex items-start justify-center p-4 pt-16"
         style="display:none"
         @click.self="open = false">

        <div class="bg-white w-full max-w-3xl rounded-lg shadow-2xl border border-gray-200 flex flex-col max-h-[80vh]"
             @click.stop>

            {{-- Header --}}
            <div class="flex items-center px-5 py-3.5 border-b border-gray-200 shrink-0">
                <h2 class="text-base font-semibold text-gray-800">Export Data</h2>
                <button type="button" @click="open = false"
                        class="ms-auto text-gray-400 hover:text-gray-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Options bar --}}
            <div class="flex flex-wrap items-center gap-4 px-5 py-3 bg-gray-50 border-b border-gray-200 shrink-0">
                <div class="flex items-center gap-1.5 text-sm">
                    <span class="text-gray-500 font-medium me-1">Export Format:</span>
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="radio" x-model="format" value="xlsx"
                               class="border-gray-300 text-purple-600 focus:ring-purple-500">
                        <span class="font-medium">XLSX</span>
                    </label>
                    <label class="flex items-center gap-1.5 cursor-pointer ms-2">
                        <input type="radio" x-model="format" value="csv"
                               class="border-gray-300 text-purple-600 focus:ring-purple-500">
                        <span class="font-medium">CSV</span>
                    </label>
                </div>
            </div>

            {{-- Body: two-column field picker --}}
            <div class="grid grid-cols-2 divide-x divide-gray-200 flex-1 min-h-0">

                {{-- Left — Available fields --}}
                <div class="flex flex-col min-h-0">
                    <div class="px-4 py-2.5 border-b border-gray-100 shrink-0">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Available fields</p>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 text-gray-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.2-5.2m1.7-5.3a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </span>
                            <input type="text" x-model="fieldSearch" placeholder="Search"
                                   class="w-full pl-7 pr-3 py-1.5 text-sm border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-purple-400">
                        </div>
                    </div>
                    <div class="overflow-y-auto flex-1 py-1">
                        <template x-for="field in availableFields" :key="field.key">
                            <div class="flex items-center justify-between px-4 py-1.5 hover:bg-gray-50 group">
                                <span class="text-sm text-gray-700" x-text="field.label"></span>
                                <button type="button" @click="addField(field)"
                                        class="text-gray-300 hover:text-[#714B67] transition-colors opacity-0 group-hover:opacity-100">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                        <template x-if="availableFields.length === 0">
                            <p class="px-4 py-4 text-sm text-gray-400 text-center">
                                <span x-show="fieldSearch !== ''">No fields match your search.</span>
                                <span x-show="fieldSearch === ''">All fields added.</span>
                            </p>
                        </template>
                    </div>
                </div>

                {{-- Right — Fields to export --}}
                <div class="flex flex-col min-h-0">
                    <div class="px-4 py-2.5 border-b border-gray-100 shrink-0">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Fields to export</p>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400" x-text="`${toExport.length} selected`"></span>
                                <span class="text-xs text-gray-300">Template:</span>
                                <span class="text-xs text-gray-400 italic">—</span>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-y-auto flex-1 py-1">
                        <template x-if="toExport.length === 0">
                            <p class="px-4 py-4 text-sm text-gray-400 text-center">No fields selected yet.</p>
                        </template>
                        <template x-for="(field, index) in toExport" :key="field.key">
                            <div class="flex items-center gap-1 px-4 py-1.5 hover:bg-gray-50 group">
                                {{-- Reorder arrows --}}
                                <div class="flex flex-col me-1 opacity-0 group-hover:opacity-100 shrink-0">
                                    <button type="button" @click="moveUp(index)"
                                            :disabled="index === 0"
                                            class="text-gray-400 hover:text-gray-700 disabled:opacity-30 leading-none">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                    <button type="button" @click="moveDown(index)"
                                            :disabled="index >= toExport.length - 1"
                                            class="text-gray-400 hover:text-gray-700 disabled:opacity-30 leading-none">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </div>
                                <span class="flex-1 text-sm text-gray-700" x-text="field.label"></span>
                                <button type="button" @click="removeField(field.key)"
                                        class="text-gray-300 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100 shrink-0">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8 2a1 1 0 00-.9.55L6.38 4H4a1 1 0 100 2h.3l.86 10.34A2 2 0 007.16 18h5.68a2 2 0 001.99-1.66L15.7 6H16a1 1 0 100-2h-2.38l-.72-1.45A1 1 0 0012 2H8zm1 7a1 1 0 012 0v5a1 1 0 11-2 0V9zm4 0a1 1 0 10-2 0v5a1 1 0 102 0V9z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center gap-2 px-5 py-3 border-t border-gray-200 shrink-0">
                <button type="button"
                        @click="doExport()"
                        :disabled="toExport.length === 0"
                        class="px-4 py-2 bg-[#714B67] text-white text-sm font-semibold rounded hover:bg-[#5c3d55] disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    Export
                </button>
                <button type="button"
                        @click="open = false"
                        class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded hover:bg-gray-200 transition-colors">
                    Close
                </button>
                <span class="ms-auto text-xs text-gray-400" x-show="mode === 'selected'" x-text="`Exporting ${selectAllPages ? 'all' : ids.length} record${ids.length !== 1 ? 's' : ''}`"></span>
                <span class="ms-auto text-xs text-gray-400" x-show="mode === 'all'">Exporting all records</span>
            </div>
        </div>
    </div>
</div>
