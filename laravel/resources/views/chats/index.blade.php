@extends('layouts.app')
@section('title', 'チャット一覧 - Rice')

@section('css')
<style>
    /* AdminLTE 既定の余白・ヘッダーを潰してチャットを画面いっぱいに
       注: .content-wrapper の margin-left はサイドバー幅ぶんなので絶対に潰さない */
    body.chats-page { overflow: hidden !important; }
    body.chats-page .content-header { display: none !important; }
    body.chats-page .main-footer { display: none !important; }
    body.chats-page .content-wrapper {
        padding: 0 !important;
        overflow: hidden !important;
    }
    body.chats-page .content,
    body.chats-page .content > .container-fluid {
        padding: 0 !important;
        max-width: none !important;
        width: 100% !important;
        height: calc(100vh - 3.5rem) !important;
        min-height: 0 !important;
        overflow: hidden !important;
        background: #f9fafb;
    }
    body.chats-page .content > .container-fluid { height: 100% !important; }

    /* ルート: 横スクロールを完全に抑止 */
    .chats-root {
        height: 100%;
        width: 100%;
        min-width: 0;
        min-height: 0;
        overflow: hidden;
    }

    /* line-clamp の Tailwind 不在対策 (素の CSS で明示) */
    .chats-root .clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        word-break: break-word;
    }

    /* スクロールバー */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }

    [x-cloak] { display: none !important; }
</style>

@endsection

@section('content')
{{-- このページの間だけ body にクラスを付ける (CSS スコープ用) --}}
<script>
    document.body.classList.add('chats-page');
    window.addEventListener('beforeunload', function() {
        document.body.classList.remove('chats-page');
    });
</script>

<div class="chats-root flex bg-gray-50" x-data="threadChatApp()" x-init="init()" x-cloak>

    {{-- 左ペイン: チャットがあるスレッドのリスト (メール一覧と同じパネルスタイル) --}}
    <aside class="flex flex-col flex-shrink-0 overflow-hidden bg-white border-r border-gray-200 relative z-20 shadow-sm min-h-0"
           :style="'width:' + panelWidth + 'px'">

        {{-- ヘッダー (タイトル + 更新 + 検索) --}}
        <div class="shrink-0 px-4 py-3 border-b border-gray-200 bg-white flex flex-col gap-2 relative">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-extrabold text-gray-900 inline-flex items-center gap-2 truncate">
                    <i class="fas fa-comments text-emerald-500 text-xs"></i> チャット一覧
                </h2>
                <button @click="load()"
                    class="h-9 w-9 inline-flex items-center justify-center rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-all"
                    :class="{ 'animate-spin text-emerald-600': loading }"
                    title="一覧を更新">
                    <i class="fas fa-sync-alt text-sm"></i>
                </button>
            </div>
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                <input type="text" x-model="searchQuery" @input.debounce.300ms="load()"
                       placeholder="件名・本文で検索..."
                       class="w-full pl-8 pr-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-300">
            </div>
        </div>

        {{-- フィルタタブ (メール一覧と同じスタイル) --}}
        <div class="shrink-0 px-3 py-2 border-b border-gray-200 bg-gray-50 flex items-center gap-2">
            <div class="flex items-center gap-1 bg-gray-200/50 p-1 rounded-xl shadow-inner flex-1 overflow-hidden">
                <button @click="setFilter('all')"
                        :class="filter === 'all' ? 'bg-white shadow text-emerald-600' : 'text-gray-500 hover:text-gray-800'"
                        class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all truncate">すべて</button>
                <button @click="setFilter('mentioned')"
                        :class="filter === 'mentioned' ? 'bg-white shadow text-amber-600' : 'text-gray-500 hover:text-gray-800'"
                        class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all truncate">自分宛</button>
            </div>
        </div>

        {{-- スレッドリスト (メール一覧と同じフラット行スタイル) --}}
        <div class="flex-1 min-h-0 overflow-y-auto bg-white custom-scrollbar relative">
            <template x-if="loading">
                <div class="flex items-center justify-center py-12 text-emerald-300">
                    <i class="fas fa-circle-notch fa-spin fa-lg"></i>
                </div>
            </template>
            <template x-if="!loading && threads.length === 0">
                <div class="text-center py-16 px-6 text-gray-400">
                    <i class="fas fa-comment-slash fa-2x text-gray-200 mb-3"></i>
                    <p class="text-sm font-semibold text-gray-600">
                        <span x-show="filter === 'all'">チャットのあるスレッドはありません</span>
                        <span x-show="filter === 'mentioned'">@自分宛のメッセージはありません</span>
                        <span x-show="filter === 'mine'">自分の発言はありません</span>
                    </p>
                    <p class="text-[11px] text-gray-400 mt-1">受信トレイのスレッドでチャットを始めると、ここに表示されます。</p>
                </div>
            </template>
            <template x-for="t in threads" :key="t.id">
                <div @click="selectThread(t)"
                     class="group/row w-full cursor-pointer border-b border-gray-100 hover:bg-blue-50 transition-all duration-200 relative"
                     :class="selectedThreadId === t.id ? 'bg-blue-50 ring-1 ring-inset ring-blue-200' : ''">
                    <div class="px-5 py-2 flex flex-col justify-center gap-1">
                        {{-- 1 行目: 件名 + 日時 --}}
                        <div class="flex items-center gap-2 min-w-0">
                            <i x-show="t.is_pinned" class="fas fa-thumbtack text-amber-500 text-[10px] shrink-0"></i>
                            <span class="text-[12px] font-bold text-gray-900 truncate flex-1" x-text="t.subject"></span>
                            <span class="text-[10px] text-gray-400 font-medium shrink-0 whitespace-nowrap" x-text="t.last_comment?.created_at"></span>
                        </div>
                        {{-- 2 行目: 最新コメント著者 + プレビュー --}}
                        <div class="text-[11px] text-gray-700 font-medium leading-snug break-words flex items-start gap-1.5"
                             style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                            <span class="text-[10px] font-bold shrink-0 max-w-[80px] truncate"
                                  :class="t.last_comment?.is_mine ? 'text-emerald-600' : 'text-gray-500'"
                                  x-text="(t.last_comment?.author || '') + ':'"></span>
                            <span class="text-gray-600 min-w-0 flex-1" x-text="t.last_comment?.preview"></span>
                        </div>
                        {{-- 3 行目: メタデータ (メンション数 / コメント数 / 担当者) --}}
                        <div class="flex items-center gap-1.5 flex-wrap min-h-[18px]">
                        <template x-if="t.mention_count > 0">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-100 text-amber-700 border border-amber-200 shrink-0">
                                <i class="fas fa-at"></i><span x-text="t.mention_count"></span>
                            </span>
                        </template>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold bg-gray-100 text-gray-600 border border-gray-200 shrink-0">
                            <i class="fas fa-comment-dots"></i><span x-text="t.comment_count"></span>
                        </span>
                        <template x-if="t.assignee">
                            <span class="bg-gray-100 px-2 py-0.5 rounded text-[9px] font-black text-gray-600 border border-gray-200 inline-flex items-center gap-1 shadow-sm max-w-[140px]">
                                <i class="fas fa-user-circle text-gray-400 text-[8px]"></i>
                                <span class="truncate" x-text="t.assignee.name"></span>
                            </span>
                        </template>
                        </div>
                    </div>
                    {{-- 選択中の左ライン --}}
                    <div x-show="selectedThreadId === t.id" class="absolute left-0 top-0 w-1.5 h-full bg-blue-600"></div>
                </div>
            </template>
        </div>
        {{-- ドラッグリサイズハンドル (メール一覧と同じ) --}}
        <div class="absolute top-0 right-0 w-1 h-full cursor-col-resize hover:bg-blue-500 z-50"
             @mousedown.prevent="startResizePanel($event)"></div>
    </aside>

    {{-- 右ペイン: 選択スレッドのチャット --}}
    <main class="flex-1 flex flex-col min-w-0 min-h-0 bg-emerald-50/20 overflow-hidden" style="height:100%;width:0;">

        {{-- 空状態 (スレッド未選択) --}}
        <div x-show="!selectedThreadId" class="flex-1 flex flex-col items-center justify-center text-gray-300 px-6"
             style="min-height:0;">
            <div class="w-20 h-20 rounded-3xl bg-white shadow-xl flex items-center justify-center text-emerald-300 mb-4">
                <i class="fas fa-comments fa-2x"></i>
            </div>
            <p class="text-base font-bold text-gray-700">スレッドを選択してください</p>
            <p class="text-xs text-gray-400 mt-1">左のリストから対話するスレッドを選びます</p>
        </div>

        {{-- スレッドヘッダー --}}
        <div x-show="selectedThreadId"
             class="shrink-0 px-5 py-3 bg-white border-b border-gray-200 flex items-center justify-between gap-3 min-w-0">
            <div class="min-w-0 flex-1 overflow-hidden">
                <h2 class="text-base font-extrabold text-gray-900 truncate" x-text="selectedThread?.subject || ''"></h2>
                <div class="flex items-center gap-2 mt-1 flex-wrap min-w-0">
                    <template x-if="selectedThread?.assignee">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200 max-w-[180px]">
                            <i class="fas fa-user-circle shrink-0"></i>
                            <span class="truncate">担当: <span x-text="selectedThread.assignee.name"></span></span>
                        </span>
                    </template>
                    <template x-if="selectedThread?.customer_name">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-700 border border-gray-200 max-w-[200px]">
                            <i class="fas fa-building shrink-0"></i>
                            <span class="truncate" x-text="selectedThread.customer_name"></span>
                        </span>
                    </template>
                    <span class="text-[10px] text-gray-400 whitespace-nowrap">
                        最終メール: <span x-text="selectedThread?.thread_last_email_at || '—'"></span>
                    </span>
                </div>
            </div>
            {{-- 元メールへのリンク (新規タブで開いて、現在のチャット画面はそのまま残す) --}}
            <a :href="`/?thread=${selectedThreadId}`" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 transition-all shrink-0 shadow-sm"
               title="このチャットの元メールスレッドを新規タブで開く">
                <i class="fas fa-envelope"></i><span>元メールを開く</span><i class="fas fa-external-link-alt text-[10px] opacity-75"></i>
            </a>
        </div>

        {{-- メッセージ一覧 (スクロール領域) --}}
        <div x-show="selectedThreadId"
             class="custom-scrollbar"
             id="chat-hub-messages"
             style="flex:1 1 auto;min-height:0;overflow-y:auto;padding:24px;background:#f9fafb;">

            {{-- ローディング --}}
            <div x-show="chatLoading" class="flex items-center justify-center py-8 text-emerald-300">
                <i class="fas fa-circle-notch fa-spin fa-lg"></i>
            </div>

            {{-- 空メッセージ --}}
            <div x-show="!chatLoading && chatComments.length === 0"
                 class="text-center py-16 text-gray-400">
                <i class="fas fa-comment-slash fa-2x text-gray-200 mb-3"></i>
                <p class="text-sm font-semibold">まだメッセージがありません</p>
                <p class="text-[11px] text-gray-400 mt-1">下から最初のメッセージを送ってみましょう</p>
            </div>

            {{-- メッセージリスト --}}
            <div class="space-y-3" x-show="!chatLoading && chatComments.length > 0">
                <template x-for="c in chatComments" :key="c.id">
                    <div class="flex" :class="c.is_author ? 'justify-end' : 'justify-start'"
                         :id="'comment-' + c.id">
                        <div class="max-w-[75%] group">
                            <div class="flex items-center gap-2 mb-1"
                                 :class="c.is_author ? 'justify-end' : 'justify-start'">
                                <span class="text-[11px] font-bold text-gray-700" x-text="c.author"></span>
                                <span class="text-[10px] text-gray-400" x-text="c.created_at"></span>
                                <template x-if="isMentionedToMe(c.content)">
                                    <span class="text-[9px] font-black px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 border border-amber-200">
                                        @あなた宛
                                    </span>
                                </template>
                            </div>
                            <div @click="openDetail(c)"
                                 class="rounded-2xl px-4 py-2.5 text-sm whitespace-pre-wrap break-words leading-relaxed shadow-sm cursor-pointer transition-transform hover:-translate-y-px hover:shadow-md"
                                 :style="c.is_author
                                    ? 'background-color:#10b981;color:#ffffff;'
                                    : 'background-color:#ffffff;color:#1f2937;border:1px solid #e5e7eb;'"
                                 :class="highlightedCommentId === c.id ? 'ring-4 ring-amber-300 ring-offset-2 ring-offset-emerald-50' : ''"
                                 title="クリックで詳細を表示"
                                 x-html="renderMentions(c.content, c.is_author)"></div>
                            <div class="mt-1" :class="c.is_author ? 'text-right' : 'text-left'" x-show="c.is_author">
                                <button @click="deleteComment(c.id)"
                                        class="text-[10px] text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity"
                                        title="削除">
                                    <i class="fas fa-trash"></i> 削除
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- 入力エリア (常に下部に固定) --}}
        <div x-show="selectedThreadId"
             class="shrink-0 border-t border-emerald-100 bg-white p-4 relative">
            {{-- メンション候補 --}}
            <div x-show="mentionOpen && mentionMatches.length > 0"
                 class="absolute left-4 right-4 bottom-full mb-2 bg-white border border-gray-200 rounded-xl shadow-2xl overflow-hidden max-h-56 overflow-y-auto custom-scrollbar z-50">
                <div class="px-3 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 bg-gray-50/60">
                    @メンション (↑↓ で移動 / Enter で選択 / Esc でキャンセル)
                </div>
                <template x-for="(u, i) in mentionMatches" :key="u.id">
                    <button type="button"
                            @click.stop="pickMention(u)"
                            @mouseenter="mentionIndex = i"
                            :class="mentionIndex === i ? 'bg-emerald-50 text-emerald-700' : 'text-gray-700 hover:bg-gray-50'"
                            class="w-full text-left px-3 py-2 text-sm font-semibold flex items-center gap-2 transition-colors">
                        <div class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs shrink-0"
                             x-text="(u.name || '?').charAt(0)"></div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate" x-text="u.name"></p>
                            <p class="text-[10px] text-gray-400 truncate" x-text="u.email"></p>
                        </div>
                        <i x-show="mentionIndex === i" class="fas fa-arrow-turn-down text-[10px] text-emerald-500"></i>
                    </button>
                </template>
            </div>

            <div class="flex items-end gap-2">
                <textarea id="chat-hub-input"
                          x-model="chatInput"
                          rows="2"
                          @input="onChatInput($event)"
                          @keydown.arrow-up="onMentionKeydown($event, 'up')"
                          @keydown.arrow-down="onMentionKeydown($event, 'down')"
                          @keydown.escape="closeMention()"
                          @keydown.enter.exact.prevent="onChatEnter()"
                          @keydown.enter.shift="" @keydown.enter.meta="" @keydown.enter.ctrl=""
                          placeholder="メッセージを入力 (@で担当者をメンション / Enterで送信 / Shift+Enterで改行)"
                          style="color:#111827;background-color:#f9fafb;"
                          class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-300 resize-none"></textarea>
                <button @click="sendComment()"
                        :disabled="!chatInput.trim() || chatSending"
                        class="h-11 w-11 inline-flex items-center justify-center rounded-xl text-white shadow-md disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
                        style="background-color:#10b981;">
                    <i class="fas" :class="chatSending ? 'fa-spinner animate-spin' : 'fa-paper-plane'"></i>
                </button>
            </div>
            <p class="text-[10px] text-gray-400 mt-1.5">
                <i class="fas fa-at mr-1"></i><span class="font-bold">@名前</span> でメンション
            </p>
        </div>
    </main>

    {{-- チャット詳細モーダル --}}
    <div x-show="detail" x-cloak
         @keydown.escape.window="closeDetail()"
         class="fixed inset-0 z-[100] flex items-center justify-center p-4"
         style="background-color:rgba(15,23,42,0.55);">
        <div @click.outside="closeDetail()"
             class="w-full max-w-xl rounded-2xl shadow-2xl overflow-hidden flex flex-col"
             style="background-color:#ffffff;max-height:85vh;">
            {{-- ヘッダー --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100"
                 style="background-color:#f9fafb;">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm shrink-0"
                         style="background-color:#d1fae5;color:#047857;"
                         x-text="(detail?.author || '?').charAt(0)"></div>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold truncate" style="color:#111827;" x-text="detail?.author || '不明'"></p>
                        <p class="text-[10px] truncate" style="color:#6b7280;" x-text="detail?.created_at || ''"></p>
                    </div>
                </div>
                <button @click="closeDetail()"
                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg transition-colors"
                        style="color:#9ca3af;"
                        onmouseover="this.style.backgroundColor='#f3f4f6';this.style.color='#374151';"
                        onmouseout="this.style.backgroundColor='';this.style.color='#9ca3af';"
                        title="閉じる">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            {{-- バッジ群 --}}
            <div class="flex items-center gap-2 flex-wrap px-5 py-2 border-b border-gray-100"
                 style="background-color:#ffffff;">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold"
                      :style="detail?.is_author
                        ? 'background-color:#d1fae5;color:#047857;border:1px solid #a7f3d0;'
                        : 'background-color:#f3f4f6;color:#374151;border:1px solid #e5e7eb;'">
                    <i class="fas" :class="detail?.is_author ? 'fa-user-check' : 'fa-user'"></i>
                    <span x-text="detail?.is_author ? '自分の発言' : '他のユーザー'"></span>
                </span>
                <template x-if="detail && isMentionedToMe(detail.content)">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold"
                          style="background-color:#fef3c7;color:#92400e;border:1px solid #fde68a;">
                        <i class="fas fa-at"></i> あなた宛のメンション
                    </span>
                </template>
                <template x-if="selectedThread?.subject">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold max-w-[260px]"
                          style="background-color:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;">
                        <i class="fas fa-envelope shrink-0"></i>
                        <span class="truncate" x-text="selectedThread.subject"></span>
                    </span>
                </template>
            </div>

            {{-- 本文 --}}
            <div class="flex-1 overflow-y-auto custom-scrollbar px-5 py-4"
                 style="background-color:#ffffff;">
                <p class="text-[10px] font-bold uppercase tracking-widest mb-2" style="color:#9ca3af;">本文</p>
                <div class="rounded-xl p-4 text-sm whitespace-pre-wrap break-words leading-relaxed"
                     style="background-color:#f9fafb;color:#111827;border:1px solid #e5e7eb;"
                     x-html="detail ? renderMentions(detail.content, false) : ''"></div>

                <p class="text-[10px] font-bold uppercase tracking-widest mt-4 mb-2" style="color:#9ca3af;">メタ情報</p>
                <dl class="grid grid-cols-3 gap-2 text-xs">
                    <dt class="font-bold" style="color:#6b7280;">投稿者</dt>
                    <dd class="col-span-2" style="color:#111827;" x-text="detail?.author || '不明'"></dd>
                    <dt class="font-bold" style="color:#6b7280;">投稿日時</dt>
                    <dd class="col-span-2" style="color:#111827;" x-text="detail?.created_at || ''"></dd>
                    <dt class="font-bold" style="color:#6b7280;">スレッド</dt>
                    <dd class="col-span-2" style="color:#111827;" x-text="selectedThread?.subject || ''"></dd>
                    <template x-if="selectedThread?.assignee">
                        <div class="contents">
                            <dt class="font-bold" style="color:#6b7280;">担当</dt>
                            <dd class="col-span-2 truncate" style="color:#111827;" x-text="selectedThread.assignee.name"></dd>
                        </div>
                    </template>
                    <template x-if="selectedThread?.customer_name">
                        <div class="contents">
                            <dt class="font-bold" style="color:#6b7280;">顧客</dt>
                            <dd class="col-span-2 truncate" style="color:#111827;" x-text="selectedThread.customer_name"></dd>
                        </div>
                    </template>
                </dl>
            </div>

            {{-- フッター操作 --}}
            <div class="flex items-center justify-between gap-2 px-5 py-3 border-t border-gray-100"
                 style="background-color:#f9fafb;">
                <div class="flex items-center gap-2">
                    <button @click="copyDetail()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
                            style="background-color:#ffffff;color:#374151;border:1px solid #d1d5db;"
                            onmouseover="this.style.backgroundColor='#f3f4f6';"
                            onmouseout="this.style.backgroundColor='#ffffff';">
                        <i class="fas" :class="detailCopied ? 'fa-check' : 'fa-copy'"></i>
                        <span x-text="detailCopied ? 'コピーしました' : '本文をコピー'"></span>
                    </button>
                    <a :href="`/?thread=${selectedThreadId}`" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors text-white"
                       style="background-color:#2563eb;"
                       onmouseover="this.style.backgroundColor='#1d4ed8';"
                       onmouseout="this.style.backgroundColor='#2563eb';"
                       title="このチャットの元メールスレッドを新規タブで開く">
                        <i class="fas fa-envelope"></i> 元メールを開く <i class="fas fa-external-link-alt text-[10px] opacity-75"></i>
                    </a>
                </div>
                <div class="flex items-center gap-2">
                    <template x-if="detail?.is_author">
                        <button @click="deleteFromDetail()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
                                style="background-color:#fef2f2;color:#b91c1c;border:1px solid #fecaca;"
                                onmouseover="this.style.backgroundColor='#fee2e2';"
                                onmouseout="this.style.backgroundColor='#fef2f2';">
                            <i class="fas fa-trash"></i> 削除
                        </button>
                    </template>
                    <button @click="closeDetail()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white transition-colors"
                            style="background-color:#10b981;"
                            onmouseover="this.style.backgroundColor='#059669';"
                            onmouseout="this.style.backgroundColor='#10b981';">
                        閉じる
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function threadChatApp() {
    return {
        // 一覧
        loading: false,
        threads: [],
        searchQuery: '',
        filter: 'all',         // 'all' / 'mentioned' / 'mine'

        // 選択中スレッド
        selectedThreadId: null,
        selectedThread: null,

        // チャット
        chatComments: [],
        chatLoading: false,
        chatInput: '',
        chatSending: false,
        chatPollIntervalId: null,

        // ユーザー一覧 (メンション用)
        users: [],

        // メンション
        mentionOpen: false, mentionQuery: '', mentionStart: -1, mentionIndex: 0,

        // チャット詳細モーダル
        detail: null,
        detailCopied: false,

        // 通知ベルからの遷移でハイライトするコメント ID
        highlightedCommentId: null,
        // 次の loadComments() 完了後にスクロール対象とするコメント ID (一時保持)
        pendingScrollCommentId: null,

        // 左パネル幅 (ドラッグで調整可能、localStorage に永続化)
        panelWidth: parseInt(localStorage.getItem('chatsPanelWidth')) || 340,

        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },
        jsonHeaders() {
            return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' };
        },

        async init() {
            await Promise.all([this.load(), this.loadUsers()]);
            // URL ハッシュ (#thread-123) からスレッドを自動選択
            this.restoreFromHash();
            // ブラウザの戻る/進むに対応
            window.addEventListener('hashchange', () => this.restoreFromHash());
            // タブを離れる時にポーリング停止
            window.addEventListener('beforeunload', () => this.stopPolling());
        },

        restoreFromHash() {
            // 受け付けるハッシュ:
            //   #thread-<id>
            //   #thread-<id>&comment=<commentId>  (ベル通知から特定コメントへ遷移)
            const raw = location.hash || '';
            const m = raw.match(/^#thread-(\d+)(?:&comment=(\d+))?$/);
            if (!m) {
                if (this.selectedThreadId !== null) {
                    this.selectedThreadId = null;
                    this.selectedThread = null;
                    this.chatComments = [];
                    this.stopPolling();
                }
                return;
            }
            const id = parseInt(m[1], 10);
            const commentId = m[2] ? parseInt(m[2], 10) : null;
            // 同じスレッドだが特定コメントへスクロールしたい場合
            if (this.selectedThreadId === id) {
                if (commentId) this.scrollToComment(commentId);
                return;
            }
            this.pendingScrollCommentId = commentId;
            const t = this.threads.find(x => x.id === id);
            if (t) {
                this.selectThread(t, /*updateHash*/ false);
            } else {
                // 一覧フィルタで非表示でも、IDだけで開けるようにする
                this.selectThread({ id, subject: '読み込み中...', assignee: null, customer_name: null, thread_last_email_at: null }, false);
            }
        },

        // 指定 ID のコメントへスクロール + 一時ハイライト
        scrollToComment(commentId) {
            if (!commentId) return;
            this.$nextTick(() => {
                const el = document.getElementById('comment-' + commentId);
                if (!el) return;
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                this.highlightedCommentId = commentId;
                // 数秒後に強調表示を解除
                setTimeout(() => {
                    if (this.highlightedCommentId === commentId) {
                        this.highlightedCommentId = null;
                    }
                }, 3000);
            });
        },

        async loadUsers() {
            try {
                const res = await fetch('/users', { headers: { 'Accept': 'application/json' } });
                if (res.ok) this.users = await res.json();
            } catch (e) {}
        },

        async load() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.searchQuery) params.set('q', this.searchQuery);
                if (this.filter === 'mentioned') params.set('mentioned', '1');
                if (this.filter === 'mine')      params.set('mine', '1');
                const res = await fetch('/chats/threads?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                this.threads = data.threads || [];
                // 選択中スレッドがリストにいればメタ情報を更新 (件名・最新コメント等の同期)
                if (this.selectedThreadId) {
                    const fresh = this.threads.find(t => t.id === this.selectedThreadId);
                    if (fresh) this.selectedThread = fresh;
                }
            } catch (e) {
                console.error('Failed to load threads:', e);
            } finally {
                this.loading = false;
            }
        },

        setFilter(f) {
            if (this.filter === f) return;
            this.filter = f;
            this.load();
        },

        // 左パネルのドラッグリサイズ (260〜600px)
        startResizePanel(e) {
            const startX = e.clientX, startW = this.panelWidth;
            const onMove = (me) => {
                this.panelWidth = Math.max(260, Math.min(600, startW + (me.clientX - startX)));
            };
            const onUp = () => {
                localStorage.setItem('chatsPanelWidth', this.panelWidth);
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        async selectThread(t, updateHash = true) {
            this.selectedThreadId = t.id;
            this.selectedThread = t;
            this.chatComments = [];
            this.chatInput = '';
            this.closeMention();
            if (updateHash) {
                const newHash = '#thread-' + t.id;
                if (location.hash !== newHash) location.hash = newHash;
            }
            await this.loadComments();
            this.startPolling();
        },

        async loadComments(silent = false) {
            if (!this.selectedThreadId) return;
            if (!silent) this.chatLoading = true;
            try {
                const res = await fetch(`/threads/${this.selectedThreadId}/comments`, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                const before = this.chatComments.length;
                this.chatComments = data.comments || [];
                // 通知ベルから特定コメントを開く要求があれば優先してスクロール
                if (!silent && this.pendingScrollCommentId) {
                    const target = this.pendingScrollCommentId;
                    this.pendingScrollCommentId = null;
                    this.scrollToComment(target);
                } else if (!silent || this.chatComments.length > before) {
                    this.$nextTick(() => this.scrollToBottom(silent));
                }
            } catch (e) {
                if (!silent) console.error(e);
            } finally {
                if (!silent) this.chatLoading = false;
            }
        },

        async sendComment() {
            const text = this.chatInput.trim();
            if (!text || !this.selectedThreadId || this.chatSending) return;
            this.chatSending = true;
            try {
                const res = await fetch(`/threads/${this.selectedThreadId}/comments`, {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({ content: text }),
                });
                const data = await res.json();
                if (!res.ok || data.status === 'error') {
                    alert(data.message || '送信に失敗しました');
                    return;
                }
                if (data.comment) this.chatComments.push(data.comment);
                this.chatInput = '';
                this.closeMention();
                this.$nextTick(() => this.scrollToBottom());
                // 一覧の最新プレビューも更新
                this.load();
            } catch (e) {
                alert('通信エラー: ' + (e.message || ''));
            } finally {
                this.chatSending = false;
            }
        },

        // ============= チャット詳細モーダル =============
        openDetail(c) {
            this.detail = c;
            this.detailCopied = false;
        },
        closeDetail() {
            this.detail = null;
            this.detailCopied = false;
        },
        async copyDetail() {
            if (!this.detail) return;
            try {
                await navigator.clipboard.writeText(this.detail.content || '');
                this.detailCopied = true;
                setTimeout(() => { this.detailCopied = false; }, 1500);
            } catch (e) {
                alert('コピーに失敗しました');
            }
        },
        async deleteFromDetail() {
            if (!this.detail) return;
            const id = this.detail.id;
            await this.deleteComment(id);
            // deleteComment 内で chatComments から除去済み。詳細を閉じる
            if (!this.chatComments.find(c => c.id === id)) {
                this.closeDetail();
            }
        },

        async deleteComment(id) {
            if (!confirm('このメッセージを削除しますか？')) return;
            try {
                const res = await fetch(`/thread-comments/${id}`, {
                    method: 'DELETE',
                    headers: this.jsonHeaders(),
                });
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    alert(data.message || '削除に失敗しました');
                    return;
                }
                this.chatComments = this.chatComments.filter(c => c.id !== id);
                this.load();
            } catch (e) {}
        },

        startPolling() {
            this.stopPolling();
            this.chatPollIntervalId = setInterval(() => {
                if (!this.selectedThreadId) {
                    this.stopPolling();
                    return;
                }
                this.loadComments(true);
            }, 8000);
        },
        stopPolling() {
            if (this.chatPollIntervalId) {
                clearInterval(this.chatPollIntervalId);
                this.chatPollIntervalId = null;
            }
        },

        scrollToBottom(silent = false) {
            const el = document.getElementById('chat-hub-messages');
            if (!el) return;
            if (silent) {
                const distance = el.scrollHeight - (el.scrollTop + el.clientHeight);
                if (distance > 80) return;
            }
            el.scrollTop = el.scrollHeight;
        },

        // ============= @メンション (受信トレイと同じロジック) =============
        onChatInput(e) {
            const value = e.target.value;
            const cursor = e.target.selectionStart || 0;
            const before = value.slice(0, cursor);
            const match = before.match(/(?:^|[\s\n])@([^\s\n]*)$/) || before.match(/^@([^\s\n]*)$/);
            if (match) {
                this.mentionQuery = match[1] || '';
                this.mentionStart = cursor - this.mentionQuery.length - 1;
                this.mentionOpen = true;
                this.mentionIndex = 0;
            } else {
                this.closeMention();
            }
        },
        get mentionMatches() {
            if (!this.mentionOpen) return [];
            const q = (this.mentionQuery || '').toLowerCase();
            return (this.users || []).filter(u =>
                !q || (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q)
            ).slice(0, 8);
        },
        onMentionKeydown(e, dir) {
            if (!this.mentionOpen || this.mentionMatches.length === 0) return;
            e.preventDefault();
            if (dir === 'up') {
                this.mentionIndex = (this.mentionIndex - 1 + this.mentionMatches.length) % this.mentionMatches.length;
            } else {
                this.mentionIndex = (this.mentionIndex + 1) % this.mentionMatches.length;
            }
        },
        onChatEnter() {
            if (this.mentionOpen && this.mentionMatches.length > 0) {
                this.pickMention(this.mentionMatches[this.mentionIndex]);
            } else {
                this.sendComment();
            }
        },
        pickMention(user) {
            if (!user || this.mentionStart < 0) { this.closeMention(); return; }
            const value = this.chatInput;
            const before = value.slice(0, this.mentionStart);
            const ta = document.getElementById('chat-hub-input');
            const cursor = ta?.selectionStart ?? value.length;
            const after = value.slice(cursor);
            const inserted = '@' + user.name + ' ';
            this.chatInput = before + inserted + after;
            this.closeMention();
            this.$nextTick(() => {
                if (ta) {
                    const pos = (before + inserted).length;
                    ta.focus();
                    ta.setSelectionRange(pos, pos);
                }
            });
        },
        closeMention() {
            this.mentionOpen = false;
            this.mentionQuery = '';
            this.mentionStart = -1;
            this.mentionIndex = 0;
        },
        renderMentions(content, isAuthor) {
            const escape = (s) => String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            const escaped = escape(content);
            const names = (this.users || []).map(u => u.name).filter(Boolean).sort((a, b) => b.length - a.length);
            if (names.length === 0) return escaped;
            const reEsc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const pattern = new RegExp('@(' + names.map(reEsc).join('|') + ')(?=[\\s\\n.,!?。、]|$)', 'g');
            const cls = isAuthor
                ? 'bg-white/25 text-white font-bold rounded px-1'
                : 'bg-amber-100 text-amber-700 font-bold rounded px-1';
            return escaped.replace(pattern, '<span class="' + cls + '">@$1</span>');
        },
        isMentionedToMe(content) {
            const myName = @json(auth()->user()->name ?? '');
            if (!myName) return false;
            const reEsc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const re = new RegExp('@' + reEsc(myName) + '(?=[\\s\\n.,!?。、]|$)');
            return re.test(content || '');
        },
    };
}
</script>
@endsection
