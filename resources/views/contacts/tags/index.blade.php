@extends('layouts.app')
@section('title', __('contacts.tags_title'))

@section('content')
@php
    $tagGroups = [
        ['label' => __('contacts.tag_color'), 'url' => route('contacts.tags.index', array_merge(request()->except('page'), ['group_by' => 'color']))],
        ['label' => __('common.created_by'), 'url' => route('contacts.tags.index', array_merge(request()->except('page'), ['group_by' => 'created_by']))],
    ];
@endphp
<div class="flex flex-col h-full bg-white">
    <div class="flex items-center gap-3 px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @if(auth()->user()->hasPermission('contacts.write'))
        <a href="{{ route('contacts.tags.create') }}"
           class="px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm">
            {{ __('common.new') }}
        </a>
        @endif

        <div class="flex items-center gap-1.5 shrink-0">
            <span class="text-xl font-semibold text-gray-700">{{ __('contacts.tags_title') }}</span>
            <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M11.49 2.17c-.38-1.56-2.6-1.56-2.98 0a1.53 1.53 0 01-2.29.95c-1.37-.84-2.94.73-2.1 2.1.54.89.06 2.05-.95 2.29-1.56.38-1.56 2.6 0 2.98 1.01.24 1.49 1.4.95 2.29-.84 1.37.73 2.94 2.1 2.1.89-.54 2.05-.06 2.29.95.38 1.56 2.6 1.56 2.98 0 .24-1.01 1.4-1.49 2.29-.95 1.37.84 2.94-.73 2.1-2.1-.54-.89-.06-2.05.95-2.29 1.56-.38 1.56-2.6 0-2.98a1.53 1.53 0 01-.95-2.29c.84-1.37-.73-2.94-2.1-2.1a1.53 1.53 0 01-2.29-.95zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
            </svg>
        </div>

        <x-search
            :model="\App\Models\Contacts\Tag::class"
            :action="route('contacts.tags.index')"
            :group-by="$tagGroups"
        />

        <div class="flex items-center gap-3 shrink-0 text-sm text-gray-500">
            @if(isset($groups))
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">{{ $groups->sum('count') }} records</span>
            @elseif(isset($tags))
                @if($tags->total() > 0)
                    <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                        {{ $tags->firstItem() }}-{{ $tags->lastItem() }} / {{ $tags->total() }}
                    </span>
                @else
                    <span class="text-sm font-semibold text-gray-400">0</span>
                @endif
            @endif

            @if(!isset($groups))
            <div class="flex items-center gap-1">
                @if(!isset($tags) || $tags->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $tags->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if(isset($tags) && $tags->hasMorePages())
                    <a href="{{ $tags->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
            @endif

            <div class="flex items-center rounded overflow-hidden bg-gray-200">
                <span class="w-10 h-10 inline-flex items-center justify-center border border-purple-400 bg-purple-100 text-gray-900" title="{{ __('common.list_view') }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 5h12v2H4V5zm0 4h12v2H4V9zm0 4h12v2H4v-2z"/>
                    </svg>
                </span>
            </div>
        </div>
    </div>

    @if(isset($groups))
    <x-list :grouped="true" :empty-text="__('contacts.no_tags')">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('contacts.tag_name')" class="px-4 py-2" :default="true" />
            <x-sortable-th column="color" :label="__('contacts.tag_color')" class="px-3 py-2" />
            <x-sortable-th column="contacts" :label="__('contacts.title')" class="px-3 py-2" />
            <th class="w-24 px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide"></th>
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
            @foreach($group['items'] as $tag)
            <tr x-show="open" class="hover:bg-purple-50/30 transition-colors cursor-pointer"
                onclick="window.location='{{ route('contacts.tags.show', $tag) }}'">
                <td class="px-4 py-2 font-medium text-gray-900">
                    <a href="{{ route('contacts.tags.show', $tag) }}" class="hover:text-purple-700" onclick="event.stopPropagation()">{{ $tag->name }}</a>
                </td>
                <td class="px-3 py-2 text-gray-600">
                    <span class="inline-flex items-center gap-2">
                        <span class="w-5 h-5 rounded border border-gray-200" style="background-color: {{ $tag->color }}"></span>
                        <span>{{ $tag->color }}</span>
                    </span>
                </td>
                <td class="px-3 py-2 text-gray-600">{{ $tag->contacts_count }}</td>
                <td class="px-3 py-2 text-end"></td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('contacts.no_tags') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$tags" :empty-text="__('contacts.no_tags')">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('contacts.tag_name')" class="px-4 py-2" :default="true" />
            <x-sortable-th column="color" :label="__('contacts.tag_color')" class="px-3 py-2" />
            <x-sortable-th column="contacts" :label="__('contacts.title')" class="px-3 py-2" />
            <th class="w-24 px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide"></th>
        </x-slot:columns>

        @foreach($tags as $tag)
        <tr class="hover:bg-purple-50/30 transition-colors cursor-pointer"
            onclick="window.location='{{ route('contacts.tags.show', $tag) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">
                <a href="{{ route('contacts.tags.show', $tag) }}" class="hover:text-purple-700" onclick="event.stopPropagation()">{{ $tag->name }}</a>
            </td>
            <td class="px-3 py-2 text-gray-600">
                <span class="inline-flex items-center gap-2">
                    <span class="w-5 h-5 rounded border border-gray-200" style="background-color: {{ $tag->color }}"></span>
                    <span>{{ $tag->color }}</span>
                </span>
            </td>
            <td class="px-3 py-2 text-gray-600">{{ $tag->contacts_count }}</td>
            <td class="px-3 py-2 text-end">
                @if(auth()->user()->hasPermission('contacts.unlink') && $tag->contacts_count === 0)
                <form method="POST" action="{{ route('contacts.tags.delete', $tag) }}" @submit.prevent.stop="$dispatch('confirm-delete', { message: '{{ __('common.confirm_delete') }}', form: $el })" @click.stop class="inline">
                    @csrf
                    @method('DELETE')
                    <button class="text-xs text-red-600 hover:text-red-700">{{ __('common.delete') }}</button>
                </form>
                @endif
            </td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
