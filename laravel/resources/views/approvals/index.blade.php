@extends('layouts.app')
@section('title', '承認一覧')

@section('content')
<div class="flex h-full bg-gray-50" x-data="approvalApp()" x-init="init()" x-cloak>

    {{-- 左: 承認待ちリスト --}}
    <div class="w-96 shrink-0 border-r border-gray-200 bg-white flex flex-col shadow-sm">
        <div class="px-6 py-5 border-b border-gray-100 bg-white">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-lg font-black text-gray-800 tracking-tighter uppercase">承認依頼</h2>
                <button @click="loadPending()"
                    class="p-2 text-gray-400 hover:text-blue-600 transition-all rounded-full hover:bg-blue-50"
                    :class="{ 'animate-spin': loading }">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.001 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
            </div>
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">
                承認待ち (<span x-text="allEmails.length"></span> 件)
            </p>
        </div>

        <div class="overflow-y-auto flex-1 px-4 py-4 space-y-4 custom-scrollbar">
            <template x-if="loading">
                <div class="flex flex-col items-center justify-center py-20 text-gray-300">
                    <i class="fas fa-circle-notch fa-spin fa-2x mb-3"></i>
                    <p class="text-xs font-bold tracking-widest uppercase">Loading...</p>
                </div>
            </template>
            <template x-if="!loading && allEmails.length === 0">
                <div class="text-center py-20 text-gray-400">
                    <div class="mb-4 text-4xl opacity-20">📥</div>
                    <p class="text-sm font-bold">承認待ちの依頼はありません</p>
                </div>
            </template>
            <template x-for="p in allEmails" :key="p.id">
                <div class="group border rounded-2xl overflow-hidden shadow-sm cursor-pointer transition-all duration-200"
                    :class="selectedId === p.id ? 'border-blue-500 ring-4 ring-blue-50 bg-blue-50/10' : 'border-gray-200 hover:border-gray-300 bg-white'"
                    @click="selectEmail(p)">
                    <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between gap-2"
                         :class="selectedId === p.id ? 'bg-blue-50' : 'bg-gray-50/50 group-hover:bg-gray-50'">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-[10px] font-black text-white bg-blue-600 px-2 py-0.5 rounded uppercase tracking-tighter shrink-0"
                                x-text="p.reply_type_label"></span>
                            <span class="text-xs font-bold text-gray-600 truncate"
                                x-text="(p.created_by || '不明') + ' からの依頼'"></span>
                        </div>
                        <span class="text-[10px] font-bold text-gray-400 shrink-0" x-text="p.created_at"></span>
                    </div>
                    <div class="px-4 py-4">
                        <p class="text-sm font-black text-gray-800 truncate mb-1" x-text="p.subject"></p>
                        <p class="text-[11px] text-gray-400 font-bold uppercase truncate">
                            To: <span class="text-gray-600" x-text="p.to_address"></span>
                        </p>
                        <template x-if="p.memo">
                            <div class="mt-3 p-2 bg-amber-50 border border-amber-100 rounded-xl text-[11px] text-amber-700 font-medium line-clamp-2">
                                <span class="mr-1">📝</span><span x-text="p.memo"></span>
                            </div>
                        </template>
                        <p class="text-[11px] text-gray-500 mt-3 line-clamp-2 leading-relaxed" x-text="p.body_preview"></p>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- 右: 詳細 + 承認/却下 --}}
    <div class="flex-1 flex flex-col min-w-0 bg-gray-50 overflow-hidden relative">

        <template x-if="!selectedEmail">
            <div class="flex flex-col items-center justify-center h-full text-gray-300">
                <div class="mb-6 text-6xl opacity-10">📄</div>
                <p class="text-sm font-bold tracking-widest uppercase">Select an item from the list</p>
            </div>
        </template>

        <template x-if="selectedEmail">
            <div class="flex flex-col h-full animate-in fade-in duration-300">
                {{-- アクションヘッダー --}}
                <div class="px-10 py-6 bg-white border-b border-gray-200 flex items-start justify-between gap-8 shrink-0 shadow-sm z-10">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-3 mb-2 flex-wrap">
                            <span class="text-[10px] font-black text-white bg-blue-600 px-3 py-1 rounded-full uppercase tracking-widest"
                                x-text="selectedEmail.reply_type_label"></span>
                            <span class="text-sm font-bold text-gray-500 uppercase tracking-tight"
                                x-text="(selectedEmail.created_by || '不明') + ' による承認依頼'"></span>
                            <span class="text-xs font-bold text-gray-300" x-text="selectedEmail.created_at"></span>
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 leading-tight mb-2" x-text="selectedEmail.subject"></h1>
                        <div class="flex flex-col gap-1">
                            <p class="text-xs font-bold text-gray-400 uppercase">To: <span class="text-gray-700 font-black ml-2" x-text="selectedEmail.to_address"></span></p>
                            <template x-if="selectedEmail.cc">
                                <p class="text-xs font-bold text-gray-400 uppercase">Cc: <span class="text-gray-600 font-medium ml-2" x-text="selectedEmail.cc"></span></p>
                            </template>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 shrink-0 pt-2">
                        <button @click="reject(selectedEmail)"
                            :disabled="actionLoading"
                            class="bg-white hover:bg-red-50 text-red-600 border-2 border-red-100 text-xs px-8 py-3 rounded-2xl font-black shadow-sm transition-all disabled:opacity-50">
                            却下する
                        </button>
                        <button @click="approve(selectedEmail)"
                            :disabled="actionLoading"
                            class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-10 py-3 rounded-2xl font-black shadow-xl shadow-blue-100 transition-all flex items-center gap-2 disabled:opacity-50">
                            <span x-text="actionLoading ? '処理中...' : '承認・送信実行'"></span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        </button>
                    </div>
                </div>

                <template x-if="actionMessage">
                    <div class="mx-10 mt-6 shrink-0 text-xs font-black px-6 py-4 rounded-2xl shadow-sm border animate-in slide-in-from-top duration-300"
                        :class="actionError ? 'bg-red-50 text-red-600 border-red-100' : 'bg-green-50 text-green-700 border-green-100'"
                        x-text="actionMessage"></div>
                </template>

                <div class="flex-1 overflow-y-auto px-10 py-8 space-y-8 custom-scrollbar">

                    {{-- 担当者のメモ --}}
                    <template x-if="selectedEmail.memo">
                        <div class="bg-amber-50 border-2 border-amber-100 rounded-3xl p-6 shadow-sm">
                            <p class="text-[10px] font-black text-amber-600 uppercase tracking-widest mb-3">
                                📝 <span x-text="selectedEmail.created_by || '担当者'"></span> からの申し送り事項
                            </p>
                            <p class="text-sm text-amber-900 font-bold leading-relaxed whitespace-pre-wrap" x-text="selectedEmail.memo"></p>
                        </div>
                    </template>

                    {{-- 返信元メール --}}
                    <template x-if="selectedEmail.in_reply_to">
                        <div class="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm">
                            <div class="px-6 py-3 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Original Message (返信元)</p>
                                <span class="text-[10px] font-bold text-gray-400" x-text="selectedEmail.in_reply_to.received_at"></span>
                            </div>
                            <div class="px-6 py-5">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 font-bold text-xs" x-text="selectedEmail.in_reply_to.from_label.charAt(0)"></div>
                                    <p class="text-sm font-black text-gray-800" x-text="selectedEmail.in_reply_to.from_label"></p>
                                </div>
                                <p class="text-xs font-bold text-gray-400 mb-4" x-text="'Subject: ' + selectedEmail.in_reply_to.subject"></p>
                                <div class="text-xs text-gray-600 whitespace-pre-wrap leading-relaxed bg-gray-50 p-4 rounded-2xl border border-gray-100"
                                    x-text="selectedEmail.in_reply_to.plain_body"></div>
                            </div>
                        </div>
                    </template>

                    {{-- 添付ファイル --}}
                    <template x-if="selectedEmail.attachments && selectedEmail.attachments.length > 0">
                        <div class="bg-white rounded-3xl border border-gray-200 p-6 shadow-sm">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">
                                Attachments (<span x-text="selectedEmail.attachments.length"></span>)
                            </p>
                            <div class="flex flex-wrap gap-3">
                                <template x-for="att in selectedEmail.attachments" :key="att.filename">
                                    <span class="inline-flex items-center gap-2 text-xs font-bold bg-gray-50 border border-gray-200 text-gray-700 px-4 py-2 rounded-xl hover:bg-gray-100 transition-colors">
                                        📎 <span x-text="att.filename"></span>
                                        <span class="text-[10px] text-gray-400 font-medium" x-text="'(' + att.size + ')'"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- 返信本文 --}}
                    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
                         <div class="px-6 py-3 bg-gray-50 border-b border-gray-100">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Reply Content (送信予定の本文)</p>
                        </div>
                        <div class="p-8">
                            <pre class="text-base text-gray-800 whitespace-pre-wrap font-sans leading-relaxed tracking-tight" x-text="selectedEmail.body"></pre>
                        </div>
                    </div>

                </div>
            </div>
        </template>
    </div>
</div>

<script>
function approvalApp() {
    return {
        loading: false,
        allEmails: [],
        selectedId: null,
        selectedEmail: null,
        actionLoading: false,
        actionMessage: '',
        actionError: false,

        async init() {
            await this.loadPending();
        },

        async loadPending() {
            this.loading = true;
            try {
                const res = await fetch('/pending-emails?status=pending', { headers: { 'Accept': 'application/json' } });
                this.allEmails = await res.json();
                if (this.selectedId) {
                    const updated = this.allEmails.find(p => p.id === this.selectedId);
                    if (updated) {
                        this.selectedEmail = updated;
                    } else {
                        this.selectedId = null;
                        this.selectedEmail = null;
                    }
                }
            } catch (e) {
                console.error('Failed to load pending emails:', e);
            } finally {
                this.loading = false;
            }
        },

        selectEmail(p) {
            this.selectedId = p.id;
            this.selectedEmail = p;
            this.actionMessage = '';
            this.actionError = false;
        },

        async approve(p) {
            if (!confirm('このメールを承認し、実際に送信しますか？')) return;
            this.actionLoading = true;
            this.actionMessage = '';
            this.actionError = false;
            try {
                const res = await fetch(`/pending-emails/${p.id}/approve`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
                const data = await res.json();
                if (!res.ok || data.status === 'error') {
                    this.actionMessage = data.message || '送信に失敗しました';
                    this.actionError = true;
                } else {
                    this.actionMessage = '承認されました。メールを送信しました。 ✓';
                    this.actionError = false;
                    setTimeout(() => {
                        this.selectedId = null;
                        this.selectedEmail = null;
                        this.actionMessage = '';
                        this.loadPending();
                    }, 2000);
                }
            } catch (e) {
                this.actionMessage = 'エラー: ' + e.message;
                this.actionError = true;
            } finally {
                this.actionLoading = false;
            }
        },

        async reject(p) {
            if (!confirm('この依頼を却下しますか？')) return;
            this.actionLoading = true;
            this.actionMessage = '';
            this.actionError = false;
            try {
                const res = await fetch(`/pending-emails/${p.id}/reject`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
                const data = await res.json();
                if (!res.ok || data.status === 'error') {
                    this.actionMessage = data.message || '却下に失敗しました';
                    this.actionError = true;
                } else {
                    this.actionMessage = '却下しました';
                    this.actionError = false;
                    setTimeout(() => {
                        this.selectedId = null;
                        this.selectedEmail = null;
                        this.actionMessage = '';
                        this.loadPending();
                    }, 1000);
                }
            } catch (e) {
                this.actionMessage = 'エラー: ' + e.message;
                this.actionError = true;
            } finally {
                this.actionLoading = false;
            }
        },
    };
}
</script>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #E5E7EB; border-radius: 10px; }
[x-cloak] { display: none !important; }
</style>
@endsection
