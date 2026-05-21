<nav class="h-[52px] w-full max-w-full bg-[#714B67] flex items-center px-0 z-50 relative shadow-sm">

    {{-- Logo / App name --}}
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 sm:gap-3 h-full px-3 sm:px-4 hover:bg-[#5c3d55] transition-colors shrink-0">
        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M3 3h4v4H3V3zm7 0h4v4h-4V3zm7 0h4v4h-4V3zM3 10h4v4H3v-4zm7 0h4v4h-4v-4zm7 0h4v4h-4v-4zM3 17h4v4H3v-4zm7 0h4v4h-4v-4zm7 0h4v4h-4v-4z"/>
        </svg>
        <span class="text-white font-bold text-lg tracking-wide">S-ERP</span>
    </a>

    @if(request()->routeIs('workflow.*'))
    <div class="flex min-w-0 flex-1 items-center h-full">
        <a href="{{ route('workflow.dashboard') }}"
           class="flex items-center h-full px-3 sm:px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold shrink-0
                  {{ request()->routeIs('workflow.dashboard') ? 'bg-[#5c3d55]' : '' }}">
            {{ __('nav.dashboard') }}
        </a>
        <a href="{{ route('workflow.tickets.index') }}"
           class="flex items-center h-full px-3 sm:px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold shrink-0
                  {{ request()->routeIs('workflow.tickets.*') ? 'bg-[#5c3d55]' : '' }}">
            {{ __('nav.tickets') }}
        </a>
        <a href="{{ route('workflow.procedures.index') }}"
           class="flex items-center h-full px-3 sm:px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold shrink-0
                  {{ request()->routeIs('workflow.procedures.*') ? 'bg-[#5c3d55]' : '' }}">
            {{ __('nav.procedures') }}
        </a>

        @if(auth()->user()->isAdmin())
        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button"
                    @click="open = !open"
                    class="hidden sm:flex items-center h-full px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold
                           {{ request()->routeIs('workflow.config.*') ? 'bg-[#5c3d55]' : '' }}">
                {{ __('nav.configuration') }}
                <svg class="ms-1 w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                 class="absolute start-0 top-full w-56 bg-white rounded-b-lg shadow-xl border border-gray-200 z-50 py-1"
                 style="display:none">
                <div class="px-3 py-1.5 text-[10px] font-semibold text-gray-400 uppercase">{{ __('nav.configuration') }}</div>
                @can('viewAny', \App\Models\Workflow\Group::class)
                <a href="{{ route('workflow.config.groups.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('workflow.config.groups.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.groups') }}
                </a>
                @endcan
                @can('viewAny', \App\Models\Workflow\WorkflowUser::class)
                <a href="{{ route('workflow.config.users.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('workflow.config.users.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('settings.users') }}
                </a>
                @endcan
                @if(auth()->user()->hasPermission('workflow.config.read'))
                <a href="{{ route('workflow.config.managers.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('workflow.config.managers.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.managers') }}
                </a>
                @endif
                <div class="border-t border-gray-100 my-1"></div>
                <div class="px-3 py-1.5 text-[10px] font-semibold text-gray-400 uppercase">{{ __('nav.templates') }}</div>
                @can('viewAny', \App\Models\Workflow\TicketTemplate::class)
                <a href="{{ route('workflow.config.ticket-templates.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('workflow.config.ticket-templates.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.tickets_templates') }}
                </a>
                @endcan
                @can('viewAny', \App\Models\Workflow\ProcedureTemplate::class)
                <a href="{{ route('workflow.config.procedure-templates.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('workflow.config.procedure-templates.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.procedures_templates') }}
                </a>
                @endcan
            </div>
        </div>

        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button"
                    @click="open = !open"
                    class="hidden sm:flex items-center h-full px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold
                           {{ request()->routeIs('workflow.reports.*') ? 'bg-[#5c3d55]' : '' }}">
                {{ __('nav.reports') }}
                <svg class="ms-1 w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                 class="absolute start-0 top-full w-64 bg-white rounded-b-lg shadow-xl border border-gray-200 z-50 py-1"
                 style="display:none">
                <a href="{{ route('workflow.reports.show', 'activity') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->route('report') === 'activity' ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.activity_report') }}
                </a>
                <a href="{{ route('workflow.reports.show', 'procedure-performance') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->route('report') === 'procedure-performance' ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.procedure_performance') }}
                </a>
                <a href="{{ route('workflow.reports.show', 'ticket-performance') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->route('report') === 'ticket-performance' ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.ticket_performance') }}
                </a>
                <a href="{{ route('workflow.reports.show', 'task-performance') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->route('report') === 'task-performance' ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.task_performance') }}
                </a>
            </div>
        </div>
        @endif

        @if(auth()->user()->hasPermission('workflow.config.read'))
        <a href="{{ route('workflow.settings.index') }}"
           class="hidden sm:flex items-center h-full px-3 sm:px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold shrink-0
                  {{ request()->routeIs('workflow.settings.*') ? 'bg-[#5c3d55]' : '' }}">
            {{ __('settings.title') }}
        </a>
        @endif
    </div>
    @endif

    @if(request()->routeIs('chat.*'))
    <div class="flex min-w-0 flex-1 items-center h-full">
        <a href="{{ route('chat.index') }}"
           class="flex items-center h-full px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold shrink-0
                  {{ request()->routeIs('chat.*') ? 'bg-[#5c3d55]' : '' }}">
            Chat
        </a>
    </div>
    @endif

    @if(request()->routeIs('employees.*'))
    <div class="flex min-w-0 flex-1 items-center h-full">
        @can('viewAny', \App\Models\Employees\Employee::class)
        <a href="{{ route('employees.index') }}"
           class="flex items-center h-full px-3 sm:px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold shrink-0
                  {{ request()->routeIs('employees.index', 'employees.show', 'employees.create', 'employees.edit') ? 'bg-[#5c3d55]' : '' }}">
            Employees
        </a>
        @endcan

        {{-- Configuration dropdown --}}
        @can('viewAny', \App\Models\Employees\Employee::class)
        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button"
                    @click="open = !open"
                    class="hidden sm:flex items-center h-full px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold
                           {{ request()->routeIs('employees.departments.*', 'employees.jobs.*', 'employees.work-locations.*', 'employees.schedules.*', 'employees.categories.*', 'employees.departure-reasons.*', 'employees.skill-types.*', 'employees.resume-line-types.*', 'employees.employment-types.*', 'employees.badges.*', 'employees.challenges.*', 'employees.goals.*') ? 'bg-[#5c3d55]' : '' }}">
                Configuration
                <svg class="ms-1 w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                 class="absolute start-0 top-full w-56 bg-white rounded-b-lg shadow-xl border border-gray-200 z-50 py-1"
                 style="display:none">
                <div class="px-3 py-1.5 text-[10px] font-semibold text-gray-400 uppercase">Organization</div>
                <a href="{{ route('employees.departments.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.departments.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    Departments
                </a>
                <a href="{{ route('employees.jobs.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.jobs.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    Job Positions
                </a>
                <div class="border-t border-gray-100 my-1"></div>
                <div class="px-3 py-1.5 text-[10px] font-semibold text-gray-400 uppercase">Settings</div>
                <a href="{{ route('employees.work-locations.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.work-locations.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    Work Locations
                </a>
                <a href="{{ route('employees.schedules.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.schedules.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    Working Schedules
                </a>
                <a href="{{ route('employees.categories.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.categories.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    Tags
                </a>
                <a href="{{ route('employees.departure-reasons.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.departure-reasons.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    Departure Reasons
                </a>
                <a href="{{ route('employees.skill-types.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.skill-types.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    Skill Types
                </a>
                <a href="{{ route('employees.resume-line-types.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.resume-line-types.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    Line Types
                </a>
                <a href="{{ route('employees.employment-types.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.employment-types.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    Employment Types
                </a>
                <div class="border-t border-gray-100 my-1"></div>
                <div class="px-3 py-1.5 text-[10px] font-semibold text-gray-400 uppercase">Challenges</div>
                <a href="{{ route('employees.badges.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.badges.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    Badges
                </a>
                <a href="{{ route('employees.challenges.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.challenges.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    Challenges
                </a>
                <a href="{{ route('employees.goals.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.goals.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    Goals History
                </a>
            </div>
        </div>
        @endcan
    </div>
    @endif

    @if(request()->routeIs('contacts.*'))
    <div class="flex min-w-0 flex-1 items-center h-full">
        <a href="{{ route('contacts.index') }}"
           class="flex items-center h-full px-3 sm:px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold shrink-0
                  {{ request()->routeIs('contacts.index', 'contacts.show', 'contacts.create', 'contacts.edit') ? 'bg-[#5c3d55]' : '' }}">
            {{ __('nav.contacts') }}
        </a>

        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button"
                    @click="open = !open"
                    class="hidden sm:flex items-center h-full px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold
                           {{ request()->routeIs('contacts.tags.*') ? 'bg-[#5c3d55]' : '' }}">
                {{ __('nav.configuration') }}
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
                 class="absolute start-0 top-full w-56 max-w-[calc(100vw-1rem)] bg-white rounded-b-lg shadow-xl border border-gray-200 z-50 py-1"
                 style="display:none">
                <a href="{{ route('contacts.tags.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('contacts.tags.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.contact_tags') }}
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- Right side --}}
    <div class="ms-auto flex shrink-0 items-center h-full pe-1 sm:pe-2 gap-1">

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
                 class="absolute end-0 top-full mt-0.5 w-[min(16rem,calc(100vw-1rem))] bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-hidden"
                 style="display: none;">

                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('common.switch_company') }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ __('common.select_companies') }}</p>
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
                            <div class="w-7 h-7 rounded-lg overflow-hidden bg-[#714B67]/10 flex items-center justify-center text-xs font-bold text-[#714B67] shrink-0">
                                @if($company->logo_url)
                                    <img src="{{ $company->logo_url }}" alt="{{ $company->name }}" class="w-full h-full object-cover">
                                @else
                                    {{ strtoupper(substr($company->name, 0, 2)) }}
                                @endif
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
                            {{ __('common.apply') }}
                        </button>
                    </form>
                    <button @click="open = false"
                            class="px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        {{ __('common.cancel') }}
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- ===== Chat icon ===== --}}
        <a href="{{ route('chat.index') }}"
           class="relative flex items-center justify-center h-full px-2 sm:px-3 transition-colors
                  {{ request()->routeIs('chat.*') ? 'bg-[#5c3d55] text-white' : 'text-white/80 hover:text-white hover:bg-[#5c3d55]' }}"
           title="Chat">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
        </a>

        {{-- ===== Notification Bell ===== --}}
        <div x-data="{
                open: false,
                count: 0,
                notifications: [],
                initialized: false,
                async fetchCount() {
                    try {
                        const r = await fetch('{{ route('notifications.count') }}');
                        const d = await r.json();
                        if (this.initialized && d.count > this.count) {
                            this.ping();
                        }
                        this.count = d.count;
                        this.initialized = true;
                    } catch (e) {}
                },
                async openDropdown() {
                    this.open = !this.open;
                    if (this.open) {
                        try {
                            const r = await fetch('{{ route('notifications.recent') }}');
                            const d = await r.json();
                            this.notifications = d.notifications;
                        } catch (e) {}
                    }
                },
                async markAllSeen() {
                    try {
                        await fetch('{{ route('notifications.seen-all') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                        });
                        this.count = 0;
                        this.notifications = this.notifications.map(n => ({ ...n, seen_at: new Date().toISOString() }));
                    } catch (e) {}
                },
                async markSeen(n) {
                    if (n.seen_at) {
                        if (n.url) window.location.href = n.url;
                        return;
                    }
                    try {
                        await fetch('/notifications/' + n.id + '/seen', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                        });
                        n.seen_at = new Date().toISOString();
                        this.count = Math.max(0, this.count - 1);
                    } catch (e) {}
                    if (n.url) window.location.href = n.url;
                },
                ping() {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const o = ctx.createOscillator();
                        const g = ctx.createGain();
                        o.connect(g);
                        g.connect(ctx.destination);
                        o.frequency.value = 880;
                        g.gain.setValueAtTime(0.3, ctx.currentTime);
                        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
                        o.start(ctx.currentTime);
                        o.stop(ctx.currentTime + 0.4);
                    } catch (e) {}
                },
                init() {
                    this.fetchCount();
                    setInterval(() => this.fetchCount(), 30000);
                }
             }"
             class="relative h-full flex items-center"
             @click.outside="open = false">

            <button @click="openDropdown()"
                    class="relative flex items-center justify-center h-full px-2 sm:px-3 text-white/80 hover:text-white hover:bg-[#5c3d55] transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.003 6.003 0 00-5-5.917V5a1 1 0 10-2 0v.083A6.003 6.003 0 006 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span x-show="count > 0"
                      x-text="count > 99 ? '99+' : count"
                      class="absolute top-2 right-1 min-w-[16px] h-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-0.5 leading-none"
                      style="display:none">
                </span>
            </button>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute end-0 top-full mt-0.5 w-[min(22rem,calc(100vw-1rem))] bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-hidden"
                 style="display: none;">

                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Notifications</p>
                    <button @click="markAllSeen()" x-show="count > 0"
                            class="text-xs text-purple-600 hover:text-purple-700 font-medium"
                            style="display:none">
                        Mark all read
                    </button>
                </div>

                <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
                    <template x-if="notifications.length === 0">
                        <p class="px-4 py-8 text-sm text-gray-400 text-center">No notifications</p>
                    </template>
                    <template x-for="n in notifications" :key="n.id">
                        <div @click="markSeen(n)"
                             class="flex items-start gap-3 px-4 py-3 transition-colors"
                             :class="n.url ? 'cursor-pointer hover:bg-gray-50' : 'hover:bg-gray-50'"
                             :style="!n.seen_at ? 'background-color: #faf5ff;' : ''">
                            <div class="w-2 h-2 mt-1.5 rounded-full shrink-0 transition-colors"
                                 :class="!n.seen_at ? 'bg-purple-500' : 'bg-transparent'"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800" x-text="n.title"></p>
                                <p x-show="n.body" class="text-xs text-gray-500 mt-0.5 line-clamp-2" x-text="n.body"></p>
                                <p class="text-[10px] text-gray-400 mt-1" x-text="n.created_at"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

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
                 class="absolute end-0 top-full mt-0.5 w-[min(13rem,calc(100vw-1rem))] bg-white rounded-lg shadow-xl border border-gray-100 z-50 py-1"
                 style="display: none;">

                <div class="px-4 py-2.5 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-800">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                </div>

                <a href="{{ route('profile') }}"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors {{ request()->routeIs('profile') ? 'bg-gray-50 font-semibold' : '' }}">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    {{ __('nav.my_profile') }}
                </a>

                <div class="border-t border-gray-100"></div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        {{ __('common.sign_out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
