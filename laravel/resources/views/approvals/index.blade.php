@extends('layouts.app')
@section('title', '承認')

@section('content')
<div class="flex h-full" x-data="approvalApp()" x-init="init()" x-cloak>

    {{-- ユーザー名未設定モーダル --}}
    <template x-if="!currentUser">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-8">
                <h2 class="text-lg font-bold text-gray-800 mb-2">ユーザー名を設定</h2>
                <p class="text-sm text-gray-500 mb-4">承認ページを使用するにはあなたのユーザー名を入力してください。</p>
                <input type="text" x-model="userNameInput" @keydown.enter="setCurrentUser()"
                    placeholder="名前を入力..."
                    class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-blue-400">
                <button @click="setCurrentUser()"
                    :disabled="!userNameInput.trim()"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm py-2.5 rounded-full font-semibold disabled:opacity-40 transition-colors">
                    設定する
                </button>
            </div>
        </div>
    </template>

    {{-- 左: 承認待ちリスト --}}
    <div class="w-96 shrink-0 border-r border-gray-200 bg-white flex flex-col">
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex items-center justify-between mb-1">
                <h2 class="font-semibold text-gray-800">承認依頼</h2>
                <button @click="loadPending()"
                    class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-1.5 rounded-full transition-colors"
                    :class="{ 'opacity-50': loading }">
                    更新
                </button>
            </div>
            <p class="text-xs text-gray-400">
                他のユーザーからの承認依頼 (<span x-text="otherEmails.length"></span> 件)
            </p>
            <template x-if="currentUser">
                <p class="text-xs text-gray-400 mt-0.5">
                    ログイン中: <span class="font-medium text-gray-600" x-text="currentUser"></span>
                    <button @click="changeUser()" class="ml-2 text-blue-500 hover:text-blue-700 underline">変更</button>
                </p>
            </template>
        </div>

        <div class="overflow-y-auto flex-1 px-4 py-3 space-y-3">
            <template x-if="loading">
                <div class="text-center py-10 text-sm text-gray-400 animate-pulse">読み込み中...</div>
            </template>
            <template x-if="!loading && otherEmails.length === 0">
                <div class="text-center py-10 text-sm text-gray-400">
                    <p>他のユーザーからの承認依頼はありません</p>
                </div>
            </template>
            <template x-for="p in otherEmails" :key="p.id">
                <div class="border rounded-xl overflow-hidden shadow-sm cursor-pointer transition-all"
                    :class="selectedId === p.id ? 'border-amber-400 ring-2 ring-amber-200' : 'border-gray-200 hover:border-gray-300'"
                    @click="selectEmail(p)">
                    <div class="px-4 py-2.5 bg-amber-50 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-xs font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full shrink-0"
                                x-text="p.reply_type_label"></span>
                            <span class="text-xs font-semibold text-gray-700 truncate"
                                x-text="(p.created_by || '不明') + 'さんからの依頼'"></span>
                        </div>
                        <span class="text-xs text-gray-400 shrink-0" x-text="p.created_at"></span>
                    </div>
                    <div class="px-4 py-3">
                        <p class="text-sm font-semibold text-gray-800 truncate" x-text="p.subject"></p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            宛先: <span class="font-medium text-gray-700" x-text="p.to_address"></span>
                        </p>
                        <template x-if="p.memo">
                            <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2 py-1 mt-1.5 line-clamp-2"
                                x-text="'📝 ' + p.memo"></p>
                        </template>
                        <p class="text-xs text-gray-500 mt-1.5 line-clamp-2 whitespace-pre-wrap" x-text="p.body_preview"></p>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- 右: 詳細 + 承認/却下 --}}
    <div class="flex-1 flex flex-col min-w-0 bg-gray-50 overflow-hidden">

        <template x-if="!selectedEmail">
            <div class="flex items-center justify-center h-full text-gray-400 text-sm">
                左のリストから承認依頼を選択してください
            </div>
        </template>

        <template x-if="selectedEmail">
            <div class="flex flex-col h-full">
                {{-- アクションヘッダー --}}
                <div class="px-8 py-5 bg-white border-b border-gray-200 flex items-start justify-between gap-6 shrink-0">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <span class="text-xs font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full"
                                x-text="selectedEmail.reply_type_label"></span>
                            <span class="text-sm font-semibold text-gray-700"
                                x-text="(selectedEmail.created_by || '不明') + 'さんからの承認依頼'"></span>
                            <span class="text-xs text-gray-400" x-text="selectedEmail.created_at"></span>
                        </div>
                        <h1 class="text-lg font-bold text-gray-900" x-text="selectedEmail.subject"></h1>
                        <p class="text-sm text-gray-600 mt-1">
                            宛先: <span class="font-medium" x-text="selectedEmail.to_address"></span>
                        </p>
                        <template x-if="selectedEmail.cc">
                            <p class="text-sm text-gray-500">CC: <span x-text="selectedEmail.cc"></span></p>
                        </template>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <button @click="approve(selectedEmail)"
                            :disabled="actionLoading"
                            class="bg-green-600 hover:bg-green-700 text-white text-sm px-6 py-2 rounded-full font-semibold disabled:opacity-50 transition-colors">
                            承認・送信
                        </button>
                        <button @click="reject(selectedEmail)"
                            :disabled="actionLoading"
                            class="bg-white hover:bg-red-50 text-red-600 border border-red-300 text-sm px-5 py-2 rounded-full font-semibold disabled:opacity-50 transition-colors">
                            却下
                        </button>
                    </div>
                </div>

                <template x-if="actionMessage">
                    <div class="mx-8 mt-3 shrink-0 text-sm px-4 py-2 rounded-lg"
                        :class="actionError ? 'bg-red-50 text-red-600 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'"
                        x-text="actionMessage"></div>
                </template>

                <div class="flex-1 overflow-y-auto px-8 py-6 space-y-4">

                    {{-- 担当者のメモ --}}
                    <template x-if="selectedEmail.memo">
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                            <p class="text-xs font-semibold text-amber-700 mb-1">
                                📝 <span x-text="selectedEmail.created_by || '担当者'"></span>からのメモ
                            </p>
                            <p class="text-sm text-amber-800 whitespace-pre-wrap" x-text="selectedEmail.memo"></p>
                        </div>
                    </template>

                    {{-- 返信元メール --}}
                    <template x-if="selectedEmail.in_reply_to">
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="px-4 py-2.5 bg-gray-100 border-b border-gray-200">
                                <p class="text-xs font-semibold text-gray-600">返信元メール</p>
                            </div>
                            <div class="px-4 py-3">
                                <div class="flex items-center gap-2 mb-1">
                                    <p class="text-sm font-semibold text-gray-800" x-text="selectedEmail.in_reply_to.from_label"></p>
                                    <span class="text-xs text-gray-400" x-text="selectedEmail.in_reply_to.received_at"></span>
                                </div>
                                <p class="text-xs text-gray-500 mb-2" x-text="'件名: ' + selectedEmail.in_reply_to.subject"></p>
                                <p class="text-xs text-gray-600 whitespace-pre-wrap leading-relaxed line-clamp-6"
                                    x-text="selectedEmail.in_reply_to.plain_body"></p>
                            </div>
                        </div>
                    </template>

                    {{-- 添付ファイル --}}
                    <template x-if="selectedEmail.attachments && selectedEmail.attachments.length > 0">
                        <div class="bg-white rounded-xl border border-gray-200 p-4">
                            <p class="text-xs font-semibold text-gray-500 mb-2">
                                添付ファイル (<span x-text="selectedEmail.attachments.length"></span>)
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="att in selectedEmail.attachments" :key="att.filename">
                                    <span class="inline-flex items-center gap-1.5 text-sm bg-gray-50 border border-gray-200 text-gray-700 px-3 py-1.5 rounded-lg">
                                        📎 <span x-text="att.filename"></span>
                                        <span class="text-xs text-gray-400" x-text="'(' + att.size + ')'"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- 返信本文 --}}
                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <p class="text-xs font-semibold text-gray-500 mb-3">送信予定の本文</p>
                        <pre class="text-sm text-gray-800 whitespace-pre-wrap font-sans leading-relaxed" x-text="selectedEmail.body"></pre>
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
        currentUser: '',
        userNameInput: '',
        allEmails: [],
        selectedId: null,
        selectedEmail: null,
        actionLoading: false,
        actionMessage: '',
        actionError: false,

        get otherEmails() {
            if (!this.currentUser) return this.allEmails;
            return this.allEmails.filter(p => p.created_by !== this.currentUser);
        },

        async init() {
            this.currentUser = localStorage.getItem('currentUser') || '';
            await this.loadPending();
        },

        setCurrentUser() {
            const name = this.userNameInput.trim();
            if (!name) return;
            this.currentUser = name;
            localStorage.setItem('currentUser', name);
        },

        changeUser() {
            this.userNameInput = this.currentUser;
            this.currentUser = '';
        },

        async loadPending() {
            this.loading = true;
            try {
                const res = await fetch('/pending-emails', { headers: { 'Accept': 'application/json' } });
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
            } catch (_) {
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
                    this.actionMessage = '送信しました ✓';
                    this.actionError = false;
                    setTimeout(() => {
                        this.selectedId = null;
                        this.selectedEmail = null;
                        this.actionMessage = '';
                        this.loadPending();
                    }, 1500);
                }
            } catch (e) {
                this.actionMessage = 'エラー: ' + e.message;
                this.actionError = true;
            } finally {
                this.actionLoading = false;
            }
        },

        async reject(p) {
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
@endsection
