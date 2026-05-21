@extends('layouts.app')
@section('title', $tag->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex items-center gap-2 shrink-0">
            @if(auth()->user()->hasPermission('contacts.write'))
            <a href="{{ route('contacts.tags.create') }}" class="px-3 py-1.5 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-medium rounded">{{ __('common.new') }}</a>
            @endif
            <div class="flex flex-col leading-tight">
                <a href="{{ route('contacts.tags.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('contacts.tags_title') }}</a>
                <span class="text-sm font-semibold text-gray-800">{{ $tag->name }}</span>
            </div>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            @if(auth()->user()->hasPermission('contacts.write'))
            <a href="{{ route('contacts.tags.edit', $tag) }}"
               class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">
                {{ __('common.edit') }}
            </a>
            @endif

            @if(auth()->user()->hasPermission('contacts.unlink') && $tag->contacts_count === 0)
            <form method="POST" action="{{ route('contacts.tags.delete', $tag) }}" @submit.prevent="$dispatch('confirm-delete', { message: '{{ __('common.confirm_delete') }}', form: $el })">
                @csrf
                @method('DELETE')
                <button class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
            </form>
            @endif

            @if($recordPosition)
            <div class="flex items-center gap-1 text-sm text-gray-500">
                <span class="text-xs whitespace-nowrap">{{ $recordPosition }} / {{ $recordTotal }}</span>
                @if($prevId)
                    <a href="{{ route('contacts.tags.show', $prevId) }}" class="p-1 hover:bg-gray-100 rounded">‹</a>
                @else
                    <span class="p-1 text-gray-300">‹</span>
                @endif
                @if($nextId)
                    <a href="{{ route('contacts.tags.show', $nextId) }}" class="p-1 hover:bg-gray-100 rounded">›</a>
                @else
                    <span class="p-1 text-gray-300">›</span>
                @endif
            </div>
            @endif
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            <div class="p-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ $tag->name }}</h1>

                <div class="max-w-2xl">
                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <span class="w-32 shrink-0 text-sm text-gray-500">{{ __('contacts.tag_color') }}</span>
                        <span class="flex items-center gap-2 flex-1 text-sm text-gray-800">
                            <span class="w-5 h-5 rounded border border-gray-200" style="background-color: {{ $tag->color }}"></span>
                            {{ $tag->color }}
                        </span>
                    </div>
                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <span class="w-32 shrink-0 text-sm text-gray-500">{{ __('contacts.title') }}</span>
                        <span class="flex-1 text-sm text-gray-800">{{ $tag->contacts_count }}</span>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-600 mb-3">{{ __('contacts.tagged_contacts') }}</h2>

                @if($contacts->isEmpty())
                    <p class="text-sm text-gray-400 py-4 text-center">{{ __('contacts.no_tag_contacts') }}</p>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($contacts as $contact)
                        <a href="{{ route('contacts.show', $contact) }}"
                           class="flex items-center gap-3 py-2.5 hover:bg-gray-50 rounded-lg px-2 -mx-2">
                            <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">
                                {{ strtoupper(substr($contact->name, 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900">{{ $contact->name }}</p>
                                @if($contact->job_position)
                                    <p class="text-xs text-gray-500">{{ $contact->job_position }}</p>
                                @endif
                            </div>
                            <span class="text-xs text-gray-400">{{ $contact->company?->name ?? $contact->company_name }}</span>
                        </a>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        {{ $contacts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
