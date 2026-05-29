@php
    $val = fn($field, $default = '') => old($field, $employee?->{$field} ?? $default);
    $selectedCategoryIds = old('categories', $employee ? $employee->categories->pluck('id')->toArray() : []);
@endphp

@if($errors->any())
<div class="px-6 pt-4 pb-0">
    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
        <p class="text-sm font-medium text-red-700 mb-1">{{ __('employees.fix_errors') }}</p>
        <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
</div>
@endif

<div class="p-6"
     x-data="{
         avatarPreview: '{{ $employee?->avatar_url ?? '' }}',
         warnings: {},
         checkLinkConflict(field, value) {
             if (!value) { delete this.warnings[field]; return; }
             fetch('{{ route('employees.check-link') }}?field=' + field + '&value=' + value + '&exclude={{ $employee?->id ?? '' }}')
                 .then(r => r.json())
                 .then(data => {
                     if (data.conflict) {
                         this.warnings[field] = data.employee.name;
                     } else {
                         delete this.warnings[field];
                     }
                 });
         }
     }"
     @emp-user-changed="checkLinkConflict('user_id', $event.detail.value)"
     @emp-contact-changed="checkLinkConflict('contact_id', $event.detail.value)">

    {{-- Name + job title --}}
    <div class="mb-1">
        <input type="text" name="name" value="{{ $val('name') }}" required placeholder="{{ __('employees.employee_name') }}"
               class="w-full text-3xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent {{ $errors->has('name') ? 'border-red-400' : 'border-gray-200' }}">
    </div>
    <div class="mb-5 flex gap-4 items-end">
        <input type="text" name="job_title" value="{{ $val('job_title') }}" placeholder="{{ __('employees.job_position') }}"
               class="flex-1 text-sm text-gray-500 placeholder-gray-300 border-0 focus:outline-none bg-transparent pb-0.5">
        <input type="text" name="scientific_title" value="{{ $val('scientific_title') }}" placeholder="{{ __('employees.scientific_title') }}"
               class="flex-1 text-sm text-gray-400 placeholder-gray-300 border-0 focus:outline-none bg-transparent pb-0.5">
    </div>

    {{-- Main header: three columns + avatar --}}
    <div class="flex gap-6 items-start mb-0">

        {{-- Left col: contact + org fields --}}
        <div class="flex-1 min-w-0">
            @foreach([
                [__('employees.full_name_ar'), 'name_ar',  'text'],
                [__('employees.full_name_en'), 'name_en',  'text'],
                [__('employees.family_name'),  'family_name','text'],
                [__('employees.mother_name'),  'mother_name','text'],
                [__('employees.work_email'),   'work_email', 'email'],
                [__('employees.work_phone'),   'work_phone', 'text'],
                [__('employees.work_mobile'),  'work_mobile','text'],
            ] as [$label, $name, $type])
            <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                <label class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</label>
                <input type="{{ $type }}" name="{{ $name }}" value="{{ $val($name) }}"
                       class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
            </div>
            @endforeach

            {{-- Tags --}}
            <div class="py-1.5 border-b border-gray-100">
                <x-relation-dropdown
                    table="hr_employee_categories"
                    field="name"
                    name="categories"
                    :label="__('employees.tags')"
                    :selected="$selectedCategoryIds"
                    relation="many2many"
                />
            </div>

            <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('common.company') }}</label>
                <x-relation-dropdown table="companies" field="name" name="company_id" relation="many2one"
                    :selected="old('company_id', $employee?->company_id ?? ($defaultCompanyId ?? null))" class="flex-1" compact />
            </div>
        </div>

        {{-- Right col: org fields --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('common.department') }}</label>
                <x-relation-dropdown table="hr_departments" field="name" name="department_id" relation="many2one"
                    :selected="old('department_id', $employee?->department_id)" class="flex-1" compact />
            </div>
            <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.job_position') }}</label>
                <x-relation-dropdown table="hr_jobs" field="name" name="job_id" relation="many2one"
                    :selected="old('job_id', $employee?->job_id)" class="flex-1" compact />
            </div>
            <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('common.manager') }}</label>
                <x-relation-dropdown table="hr_employees" field="name" name="parent_id" relation="many2one"
                    :selected="old('parent_id', $employee?->parent_id)"
                    :exclude="$employee?->id" class="flex-1" compact />
            </div>
            <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.coach') }}</label>
                <x-relation-dropdown table="hr_employees" field="name" name="coach_id" relation="many2one"
                    :selected="old('coach_id', $employee?->coach_id)"
                    :exclude="$employee?->id" class="flex-1" compact />
            </div>
            <div class="flex flex-col py-1.5 border-b border-gray-100 gap-0.5">
                <div class="flex items-center gap-4">
                    <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.linked_user') }}</label>
                    <x-relation-dropdown table="users" field="name" name="user_id" relation="many2one"
                        :selected="old('user_id', $employee?->user_id)" class="flex-1" compact event="emp-user-changed" />
                </div>
                <div x-show="warnings.user_id" x-cloak class="flex items-center gap-1.5 pl-48 text-xs text-amber-700">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                    {{ __('employees.already_linked_to') }} <span class="font-semibold" x-text="warnings.user_id"></span>
                </div>
            </div>
            <div class="flex flex-col py-1.5 border-b border-gray-100 gap-0.5">
                <div class="flex items-center gap-4">
                    <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.linked_contact') }}</label>
                    <x-relation-dropdown table="contacts" field="name" name="contact_id" relation="many2one"
                        :selected="old('contact_id', $employee?->contact_id)" class="flex-1" compact event="emp-contact-changed" />
                </div>
                <div x-show="warnings.contact_id" x-cloak class="flex items-center gap-1.5 pl-48 text-xs text-amber-700">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                    {{ __('employees.already_linked_to') }} <span class="font-semibold" x-text="warnings.contact_id"></span>
                </div>
            </div>
        </div>

        {{-- Avatar --}}
        <div class="shrink-0 w-28">
            <label class="block cursor-pointer">
                <div class="w-28 h-28 rounded-xl overflow-hidden border border-gray-200 shadow-sm">
                    <img x-show="avatarPreview" :src="avatarPreview" class="w-full h-full object-cover" style="display:none">
                    <div x-show="!avatarPreview" class="w-full h-full flex items-center justify-center text-3xl font-bold bg-purple-100 text-purple-700">
                        {{ $employee ? strtoupper(substr($employee->name, 0, 2)) : '?' }}
                    </div>
                </div>
                <input type="file" name="avatar" accept="image/*" class="hidden"
                       @change="avatarPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : avatarPreview">
                <p class="mt-1.5 text-center text-xs text-gray-400">{{ __('employees.click_to_upload') }}</p>
            </label>
        </div>
    </div>

    {{-- Tabs --}}
    <div x-data="{ tab: 'work' }" class="border-t border-gray-200 mt-5">
        <div class="flex items-end gap-1 pt-3 border-b border-gray-200 overflow-x-auto">
            @foreach([
                ['work',    __('employees.work_info')],
                ['private', __('employees.private_info')],
                ['hr',      __('employees.hr_settings')],
                ['skills',  __('employees.skills_tab')],
            ] as [$key, $label])
            <button type="button"
                    @click="tab = '{{ $key }}'"
                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white shrink-0"
                    :class="tab === '{{ $key }}' ? 'text-gray-900 border-gray-300 -mb-px pb-2.5' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                {{ $label }}
            </button>
            @endforeach
        </div>

        {{-- Work Information --}}
        <div x-show="tab === 'work'" style="display:none" class="pt-4">
            <div class="flex gap-8">
                <div class="flex-1">
                    <p class="text-xs font-semibold text-gray-400 uppercase mb-2">{{ __('employees.location') }}</p>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.work_location') }}</label>
                        <x-relation-dropdown table="hr_work_locations" field="name" name="work_location_id" relation="many2one"
                            :selected="old('work_location_id', $employee?->work_location_id)" class="flex-1" compact />
                    </div>

                    <p class="text-xs font-semibold text-gray-400 uppercase mt-4 mb-2">{{ __('employees.approvers') }}</p>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.expense_approver') }}</label>
                        <x-relation-dropdown table="hr_employees" field="name" name="expense_manager_id" relation="many2one"
                            :selected="old('expense_manager_id', $employee?->expense_manager_id)"
                            :exclude="$employee?->id" class="flex-1" compact />
                    </div>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.attendance_approver') }}</label>
                        <x-relation-dropdown table="hr_employees" field="name" name="attendance_manager_id" relation="many2one"
                            :selected="old('attendance_manager_id', $employee?->attendance_manager_id)"
                            :exclude="$employee?->id" class="flex-1" compact />
                    </div>

                    <p class="text-xs font-semibold text-gray-400 uppercase mt-4 mb-2">{{ __('employees.schedule') }}</p>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.working_hours') }}</label>
                        <x-relation-dropdown table="hr_resource_calendars" field="name" name="resource_calendar_id" relation="many2one"
                            :selected="old('resource_calendar_id', $employee?->resource_calendar_id)" class="flex-1" compact />
                    </div>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.timezone') }}</label>
                        <input type="text" name="timezone" value="{{ $val('timezone') }}"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="e.g. Asia/Baghdad">
                    </div>
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold text-gray-400 uppercase">{{ __('employees.position_section') }}</p>
                        @if($employee?->exists)
                        <a href="{{ route('employees.positions.index') }}"
                           class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.view_all_positions') }}</a>
                        @endif
                    </div>
                    @php $cp = $employee?->currentPosition(); @endphp
                    @if($cp)
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.organizational_structure') }}</span>
                        <span class="flex-1 text-sm text-gray-800">{{ $cp->organizational_structure ?? '—' }}</span>
                    </div>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.assignment_type') }}</span>
                        <span class="flex-1 text-sm text-gray-800">{{ $cp->assignment_type ?? '—' }}</span>
                    </div>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.data_status') }}</span>
                        <span class="flex-1 text-sm text-gray-800">
                            @if($cp->data_status)
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $cp->data_status === 'current' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $cp->data_status === 'current' ? __('employees.data_status_current') : __('employees.data_status_previous') }}
                            </span>
                            @else —
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.financial_specialization') }}</span>
                        <span class="flex-1 text-sm text-gray-800">{{ $cp->financial_specialization ? number_format($cp->financial_specialization, 2) : '—' }}</span>
                    </div>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.affective_date') }}</span>
                        <span class="flex-1 text-sm text-gray-800">{{ $cp->affective_date?->format('d M Y') ?? '—' }}</span>
                    </div>
                    <div class="mt-2">
                        <a href="{{ route('employees.positions.index', ['search' => $employee->name]) }}"
                           class="text-xs text-purple-600 hover:underline">{{ __('employees.view_all_positions') }}</a>
                    </div>
                    @else
                    <p class="text-sm text-gray-400 py-2">{{ __('employees.no_current_position') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Private Information --}}
        <div x-show="tab === 'private'" style="display:none" class="pt-4">
            <div class="flex gap-8">
                {{-- Left col --}}
                <div class="flex-1">
                    <p class="text-xs font-semibold text-gray-400 uppercase mb-2">{{ __('employees.private_contact') }}</p>
                    <div class="flex items-start gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500 pt-0.5">{{ __('employees.private_address') }}</label>
                        <textarea name="private_address" rows="2" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0 resize-none" placeholder="-">{{ $val('private_address') }}</textarea>
                    </div>
                    @foreach([
                        [__('employees.private_email'),  'private_email',  'email'],
                        [__('employees.private_phone'),  'private_phone',  'text'],
                        [__('employees.private_mobile'), 'private_mobile', 'text'],
                    ] as [$label, $name, $type])
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</label>
                        <input type="{{ $type }}" name="{{ $name }}" value="{{ $val($name) }}"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
                    </div>
                    @endforeach
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.home_work_distance') }}</label>
                        <div class="flex items-center gap-1 flex-1">
                            <input type="number" name="km_home_work" value="{{ $val('km_home_work') }}" min="0"
                                   class="w-24 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="0">
                            <span class="text-sm text-gray-400">{{ __('employees.km') }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.private_car_plate') }}</label>
                        <input type="text" name="private_car_plate" value="{{ $val('private_car_plate') }}"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
                    </div>

                    <p class="text-xs font-semibold text-gray-400 uppercase mt-4 mb-2">{{ __('employees.emergency') }}</p>
                    @foreach([
                        [__('employees.contact_name'), 'emergency_contact',  'text'],
                        [__('common.phone'),           'emergency_phone',    'text'],
                        [__('employees.relationship'), 'emergency_relation', 'text'],
                    ] as [$label, $name, $type])
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</label>
                        <input type="{{ $type }}" name="{{ $name }}" value="{{ $val($name) }}"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
                    </div>
                    @endforeach

                    <p class="text-xs font-semibold text-gray-400 uppercase mt-4 mb-2">{{ __('employees.family_status') }}</p>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.marital_status') }}</label>
                        {{-- Marital status options were hardcoded English strings — Arabic
                             users saw "Single / Married / Cohabitant / Widower / Divorced"
                             on every form. The translation keys already exist; route the
                             labels through __() so the form respects the active locale. --}}
                        <select name="marital_status" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
                            <option value="">—</option>
                            @foreach(['single' => __('employees.single'), 'married' => __('employees.married'), 'cohabitant' => __('employees.cohabitant'), 'widower' => __('employees.widower'), 'divorced' => __('employees.divorced')] as $k => $v)
                                <option value="{{ $k }}" {{ $val('marital_status') === $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.dependent_children') }}</label>
                        <input type="number" name="children" value="{{ $val('children', '0') }}" min="0"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
                    </div>
                </div>

                {{-- Right col --}}
                <div class="flex-1">
                    <p class="text-xs font-semibold text-gray-400 uppercase mb-2">{{ __('employees.citizenship') }}</p>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.nationality') }}</label>
                        <input type="text" name="nationality" value="{{ $val('nationality') }}"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
                    </div>
                    @foreach([
                        [__('employees.identification_no'), 'identification_id', 'text'],
                        [__('employees.ssn_no'),            'ssnid',             'text'],
                        [__('employees.passport_no'),       'passport_id',       'text'],
                    ] as [$label, $name, $type])
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</label>
                        <input type="{{ $type }}" name="{{ $name }}" value="{{ $val($name) }}"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
                    </div>
                    @endforeach
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.gender') }}</label>
                        <select name="gender" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
                            <option value="">—</option>
                            @foreach(['male' => __('employees.male'), 'female' => __('employees.female'), 'other' => __('employees.other_gender')] as $k => $v)
                                <option value="{{ $k }}" {{ $val('gender') === $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    @foreach([
                        [__('employees.date_of_birth'),    'birthday',        'date'],
                        [__('employees.place_of_birth'),   'place_of_birth',  'text'],
                        [__('employees.country_of_birth'), 'country_of_birth','text'],
                    ] as [$label, $name, $type])
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</label>
                        <input type="{{ $type }}" name="{{ $name }}" value="{{ $val($name) }}"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
                    </div>
                    @endforeach

                    <p class="text-xs font-semibold text-gray-400 uppercase mt-4 mb-2">{{ __('employees.education') }}</p>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.certificate_level') }}</label>
                        <select name="certificate_level" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
                            <option value="">—</option>
                            {{-- The "other" option used `employees.other_gender` —
                                 the GENDER dropdown's translation key. It
                                 happens to read "Other" in en/ar, so the bug
                                 was invisible, but the semantic mismatch was
                                 real. Routed through cert_other now. --}}
                            @foreach(['none' => __('employees.cert_none'), 'graduate' => __('employees.cert_graduate'), 'bachelor' => __('employees.cert_bachelor'), 'master' => __('employees.cert_master'), 'doctor' => __('employees.cert_doctor'), 'other' => __('employees.cert_other')] as $k => $v)
                                <option value="{{ $k }}" {{ $val('certificate_level') === $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    @foreach([
                        [__('employees.field_of_study'), 'study_field',  'text'],
                        [__('employees.school'),         'study_school', 'text'],
                    ] as [$label, $name, $type])
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</label>
                        <input type="{{ $type }}" name="{{ $name }}" value="{{ $val($name) }}"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
                    </div>
                    @endforeach

                    <p class="text-xs font-semibold text-gray-400 uppercase mt-4 mb-2">{{ __('employees.work_permit') }}</p>
                    @foreach([
                        [__('employees.visa_no'),           'visa_no',           'text'],
                        [__('employees.work_permit_no'),    'work_permit_no',    'text'],
                        [__('employees.visa_expiration'),   'visa_expire',       'date'],
                        [__('employees.permit_expiration'), 'work_permit_expiration_date', 'date'],
                    ] as [$label, $name, $type])
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</label>
                        <input type="{{ $type }}" name="{{ $name }}" value="{{ $val($name) }}"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- HR Settings --}}
        <div x-show="tab === 'hr'" style="display:none" class="pt-4">
            <div class="flex gap-8">
                <div class="flex-1">
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.employee_code') }}</label>
                        <input type="text" name="employee_code" value="{{ $val('employee_code') }}"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
                    </div>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        {{-- Employment status was rendered with hardcoded English
                             labels — Arabic users saw "Draft / Active / Probation"
                             on every employee form. Now routed through __(). --}}
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.employment_status') }}</label>
                        <select name="employment_status" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
                            @foreach(['draft' => __('employees.emp_status_draft'), 'active' => __('employees.emp_status_active'), 'probation' => __('employees.emp_status_probation'), 'suspended' => __('employees.emp_status_suspended'), 'resigned' => __('employees.emp_status_resigned'), 'terminated' => __('employees.emp_status_terminated')] as $k => $v)
                                <option value="{{ $k }}" {{ $val('employment_status', 'active') === $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    @foreach([
                        [__('employees.hire_date'),       'hire_date',           'date'],
                        [__('employees.first_contract'),  'first_contract_date', 'date'],
                        [__('employees.end_date'),        'end_date',            'date'],
                        [__('employees.probation_start'), 'probation_start_date','date'],
                        [__('employees.probation_end'),   'probation_end_date',  'date'],
                        [__('employees.departure_date'),  'departure_date',      'date'],
                    ] as [$label, $name, $type])
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</label>
                        <input type="{{ $type }}" name="{{ $name }}" value="{{ $val($name) }}"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
                    </div>
                    @endforeach
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.departure_reason') }}</label>
                        <x-relation-dropdown table="hr_departure_reasons" field="name" name="departure_reason_id" relation="many2one"
                            :selected="old('departure_reason_id', $employee?->departure_reason_id)" class="flex-1" compact />
                    </div>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.wage') }}</label>
                        <input type="number" name="wage" step="0.01" value="{{ $val('wage') }}"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="0.00">
                    </div>
                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.payment_method') }}</label>
                        <select name="payment_method" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
                            <option value="">—</option>
                            {{-- Options were hardcoded English; the keys exist in employees.php for both locales. --}}
                            @foreach(['cash' => __('employees.cash'), 'bank_transfer' => __('employees.bank_transfer'), 'cheque' => __('employees.cheque')] as $k => $v)
                                <option value="{{ $k }}" {{ $val('payment_method') === $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-start gap-4 py-1.5 border-b border-gray-100 mt-2">
                        <label class="w-44 shrink-0 text-sm text-gray-500 pt-0.5">{{ __('employees.departure_notes') }}</label>
                        <textarea name="departure_description" rows="2" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0 resize-y" placeholder="-">{{ $val('departure_description') }}</textarea>
                    </div>
                    <div class="flex items-start gap-4 py-1.5 border-b border-gray-100">
                        <label class="w-44 shrink-0 text-sm text-gray-500 pt-0.5">{{ __('common.notes') }}</label>
                        <textarea name="notes" rows="4" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0 resize-y" placeholder="{{ __('common.internal_notes') }}">{{ $val('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Skills --}}
        @php
            $skillTypesJson = isset($skillTypes) ? $skillTypes->map(fn($t) => [
                'id'     => $t->id,
                'name'   => $t->name,
                'skills' => $t->skills->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->values(),
                'levels' => $t->levels->map(fn($l) => ['id' => $l->id, 'name' => $l->name, 'progress' => $l->level_progress])->values(),
            ])->values() : collect();
            $existingSkills = $employee?->skills->map(fn($s) => [
                'type_id'  => (string) $s->skill_type_id,
                'skill_id' => (string) $s->skill_id,
                'level_id' => (string) ($s->skill_level_id ?? ''),
            ])->values() ?? collect();
        @endphp
        <div x-show="tab === 'skills'" style="display:none" class="pt-4 pb-2"
             x-data="{
                 types: @js($skillTypesJson),
                 rows: @js($existingSkills),
                 typeSkills(tid) { const t = this.types.find(t => t.id == tid); return t ? t.skills : []; },
                 typeLevels(tid) { const t = this.types.find(t => t.id == tid); return t ? t.levels : []; },
                 addRow() { this.rows.push({ type_id: '', skill_id: '', level_id: '' }); },
                 removeRow(i) { this.rows.splice(i, 1); },
                 onTypeChange(row) { row.skill_id = ''; row.level_id = ''; }
             }">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.skills_tab') }}</p>
                <button type="button" @click="addRow()"
                        class="inline-flex items-center gap-1 text-xs text-purple-700 border border-purple-200 rounded px-2.5 py-1 hover:bg-purple-50">
                    {{ __('employees.add_skill') }}
                </button>
            </div>

            <div x-show="rows.length === 0" class="py-8 text-center text-sm text-gray-400">
                {{ __('employees.no_skills') }}
            </div>

            <template x-for="(row, i) in rows" :key="i">
                <div class="grid gap-3 mb-2 py-2 border-b border-gray-100 items-center" style="grid-template-columns: 1fr 1fr 1fr auto;">
                    <select :name="`skills[${i}][skill_type_id]`" x-model="row.type_id" @change="onTypeChange(row)"
                            class="text-sm text-gray-800 bg-white border border-gray-200 rounded px-2 py-1 focus:outline-none focus:border-purple-400">
                        <option value="">{{ __('employees.skill_type_ph') }}</option>
                        <template x-for="t in types" :key="t.id">
                            <option :value="String(t.id)" x-text="t.name" :selected="row.type_id == t.id"></option>
                        </template>
                    </select>
                    <select :name="`skills[${i}][skill_id]`" x-model="row.skill_id"
                            class="text-sm text-gray-800 bg-white border border-gray-200 rounded px-2 py-1 focus:outline-none focus:border-purple-400">
                        <option value="">{{ __('employees.skill_ph') }}</option>
                        <template x-for="s in typeSkills(row.type_id)" :key="s.id">
                            <option :value="String(s.id)" x-text="s.name" :selected="row.skill_id == s.id"></option>
                        </template>
                    </select>
                    <select :name="`skills[${i}][skill_level_id]`" x-model="row.level_id"
                            class="text-sm text-gray-800 bg-white border border-gray-200 rounded px-2 py-1 focus:outline-none focus:border-purple-400">
                        <option value="">{{ __('employees.skill_level_ph') }}</option>
                        <template x-for="l in typeLevels(row.type_id)" :key="l.id">
                            <option :value="String(l.id)" x-text="l.name + ' (' + l.progress + '%)'"></option>
                        </template>
                    </select>
                    <button type="button" @click="removeRow(i)"
                            class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>
