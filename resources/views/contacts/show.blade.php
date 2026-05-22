@extends('layouts.app')
@section('title', $contact->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50" x-data="{ compose: false }">
    @can('create', \App\Models\Contacts\Contact::class)
        @php $newHref = route('contacts.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('contacts.show', $prevId) : null"
        :next-href="$nextId ? route('contacts.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('contacts.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('contacts.title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $contact->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            @can('update', $contact)
            <a href="{{ route('contacts.edit', $contact) }}" class="shrink-0 px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
            @if($contact->active)
            <form method="POST" action="{{ route('contacts.archive', $contact) }}">
                @csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">{{ __('common.archive') }}</button>
            </form>
            @else
            <form method="POST" action="{{ route('contacts.unarchive', $contact) }}">
                @csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50">{{ __('common.unarchive') }}</button>
            </form>
            @endif
            @endcan
            @can('delete', $contact)
            <form method="POST" action="{{ route('contacts.delete', $contact) }}" @submit.prevent="$dispatch('confirm-delete', { message: '{{ __('common.confirm_delete') }}', form: $el })">
                @csrf @method('DELETE')
                <button class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
            </form>
            @endcan
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            @if(!$contact->active)
            <div class="px-6 pt-4 pb-0">
                <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">{{ __('contacts.contact_archived') }}</div>
            </div>
            @endif

            <div class="p-6">
                <div class="text-sm text-gray-600 mb-3">{{ $contact->isCompany() ? __('contacts.company_type') : __('contacts.individual') }}</div>
                <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ $contact->name }}</h1>

                <div class="flex gap-8">
                    <div class="flex-1">
                        @foreach([
                            [__('contacts.job_position'), $contact->job_position],
                            [__('contacts.company'),      $contact->company?->name ?? $contact->company_name],
                            [__('contacts.tax_id'),       $contact->tax_id],
                            [__('contacts.parent'),       $contact->parent?->name],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-32 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '' }}</span>
                        </div>
                        @endforeach

                        <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                            <span class="w-32 shrink-0 text-sm text-gray-500 pt-0.5">{{ __('contacts.tags') }}</span>
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
                        @foreach($contact->phones as $i => $phoneRecord)
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-24 shrink-0 text-sm text-gray-500">{{ $i === 0 ? __('contacts.phone') : '' }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $phoneRecord->phone }}</span>
                        </div>
                        @endforeach

                        @foreach([
                            [__('contacts.email'),   $contact->email],
                            [__('contacts.website'), $contact->website],
                            [__('contacts.street'),  $contact->street],
                            [__('contacts.city'),    $contact->city],
                            [__('contacts.state'),   $contact->state],
                            [__('contacts.zip'),     $contact->zip],
                            [__('contacts.country'), $contact->country],
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
                        {{ __('contacts.related_contacts') }}
                    </button>
                    <button type="button"
                            @click="page = 'notes'"
                            class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                            :class="page === 'notes' ? 'text-gray-900 border-gray-300 -mb-px pb-[9px]' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                        {{ __('contacts.notes') }}
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
                            <div class="min-h-32 flex items-center justify-center text-sm text-gray-400">{{ __('contacts.no_contacts') }}</div>
                        @endforelse
                    </div>

                    <div x-show="page === 'notes'" style="display:none" class="p-6">
                        @if($contact->notes)
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $contact->notes }}</p>
                        @else
                            <div class="min-h-32 flex items-center justify-center text-sm text-gray-400">{{ __('contacts.no_notes') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Contacts\Contact"
                :model-id="$contact->id"
                :can-comment="auth()->user()->can('comment', $contact)"
            />
        </div>

        <div class="px-4 pb-4 text-xs text-gray-400 flex gap-6">
            <span>{{ __('common.created_at') }}: {{ $contact->created_at->format('M d, Y') }}{{ $contact->creator ? ' · ' . $contact->creator->name : '' }}</span>
            <span>{{ __('common.updated_at') }}: {{ $contact->updated_at->diffForHumans() }}{{ $contact->updater ? ' · ' . $contact->updater->name : '' }}</span>
        </div>
    </div>
</div>
@endsection
