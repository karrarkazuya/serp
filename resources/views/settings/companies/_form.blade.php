{{-- Shared form partial for company create/edit --}}
@php $old = fn($f, $d = '') => old($f, $company?->{$f} ?? $d); @endphp

{{-- Validation errors --}}
@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
    <p class="text-sm font-medium text-red-700 mb-1">Please fix the following errors:</p>
    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- Basic info --}}
<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Company Information</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Company Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ $old('name') }}" required
                   class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500
                          {{ $errors->has('name') ? 'border-red-400' : 'border-gray-300' }}">
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Tax ID / VAT</label>
            <input type="text" name="tax_id" value="{{ $old('tax_id') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Currency</label>
            <input type="text" name="currency" value="{{ $old('currency', 'USD') }}" placeholder="USD"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
    </div>
</div>

{{-- Contact details --}}
<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Contact Details</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Email</label>
            <input type="email" name="email" value="{{ $old('email') }}"
                   class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500
                          {{ $errors->has('email') ? 'border-red-400' : 'border-gray-300' }}">
            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Phone</label>
            <input type="text" name="phone" value="{{ $old('phone') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Mobile</label>
            <input type="text" name="mobile" value="{{ $old('mobile') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Website</label>
            <input type="url" name="website" value="{{ $old('website') }}" placeholder="https://"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            @error('website')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

{{-- Address --}}
<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Address</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Street</label>
            <input type="text" name="street" value="{{ $old('street') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">City</label>
            <input type="text" name="city" value="{{ $old('city') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">State / Province</label>
            <input type="text" name="state" value="{{ $old('state') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">ZIP / Postal Code</label>
            <input type="text" name="zip" value="{{ $old('zip') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Country</label>
            <input type="text" name="country" value="{{ $old('country') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>
    </div>
</div>

{{-- Notes --}}
<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Internal Notes</h2>
    <textarea name="notes" rows="4" placeholder="Add internal notes about this company..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 resize-y">{{ $old('notes') }}</textarea>
</div>
