@php
    $dayNamesMap = \App\Models\Employees\ResourceCalendar::$dayNames;

    $initialLines = ($schedule && $schedule->attendances->isNotEmpty())
        ? $schedule->attendances->map(fn($a) => [
            'day_of_week' => (string) $a->day_of_week,
            'hour_from'   => sprintf('%02d:%02d', (int) $a->hour_from, (int) round(($a->hour_from - floor($a->hour_from)) * 60)),
            'hour_to'     => sprintf('%02d:%02d',
                (int) ($a->hour_to > 24 ? $a->hour_to - 24 : $a->hour_to),
                (int) round((($a->hour_to > 24 ? $a->hour_to - 24 : $a->hour_to) - floor($a->hour_to > 24 ? $a->hour_to - 24 : $a->hour_to)) * 60)
            ),
            'next_day' => $a->hour_to > 24 ? '1' : '0',
        ])->values()->all()
        : [];

    $leftDays  = array_slice($dayNamesMap, 0, 4, true);
    $rightDays = array_slice($dayNamesMap, 4, 3, true);
@endphp

<form id="schedule-form" method="POST"
      action="{{ $schedule ? route('employees.schedules.update', $schedule) : route('employees.schedules.store') }}">
    @csrf
    @if($schedule) @method('PUT') @endif

    {{-- Name (large heading style) --}}
    <div class="border-b border-gray-200 pb-4 mb-5">
        <input type="text" name="name" value="{{ old('name', $schedule?->name) }}"
               placeholder="{{ __('employees.schedule_name') }}"
               class="w-full text-xl font-semibold text-gray-900 border-0 focus:outline-none bg-transparent placeholder-gray-300"
               required>
    </div>

    {{-- Fields grid --}}
    <div class="grid grid-cols-2 gap-x-16 mb-6">
        {{-- Left column --}}
        <div class="divide-y divide-gray-100">
            <div class="flex items-center py-2.5">
                <span class="w-48 text-sm text-gray-500 shrink-0">{{ __('employees.flexible_hours') }}</span>
                <input type="checkbox" name="flexible_hours" value="1"
                       {{ old('flexible_hours', $schedule?->flexible_hours) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-400">
            </div>
            <div class="flex items-center py-2.5">
                <span class="w-48 text-sm text-gray-500 shrink-0">{{ __('employees.company_full_time') }}</span>
                <div class="flex items-center gap-2">
                    <input type="number" step="0.5" name="company_hours_per_week"
                           value="{{ old('company_hours_per_week', $schedule?->company_hours_per_week ?? 40) }}"
                           min="0" max="168"
                           class="w-16 border-b border-gray-300 focus:border-purple-500 focus:outline-none py-0.5 text-sm bg-transparent text-right">
                    <span class="text-sm text-gray-400">{{ __('employees.hours_per_week') }}</span>
                </div>
            </div>
            <div class="flex items-center py-2.5">
                <span class="w-48 text-sm text-gray-500 shrink-0">{{ __('employees.avg_hour_per_day') }}</span>
                <input type="number" step="0.25" name="hours_per_day"
                       value="{{ old('hours_per_day', $schedule?->hours_per_day ?? 8) }}"
                       min="0" max="24"
                       class="w-16 border-b border-gray-300 focus:border-purple-500 focus:outline-none py-0.5 text-sm bg-transparent text-right">
            </div>
        </div>

        {{-- Right column --}}
        <div class="divide-y divide-gray-100">
            <div class="flex items-center py-2.5">
                <span class="w-28 text-sm text-gray-500 shrink-0">{{ __('common.company') }}</span>
                <div class="flex-1">
                    <x-relation-dropdown name="company_id" table="companies" field="name" relation="many2one"
                        :selected="old('company_id', $schedule?->company_id)" />
                </div>
            </div>
            <div class="flex items-center py-2.5">
                <span class="w-28 text-sm text-gray-500 shrink-0">{{ __('employees.timezone') }}</span>
                <input type="text" name="timezone"
                       value="{{ old('timezone', $schedule?->timezone ?? 'Asia/Baghdad') }}"
                       list="tz-list"
                       class="flex-1 border-b border-gray-300 focus:border-purple-500 focus:outline-none py-0.5 text-sm bg-transparent"
                       placeholder="e.g. Asia/Baghdad">
                <datalist id="tz-list">
                    @foreach(\DateTimeZone::listIdentifiers() as $tz)
                    <option value="{{ $tz }}">
                    @endforeach
                </datalist>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div x-data="scheduleEditor()" x-init="init()">
        {{-- Tab headers --}}
        <div class="flex border-b border-gray-200 mb-5">
            <button type="button" @click="tab = 'working_hours'"
                    :class="tab === 'working_hours'
                        ? 'border-b-2 border-gray-900 text-gray-900 font-medium -mb-px'
                        : 'text-purple-600 hover:text-purple-800'"
                    class="px-4 pb-2.5 text-sm transition-colors">{{ __('employees.working_hours') }}</button>
            <button type="button" @click="tab = 'modifier'"
                    :class="tab === 'modifier'
                        ? 'border-b-2 border-gray-900 text-gray-900 font-medium -mb-px'
                        : 'text-purple-600 hover:text-purple-800'"
                    class="px-4 pb-2.5 text-sm transition-colors">{{ __('employees.modifier_tab') }}</button>
            @if($schedule && $schedule->employees->isNotEmpty())
            <button type="button" @click="tab = 'employees'"
                    :class="tab === 'employees'
                        ? 'border-b-2 border-gray-900 text-gray-900 font-medium -mb-px'
                        : 'text-purple-600 hover:text-purple-800'"
                    class="px-4 pb-2.5 text-sm transition-colors">
                {{ __('employees.employees_tab') }}
                <span class="ml-1 text-xs text-gray-400">({{ $schedule->employees->count() }})</span>
            </button>
            @endif
        </div>

        {{-- Working Hours tab --}}
        <div x-show="tab === 'working_hours'">
            <div x-show="lines.length === 0" class="py-8 text-center">
                <p class="text-sm text-gray-400">{{ __('employees.no_schedule_hours') }}</p>
                <button type="button" @click="tab = 'modifier'"
                        class="mt-2 text-sm text-purple-600 hover:text-purple-800 hover:underline">
                    {{ __('employees.configure_modifier') }}
                </button>
            </div>

            <div x-show="lines.length > 0">
                <div class="grid grid-cols-[1fr_130px_150px_32px] gap-2 pb-2 mb-1 border-b border-gray-200">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('employees.day') }}</div>
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('employees.from') }}</div>
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('employees.to') }}</div>
                    <div></div>
                </div>
                <template x-for="(line, index) in lines" :key="index">
                    <div class="grid grid-cols-[1fr_130px_150px_32px] gap-2 items-center py-2 border-b border-gray-50 hover:bg-gray-50 rounded">
                        <div class="text-sm text-gray-900 font-medium" x-text="dayName(line.day_of_week)"></div>
                        <div class="text-sm text-gray-600" x-text="line.hour_from"></div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-sm text-gray-600" x-text="line.hour_to"></span>
                            <span x-show="line.next_day == '1'"
                                  class="text-xs font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">+1</span>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" @click="lines.splice(index, 1)"
                                    class="text-gray-300 hover:text-red-500 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <input type="hidden" :name="'attendances[' + index + '][day_of_week]'" :value="line.day_of_week">
                        <input type="hidden" :name="'attendances[' + index + '][hour_from]'" :value="line.hour_from">
                        <input type="hidden" :name="'attendances[' + index + '][hour_to]'" :value="line.hour_to">
                        <input type="hidden" :name="'attendances[' + index + '][next_day]'" :value="line.next_day">
                    </div>
                </template>
            </div>
        </div>

        {{-- Modifier tab --}}
        <div x-show="tab === 'modifier'" style="display:none">
            <div class="grid grid-cols-2 gap-x-16">
                {{-- Working Days --}}
                <div>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __('employees.work_days') }}</p>
                    <div class="flex gap-10">
                        {{-- Left days: Saturday–Tuesday --}}
                        <div class="space-y-3">
                            @foreach($leftDays as $num => $dayLabel)
                            <label class="flex items-center gap-2.5 cursor-pointer select-none">
                                <input type="checkbox"
                                       :checked="modifier.days.includes({{ $num }})"
                                       @change="toggleDay({{ $num }}, $event.target.checked)"
                                       class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-400">
                                <span class="text-sm text-gray-700">{{ $dayLabel }}</span>
                            </label>
                            @endforeach
                        </div>
                        {{-- Right days: Wednesday–Friday --}}
                        <div class="space-y-3">
                            @foreach($rightDays as $num => $dayLabel)
                            <label class="flex items-center gap-2.5 cursor-pointer select-none">
                                <input type="checkbox"
                                       :checked="modifier.days.includes({{ $num }})"
                                       @change="toggleDay({{ $num }}, $event.target.checked)"
                                       class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-400">
                                <span class="text-sm text-gray-700">{{ $dayLabel }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Working Hours --}}
                <div>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __('employees.working_hours') }}</p>
                    <div class="space-y-3">
                        <div class="flex items-center gap-6">
                            <span class="text-sm text-gray-500 w-24">{{ __('employees.start_time') }}</span>
                            <input type="time" x-model="modifier.startTime"
                                   class="border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1 text-sm bg-transparent w-28">
                        </div>
                        <div class="flex items-center gap-6">
                            <span class="text-sm text-gray-500 w-24">{{ __('employees.end_time') }}</span>
                            <div class="flex items-center gap-3">
                                <input type="time" x-model="modifier.endTime"
                                       class="border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1 text-sm bg-transparent w-28">
                                <span x-show="isOvernight()" x-cloak
                                      class="text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded">
                                    {{ __('employees.next_day') }}
                                </span>
                            </div>
                        </div>
                        <div x-show="isOvernight()" x-cloak
                             class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded px-3 py-2 leading-relaxed">
                            {{ __('employees.overnight_warning') }}
                        </div>
                    </div>
                    <button type="button" @click="applyModifier()"
                            class="mt-6 px-4 py-2 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm transition-colors">
                        {{ __('common.apply') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Employees tab --}}
        @if($schedule && $schedule->employees->isNotEmpty())
        <div x-show="tab === 'employees'" style="display:none">
            <div class="divide-y divide-gray-100">
                @foreach($schedule->employees as $emp)
                <div class="flex items-center gap-3 py-2.5">
                    <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">
                        {{ mb_strtoupper(mb_substr($emp->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('employees.show', $emp) }}"
                           class="text-sm font-medium text-gray-900 hover:text-purple-700">{{ $emp->name }}</a>
                        @if($emp->job)
                        <p class="text-xs text-gray-500">{{ $emp->job->name }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</form>

<script>
function scheduleEditor() {
    const dayMap = {
        '0': 'Saturday', '1': 'Sunday',  '2': 'Monday',  '3': 'Tuesday',
        '4': 'Wednesday','5': 'Thursday', '6': 'Friday',
    };
    return {
        tab: 'working_hours',
        lines: [],
        modifier: { days: [], startTime: '09:00', endTime: '17:00' },

        init() {
            this.lines = @json($initialLines);
            if (this.lines.length === 0) {
                this.tab = 'modifier';
            } else {
                this.inferModifier();
            }
        },

        dayName(dow) {
            return dayMap[String(dow)] || 'Unknown';
        },

        inferModifier() {
            if (!this.lines.length) return;
            this.modifier.days      = [...new Set(this.lines.map(l => parseInt(l.day_of_week)))];
            this.modifier.startTime = this.lines[0].hour_from;
            this.modifier.endTime   = this.lines[0].hour_to;
        },

        toggleDay(day, checked) {
            if (checked) {
                if (!this.modifier.days.includes(day)) this.modifier.days.push(day);
            } else {
                this.modifier.days = this.modifier.days.filter(d => d !== day);
            }
        },

        isOvernight() {
            const s = this.toDecimal(this.modifier.startTime);
            const e = this.toDecimal(this.modifier.endTime);
            return e > 0 && e <= s;
        },

        applyModifier() {
            if (!this.modifier.days.length) return;
            const overnight = this.isOvernight();
            this.lines = [...this.modifier.days].sort((a, b) => a - b).map(day => ({
                day_of_week: String(day),
                hour_from:   this.modifier.startTime,
                hour_to:     this.modifier.endTime,
                next_day:    overnight ? '1' : '0',
            }));
            this.tab = 'working_hours';
        },

        toDecimal(time) {
            const [h, m] = (time || '00:00').split(':').map(Number);
            return h + m / 60;
        },
    };
}
</script>
