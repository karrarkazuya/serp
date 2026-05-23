@php $old = fn($f, $d = '') => old($f, $user?->{$f} ?? $d); @endphp

@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-600">
    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('settings.user_info') }}</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('profile.full_name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ $old('name') }}" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 {{ $errors->has('name') ? 'border-red-400' : '' }}">
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.email') }} <span class="text-red-500">*</span></label>
            <input type="email" name="email" value="{{ $old('email') }}" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 {{ $errors->has('email') ? 'border-red-400' : '' }}">
            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.phone') }}</label>
            <input type="text" name="phone" value="{{ $old('phone') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.job_position') }}</label>
            <input type="text" name="job_position" value="{{ $old('job_position') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
        </div>
        <div class="flex items-center gap-2">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" id="active" name="active" value="1"
                   {{ old('active', $user?->active ? '1' : '0') == '1' ? 'checked' : '' }}
                   class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
            <label for="active" class="text-sm text-gray-700">{{ __('settings.user_active') }}</label>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-1">{{ __('settings.password') }}</h2>
    @if($user)
        <p class="text-xs text-gray-500 mb-4">{{ __('settings.password_hint') }}</p>
    @else
        <p class="text-xs text-gray-500 mb-4">{{ __('settings.password_set_hint') }}</p>
    @endif
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.password') }} @if(!$user)<span class="text-red-500">*</span>@endif</label>
            <input type="password" name="password" {{ !$user ? 'required' : '' }}
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 {{ $errors->has('password') ? 'border-red-400' : '' }}">
            @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.confirm_password') }}</label>
            <input type="password" name="password_confirmation"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('settings.roles') }}</h2>
    <x-relation-dropdown
        table="roles"
        field="name"
        name="roles"
        relation="many2many"
        :selected="old('roles', $user?->roles->pluck('id')->toArray() ?? [])"
    />
</div>
