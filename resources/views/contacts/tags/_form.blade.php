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

<div class="p-6" x-data="{ color: @js(old('color', $tag?->color ?? '#8B5CF6')) }">
    <div class="mb-6">
        <input type="text" name="name" value="{{ old('name', $tag?->name) }}" required placeholder="{{ __('contacts.tag_name') }}"
               class="w-full text-3xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent {{ $errors->has('name') ? 'border-red-400' : 'border-gray-200' }}">
    </div>

    <div class="max-w-2xl">
        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
            <label class="w-32 shrink-0 text-sm text-gray-500">{{ __('contacts.tag_color') }}</label>
            <div class="flex items-center gap-3 flex-1">
                <input type="color" x-model="color" class="w-10 h-8 p-0 border border-gray-200 rounded bg-white cursor-pointer">
                <input type="text" name="color" x-model="color" required pattern="^#[0-9A-Fa-f]{6}$"
                       class="w-28 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5 uppercase">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-white"
                      :style="`background-color: ${color}`">
                    {{ __('common.preview') }}
                </span>
            </div>
        </div>
    </div>
</div>
