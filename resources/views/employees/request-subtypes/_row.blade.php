@php $selectable ??= false; @endphp
<tr @if($grouped) x-show="open" @endif class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.request-subtypes.show', $subtype) }}'">
    @if($selectable)
    <td class="w-10 px-3 py-2 text-center" @click.stop>
        <input type="checkbox" class="list-checkbox rounded border-gray-300 text-purple-600" x-model="selected" value="{{ $subtype->id }}">
    </td>
    @endif
    <td class="px-4 py-2 text-sm font-semibold text-gray-900">{{ $subtype->name }}</td>
    <td class="px-3 py-2 text-sm text-gray-700">
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700">{{ __('employees.' . $subtype->type) }}</span>
    </td>
    <td class="px-3 py-2 text-sm text-gray-600">{{ $subtype->company?->name ?? '—' }}</td>
    <td class="px-3 py-2 text-sm text-gray-700">{{ $subtype->type === 'overtime' ? number_format((float) $subtype->factor, 2) . 'x' : '—' }}</td>
</tr>
