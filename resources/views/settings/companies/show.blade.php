@extends('layouts.app')
@section('title', $company->name)
@include('settings._sidebar')

@section('content')
<div class="flex flex-col h-full overflow-hidden">

    {{-- Header --}}
    <div class="bg-white border-b border-gray-200 px-6 py-3">
        <div class="flex items-center gap-3 flex-wrap">
            @include('components.breadcrumb', ['items' => [
                ['label' => 'Settings', 'url' => route('settings.index')],
                ['label' => 'Companies', 'url' => route('settings.companies.index')],
                ['label' => $company->name],
            ]])

            <div class="ml-auto flex items-center gap-2">

                @can('update', $company)
                <a href="{{ route('settings.companies.edit', $company) }}"
                   class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </a>
                @endcan

                @can('update', $company)
                @if($company->active)
                <form method="POST" action="{{ route('settings.companies.archive', $company) }}">
                    @csrf @method('PATCH')
                    <button type="submit"
                            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-amber-50 hover:border-amber-300 hover:text-amber-700 transition-colors">
                        Archive
                    </button>
                </form>
                @else
                <form method="POST" action="{{ route('settings.companies.unarchive', $company) }}">
                    @csrf @method('PATCH')
                    <button type="submit"
                            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-green-700 bg-white border border-green-300 rounded-md hover:bg-green-50 transition-colors">
                        Restore
                    </button>
                </form>
                @endif
                @endcan

                @can('delete', $company)
                <form method="POST" action="{{ route('settings.companies.delete', $company) }}"
                      onsubmit="return confirm('Delete {{ $company->name }}? This cannot be undone.')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-md hover:bg-red-50 transition-colors">
                        Delete
                    </button>
                </form>
                @endcan
            </div>
        </div>

        @if(!$company->active)
        <div class="mt-2 flex items-center gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            This company is archived.
        </div>
        @endif
    </div>

    {{-- Body --}}
    <div class="flex flex-1 overflow-hidden">

        {{-- Main area --}}
        <div class="flex-1 overflow-y-auto p-6">

            {{-- Company header --}}
            <div class="flex items-start gap-5 mb-6">
                <div class="w-16 h-16 rounded-xl bg-purple-100 flex items-center justify-center text-xl font-bold text-purple-700 shrink-0">
                    {{ strtoupper(substr($company->name, 0, 2)) }}
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $company->name }}</h1>
                    @if($company->city || $company->country)
                        <p class="text-gray-500 text-sm mt-0.5">
                            {{ implode(', ', array_filter([$company->city, $company->country])) }}
                        </p>
                    @endif
                    @if($company->currency)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 mt-1">
                            {{ $company->currency }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Info cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- Contact details --}}
                <div class="bg-gray-50 rounded-xl p-5">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Contact Information</h3>
                    <dl class="space-y-2.5">
                        @if($company->email)
                        <div class="flex items-center gap-3">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <a href="mailto:{{ $company->email }}" class="text-sm text-blue-600 hover:text-blue-700">{{ $company->email }}</a>
                        </div>
                        @endif
                        @if($company->phone)
                        <div class="flex items-center gap-3">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <span class="text-sm text-gray-700">{{ $company->phone }}</span>
                        </div>
                        @endif
                        @if($company->mobile)
                        <div class="flex items-center gap-3">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-sm text-gray-700">{{ $company->mobile }}</span>
                        </div>
                        @endif
                        @if($company->website)
                        <div class="flex items-center gap-3">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>
                            </svg>
                            <a href="{{ $company->website }}" target="_blank" class="text-sm text-blue-600 hover:text-blue-700">{{ $company->website }}</a>
                        </div>
                        @endif
                        @if($company->tax_id)
                        <div class="flex items-center gap-3">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                            <span class="text-sm text-gray-700">VAT: {{ $company->tax_id }}</span>
                        </div>
                        @endif
                    </dl>
                </div>

                {{-- Address --}}
                <div class="bg-gray-50 rounded-xl p-5">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Address</h3>
                    @if($company->street || $company->city || $company->country)
                    <address class="not-italic text-sm text-gray-700 space-y-1">
                        @if($company->street)<div>{{ $company->street }}</div>@endif
                        @if($company->city || $company->state || $company->zip)
                        <div>{{ implode(', ', array_filter([$company->city, $company->state, $company->zip])) }}</div>
                        @endif
                        @if($company->country)<div>{{ $company->country }}</div>@endif
                    </address>
                    @else
                    <p class="text-sm text-gray-400">No address provided.</p>
                    @endif
                </div>

                {{-- Assigned users --}}
                <div class="bg-gray-50 rounded-xl p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Assigned Users</h3>
                        @can('update', $company)
                        <a href="{{ route('settings.companies.edit', $company) }}"
                           class="text-xs text-purple-600 hover:text-purple-700">Manage</a>
                        @endcan
                    </div>
                    @forelse($company->users as $user)
                    <div class="flex items-center gap-2.5 py-1.5">
                        <div class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">
                            {{ $user->initials }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $user->name }}</p>
                            @if($user->job_position)
                                <p class="text-xs text-gray-400">{{ $user->job_position }}</p>
                            @endif
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-400">No users assigned.</p>
                    @endforelse
                </div>

                {{-- Notes --}}
                @if($company->notes)
                <div class="bg-gray-50 rounded-xl p-5">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Notes</h3>
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $company->notes }}</p>
                </div>
                @endif
            </div>

            {{-- Meta --}}
            <div class="mt-6 pt-4 border-t border-gray-100 text-xs text-gray-400 flex gap-6">
                <span>Created {{ $company->created_at->format('M d, Y') }}{{ $company->creator ? ' by ' . $company->creator->name : '' }}</span>
                <span>Last updated {{ $company->updated_at->diffForHumans() }}</span>
            </div>
        </div>

        {{-- Chatter sidebar --}}
        <div class="w-80 border-l border-gray-200 flex flex-col overflow-hidden shrink-0">
            @include('components.chatter', [
                'model'      => $company,
                'messages'   => $messages,
                'commentUrl' => route('settings.companies.show', $company),
            ])
        </div>
    </div>
</div>
@endsection
