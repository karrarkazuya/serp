@extends('layouts.app')
@section('title', __('common.edit') . ' ' . $departureReason->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('employees.departure-reasons.show', $departureReason) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $departureReason->name }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('common.edit') }}</span>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('employees.departure-reasons.show', $departureReason) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.cancel') }}</a>
            <button form="departure-reason-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save_short') }}</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm p-6">
            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <form id="departure-reason-form" method="POST" action="{{ route('employees.departure-reasons.update', $departureReason) }}">
                @csrf @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('common.name') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $departureReason->name) }}"
                               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent" required>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
