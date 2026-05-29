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
            {{ __('nav.chat') }}
        </a>
    </div>
    @endif

    @if(request()->routeIs('employees.*'))
    <div class="flex min-w-0 flex-1 items-center h-full">
        @can('viewAny', \App\Models\Employees\Employee::class)
        <a href="{{ route('employees.index') }}"
           class="flex items-center h-full px-3 sm:px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold shrink-0
                  {{ request()->routeIs('employees.index', 'employees.show', 'employees.create', 'employees.edit') ? 'bg-[#5c3d55]' : '' }}">
            {{ __('nav.employees') }}
        </a>
        @endcan

        {{-- Allocations dropdown --}}
        @can('viewAny', \App\Models\Employees\Employee::class)
        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button"
                    @click="open = !open"
                    class="hidden sm:flex items-center h-full px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold
                           {{ request()->routeIs('employees.positions.*', 'employees.certificates.*', 'employees.bonuses.*', 'employees.appreciations.*', 'employees.sanctions.*', 'employees.rewards.*', 'employees.job-grades.*') ? 'bg-[#5c3d55]' : '' }}">
                {{ __('nav.allocations') }}
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
                <a href="{{ route('employees.positions.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.positions.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.positions') }}
                </a>
                <a href="{{ route('employees.certificates.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.certificates.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.certificates') }}
                </a>
                <a href="{{ route('employees.bonuses.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.bonuses.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('employees.bonuses_title') }}
                </a>
                <a href="{{ route('employees.appreciations.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.appreciations.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('employees.appreciations_title') }}
                </a>
                <a href="{{ route('employees.sanctions.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.sanctions.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('employees.sanctions_title') }}
                </a>
                <a href="{{ route('employees.rewards.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.rewards.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('employees.rewards_title') }}
                </a>
                <a href="{{ route('employees.job-grades.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.job-grades.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('employees.job_grades_title') }}
                </a>
            </div>
        </div>
        @endcan

        {{-- Attendance dropdown --}}
        @can('viewAny', \App\Models\Employees\Attendance::class)
        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button"
                    @click="open = !open"
                    class="hidden sm:flex items-center h-full px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold
                           {{ request()->routeIs('employees.attendances.*', 'employees.leave-requests.*') ? 'bg-[#5c3d55]' : '' }}">
                {{ __('employees.attendance_menu') }}
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
                <a href="{{ route('employees.attendances.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.attendances.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('employees.attendances_title') }}
                </a>
                @if(auth()->user()->hasPermission('attendance.requests.read'))
                <a href="{{ route('employees.requests.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.requests.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('employees.requests_title') }}
                </a>
                @elseif(auth()->user()->hasPermission('attendance.self.request'))
                <a href="{{ route('employees.my-requests') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.my-requests', 'employees.requests.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('employees.my_requests_title') }}
                </a>
                @endif
            </div>
        </div>
        @endcan

        {{-- Configuration dropdown --}}
        @can('viewAny', \App\Models\Employees\Employee::class)
        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button"
                    @click="open = !open"
                    class="hidden sm:flex items-center h-full px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold
                           {{ request()->routeIs('employees.jobs.*', 'employees.departments.*', 'employees.documents.*', 'employees.work-locations.*', 'employees.schedules.*', 'employees.categories.*', 'employees.departure-reasons.*', 'employees.skill-types.*', 'employees.resume-line-types.*', 'employees.employment-types.*', 'employees.badges.*', 'employees.challenges.*', 'employees.goals.*') ? 'bg-[#5c3d55]' : '' }}">
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
                <div class="px-3 py-1.5 text-[10px] font-semibold text-gray-400 uppercase">{{ __('nav.section_organization') }}</div>
                <a href="{{ route('employees.jobs.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.jobs.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.job_positions') }}
                </a>
                <a href="{{ route('employees.departments.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.departments.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.departments') }}
                </a>
                <a href="{{ route('employees.documents.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.documents.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.documents') }}
                </a>
                <div class="border-t border-gray-100 my-1"></div>
                <div class="px-3 py-1.5 text-[10px] font-semibold text-gray-400 uppercase">{{ __('nav.section_settings') }}</div>
                <a href="{{ route('employees.work-locations.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.work-locations.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.work_locations') }}
                </a>
                <a href="{{ route('employees.schedules.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.schedules.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.working_schedules') }}
                </a>
                @if(auth()->user()->hasPermission('attendance.requests.config'))
                <a href="{{ route('employees.request-subtypes.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.request-subtypes.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('employees.subtypes_title') }}
                </a>
                <a href="{{ route('employees.request-balance-config.show') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.request-balance-config.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('employees.balance_config_title') }}
                </a>
                @endif
                <a href="{{ route('employees.categories.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.categories.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.tags') }}
                </a>
                <a href="{{ route('employees.departure-reasons.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.departure-reasons.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.departure_reasons') }}
                </a>
                <a href="{{ route('employees.skill-types.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.skill-types.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.skill_types') }}
                </a>
                <a href="{{ route('employees.resume-line-types.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.resume-line-types.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.line_types') }}
                </a>
                <a href="{{ route('employees.employment-types.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.employment-types.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.employment_types') }}
                </a>
                <div class="border-t border-gray-100 my-1"></div>
                <div class="px-3 py-1.5 text-[10px] font-semibold text-gray-400 uppercase">{{ __('nav.section_challenges') }}</div>
                <a href="{{ route('employees.badges.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.badges.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.badges') }}
                </a>
                <a href="{{ route('employees.challenges.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.challenges.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.challenges') }}
                </a>
                <a href="{{ route('employees.goals.index') }}"
                   class="block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100 {{ request()->routeIs('employees.goals.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.goals_history') }}
                </a>
            </div>
        </div>
        @endcan
    </div>
    @endif

    @if(request()->routeIs('accounting.*'))
    @php
        $accLink = 'flex items-center h-full px-3 sm:px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold shrink-0';
        $accBtn  = 'hidden sm:flex items-center h-full px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold';
        $accDD   = 'absolute start-0 top-full w-60 max-w-[calc(100vw-1rem)] bg-white rounded-b-lg shadow-xl border border-gray-200 z-50 py-1';
        $accItem = 'block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100';
        $accDis  = 'block px-4 py-2.5 text-sm text-gray-400 cursor-not-allowed select-none';
        $accHead = 'px-3 pt-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide';
        $caret   = '<svg class="ms-1 w-3.5 h-3.5 transition-transform" :class="open ? \'rotate-180\' : \'\'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
    @endphp
    <div class="flex min-w-0 flex-1 items-center h-full">
        {{-- Dashboard --}}
        <a href="{{ route('accounting.dashboard') }}"
           class="{{ $accLink }} {{ request()->routeIs('accounting.dashboard') ? 'bg-[#5c3d55]' : '' }}">
            {{ __('accounting.nav_dashboard') }}
        </a>

        {{-- Customers --}}
        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button" @click="open = !open"
                    class="{{ $accBtn }} {{ request()->routeIs('accounting.invoices.*', 'accounting.credit-notes.*', 'accounting.payments.*') ? 'bg-[#5c3d55]' : '' }}">
                {{ __('accounting.nav_customers') }} {!! $caret !!}
            </button>
            <div x-show="open" x-transition class="{{ $accDD }}" style="display:none">
                <a href="{{ route('accounting.invoices.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.invoices.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.invoices') }}
                </a>
                <a href="{{ route('accounting.credit-notes.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.credit-notes.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.credit_notes') }}
                </a>
                <a href="{{ route('accounting.payments.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.payments.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.payments') }}
                </a>
                <a href="{{ route('inventory.products.index') }}" class="{{ $accItem }}">{{ __('accounting.nav_products') }}</a>
                <a href="{{ route('contacts.index', ['type' => 'individual']) }}" class="{{ $accItem }}">{{ __('accounting.nav_customers_label') }}</a>
            </div>
        </div>

        {{-- Vendors --}}
        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button" @click="open = !open"
                    class="{{ $accBtn }} {{ request()->routeIs('accounting.bills.*', 'accounting.refunds.*', 'accounting.payments.*') ? 'bg-[#5c3d55]' : '' }}">
                {{ __('accounting.nav_vendors') }} {!! $caret !!}
            </button>
            <div x-show="open" x-transition class="{{ $accDD }}" style="display:none">
                <a href="{{ route('accounting.bills.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.bills.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.bills') }}
                </a>
                <a href="{{ route('accounting.refunds.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.refunds.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.refunds') }}
                </a>
                <a href="{{ route('accounting.payments.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.payments.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.payments') }}
                </a>
                <a href="{{ route('inventory.products.index') }}" class="{{ $accItem }}">{{ __('accounting.nav_products') }}</a>
                <a href="{{ route('contacts.index', ['type' => 'company']) }}" class="{{ $accItem }}">{{ __('accounting.nav_vendors_label') }}</a>
            </div>
        </div>

        {{-- Accounting --}}
        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button" @click="open = !open"
                    class="{{ $accBtn }} {{ request()->routeIs('accounting.moves.*', 'accounting.items.*') ? 'bg-[#5c3d55]' : '' }}">
                {{ __('accounting.nav_accounting') }} {!! $caret !!}
            </button>
            <div x-show="open" x-transition class="{{ $accDD }}" style="display:none">
                <a href="{{ route('accounting.moves.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.moves.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.journal_entries') }}
                </a>
                <a href="{{ route('accounting.items.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.items.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.journal_items') }}
                </a>
                <div class="{{ $accHead }}">{{ __('accounting.nav_actions') }}</div>
                @if(auth()->user()->hasPermission('accounting.lock'))
                <a href="{{ route('accounting.settings') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.settings') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.nav_lock_dates') }}
                </a>
                @else
                <span class="{{ $accDis }}" title="{{ __('accounting.requires_lock_perm') }}">{{ __('accounting.nav_lock_dates') }}</span>
                @endif
            </div>
        </div>

        {{-- Reporting --}}
        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button" @click="open = !open"
                    class="{{ $accBtn }} {{ request()->routeIs('accounting.reports.*') ? 'bg-[#5c3d55]' : '' }}">
                {{ __('accounting.nav_reporting') }} {!! $caret !!}
            </button>
            <div x-show="open" x-transition class="{{ $accDD }} max-h-[80vh] overflow-y-auto" style="display:none">
                <div class="{{ $accHead }}">{{ __('accounting.nav_statement_reports') }}</div>
                <a href="{{ route('accounting.reports.profit-and-loss') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.reports.profit-and-loss') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.report_profit_loss') }}
                </a>
                <a href="{{ route('accounting.reports.balance-sheet') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.reports.balance-sheet') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.report_balance_sheet') }}
                </a>
                <a href="{{ route('accounting.reports.executive-summary') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.reports.executive-summary') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.report_executive') }}
                </a>
                <a href="{{ route('accounting.reports.cash-flow') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.reports.cash-flow') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.report_cash_flow') }}
                </a>
                <a href="{{ route('accounting.reports.tax-report') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.reports.tax-report') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.report_tax') }}
                </a>
                <div class="{{ $accHead }}">{{ __('accounting.nav_ledger_reports') }}</div>
                <a href="{{ route('accounting.reports.general-ledger') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.reports.general-ledger') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.report_general_ledger') }}
                </a>
                <a href="{{ route('accounting.reports.trial-balance') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.reports.trial-balance') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.report_trial_balance') }}
                </a>
                <a href="{{ route('accounting.reports.partner-ledger') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.reports.partner-ledger') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.report_partner_ledger') }}
                </a>
                <a href="{{ route('accounting.reports.aged-receivable') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.reports.aged-receivable') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.report_aged_receivable') }}
                </a>
                <a href="{{ route('accounting.reports.aged-payable') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.reports.aged-payable') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.report_aged_payable') }}
                </a>
                <div class="{{ $accHead }}">{{ __('accounting.nav_audit_reports') }}</div>
                <a href="{{ route('accounting.reports.journal-audit') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.reports.journal-audit') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.report_journal_audit') }}
                </a>
                <a href="{{ route('accounting.reports.bank-reconciliation') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.reports.bank-reconciliation') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.report_bank_recon') }}
                </a>
            </div>
        </div>

        {{-- Configuration --}}
        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button" @click="open = !open"
                    class="{{ $accBtn }} {{ request()->routeIs('accounting.accounts.*', 'accounting.journals.*', 'accounting.taxes.*', 'accounting.currencies.*', 'accounting.settings', 'accounting.audit', 'accounting.payment-terms.*', 'accounting.incoterms.*', 'accounting.tax-groups.*', 'accounting.account-groups.*') ? 'bg-[#5c3d55]' : '' }}">
                {{ __('accounting.nav_configuration') }} {!! $caret !!}
            </button>
            <div x-show="open" x-transition class="{{ $accDD }} w-64 max-h-[80vh] overflow-y-auto" style="display:none">
                @if(auth()->user()->hasPermission('accounting.lock'))
                <a href="{{ route('accounting.settings') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.settings') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.nav_settings_lock') }}
                </a>
                @else
                <span class="{{ $accDis }}" title="{{ __('accounting.requires_lock_perm') }}">{{ __('accounting.nav_settings_lock') }}</span>
                @endif

                <div class="{{ $accHead }}">{{ __('accounting.nav_invoicing') }}</div>
                <a href="{{ route('accounting.payment-terms.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.payment-terms.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.payment_terms') }}
                </a>
                <a href="{{ route('accounting.incoterms.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.incoterms.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.incoterms') }}
                </a>

                <div class="{{ $accHead }}">{{ __('accounting.nav_banks') }}</div>
                <a href="{{ route('accounting.journals.create') }}" class="{{ $accItem }}">{{ __('accounting.nav_add_bank') }}</a>

                <div class="{{ $accHead }}">{{ __('accounting.nav_accounting') }}</div>
                <a href="{{ route('accounting.accounts.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.accounts.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.chart_of_accounts') }}
                </a>
                <a href="{{ route('accounting.taxes.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.taxes.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.taxes') }}
                </a>
                <a href="{{ route('accounting.journals.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.journals.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.journals') }}
                </a>
                <a href="{{ route('accounting.currencies.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.currencies.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.exchange_rates') }}
                </a>
                <a href="{{ route('accounting.tax-groups.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.tax-groups.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.tax_groups') }}
                </a>
                <a href="{{ route('accounting.account-groups.index') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.account-groups.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('accounting.account_groups') }}
                </a>


                <div class="{{ $accHead }}">{{ __('accounting.section_audit') }}</div>
                <a href="{{ route('accounting.audit') }}"
                   class="{{ $accItem }} {{ request()->routeIs('accounting.audit') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.audit_log') }}
                </a>
            </div>
        </div>
    </div>
    @endif

    @if(request()->routeIs('inventory.*'))
    @php
        $invLink = 'flex items-center h-full px-3 sm:px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold shrink-0';
        $invBtn  = 'hidden sm:flex items-center h-full px-4 text-white/85 hover:text-white hover:bg-[#5c3d55] transition-colors text-sm font-semibold';
        $invDD   = 'absolute start-0 top-full w-56 bg-white rounded-b-lg shadow-xl border border-gray-200 z-50 py-1';
        $invItem = 'block px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-100';
        $invHead = 'px-3 pt-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide';
        $caret   = '<svg class="ms-1 w-3.5 h-3.5 transition-transform" :class="open ? \'rotate-180\' : \'\'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
    @endphp
    <div class="flex min-w-0 flex-1 items-center h-full">

        {{-- Overview --}}
        <a href="{{ route('inventory.dashboard') }}"
           class="{{ $invLink }} {{ request()->routeIs('inventory.dashboard') ? 'bg-[#5c3d55]' : '' }}">
            {{ __('nav.overview') }}
        </a>

        {{-- Operations --}}
        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button" @click="open = !open"
                    class="{{ $invBtn }} {{ request()->routeIs('inventory.receipts.*', 'inventory.deliveries.*', 'inventory.internal-transfers.*', 'inventory.transfers.*', 'inventory.scrap.*', 'inventory.replenishment.*', 'inventory.adjustments.*') ? 'bg-[#5c3d55]' : '' }}">
                {{ __('nav.operations') }} {!! $caret !!}
            </button>
            <div x-show="open" x-transition class="{{ $invDD }} w-60" style="display:none">
                <div class="{{ $invHead }}">{{ __('nav.section_transfers') }}</div>
                <a href="{{ route('inventory.receipts.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.receipts.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.receipts') }}
                </a>
                <a href="{{ route('inventory.deliveries.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.deliveries.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.deliveries') }}
                </a>
                <a href="{{ route('inventory.internal-transfers.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.internal-transfers.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.internal_transfers') }}
                </a>
                <div class="{{ $invHead }}">{{ __('nav.section_adjustments') }}</div>
                <a href="{{ route('inventory.adjustments.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.adjustments.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.physical_inventory') }}
                </a>
                <a href="{{ route('inventory.scrap.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.scrap.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.scrap') }}
                </a>
                <div class="{{ $invHead }}">{{ __('nav.section_procurement') }}</div>
                <a href="{{ route('inventory.replenishment.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.replenishment.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.replenishment') }}
                </a>
            </div>
        </div>

        {{-- Products --}}
        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button" @click="open = !open"
                    class="{{ $invBtn }} {{ request()->routeIs('inventory.products.*', 'inventory.lots.*') ? 'bg-[#5c3d55]' : '' }}">
                {{ __('nav.products') }} {!! $caret !!}
            </button>
            <div x-show="open" x-transition class="{{ $invDD }}" style="display:none">
                <a href="{{ route('inventory.products.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.products.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.products') }}
                </a>
                <a href="{{ route('inventory.lots.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.lots.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.lots_serial_numbers') }}
                </a>
            </div>
        </div>

        {{-- Reporting --}}
        <a href="{{ route('inventory.reports.stock') }}"
           class="{{ $invLink }} {{ request()->routeIs('inventory.reports.*') ? 'bg-[#5c3d55]' : '' }}">
            {{ __('nav.reporting') }}
        </a>

        {{-- Configuration --}}
        @if(auth()->user()->hasPermission('inventory.config'))
        <div x-data="{ open: false }" class="relative h-full shrink-0" @click.outside="open = false">
            <button type="button" @click="open = !open"
                    class="{{ $invBtn }} {{ request()->routeIs('inventory.config.*') ? 'bg-[#5c3d55]' : '' }}">
                {{ __('nav.configuration') }} {!! $caret !!}
            </button>
            <div x-show="open" x-transition class="{{ $invDD }} w-64" style="display:none">
                <div class="{{ $invHead }}">{{ __('nav.section_warehouse_mgmt') }}</div>
                <a href="{{ route('inventory.config.warehouses.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.config.warehouses.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.warehouses') }}
                </a>
                <a href="{{ route('inventory.config.operation-types.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.config.operation-types.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.operation_types') }}
                </a>
                <a href="{{ route('inventory.config.locations.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.config.locations.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.locations') }}
                </a>
                <a href="{{ route('inventory.config.routes.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.config.routes.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.routes') }}
                </a>
                <a href="{{ route('inventory.config.putaway-rules.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.config.putaway-rules.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.putaway_rules') }}
                </a>
                <div class="{{ $invHead }}">{{ __('nav.section_products') }}</div>
                <a href="{{ route('inventory.config.product-categories.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.config.product-categories.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.product_categories') }}
                </a>
                <div class="{{ $invHead }}">{{ __('nav.section_uom') }}</div>
                <a href="{{ route('inventory.config.uoms.index') }}"
                   class="{{ $invItem }} {{ request()->routeIs('inventory.config.uoms.*') ? 'bg-gray-100 font-semibold' : '' }}">
                    {{ __('nav.units_of_measure') }}
                </a>
            </div>
        </div>
        @endif
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
                <span class="hidden md:block truncate font-medium">{{ $companyLabel ?? __('nav.no_company') }}</span>
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
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('common.notifications') }}</p>
                    <button @click="markAllSeen()" x-show="count > 0"
                            class="text-xs text-purple-600 hover:text-purple-700 font-medium"
                            style="display:none">
                        {{ __('common.mark_all_read') }}
                    </button>
                </div>

                <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
                    <template x-if="notifications.length === 0">
                        <p class="px-4 py-8 text-sm text-gray-400 text-center">{{ __('common.no_notifications') }}</p>
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
