@extends('layouts.app')
@section('title', 'Apps')

@section('content')
@php
$icons = [
    'contacts' => '<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" class="w-14 h-14">
        <rect x="14" y="10" width="36" height="44" rx="6" fill="#1f66d1"/>
        <rect x="18" y="14" width="36" height="36" rx="5" fill="#13bfd7"/>
        <rect x="23" y="19" width="25" height="26" rx="4" fill="#f28b2e"/>
        <circle cx="35.5" cy="27" r="5.5" fill="#5a2ca0"/>
        <path d="M24 43c1.5-7 6.1-10.5 11.5-10.5S45.5 36 47 43H24z" fill="#5a2ca0"/>
        <path d="M53 18v29l-6-4.2L41 47V18h12z" fill="#3131a2" opacity=".9"/>
    </svg>',
    'settings' => '<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" class="w-14 h-14">
        <rect x="13" y="12" width="38" height="40" rx="7" fill="#f8fafc"/>
        <path d="M35.4 13.5l1.5 6.1a15.7 15.7 0 014.7 2l5.7-2.5 4.1 7.1-5 3.8c.2 1 .3 2 .3 3s-.1 2-.3 3l5 3.8-4.1 7.1-5.7-2.5a15.7 15.7 0 01-4.7 2l-1.5 6.1h-8.2l-1.5-6.1a15.7 15.7 0 01-4.7-2l-5.7 2.5-4.1-7.1 5-3.8c-.2-1-.3-2-.3-3s.1-2 .3-3l-5-3.8 4.1-7.1 5.7 2.5a15.7 15.7 0 014.7-2l1.5-6.1h8.2z" fill="#7b4b93"/>
        <circle cx="31.3" cy="33" r="8.2" fill="#22c7b8"/>
        <circle cx="31.3" cy="33" r="4.2" fill="#fff"/>
    </svg>',
];
@endphp

<div class="relative min-h-full overflow-y-auto bg-[#f4f5f7] text-gray-800">
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -left-72 top-10 h-[900px] w-[900px] rounded-full border border-white/70 bg-white/30"></div>
        <div class="absolute -left-44 top-36 h-[680px] w-[680px] rounded-full border border-white/70"></div>
    </div>

    <header class="relative z-10 flex h-16 items-center justify-end px-5">
        <div class="flex items-center gap-4 text-gray-700">
            @if($allowedCompanies->isNotEmpty())
            <div x-data="{
                    open: false,
                    selected: {{ json_encode($activeCompanyIds ?? []) }},
                    toggle(id) {
                        const idx = this.selected.indexOf(id);
                        if (idx >= 0) {
                            if (this.selected.length > 1) this.selected.splice(idx, 1);
                        } else {
                            this.selected.push(id);
                        }
                    },
                    isSelected(id) { return this.selected.includes(id); }
                 }"
                 class="relative"
                 @click.outside="open = false">
                <button type="button" @click="open = !open" class="flex max-w-72 items-center gap-1 rounded px-2 py-1 text-sm font-medium hover:bg-white/70">
                    <span class="truncate">{{ $companyLabel }}</span>
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open"
                     x-transition
                     class="absolute right-0 top-full mt-2 w-72 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl"
                     style="display:none">
                    <div class="border-b border-gray-100 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Switch Company</p>
                    </div>
                    <div class="max-h-64 overflow-y-auto py-1">
                        @foreach($allowedCompanies as $company)
                        <button type="button" @click="toggle({{ $company->id }})" class="flex w-full items-center gap-3 px-4 py-2 text-left hover:bg-gray-50">
                            <span class="grid h-4 w-4 place-items-center rounded border"
                                  :class="isSelected({{ $company->id }}) ? 'border-[#714B67] bg-[#714B67]' : 'border-gray-300'">
                                <svg x-show="isSelected({{ $company->id }})" class="h-3 w-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-gray-800">{{ $company->name }}</span>
                                @if($company->city)<span class="block truncate text-xs text-gray-400">{{ $company->city }}</span>@endif
                            </span>
                        </button>
                        @endforeach
                    </div>
                    <form method="POST" action="{{ route('company.switch') }}" class="flex gap-2 border-t border-gray-100 bg-gray-50 px-4 py-3">
                        @csrf
                        <template x-for="id in selected" :key="id">
                            <input type="hidden" name="companies[]" :value="id">
                        </template>
                        <button type="submit" class="rounded bg-[#714B67] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#5c3d55]">Apply</button>
                        <button type="button" @click="open = false" class="rounded bg-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-300">Cancel</button>
                    </form>
                </div>
            </div>
            @endif

            <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                <button type="button" @click="open = !open" class="grid h-9 w-9 place-items-center overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-200">
                    <span class="text-xs font-bold text-[#714B67]">{{ auth()->user()->initials }}</span>
                </button>
                <div x-show="open"
                     x-transition
                     class="absolute right-0 top-full mt-2 w-56 rounded-lg border border-gray-200 bg-white py-1 shadow-xl"
                     style="display:none">
                    <div class="border-b border-gray-100 px-4 py-2.5">
                        <p class="text-sm font-semibold text-gray-800">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-gray-500">{{ auth()->user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50">Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <div class="relative z-0 mx-auto flex min-h-[calc(100vh-4rem)] max-w-5xl items-start justify-center px-6 pb-16 pt-6">
        @if($modules->isEmpty())
            <div class="mt-28 text-center text-sm text-gray-400">No modules available. Contact your administrator.</div>
        @else
            <div class="grid w-full max-w-3xl grid-cols-2 gap-x-12 gap-y-10 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                @foreach($modules as $module)
                <a href="{{ route($module['route']) }}" class="group flex flex-col items-center gap-3 text-center">
                    <span class="grid h-20 w-20 place-items-center rounded-md border border-gray-200 bg-white shadow-sm transition group-hover:-translate-y-0.5 group-hover:shadow-md group-active:translate-y-0">
                        {!! $icons[$module['icon']] ?? $icons['settings'] !!}
                    </span>
                    <span class="max-w-28 truncate text-sm font-medium text-gray-800 group-hover:text-[#714B67]">{{ $module['label'] }}</span>
                </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
