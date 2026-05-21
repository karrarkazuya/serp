@extends('layouts.app')
@section('title', 'New Job Position')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('employees.jobs.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Job Positions</a>
            <span class="text-sm font-semibold text-gray-800">New Job Position</span>
        </div>

        <div class="ms-auto flex items-center gap-2">
            <a href="{{ route('employees.jobs.index') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Cancel</a>
            <button form="job-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">Save</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="job-form" method="POST" action="{{ route('employees.jobs.store') }}">
                @csrf
                @include('employees.jobs._form', ['job' => null])
            </form>
        </div>
    </div>
</div>
@endsection
