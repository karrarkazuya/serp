@extends('layouts.app')
@section('title', $tag->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
                <a href="{{ route('contacts.tags.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('contacts.tags_title') }}</a>
                <span class="text-sm font-semibold text-gray-800">{{ $tag->name }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

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
