@extends('layouts.auth')

@section('title', 'Login')

@section('content')
<div class="min-h-screen flex">

    {{-- Left panel - Branding --}}
    <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-12 relative overflow-hidden"
         style="background-image: url('{{ asset('images/IFS07479-scaled.jpg') }}'); background-size: cover; background-position: center;">
        <div class="absolute inset-0 bg-slate-950/65"></div>

        <div class="relative z-10 flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-500 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <span class="text-xl font-bold text-white">Asata Production System</span>
        </div>

        <div class="relative z-10 space-y-6">
            <div>
                <h2 class="text-4xl font-bold text-white leading-tight">
                    Sistem Pencatatan<br>Produksi Digital
                </h2>
                <p class="mt-4 text-blue-200 text-lg">
                    Gantikan pencatatan manual dengan sistem digital yang cepat, akurat, dan mudah digunakan.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                @foreach([
                    ['icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'text' => 'Input Cepat'],
                    ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'text' => 'Grafik Real-time'],
                    ['icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'text' => 'Export Laporan'],
                    ['icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'text' => 'Aman & Terlindungi'],
                ] as $feature)
                <div class="flex items-center gap-3 bg-white/10 backdrop-blur-sm rounded-xl p-3">
                    <div class="w-8 h-8 bg-blue-500/30 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $feature['icon'] }}"/>
                        </svg>
                    </div>
                    <span class="text-sm text-blue-100 font-medium">{{ $feature['text'] }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <p class="relative z-10 text-blue-400 text-sm">&copy; {{ now()->year }} Asata Production System</p>
    </div>

    {{-- Right panel - Form --}}
    <div class="flex-1 flex items-center justify-center p-8">
        <div class="w-full max-w-md">

            {{-- Mobile logo --}}
            <div class="lg:hidden flex items-center gap-3 mb-8 justify-center">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <span class="text-xl font-bold text-white">Asata Production</span>
            </div>

            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-white">Selamat Datang</h1>
                <p class="text-slate-400 mt-2">Masuk ke Asata Production System</p>
            </div>

            @php
                $throttleSeconds = 0;
                $firstError = $errors->first();
                if ($firstError && preg_match('/Coba lagi dalam (\d+) detik/', $firstError, $_m)) {
                    $throttleSeconds = (int) $_m[1];
                }
            @endphp

            {{-- Error --}}
            @if($errors->any())
            <div class="mb-6 p-4 bg-red-900/30 border border-red-700 rounded-xl">
                <div class="flex items-center gap-2 text-red-400">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm font-medium" id="login-error-msg">{{ $firstError }}</p>
                </div>
            </div>
            @endif

            @if(session('success'))
            <div class="mb-6 p-4 bg-green-900/30 border border-green-700 rounded-xl">
                <p class="text-sm text-green-400">{{ session('success') }}</p>
            </div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('login') }}" class="space-y-5" id="login-form">
                @csrf

                {{-- Username / Email --}}
                <div>
                    <label for="identifier" class="block text-sm font-medium text-slate-300 mb-2">
                        Username atau Email
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <input type="text" id="identifier" name="identifier"
                               value="{{ old('identifier') }}"
                               autocomplete="username"
                               autofocus
                               @if($throttleSeconds > 0) disabled @endif
                               class="w-full pl-10 pr-4 py-3 bg-slate-800 border text-white rounded-xl
                                      placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                      disabled:opacity-50 disabled:cursor-not-allowed
                                      {{ $errors->has('identifier') ? 'border-red-500' : 'border-slate-600' }}"
                               placeholder="Username atau email Anda">
                    </div>
                </div>

                {{-- Password --}}
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <input type="password" id="password" name="password"
                               autocomplete="current-password"
                               @if($throttleSeconds > 0) disabled @endif
                               class="w-full pl-10 pr-12 py-3 bg-slate-800 border border-slate-600 text-white rounded-xl
                                      placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                      disabled:opacity-50 disabled:cursor-not-allowed"
                               placeholder="••••••••">
                        <button type="button" onclick="togglePassword()"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" value="1"
                               @if($throttleSeconds > 0) disabled @endif
                               class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-blue-600 focus:ring-blue-500
                                      disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="text-sm text-slate-400">Ingat saya</span>
                    </label>
                    <a href="{{ route('password.request') }}"
                       class="text-sm text-blue-400 hover:text-blue-300 transition-colors">
                        Lupa password?
                    </a>
                </div>

                <button type="submit" id="login-btn"
                        @if($throttleSeconds > 0) disabled @endif
                        class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl
                               shadow-lg shadow-blue-900/30 transition-all duration-200
                               disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-blue-600 disabled:hover:scale-100
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                    @if($throttleSeconds > 0)
                        Tunggu <span id="login-countdown">{{ $throttleSeconds }}</span> detik...
                    @else
                        Masuk ke Sistem
                    @endif
                </button>
            </form>

            <p class="mt-8 text-center text-xs text-slate-600">
                Hubungi administrator jika mengalami masalah login
            </p>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
}

// Countdown throttle — re-enable form saat waktu habis
(function () {
    var secs = {{ $throttleSeconds ?? 0 }};
    if (secs <= 0) return;

    var countEl = document.getElementById('login-countdown');
    var btn     = document.getElementById('login-btn');
    var form    = document.getElementById('login-form');
    var errMsg  = document.getElementById('login-error-msg');

    var timer = setInterval(function () {
        secs--;
        if (countEl) countEl.textContent = secs;

        if (secs <= 0) {
            clearInterval(timer);
            // Re-enable semua elemen form
            form.querySelectorAll('input, button[type=submit]').forEach(function (el) {
                el.disabled = false;
            });
            if (btn) btn.textContent = 'Masuk ke Sistem';
            if (errMsg) errMsg.closest('div.mb-6')?.remove();
        }
    }, 1000);
}());
</script>
@endsection
