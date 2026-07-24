@extends('layouts.auth')

@section('title', 'Lupa Password')

@section('content')
<div class="min-h-screen flex">

    {{-- Left panel --}}
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

        <div class="relative z-10 space-y-4">
            <h2 class="text-4xl font-bold text-white leading-tight">Lupa Password?</h2>
            <p class="text-blue-200 text-lg">
                Masukkan username atau email Anda dan kami akan mengirimkan link untuk membuat password baru.
            </p>
        </div>

        <p class="relative z-10 text-blue-400 text-sm">&copy; {{ now()->year }} Asata Production System</p>
    </div>

    {{-- Right panel --}}
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

            <div class="mb-8">
                <h1 class="text-3xl font-bold text-white">Lupa Password</h1>
                <p class="text-slate-400 mt-2">Masukkan username atau email akun Anda.</p>
            </div>

            {{-- Success --}}
            @if(session('success'))
            <div class="mb-6 p-4 bg-green-900/30 border border-green-700 rounded-xl">
                <div class="flex items-start gap-2 text-green-400">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <p class="text-sm">{{ session('success') }}</p>
                </div>
            </div>
            @endif

            {{-- Error --}}
            @if($errors->any())
            <div class="mb-6 p-4 bg-red-900/30 border border-red-700 rounded-xl">
                <div class="flex items-center gap-2 text-red-400">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm font-medium">{{ $errors->first() }}</p>
                </div>
            </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                @csrf

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
                               autofocus
                               class="w-full pl-10 pr-4 py-3 bg-slate-800 border text-white rounded-xl
                                      placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                      {{ $errors->has('identifier') ? 'border-red-500' : 'border-slate-600' }}"
                               placeholder="Username atau email Anda">
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500">
                        Link reset akan dikirim ke email yang terdaftar pada akun tersebut.
                    </p>
                </div>

                <button type="submit"
                        class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl
                               shadow-lg shadow-blue-900/30 transition-all duration-200 transform hover:scale-[1.02]
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                    Kirim Link Reset Password
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="{{ route('login') }}" class="text-sm text-blue-400 hover:text-blue-300 transition-colors">
                    &larr; Kembali ke halaman login
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
