@extends('layouts.app')
@section('title', $user->name)
@include('settings._sidebar')

@section('content')
<div class="flex flex-col h-full overflow-hidden">

    <div class="bg-white border-b border-gray-200 px-6 py-3">
        <div class="flex items-center gap-3 flex-wrap">
            @include('components.breadcrumb', ['items' => [
                ['label' => __('settings.title'), 'url' => route('settings.index')],
                ['label' => __('settings.users'), 'url' => route('settings.users.index')],
                ['label' => $user->name],
            ]])

            <div class="ms-auto flex items-center gap-2">
                @can('update', $user)
                <a href="{{ route('settings.users.edit', $user) }}"
                   class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    {{ __('common.edit') }}
                </a>
                @endcan

                @can('delete', $user)
                <form method="POST" action="{{ route('settings.users.delete', $user) }}"
                      onsubmit="return confirm('{{ __('common.confirm_delete') }}')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-md hover:bg-red-50 transition-colors">
                        {{ __('common.delete') }}
                    </button>
                </form>
                @endcan
            </div>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-6">
        <div class="max-w-3xl mx-auto space-y-6">

            {{-- User header --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-center gap-5">
                <div class="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center text-xl font-bold text-purple-700 shrink-0">
                    {{ $user->initials }}
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $user->name }}</h1>
                    @if($user->job_position)
                        <p class="text-sm text-gray-500 mt-0.5">{{ $user->job_position }}</p>
                    @endif
                    <div class="flex flex-wrap items-center gap-3 mt-2">
                        <span class="text-xs text-gray-500">{{ $user->email }}</span>
                        @if($user->phone)
                            <span class="text-xs text-gray-400">·</span>
                            <span class="text-xs text-gray-500">{{ $user->phone }}</span>
                        @endif
                        <span class="text-xs text-gray-400">·</span>
                        @if($user->active)
                            <span class="inline-flex items-center gap-1 text-xs text-green-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> {{ __('common.active') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs text-gray-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span> {{ __('common.inactive') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Assigned roles --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('settings.assigned_roles') }}</h2>
                @if($user->roles->isEmpty())
                    <p class="text-sm text-gray-400">{{ __('settings.no_roles') }}</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach($user->roles as $role)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-50 text-purple-700 border border-purple-100">
                                {{ $role->name }}
                                @if($role->description)
                                    <span class="ms-1.5 text-xs text-purple-400">— {{ $role->description }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Meta --}}
            <div class="pt-2 text-xs text-gray-400 flex gap-6">
                <span>{{ __('common.created_at') }}: {{ $user->created_at->format('M d, Y') }}</span>
                <span>{{ __('common.last_updated') }}: {{ $user->updated_at->diffForHumans() }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
