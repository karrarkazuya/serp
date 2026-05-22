@extends('layouts.auth')
@section('title', __('auth.sign_in'))

@section('content')
<style>
@keyframes floatA {
    0%,100% { transform: translate(0,0) scale(1); }
    33%  { transform: translate(40px,-60px) scale(1.06); }
    66%  { transform: translate(-25px,25px) scale(0.95); }
}
@keyframes floatB {
    0%,100% { transform: translate(0,0) scale(1); }
    40%  { transform: translate(-50px,40px) scale(1.09); }
    70%  { transform: translate(30px,-20px) scale(0.93); }
}
@keyframes floatC {
    0%,100% { transform: translate(0,0) scale(1); }
    50%  { transform: translate(30px,45px) scale(1.05); }
}
@keyframes fadeUp {
    from { opacity:0; transform:translateY(28px); }
    to   { opacity:1; transform:translateY(0); }
}
@keyframes fadeLeft {
    from { opacity:0; transform:translateX(-32px); }
    to   { opacity:1; transform:translateX(0); }
}
@keyframes popIn {
    from { opacity:0; transform:scale(0.8); }
    to   { opacity:1; transform:scale(1); }
}
@keyframes ringPulse {
    0%,100% { box-shadow:0 0 0 0 rgba(255,255,255,0.25); }
    50%     { box-shadow:0 0 0 14px rgba(255,255,255,0); }
}
@keyframes shimmer {
    from { left:-100%; }
    to   { left:150%; }
}
@keyframes gradientShift {
    0%,100% { background-position: 0% 50%; }
    50%     { background-position: 100% 50%; }
}

.orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(72px);
    pointer-events: none;
    will-change: transform;
}
.orb-a {
    width: 500px; height: 500px;
    background: rgba(124, 58, 237, 0.4);
    top: -120px; left: -120px;
    animation: floatA 20s ease-in-out infinite;
}
.orb-b {
    width: 400px; height: 400px;
    background: rgba(113, 75, 103, 0.55);
    bottom: -80px; right: -100px;
    animation: floatB 24s ease-in-out infinite;
    animation-delay: -7s;
}
.orb-c {
    width: 320px; height: 320px;
    background: rgba(196, 100, 180, 0.3);
    top: 40%; left: 40%;
    animation: floatC 15s ease-in-out infinite;
    animation-delay: -4s;
}

/* Load-in animations — left panel */
.anim-panel-left  { animation: fadeLeft 0.9s ease both; }
.anim-logo-left   { animation: popIn 0.65s cubic-bezier(0.34,1.56,0.64,1) both 0.4s; }
.logo-ring        { animation: ringPulse 2.8s ease-in-out infinite 1.4s; }
.anim-h1          { animation: fadeUp 0.6s ease both 0.55s; }
.anim-tagline     { animation: fadeUp 0.6s ease both 0.65s; }
.anim-features    { animation: fadeUp 0.65s ease both 0.8s; }

/* Load-in animations — right panel */
.anim-form-wrap   { animation: fadeUp 0.8s cubic-bezier(0.16,1,0.3,1) both 0.2s; }
.anim-logo-sm     { animation: popIn 0.55s cubic-bezier(0.34,1.56,0.64,1) both 0.45s; }
.anim-form-title  { animation: fadeUp 0.55s ease both 0.55s; }
.anim-f1          { animation: fadeUp 0.5s ease both 0.65s; }
.anim-f2          { animation: fadeUp 0.5s ease both 0.73s; }
.anim-f3          { animation: fadeUp 0.5s ease both 0.79s; }
.anim-btn         { animation: fadeUp 0.5s ease both 0.86s; }
.anim-footer      { animation: fadeUp 0.45s ease both 1s; }

/* Shimmer on button hover */
.btn-shine { position: relative; overflow: hidden; }
.btn-shine::after {
    content: '';
    position: absolute; top: 0;
    left: -100%; width: 55%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.18), transparent);
    transform: skewX(-20deg);
    pointer-events: none;
}
.btn-shine:hover::after {
    animation: shimmer 0.6s ease forwards;
}
</style>

<div class="min-h-screen flex">

    {{-- ── Left panel ─────────────────────────────────── --}}
    <div class="hidden lg:flex lg:w-[52%] relative overflow-hidden flex-col items-center justify-center px-16 anim-panel-left"
         style="background: linear-gradient(145deg, #160829 0%, #2e1050 30%, #5a2d6e 65%, #714B67 100%);">

        <div class="orb orb-a"></div>
        <div class="orb orb-b"></div>
        <div class="orb orb-c"></div>

        <div class="relative z-10 w-full max-w-sm text-center">

            <div class="anim-logo-left logo-ring inline-flex w-20 h-20 items-center justify-center rounded-2xl mb-7 border border-white/20"
                 style="background: rgba(255,255,255,0.1); backdrop-filter: blur(8px);">
                <span class="text-white font-bold text-3xl tracking-tight">S</span>
            </div>

            <h1 class="anim-h1 text-4xl font-bold text-white mb-3 tracking-tight">S-ERP</h1>
            <p class="anim-tagline text-white/55 text-base leading-relaxed">{{ __('auth.business_tagline') }}</p>

            <div class="anim-features mt-11 grid grid-cols-2 gap-3 text-start">
                @foreach([
                    ['icon' => '👥', 'title' => __('auth.feature_contacts'),  'desc' => __('auth.feature_contacts_d')],
                    ['icon' => '⚙️', 'title' => __('auth.feature_settings'),  'desc' => __('auth.feature_settings_d')],
                    ['icon' => '📋', 'title' => __('auth.feature_log'),       'desc' => __('auth.feature_log_d')],
                    ['icon' => '🔒', 'title' => __('auth.feature_security'),  'desc' => __('auth.feature_security_d')],
                ] as $feature)
                <div class="rounded-xl p-4 border border-white/10 transition-colors duration-300 hover:border-white/20"
                     style="background: rgba(255,255,255,0.07); backdrop-filter: blur(4px);">
                    <div class="text-2xl mb-2 leading-none">{{ $feature['icon'] }}</div>
                    <div class="text-white/90 font-semibold text-sm">{{ $feature['title'] }}</div>
                    <div class="text-white/45 text-xs mt-1 leading-relaxed">{{ $feature['desc'] }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Right panel ──────────────────────────────────── --}}
    <div class="flex-1 flex items-center justify-center px-8 py-12 bg-white">
        <div class="anim-form-wrap w-full max-w-85">

            {{-- Mobile logo --}}
            <div class="anim-logo-sm lg:hidden text-center mb-8">
                <div class="w-14 h-14 bg-[#714B67] rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-lg shadow-purple-200">
                    <span class="text-white font-bold text-2xl">S</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">S-ERP</h1>
            </div>

            <div class="anim-form-title mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-1">{{ __('auth.welcome_back') }}</h2>
                <p class="text-gray-400 text-sm">{{ __('auth.sign_in_subtitle') }}</p>
            </div>

            <form method="POST" action="{{ route('login.post') }}" class="space-y-5">
                @csrf

                {{-- Email --}}
                <div class="anim-f1">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('auth.email_address') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" autofocus
                           class="w-full px-4 py-2.5 border rounded-xl text-sm transition-all duration-200
                                  focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent focus:bg-white
                                  {{ $errors->has('email') ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-gray-50 hover:border-gray-300' }}">
                    @error('email')
                        <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="anim-f2">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('auth.password') }}</label>
                    <div class="relative" x-data="{ show: false }">
                        <input id="password" :type="show ? 'text' : 'password'" name="password" autocomplete="current-password"
                               class="w-full px-4 py-2.5 pe-10 border rounded-xl text-sm transition-all duration-200
                                      focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent focus:bg-white
                                      {{ $errors->has('password') ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-gray-50 hover:border-gray-300' }}">
                        <button type="button" @click="show = !show"
                                class="absolute end-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Remember me --}}
                <div class="anim-f3 flex items-center gap-2">
                    <input id="remember" type="checkbox" name="remember"
                           class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                    <label for="remember" class="text-sm text-gray-500">{{ __('auth.remember_me') }}</label>
                </div>

                {{-- Submit --}}
                <div class="anim-btn pt-1">
                    <button type="submit"
                            class="btn-shine w-full bg-[#714B67] hover:bg-[#5c3d55] text-white font-semibold py-2.5 px-4 rounded-xl text-sm
                                   transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-purple-400 focus:ring-offset-2
                                   shadow-sm hover:shadow-md hover:shadow-purple-200 active:scale-[0.98]">
                        {{ __('auth.sign_in') }}
                    </button>
                </div>
            </form>

            <p class="anim-footer mt-8 text-center text-xs text-gray-300">
                S-ERP &copy; {{ date('Y') }} — {{ __('auth.powered_by') }}
            </p>
        </div>
    </div>

</div>
@endsection
