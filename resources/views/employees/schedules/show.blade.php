@extends('layouts.app')
@section('title', $schedule->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('employees.schedules.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Working Schedules</a>
            <span class="text-sm font-semibold text-gray-800">{{ $schedule->name }}</span>
        </div>

        <div class="ms-auto flex items-center gap-2">
            @can('update', \App\Models\Employees\Employee::class)
            <a href="{{ route('employees.schedules.edit', $schedule) }}"
               class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Edit</a>
            @endcan

            @can('delete', \App\Models\Employees\Employee::class)
            <div x-data="{ confirming: false }">
                <button type="button" x-show="!confirming" @click="confirming = true"
                        class="px-3 py-1.5 text-sm text-red-600 bg-white border border-red-200 rounded hover:bg-red-50">Delete</button>
                <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                    <span class="text-xs text-red-600">Are you sure?</span>
                    <form method="POST" action="{{ route('employees.schedules.delete', $schedule) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">Yes</button>
                    </form>
                    <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border border-gray-300 rounded">Cancel</button>
                </div>
            </div>
            @endcan
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 mb-6">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Name</p>
                    <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $schedule->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Company</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $schedule->company?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Timezone</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $schedule->timezone ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Hours per Day</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $schedule->hours_per_day ? number_format($schedule->hours_per_day, 1) . 'h' : '—' }}</p>
                </div>
            </div>

            {{-- Attendance lines --}}
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Work Hours</h4>
            @if($schedule->attendances->isEmpty())
                <p class="text-sm text-gray-400">No attendance lines defined.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left">
                                <th class="pb-2 text-xs font-semibold text-gray-500 uppercase">Day</th>
                                <th class="pb-2 text-xs font-semibold text-gray-500 uppercase">Period</th>
                                <th class="pb-2 text-xs font-semibold text-gray-500 uppercase">From</th>
                                <th class="pb-2 text-xs font-semibold text-gray-500 uppercase">To</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($schedule->attendances as $line)
                            <tr>
                                <td class="py-2 text-gray-900 font-medium">{{ $line->day_name }}</td>
                                <td class="py-2 text-gray-600 capitalize">{{ str_replace('_', ' ', $line->day_period) }}</td>
                                <td class="py-2 text-gray-600">{{ $line->hour_from_formatted }}</td>
                                <td class="py-2 text-gray-600">{{ $line->hour_to_formatted }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\ResourceCalendar"
                :model-id="$schedule->id"
                :can-comment="auth()->user()->can('update', \App\Models\Employees\Employee::class)"
                :comment-url="route('employees.schedules.comment', $schedule)"
            />
        </div>
    </div>
</div>
@endsection
