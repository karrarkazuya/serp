{{-- Shared form partial for company create/edit --}}
@php $old = fn($f, $d = '') => old($f, $company?->{$f} ?? $d); @endphp

@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
    <p class="text-sm font-medium text-red-700 mb-1">{{ __('contacts.fix_errors') }}</p>
    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('settings.company_info') }}</h2>

    {{-- Logo upload --}}
    <div class="mb-5" x-data="{ preview: '{{ $company?->logo_url ?? '' }}' }">
        <label class="block text-xs font-medium text-gray-600 mb-2">{{ __('settings.logo') }}</label>
        <div class="flex items-center gap-4">
            <label class="cursor-pointer group">
                <div class="w-20 h-20 rounded-xl border-2 border-dashed border-gray-200 overflow-hidden flex items-center justify-center bg-gray-50 group-hover:border-[#714B67]/40 transition-colors">
                    <img x-show="preview" :src="preview" class="w-full h-full object-cover" style="display:none">
                    <div x-show="!preview" class="flex flex-col items-center gap-1 text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-[10px]">{{ __('settings.upload') }}</span>
                    </div>
                </div>
                <input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden"
                       @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : preview">
            </label>
            <div class="text-xs text-gray-400 space-y-1">
                <p>{{ __('settings.logo_hint') }}</p>
                @if($company?->logo)
                <button type="button" class="text-red-400 hover:text-red-600 transition-colors"
                        @click="preview = ''; $el.closest('[x-data]').querySelector('input[type=file]').value = ''; $el.closest('[x-data]').querySelector('input[name=remove_logo]').value = '1'">
                    {{ __('settings.remove_logo') }}
                </button>
                <input type="hidden" name="remove_logo" value="0">
                @endif
            </div>
        </div>
        @error('logo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.company_name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ $old('name') }}" required
                   class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500
                          {{ $errors->has('name') ? 'border-red-400' : 'border-gray-300' }}">
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.tax_id') }}</label>
            <input type="text" name="tax_id" value="{{ $old('tax_id') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.currency') }}</label>
            <x-relation-dropdown
                table="currencies"
                field="code"
                name="currency"
                relation="many2one"
                :compact="true"
                :selected="$old('currency', 'USD')"
            />
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('settings.contact_details') }}</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.email') }}</label>
            <input type="email" name="email" value="{{ $old('email') }}"
                   class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500
                          {{ $errors->has('email') ? 'border-red-400' : 'border-gray-300' }}">
            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.phone') }}</label>
            <input type="text" name="phone" value="{{ $old('phone') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('contacts.mobile') }}</label>
            <input type="text" name="mobile" value="{{ $old('mobile') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('contacts.website') }}</label>
            <input type="url" name="website" value="{{ $old('website') }}" placeholder="https://"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            @error('website')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('settings.address') }}</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.street') }}</label>
            <input type="text" name="street" value="{{ $old('street') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.city') }}</label>
            <input type="text" name="city" value="{{ $old('city') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.state') }}</label>
            <input type="text" name="state" value="{{ $old('state') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.zip') }}</label>
            <input type="text" name="zip" value="{{ $old('zip') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.country') }}</label>
            <input type="text" name="country" value="{{ $old('country') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('settings.internal_notes') }}</h2>
    <textarea name="notes" rows="4" placeholder="{{ __('settings.notes_placeholder') }}"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 resize-y">{{ $old('notes') }}</textarea>
</div>
