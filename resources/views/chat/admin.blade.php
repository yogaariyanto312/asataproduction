@extends('layouts.app')

@section('title', 'Pesan Masuk')
@section('page-title', 'Pesan Masuk')
@section('page-subtitle', 'Chat dengan operator & supervisor')

@section('content')
<div x-data="adminChatPage()" class="flex flex-col sm:flex-row bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden"
     style="height: calc(100vh - 11rem)">

    {{-- ===== PANEL KIRI: Daftar Operator ===== --}}
    <div x-show="showLeftPanel"
         class="w-full sm:w-80 shrink-0 flex flex-col border-b sm:border-b-0 sm:border-r border-slate-100 dark:border-slate-700"
         style="display: flex">

        {{-- Header kiri --}}
        <div class="px-4 py-4 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-center justify-between mb-3">
                <p class="font-semibold text-slate-800 dark:text-white">Percakapan</p>
                <span x-show="totalUnread > 0"
                      x-text="totalUnread + ' baru'"
                      class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full"></span>
            </div>
            {{-- Search --}}
            <input x-model="search" type="text" placeholder="Cari pengguna..."
                   class="w-full px-3 py-2 text-sm bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-white
                          rounded-xl border border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        {{-- List operator --}}
        <div class="flex-1 overflow-y-auto">
            <div x-show="filteredSenders.length === 0" class="py-12 text-center">
                <p class="text-sm text-slate-400">Belum ada pesan masuk</p>
            </div>

            <template x-for="sender in filteredSenders" :key="sender.id">
                <button @click="selectSender(sender.id)"
                        class="w-full flex items-center gap-3 px-4 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors text-left border-b border-slate-50 dark:border-slate-700/50"
                        :class="selectedId === sender.id ? 'bg-blue-50 dark:bg-blue-900/20 border-r-2 border-r-blue-600' : ''">
                    {{-- Avatar --}}
                    <div class="relative shrink-0">
                        <div class="w-11 h-11 rounded-full bg-blue-600 flex items-center justify-center">
                            <span class="text-sm font-bold text-white" x-text="sender.name.charAt(0).toUpperCase()"></span>
                        </div>
                        <span x-show="sender.unread > 0"
                              x-text="sender.unread"
                              class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center"></span>
                    </div>
                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1">
                            <p class="text-sm font-semibold text-slate-800 dark:text-white truncate" x-text="sender.name"></p>
                            <div class="flex items-center gap-1 shrink-0">
                                <span x-show="sender.role === 'supervisor'"
                                      class="text-xs px-1.5 py-0.5 rounded-full bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300 font-semibold">SPV</span>
                                <p class="text-xs text-slate-400" x-text="sender.lastTime"></p>
                            </div>
                        </div>
                        <p class="text-xs text-slate-400 truncate mt-0.5" x-text="sender.lastMessage"></p>
                        <p x-show="sender.department" class="text-xs text-blue-400 mt-0.5" x-text="sender.department"></p>
                    </div>
                </button>
            </template>
        </div>
    </div>

    {{-- ===== PANEL KANAN: Percakapan ===== --}}
    <div x-show="showRightPanel" class="flex-1 flex flex-col min-w-0">

        {{-- Tidak ada yang dipilih (desktop only, mobile langsung pilih dari list) --}}
        <div x-show="!selectedId" class="flex-1 flex flex-col items-center justify-center text-center p-8">
            <div class="w-20 h-20 bg-slate-100 dark:bg-slate-700 rounded-2xl flex items-center justify-center mb-4">
                <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z"/>
                </svg>
            </div>
            <p class="text-slate-500 dark:text-slate-400 font-medium">Pilih percakapan</p>
            <p class="text-sm text-slate-400 mt-1">Klik nama pengguna di sebelah kiri untuk membuka chat</p>
        </div>

        {{-- Area percakapan --}}
        <template x-if="selectedId">
            <div class="flex flex-col h-full">

                {{-- Header percakapan --}}
                <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800 shrink-0">
                    {{-- Tombol back (mobile only) --}}
                    <button @click="showList = true" class="sm:hidden p-1.5 -ml-1 text-slate-500 hover:text-slate-800 dark:hover:text-white rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
                         :class="activeSender?.role === 'supervisor' ? 'bg-teal-600' : 'bg-blue-600'">
                        <span class="text-sm font-bold text-white" x-text="activeSender?.name.charAt(0).toUpperCase()"></span>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800 dark:text-white" x-text="activeSender?.name"></p>
                        <p class="text-xs text-slate-400"
                           x-text="activeSender?.role === 'supervisor' ? 'Supervisor' + (activeSender?.department ? ' · ' + activeSender.department : '') : (activeSender?.department ?? 'Operator')"></p>
                    </div>
                    <div class="ml-auto flex items-center gap-3">
                        <span class="text-xs text-slate-400" x-text="conversation.length + ' pesan'"></span>
                        <button @click="deleteConversation(selectedId)"
                                title="Hapus semua percakapan"
                                class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Bubble chat --}}
                <div id="admin-chat-thread" class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50 dark:bg-slate-900/30">

                    <template x-for="msg in conversation" :key="msg.id">
                        <div class="space-y-2 group">
                            {{-- Pesan dari operator (kiri) --}}
                            <div class="flex justify-start items-end gap-2">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0"
                                     :class="activeSender?.role === 'supervisor' ? 'bg-teal-600' : 'bg-blue-600'">
                                    <span class="text-xs font-bold text-white" x-text="activeSender?.name.charAt(0).toUpperCase()"></span>
                                </div>
                                <div class="max-w-[70%] bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600
                                            text-slate-800 dark:text-white text-sm rounded-2xl rounded-bl-sm px-4 py-2.5 shadow-sm">
                                    <p x-text="msg.message" class="leading-relaxed"></p>
                                    <p class="text-slate-400 text-xs mt-1.5" x-text="formatTime(msg.created_at)"></p>
                                </div>
                                <button @click="deleteMsg(msg.id)"
                                        title="Hapus pesan"
                                        class="opacity-0 group-hover:opacity-100 transition-opacity p-1 text-slate-300 hover:text-red-500 rounded-lg shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>

                            {{-- Balasan admin (kanan) --}}
                            <div x-show="msg.reply" class="flex justify-end items-end gap-2">
                                <div class="max-w-[70%] bg-blue-600 text-white text-sm rounded-2xl rounded-br-sm px-4 py-2.5 shadow-sm">
                                    <p x-text="msg.reply" class="leading-relaxed"></p>
                                    <p class="text-blue-200 text-xs mt-1.5 text-right" x-text="formatTime(msg.replied_at)"></p>
                                </div>
                                <div class="w-7 h-7 rounded-full bg-slate-600 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                            </div>

                            {{-- Status belum dibalas --}}
                            <div x-show="!msg.reply" class="flex justify-end">
                                <span class="text-xs text-slate-400 italic mr-9">Belum dibalas</span>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Input balas --}}
                <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shrink-0">
                    <div class="flex gap-3 items-end">
                        <textarea x-model="replyText"
                                  @keydown.enter.prevent="if(!$event.shiftKey) sendReply()"
                                  rows="1"
                                  placeholder="Tulis balasan... (Enter untuk kirim)"
                                  class="flex-1 px-4 py-2.5 text-sm bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-white
                                         rounded-xl border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                        <button @click="sendReply()"
                                :disabled="!replyText.trim() || sending"
                                class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white rounded-xl
                                       transition-colors flex items-center gap-2 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            <span x-text="sending ? '...' : 'Kirim'" class="text-sm font-semibold"></span>
                        </button>
                    </div>
                    <p class="text-xs text-slate-400 mt-1.5">Shift+Enter untuk baris baru · membalas pesan terakhir yang belum dibalas</p>
                </div>
            </div>
        </template>
    </div>
</div>
@endsection

@push('scripts')
<script>
function adminChatPage() {
    return {
        messages: @json($messages),
        selectedId: null,
        search: '',
        replyText: '',
        sending: false,
        isMobile: window.innerWidth < 640,
        showList: true,

        get showLeftPanel()  { return !this.isMobile || this.showList; },
        get showRightPanel() { return !this.isMobile || !this.showList; },

        get senders() {
            const map = {};
            this.messages.forEach(msg => {
                if (!msg.sender) return;
                const sid = msg.sender.id;
                if (!map[sid]) {
                    map[sid] = {
                        id: sid,
                        name: msg.sender.name,
                        department: msg.sender.department ?? '',
                        role: msg.sender.role ?? 'operator',
                        messages: [],
                        unread: 0,
                        lastTime: '',
                        lastMessage: '',
                    };
                }
                map[sid].messages.push(msg);
                if (!msg.is_read) map[sid].unread++;
            });
            return Object.values(map).map(s => {
                const last = s.messages[0];
                s.lastMessage = last?.reply
                    ? ('Anda: ' + last.reply.substring(0, 40))
                    : (last?.message.substring(0, 40) ?? '');
                s.lastTime = this.formatTimeShort(last?.created_at);
                return s;
            });
        },

        get filteredSenders() {
            const q = this.search.toLowerCase();
            return this.senders.filter(s =>
                !q || s.name.toLowerCase().includes(q) || (s.department ?? '').toLowerCase().includes(q)
            );
        },

        get activeSender() {
            return this.senders.find(s => s.id === this.selectedId) ?? null;
        },

        get conversation() {
            return this.messages
                .filter(m => m.sender?.id === this.selectedId)
                .slice()
                .reverse();
        },

        get totalUnread() {
            return this.messages.filter(m => !m.is_read).length;
        },

        selectSender(id) {
            this.selectedId = id;
            if (this.isMobile) this.showList = false;
            this.$nextTick(() => this.scrollBottom());
            const unread = this.messages.filter(m => m.sender?.id === id && !m.is_read);
            unread.forEach(m => this.markRead(m.id));
        },

        async markRead(id) {
            try {
                await fetch(`/messages/${id}/read`, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const msg = this.messages.find(m => m.id === id);
                if (msg) msg.is_read = true;
            } catch(e) {}
        },

        async sendReply() {
            const text = this.replyText.trim();
            if (!text || this.sending) return;

            // Cari pesan terakhir yang belum dibalas dari sender ini
            const unreplied = this.messages.filter(m => m.sender?.id === this.selectedId && !m.reply);
            if (!unreplied.length) return;
            const target = unreplied[0]; // ordered by created_at desc, jadi ini yang terbaru

            this.sending = true;
            this.replyText = '';
            try {
                await fetch(`/messages/${target.id}/reply`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ reply: text })
                });
                await this.load();
                this.$nextTick(() => this.scrollBottom());
            } catch(e) {}
            this.sending = false;
        },

        async deleteMsg(id) {
            const ok = await window.appConfirmAsync('Pesan ini akan dihapus permanen.', { title: 'Hapus Pesan?' });
            if (!ok) return;
            try {
                await fetch(`/messages/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                this.messages = this.messages.filter(m => m.id !== id);
            } catch(e) {}
        },

        async deleteConversation(senderId) {
            const ok = await window.appConfirmAsync('Semua pesan dari operator ini akan dihapus permanen dan tidak bisa dikembalikan.', { title: 'Hapus Semua Pesan?' });
            if (!ok) return;
            try {
                await fetch(`/messages/conversation/${senderId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                this.messages = this.messages.filter(m => m.sender?.id !== senderId);
                this.selectedId = null;
            } catch(e) {}
        },

        async load() {
            try {
                const res = await fetch('{{ route('messages.index') }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                this.messages = await res.json();
            } catch(e) {}
        },

        scrollBottom() {
            const el = document.getElementById('admin-chat-thread');
            if (el) el.scrollTop = el.scrollHeight;
        },

        formatTime(ts) {
            if (!ts) return '';
            const d = new Date(ts);
            return d.toLocaleDateString('id-ID', { day:'2-digit', month:'short' }) + ' ' +
                   d.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' });
        },

        formatTimeShort(ts) {
            if (!ts) return '';
            const d = new Date(ts);
            const now = new Date();
            if (d.toDateString() === now.toDateString()) {
                return d.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' });
            }
            return d.toLocaleDateString('id-ID', { day:'2-digit', month:'short' });
        },

        init() {
            window.addEventListener('resize', () => {
                this.isMobile = window.innerWidth < 640;
                if (!this.isMobile) this.showList = true;
            });
            setInterval(() => this.load(), 15000);
        }
    };
}
</script>
@endpush
