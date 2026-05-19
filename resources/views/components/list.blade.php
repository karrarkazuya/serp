<div class="flex-1 overflow-auto {{ $class }}">
    <table class="w-full text-sm border-collapse">
        @isset($columns)
        <thead>
            <tr class="border-b border-gray-200 bg-gray-50">
                {{ $columns }}
            </tr>
        </thead>
        @endisset
        <tbody class="divide-y divide-gray-100">
            @if($isEmpty)
                <tr>
                    <td colspan="100" class="px-4 py-20 text-center text-sm text-gray-400">{{ $emptyText }}</td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>

@if($hasPagination)
<div class="bg-white border-t border-gray-200 px-6 py-3">
    {{ $paginator->withQueryString()->links() }}
</div>
@endif
