@php $old = fn($f, $d = '') => old($f, $role?->{$f} ?? $d); @endphp

@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-600">
    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('settings.role_details') }}</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.role_name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ $old('name') }}" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 {{ $errors->has('name') ? 'border-red-400' : '' }}"
                   placeholder="e.g. Sales Manager">
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('settings.role_key') }} <span class="text-red-500">*</span></label>
            <input type="text" name="key" value="{{ $old('key') }}" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 font-mono {{ $errors->has('key') ? 'border-red-400' : '' }}"
                   placeholder="e.g. sales_manager"
                   {{ $role && $role->key === 'admin' ? 'readonly' : '' }}>
            <p class="mt-1 text-xs text-gray-400">{{ __('settings.key_hint') }}</p>
            @error('key')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.description') }}</label>
            <input type="text" name="description" value="{{ $old('description') }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
        </div>
        <div class="flex items-center gap-2">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" id="active" name="active" value="1"
                   {{ $old('active', $role ? ($role->active ? '1' : '0') : '1') === '1' ? 'checked' : '' }}
                   class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
            <label for="active" class="text-sm text-gray-700">{{ __('settings.user_active') }}</label>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-700">{{ __('settings.role_permissions') }}</h2>
        <div class="flex gap-2">
            <button type="button" onclick="document.querySelectorAll('input[name=\'permissions[]\']').forEach(c => c.checked = true)"
                    class="text-xs text-purple-600 hover:text-purple-700 font-medium">{{ __('settings.select_all') }}</button>
            <span class="text-gray-300">|</span>
            <button type="button" onclick="document.querySelectorAll('input[name=\'permissions[]\']').forEach(c => c.checked = false)"
                    class="text-xs text-gray-500 hover:text-gray-700 font-medium">{{ __('settings.clear_all') }}</button>
        </div>
    </div>

    @foreach($permissions as $module => $perms)
    <div class="mb-5">
        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-2">
            <span class="flex-1">{{ ucfirst($module) }}</span>
            <span class="text-gray-300 font-normal lowercase">{{ $perms->count() }} {{ __('settings.permissions') }}</span>
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            @foreach($perms as $perm)
            <label class="flex items-center gap-2 p-2.5 border rounded-lg cursor-pointer hover:bg-purple-50 hover:border-purple-200 transition-colors">
                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                       {{ in_array($perm->id, old('permissions', $assignedIds)) ? 'checked' : '' }}
                       class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                <div>
                    <div class="text-xs font-medium text-gray-700">{{ $perm->name }}</div>
                    <code class="text-xs text-gray-400 font-mono">{{ $perm->key }}</code>
                </div>
            </label>
            @endforeach
        </div>
    </div>
    @endforeach
</div>
