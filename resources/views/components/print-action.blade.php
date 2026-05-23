<div x-data="{ openPrint(url) { window.open(url, '_blank', 'noopener,noreferrer'); } }" class="inline-flex">
    <button
        type="button"
        @click="openPrint(@js($href))"
        class="px-3 py-1.5 text-sm font-medium rounded shadow-sm {{ $preview ? 'text-gray-700 bg-gray-200 hover:bg-gray-300' : 'text-white bg-[#71639e] hover:bg-[#5c527f]' }}">
        {{ $label }}
    </button>
</div>
