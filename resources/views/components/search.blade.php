@props([])

@php
    $componentId = 'search_' . md5($model . request()->fullUrl());
    $preserved = array_merge(request()->except(['page', 'search', 'filters', 'group_by']), $preserve);
    $currentSearch = request('search', '');
    $fieldList = array_values($fields);
    $relationFields = collect($fields)
        ->filter(fn ($field) => ($field['type'] ?? null) === 'relation' && !empty($field['relation']['table']))
        ->all();
@endphp

<form method="GET"
      action="{{ $action }}"
      class="order-3 w-full sm:order-none sm:flex-1 sm:max-w-3xl sm:mx-auto"
      @foreach($relationFields as $key => $field)
      @search-relation-{{ md5($componentId . $key . 'single') }}="pickRelationFromComponent(@js($key), 'single', $event.detail)"
      @search-relation-{{ md5($componentId . $key . 'multi') }}="pickRelationFromComponent(@js($key), 'multi', $event.detail)"
      @endforeach
      x-data="{
        open: false,
        modalOpen: false,
        search: @js($currentSearch),
        filters: @js($activeFilters),
        activeQuickFilters: @js($activeQuickFilters),
        groupBy: @js($activeGroupBy),
        groupLabel: @js($activeGroupLabel),
        fields: @js($fields),
        fieldList: @js($fieldList),
        operatorLabels: @js($operatorLabels),
        draft: { field: @js(array_key_first($fields)), operator: 'contains', value: '', value_to: '', display: '' },
        draftRules: [],
        draftMatch: 'any',
        relationSelected: [],
        get currentField() { return this.fields[this.draft.field] || this.fieldList[0]; },
        get currentOperators() { return this.currentField?.operators || []; },
        get requiresValue() { return !['is_set', 'is_not_set'].includes(this.draft.operator); },
        get isBetween() { return this.draft.operator === 'between'; },
        get isRelation() { return this.currentField?.type === 'relation'; },
        get isMultiRelation() { return this.isRelation && ['in', 'not_in'].includes(this.draft.operator); },
        init() {
            this.normalizeDraft();
        },
        normalizeDraft() {
            const field = this.currentField;
            if (!field) return;
            if (!field.operators.includes(this.draft.operator)) {
                this.draft.operator = field.operators[0] || '=';
            }
            this.draft.value = '';
            this.draft.value_to = '';
            this.draft.display = '';
            this.relationSelected = [];
            if (field.type === 'boolean') {
                this.draft.value = '1';
                this.draft.display = 'Yes';
            }
        },
        describe(filter) {
            if (Array.isArray(filter.rules) && filter.rules.length) {
                const separator = filter.match === 'all' ? ' and ' : ' or ';
                return filter.rules.map((rule) => this.describe(rule)).join(separator);
            }

            const field = this.fields[filter.field];
            const label = field?.label || filter.field;
            const operator = this.operatorLabels[filter.operator] || filter.operator;
            if (['is_set', 'is_not_set'].includes(filter.operator)) return `${label} ${operator}`;
            if (filter.operator === 'between') return `${label} ${operator} ${filter.value} and ${filter.value_to}`;
            return `${label} ${operator} ${filter.display || filter.value}`;
        },
        canUseDraft() {
            if (!this.currentField) return;
            if (this.requiresValue && !this.draft.value) return false;
            if (this.isBetween && !this.draft.value_to) return false;
            return true;
        },
        currentRule() {
            return {
                field: this.draft.field,
                operator: this.draft.operator,
                value: this.draft.value,
                value_to: this.draft.value_to,
                display: this.draft.display,
            };
        },
        addDraftRule() {
            if (!this.canUseDraft()) return;

            this.draftRules.push(this.currentRule());
            this.normalizeDraft();
        },
        removeDraftRule(index) {
            this.draftRules.splice(index, 1);
        },
        addFilter() {
            const rules = [...this.draftRules];

            if (this.canUseDraft()) {
                rules.push(this.currentRule());
            }

            if (!rules.length) return;

            this.filters.push(rules.length === 1 ? rules[0] : {
                match: this.draftMatch,
                rules,
            });
            this.modalOpen = false;
            this.draftRules = [];
            this.normalizeDraft();
            this.submitSearch();
        },
        removeFilter(index) {
            this.filters.splice(index, 1);
            this.submitSearch();
        },
        clearSearch() {
            this.search = '';
            this.submitSearch();
        },
        setGroup(key, label) {
            this.groupBy = key;
            this.groupLabel = label;
            this.open = false;
            this.submitSearch();
        },
        clearGroup() {
            this.groupBy = null;
            this.groupLabel = null;
            this.submitSearch();
        },
        submitSearch() {
            const params = new URLSearchParams(new FormData(this.$refs.form));
            params.delete('search');
            params.delete('filters');
            params.delete('group_by');
            Array.from(params.keys()).forEach((key) => {
                if (key.startsWith('_search_relation')) params.delete(key);
            });

            if (this.search && this.search.trim() !== '') {
                params.set('search', this.search.trim());
            }

            if (this.filters.length) {
                params.set('filters', JSON.stringify(this.filters));
            }

            if (this.groupBy) {
                params.set('group_by', this.groupBy);
            }

            const query = params.toString();
            window.location.href = query ? `${this.$refs.form.action}?${query}` : this.$refs.form.action;
        },
        pickRelationFromComponent(field, mode, detail) {
            if (this.draft.field !== field) return;
            if (mode === 'multi' && !this.isMultiRelation) return;
            if (mode === 'single' && this.isMultiRelation) return;

            this.draft.value = detail.value;
            this.draft.display = detail.display || '';
            this.relationSelected = detail.records || [];
        },
      }"
      x-ref="form"
      @click.outside="open = false">
    @foreach($preserved as $name => $value)
        @if(is_array($value))
            @foreach($value as $item)
                <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
            @endforeach
        @elseif($value !== null && $value !== '')
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    <input type="hidden" name="filters" :value="JSON.stringify(filters)">
    <input type="hidden" name="group_by" :value="groupBy || ''">

    <div class="relative">
        <div class="flex min-h-9 items-center border border-gray-300 rounded bg-white overflow-hidden focus-within:ring-2 focus-within:ring-purple-300">
            <span class="pl-3 pr-2 text-gray-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.2-5.2m1.7-5.3a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </span>

            <template x-for="quickFilter in activeQuickFilters" :key="quickFilter.label">
                <span class="my-1 mr-1 inline-flex max-w-72 items-center gap-1.5 rounded bg-purple-100 text-gray-700 text-xs font-semibold overflow-hidden">
                    <span class="inline-flex h-7 w-7 items-center justify-center bg-[#714B67] text-white">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 4a1 1 0 011-1h12a1 1 0 01.8 1.6L12 11v4a1 1 0 01-.45.83l-2 1.33A1 1 0 018 16.33V11L3.2 4.6A1 1 0 013 4z"/>
                        </svg>
                    </span>
                    <span class="truncate" x-text="quickFilter.label"></span>
                    <a :href="quickFilter.clear_url" class="px-1.5 text-gray-500 hover:text-gray-800">×</a>
                </span>
            </template>

            <template x-for="(filter, index) in filters" :key="`${filter.field}_${filter.operator}_${index}`">
                <span class="my-1 mr-1 inline-flex max-w-72 items-center gap-1.5 rounded bg-purple-100 text-gray-700 text-xs font-semibold overflow-hidden">
                    <span class="inline-flex h-7 w-7 items-center justify-center bg-[#714B67] text-white">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 4a1 1 0 011-1h12a1 1 0 01.8 1.6L12 11v4a1 1 0 01-.45.83l-2 1.33A1 1 0 018 16.33V11L3.2 4.6A1 1 0 013 4z"/>
                        </svg>
                    </span>
                    <span class="truncate" x-text="describe(filter)"></span>
                    <button type="button" class="px-1.5 text-gray-500 hover:text-gray-800" @click="removeFilter(index)">×</button>
                </span>
            </template>

            <template x-if="groupBy">
                <span class="my-1 mr-1 inline-flex max-w-72 items-center gap-1.5 rounded bg-gray-100 text-gray-700 text-xs font-semibold overflow-hidden">
                    <span class="inline-flex h-7 w-7 items-center justify-center bg-[#714B67] text-white">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 2L2 6l8 4 8-4-8-4zm-6 8l6 3 6-3v2l-6 3-6-3v-2zm0 4l6 3 6-3v2l-6 3-6-3v-2z"/>
                        </svg>
                    </span>
                    <span class="truncate" x-text="`Group By: ${groupLabel || groupBy}`"></span>
                    <button type="button" class="px-1.5 text-gray-500 hover:text-gray-800" @click="clearGroup()">×</button>
                </span>
            </template>

            <input type="text"
                   name="search"
                   x-model="search"
                   @keydown.enter="$refs.form.requestSubmit()"
                   placeholder="{{ $placeholder }}"
                   class="flex-1 min-w-28 border-0 bg-transparent py-2 pr-2 text-sm focus:outline-none focus:ring-0 placeholder-gray-400">

            <button type="button" x-show="search" @click="clearSearch()" class="px-2 text-gray-400 hover:text-gray-700" style="display:none">×</button>
            <button type="button"
                    @click="open = !open"
                    class="self-stretch px-3 border-l border-gray-300 text-gray-600 hover:bg-gray-50"
                    :class="open ? 'bg-purple-100 text-[#714B67]' : ''">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>

        <div x-show="open"
             x-transition
             class="absolute left-0 right-0 top-full mt-2 z-40 bg-white border border-gray-200 rounded shadow-xl overflow-visible"
             style="display:none">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-0 p-4 min-h-52">
                <div class="px-4 border-r border-gray-200">
                    <div class="flex items-center gap-2 mb-3 text-sm font-bold text-gray-700">
                        <svg class="w-4 h-4 text-[#714B67]" fill="currentColor" viewBox="0 0 20 20"><path d="M3 4a1 1 0 011-1h12a1 1 0 01.8 1.6L12 11v4a1 1 0 01-.45.83l-2 1.33A1 1 0 018 16.33V11L3.2 4.6A1 1 0 013 4z"/></svg>
                        Filters
                    </div>
                    @foreach($quickOptions as $filter)
                        <a href="{{ $filter['url'] }}" class="block px-2 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded">
                            {{ $filter['label'] }}
                        </a>
                    @endforeach
                    @if(!empty($quickOptions))
                        <div class="border-t border-gray-200 my-2"></div>
                    @endif
                    <button type="button" @click="modalOpen = true; open = false" class="block px-2 py-1.5 text-sm font-medium text-[#714B67] hover:bg-gray-100 rounded">
                        Add Custom Filter
                    </button>
                </div>

                <div class="px-4 border-r border-gray-200">
                    <div class="flex items-center gap-2 mb-3 text-sm font-bold text-gray-700">
                        <svg class="w-4 h-4 text-[#714B67]" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2L2 6l8 4 8-4-8-4zm-6 8l6 3 6-3v2l-6 3-6-3v-2zm0 4l6 3 6-3v2l-6 3-6-3v-2z"/></svg>
                        Group By
                    </div>
                    @forelse($groupOptions as $group)
                        <button type="button"
                                @click="setGroup(@js($group['key']), @js($group['label']))"
                                class="block w-full px-2 py-1.5 text-left text-sm font-medium text-gray-700 hover:bg-gray-100 rounded">
                            {{ $group['label'] }}
                        </button>
                    @empty
                        <div class="px-2 py-1.5 text-sm text-gray-400">No groups</div>
                    @endforelse
                </div>

                <div class="px-4">
                    <div class="flex items-center gap-2 mb-3 text-sm font-bold text-gray-700">
                        <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.18 3.63h3.82c.97 0 1.37 1.24.59 1.8l-3.09 2.25 1.18 3.63c.3.92-.75 1.69-1.54 1.12L10 13.12l-3.09 2.24c-.79.57-1.84-.2-1.54-1.12l1.18-3.63-3.09-2.25c-.78-.56-.38-1.8.59-1.8h3.82l1.18-3.63z"/></svg>
                        Favorites
                    </div>
                    <button type="button" class="block px-2 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded">
                        Save current search
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="modalOpen"
         x-transition.opacity
         class="fixed inset-0 z-[100] bg-black/45 flex items-start justify-center p-4 pt-16"
         style="display:none">
        <div class="bg-white w-full max-w-5xl rounded shadow-2xl border border-gray-300 overflow-visible" @click.outside="modalOpen = false">
            <div class="flex items-center px-4 py-3 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Add Custom Filter</h2>
                <button type="button" @click="modalOpen = false" class="ml-auto text-gray-500 hover:text-gray-800">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="px-4 py-5 border-b border-gray-200">
                <div class="text-sm font-semibold text-gray-600 mb-4">
                    Match
                    <select x-model="draftMatch" class="border-0 bg-transparent px-0 py-0 text-sm font-semibold text-gray-600 focus:ring-0">
                        <option value="any">any</option>
                        <option value="all">all</option>
                    </select>
                    of the following rules:
                </div>
                <div x-show="draftRules.length" class="mb-3 space-y-2" style="display:none">
                    <template x-for="(rule, index) in draftRules" :key="`${rule.field}_${rule.operator}_${index}`">
                        <div class="grid grid-cols-1 md:grid-cols-[1fr_140px_1.5fr_32px] gap-5 items-center">
                            <div class="border-0 border-b border-gray-300 px-2 py-2 text-sm text-gray-700" x-text="fields[rule.field]?.label || rule.field"></div>
                            <div class="border-0 border-b border-gray-300 px-2 py-2 text-sm text-gray-700" x-text="operatorLabels[rule.operator] || rule.operator"></div>
                            <div class="border-0 border-b border-gray-300 px-2 py-2 text-sm text-gray-700 truncate" x-text="rule.operator === 'between' ? `${rule.value} and ${rule.value_to}` : (rule.display || rule.value || '')"></div>
                            <button type="button" class="text-gray-300 hover:text-red-500" @click="removeDraftRule(index)">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8 2a1 1 0 00-.9.55L6.38 4H4a1 1 0 100 2h.3l.86 10.34A2 2 0 007.16 18h5.68a2 2 0 001.99-1.66L15.7 6H16a1 1 0 100-2h-2.38l-.72-1.45A1 1 0 0012 2H8zm1 7a1 1 0 012 0v5a1 1 0 11-2 0V9zm4 0a1 1 0 10-2 0v5a1 1 0 102 0V9z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-[1fr_140px_1.5fr_1fr] gap-5 items-start">
                    <select x-model="draft.field" @change="normalizeDraft()" class="border-0 border-b border-gray-300 bg-white px-2 py-2 text-sm focus:border-[#714B67] focus:ring-0">
                        <template x-for="field in fieldList" :key="field.key">
                            <option :value="field.key" x-text="field.label"></option>
                        </template>
                    </select>

                    <select x-model="draft.operator" @change="normalizeDraft()" class="border-0 border-b border-gray-300 bg-white px-2 py-2 text-sm focus:border-[#714B67] focus:ring-0">
                        <template x-for="operator in currentOperators" :key="operator">
                            <option :value="operator" x-text="operatorLabels[operator] || operator"></option>
                        </template>
                    </select>

                    <div>
                        <template x-if="requiresValue && isRelation">
                            <div>
                                @foreach($relationFields as $key => $field)
                                    <div x-show="draft.field === @js($key) && !isMultiRelation" style="display:none">
                                        <x-relation-dropdown
                                            :table="$field['relation']['table']"
                                            :field="$field['relation']['field'] ?? 'name'"
                                            name="_search_relation_{{ $key }}_single"
                                            label=""
                                            relation="many2one"
                                            :compact="true"
                                            :event="'search-relation-' . md5($componentId . $key . 'single')"
                                        />
                                    </div>
                                    <div x-show="draft.field === @js($key) && isMultiRelation" style="display:none">
                                        <x-relation-dropdown
                                            :table="$field['relation']['table']"
                                            :field="$field['relation']['field'] ?? 'name'"
                                            name="_search_relation_{{ $key }}_multi"
                                            label=""
                                            relation="many2many"
                                            :compact="true"
                                            :event="'search-relation-' . md5($componentId . $key . 'multi')"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        </template>

                        <template x-if="requiresValue && !isRelation && currentField?.type === 'boolean'">
                            <select x-model="draft.value"
                                    @change="draft.display = draft.value === '1' ? 'Yes' : 'No'"
                                    class="w-full border-0 border-b border-gray-300 bg-white px-2 py-2 text-sm focus:border-[#714B67] focus:ring-0">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </template>

                        <template x-if="requiresValue && !isRelation && currentField?.type !== 'boolean'">
                            <input :type="['date', 'datetime'].includes(currentField?.type) ? (currentField.type === 'datetime' ? 'datetime-local' : 'date') : (['integer', 'decimal', 'number'].includes(currentField?.type) ? 'number' : 'text')"
                                   x-model="draft.value"
                                   class="w-full border-0 border-b border-gray-300 px-2 py-2 text-sm focus:border-[#714B67] focus:ring-0">
                        </template>
                    </div>

                    <div x-show="isBetween" style="display:none">
                        <input :type="currentField?.type === 'datetime' ? 'datetime-local' : (currentField?.type === 'date' ? 'date' : 'number')"
                               x-model="draft.value_to"
                               class="w-full border-0 border-b border-gray-300 px-2 py-2 text-sm focus:border-[#714B67] focus:ring-0"
                               placeholder="To">
                    </div>
                </div>
                <button type="button" class="mt-3 text-sm font-semibold text-[#714B67] hover:text-[#5c3d55]" @click="addDraftRule()">New Rule</button>
            </div>

            <div class="px-4 py-4 flex items-center gap-2">
                <button type="button" @click="addFilter()" class="px-4 py-2 bg-[#714B67] text-white text-sm font-semibold rounded hover:bg-[#5c3d55]">Add</button>
                <button type="button" @click="modalOpen = false" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm font-semibold rounded hover:bg-gray-300">Cancel</button>
            </div>
        </div>
    </div>
</form>
