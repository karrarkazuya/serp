@php $selectable ??= false; @endphp
<tr @if($grouped) x-show="open" @endif class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.requests.show', $r) }}'">
    @if($selectable)
    <td class="w-10 px-3 py-2 text-center" @click.stop>
        <input type="checkbox" class="list-checkbox rounded border-gray-300 text-purple-600" x-model="selected" value="{{ $r->id }}">
    </td>
    @endif
    <td class="px-4 py-2 text-sm font-semibold text-gray-900">{{ $r->employee?->name ?? '—' }}</td>
    <td class="px-3 py-2 text-sm text-gray-700">{{ __('employees.' . $r->type) }}</td>
    <td class="px-3 py-2 text-sm text-gray-700">{{ $r->subtype?->name }}</td>
    <td class="px-3 py-2 text-sm text-gray-700 whitespace-nowrap">{{ $r->start_at?->format($r->type === 'leave' ? 'M d, Y' : 'M d H:i') }}</td>
    <td class="px-3 py-2 text-sm text-gray-700 whitespace-nowrap">{{ $r->end_at?->format($r->type === 'leave' ? 'M d, Y' : 'M d H:i') }}</td>
    <td class="px-3 py-2 text-sm">
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $r->state_color }}-100 text-{{ $r->state_color }}-700">{{ __('employees.request_state_' . $r->state) }}</span>
    </td>
</tr>
