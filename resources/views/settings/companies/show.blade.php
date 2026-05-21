@extends('layouts.app')
@section('title', $company->name)
@include('settings._sidebar')

@section('content')
<div class="flex flex-col h-full overflow-hidden">

    {{-- Top bar --}}
    <div class="bg-white border-b border-gray-200 px-6 py-3 shrink-0">
        <div class="flex items-center gap-3 flex-wrap">
            @include('components.breadcrumb', ['items' => [
                ['label' => __('settings.title'), 'url' => route('settings.index')],
                ['label' => __('settings.companies'), 'url' => route('settings.companies.index')],
                ['label' => $company->name],
            ]])

            <div class="ms-auto flex items-center gap-2">
                @can('update', $company)
                <a href="{{ route('settings.companies.edit', $company) }}"
                   class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    {{ __('common.edit') }}
                </a>
                @endcan

                @can('update', $company)
                @if($company->active)
                <form method="POST" action="{{ route('settings.companies.archive', $company) }}">
                    @csrf @method('PATCH')
                    <button type="submit"
                            class="px-3 py-1.5 text-sm font-medium text-amber-700 bg-white border border-amber-200 rounded-md hover:bg-amber-50 transition-colors">
                        {{ __('common.archive') }}
                    </button>
                </form>
                @else
                <form method="POST" action="{{ route('settings.companies.unarchive', $company) }}">
                    @csrf @method('PATCH')
                    <button type="submit"
                            class="px-3 py-1.5 text-sm font-medium text-green-700 bg-white border border-green-300 rounded-md hover:bg-green-50 transition-colors">
                        {{ __('common.unarchive') }}
                    </button>
                </form>
                @endif
                @endcan

                @can('delete', $company)
                <form method="POST" action="{{ route('settings.companies.delete', $company) }}"
                      @submit.prevent="$dispatch('confirm-delete', { message: '{{ __('common.confirm_delete') }}', form: $el })">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="px-3 py-1.5 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-md hover:bg-red-50 transition-colors">
                        {{ __('common.delete') }}
                    </button>
                </form>
                @endcan
            </div>
        </div>

        @if(!$company->active)
        <div class="mt-2 flex items-center gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            {{ __('settings.archived_notice') }}
        </div>
        @endif
    </div>

    {{-- Scrollable body --}}
    <div class="flex-1 overflow-y-auto bg-gray-50/50">
        <div class="p-6 space-y-5">

            {{-- Company header --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center gap-5">
                    <div class="w-16 h-16 rounded-xl overflow-hidden bg-[#714B67]/10 flex items-center justify-center text-xl font-bold text-[#714B67] shrink-0">
                        @if($company->logo_url)
                            <img src="{{ $company->logo_url }}" alt="{{ $company->name }}" class="w-full h-full object-cover">
                        @else
                            {{ strtoupper(substr($company->name, 0, 2)) }}
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-2xl font-bold text-gray-900">{{ $company->name }}</h1>
                            @if($company->active)
                                <span class="inline-flex items-center gap-1 text-xs text-green-700 bg-green-50 border border-green-100 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> {{ __('common.active') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs text-gray-400 bg-gray-100 border border-gray-200 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span> {{ __('common.archived') }}
                                </span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-3 mt-1.5">
                            @if($company->city || $company->country)
                                <span class="text-sm text-gray-500">
                                    {{ implode(', ', array_filter([$company->city, $company->country])) }}
                                </span>
                            @endif
                            @if($company->currency)
                                <span class="inline-flex items-center text-xs font-medium bg-gray-100 text-gray-600 px-2 py-0.5 rounded">
                                    {{ $company->currency }}
                                </span>
                            @endif
                            @if($company->tax_id)
                                <span class="text-xs text-gray-400">{{ __('settings.tax_id') }}: {{ $company->tax_id }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Info cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                {{-- Contact Info --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">{{ __('settings.contact_info') }}</h3>
                    @if($company->email || $company->phone || $company->mobile || $company->website)
                    <dl class="space-y-3">
                        @if($company->email)
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <a href="mailto:{{ $company->email }}" class="text-sm text-[#714B67] hover:underline">{{ $company->email }}</a>
                        </div>
                        @endif
                        @if($company->phone)
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                            </div>
                            <span class="text-sm text-gray-700">{{ $company->phone }}</span>
                        </div>
                        @endif
                        @if($company->mobile)
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <span class="text-sm text-gray-700">{{ $company->mobile }}</span>
                        </div>
                        @endif
                        @if($company->website)
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>
                                </svg>
                            </div>
                            <a href="{{ $company->website }}" target="_blank" rel="noopener noreferrer" class="text-sm text-[#714B67] hover:underline">{{ $company->website }}</a>
                        </div>
                        @endif
                    </dl>
                    @else
                    <p class="text-sm text-gray-400">{{ __('settings.no_contact_info') }}</p>
                    @endif
                </div>

                {{-- Address --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">{{ __('settings.address') }}</h3>
                    @if($company->street || $company->city || $company->country)
                    <div class="flex items-start gap-3">
                        <div class="w-7 h-7 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <address class="not-italic text-sm text-gray-700 space-y-0.5">
                            @if($company->street)<div>{{ $company->street }}</div>@endif
                            @if($company->city || $company->state || $company->zip)
                            <div>{{ implode(', ', array_filter([$company->city, $company->state, $company->zip])) }}</div>
                            @endif
                            @if($company->country)<div>{{ $company->country }}</div>@endif
                        </address>
                    </div>
                    @else
                    <p class="text-sm text-gray-400">{{ __('settings.no_address') }}</p>
                    @endif
                </div>

                {{-- Assigned Users --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5 @if(!$company->notes) md:col-span-2 @endif">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">{{ __('settings.assign_users') }}</h3>
                        @can('update', $company)
                        <a href="{{ route('settings.companies.edit', $company) }}"
                           class="text-xs text-[#714B67] hover:text-[#5c3d55] font-medium transition-colors">{{ __('settings.manage') }}</a>
                        @endcan
                    </div>
                    @forelse($company->users as $user)
                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 last:border-0">
                        <div class="w-8 h-8 rounded-full bg-[#714B67]/10 flex items-center justify-center text-xs font-bold text-[#714B67] shrink-0">
                            {{ $user->initials }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $user->name }}</p>
                            @if($user->job_position)
                                <p class="text-xs text-gray-400 truncate">{{ $user->job_position }}</p>
                            @endif
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-400">{{ __('settings.no_users_assigned') }}</p>
                    @endforelse
                </div>

                {{-- Notes --}}
                @if($company->notes)
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">{{ __('common.notes') }}</h3>
                    <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ $company->notes }}</p>
                </div>
                @endif
            </div>

            {{-- Meta --}}
            <div class="text-xs text-gray-400 flex gap-6 px-1">
                <span>{{ __('common.created_at') }}: {{ $company->created_at->format('M d, Y') }}{{ $company->creator ? ' · ' . $company->creator->name : '' }}</span>
                <span>{{ __('common.last_updated') }}: {{ $company->updated_at->diffForHumans() }}</span>
            </div>

            {{-- Chatter --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <x-chatter
                    model-type="App\Models\Settings\Company"
                    :model-id="$company->id"
                    :can-comment="auth()->user()->can('comment', $company)"
                />
            </div>

        </div>
    </div>
</div>
@endsection
