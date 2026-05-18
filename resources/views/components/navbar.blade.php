<nav class="h-[52px] w-full max-w-full bg-[#714B67] flex items-center px-0 z-50 relative shadow-sm">

    {{-- Logo / App name --}}
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 sm:gap-3 h-full px-3 sm:px-4 hover:bg-[#5c3d55] transition-colors shrink-0">
        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M3 3h4v4H3V3zm7 0h4v4h-4V3zm7 0h4v4h-4V3zM3 10h4v4H3v-4zm7 0h4v4h-4v-4zm7 0h4v4h-4v-4zM3 17h4v4H3v-4zm7 0h4v4h-4v-4zm7 0h4v4h-4v-4z"/>
        </svg>
        <span class="text-white font-bold text-lg tracking-wide">S-ERP</span>
    </a>

    @if(request()->routeIs('contacts.*'))
    <div class="flex min-w-0 flex-1 items-center h-full">
        <a href="{{ route('contacts.index') }}"
           class="flex items-center h-full px-3 sm:px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold shrink-0
                  {{ request()->routeIs('contacts.index', 'contacts.show', 'contacts.create', 'contacts.edit') ? 'bg-[#5c3d55]' : '' }}">
            Contacts
        </a>

        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button"
                    @click="open = !open"
                    class="hidden sm:flex items-center h-full px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold
                           {{ request()->routeIs('contacts.tags.*') ? 'bg-[#5c3d55]' : '' }}">
                Configuration
            </button>
            <button type="button"
                    @click="open = !open"
                    class="sm:hidden flex items-center justify-center gap-1 h-full px-2.5 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors
                           {{ request()->routeIs('contacts.tags.*') ? 'bg-[#5c3d55]' : '' }}"
                    aria-label="Configuration">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M11.49 2.17c-.38-1.56-2.6-1.56-2.98 0a1.53 1.53 0 01-2.29.95c-1.37-.84-2.94.73-2.1 2.1.54.89.06 2.05-.95 2.29-1.56.38-1.56 2.6 0 2.98 1.01.24 1.49 1.4.95 2.29-.84 1.37.73 2.94 2.1 2.1.89-.54 2.05-.06 2.29.95.38 1.56 2.6 1.56 2.98 0 .24-1.01 1.4-1.49 2.29-.95 1.37.84 2.94-.73 2.1-2.1-.54-.89-.06-2.05.95-2.29 1.56-.38 1.56-2.6 0-2.98a1.53 1.53 0 01-.95-2.29c.84-1.37-.73-2.94-2.1-2.1a1.53 1.53 0 01-2.29-.95zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                </svg>
                <svg class="w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute left-0 top-full w-56 max-w-[calc(100vw-1rem)] bg-white rounded-b-lg shadow-xl border border-gray-200 z-50 py-1"
                 style="display:none">
                <a href="{{ route('contacts.tags.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('contacts.tags.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    Contact Tags
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- Right side --}}
    <div class="ml-auto flex shrink-0 items-center h-full pr-1 sm:pr-2 gap-1">

        {{-- ===== Company Switcher ===== --}}
        @if(isset($allowedCompanies) && $allowedCompanies->isNotEmpty())
        <div x-data="{
                open: false,
                selected: {{ json_encode($activeCompanyIds ?? []) }},
                toggle(id) {
                    const idx = this.selected.indexOf(id);
                    if (idx >= 0) {
                        if (this.selected.length > 1) this.selected.splice(idx, 1);
                    } else {
                        this.selected.push(id);
                    }
                },
                isSelected(id) { return this.selected.includes(id); }
             }"
             class="relative h-full flex items-center"
             @click.outside="open = false">

            {{-- Trigger button --}}
            <button @click="open = !open"
                    class="flex items-center gap-2 h-full px-2 sm:px-3 text-white/80 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm max-w-[12rem]">
                {{-- Building icon --}}
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span class="hidden md:block truncate font-medium">{{ $companyLabel ?? 'No Company' }}</span>
                <svg class="w-3.5 h-3.5 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            {{-- Dropdown panel --}}
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute right-0 top-full mt-0.5 w-[min(16rem,calc(100vw-1rem))] bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-hidden"
                 style="display: none;">

                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Switch Company</p>
                    <p class="text-xs text-gray-400 mt-0.5">Select one or more companies</p>
                </div>

                <div class="py-1 max-h-60 overflow-y-auto">
                    @foreach($allowedCompanies as $company)
                    <div @click="toggle({{ $company->id }})"
                         class="flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-purple-50 transition-colors select-none">

                        {{-- Checkbox --}}
                        <div class="w-4 h-4 rounded border-2 transition-colors flex items-center justify-center shrink-0"
                             :class="isSelected({{ $company->id }}) ? 'bg-purple-600 border-purple-600' : 'border-gray-300'">
                            <svg x-show="isSelected({{ $company->id }})"
                                 class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>

                        {{-- Company info --}}
                        <div class="flex items-center gap-2.5 flex-1 min-w-0">
                            <div class="w-7 h-7 rounded-lg bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">
                                {{ strtoupper(substr($company->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $company->name }}</p>
                                @if($company->city)
                                    <p class="text-xs text-gray-400 truncate">{{ $company->city }}</p>
                                @endif
                            </div>
                        </div>

                        {{-- Selected dot --}}
                        <div class="w-1.5 h-1.5 rounded-full shrink-0 transition-colors"
                             :class="isSelected({{ $company->id }}) ? 'bg-purple-500' : 'bg-gray-200'"></div>
                    </div>
                    @endforeach
                </div>

                <div class="px-4 py-3 border-t border-gray-100 bg-gray-50 flex gap-2">
                    <form method="POST" action="{{ route('company.switch') }}" class="flex-1" id="company-switch-form">
                        @csrf
                        <template x-for="id in selected" :key="id">
                            <input type="hidden" name="companies[]" :value="id">
                        </template>
                        <button type="submit"
                                class="w-full px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-lg transition-colors">
                            Apply
                        </button>
                    </form>
                    <button @click="open = false"
                            class="px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- ===== User menu ===== --}}
        <div x-data="{ open: false }" class="relative h-full flex items-center" @click.outside="open = false">
            <button @click="open = !open"
                    class="flex items-center gap-2 h-full px-2 sm:px-3 text-white/80 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm">
                <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center text-xs font-bold text-white">
                    {{ auth()->user()->initials }}
                </div>
                <span class="font-medium hidden sm:block">{{ auth()->user()->name }}</span>
                <svg class="w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute right-0 top-full mt-0.5 w-[min(13rem,calc(100vw-1rem))] bg-white rounded-lg shadow-xl border border-gray-100 z-50 py-1"
                 style="display: none;">

                <div class="px-4 py-2.5 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-800">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
