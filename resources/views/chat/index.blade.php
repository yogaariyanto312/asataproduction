@extends('layouts.app')

@section('title', 'Chat Admin')
@section('page-title', 'Chat Admin')
@section('page-subtitle', 'Kirim pertanyaan atau laporan ke admin')

@section('content')
<div class="max-w-2xl mx-auto">
    <div x-data="chatPage()"
         class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden flex flex-col"
         style="height: calc(100vh - 14rem)">

        {{-- Header --}}
        <div class="bg-blue-600 px-5 py-4 flex items-center gap-3 shrink-0">
            <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z"/>
                </svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-bold text-white">Admin Asata Production</p>
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 bg-green-400 rounded-full"></span>
                    <p class="text-xs text-blue-200">Aktif</p>
                </div>
            </div>
            <button @click="load()" class="text-blue-200 hover:text-white transition-colors" title="Refresh">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>
        </div>

        {{-- Area pesan --}}
        <div id="chat-thread" class="flex-1 overflow-y-auto p-4 bg-slate-50 dark:bg-slate-900/30">

            {{-- Loading --}}
            <div x-show="loading" class="flex justify-center py-8">
                <svg class="w-6 h-6 text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
            </div>

            {{-- Empty --}}
            <div x-show="!loading && bubbles.length === 0"
                 class="flex flex-col items-center justify-center h-full text-center py-16">
                <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900/30 rounded-2xl flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z"/>
                    </svg>
                </div>
                <p class="text-slate-500 font-medium">Belum ada pesan</p>
                <p class="text-slate-400 text-sm mt-1">Ketik pesan di bawah untuk mulai chat</p>
            </div>

            {{-- Bubbles --}}
            <div x-show="!loading" class="space-y-2">
                <template x-for="b in bubbles" :key="b.id">
                    <div>
                        {{-- Pesan operator (kanan) --}}
                        <template x-if="b.type === 'operator'">
                            <div class="flex justify-end items-end gap-2 mb-1">
                                <div class="max-w-[78%] bg-blue-600 text-white text-sm rounded-2xl rounded-br-sm px-4 py-2.5 shadow-sm">
                                    <p x-text="b.text" class="leading-relaxed"></p>
                                    <p class="text-blue-200 text-xs mt-1.5 text-right" x-text="b.time"></p>
                                </div>
                                <div class="w-7 h-7 rounded-full bg-blue-700 flex items-center justify-center shrink-0 text-white text-xs font-bold">
                                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                </div>
                            </div>
                        </template>

                        {{-- Balasan admin (kiri) --}}
                        <template x-if="b.type === 'admin'">
                            <div class="flex justify-start items-end gap-2 mb-1">
                                <div class="w-7 h-7 rounded-full bg-slate-500 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div class="max-w-[78%] bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600
                                            text-slate-800 dark:text-white text-sm rounded-2xl rounded-bl-sm px-4 py-2.5 shadow-sm">
                                    <p class="text-xs font-semibold text-blue-600 dark:text-blue-400 mb-1">Admin</p>
                                    <p x-text="b.text" class="leading-relaxed"></p>
                                    <p class="text-slate-400 text-xs mt-1.5" x-text="b.time"></p>
                                </div>
                            </div>
                        </template>

                        {{-- Menunggu balasan --}}
                        <template x-if="b.type === 'pending'">
                            <div class="flex justify-end pr-9 mb-2">
                                <span class="text-xs text-slate-400 italic">Menunggu balasan admin...</span>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- Input --}}
        <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shrink-0">
            <div class="flex gap-3 items-center">
                <input x-model="newMsg"
                       @keydown.enter.prevent="send()"
                       type="text"
                       placeholder="Ketik pesan ke admin..."
                       class="flex-1 px-4 py-2.5 text-sm bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-white
                              rounded-xl border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button @click="send()" :disabled="!newMsg.trim() || sending"
                        class="w-10 h-10 bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white rounded-xl
                               transition-colors flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function chatPage() {
    return {
        messages: [],
        newMsg: '',
        loading: true,
        sending: false,

        // Flat list: setiap message → 1 bubble 'operator' + 1 bubble 'admin'/'pending'
        // Key unik per bubble → Alpine buat/hapus DOM node, bukan toggle x-show
        get bubbles() {
            const list = [];
            for (const msg of this.messages) {
                list.push({
                    id:   'op_' + msg.id,
                    type: 'operator',
                    text: msg.message,
                    time: this.formatTime(msg.created_at),
                });
                if (msg.reply) {
                    list.push({
                        id:   'ad_' + msg.id,
                        type: 'admin',
                        text: msg.reply,
                        time: this.formatTime(msg.replied_at),
                    });
                } else {
                    list.push({
                        id:   'pd_' + msg.id,
                        type: 'pending',
                    });
                }
            }
            return list;
        },

        async load() {
            try {
                const res  = await fetch('{{ route('messages.my') }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                this.messages = await res.json();
                this.$nextTick(() => this.scrollBottom());
            } catch(e) {}
            this.loading = false;
        },

        async send() {
            const msg = this.newMsg.trim();
            if (!msg || this.sending) return;
            this.sending = true;
            this.newMsg  = '';
            try {
                await fetch('{{ route('messages.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ message: msg })
                });
                await this.load();
            } catch(e) {}
            this.sending = false;
        },

        scrollBottom() {
            const el = document.getElementById('chat-thread');
            if (el) el.scrollTop = el.scrollHeight;
        },

        formatTime(ts) {
            if (!ts) return '';
            const d = new Date(ts);
            return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }) + ' ' +
                   d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        },

        init() {
            this.load();
            setInterval(() => this.load(), 5000);
        }
    };
}
</script>
@endpush
