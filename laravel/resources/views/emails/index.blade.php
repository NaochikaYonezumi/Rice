@extends('layouts.app')
@section('title', 'メール')

@section('content')
<div class="flex h-full" x-data="emailApp()" x-init="init()" x-cloak>

    {{-- スレッド一覧パネル --}}
    <div class="w-80 shrink-0 border-r border-gray-200 bg-white flex flex-col">

        {{-- ヘッダー --}}
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-sm text-gray-700">受信トレイ</h2>
            <button @click="fetchEmails()"
                class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-1 rounded-full"
                :class="{ 'opacity-50 cursor-wait': fetching }">
                <span x-text="fetching ? '取得中...' : '更新'"></span>
            </button>
        </div>

        {{-- 検索バー --}}
        <div class="px-3 py-2 border-b border-gray-100">
            <input
                type="text"
                x-model="searchQuery"
                @input="onSearchInput()"
                placeholder="キーワード検索..."
                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-400"
            >
        </div>

        {{-- アクティブなタグフィルター --}}
        <template x-if="activeTags.length > 0">
            <div class="px-3 py-2 border-b border-gray-100 flex flex-wrap gap-1">
                <template x-for="tag in activeTags" :key="tag">
                    <span class="inline-flex items-center gap-1 text-xs bg-blue-600 text-white rounded-full px-2 py-0.5">
                        <span x-text="tag"></span>
                        <button @click="removeTagFilter(tag)" class="hover:text-blue-200 ml-0.5">✕</button>
                    </span>
                </template>
            </div>
        </template>

        {{-- エラー表示 --}}
        <template x-if="fetchError">
            <div class="px-3 py-2 mx-3 mt-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg flex items-start gap-2">
                <span class="shrink-0">⚠</span>
                <span x-text="fetchError"></span>
            </div>
        </template>

        {{-- スレッドリスト --}}
        <div class="overflow-y-auto flex-1">
            <template x-if="threadsLoading">
                <div class="px-4 py-8 text-center text-sm text-gray-400 animate-pulse">読み込み中...</div>
            </template>

            <template x-if="!threadsLoading && threads.length === 0">
                <div class="px-4 py-8 text-center text-sm text-gray-400">
                    <span x-text="searchQuery || activeTags.length > 0 ? '該当するメールがありません' : 'メールがありません。「更新」ボタンで取得してください。'"></span>
                </div>
            </template>

            <template x-for="thread in threads" :key="thread.id">
                <button
                    @click="loadThread(thread.id)"
                    class="w-full text-left px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition-colors"
                    :class="{ 'bg-blue-50 border-l-2 border-l-blue-500': selectedThreadId === thread.id }"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 mb-0.5">
                                <template x-if="thread.latest_email && !thread.latest_email.is_read">
                                    <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0"></span>
                                </template>
                                <p class="text-sm font-medium truncate text-gray-800"
                                   x-text="thread.latest_email?.from_label ?? '—'"></p>
                            </div>
                            <p class="text-xs text-gray-600 truncate font-medium" x-text="thread.subject"></p>
                            <p class="text-xs text-gray-400 truncate mt-0.5"
                               x-text="thread.latest_email?.plain_body ?? ''"></p>

                            {{-- タグ --}}
                            <div class="flex flex-wrap gap-1 mt-1.5" x-show="thread.tags && thread.tags.length > 0">
                                <template x-for="tag in (thread.tags || [])" :key="tag">
                                    <span
                                        class="inline-flex items-center gap-0.5 text-xs rounded-full px-2 py-0.5 cursor-pointer"
                                        :class="activeTags.includes(tag) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        @click.stop="toggleTagFilter(tag)"
                                    >
                                        <span x-text="tag"></span>
                                        <span
                                            @click.stop="removeTag(thread, tag)"
                                            class="ml-0.5 opacity-60 hover:opacity-100"
                                        >✕</span>
                                    </span>
                                </template>
                            </div>

                            {{-- タグ追加 --}}
                            <div class="mt-1 flex items-center">
                                <template x-if="addingTagThreadId !== thread.id">
                                    <button
                                        @click.stop="startAddTag(thread.id)"
                                        class="text-xs text-gray-300 hover:text-gray-500 leading-none"
                                    >+ タグ</button>
                                </template>
                                <template x-if="addingTagThreadId === thread.id">
                                    <input
                                        type="text"
                                        x-model="newTagInput"
                                        @click.stop
                                        @keydown.enter.stop="saveTag(thread)"
                                        @keydown.escape.stop="addingTagThreadId = null; newTagInput = ''"
                                        @blur="saveTag(thread)"
                                        placeholder="タグ名…"
                                        class="text-xs border border-blue-300 rounded px-1.5 py-0.5 w-24 focus:outline-none focus:ring-1 focus:ring-blue-400"
                                        x-init="$nextTick(() => $el.focus())"
                                    >
                                </template>
                            </div>
                        </div>
                        <span class="text-xs text-gray-400 shrink-0 mt-0.5" x-text="thread.last_email_at"></span>
                    </div>
                </button>
            </template>
        </div>
    </div>

    {{-- メール本文パネル --}}
    <div class="flex-1 flex flex-col min-w-0 bg-white">

        <template x-if="!selectedThread && !loadingThread">
            <div class="flex items-center justify-center h-full text-gray-400 text-sm">
                メールを選択してください
            </div>
        </template>

        <template x-if="loadingThread">
            <div class="flex items-center justify-center h-full text-gray-400 text-sm animate-pulse">
                読み込み中...
            </div>
        </template>

        {{-- スレッドビュー --}}
        <template x-if="selectedThread && !loadingThread">
            <div class="flex flex-col h-full">

                {{-- スレッドヘッダー --}}
                <div class="px-6 py-4 border-b border-gray-100">
                    <h1 class="text-base font-semibold text-gray-900" x-text="selectedThread.subject"></h1>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="threadEmails.length + ' 件のメッセージ'"></p>
                </div>

                {{-- メッセージ一覧 --}}
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                    <template x-for="email in threadEmails" :key="email.id">
                        <div class="border border-gray-100 rounded-xl overflow-hidden shadow-sm">
                            {{-- メールヘッダー --}}
                            <div
                                @click="toggleEmail(email.id)"
                                class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 cursor-pointer"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm shrink-0"
                                         x-text="(email.from_label || '?').charAt(0).toUpperCase()"></div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-800 truncate" x-text="email.from_label"></p>
                                        <p class="text-xs text-gray-400" x-text="'宛先: ' + email.to_address"></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="text-xs text-gray-400" x-text="email.received_at"></span>
                                    <button
                                        @click.stop="openAi(email)"
                                        class="bg-purple-600 hover:bg-purple-700 text-white text-xs px-3 py-1 rounded-full"
                                    >AI</button>
                                </div>
                            </div>

                            {{-- メール本文 --}}
                            <div x-show="expandedEmailIds.includes(email.id)" class="px-4 py-4 text-sm text-gray-700">
                                <div x-show="!!email.body_html" class="prose-email max-w-none" x-html="email.body_html || ''"></div>
                                <pre x-show="!email.body_html" class="whitespace-pre-wrap font-sans" x-text="email.plain_body || ''"></pre>

                                {{-- 返信ボタン --}}
                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <button
                                        @click.stop="openReply(email)"
                                        class="text-xs text-blue-600 hover:text-blue-800 font-medium"
                                    >↩ 返信</button>
                                </div>

                                {{-- 返信フォーム --}}
                                <div x-show="replyEmailId === email.id" class="mt-3 space-y-2">
                                    <div class="flex gap-2">
                                        <label class="text-xs text-gray-500 w-10 pt-1.5 shrink-0">宛先</label>
                                        <input type="email" x-model="replyTo"
                                            class="flex-1 text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-400">
                                    </div>
                                    <div class="flex gap-2">
                                        <label class="text-xs text-gray-500 w-10 pt-1.5 shrink-0">CC</label>
                                        <input type="text" x-model="replyCc" placeholder="複数の場合はカンマ区切り"
                                            class="flex-1 text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-400">
                                    </div>
                                    <textarea x-model="replyBody" rows="6" placeholder="返信内容を入力..."
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 resize-y focus:outline-none focus:ring-2 focus:ring-blue-400"></textarea>
                                    <div class="flex items-center gap-2">
                                        <button
                                            @click.stop="sendReply(email)"
                                            :disabled="replySending"
                                            class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-4 py-1.5 rounded-full disabled:opacity-50"
                                        ><span x-text="replySending ? '送信中...' : '送信'"></span></button>
                                        <button
                                            @click.stop="replyEmailId = null"
                                            class="text-xs text-gray-400 hover:text-gray-600"
                                        >キャンセル</button>
                                        <span x-show="replyError" class="text-xs text-red-500" x-text="replyError"></span>
                                        <span x-show="replySent" class="text-xs text-green-600">送信しました</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- AIサイドパネル --}}
    <div
        x-show="aiPanelOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="w-96 shrink-0 border-l border-gray-200 bg-white flex flex-col shadow-xl"
        style="display: none;"
    >
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-sm">AI アシスト</h3>
            <button @click="aiPanelOpen = false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>

        {{-- 質問入力 --}}
        <div class="px-4 py-3 border-b border-gray-100">
            <textarea
                x-model="aiQuestion"
                placeholder="質問を入力（省略すると返信文を自動生成）"
                rows="3"
                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 resize-none focus:outline-none focus:ring-2 focus:ring-purple-400"
            ></textarea>
            <button
                @click="submitAi()"
                :disabled="aiLoading"
                class="mt-2 w-full bg-purple-600 hover:bg-purple-700 text-white text-sm py-2 rounded-lg disabled:opacity-50"
            >
                <span x-text="aiLoading ? '生成中...' : 'AI に聞く'"></span>
            </button>
        </div>

        {{-- AI回答 --}}
        <div class="flex-1 overflow-y-auto px-4 py-3">
            <template x-if="aiAnswer">
                <div>
                    <p class="text-xs font-semibold text-gray-500 mb-2">回答</p>
                    <div class="text-sm text-gray-800 whitespace-pre-wrap bg-gray-50 rounded-lg p-3" x-text="aiAnswer"></div>

                    <template x-if="aiSources && aiSources.length > 0">
                        <div class="mt-3">
                            <p class="text-xs font-semibold text-gray-400 mb-1">参照ソース</p>
                            <template x-for="src in aiSources" :key="src">
                                <p class="text-xs text-blue-500 truncate" x-text="src"></p>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="!aiAnswer && !aiLoading">
                <p class="text-xs text-gray-400">AIがメール・スクレイピングデータ・アップロード済みドキュメントを参照して回答します。</p>
            </template>
        </div>
    </div>
</div>

<script>
function emailApp() {
    return {
        // スレッドリスト
        threads: [],
        threadsLoading: false,
        searchQuery: '',
        activeTags: [],
        searchDebounce: null,

        // スレッド詳細
        selectedThreadId: null,
        selectedThread: null,
        threadEmails: [],
        expandedEmailIds: [],
        loadingThread: false,

        // タグ管理
        addingTagThreadId: null,
        newTagInput: '',

        // その他
        fetching: false,
        fetchError: '',
        aiPanelOpen: false,
        aiTargetEmail: null,
        aiQuestion: '',
        aiAnswer: '',
        aiSources: [],
        aiLoading: false,
        replyEmailId: null,
        replyTo: '',
        replyCc: '',
        replyBody: '',
        replySending: false,
        replyError: '',
        replySent: false,

        async init() {
            await this.loadThreads();
        },

        async loadThreads() {
            this.threadsLoading = true;
            const params = new URLSearchParams();
            if (this.searchQuery) params.set('q', this.searchQuery);
            this.activeTags.forEach(t => params.append('tags[]', t));

            try {
                const res = await fetch('/emails/search?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                this.threads = await res.json();
            } finally {
                this.threadsLoading = false;
            }
        },

        onSearchInput() {
            clearTimeout(this.searchDebounce);
            this.searchDebounce = setTimeout(() => this.loadThreads(), 300);
        },

        toggleTagFilter(tag) {
            const idx = this.activeTags.indexOf(tag);
            if (idx === -1) {
                this.activeTags.push(tag);
            } else {
                this.activeTags.splice(idx, 1);
            }
            this.loadThreads();
        },

        removeTagFilter(tag) {
            this.activeTags = this.activeTags.filter(t => t !== tag);
            this.loadThreads();
        },

        startAddTag(threadId) {
            this.addingTagThreadId = threadId;
            this.newTagInput = '';
        },

        async saveTag(thread) {
            const tag = this.newTagInput.trim();
            this.addingTagThreadId = null;
            this.newTagInput = '';
            if (!tag) return;

            const newTags = [...(thread.tags || []), tag];
            try {
                const res = await fetch(`/threads/${thread.id}/tags`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ tags: newTags }),
                });
                const data = await res.json();
                thread.tags = data.tags;
            } catch (_) {}
        },

        async removeTag(thread, tag) {
            const newTags = (thread.tags || []).filter(t => t !== tag);
            try {
                const res = await fetch(`/threads/${thread.id}/tags`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ tags: newTags }),
                });
                const data = await res.json();
                thread.tags = data.tags;
            } catch (_) {}
        },

        async loadThread(threadId) {
            if (this.selectedThreadId === threadId) return;
            this.selectedThreadId = threadId;
            this.loadingThread = true;
            this.selectedThread = null;
            this.threadEmails = [];
            this.expandedEmailIds = [];
            this.aiPanelOpen = false;

            try {
                const res = await fetch(`/threads/${threadId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.selectedThread = data.thread;
                this.threadEmails = data.emails;
                this.expandedEmailIds = data.emails.map(e => e.id);
            } finally {
                this.loadingThread = false;
            }
        },

        toggleEmail(emailId) {
            const idx = this.expandedEmailIds.indexOf(emailId);
            if (idx === -1) {
                this.expandedEmailIds.push(emailId);
            } else {
                this.expandedEmailIds.splice(idx, 1);
            }
        },

        openAi(email) {
            this.aiTargetEmail = email;
            this.aiQuestion = '';
            this.aiAnswer = '';
            this.aiSources = [];
            this.aiPanelOpen = true;
        },

        async submitAi() {
            if (!this.aiTargetEmail) return;
            this.aiLoading = true;
            this.aiAnswer = '';
            this.aiSources = [];

            try {
                const res = await fetch(`/emails/${this.aiTargetEmail.id}/ai`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ question: this.aiQuestion }),
                });
                const data = await res.json();
                this.aiAnswer = data.answer || data.error || '回答を取得できませんでした。';
                this.aiSources = data.sources || [];
            } catch (e) {
                this.aiAnswer = 'エラー: ' + e.message;
            } finally {
                this.aiLoading = false;
            }
        },

        openReply(email) {
            this.replyEmailId = this.replyEmailId === email.id ? null : email.id;
            this.replyTo = email.from_address;
            this.replyCc = '';
            this.replyBody = '';
            this.replyError = '';
            this.replySent = false;
        },

        async sendReply(email) {
            if (!this.replyBody.trim()) return;
            this.replySending = true;
            this.replyError = '';
            this.replySent = false;
            try {
                const res = await fetch(`/emails/${email.id}/reply`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ to: this.replyTo, cc: this.replyCc, body: this.replyBody }),
                });
                const data = await res.json();
                if (!res.ok || data.status === 'error') {
                    this.replyError = data.message || '送信に失敗しました';
                } else {
                    this.replySent = true;
                    this.replyBody = '';
                    setTimeout(() => { this.replyEmailId = null; this.replySent = false; }, 2000);
                }
            } catch (e) {
                this.replyError = '送信に失敗しました: ' + e.message;
            } finally {
                this.replySending = false;
            }
        },

        async fetchEmails() {
            this.fetching = true;
            this.fetchError = '';
            try {
                const res = await fetch('/emails/fetch', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
                const data = await res.json();
                if (!res.ok || data.status === 'error') {
                    this.fetchError = data.message || 'メール取得に失敗しました';
                } else {
                    await this.loadThreads();
                }
            } catch (e) {
                this.fetchError = 'メール取得に失敗しました: ' + e.message;
            } finally {
                this.fetching = false;
            }
        },
    };
}
</script>
@endsection
