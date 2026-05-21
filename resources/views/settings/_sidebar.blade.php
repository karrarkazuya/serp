@section('sidebar')
<div class="px-3 py-3">
    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-3 pb-2">{{ __('settings.title') }}</p>
    @php
        $items = collect([
            ['route' => 'settings.index', 'pattern' => 'settings.index', 'permission' => 'settings.read', 'label' => __('settings.general_settings'), 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ['route' => 'settings.companies.index', 'pattern' => 'settings.companies.*', 'permission' => 'companies.read', 'label' => __('settings.companies'), 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
            ['route' => 'settings.users.index', 'pattern' => 'settings.users.*', 'permission' => 'users.read', 'label' => __('settings.users'), 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
            ['route' => 'settings.roles.index', 'pattern' => 'settings.roles.*', 'permission' => 'roles.read', 'label' => __('settings.roles'), 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ['route' => 'settings.permissions.index', 'pattern' => 'settings.permissions.*', 'permission' => 'roles.read', 'label' => __('settings.permissions'), 'icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z'],
            ['route' => 'settings.system',          'pattern' => 'settings.system',          'permission' => 'settings.read', 'label' => __('settings.system'),      'icon' => 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01'],
        ])->filter(fn ($item) => auth()->user()->hasPermission($item['permission']));
    @endphp

    <nav class="space-y-0.5">
        @foreach($items as $item)
        @php $active = request()->routeIs($item['pattern']); @endphp
        <a href="{{ route($item['route']) }}"
           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-all duration-150
                  {{ $active
                      ? 'bg-[#714B67]/10 text-[#714B67] font-semibold'
                      : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
            <svg class="w-4 h-4 shrink-0 {{ $active ? 'text-[#714B67]' : 'text-gray-400' }}"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
            </svg>
            {{ $item['label'] }}
        </a>
        @endforeach
    </nav>
</div>
@endsection
