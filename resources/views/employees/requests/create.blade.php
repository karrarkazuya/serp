@extends('layouts.app')
@section('title', __('employees.new_request'))

@section('content')
{{-- JSON moved out of x-data="…" because @json's double-quote-laden output
     would close the attribute prematurely and dump the rest as text. --}}
<script type="application/json" id="request-form-subtypes">@json($subtypes)</script>
<script type="application/json" id="request-form-working-days">@json(array_values($workingDays))</script>

<div class="flex flex-col h-full bg-gray-50"
     x-data="{
        type: '{{ old('type', 'leave') }}',
        subtypeId: '{{ old('subtype_id') }}',
        startAt: '{{ old('start_at') }}',
        endAt: '{{ old('end_at') }}',
        subtypes: JSON.parse(document.getElementById('request-form-subtypes').textContent || '[]'),
        workingDays: JSON.parse(document.getElementById('request-form-working-days').textContent || '[]'),
        get isLeave() { return this.type === 'leave'; },
        get currentSubtype() {
            return this.subtypes.find(s => String(s.id) === String(this.subtypeId)) || null;
        },
        get filteredSubtypes() { return this.subtypes.filter(s => s.type === this.type); },
        // Convert a Carbon-style sys dow (0=Sat..6=Fri) from a JS Date.
        sysDowOf(d) { return (d.getDay() + 1) % 7; },
        // For a leave range, return the day-off date labels that the schedule
        // marks as off. Empty array = no hint.
        dayOffWarnings() {
            if (!this.isLeave || !this.startAt || !this.endAt) return [];
            const a = new Date(this.startAt + 'T00:00:00'),
                  b = new Date(this.endAt   + 'T00:00:00');
            if (isNaN(a) || isNaN(b) || b < a) return [];
            const labels = [];
            for (let d = new Date(a); d <= b; d.setDate(d.getDate() + 1)) {
                if (!this.workingDays.includes(this.sysDowOf(d))) {
                    labels.push(d.toDateString().slice(0, 10));
                }
            }
            return labels;
        },
        duration() {
            if (!this.startAt || !this.endAt) return '—';
            const a = new Date(this.startAt), b = new Date(this.endAt);
            if (isNaN(a) || isNaN(b) || b < a) return '—';
            if (this.isLeave) {
                const days = Math.round((b - a) / 86400000) + 1;
                return days + ' {{ __('employees.request_duration_days') }}';
            }
            const hrs = ((b - a) / 3600000);
            return hrs.toFixed(2) + ' {{ __('employees.request_duration_hours') }}';
        },
        periodText() {
            if (!this.startAt || !this.endAt) return '';
            return this.startAt.replace('T',' ') + ' → ' + this.endAt.replace('T',' ');
        }
     }">
    @php $backUrl = $fromMy ? route('employees.my-requests') : route('employees.requests.index'); @endphp
    @php $backLabel = $fromMy ? __('employees.my_requests_title') : __('employees.requests_title'); @endphp
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ $backUrl }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $backLabel }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('employees.new_request') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a href="{{ $backUrl }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.cancel') }}</a>
                <button form="request-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save_short') }}</button>
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm p-6">
            @if($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">{{ session('error') }}</div>
            @endif

            <form id="request-form" method="POST" action="{{ route('employees.requests.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                    {{-- Submission is always for the actor themselves — no
                         "submit on behalf of" path, even for HR users. --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.employee_name') }}</label>
                        <p class="py-1.5 text-sm text-gray-800">{{ $myEmployee?->name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.request_type') }} <span class="text-red-500">*</span></label>
                        <select name="type" x-model="type" class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
                            <option value="leave">{{ __('employees.leave') }}</option>
                            <option value="time_off">{{ __('employees.time_off') }}</option>
                            <option value="overtime">{{ __('employees.overtime') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.request_subtype') }} <span class="text-red-500">*</span></label>
                        <select name="subtype_id" x-model="subtypeId" class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
                            <option value="">—</option>
                            <template x-for="s in filteredSubtypes" :key="s.id">
                                <option :value="s.id" x-text="s.name"></option>
                            </template>
                        </select>
                    </div>

                    <div></div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.request_from') }} <span class="text-red-500">*</span></label>
                        <input :type="isLeave ? 'date' : 'datetime-local'" name="start_at" x-model="startAt" required
                               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.request_to') }} <span class="text-red-500">*</span></label>
                        <input :type="isLeave ? 'date' : 'datetime-local'" name="end_at" x-model="endAt" required
                               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
                    </div>

                    <div class="sm:col-span-2">
                        <div class="text-sm text-gray-600 bg-purple-50/40 border border-purple-100 rounded p-3 flex flex-col gap-1">
                            <div><span class="font-semibold">{{ __('employees.request_from') }} → {{ __('employees.request_to') }}:</span> <span x-text="periodText()"></span></div>
                            <div><span class="font-semibold">{{ __('employees.request_duration') }}:</span> <span x-text="duration()"></span></div>
                        </div>
                        {{-- Leave-only: warn if the range crosses day-off days per the
                             schedule. Submission still succeeds — the user can ignore. --}}
                        <div x-show="isLeave && dayOffWarnings().length > 0" style="display:none"
                             class="mt-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded p-3">
                            <p class="font-semibold mb-1">{{ __('employees.request_hint_includes_day_off') }}</p>
                            <p class="text-xs text-amber-600">
                                <span x-text="dayOffWarnings().join(', ')"></span>
                            </p>
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.request_field_title') }} <span class="text-red-500" x-show="currentSubtype?.requires_title">*</span></label>
                        <input type="text" name="title" value="{{ old('title') }}"
                               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('common.description') }} <span class="text-red-500" x-show="currentSubtype?.requires_description">*</span></label>
                        <textarea name="description" rows="3" class="w-full border border-gray-200 focus:border-purple-500 focus:outline-none p-2 text-sm rounded">{{ old('description') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.request_field_attachment') }} <span class="text-red-500" x-show="currentSubtype?.requires_attachment">*</span></label>
                        <input type="file" name="attachment" class="text-sm">
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
