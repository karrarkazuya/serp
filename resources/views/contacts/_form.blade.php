@php
    $val = fn($field, $default = '') => old($field, $contact?->{$field} ?? $default);
    $selectedTags = old('tags', $contact ? $contact->tags->pluck('id')->toArray() : []);
    $selectedRelatedContacts = old('related_contacts', $relatedContactIds ?? []);
    $selectedPhones = old('phones', $contact ? $contact->phones->pluck('phone')->toArray() : []);
    if (empty($selectedPhones)) $selectedPhones = [''];
@endphp

@if($errors->any())
<div class="px-6 pt-4 pb-0">
    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
        <p class="text-sm font-medium text-red-700 mb-1">{{ __('contacts.fix_errors') }}</p>
        <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

<div class="p-6" x-data="{ type: '{{ $val('contact_type', 'individual') }}', avatarPreview: '{{ $contact?->avatar_url ?? '' }}', phones: {{ Js::from($selectedPhones) }} }">
    <div class="flex items-center gap-4 mb-3 text-sm">
        <label class="flex items-center gap-1.5 cursor-pointer">
            <input type="radio" name="contact_type" value="individual" x-model="type" class="text-purple-600">
            <span>{{ __('contacts.individual') }}</span>
        </label>
        <label class="flex items-center gap-1.5 cursor-pointer">
            <input type="radio" name="contact_type" value="company" x-model="type" class="text-purple-600">
            <span>{{ __('contacts.company_type') }}</span>
        </label>
    </div>

    <div class="mb-6">
        <input type="text" name="name" value="{{ $val('name') }}" required placeholder="{{ __('contacts.name') }}"
               class="w-full text-3xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent {{ $errors->has('name') ? 'border-red-400' : 'border-gray-200' }}">
    </div>

    <div class="flex gap-8">
        <div class="flex-1">
            @foreach([
                [__('contacts.job_position'), 'job_position'],
                [__('contacts.company'),      'company_name'],
                [__('contacts.tax_id'),       'tax_id'],
            ] as [$label, $name])
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-32 shrink-0 text-sm text-gray-500">{{ $label }}</label>
                <input type="text" name="{{ $name }}" value="{{ $val($name) }}" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="-">
            </div>
            @endforeach

            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-32 shrink-0 text-sm text-gray-500">{{ __('contacts.company') }}</label>
                <x-relation-dropdown
                    table="companies"
                    field="name"
                    name="company_id"
                    relation="many2one"
                    :selected="old('company_id', $contact?->company_id ?? ($defaultCompanyId ?? null))"
                    class="flex-1"
                />
            </div>

            <x-relation-dropdown
                table="tags"
                field="name"
                name="tags"
                :label="__('contacts.tags')"
                :selected="$selectedTags"
                relation="many2many"
            />
        </div>

        <div class="flex-1">
            {{-- Dynamic multi-phone section --}}
            <template x-for="(phone, i) in phones" :key="'p'+i">
                <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                    <label class="w-24 shrink-0 text-sm text-gray-500" x-show="i === 0">{{ __('contacts.phone') }}</label>
                    <span class="w-24 shrink-0" x-show="i > 0"></span>
                    <input type="tel"
                           :name="'phones['+i+']'"
                           :value="phone"
                           @input="phones[i] = $event.target.value"
                           class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5"
                           placeholder="-">
                    <button type="button" @click="phones.splice(i, 1)"
                            class="shrink-0 p-1 text-gray-300 hover:text-red-500 rounded transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
            <div class="flex items-center gap-4 py-1.5">
                <span class="w-24 shrink-0"></span>
                <button type="button" @click="phones.push('')"
                        class="text-xs font-medium text-purple-600 hover:text-purple-700">
                    + {{ __('contacts.add_phone') }}
                </button>
            </div>

            @foreach([
                [__('contacts.email'),   'email',   'email'],
                [__('contacts.website'), 'website', 'url'],
                [__('contacts.street'),  'street',  'text'],
                [__('contacts.city'),    'city',    'text'],
                [__('contacts.state'),   'state',   'text'],
                [__('contacts.zip'),     'zip',     'text'],
                [__('contacts.country'), 'country', 'text'],
            ] as [$label, $name, $type])
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <label class="w-24 shrink-0 text-sm text-gray-500">{{ $label }}</label>
                <input type="{{ $type }}" name="{{ $name }}" value="{{ $val($name) }}" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="-">
            </div>
            @endforeach
        </div>

        <div class="shrink-0 w-36">
            <label class="block cursor-pointer">
                <div class="w-36 h-36 rounded-xl overflow-hidden border border-gray-200 shadow-sm">
                    <img x-show="avatarPreview" :src="avatarPreview" class="w-full h-full object-cover" style="display:none">
                    <div x-show="!avatarPreview" class="w-full h-full flex items-center justify-center text-4xl font-bold"
                         :class="type === 'company' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'">
                        {{ $contact ? strtoupper(substr($contact->name, 0, 2)) : '?' }}
                    </div>
                </div>
                <input type="file" name="avatar" accept="image/*" class="hidden" @change="avatarPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : avatarPreview">
                <p class="mt-1.5 text-center text-xs text-gray-400">{{ __('contacts.click_upload') }}</p>
            </label>
        </div>
    </div>

    <div class="mt-8 border-t border-gray-200" x-data="{ page: 'related' }">
        <div class="flex items-end gap-1 pt-3 border-b border-gray-200">
            <button type="button"
                    @click="page = 'related'"
                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                    :class="page === 'related' ? 'text-gray-900 border-gray-300 -mb-px pb-[9px]' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                {{ __('contacts.related_contacts') }}
            </button>
            <button type="button"
                    @click="page = 'notes'"
                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                    :class="page === 'notes' ? 'text-gray-900 border-gray-300 -mb-px pb-[9px]' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                {{ __('contacts.notes') }}
            </button>
        </div>

        <div class="min-h-48">
            <div x-show="page === 'related'" style="display:none" class="pt-4">
                <x-relation-dropdown
                    table="contacts"
                    field="name"
                    name="related_contacts"
                    :label="__('contacts.related_contacts')"
                    :selected="$selectedRelatedContacts"
                    relation="one2many"
                    :exclude="$contact?->id"
                />
            </div>

            <div x-show="page === 'notes'" style="display:none">
                <textarea name="notes"
                          rows="7"
                          placeholder="{{ __('contacts.notes') }}..."
                          class="w-full min-h-44 px-4 py-4 border-0 text-sm focus:outline-none focus:ring-0 resize-y text-gray-800 placeholder-gray-400">{{ $val('notes') }}</textarea>
            </div>
        </div>
    </div>
</div>
