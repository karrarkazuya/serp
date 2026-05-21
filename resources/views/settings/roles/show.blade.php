@extends('layouts.app')
@section('title', $role->name)
@include('settings._sidebar')

@section('content')
<div class="flex flex-col h-full overflow-hidden">

    <div class="bg-white border-b border-gray-200 px-6 py-3 shrink-0">
        <div class="flex items-center gap-3 flex-wrap">
            @include('components.breadcrumb', ['items' => [
                ['label' => __('settings.title'), 'url' => route('settings.index')],
                ['label' => __('settings.roles'), 'url' => route('settings.roles.index')],
                ['label' => $role->name],
            ]])

            <div class="flex items-center gap-2">
                @can('update', $role)
                <a href="{{ route('settings.roles.edit', $role) }}"
                   class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    {{ __('common.edit') }}
                </a>
                @endcan

                @can('delete', $role)
                @if($role->key !== 'admin')
                <form method="POST" action="{{ route('settings.roles.delete', $role) }}"
                      @submit.prevent="$dispatch('confirm-delete', { message: '{{ __('common.confirm_delete') }}', form: $el })">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="px-3 py-1.5 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-md hover:bg-red-50 transition-colors">
                        {{ __('common.delete') }}
                    </button>
                </form>
                @endif
                @endcan
            </div>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto bg-gray-50/50">
        <div class="p-6 space-y-5">

            {{-- Header card --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-[#714B67]/10 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-[#714B67]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-xl font-bold text-gray-900">{{ $role->name }}</h1>
                            <code class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded font-mono">{{ $role->key }}</code>
                            @if($role->active)
                                <span class="inline-flex items-center gap-1 text-xs text-green-700 bg-green-50 border border-green-100 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> {{ __('common.active') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs text-gray-400 bg-gray-100 border border-gray-200 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span> {{ __('common.inactive') }}
                                </span>
                            @endif
                        </div>
                        @if($role->description)
                            <p class="mt-1 text-sm text-gray-500">{{ $role->description }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Permissions --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-gray-700">{{ __('settings.role_permissions') }}</h2>
                    <span class="inline-flex items-center justify-center text-[10px] font-bold text-white bg-[#714B67] rounded-full min-w-5 h-5 px-1.5">
                        {{ $role->permissions->count() }}
                    </span>
                </div>

                @if($role->permissions->isEmpty())
                    <p class="text-sm text-gray-400">{{ __('settings.no_permissions_assigned') }}</p>
                @else
                    @php $grouped = $role->permissions->groupBy('module'); @endphp
                    <div class="space-y-4">
                        @foreach($grouped as $module => $perms)
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">{{ ucfirst($module) }}</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($perms as $perm)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium bg-[#714B67]/8 text-[#714B67] border border-[#714B67]/15 rounded-lg">
                                    {{ $perm->name }}
                                    <code class="text-[10px] text-[#714B67]/60 font-mono">{{ $perm->key }}</code>
                                </span>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Meta --}}
            <div class="text-xs text-gray-400 flex gap-6 px-1">
                <span>{{ __('common.created_at') }}: {{ $role->created_at->format('M d, Y') }}</span>
                <span>{{ __('common.last_updated') }}: {{ $role->updated_at->diffForHumans() }}</span>
            </div>

        </div>
    </div>
</div>
@endsection
