@php
    $unit = __('employees.hours_unit');
    $selectable ??= false;
@endphp
<tr @if($grouped) x-show="open" @endif class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.attendances.show', $attendance) }}'">
    @if($selectable)
    <td class="w-10 px-3 py-2 text-center" @click.stop>
        <input type="checkbox"
               class="list-checkbox rounded border-gray-300 text-purple-600"
               x-model="selected"
               value="{{ $attendance->id }}">
    </td>
    @endif
    <td class="px-4 py-2 text-sm font-semibold text-gray-900">{{ $attendance->attendance_date?->format('M d, Y') }}</td>
    <td class="px-3 py-2 text-sm text-gray-700">
        <div class="flex items-center gap-2">
            @if($attendance->employee?->avatar)
                <img src="{{ route('files.serve', $attendance->employee->avatar) }}" alt="{{ $attendance->employee->name }}" class="w-7 h-7 rounded-full object-cover shrink-0">
            @else
                <div class="w-7 h-7 rounded-full bg-purple-100 flex items-center justify-center text-[10px] font-bold text-purple-700 shrink-0">{{ mb_strtoupper(mb_substr($attendance->employee?->name ?? '?', 0, 2)) }}</div>
            @endif
            <span>{{ $attendance->employee?->name ?? '—' }}</span>
        </div>
    </td>
    <td class="px-3 py-2 text-sm text-gray-600 whitespace-nowrap">{{ $attendance->check_in?->format('H:i') ?? '—' }}</td>
    <td class="px-3 py-2 text-sm text-gray-600 whitespace-nowrap">{{ $attendance->check_out?->format('H:i') ?? '—' }}</td>
    <td class="px-3 py-2 text-sm text-gray-800 text-end whitespace-nowrap">{{ number_format((float) $attendance->worked_hours, 2) }} {{ $unit }}</td>
    <td class="px-3 py-2 text-sm text-end whitespace-nowrap {{ $attendance->overtime_hours > 0 ? 'text-green-600 font-medium' : 'text-gray-500' }}">{{ number_format((float) $attendance->overtime_hours, 2) }} {{ $unit }}</td>
    <td class="px-3 py-2 text-sm text-end whitespace-nowrap {{ $attendance->shortage_hours > 0 ? 'text-red-600 font-medium' : 'text-gray-500' }}">{{ number_format((float) $attendance->shortage_hours, 2) }} {{ $unit }}</td>
    <td class="px-3 py-2 text-sm">
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $attendance->status_color }}-100 text-{{ $attendance->status_color }}-700">
            @php
                $statusKey = $attendance->is_day_off ? 'day_off' : ($attendance->is_absence ? 'absence' : 'present');
            @endphp
            {{ __('employees.attendance_status_' . $statusKey) }}
        </span>
    </td>
</tr>
