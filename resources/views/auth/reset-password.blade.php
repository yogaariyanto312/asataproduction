@extends('layouts.auth')

@section('title', 'Reset Password')

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
            <h2 class="text-4xl font-bold text-white leading-tight">Buat Password Baru</h2>
            <p class="text-blue-200 text-lg">
                Masukkan password baru untuk akun Anda. Pastikan minimal 8 karakter.
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
                <h1 class="text-3xl font-bold text-white">Reset Password</h1>
                <p class="text-slate-400 mt-2">Buat password baru untuk akun Anda.</p>
            </div>

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

            <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-300 mb-2">Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                            </svg>
                        </div>
                        <input type="email" id="email" name="email"
                               value="{{ old('email', $email) }}"
                               readonly
                               class="w-full pl-10 pr-4 py-3 bg-slate-700 border border-slate-600 text-slate-400 rounded-xl cursor-not-allowed">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-300 mb-2">Password Baru</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <input type="password" id="password" name="password"
                               autofocus autocomplete="new-password"
                               class="w-full pl-10 pr-12 py-3 bg-slate-800 border text-white rounded-xl
                                      placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                      {{ $errors->has('password') ? 'border-red-500' : 'border-slate-600' }}"
                               placeholder="Minimal 8 karakter">
                        <button type="button" onclick="togglePass('password', 'eye1')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300">
                            <svg id="eye1" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                    <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-300 mb-2">Konfirmasi Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               autocomplete="new-password"
                               class="w-full pl-10 pr-12 py-3 bg-slate-800 border border-slate-600 text-white rounded-xl
                                      placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Ulangi password baru">
                        <button type="button" onclick="togglePass('password_confirmation', 'eye2')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300">
                            <svg id="eye2" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl
                               shadow-lg shadow-blue-900/30 transition-all duration-200 transform hover:scale-[1.02]
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                    Simpan Password Baru
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function togglePass(fieldId, iconId) {
    const input = document.getElementById(fieldId);
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
@endsection
