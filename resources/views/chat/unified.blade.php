@extends('layouts.app')
@section('title', 'Chatting')
@section('page-title', 'Chatting')
@section('page-subtitle', 'Pesan & percakapan')
@section('main-class', 'flex-1 flex overflow-hidden min-h-0')

@section('content')
<div x-data="chatApp()" class="flex flex-1 min-h-0 overflow-hidden bg-slate-900 dark:bg-slate-950">

    {{-- ===== SIDEBAR KIRI ===== --}}
    <div class="flex flex-col shrink-0 border-r border-slate-700 bg-slate-900 w-full lg:w-72"
         x-show="!activeConv || !isMobile">

        {{-- Header + Search --}}
        <div class="shrink-0 px-4 pt-4 pb-3 border-b border-slate-700">
            <h2 class="text-white font-bold text-base mb-3">Chatting</h2>
            <div class="relative">
                <input x-model="searchQuery" type="text" placeholder="Cari percakapan..."
                       class="w-full pl-9 pr-4 py-2 bg-slate-800 text-slate-100 text-sm rounded-xl
                              border border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500
                              placeholder-slate-500">
                <svg class="absolute left-3 top-2.5 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </div>

        {{-- Loading --}}
        <div x-show="loading" class="flex-1 flex items-center justify-center">
            <svg class="w-7 h-7 text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
        </div>

        {{-- Conversation list --}}
        <div x-show="!loading" class="flex-1 overflow-y-auto min-h-0">

            <div x-show="filteredConversations.length === 0" class="p-6 text-center">
                <p class="text-slate-500 text-sm">Tidak ada percakapan</p>
            </div>

            <template x-for="(conv, idx) in filteredConversations" :key="conv.type + '_' + conv.sender.id">
                <div>
                    {{-- Item percakapan --}}
                    <button @click="selectConv(conv)" type="button"
                            :class="isActive(conv)
                                ? 'bg-slate-700/60 border-l-[3px] border-blue-500'
                                : 'hover:bg-slate-800/70 border-l-[3px] border-transparent'"
                            class="w-full flex items-center gap-3 px-4 py-3 transition-all text-left">

                        {{-- Avatar + unread badge --}}
                        <div class="relative shrink-0">
                            <div :class="conv.sender.avatar ? '' : avatarBg(conv.sender.role)"
                                 class="w-11 h-11 rounded-full flex items-center justify-center text-white font-bold text-sm overflow-hidden shrink-0">
                                <template x-if="conv.sender.avatar">
                                    <img :src="avatarUrl(conv.sender.avatar)" class="w-full h-full object-cover"
                                         x-on:error="conv.sender.avatar = null">
                                </template>
                                <template x-if="!conv.sender.avatar">
                                    <span x-text="initial(conv.sender.name)"></span>
                                </template>
                            </div>
                            <span x-show="conv.unread > 0"
                                  x-text="conv.unread > 9 ? '9+' : conv.unread"
                                  class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] bg-green-500 text-white
                                         text-[10px] font-bold rounded-full flex items-center justify-center px-0.5">
                            </span>
                        </div>

                        {{-- Nama + preview --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline justify-between gap-1">
                                <span :class="conv.unread > 0 ? 'text-white font-semibold' : 'text-slate-200 font-medium'"
                                      class="text-sm truncate" x-text="conv.sender.name"></span>
                                <span class="text-[10px] text-slate-500 shrink-0" x-text="lastTime(conv)"></span>
                            </div>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="text-xs text-slate-400 truncate flex-1" x-text="lastMsg(conv)"></span>
                                <span x-show="conv.sender.role === 'developer'"
                                      class="shrink-0 text-[9px] bg-slate-700 text-slate-300 px-1.5 py-0.5 rounded font-bold">DEV</span>
                                <span x-show="conv.sender.role === 'supervisor'"
                                      class="shrink-0 text-[9px] bg-teal-900/60 text-teal-400 px-1.5 py-0.5 rounded font-bold">SPV</span>
                                <span x-show="conv.sender.role === 'admin'"
                                      class="shrink-0 text-[9px] bg-purple-900/60 text-purple-400 px-1.5 py-0.5 rounded font-bold">ADM</span>
                                <span x-show="conv.sender.role === 'mandor'"
                                      class="shrink-0 text-[9px] bg-orange-900/60 text-orange-400 px-1.5 py-0.5 rounded font-bold">MDR</span>
                                <span x-show="conv.sender.role === 'operator'"
                                      class="shrink-0 text-[9px] bg-blue-900/60 text-blue-400 px-1.5 py-0.5 rounded font-bold">QC</span>
                                <span x-show="conv.sender.role === 'visitor'"
                                      class="shrink-0 text-[9px] bg-slate-800 text-slate-500 px-1.5 py-0.5 rounded font-bold">VST</span>
                            </div>
                        </div>
                    </button>
                </div>
            </template>
        </div>
    </div>

    {{-- ===== PANEL KANAN ===== --}}
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden"
         x-show="!!activeConv || !isMobile">

        {{-- Empty state --}}
        <div x-show="!activeConv"
             class="flex-1 flex flex-col items-center justify-center text-center bg-slate-900/50 select-none">
            <div class="w-20 h-20 bg-slate-800 rounded-3xl flex items-center justify-center mb-4">
                <svg class="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z"/>
                </svg>
            </div>
            <p class="text-slate-400 font-medium">Pilih percakapan</p>
            <p class="text-slate-600 text-sm mt-1">Klik nama di sebelah kiri untuk mulai</p>
        </div>

        {{-- Conversation aktif --}}
        <div x-show="activeConv" class="flex-1 flex flex-col min-h-0 overflow-hidden">

            {{-- Header percakapan --}}
            <div class="shrink-0 px-3 lg:px-5 py-3 bg-slate-800 border-b border-slate-700 flex items-center gap-2 lg:gap-3">
                {{-- Tombol back (mobile only) --}}
                <button @click="activeConv = null" type="button"
                        class="lg:hidden p-2 -ml-1 text-slate-400 hover:text-white rounded-lg hover:bg-slate-700 transition-colors shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                <div x-show="activeConv"
                     :class="(activeConv && activeConv.sender.avatar) ? '' : (activeConv ? avatarBg(activeConv.sender.role) : 'bg-slate-600')"
                     class="w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-sm shrink-0 overflow-hidden">
                    <template x-if="activeConv && activeConv.sender.avatar">
                        <img :src="avatarUrl(activeConv.sender.avatar)" class="w-full h-full object-cover"
                             x-on:error="activeConv.sender.avatar = null">
                    </template>
                    <template x-if="!(activeConv && activeConv.sender.avatar)">
                        <span x-text="activeConv ? initial(activeConv.sender.name) : ''"></span>
                    </template>
                </div>
                <div class="flex-1 min-w-0" x-show="activeConv">
                    <p class="text-sm font-semibold text-white truncate" x-text="activeConv?.sender.name"></p>
                    <p class="text-xs text-slate-400 truncate"
                       x-text="activeConv?.sender.department
                           ? activeConv.sender.department + ' · ' + roleLabel(activeConv.sender.role)
                           : roleLabel(activeConv?.sender.role)"></p>
                    <p class="text-xs truncate"
                       :class="lastSeen(activeConv?.sender) === 'Online' ? 'text-green-400' : 'text-slate-500'"
                       x-text="lastSeen(activeConv?.sender)"></p>
                </div>

                {{-- Tombol opsi (semua user) --}}
                <div x-show="activeConv"
                     x-data="{ dopen: false }" class="relative shrink-0">
                    <button @click="dopen = !dopen" type="button"
                            class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-700 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                        </svg>
                    </button>
                    <div x-show="dopen" @click.outside="dopen = false" x-cloak
                         class="absolute right-0 top-full mt-1 bg-slate-800 border border-slate-700 rounded-xl shadow-xl z-50 min-w-[180px] overflow-hidden">
                        <button @click="deleteConv(); dopen = false" type="button"
                                class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-400 hover:bg-slate-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Hapus Percakapan
                        </button>
                    </div>
                </div>
            </div>

            {{-- Area pesan --}}
            <div x-ref="msgArea"
                 class="flex-1 overflow-y-auto min-h-0 p-4 space-y-2"
                 style="background-color: rgb(15 23 42 / 0.6)">

                {{-- Empty --}}
                <div x-show="activeTimeline.length === 0"
                     class="flex flex-col items-center justify-center h-full text-center py-10 select-none">
                    <div class="w-14 h-14 bg-slate-800 rounded-2xl flex items-center justify-center mb-3">
                        <svg class="w-7 h-7 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z"/>
                        </svg>
                    </div>
                    <p class="text-slate-500 text-sm"
                       x-text="activeConv && activeConv.type === 'inbox' ? 'Belum ada pesan dari pengguna ini' : 'Belum ada pesan'"></p>
                    <p x-show="activeConv && activeConv.type === 'own'"
                       class="text-slate-600 text-xs mt-1">Mulai percakapan di bawah</p>
                </div>

                {{-- Bubble messages --}}
                <template x-for="item in activeTimeline" :key="item.id">
                    <div class="flex items-end gap-2" :class="item.isSent ? 'flex-row-reverse' : ''">

                        {{-- Avatar penerima (kiri) --}}
                        <template x-if="!item.isSent && activeConv">
                            <div :class="(item.isReply ? userAvatar : (activeConv.sender.avatar)) ? '' : (item.isReply ? avatarBg(userRole) : avatarBg(activeConv.type === 'own' ? 'admin' : activeConv.sender.role))"
                                 class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 text-white text-[11px] font-bold mb-1 overflow-hidden">
                                <template x-if="item.isReply && userAvatar">
                                    <img :src="avatarUrl(userAvatar)" class="w-full h-full object-cover"
                                         x-on:error="userAvatar = ''">
                                </template>
                                <template x-if="!item.isReply && activeConv.sender.avatar">
                                    <img :src="avatarUrl(activeConv.sender.avatar)" class="w-full h-full object-cover"
                                         x-on:error="activeConv.sender.avatar = null">
                                </template>
                                <template x-if="(item.isReply && !userAvatar) || (!item.isReply && !activeConv.sender.avatar)">
                                    <span x-text="item.isReply ? userInitial : initial(activeConv.type === 'own' ? 'Admin' : activeConv.sender.name)"></span>
                                </template>
                            </div>
                        </template>

                        {{-- Bubble --}}
                        <div class="flex flex-col max-w-[68%]" :class="item.isSent ? 'items-end' : 'items-start'">
                            <div :class="item.isSent
                                    ? 'bg-blue-600 text-white rounded-br-sm'
                                    : 'bg-slate-700 text-slate-100 rounded-bl-sm'"
                                 class="rounded-2xl px-4 py-2.5 shadow-sm">
                                <p x-text="item.text" class="text-sm leading-relaxed whitespace-pre-wrap break-words"></p>
                            </div>
                            <div class="flex items-center gap-1 mt-1 px-1" :class="item.isSent ? 'flex-row-reverse' : ''">
                                <span class="text-[10px] text-slate-500" x-text="fmt(item.time)"></span>
                                <template x-if="item.isPending && item.isSent">
                                    <span class="text-[10px] text-amber-500/70">· menunggu balasan</span>
                                </template>
                                <template x-if="item.isSent">
                                    <span :class="item.isRead ? 'text-blue-400' : 'text-slate-500'"
                                          class="flex items-center shrink-0 -space-x-1.5">
                                        <svg class="w-3 h-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="1,6 4,9 11,2"/>
                                        </svg>
                                        <svg class="w-3 h-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="1,6 4,9 11,2"/>
                                        </svg>
                                    </span>
                                </template>
                            </div>
                        </div>

                        {{-- Avatar pengirim (kanan) --}}
                        <template x-if="item.isSent">
                            <div :class="userAvatar ? '' : 'bg-blue-700'"
                                 class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 text-white text-[11px] font-bold mb-1 overflow-hidden">
                                <template x-if="userAvatar">
                                    <img :src="avatarUrl(userAvatar)" class="w-full h-full object-cover"
                                         x-on:error="userAvatar = ''">
                                </template>
                                <template x-if="!userAvatar">
                                    <span x-text="userInitial"></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Typing indicator bubble --}}
                <template x-if="activeConv && activeConv.sender.is_typing">
                    <div class="flex items-end gap-2">
                        <div :class="activeConv.sender.avatar ? '' : avatarBg(activeConv.sender.role)"
                             class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 text-white text-[11px] font-bold mb-1 overflow-hidden">
                            <template x-if="activeConv.sender.avatar">
                                <img :src="avatarUrl(activeConv.sender.avatar)" class="w-full h-full object-cover"
                                     x-on:error="activeConv.sender.avatar = null">
                            </template>
                            <template x-if="!activeConv.sender.avatar">
                                <span x-text="initial(activeConv.sender.name)"></span>
                            </template>
                        </div>
                        <div class="bg-slate-700 rounded-2xl rounded-bl-sm px-4 py-3 flex items-center gap-1.5">
                            <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                            <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 160ms"></span>
                            <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 320ms"></span>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Input --}}
            <div class="shrink-0 px-4 py-3 bg-slate-800 border-t border-slate-700">
                <div class="flex items-center gap-2">
                    <input x-model="newMessage"
                           @keydown.enter.prevent="send()"
                           @input="notifyTyping()"
                           :disabled="!canSend"
                           :placeholder="inputPlaceholder"
                           type="text"
                           class="flex-1 px-4 py-2.5 text-sm bg-slate-900 text-white rounded-xl
                                  border border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500
                                  placeholder-slate-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <button @click="send()"
                            :disabled="!newMessage.trim() || sending || !canSend"
                            type="button"
                            class="w-10 h-10 bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition-colors
                                   flex items-center justify-center shrink-0 disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg x-show="!sending" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        <svg x-show="sending" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
const BASE = window.APP_BASE;

function chatApp() {
    return {
        userRole: '{{ auth()->user()->role }}',
        userId: {{ auth()->id() }},
        userInitial: '{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}',
        userAvatar: '{{ auth()->user()->avatarUrl() ?? '' }}',
        storageBase: '{{ rtrim(config('app.url'), '/') }}/storage',

        conversations: [],
        activeConv: null,
        newMessage: '',
        searchQuery: '',
        loading: true,
        sending: false,
        isMobile: window.innerWidth < 1024,
        typingTimeout: null,
        lastMessageId: 0,
        sinceTimer: null,
        fullTimer: null,
        pingTimer: null,
        _visibilityHandler: null,
        _resizeHandler: null,

        get isPrivileged() {
            return this.userRole === 'developer' || this.userRole === 'admin' || this.userRole === 'supervisor';
        },

        get filteredConversations() {
            if (!this.searchQuery.trim()) return this.conversations;
            const q = this.searchQuery.toLowerCase();
            return this.conversations.filter(c => c.sender.name.toLowerCase().includes(q));
        },

        get activeTimeline() {
            if (!this.activeConv) return [];
            const timeline = [];
            const msgs = [...this.activeConv.messages].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
            msgs.forEach(msg => {
                const iSent = msg.sender_id === this.userId;
                timeline.push({
                    id: 'msg_' + msg.id,
                    text: msg.message,
                    time: msg.created_at,
                    isSent: iSent,
                    isPending: iSent && !msg.reply && !msg.recipient_id,
                    isRead: !!msg.is_read,
                    isReply: false,
                });
                if (msg.reply) {
                    timeline.push({
                        id: 'rep_' + msg.id,
                        text: msg.reply,
                        time: msg.replied_at,
                        isSent: !iSent,
                        isPending: false,
                        isRead: true,
                        isReply: true,
                    });
                }
            });
            return timeline.sort((a, b) => new Date(a.time) - new Date(b.time));
        },

        get lastUnrepliedId() {
            if (!this.activeConv || this.activeConv.type === 'own') return null;
            const unreplied = this.activeConv.messages.filter(m => !m.reply && m.sender_id !== this.userId);
            return unreplied.length ? unreplied[unreplied.length - 1].id : null;
        },

        get canSend() {
            return !!this.activeConv;
        },

        get inputPlaceholder() {
            if (!this.activeConv) return 'Pilih percakapan...';
            return 'Ketik pesan...';
        },

        isActive(conv) {
            return this.activeConv
                && this.activeConv.type === conv.type
                && this.activeConv.sender.id === conv.sender.id;
        },

        async init() {
            this._resizeHandler = () => { this.isMobile = window.innerWidth < 1024; };
            window.addEventListener('resize', this._resizeHandler);

            await this.loadAll();
            this.sinceTimer = setInterval(() => this.pollSince(), 5000);
            this.fullTimer  = setInterval(() => this.loadAll(), 60000);
            this.pingServer();
            this.pingTimer = setInterval(() => this.pingServer(), 60000);

            // Hentikan polling saat tab tidak aktif
            this._visibilityHandler = () => {
                if (document.hidden) {
                    clearInterval(this.sinceTimer);
                    clearInterval(this.fullTimer);
                } else {
                    this.sinceTimer = setInterval(() => this.pollSince(), 5000);
                    this.fullTimer  = setInterval(() => this.loadAll(), 60000);
                    this.pollSince();
                }
            };
            document.addEventListener('visibilitychange', this._visibilityHandler);
        },

        destroy() {
            clearInterval(this.sinceTimer);
            clearInterval(this.fullTimer);
            clearInterval(this.pingTimer);
            if (this._visibilityHandler) document.removeEventListener('visibilitychange', this._visibilityHandler);
            if (this._resizeHandler) window.removeEventListener('resize', this._resizeHandler);
        },

        // Smart polling: hanya ambil pesan baru
        async pollSince() {
            try {
                const res  = await fetch(`${BASE}/api/messages/since?last_id=${this.lastMessageId}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (!data.has_new) return; // tidak ada pesan baru — skip

                // Ada pesan baru → merge ke conversations yang sudah ada
                data.messages.forEach(msg => {
                    if (msg.id > this.lastMessageId) this.lastMessageId = msg.id;
                    this.mergeMessage(msg);
                });

                // Sort conversations by latest message
                this.conversations.sort((a, b) => this.latestMs(b) - this.latestMs(a));

                // Auto-scroll jika active conversation dapat pesan baru
                if (this.activeConv) this.$nextTick(() => this.scrollToBottom());
            } catch (e) {}
        },

        // Merge satu pesan baru ke dalam conversations yang sudah ada
        mergeMessage(msg) {
            const partnerId = msg.sender_id === this.userId ? msg.recipient_id : msg.sender_id;
            if (!partnerId && msg.sender_id !== this.userId) return;

            const conv = this.conversations.find(c =>
                (c.sender.id === partnerId) ||
                (partnerId === null && c.sender.id === msg.sender_id)
            );

            if (conv) {
                const exists = conv.messages.find(m => m.id === msg.id);
                if (!exists) {
                    conv.messages.push(msg);
                    if (!msg.is_read && msg.sender_id !== this.userId) conv.unread++;
                } else {
                    // Update existing message (e.g., reply added)
                    Object.assign(exists, msg);
                }
            } else {
                // Conversation baru — trigger full load
                this.loadAll();
            }
        },

        notifyTyping() {
            if (!this.activeConv || !this.activeConv.sender.id) return;
            if (this.typingTimeout) return; // throttle: max 1 ping per 3 detik
            const token = document.querySelector('meta[name=csrf-token]').content;
            fetch(BASE + '/api/typing', {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ recipient_id: this.activeConv.sender.id }),
            }).catch(() => {});
            this.typingTimeout = setTimeout(() => { this.typingTimeout = null; }, 3000);
        },

        async pingServer() {
            try {
                const token = document.querySelector('meta[name=csrf-token]').content;
                await fetch(BASE + '/api/ping', {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                });
            } catch (e) {}
        },

        async loadAll() {
            try {
                if (this.userRole === 'developer' || this.userRole === 'admin') await this.loadInbox();
                else if (this.userRole === 'supervisor')                        await this.loadSupervisor();
                else                                                            await this.loadOwn();

                // Update lastMessageId dari semua pesan yang sudah ada
                this.conversations.forEach(c => {
                    c.messages.forEach(m => {
                        if (m.id > this.lastMessageId) this.lastMessageId = m.id;
                    });
                });
            } catch (e) {}
            this.loading = false;
        },

        async loadInbox() {
            const [msgRes, ctRes] = await Promise.all([
                fetch(BASE + '/messages',          { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
                fetch(BASE + '/api/chat-contacts', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
            ]);
            const msgData      = await msgRes.json();
            const contactsData = ctRes.ok ? await ctRes.json() : [];

            const map = {};
            msgData.forEach(msg => {
                // Tentukan siapa "lawan bicara" — bukan diri sendiri
                const partnerId   = msg.sender_id === this.userId ? msg.recipient_id : msg.sender_id;
                const partnerInfo = msg.sender_id === this.userId ? (msg.recipient || msg.sender) : msg.sender;
                if (!partnerId) return; // abaikan pesan lama tanpa recipient yang kita kirim
                if (!map[partnerId]) map[partnerId] = { sender: partnerInfo, messages: [], unread: 0, type: 'inbox' };
                map[partnerId].messages.push(msg);
                if (!msg.is_read && msg.sender_id !== this.userId) map[partnerId].unread++;
            });
            // Kontak tanpa pesan tetap muncul di list; yang sudah ada di-refresh datanya
            contactsData.forEach(c => {
                if (!map[c.id]) map[c.id] = { sender: c, messages: [], unread: 0, type: 'inbox' };
                else Object.assign(map[c.id].sender, c);
            });

            const withMsg = Object.values(map).filter(c => c.messages.length).sort((a, b) => this.latestMs(b) - this.latestMs(a));
            const noMsg   = Object.values(map).filter(c => !c.messages.length).sort((a, b) => a.sender.name.localeCompare(b.sender.name));
            this.syncConversations([...withMsg, ...noMsg]);
        },

        async loadSupervisor() {
            const [ownRes, inboxRes, ctRes] = await Promise.all([
                fetch(BASE + '/messages/my', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
                fetch(BASE + '/messages',   { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
                fetch(BASE + '/api/chat-contacts', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
            ]);
            const ownData      = await ownRes.json();
            const inboxData    = await inboxRes.json();
            const contactsData = ctRes.ok ? await ctRes.json() : [];

            // Chat Saya: kelompokkan pesan berdasarkan lawan bicara (admin)
            const ownMap = {};
            ownData.forEach(msg => {
                const partnerId   = msg.sender_id === this.userId ? msg.recipient_id : msg.sender_id;
                const partnerInfo = msg.sender_id === this.userId ? (msg.recipient || null) : msg.sender;
                const key = partnerId || 0;
                if (!ownMap[key]) ownMap[key] = { sender: partnerInfo || { id: 0, name: 'Admin', role: 'admin', department: '' }, messages: [], unread: 0, type: 'own' };
                ownMap[key].messages.push(msg);
            });
            contactsData.filter(c => c.role === 'admin').forEach(c => {
                if (!ownMap[c.id]) ownMap[c.id] = { sender: c, messages: [], unread: 0, type: 'own' };
                else Object.assign(ownMap[c.id].sender, c);
            });
            const ownConvs = Object.values(ownMap).length
                ? Object.values(ownMap)
                : [{ sender: { id: 0, name: 'Admin', role: 'admin', department: '' }, messages: [], unread: 0, type: 'own' }];

            // Pesan Masuk: operator & supervisor lain
            const map = {};
            inboxData.forEach(msg => {
                const partnerId   = msg.sender_id === this.userId ? msg.recipient_id : msg.sender_id;
                const partnerInfo = msg.sender_id === this.userId ? (msg.recipient || msg.sender) : msg.sender;
                if (!partnerId || partnerId === this.userId) return;
                if (!map[partnerId]) map[partnerId] = { sender: partnerInfo, messages: [], unread: 0, type: 'inbox' };
                map[partnerId].messages.push(msg);
                if (!msg.is_read && msg.sender_id !== this.userId) map[partnerId].unread++;
            });
            contactsData.filter(c => c.role !== 'admin').forEach(c => {
                if (!map[c.id]) map[c.id] = { sender: c, messages: [], unread: 0, type: 'inbox' };
                else Object.assign(map[c.id].sender, c);
            });

            // Gabung semua kontak, urutkan berdasarkan waktu pesan terakhir
            const all = [...Object.values(ownMap), ...Object.values(map)];
            const withMsg = all.filter(c => c.messages.length).sort((a, b) => this.latestMs(b) - this.latestMs(a));
            const noMsg   = all.filter(c => !c.messages.length).sort((a, b) => a.sender.name.localeCompare(b.sender.name));
            this.syncConversations([...withMsg, ...noMsg]);
        },

        async loadOwn() {
            const [msgRes, ctRes] = await Promise.all([
                fetch(BASE + '/messages/my', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
                fetch(BASE + '/api/chat-contacts', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
            ]);
            const msgData      = await msgRes.json();
            const contactsData = ctRes.ok ? await ctRes.json() : [];

            // Kelompokkan pesan per kontak berdasarkan lawan bicara
            const map = {};
            msgData.forEach(msg => {
                const partnerId   = msg.sender_id === this.userId ? msg.recipient_id : msg.sender_id;
                const partnerInfo = msg.sender_id === this.userId ? (msg.recipient || null) : msg.sender;
                const key = partnerId || 0;
                if (!map[key]) map[key] = { sender: partnerInfo || { id: 0, name: 'Admin', role: 'admin', department: '' }, messages: [], unread: 0, type: 'own' };
                map[key].messages.push(msg);
                if (!msg.is_read && msg.sender_id !== this.userId) map[key].unread++;
            });

            // Kontak dari API yang belum ada percakapan tetap muncul; yang ada di-refresh datanya
            contactsData.forEach(c => {
                if (!map[c.id]) map[c.id] = { sender: c, messages: [], unread: 0, type: 'own' };
                else Object.assign(map[c.id].sender, c);
            });

            const convs = Object.values(map).length
                ? Object.values(map)
                : [{ sender: { id: 0, name: 'Admin', role: 'admin', department: '' }, messages: [], unread: 0, type: 'own' }];

            this.syncConversations(convs);
        },

        syncConversations(newConvs) {
            this.conversations = newConvs;
            if (this.activeConv) {
                const updated = newConvs.find(c => c.type === this.activeConv.type && c.sender.id === this.activeConv.sender.id);
                if (updated) {
                    const wasBottom = this.isAtBottom();
                    this.activeConv = updated;
                    if (wasBottom) this.$nextTick(() => this.scrollBottom());
                }
            }
        },

        selectConv(conv) {
            this.activeConv = conv;
            // Tandai semua pesan masuk dari kontak ini sebagai dibaca (semua role, dua arah).
            if (conv.unread > 0 && conv.sender.id > 0) {
                const token = document.querySelector('meta[name=csrf-token]').content;
                fetch(`${BASE}/api/messages/read-conversation/${conv.sender.id}`, {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                }).catch(() => {});
                conv.messages.forEach(m => { if (m.sender_id !== this.userId) m.is_read = true; });
                conv.unread = 0;
            }
            this.$nextTick(() => this.scrollBottom());
        },

        async send() {
            const text = this.newMessage.trim();
            if (!text || this.sending || !this.canSend) return;
            this.sending = true;
            const token   = document.querySelector('meta[name=csrf-token]').content;
            const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' };
            try {
                if (this.activeConv.type === 'own') {
                    // operator/supervisor: kirim ke kontak yang dipilih
                    const recipientId = this.activeConv.sender.id > 0 ? this.activeConv.sender.id : null;
                    await fetch(BASE + '/messages', { method: 'POST', headers, body: JSON.stringify({ message: text, recipient_id: recipientId }) });
                } else if (this.lastUnrepliedId) {
                    // admin/supervisor: balas pesan lama yang belum dibalas
                    await fetch(`${BASE}/messages/${this.lastUnrepliedId}/reply`, { method: 'POST', headers, body: JSON.stringify({ reply: text }) });
                } else {
                    // admin/supervisor: inisiasi pesan baru ke kontak
                    await fetch(BASE + '/messages', { method: 'POST', headers, body: JSON.stringify({ message: text, recipient_id: this.activeConv.sender.id }) });
                }
                this.newMessage = '';
                await this.loadAll();
                this.$nextTick(() => this.scrollBottom());
            } catch (e) {}
            this.sending = false;
        },

        async deleteConv() {
            const ok = await window.appConfirmAsync(`Semua percakapan dengan ${this.activeConv.sender.name} akan dihapus permanen.`, { title: 'Hapus Percakapan?' });
            if (!ok) return;
            const token = document.querySelector('meta[name=csrf-token]').content;
            await fetch(`${BASE}/messages/conversation/${this.activeConv.sender.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            });
            this.conversations = this.conversations.filter(c => !(c.type === this.activeConv.type && c.sender.id === this.activeConv.sender.id));
            this.activeConv = null;
        },

        isAtBottom() {
            const el = this.$refs.msgArea;
            return el ? (el.scrollHeight - el.scrollTop - el.clientHeight < 60) : true;
        },

        scrollBottom() {
            const el = this.$refs.msgArea;
            if (el) el.scrollTop = el.scrollHeight;
        },

        latestMs(conv) {
            return conv.messages.length ? Math.max(...conv.messages.map(m => new Date(m.created_at).getTime())) : 0;
        },

        fmt(ts) {
            if (!ts) return '';
            const d     = new Date(ts);
            const today = new Date();
            if (d.toDateString() === today.toDateString())
                return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) + ' ' +
                   d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        },

        lastMsg(conv) {
            if (!conv.messages.length) return 'Belum ada pesan';
            const m    = [...conv.messages].sort((a, b) => new Date(b.created_at) - new Date(a.created_at))[0];
            const text = m.reply || m.message;
            return text.length > 38 ? text.slice(0, 38) + '…' : text;
        },

        lastTime(conv) {
            if (!conv.messages.length) return '';
            const m = [...conv.messages].sort((a, b) => new Date(b.created_at) - new Date(a.created_at))[0];
            return this.fmt(m.created_at);
        },

        avatarUrl(path) {
            if (!path) return '';
            if (path.startsWith('http')) return path;
            return this.storageBase + '/' + path;
        },

        initial(name) { return (name || '?').charAt(0).toUpperCase(); },

        avatarBg(role) {
            if (role === 'developer')  return 'bg-slate-600';
            if (role === 'admin')      return 'bg-purple-600';
            if (role === 'supervisor') return 'bg-teal-600';
            if (role === 'mandor')     return 'bg-orange-600';
            if (role === 'visitor')    return 'bg-slate-500';
            return 'bg-blue-600';
        },

        roleLabel(role) {
            return { developer: 'Developer', admin: 'Admin', supervisor: 'Supervisor', mandor: 'Mandor', operator: 'Operator', visitor: 'Visitor' }[role] ?? (role ?? 'Operator');
        },

        lastSeen(sender) {
            if (!sender || !sender.last_seen_at) return '';
            const diffMin = Math.floor((Date.now() - new Date(sender.last_seen_at)) / 60000);
            if (diffMin < 2)  return 'Online';
            if (diffMin < 60) return `terakhir dilihat ${diffMin} menit lalu`;
            const diffHr = Math.floor(diffMin / 60);
            if (diffHr < 24)  return `terakhir dilihat ${diffHr} jam lalu`;
            return `terakhir dilihat ${Math.floor(diffHr / 24)} hari lalu`;
        },
    };
}
</script>
@endpush
