<aside class="w-56 bg-white border-r border-gray-200 flex flex-col overflow-y-auto shrink-0">

    {{-- Sidebar header --}}
    @if(isset($sidebarTitle))
    <div class="px-4 py-3 border-b border-gray-200">
        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ $sidebarTitle }}</h2>
    </div>
    @endif

    <nav class="flex-1 py-2">
        @yield('sidebar')
    </nav>
</aside>
