@extends('layouts.app')
@section('title', $contact->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50" x-data="{ compose: false }">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex items-center gap-2 shrink-0">
            @can('create', \App\Models\Contacts\Contact::class)
            <a href="{{ route('contacts.create') }}" class="px-3 py-1.5 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-medium rounded">New</a>
            @endcan
            <div class="flex flex-col leading-tight">
                <a href="{{ route('contacts.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Contacts</a>
                <span class="text-sm font-semibold text-gray-800">{{ $contact->name }}</span>
            </div>
        </div>

        <div class="ml-auto flex items-center gap-3 shrink-0">
            @can('update', $contact)
            <a href="{{ route('contacts.edit', $contact) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Edit</a>
            @if($contact->active)
            <form method="POST" action="{{ route('contacts.archive', $contact) }}">
                @csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">Archive</button>
            </form>
            @else
            <form method="POST" action="{{ route('contacts.unarchive', $contact) }}">
                @csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50">Restore</button>
            </form>
            @endif
            @endcan

            @can('delete', $contact)
            <form method="POST" action="{{ route('contacts.delete', $contact) }}" onsubmit="return confirm('Delete this contact?')">
                @csrf @method('DELETE')
                <button class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">Delete</button>
            </form>
            @endcan

            @if($recordPosition)
            <div class="flex items-center gap-1 text-sm text-gray-500">
                <span class="text-xs whitespace-nowrap">{{ $recordPosition }} / {{ $recordTotal }}</span>
                @if($prevId)
                    <a href="{{ route('contacts.show', $prevId) }}" class="p-1 hover:bg-gray-100 rounded">‹</a>
                @else
                    <span class="p-1 text-gray-300">‹</span>
                @endif
                @if($nextId)
                    <a href="{{ route('contacts.show', $nextId) }}" class="p-1 hover:bg-gray-100 rounded">›</a>
                @else
                    <span class="p-1 text-gray-300">›</span>
                @endif
            </div>
            @endif
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            @if(!$contact->active)
            <div class="px-6 pt-4 pb-0">
                <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">This contact is archived.</div>
            </div>
            @endif

            <div class="p-6">
                <div class="text-sm text-gray-600 mb-3">{{ $contact->isCompany() ? 'Company' : 'Individual' }}</div>
                <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ $contact->name }}</h1>

                <div class="flex gap-8">
                    <div class="flex-1">
                        @foreach([
                            ['Job Position', $contact->job_position],
                            ['Company', $contact->company?->name ?? $contact->company_name],
                            ['Tax ID', $contact->tax_id],
                            ['Parent Contact', $contact->parent?->name],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-32 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '' }}</span>
                        </div>
                        @endforeach

                        <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                            <span class="w-32 shrink-0 text-sm text-gray-500 pt-0.5">Tags</span>
                            <div class="flex flex-wrap gap-1.5 flex-1">
                                @forelse($contact->tags as $tag)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-white" style="background-color: {{ $tag->color }}">{{ $tag->name }}</span>
                                @empty
                                    <span class="text-sm text-gray-300">-</span>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="flex-1">
                        @foreach([
                            ['Phone', $contact->phone],
                            ['Mobile', $contact->mobile],
                            ['Email', $contact->email],
                            ['Website', $contact->website],
                            ['Street', $contact->street],
                            ['City', $contact->city],
                            ['State', $contact->state],
                            ['ZIP', $contact->zip],
                            ['Country', $contact->country],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-24 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '' }}</span>
                        </div>
                        @endforeach
                    </div>

                    <div class="shrink-0 w-36">
                        @if($contact->avatar_url)
                            <img src="{{ $contact->avatar_url }}" alt="{{ $contact->name }}" class="w-36 h-36 object-cover rounded-xl border border-gray-200 shadow-sm">
                        @else
                            <div class="w-36 h-36 rounded-xl flex items-center justify-center text-4xl font-bold {{ $contact->isCompany() ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                {{ strtoupper(substr($contact->name, 0, 2)) }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200" x-data="{ page: 'related' }">
                <div class="flex items-end gap-1 px-5 pt-3 border-b border-gray-200">
                    <button type="button"
                            @click="page = 'related'"
                            class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                            :class="page === 'related' ? 'text-gray-900 border-gray-300 -mb-px pb-[9px]' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                        Related Contacts
                    </button>
                    <button type="button"
                            @click="page = 'notes'"
                            class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                            :class="page === 'notes' ? 'text-gray-900 border-gray-300 -mb-px pb-[9px]' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                        Internal Notes
                    </button>
                </div>

                <div class="min-h-44">
                    <div x-show="page === 'related'" style="display:none" class="p-6">
                        @forelse($contact->children as $child)
                            <a href="{{ route('contacts.show', $child) }}" class="flex items-center gap-3 py-2.5 hover:bg-gray-50 rounded px-2 -mx-2">
                                <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700">{{ strtoupper(substr($child->name, 0, 2)) }}</div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900">{{ $child->name }}</p>
                                    @if($child->job_position)<p class="text-xs text-gray-500">{{ $child->job_position }}</p>@endif
                                </div>
                                <span class="text-xs text-gray-400">{{ $child->email }}</span>
                            </a>
                        @empty
                            <div class="min-h-32 flex items-center justify-center text-sm text-gray-400">No related contacts.</div>
                        @endforelse
                    </div>

                    <div x-show="page === 'notes'" style="display:none" class="p-6">
                        @if($contact->notes)
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $contact->notes }}</p>
                        @else
                            <div class="min-h-32 flex items-center justify-center text-sm text-gray-400">No internal notes.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm">
            @can('comment', $contact)
            <div class="px-6 py-3 border-b border-gray-100">
                <button type="button" @click="compose = !compose" class="px-3 py-1.5 text-sm font-medium rounded bg-[#714B67] text-white hover:bg-[#5c3d55]">Send message</button>
                <div x-show="compose" x-transition style="display:none" class="mt-3">
                    <form method="POST" action="{{ route('contacts.comment', $contact) }}" class="space-y-2">
                        @csrf
                        <textarea name="body" rows="3" placeholder="Write a message..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 resize-none bg-white"></textarea>
                        <button type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded">Send</button>
                    </form>
                </div>
            </div>
            @endcan

            <div class="px-6 py-2">
                @forelse($messages as $message)
                <div class="flex gap-3 my-4">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5 {{ $message->message_type === 'comment' ? 'bg-purple-100 text-purple-700' : 'bg-gray-700 text-white' }}">
                        {{ $message->user?->initials ?? '?' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-baseline gap-2 mb-1">
                            <span class="text-sm font-semibold text-gray-800">{{ $message->user?->name ?? 'System' }}</span>
                            <span class="text-xs text-gray-400">{{ $message->created_at->format('M j, g:i A') }}</span>
                        </div>
                        @if(!empty($message->metadata['changes']))
                            <ul class="space-y-0.5">
                                @foreach($message->metadata['changes'] as $change)
                                <li class="text-sm">
                                    <span class="text-gray-400">{{ $change['from'] }}</span>
                                    <span class="text-gray-300"> -&gt; </span>
                                    <span class="text-blue-600 font-medium">{{ $change['to'] }}</span>
                                    <span class="text-gray-400 text-xs">({{ $change['label'] }})</span>
                                </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $message->body }}</p>
                        @endif
                    </div>
                </div>
                @empty
                    <p class="text-sm text-gray-400 py-8 text-center">No messages yet.</p>
                @endforelse
            </div>
        </div>

        <div class="px-4 pb-4 text-xs text-gray-400 flex gap-6">
            <span>Created {{ $contact->created_at->format('M d, Y') }}{{ $contact->creator ? ' by ' . $contact->creator->name : '' }}</span>
            <span>Updated {{ $contact->updated_at->diffForHumans() }}{{ $contact->updater ? ' by ' . $contact->updater->name : '' }}</span>
        </div>
    </div>
</div>
@endsection
