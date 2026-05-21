@php
    $dayNames = \App\Models\Employees\ResourceCalendar::$dayNames;
    $periods  = ['morning' => 'Morning', 'afternoon' => 'Afternoon', 'evening' => 'Evening', 'night' => 'Night'];
@endphp

<form id="schedule-form" method="POST"
      action="{{ $schedule ? route('employees.schedules.update', $schedule) : route('employees.schedules.store') }}">
    @csrf
    @if($schedule) @method('PUT') @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 mb-6">
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Schedule Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $schedule?->name) }}"
                   class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent" required>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Company</label>
            <x-relation-dropdown
                name="company_id"
                table="companies"
                field="name"
                relation="many2one"
                :selected="old('company_id', $schedule?->company_id)"
            />
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Timezone</label>
            <input type="text" name="timezone" value="{{ old('timezone', $schedule?->timezone ?? 'Asia/Riyadh') }}"
                   class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent"
                   placeholder="e.g. Asia/Riyadh">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Hours per Day</label>
            <input type="number" step="0.5" name="hours_per_day" value="{{ old('hours_per_day', $schedule?->hours_per_day ?? 8) }}" min="0"
                   class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
        </div>
    </div>

    {{-- Attendance lines --}}
    <div x-data="scheduleForm()" x-init="init()">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-gray-700">Work Hours</h4>
            <button type="button" @click="addLine()"
                    class="text-sm text-purple-600 hover:text-purple-800 font-medium">+ Add Line</button>
        </div>

        <div class="space-y-2">
            <template x-for="(line, index) in lines" :key="index">
                <div class="grid grid-cols-12 gap-2 items-center">
                    <div class="col-span-3">
                        <select :name="'attendances[' + index + '][day_of_week]'"
                                x-model="line.day_of_week"
                                class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:border-purple-500 focus:outline-none">
                            @foreach($dayNames as $num => $name)
                            <option value="{{ $num }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-2">
                        <select :name="'attendances[' + index + '][day_period]'"
                                x-model="line.day_period"
                                class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:border-purple-500 focus:outline-none">
                            @foreach($periods as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-3">
                        <input type="time" :name="'attendances[' + index + '][hour_from]'"
                               x-model="line.hour_from"
                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:border-purple-500 focus:outline-none">
                    </div>
                    <div class="col-span-3">
                        <input type="time" :name="'attendances[' + index + '][hour_to]'"
                               x-model="line.hour_to"
                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:border-purple-500 focus:outline-none">
                    </div>
                    <div class="col-span-1 flex justify-end">
                        <button type="button" @click="removeLine(index)"
                                class="text-red-400 hover:text-red-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <div class="grid grid-cols-12 gap-2 mt-1 pb-1 border-b border-gray-200">
            <div class="col-span-3 text-xs text-gray-400 font-semibold uppercase">Day</div>
            <div class="col-span-2 text-xs text-gray-400 font-semibold uppercase">Period</div>
            <div class="col-span-3 text-xs text-gray-400 font-semibold uppercase">From</div>
            <div class="col-span-3 text-xs text-gray-400 font-semibold uppercase">To</div>
        </div>
    </div>
</form>

@php
$initialLines = ($schedule && $schedule->attendances->isNotEmpty())
    ? $schedule->attendances->map(fn($a) => [
        'day_of_week' => (string) $a->day_of_week,
        'day_period'  => $a->day_period,
        'hour_from'   => sprintf('%02d:%02d', (int) $a->hour_from, (int) (($a->hour_from - floor($a->hour_from)) * 60)),
        'hour_to'     => sprintf('%02d:%02d', (int) $a->hour_to,   (int) (($a->hour_to   - floor($a->hour_to))   * 60)),
    ])->values()->all()
    : [];
@endphp

<script>
function scheduleForm() {
    return {
        lines: [],
        init() {
            this.lines = @json($initialLines);
        },
        addLine() {
            this.lines.push({ day_of_week: '0', day_period: 'morning', hour_from: '08:00', hour_to: '12:00' });
        },
        removeLine(index) {
            this.lines.splice(index, 1);
        }
    };
}
</script>
