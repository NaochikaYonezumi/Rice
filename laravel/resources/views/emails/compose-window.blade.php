@extends('layouts.fullpage')
@section('title', $mode === 'compose' ? '新規メッセージ作成' : ($mode === 'reply_all' ? '全員に返信' : '返信'))

@section('content')
<div class="flex h-screen w-screen overflow-hidden bg-white text-gray-800 font-sans"
     x-data="composeWindowApp()" x-init="init()" x-cloak>

    {{-- 左ペイン: スレッド表示 / 空状態 --}}
    <aside class="w-[45%] min-w-[420px] max-w-[680px] h-full overflow-y-auto bg-gray-50 border-r border-gray-200 custom-scrollbar">
        <template x-if="mode === 'compose'">
            <div class="flex flex-col items-center justify-center h-full px-8 text-center">
                <div class="w-20 h-20 bg-white rounded-3xl shadow-xl flex items-center justify-center text-gray-300 mb-6">
                    <i class="fas fa-pen-fancy fa-2x"></i>
                </div>
                <h1 class="text-xl font-bold text-gray-800">新規メッセージ作成</h1>
                <p class="text-sm text-gray-500 mt-3 max-w-sm leading-relaxed">右側で宛先・件名・本文を入力してください。送信すると承認待ちとして登録されます。</p>
            </div>
        </template>

        <template x-if="mode !== 'compose' && email">
            <div class="p-6 space-y-5">
                <header class="space-y-2 pb-4 border-b border-gray-200">
                    <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider" x-text="mode === 'reply_all' ? '全員に返信' : '返信'"></p>
                    <h1 class="text-lg font-bold text-gray-900 leading-snug" x-text="thread?.subject || email.subject"></h1>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                        <span class="flex items-center gap-1.5"><i class="fas fa-user-circle text-gray-400"></i><span class="font-semibold text-gray-700" x-text="email.from_label || email.from_address"></span></span>
                        <span class="text-gray-300">•</span>
                        <span x-text="email.received_at"></span>
                    </div>
                    <div class="space-y-0.5 text-xs text-gray-500" x-show="email.to_address">
                        <p><span class="font-semibold text-gray-600">To:</span> <span x-text="email.to_address"></span></p>
                        <p x-show="email.cc"><span class="font-semibold text-gray-600">Cc:</span> <span x-text="email.cc"></span></p>
                    </div>
                </header>

                <article class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
                    <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap break-words" x-text="email.plain_body"></div>
                    <template x-if="email.attachments && email.attachments.length > 0">
                        <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap gap-2">
                            <template x-for="at in email.attachments" :key="at.id">
                                <a :href="at.url" class="flex items-center gap-2 bg-gray-50 border border-gray-200 px-3 py-1.5 rounded-lg text-xs font-semibold text-blue-600 hover:bg-blue-600 hover:text-white transition-all">
                                    <i class="fas fa-paperclip"></i><span x-text="at.filename"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </article>

                <template x-if="emails.length > 1">
                    <section class="space-y-3">
                        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">スレッド内の過去メール</h2>
                        <template x-for="e in pastEmails" :key="e.id">
                            <details class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                                <summary class="cursor-pointer px-4 py-3 flex items-center justify-between hover:bg-gray-50 select-none">
                                    <div class="min-w-0 pr-3">
                                        <p class="text-xs font-bold text-gray-700 truncate" x-text="e.from_label || e.from_address"></p>
                                        <p class="text-[11px] text-gray-400 truncate" x-text="e.received_at"></p>
                                    </div>
                                    <i class="fas fa-chevron-down text-gray-300 text-xs"></i>
                                </summary>
                                <div class="px-4 pb-4 pt-2 border-t border-gray-100">
                                    <div class="text-xs text-gray-700 leading-relaxed whitespace-pre-wrap break-words" x-text="e.plain_body"></div>
                                </div>
                            </details>
                        </template>
                    </section>
                </template>
            </div>
        </template>
    </aside>

    {{-- 右ペイン: ドラフトフォーム --}}
    <main class="flex-1 min-w-0 h-full flex flex-col bg-white">
        <header class="shrink-0 px-6 py-4 border-b border-gray-200 bg-white flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-md shrink-0">
                    <i class="fas" :class="mode === 'compose' ? 'fa-pen-fancy' : 'fa-reply'"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="text-base font-bold text-gray-800 truncate" x-text="headerLabel"></h2>
                    <p class="text-xs text-gray-400 mt-0.5" x-show="thread?.subject" x-text="thread?.subject"></p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button @click="toggleAi()"
                        :class="aiPanelOpen ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-indigo-600 border-indigo-200 hover:bg-indigo-50'"
                        class="px-4 py-2 rounded-lg border text-xs font-bold transition-all flex items-center gap-2 shadow-sm">
                    <i class="fas fa-magic"></i> AIアシスタント
                </button>
                <button @click="attemptClose()" class="text-gray-400 hover:text-red-500 transition-colors p-2" title="閉じる">
                    <i class="fas fa-times fa-lg"></i>
                </button>
            </div>
        </header>

        <div class="flex-1 min-h-0 flex overflow-hidden">
            {{-- ドラフトフォーム --}}
            <div class="flex-1 min-w-0 overflow-y-auto custom-scrollbar">
                <form @submit.prevent="submitDraft()" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="relative">
                            <label data-test-id="compose-from-label" class="text-[10px] font-bold text-gray-500 uppercase absolute left-3 top-2 tracking-wider">差出人 (From)</label>
                            <input type="text" x-model="form.from" data-test-id="compose-from-input" class="w-full pt-7 pb-2.5 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition-all font-semibold">
                        </div>
                        <div class="relative">
                            <label data-test-id="compose-to-label" class="text-[10px] font-bold text-gray-500 uppercase absolute left-3 top-2 tracking-wider">宛先 (To)</label>
                            <input type="text" x-model="form.to" data-test-id="compose-to-input" required class="w-full pt-7 pb-2.5 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition-all font-semibold">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="relative">
                            <label data-test-id="compose-cc-label" class="text-[10px] font-bold text-gray-500 uppercase absolute left-3 top-2 tracking-wider">Cc</label>
                            <input type="text" x-model="form.cc" class="w-full pt-7 pb-2.5 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition-all font-medium">
                        </div>
                        <div class="relative">
                            <label data-test-id="compose-bcc-label" class="text-[10px] font-bold text-gray-500 uppercase absolute left-3 top-2 tracking-wider">Bcc</label>
                            <input type="text" x-model="form.bcc" class="w-full pt-7 pb-2.5 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition-all font-medium">
                        </div>
                    </div>
                    <div class="relative">
                        <label data-test-id="compose-subject-label" class="text-[10px] font-bold text-gray-500 uppercase absolute left-3 top-2 tracking-wider">件名</label>
                        <input type="text" x-model="form.subject" class="w-full pt-7 pb-2.5 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition-all font-bold">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 block">本文</label>
                        <textarea x-model="form.body" rows="14" placeholder="返信内容を入力してください..."
                                  class="w-full text-sm border border-gray-200 bg-white rounded-xl p-4 focus:ring-2 focus:ring-blue-200 focus:border-blue-300 outline-none leading-relaxed resize-y text-gray-700 min-h-[280px]"></textarea>
                    </div>

                    {{-- 添付ファイル --}}
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">添付ファイル</label>
                            <span class="text-[10px] text-gray-400" x-text="totalSizeLabel"></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <input type="file" multiple @change="handleFileSelect($event)" class="hidden" id="compose-file-input">
                            <label for="compose-file-input" class="cursor-pointer bg-gray-50 hover:bg-gray-100 text-gray-600 px-4 py-2 rounded-lg text-xs font-bold border border-gray-200 transition-all flex items-center gap-2">
                                <i class="fas fa-paperclip"></i> 追加 (最大20MB)
                            </label>
                            <template x-for="(f, i) in selectedFiles" :key="i">
                                <span class="bg-blue-50 text-blue-700 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-2 border border-blue-100">
                                    <span x-text="f.name" class="max-w-[220px] truncate"></span>
                                    <span class="text-blue-400" x-text="formatBytes(f.size)"></span>
                                    <button type="button" @click="removeSelectedFile(i)" class="hover:text-red-500"><i class="fas fa-times-circle"></i></button>
                                </span>
                            </template>
                        </div>
                    </div>
                </form>
            </div>

            {{-- AIパネル (右側オーバレイ) --}}
            <aside x-show="aiPanelOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-4 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
                   class="w-[400px] shrink-0 border-l border-indigo-100 bg-indigo-50/30 flex flex-col overflow-hidden">
                <div class="px-6 py-4 border-b border-indigo-100 bg-white flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-indigo-700">AIアシスタント</h3>
                        <p class="text-[10px] text-indigo-400 mt-0.5">スキル + コンテキスト分析</p>
                    </div>
                    <button @click="aiPanelOpen = false" class="text-gray-300 hover:text-indigo-600 p-1"><i class="fas fa-times"></i></button>
                </div>
                <div class="flex-1 overflow-y-auto p-5 space-y-5 custom-scrollbar">
                    <div class="space-y-2">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">スキル</label>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="(skill, key) in aiSkills" :key="key">
                                <button type="button" @click="aiSkill = key"
                                        :class="aiSkill === key ? 'bg-indigo-600 text-white border-indigo-600 shadow-md' : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300'"
                                        class="p-3 rounded-xl border text-left transition-all">
                                    <p class="text-xs font-bold" x-text="skill.name"></p>
                                    <p class="text-[10px] mt-1 opacity-70 leading-tight" x-text="skill.description"></p>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">追加の指示 (任意)</label>
                        <textarea x-model="aiUserPrompt" rows="3" placeholder="例: もっと簡潔に、箇条書きで..."
                                  class="w-full text-xs border border-gray-200 bg-white rounded-lg p-3 outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300 resize-none"></textarea>
                    </div>
                    <label class="flex items-center gap-2 bg-white p-3 rounded-lg border border-gray-200 cursor-pointer">
                        <input type="checkbox" x-model="maskPii" class="w-4 h-4 rounded text-indigo-600">
                        <span class="text-xs font-semibold text-gray-700">個人情報をマスキングする</span>
                    </label>
                    <button type="button" @click="askAi()" :disabled="aiLoading || !canAskAi"
                            class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold text-sm shadow-md hover:bg-indigo-700 transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-bolt" :class="aiLoading ? 'animate-spin' : ''"></i>
                        <span x-text="aiLoading ? '分析中...' : 'AI回答を生成する'"></span>
                    </button>
                    <p x-show="!canAskAi" class="text-[11px] text-gray-400 text-center">新規作成では返信対象がないため利用できません</p>

                    <div x-show="aiAnalysis || aiLoading" class="space-y-3">
                        <div class="bg-gray-900 rounded-2xl p-5 shadow-xl">
                            <h4 class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-3">生成結果</h4>
                            <div class="text-sm text-gray-100 leading-relaxed whitespace-pre-wrap min-h-[120px]" x-text="aiAnalysis?.answer"></div>
                            <div class="mt-4 pt-3 border-t border-gray-800 flex flex-wrap gap-2">
                                <template x-if="aiAnalysis?.sources?.kb"><span class="px-2 py-0.5 bg-green-900/30 text-green-400 text-[9px] font-bold rounded border border-green-800">ナレッジ</span></template>
                                <template x-if="aiAnalysis?.sources?.reports"><span class="px-2 py-0.5 bg-blue-900/30 text-blue-400 text-[9px] font-bold rounded border border-blue-800">レポート</span></template>
                            </div>
                            <button type="button" @click="applyAiDraft()" :disabled="!aiAnalysis?.answer"
                                    class="mt-4 w-full bg-white text-gray-900 py-2.5 rounded-lg text-xs font-bold hover:bg-indigo-50 transition-all disabled:opacity-50">
                                本文に反映する
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        {{-- フッター: アクション --}}
        <footer class="shrink-0 px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center justify-between gap-3">
            <div class="text-xs text-gray-500 flex items-center gap-3">
                <span x-show="lastSavedLabel"><i class="fas fa-save text-gray-400 mr-1"></i><span x-text="lastSavedLabel"></span></span>
                <button type="button" @click="saveDraft(true)" class="text-blue-600 hover:text-blue-800 font-bold underline-offset-2 hover:underline">下書き保存</button>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="attemptClose()" class="bg-white border border-gray-200 text-gray-600 px-4 py-2.5 rounded-lg text-sm font-bold hover:bg-gray-100 transition-all">閉じる</button>
                <button type="button" @click="submitDraft()" :disabled="!form.body || sending"
                        class="bg-blue-600 text-white px-6 py-2.5 rounded-lg text-sm font-bold shadow-lg hover:bg-blue-700 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!sending">承認を依頼する</span>
                    <span x-show="sending">送信中...</span>
                    <i class="fas fa-paper-plane" x-show="!sending"></i>
                    <i class="fas fa-spinner animate-spin" x-show="sending"></i>
                </button>
            </div>
        </footer>
    </main>

    {{-- 添付エラーモーダル --}}
    <template x-if="attachmentError">
        <div class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-md p-4" @click.self="attachmentError = null">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="bg-amber-50 px-6 py-5 border-b border-amber-100 flex items-center gap-3">
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-600"><i class="fas fa-paperclip"></i></div>
                    <h3 class="text-base font-bold text-amber-900">添付ファイルエラー</h3>
                </div>
                <div class="px-6 py-5 space-y-3">
                    <p class="text-sm text-gray-700 whitespace-pre-wrap" x-text="attachmentError.message"></p>
                    <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-200">
                        <p class="text-[10px] font-bold text-gray-400 uppercase">現在の合計サイズ</p>
                        <p class="text-xl font-bold text-gray-800" x-text="attachmentError.totalSize"></p>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex gap-2">
                    <button @click="selectedFiles = []; attachmentError = null" class="flex-1 bg-white border border-gray-200 text-gray-600 py-2.5 rounded-lg text-xs font-bold hover:bg-gray-100 transition-all">添付をクリア</button>
                    <button @click="attachmentError = null" class="flex-1 bg-gray-900 text-white py-2.5 rounded-lg text-xs font-bold hover:bg-black transition-all">閉じる</button>
                </div>
            </div>
        </div>
    </template>

    {{-- 閉じる確認モーダル --}}
    <template x-if="closeConfirmOpen">
        <div class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-md p-4" @click.self="closeConfirmOpen = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-bold text-gray-900">未保存の内容があります</h3>
                    <p class="text-xs text-gray-500 mt-1">どうしますか？</p>
                </div>
                <div class="px-6 py-5 space-y-2">
                    <button @click="saveDraft(true); closeConfirmOpen = false; window.close();"
                            class="w-full bg-blue-600 text-white py-3 rounded-lg text-sm font-bold hover:bg-blue-700 transition-all flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i> 下書き保存して閉じる
                    </button>
                    <button @click="discardAndClose()"
                            class="w-full bg-white border border-red-200 text-red-600 py-3 rounded-lg text-sm font-bold hover:bg-red-50 transition-all flex items-center justify-center gap-2">
                        <i class="fas fa-trash"></i> 破棄して閉じる
                    </button>
                    <button @click="closeConfirmOpen = false"
                            class="w-full bg-gray-50 text-gray-600 py-3 rounded-lg text-sm font-bold hover:bg-gray-100 transition-all">
                        キャンセル
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- 送信完了 (window.close失敗時のフォールバック) --}}
    <template x-if="sentCompleted">
        <div class="fixed inset-0 z-[2500] flex items-center justify-center bg-black/70 backdrop-blur-md p-4">
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden text-center">
                <div class="px-8 py-10">
                    <div class="w-16 h-16 mx-auto bg-green-100 rounded-full flex items-center justify-center text-green-600 shadow-inner mb-5">
                        <i class="fas fa-check fa-2x"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">送信が完了しました</h3>
                    <p class="text-sm text-gray-500 mt-2">承認待ちとして登録されました。<br>このタブを閉じてください。</p>
                </div>
                <div class="px-8 pb-8">
                    <button @click="window.close()" class="w-full bg-blue-600 text-white py-3 rounded-xl text-sm font-bold hover:bg-blue-700 transition-all">タブを閉じる</button>
                </div>
            </div>
        </div>
    </template>

    {{-- トースト通知 --}}
    <div class="fixed bottom-6 right-6 z-[3000] flex flex-col gap-2 pointer-events-none">
        <template x-for="t in toasts" :key="t.id">
            <div :class="{
                    'bg-green-600 text-white': t.type === 'success',
                    'bg-red-600 text-white': t.type === 'error',
                    'bg-gray-900 text-white': t.type !== 'success' && t.type !== 'error'
                 }"
                 class="px-5 py-3 rounded-xl shadow-2xl text-sm font-semibold flex items-center gap-3 max-w-md pointer-events-auto">
                <i class="fas" :class="t.type === 'success' ? 'fa-check-circle' : (t.type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle')"></i>
                <span x-text="t.message" class="whitespace-pre-line"></span>
            </div>
        </template>
    </div>
</div>

<script>
function composeWindowApp() {
    return {
        mode:    @json($mode),
        email:   @json($email),
        thread:  @json($thread),
        emails:  @json($emails),
        aiSkills: @json(config('ai_skills.skills', [])),
        form: {
            from:    @json($defaultFrom),
            to:      @json($replyTo),
            cc:      @json($replyCc),
            bcc:     @json($replyBcc),
            subject: @json($replySubject),
            body:    '',
        },
        selectedFiles: [],
        attachmentError: null,
        sending: false,
        sentCompleted: false,
        closeConfirmOpen: false,
        aiPanelOpen: false,
        aiSkill: 'reply',
        aiUserPrompt: '',
        aiAnalysis: null,
        aiLoading: false,
        maskPii: true,
        toasts: [],
        lastSavedAt: null,
        initialBody: '',
        draftKey: '',

        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },
        get headerLabel() {
            if (this.mode === 'compose') return '新規メッセージ作成';
            if (this.mode === 'reply_all') return '全員に返信';
            return '返信';
        },
        get pastEmails() {
            if (!this.email) return [];
            return (this.emails || []).filter(e => e.id !== this.email.id);
        },
        get totalBytes() {
            return this.selectedFiles.reduce((acc, f) => acc + (f.size || 0), 0);
        },
        get totalSizeLabel() {
            if (this.selectedFiles.length === 0) return '';
            return `合計 ${this.formatBytes(this.totalBytes)} / 20MB`;
        },
        get canAskAi() {
            return !!(this.email && this.email.id);
        },
        get isDirty() {
            return !!(this.form.body || this.selectedFiles.length > 0
                || (this.form.body !== this.initialBody)
                || (this.mode === 'compose' && (this.form.to || this.form.subject)));
        },
        get lastSavedLabel() {
            if (!this.lastSavedAt) return '';
            return `下書き保存: ${this.lastSavedAt}`;
        },

        init() {
            this.draftKey = this.buildDraftKey();
            this.loadDraft();
            this.initialBody = this.form.body;

            // 30秒ごとに自動保存
            setInterval(() => {
                if (this.form.body || this.selectedFiles.length > 0) this.saveDraft(false);
            }, 30000);

            // 未保存確認 (タブ閉じる/リロード)
            window.addEventListener('beforeunload', (e) => {
                if (!this.sending && !this.sentCompleted && this.isDirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },

        buildDraftKey() {
            if (this.mode === 'compose') return 'compose_draft__new';
            if (this.email && this.email.id) return `compose_draft__${this.mode}__${this.email.id}`;
            return 'compose_draft__unknown';
        },
        saveDraft(showToast = false) {
            try {
                const data = {
                    from: this.form.from, to: this.form.to, cc: this.form.cc, bcc: this.form.bcc,
                    subject: this.form.subject, body: this.form.body, savedAt: new Date().toISOString(),
                };
                localStorage.setItem(this.draftKey, JSON.stringify(data));
                this.lastSavedAt = new Date().toLocaleTimeString();
                if (showToast) this.toast('下書きを保存しました', 'success');
            } catch (_) {
                if (showToast) this.toast('下書き保存に失敗しました', 'error');
            }
        },
        loadDraft() {
            try {
                const raw = localStorage.getItem(this.draftKey);
                if (!raw) return;
                const d = JSON.parse(raw);
                if (!d) return;
                if (d.from)    this.form.from    = d.from;
                if (d.to)      this.form.to      = d.to;
                if (d.cc)      this.form.cc      = d.cc;
                if (d.bcc)     this.form.bcc     = d.bcc;
                if (d.subject) this.form.subject = d.subject;
                if (d.body)    this.form.body    = d.body;
            } catch (_) {}
        },
        clearDraft() {
            try { localStorage.removeItem(this.draftKey); } catch(_) {}
        },

        toast(message, type = 'info') {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, type });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 3500);
        },

        formatBytes(bytes) {
            if (bytes < 1024) return `${bytes} B`;
            if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
            return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
        },

        handleFileSelect(e) {
            const files = Array.from(e.target.files || []);
            const MAX = 20 * 1024 * 1024;
            let total = this.totalBytes;
            const errors = [];
            files.forEach(f => {
                if (f.size > MAX) {
                    errors.push(`${f.name} が上限(20MB)を超えています。`);
                } else if (total + f.size > MAX) {
                    errors.push(`${f.name} を含めると 20MB を超えます。`);
                } else {
                    this.selectedFiles.push(f);
                    total += f.size;
                }
            });
            if (errors.length > 0) {
                this.attachmentError = {
                    title: '添付不可',
                    message: errors.join('\n'),
                    totalSize: this.formatBytes(total),
                };
            }
            e.target.value = '';
        },
        removeSelectedFile(i) { this.selectedFiles.splice(i, 1); },

        toggleAi() {
            if (!this.canAskAi && !this.aiPanelOpen) {
                this.toast('新規作成ではAIアシスタントは利用できません', 'error');
                return;
            }
            this.aiPanelOpen = !this.aiPanelOpen;
        },
        async askAi() {
            if (!this.canAskAi) { this.toast('返信対象が必要です', 'error'); return; }
            this.aiLoading = true; this.aiAnalysis = null;
            try {
                const res = await fetch(`/emails/${this.email.id}/ai`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ prompt: this.aiUserPrompt, skill: this.aiSkill, mask_pii: this.maskPii }),
                });
                if (!res.ok) throw new Error(`AI Server Error (${res.status})`);
                const data = await res.json();
                this.simulateStreaming(data);
            } catch (e) {
                this.toast('AI生成に失敗しました: ' + (e.message || ''), 'error');
                this.aiLoading = false;
            }
        },
        simulateStreaming(data) {
            const fullText = data.answer || '';
            let i = 0;
            this.aiAnalysis = { ...data, answer: '' };
            const interval = setInterval(() => {
                if (i < fullText.length) {
                    this.aiAnalysis.answer += fullText[i++];
                } else {
                    clearInterval(interval);
                    this.aiLoading = false;
                }
            }, 5);
        },
        applyAiDraft() {
            if (!this.aiAnalysis?.answer) return;
            this.form.body = (this.form.body ? this.form.body + '\n\n' : '') + this.aiAnalysis.answer;
            this.toast('本文に反映しました', 'success');
        },

        async submitDraft() {
            if (!this.form.body || this.sending) return;
            this.sending = true;
            const fd = new FormData();
            fd.append('body', this.form.body);
            fd.append('to', this.form.to);
            fd.append('from_address', this.form.from || '');
            fd.append('cc', this.form.cc || '');
            fd.append('bcc', this.form.bcc || '');
            fd.append('subject', this.form.subject || '');
            this.selectedFiles.forEach(f => fd.append('attachments[]', f));

            const url = (this.mode === 'compose' || !this.email)
                ? '/emails/compose'
                : `/emails/${this.email.id}/reply`;

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    body: fd,
                });
                let data = {};
                try { data = await res.json(); } catch(_) {}

                if (res.ok) {
                    this.clearDraft();
                    this.notifyOpener();
                    // window.close() はユーザー操作で開いたタブのみ機能。失敗時は完了画面に切替。
                    setTimeout(() => {
                        try { window.close(); } catch(_) {}
                        this.sentCompleted = true;
                    }, 200);
                    this.toast('承認待ちとして送信しました', 'success');
                } else if (res.status === 422) {
                    const errs = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || '入力内容に誤りがあります');
                    this.toast('入力エラー: ' + errs, 'error');
                } else {
                    this.toast('送信失敗: ' + (data.message || data.error || `HTTP ${res.status}`), 'error');
                }
            } catch (e) {
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            } finally {
                this.sending = false;
            }
        },

        notifyOpener() {
            try {
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({ type: 'rice-mail-sent', mode: this.mode }, window.location.origin);
                }
            } catch (_) {}
        },

        attemptClose() {
            if (this.isDirty) {
                this.closeConfirmOpen = true;
                return;
            }
            try { window.close(); } catch(_) {}
        },
        discardAndClose() {
            this.clearDraft();
            this.form.body = '';
            this.selectedFiles = [];
            this.closeConfirmOpen = false;
            try { window.close(); } catch(_) {}
        },
    };
}
</script>

<style>
[x-cloak] { display: none !important; }
.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
@endsection
