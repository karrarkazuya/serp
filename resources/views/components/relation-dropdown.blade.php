<div class="{{ $compact ? 'py-0' : 'flex items-start gap-4 py-2 border-b border-gray-100' }}"
     x-data="{
        open: false,
        modalOpen: false,
        search: '',
        modalSearch: '',
        eventName: @js($event),
        multiple: @js($multiple),
        lookupUrl: @js($lookupUrl),
        field: @js($field),
        exclude: @js($exceptValues),
        selected: @js($selectedValues),
        selectedRecords: @js($selectedOptions),
        options: [],
        modalOptions: [],
        loading: false,
        modalLoading: false,
        pageSize: @js($limit),
        modalPageSize: @js($limit),
        meta: { from: 0, to: 0, total: 0, current_page: 1, last_page: 1 },
        modalMeta: { from: 0, to: 0, total: 0, current_page: 1, last_page: 1 },
        init() {
            this.selected = this.selected.map((id) => Number.isNaN(Number(id)) ? id : Number(id));
            this.emitSelection();
        },
        emitSelection() {
            if (!this.eventName) return;

            const records = this.selectedOptions();
            this.$dispatch(this.eventName, {
                name: @js($name),
                multiple: this.multiple,
                value: this.multiple ? this.selected : (this.selected[0] || ''),
                display: records.map((record) => record.label).join(', '),
                records,
            });
        },
        has(id) {
            return this.selected.map(String).includes(String(id));
        },
        params(term, page, perPage) {
            const params = new URLSearchParams({
                field: this.field,
                search: term || '',
                page: page || 1,
                per_page: perPage || this.pageSize,
            });

            this.exclude.forEach((id) => params.append('exclude[]', id));

            return params;
        },
        applyMeta(target, payload) {
            this[target] = {
                from: payload.from || 0,
                to: payload.to || 0,
                total: payload.total || 0,
                current_page: payload.current_page || 1,
                last_page: payload.last_page || 1,
            };
        },
        async fetchOptions(page = 1) {
            if (!this.lookupUrl) return;

            this.loading = true;
            try {
                const response = await fetch(`${this.lookupUrl}?${this.params(this.search, page, this.pageSize)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) throw new Error('Lookup failed');

                const payload = await response.json();
                this.options = payload.data || [];
                this.applyMeta('meta', payload);
            } finally {
                this.loading = false;
            }
        },
        async fetchModal(page = 1) {
            if (!this.lookupUrl) return;

            this.modalLoading = true;
            try {
                const response = await fetch(`${this.lookupUrl}?${this.params(this.modalSearch, page, this.modalPageSize)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) throw new Error('Lookup failed');

                const payload = await response.json();
                this.modalOptions = payload.data || [];
                this.applyMeta('modalMeta', payload);
            } finally {
                this.modalLoading = false;
            }
        },
        ensureRecord(option) {
            if (!this.selectedRecords.some((record) => String(record.id) === String(option.id))) {
                this.selectedRecords.push(option);
            }
        },
        toggle(option) {
            if (this.multiple) {
                if (this.has(option.id)) {
                    this.remove(option.id);
                } else {
                    this.selected.push(option.id);
                    this.ensureRecord(option);
                }

                this.search = '';
                this.emitSelection();
                return;
            }

            this.selected = [option.id];
            this.selectedRecords = [option];
            this.search = option.label;
            this.open = false;
            this.emitSelection();
        },
        remove(id) {
            this.selected = this.selected.filter((item) => String(item) !== String(id));
            this.selectedRecords = this.selectedRecords.filter((item) => String(item.id) !== String(id));
            this.emitSelection();
        },
        selectedOptions() {
            return this.selectedRecords.filter((option) => this.has(option.id));
        },
        openDropdown() {
            this.open = true;
            this.fetchOptions(1);
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },
        openSearchMore() {
            this.modalOpen = true;
            this.open = false;
            this.modalSearch = this.search;
            this.fetchModal(1);
            this.$nextTick(() => this.$refs.modalSearchInput?.focus());
        },
        selectAndClose() {
            this.modalOpen = false;
            this.open = false;
            this.search = '';
            this.modalSearch = '';
        },
     }"
     @click.outside="open = false">
    @if(!$compact)
    <label class="w-32 shrink-0 text-sm text-gray-500 pt-1">{{ $label ?? ucfirst($name) }}</label>
    @endif

    <div class="flex-1 min-w-0">
        @if(!$canRead)
            <div class="text-sm text-gray-400 py-1">{{ __('common.no_access') }}</div>
        @else
            <template x-if="multiple">
                <div>
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="{{ $inputName }}" :value="id">
                    </template>
                </div>
            </template>

            <template x-if="!multiple">
                <input type="hidden" name="{{ $inputName }}" :value="selected[0] || ''">
            </template>

            <div class="relative">
                <div class="min-h-8 border-0 border-b border-dotted border-gray-300 focus-within:border-purple-500"
                     @click="openDropdown()">
                    <div class="{{ $list ? 'flex flex-col gap-0.5 pb-1' : 'flex flex-wrap items-center gap-1.5 pb-1' }}">
                        <template x-for="option in selectedOptions()" :key="option.id">
                            @if($list)
                            <div class="flex items-center justify-between gap-2 py-1 border-b border-gray-50 last:border-0">
                                <span class="text-sm text-gray-700" x-text="option.label"></span>
                                <button type="button" @click.stop="remove(option.id)"
                                        class="shrink-0 text-gray-300 hover:text-red-400 transition-colors">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                            @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                                  :class="option.color ? 'text-white' : 'bg-gray-100 text-gray-700 border border-gray-200'"
                                  :style="option.color ? `background-color: ${option.color}` : ''">
                                <span x-text="option.label"></span>
                                <button type="button" @click.stop="remove(option.id)"
                                        class="w-3.5 h-3.5 rounded-full bg-white/30 hover:bg-white/50 flex items-center justify-center text-[10px] leading-none">
                                    &times;
                                </button>
                            </span>
                            @endif
                        </template>

                        <input x-ref="searchInput"
                               type="text"
                               x-model="search"
                               @focus="open = true; fetchOptions(1)"
                               @input.debounce.250ms="fetchOptions(1)"
                               @keydown.escape="open = false"
                               placeholder=""
                               class="flex-1 min-w-24 border-0 bg-transparent px-0 py-1 text-sm text-gray-800 focus:outline-none focus:ring-0">
                    </div>
                </div>

                <div x-show="open"
                     x-transition
                     class="absolute left-0 top-full z-40 w-full max-w-lg bg-white border border-gray-200 rounded-b-lg shadow-lg overflow-hidden"
                     style="display:none">
                    <div class="max-h-56 overflow-y-auto py-1">
                        <div x-show="loading" class="px-4 py-2 text-sm text-gray-400">
                            Loading...
                        </div>

                        <template x-for="option in options" :key="option.id">
                            <button type="button"
                                    @click="toggle(option)"
                                    class="w-full flex items-center gap-2 px-4 py-2 text-left text-sm hover:bg-gray-100"
                                    :class="has(option.id) ? 'bg-gray-100 font-semibold text-gray-900' : 'text-gray-700'">
                                <span x-show="option.color" class="w-3 h-3 rounded-full shrink-0" :style="`background-color: ${option.color}`"></span>
                                <span class="flex-1" x-text="option.label"></span>
                                <svg x-show="has(option.id)" class="w-4 h-4 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </template>

                        <div x-show="!loading && options.length === 0" class="px-4 py-2 text-sm text-gray-400">
                            No records found
                        </div>
                    </div>

                    @if($searchMoreUrl)
                    <button type="button"
                       @click="openSearchMore()"
                       class="block w-full text-left px-4 py-2 text-sm font-semibold text-[#714B67] hover:bg-gray-50 border-t border-gray-100">
                        Search More...
                    </button>
                    @endif
                </div>
            </div>

            <div x-show="modalOpen"
                 x-transition.opacity
                 class="fixed inset-0 z-100 bg-black/45 flex items-center justify-center p-6"
                 style="display:none">
                <div class="bg-white w-full max-w-5xl max-h-[86vh] rounded-lg shadow-2xl border border-gray-300 flex flex-col overflow-hidden"
                     @click.outside="modalOpen = false">
                    <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-200">
                        <h2 class="text-xl font-bold text-gray-800">Search: {{ $label ?? ucfirst($name) }}</h2>
                        <button type="button" @click="modalOpen = false" class="ml-auto text-gray-500 hover:text-gray-800">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-4">
                        <div class="mx-auto w-full max-w-md flex items-center border border-[#714B67] rounded bg-white overflow-hidden focus-within:ring-1 focus-within:ring-[#714B67]">
                            <span class="pl-3 pr-2 text-gray-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.2-5.2m1.7-5.3a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </span>
                            <input x-ref="modalSearchInput"
                                   type="text"
                                   x-model="modalSearch"
                                   @input.debounce.250ms="fetchModal(1)"
                                   placeholder="Search..."
                                   class="flex-1 min-w-0 py-2.5 pr-3 border-0 text-sm focus:outline-none focus:ring-0 placeholder-gray-400">
                            <button type="button" class="px-3 self-stretch border-l border-gray-300 text-gray-500 hover:bg-gray-50">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>

                        <div class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                            <span x-text="modalMeta.total ? `${modalMeta.from}-${modalMeta.to}` : '0'"></span>
                            <span> / </span>
                            <span x-text="modalMeta.total"></span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button"
                                    @click="fetchModal(modalMeta.current_page - 1)"
                                    :disabled="modalLoading || modalMeta.current_page <= 1"
                                    class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 disabled:text-gray-300 disabled:cursor-not-allowed hover:bg-gray-200">
                                ‹
                            </button>
                            <button type="button"
                                    @click="fetchModal(modalMeta.current_page + 1)"
                                    :disabled="modalLoading || modalMeta.current_page >= modalMeta.last_page"
                                    class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 disabled:text-gray-300 disabled:cursor-not-allowed hover:bg-gray-200">
                                ›
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr class="border-b border-gray-200 bg-white">
                                    <th class="w-12 px-6 py-3 text-left">
                                        <span class="block w-4 h-4 rounded border border-gray-300"></span>
                                    </th>
                                    <th class="px-3 py-3 text-start text-sm font-semibold text-gray-800">{{ __('common.name') }}</th>
                                    @if($colorField)
                                    <th class="px-6 py-3 text-end text-sm font-semibold text-gray-800">{{ __('contacts.tag_color') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                <tr x-show="modalLoading">
                                    <td colspan="{{ $colorField ? 3 : 2 }}" class="px-6 py-10 text-center text-sm text-gray-400">Loading...</td>
                                </tr>

                                <template x-for="option in modalOptions" :key="option.id">
                                    <tr class="border-b border-gray-200 hover:bg-gray-50 cursor-pointer" @click="toggle(option)">
                                        <td class="px-6 py-3">
                                            <span class="w-4 h-4 rounded border flex items-center justify-center"
                                                  :class="has(option.id) ? 'bg-blue-600 border-blue-600' : 'border-gray-300'">
                                                <svg x-show="has(option.id)" class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-gray-700 font-medium" x-text="option.label"></td>
                                        @if($colorField)
                                        <td class="px-6 py-3 text-right">
                                            <span class="inline-block w-7 h-5 border border-gray-300" :style="`background-color: ${option.color || '#fff'}`"></span>
                                        </td>
                                        @endif
                                    </tr>
                                </template>
                                <tr x-show="!modalLoading && modalOptions.length === 0">
                                    <td colspan="{{ $colorField ? 3 : 2 }}" class="px-6 py-10 text-center text-sm text-gray-400">No records found</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 flex items-center gap-2">
                        <button type="button"
                                @click="selectAndClose()"
                                class="px-4 py-2 bg-[#714B67]/50 text-white text-sm font-semibold rounded hover:bg-[#714B67]">
                            Select
                        </button>
                        @if($canCreate && $createUrl)
                        <a href="{{ $createUrl }}" class="px-4 py-2 bg-[#714B67] text-white text-sm font-semibold rounded hover:bg-[#5c3d55]">
                            New
                        </a>
                        @endif
                        <button type="button" @click="modalOpen = false" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm font-semibold rounded hover:bg-gray-300">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
