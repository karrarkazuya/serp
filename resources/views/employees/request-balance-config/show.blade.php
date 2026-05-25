@extends('layouts.app')
@section('title', __('employees.balance_config_title'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <span class="text-sm font-semibold text-gray-800">{{ __('employees.balance_config_title') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            @unless($tooManyCompanies)
            <button form="balance-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save_short') }}</button>
            @endunless
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm p-6">
            @if($tooManyCompanies)
                <div class="py-12 text-center">
                    <p class="text-sm text-gray-500">{{ __('employees.balance_config_select_one_company') }}</p>
                </div>
            @else
                <p class="text-xs text-gray-500 mb-4">{{ __('employees.balance_config_intro') }}</p>
                @if($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
                @endif
                <form id="balance-form" method="POST" action="{{ route('employees.request-balance-config.save') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $companyId }}">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-8 gap-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.balance_config_leave_per_month') }} <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" min="0" max="31" name="leave_days_per_month" value="{{ old('leave_days_per_month', $config->leave_days_per_month ?? 0) }}" required
                                   class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.balance_config_leave_max') }} <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" min="0" max="366" name="leave_days_max" value="{{ old('leave_days_max', $config->leave_days_max ?? 0) }}" required
                                   class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.balance_config_timeoff_per_month') }} <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" min="0" max="744" name="time_off_hours_per_month" value="{{ old('time_off_hours_per_month', $config->time_off_hours_per_month ?? 0) }}" required
                                   class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
