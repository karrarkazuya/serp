@extends('layouts.app')
@section('title', 'New Contact')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('contacts.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Contacts</a>
            <span class="text-sm font-semibold text-gray-800">New Contact</span>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('contacts.index') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Discard</a>
            <button form="contact-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">Save</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="contact-form" method="POST" action="{{ route('contacts.store') }}" enctype="multipart/form-data">
                @csrf
                @include('contacts._form', [
                    'contact' => null,
                    'companies' => $companies,
                    'defaultCompanyId' => $defaultCompanyId ?? null,
                    'tags' => $tags,
                    'contacts' => $contacts,
                ])
            </form>
        </div>
    </div>
</div>
@endsection
