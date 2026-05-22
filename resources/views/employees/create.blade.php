@extends('layouts.app')
@section('title', __('employees.new_employee'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('employees.new_employee') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        <a href="{{ route('employees.index') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.cancel') }}</a>
        <button form="employee-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save_short') }}</button>
    </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="employee-form" method="POST" action="{{ route('employees.store') }}" enctype="multipart/form-data">
                @csrf
                @include('employees._form', ['employee' => null])
            </form>
        </div>
    </div>
</div>
@endsection
