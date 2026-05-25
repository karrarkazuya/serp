@extends('layouts.app')
@section('title', __('employees.my_requests_title'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <span class="text-sm font-semibold text-gray-800">{{ __('employees.my_requests_title') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <a href="{{ route('employees.requests.create', ['from' => 'my']) }}" class="px-3 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('employees.new_request') }}</a>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        {{-- My Requests --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">{{ __('employees.my_requests_title') }}</h2>
            </div>
            @if($mine->isEmpty())
                <div class="py-8 text-center text-sm text-gray-400">{{ __('employees.no_requests') }}</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-start px-4 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_type') }}</th>
                            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_subtype') }}</th>
                            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_from') }}</th>
                            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_to') }}</th>
                            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_state') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($mine as $r)
                        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.requests.show', ['employeeRequest' => $r, 'from' => 'my']) }}'">
                            <td class="px-4 py-2 text-gray-800">{{ __('employees.' . $r->type) }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $r->subtype?->name }}</td>
                            <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $r->start_at?->format($r->type === 'leave' ? 'M d, Y' : 'M d H:i') }}</td>
                            <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $r->end_at?->format($r->type === 'leave' ? 'M d, Y' : 'M d H:i') }}</td>
                            <td class="px-3 py-2"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $r->state_color }}-100 text-{{ $r->state_color }}-700">{{ __('employees.request_state_' . $r->state) }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Pending my approval --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">{{ __('employees.pending_my_approval_title') }}</h2>
            </div>
            @if($pendingMyApproval->isEmpty())
                <div class="py-8 text-center text-sm text-gray-400">{{ __('employees.no_requests') }}</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-start px-4 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.employee_name') }}</th>
                            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_type') }}</th>
                            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_subtype') }}</th>
                            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_from') }}</th>
                            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_to') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($pendingMyApproval as $r)
                        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.requests.show', ['employeeRequest' => $r, 'from' => 'my']) }}'">
                            <td class="px-4 py-2 text-gray-900 font-semibold">{{ $r->employee?->name }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ __('employees.' . $r->type) }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ $r->subtype?->name }}</td>
                            <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $r->start_at?->format($r->type === 'leave' ? 'M d, Y' : 'M d H:i') }}</td>
                            <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $r->end_at?->format($r->type === 'leave' ? 'M d, Y' : 'M d H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
