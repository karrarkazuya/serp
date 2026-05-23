@extends('layouts.app')
@section('title', __('contacts.title'))

@php
    $view = request('view', 'kanban');
    $contactQuickFilters = [
        ['label' => __('contacts.individuals'), 'params' => ['type' => 'individual'], 'url' => route('contacts.index', array_merge(request()->except('page'), ['type' => 'individual']))],
        ['label' => __('contacts.companies'),   'params' => ['type' => 'company'],    'url' => route('contacts.index', array_merge(request()->except('page'), ['type' => 'company']))],
        ['label' => __('common.active'),         'params' => ['filter' => ''], 'clear_params' => ['filter' => 'all'], 'url' => route('contacts.index', array_merge(request()->except('page', 'filter'), ['filter' => '']))],
        ['label' => __('contacts.archived'),    'params' => ['filter' => 'archived'], 'url' => route('contacts.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => __('common.all'),            'params' => ['filter' => 'all'],      'url' => route('contacts.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
    $contactGroups = [
        ['label' => __('contacts.company'),  'url' => route('contacts.index', array_merge(request()->except('page'), ['group_by' => 'company_id']))],
        ['label' => __('contacts.country'),  'url' => route('contacts.index', array_merge(request()->except('page'), ['group_by' => 'country']))],
        ['label' => __('contacts.type'),     'url' => route('contacts.index', array_merge(request()->except('page'), ['group_by' => 'contact_type']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full {{ $view === 'kanban' ? 'bg-gray-100' : 'bg-white' }}" x-data="{ checked: [] }">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Contacts\Contact::class)
        <a href="{{ route('contacts.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            {{ __('common.new') }}
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">{{ __('contacts.title') }}</span>
            <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                <button type="button" @click="open = !open" class="p-1 text-gray-500 hover:text-gray-700 rounded">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M11.49 2.17c-.38-1.56-2.6-1.56-2.98 0a1.53 1.53 0 01-2.29.95c-1.37-.84-2.94.73-2.1 2.1.54.89.06 2.05-.95 2.29-1.56.38-1.56 2.6 0 2.98 1.01.24 1.49 1.4.95 2.29-.84 1.37.73 2.94 2.1 2.1.89-.54 2.05-.06 2.29.95.38 1.56 2.6 1.56 2.98 0 .24-1.01 1.4-1.49 2.29-.95 1.37.84 2.94-.73 2.1-2.1-.54-.89-.06-2.05.95-2.29 1.56-.38 1.56-2.6 0-2.98a1.53 1.53 0 01-.95-2.29c.84-1.37-.73-2.94-2.1-2.1a1.53 1.53 0 01-2.29-.95zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <div x-show="open" x-transition class="absolute left-0 top-full mt-1 w-52 bg-white rounded-lg shadow-xl border border-gray-200 py-1 z-30" style="display:none">
                    <div class="px-3 py-1.5 text-[11px] font-semibold text-gray-400 uppercase">{{ __('contacts.type') }}</div>
                    @foreach([
                        ['value' => '', 'label' => __('contacts.title')],
                        ['value' => 'individual', 'label' => __('contacts.individuals')],
                        ['value' => 'company', 'label' => __('contacts.companies')],
                    ] as $typeOption)
                    <a href="{{ route('contacts.index', array_merge(request()->except('type','page'), ['type' => $typeOption['value']])) }}"
                       class="block px-3 py-2 text-sm {{ request('type', '') === $typeOption['value'] ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                        {{ $typeOption['label'] }}
                    </a>
                    @endforeach
                    <div class="border-t border-gray-100 my-1"></div>
                    <div class="px-3 py-1.5 text-[11px] font-semibold text-gray-400 uppercase">{{ __('common.status') }}</div>
                    @foreach([
                        ['value' => '', 'label' => __('common.active')],
                        ['value' => 'archived', 'label' => __('contacts.archived')],
                        ['value' => 'all', 'label' => __('common.all')],
                    ] as $filterOption)
                    <a href="{{ route('contacts.index', array_merge(request()->except('filter','page'), ['filter' => $filterOption['value']])) }}"
                       class="block px-3 py-2 text-sm {{ request('filter', '') === $filterOption['value'] ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                        {{ $filterOption['label'] }}
                    </a>
                    @endforeach
                </div>
            </div>
        </div>

        <x-search
            :model="\App\Models\Contacts\Contact::class"
            :action="route('contacts.index')"
            :preserve="['view' => $view]"
            :quick-filters="$contactQuickFilters"
            :group-by="$contactGroups"
        />

        <div class="ms-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            @if(isset($groups))
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">{{ $groups->sum('count') }} records</span>
            @elseif(isset($contacts))
                @if($contacts->total() > 0)
                    <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                        {{ $contacts->firstItem() }}-{{ $contacts->lastItem() }} / {{ $contacts->total() }}
                    </span>
                @else
                    <span class="text-sm font-semibold text-gray-400">0</span>
                @endif
            @endif

            @if(!isset($groups))
            <div class="flex items-center gap-1">
                @if(!isset($contacts) || $contacts->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $contacts->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if(isset($contacts) && $contacts->hasMorePages())
                    <a href="{{ $contacts->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
            @endif

            <div class="hidden sm:flex items-center rounded overflow-hidden bg-gray-200">
                <a href="{{ route('contacts.index', array_merge(request()->except('view','page'), ['view' => 'kanban'])) }}"
                   class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 {{ $view === 'kanban' ? 'bg-purple-100 text-gray-900 border-purple-400' : 'text-gray-600 hover:bg-gray-100' }}"
                   title="{{ __('common.kanban_view') }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M3 3h6v14H3V3zm8 0h6v6h-6V3zm0 8h6v6h-6v-6z"/>
                    </svg>
                </a>
                <a href="{{ route('contacts.index', array_merge(request()->except('view','page'), ['view' => 'list'])) }}"
                   class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 {{ $view === 'list' ? 'bg-purple-100 text-gray-900 border-purple-400' : 'text-gray-600 hover:bg-gray-100' }}"
                   title="{{ __('common.list_view') }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 5h12v2H4V5zm0 4h12v2H4V9zm0 4h12v2H4v-2z"/>
                    </svg>
                </a>
                <span class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 text-gray-600 bg-gray-200" title="{{ __('common.activity_view') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
        </div>
    </div>

    @if($view === 'kanban')
    <div class="flex-1 overflow-y-auto p-3 sm:p-4">
        @if($contacts->isEmpty())
            <div class="py-24 text-center text-gray-400">
                <p class="text-sm font-medium text-gray-500">{{ __('contacts.no_contacts') }}</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                @foreach($contacts as $contact)
                <a href="{{ route('contacts.show', $contact) }}" class="group bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all overflow-hidden">
                    <div class="flex gap-3 p-3">
                        <div class="shrink-0">
                            @if($contact->avatar_url)
                                <img src="{{ $contact->avatar_url }}" alt="{{ $contact->name }}" class="w-16 h-16 rounded-lg object-cover border border-gray-100">
                            @else
                                <div class="w-16 h-16 rounded-lg flex items-center justify-center text-xl font-bold {{ $contact->isCompany() ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                    {{ strtoupper(substr($contact->name, 0, 2)) }}
                                </div>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 truncate group-hover:text-purple-700">{{ $contact->name }}</h3>
                            @if($contact->job_position)
                                <p class="text-xs text-gray-500 truncate mt-0.5">{{ $contact->job_position }}</p>
                            @endif
                            @if($contact->company?->name ?? $contact->company_name)
                                <p class="text-xs text-gray-500 truncate mt-0.5">{{ $contact->company?->name ?? $contact->company_name }}</p>
                            @endif
                            @if($contact->email)
                                <p class="text-xs text-blue-500 truncate mt-0.5">{{ $contact->email }}</p>
                            @endif
                            @if($contact->phones->isNotEmpty())
                                <p class="text-xs text-gray-500 truncate mt-0.5">{{ $contact->phones->first()->phone }}</p>
                            @endif
                            @if(!$contact->active)
                                <span class="inline-block mt-1 text-[10px] font-semibold text-amber-600 uppercase">{{ __('contacts.archived') }}</span>
                            @endif
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
        @endif
    </div>
    @else
    @can('export', \App\Models\Contacts\Contact::class)
    <x-export
        :fields="config('exportable')['contacts']['fields'] ?? []"
        :export-url="route('export')"
        model-key="contacts"
    />
    @endcan

    @if(isset($groups))
    <x-list :grouped="true" :empty-text="__('contacts.no_contacts')">
        <x-slot:columns>
            <x-sortable-th column="name"    :label="__('contacts.name')"    class="px-4 py-2" :default="true" />
            <x-sortable-th column="email"   :label="__('contacts.email')"   class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('contacts.phone') }}</th>
            <x-sortable-th column="city"    :label="__('contacts.city')"    class="px-3 py-2" />
            <x-sortable-th column="country" :label="__('contacts.country')" class="px-3 py-2" />
            <x-sortable-th column="company" :label="__('contacts.company')" class="px-3 py-2" />
        </x-slot:columns>

        @forelse($groups as $group)
        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="divide-y divide-gray-100">
            <tr class="bg-gray-50 border-y border-gray-200 cursor-pointer select-none" @click="open = !open">
                <td colspan="99" class="px-4 py-2.5">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                        <svg class="w-3.5 h-3.5 transition-transform shrink-0 text-gray-400" :class="open ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ $group['label'] }}
                        <span class="ms-1 text-xs text-gray-400 font-normal">({{ $group['count'] }})</span>
                    </div>
                </td>
            </tr>
            @foreach($group['items'] as $contact)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('contacts.show', $contact) }}'">
                <td class="px-4 py-2 font-medium text-gray-900">
                    {{ $contact->name }}
                    @if(!$contact->active)
                        <span class="ms-1.5 text-[10px] text-amber-600 font-semibold uppercase">{{ __('contacts.archived') }}</span>
                    @endif
                </td>
                <td class="px-3 py-2 text-gray-600">{{ $contact->email }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $contact->phones->pluck('phone')->join(', ') }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $contact->city }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $contact->country }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $contact->company?->name ?? $contact->company_name }}</td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('contacts.no_contacts') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$contacts"
            :empty-text="__('contacts.no_contacts')"
            :selectable="true"
            :total-count="$contacts->total()">
        <x-slot:columns>
            <x-sortable-th column="name"    :label="__('contacts.name')"    class="px-4 py-2" :default="true" />
            <x-sortable-th column="email"   :label="__('contacts.email')"   class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('contacts.phone') }}</th>
            <x-sortable-th column="city"    :label="__('contacts.city')"    class="px-3 py-2" />
            <x-sortable-th column="country" :label="__('contacts.country')" class="px-3 py-2" />
            <x-sortable-th column="company" :label="__('contacts.company')" class="px-3 py-2" />
        </x-slot:columns>

        @foreach($contacts as $contact)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('contacts.show', $contact) }}'">
            <td class="w-10 px-3 py-2 text-center" @click.stop>
                <input type="checkbox"
                       class="list-checkbox rounded border-gray-300 text-purple-600 focus:ring-purple-500 cursor-pointer"
                       x-model="selected"
                       value="{{ $contact->id }}">
            </td>
            <td class="px-4 py-2 font-medium text-gray-900">
                {{ $contact->name }}
                @if(!$contact->active)
                    <span class="ms-1.5 text-[10px] text-amber-600 font-semibold uppercase">{{ __('contacts.archived') }}</span>
                @endif
            </td>
            <td class="px-3 py-2 text-gray-600">{{ $contact->email }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $contact->phones->pluck('phone')->join(', ') }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $contact->city }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $contact->country }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $contact->company?->name ?? $contact->company_name }}</td>
        </tr>
        @endforeach
    </x-list>
    @endif
    @endif
</div>
@endsection
