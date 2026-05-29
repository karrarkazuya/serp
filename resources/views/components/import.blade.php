{{--
  Generic import dialog. No hard-coded model references.

  Auto-rendered by <x-list> when :model is set and the actor's Gate
  policy allows `import`. The :model class is matched against
  config/importable.php; the matching key drives every per-module
  variable (modelKey, sample URLs, target form action).

  Submitting POSTs the file to /import. The whole batch runs inside a
  single DB::transaction in ImportController::import — any per-row
  validation failure rolls the batch back, nothing is created, and the
  row number + error are flashed back to the same page.
--}}
<div
    x-data="{
        open: false,
        fileName: '',
        submitting: false,
        onFileChange(event) {
            const f = event.target.files[0];
            this.fileName = f ? f.name : '';
        },
        submit() {
            if (!this.fileName) return;
            this.submitting = true;
            this.$refs.importForm.submit();
        },
    }"
    @keydown.escape.window="open = false">

    {{-- Trigger button — sits in the <x-list> toolbar above the table --}}
    <button type="button"
            @click="open = true"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 shadow-sm transition-colors">
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
        <span>{{ $label !== '' ? $label : __('common.import') }}</span>
    </button>

    {{-- Modal --}}
    <div x-show="open"
         x-transition.opacity
         class="fixed inset-0 z-200 bg-black/40 flex items-start justify-center p-4 pt-16"
         style="display:none"
         @click.self="open = false">

        <form x-ref="importForm"
              method="POST"
              action="{{ $importUrl }}"
              enctype="multipart/form-data"
              class="bg-white w-full max-w-xl rounded-lg shadow-2xl border border-gray-200 flex flex-col"
              @click.stop>
            @csrf
            <input type="hidden" name="model" value="{{ $modelKey }}">
            <input type="hidden" name="redirect" :value="window.location.pathname + window.location.search">

            {{-- Header --}}
            <div class="flex items-center px-5 py-3.5 border-b border-gray-200 shrink-0">
                <h2 class="text-base font-semibold text-gray-800">{{ __('common.import_data') }}</h2>
                <button type="button" @click="open = false"
                        class="ms-auto text-gray-400 hover:text-gray-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-5 py-4 space-y-4">

                {{-- Step 1 — download template --}}
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        {{ __('common.import_step1') }}
                    </p>
                    <p class="text-sm text-gray-600 mb-2">
                        {{ __('common.import_step1_desc') }}
                    </p>
                    <div class="flex items-center gap-2">
                        <a href="{{ $sampleXlsxUrl }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-[#714B67] bg-purple-50 border border-purple-100 rounded hover:bg-purple-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v3a2 2 0 002 2h14a2 2 0 002-2v-3"/>
                            </svg>
                            XLSX
                        </a>
                        <a href="{{ $sampleCsvUrl }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded hover:bg-gray-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v3a2 2 0 002 2h14a2 2 0 002-2v-3"/>
                            </svg>
                            CSV
                        </a>
                    </div>
                </div>

                {{-- Step 2 — upload --}}
                <div class="pt-3 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        {{ __('common.import_step2') }}
                    </p>
                    <p class="text-sm text-gray-600 mb-2">
                        {{ __('common.import_step2_desc') }}
                    </p>

                    <label class="flex items-center gap-3 px-3 py-2 bg-gray-50 border border-dashed border-gray-300 rounded cursor-pointer hover:border-purple-300 hover:bg-purple-50/20 transition-colors">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.9 5 5 0 019.9-1A5.5 5.5 0 0118.5 16H7z"/>
                        </svg>
                        {{-- Two separate spans avoid embedding the translation inside an Alpine attribute,
                             which would break the second any translation contains a single quote. --}}
                        <span class="text-sm text-gray-600 flex-1 truncate" x-show="!fileName">{{ __('common.import_choose_file') }}</span>
                        <span class="text-sm text-gray-600 flex-1 truncate" x-show="fileName" x-text="fileName" style="display:none"></span>
                        <input type="file"
                               name="file"
                               class="hidden"
                               accept=".csv,.xlsx,.xls"
                               @change="onFileChange($event)">
                    </label>

                    <p class="mt-2 text-xs text-gray-400">{{ __('common.import_max_rows', ['n' => \App\Services\ImportService::MAX_ROWS]) }}</p>
                </div>

                <div class="bg-amber-50 -mx-5 px-5 py-2 text-xs text-amber-800">
                    <strong>{{ __('common.import_atomic_title') }}:</strong>
                    {{ __('common.import_atomic_desc') }}
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center gap-2 px-5 py-3 border-t border-gray-200 shrink-0">
                <button type="button"
                        @click="submit()"
                        :disabled="!fileName || submitting"
                        class="px-4 py-2 bg-[#714B67] text-white text-sm font-semibold rounded hover:bg-[#5c3d55] disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <span x-show="!submitting">{{ __('common.import') }}</span>
                    <span x-show="submitting" style="display:none">{{ __('common.importing') }}</span>
                </button>
                <button type="button"
                        @click="open = false"
                        class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded hover:bg-gray-200 transition-colors">
                    {{ __('common.close') }}
                </button>
            </div>
        </form>
    </div>
</div>
