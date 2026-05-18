@extends('layouts.app')
@section('title', 'New User')
@include('settings._sidebar')

@section('content')
<div class="flex flex-col h-full">
    <div class="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-3">
        @include('components.breadcrumb', ['items' => [
            ['label' => 'Settings', 'url' => route('settings.index')],
            ['label' => 'Users', 'url' => route('settings.users.index')],
            ['label' => 'New User'],
        ]])
        <div class="ml-auto flex gap-2">
            <a href="{{ route('settings.users.index') }}"
               class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Discard</a>
            <button form="user-form" type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-md hover:bg-purple-700 shadow-sm">Save</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-6">
        <form id="user-form" method="POST" action="{{ route('settings.users.store') }}" class="max-w-2xl mx-auto space-y-6">
            @csrf
            @include('settings.users._form', ['user' => null, 'roles' => $roles])
        </form>
    </div>
</div>
@endsection
