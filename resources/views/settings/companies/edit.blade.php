@extends('layouts.app')
@section('title', 'Edit — ' . $company->name)
@include('settings._sidebar')

@section('content')
<div class="flex flex-col h-full">

    {{-- Header --}}
    <div class="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-3 flex-wrap">
        @include('components.breadcrumb', ['items' => [
            ['label' => 'Settings', 'url' => route('settings.index')],
            ['label' => 'Companies', 'url' => route('settings.companies.index')],
            ['label' => $company->name, 'url' => route('settings.companies.show', $company)],
            ['label' => 'Edit'],
        ]])

        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('settings.companies.show', $company) }}"
               class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                Cancel
            </a>
            <button type="submit" form="company-form"
                    class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-md transition-colors shadow-sm">
                Save Changes
            </button>
        </div>
    </div>

    {{-- Form --}}
    <div class="flex-1 overflow-y-auto p-6">
        <form id="company-form" method="POST" action="{{ route('settings.companies.update', $company) }}"
              class="max-w-3xl mx-auto space-y-5">
            @csrf @method('PUT')
            @include('settings.companies._form', ['company' => $company])
        </form>
    </div>
</div>
@endsection
