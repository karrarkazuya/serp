@php
    /** @var \App\Models\Employees\Employee $employee */
    /** @var \Illuminate\Support\Collection $plannedDays */
    /** @var \Illuminate\Support\Collection $plannedPattern */
    $today    = \Carbon\Carbon::today();
    $tomorrow = $today->copy()->addDay();
    $calendars = \App\Models\Employees\ResourceCalendar::query()
        ->where('active', true)
        ->where(function ($q) use ($employee) {
            $q->whereNull('company_id');
            if ($employee->company_id) {
                $q->orWhere('company_id', $employee->company_id);
            }
        })
        ->orderBy('name')
        ->get(['id', 'name']);
@endphp

@can('update', $employee)
@php $canEdit = auth()->user()->hasPermission('planned_schedules.write'); @endphp
@else
@php $canEdit = false; @endphp
@endcan

@if(!$employee->resource_calendar_id)
    <div class="py-12 text-center text-gray-400 text-sm">{{ __('employees.planned_no_calendar') }}</div>
@else
<div class="space-y-4">
    <p class="text-xs text-gray-500">{{ __('employees.planned_schedule_intro') }}</p>

    @if($canEdit)
    {{-- Pattern wizard launcher --}}
    <div x-data="{ open: false, items: @json($plannedPattern->pluck('resource_calendar_id')->all() ?: []) }">
        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" @click="open = true"
                    class="px-3 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">
                {{ __('employees.apply_pattern') }}
            </button>
            @if($plannedPattern->isNotEmpty())
                <span class="text-xs text-gray-500">{{ __('employees.pattern_sequence') }}:</span>
                @foreach($plannedPattern as $p)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700">{{ $p->resourceCalendar?->name ?? '—' }}</span>
                @endforeach
            @endif
        </div>

        {{-- Modal --}}
        <div x-show="open" x-cloak style="display:none"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
            <div @click.outside="open = false" class="bg-white rounded-xl shadow-xl w-full max-w-2xl">
                <form method="POST" action="{{ route('employees.planned-schedule.pattern', $employee) }}" class="p-6 space-y-4">
                    @csrf
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('employees.apply_pattern') }}</h3>
                    <p class="text-xs text-gray-500">{{ __('employees.pattern_hint') }}</p>

                    {{-- Sequence builder --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">{{ __('employees.pattern_sequence') }}</label>
                        <div class="space-y-2">
                            <template x-for="(item, idx) in items" :key="idx">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-400 w-6 text-end" x-text="(idx + 1) + '.'"></span>
                                    <select :name="'pattern[' + idx + ']'" x-model.number="items[idx]"
                                            class="flex-1 border border-gray-300 rounded px-2 py-1.5 text-sm">
                                        @foreach($calendars as $c)
                                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" @click="items.splice(idx, 1)"
                                            class="text-red-500 hover:text-red-700 text-xs px-2">×</button>
                                </div>
                            </template>
                            <button type="button" @click="items.push({{ $calendars->first()?->id ?? 'null' }})"
                                    class="text-xs text-purple-600 hover:text-purple-700">+ {{ __('employees.pattern_add_schedule') }}</button>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t border-gray-100">
                        <button type="button" @click="open = false"
                                class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.cancel') }}</button>
                        <button type="submit"
                                class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded">{{ __('employees.planned_save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- 30-day plan list --}}
    <div class="border border-gray-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide w-32">{{ __('employees.attendance_date') }}</th>
                    <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide w-24">{{ __('employees.day') }}</th>
                    <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('employees.schedule') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($plannedDays as $day)
                @php
                    $isToday = $day->planned_date->isSameDay($today);
                    $isTomorrow = $day->planned_date->isSameDay($tomorrow);
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 text-gray-700">
                        {{ $day->planned_date->format('M d, Y') }}
                        @if($isToday)
                            <span class="ms-1 text-[10px] font-semibold text-purple-700 bg-purple-100 rounded px-1.5 py-0.5">{{ __('employees.planned_today') }}</span>
                        @elseif($isTomorrow)
                            <span class="ms-1 text-[10px] font-semibold text-blue-700 bg-blue-50 rounded px-1.5 py-0.5">{{ __('employees.planned_tomorrow') }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-gray-600">{{ $day->planned_date->format('D') }}</td>
                    <td class="px-3 py-2">
                        @if($canEdit)
                        <form method="POST" action="{{ route('employees.planned-schedule.set-day', $employee) }}"
                              class="flex items-center gap-2">
                            @csrf
                            <input type="hidden" name="planned_date" value="{{ $day->planned_date->toDateString() }}">
                            <select name="resource_calendar_id" onchange="this.form.submit()"
                                    class="text-sm border border-gray-200 rounded px-2 py-1 bg-white">
                                @foreach($calendars as $c)
                                    <option value="{{ $c->id }}" {{ $c->id === $day->resource_calendar_id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </form>
                        @else
                            <span class="text-gray-700">{{ $day->resourceCalendar?->name ?? '—' }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-3 py-8 text-center text-sm text-gray-400">{{ __('employees.no_schedule') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
