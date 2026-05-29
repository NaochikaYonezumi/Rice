@extends('layouts.fullpage')
@section('title', $mode === 'compose' ? '新規メッセージ作成' : ($mode === 'forward' ? '転送' : ($mode === 'reply_all' ? '全員に返信' : '返信')))

@section('css')
{{-- 本文入力エリアを綺麗な日本語フォントで表示するため Noto Sans JP を読み込む.
     プリコネクト → display=swap で初期描画を遅らせない. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
<style>
    /* Alpine の x-cloak (初期化前要素を隠す) — Tailwind v4 デフォルトに無いため明示定義 */
    [x-cloak] { display: none !important; }

    /* compose-window ヘッダーは常にクリック可能であること (AI パネル等が誤って上に乗らないように) */
    .compose-window-header { position: relative; z-index: 30; }

    /* ===== 本文エディタのフォント =====
       旧 font-mono は等幅で日本語の見栄えが悪かったので、Noto Sans JP に変更。
       メール本文は読み手も Gmail / Outlook の通常 sans フォントで読むので、
       書き手側もそれに近い見た目で確認できた方がレイアウトの違和感が減る. */
    textarea[x-ref="bodyTextarea"] {
        font-family: "Noto Sans JP", "Hiragino Kaku Gothic ProN", "Yu Gothic", "Meiryo",
                     -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        font-feature-settings: "palt" 1; /* 自然なプロポーショナル詰め */
        letter-spacing: 0.01em;
    }

    /* ===== ダークモード上書き (compose-window) =====
       layouts.fullpage の基本セットだけでは textarea / 各フォーム要素まで完全に行き届かないので、
       本画面で使う具体的なセレクタにも個別に当てる。 */
    html.theme-dark textarea[x-ref="bodyTextarea"],
    html.theme-dark .compose-window-header,
    html.theme-dark main,
    html.theme-dark aside {
        background-color: #1e1f22 !important;
        color: #e5e7eb !important;
    }
    html.theme-dark textarea[x-ref="bodyTextarea"] {
        background-color: #2b2d31 !important;
        border-color: #3f4147 !important;
    }
    html.theme-dark .compose-window-header { border-bottom-color: #3f4147 !important; }

    /* ----- 却下バナー (赤系) ----- */
    html.theme-dark .bg-red-50 {
        background-color: #3a1a1f !important;
        color: #fca5a5 !important;
    }
    html.theme-dark .border-red-100,
    html.theme-dark .border-red-200 { border-color: #7f1d1d !important; }
    html.theme-dark .border-red-400 { border-color: #ef4444 !important; }
    html.theme-dark .text-red-600,
    html.theme-dark .text-red-700,
    html.theme-dark .text-red-800,
    html.theme-dark .text-red-900 { color: #fca5a5 !important; }

    /* ----- 承認者カード / モーダル (黄系) ----- */
    html.theme-dark .bg-amber-50,
    html.theme-dark .bg-amber-50\/40 {
        background-color: #2d2716 !important;
        color: #fde68a !important;
    }
    html.theme-dark .border-amber-100,
    html.theme-dark .border-amber-200 { border-color: #78350f !important; }
    html.theme-dark .text-amber-700,
    html.theme-dark .text-amber-800,
    html.theme-dark .text-amber-700\/80 { color: #fbbf24 !important; }

    /* ----- 添付チップ / 入力フォーム (緑 / 青) ----- */
    html.theme-dark .bg-emerald-50 {
        background-color: #102b1f !important;
        color: #6ee7b7 !important;
    }
    html.theme-dark .border-emerald-100,
    html.theme-dark .border-emerald-200 { border-color: #065f46 !important; }
    html.theme-dark .bg-blue-50,
    html.theme-dark .bg-indigo-50,
    html.theme-dark .bg-violet-50,
    html.theme-dark .bg-purple-50 {
        background-color: #1e293b !important;
        color: #93c5fd !important;
    }
    html.theme-dark .border-blue-100,
    html.theme-dark .border-blue-200,
    html.theme-dark .border-indigo-200 { border-color: #1e40af !important; }
    html.theme-dark .text-blue-400 { color: #93c5fd !important; }

    /* ----- 「承認者を選択してください」のホバー / ボタン背景 ----- */
    html.theme-dark .hover\:bg-amber-50:hover { background-color: #3a3217 !important; }
    html.theme-dark .hover\:bg-blue-50:hover { background-color: #1e293b !important; }
    html.theme-dark .hover\:bg-emerald-50:hover { background-color: #14352a !important; }
    html.theme-dark .hover\:bg-red-50:hover { background-color: #3a1a1f !important; }
    html.theme-dark .hover\:bg-gray-50:hover,
    html.theme-dark .hover\:bg-gray-100:hover { background-color: #313338 !important; }

    /* ----- 下書きヘッダの AI アシスタントボタン (ヘッダ右上) ----- */
    /* :style で動的に色指定されているため、 inline style を尊重しつつ
       ダーク時の白背景だけ #2b2d31 に倒す (ボタンの inline style より優先する !important) */
    html.theme-dark header.compose-window-header button[\\:style*="background-color:#ffffff"],
    html.theme-dark .btn-action,
    html.theme-dark .btn-action-reply,
    html.theme-dark .btn-action-replyall {
        background-color: #2b2d31 !important;
        color: #e5e7eb !important;
        border-color: #3f4147 !important;
    }

    /* ----- 左ペイン (返信元メールの本文表示) ----- */
    html.theme-dark aside .bg-white,
    html.theme-dark article.bg-white {
        background-color: #2b2d31 !important;
        color: #e5e7eb !important;
        border-color: #3f4147 !important;
    }
    html.theme-dark aside .border-gray-100,
    html.theme-dark aside .border-gray-200 { border-color: #3f4147 !important; }
    /* 過去メール (details) のヘッダ */
    html.theme-dark details.bg-white { background-color: #2b2d31 !important; }
    html.theme-dark details summary { color: #e5e7eb !important; }

    /* ----- フッターの「閉じる」「下書き保存」ボタン領域 ----- */
    html.theme-dark .rice-btn-primary { background-color: #2563eb !important; color: #fff !important; }
    html.theme-dark .rice-btn-secondary {
        background-color: #2b2d31 !important;
        color: #e5e7eb !important;
        border-color: #3f4147 !important;
    }

    /* ----- 各種モーダル (背景の dimmer は黒+透過なので問題なし。中身だけ調整) ----- */
    html.theme-dark .rice-modal,
    html.theme-dark .rice-modal-body,
    html.theme-dark .rice-modal-head,
    html.theme-dark .rice-modal-foot {
        background-color: #2b2d31 !important;
        color: #e5e7eb !important;
        border-color: #3f4147 !important;
    }

    /* ----- AI アシスタントパネル: 派手なインディゴ (#4f46e5) を抑えてダーク基調に -----
       要望:「AIアシスタントの色味 明るすぎる」
       inline style での指定が多いので attribute selector で個別に上書き. */
    html.theme-dark [style*="background-color:#4f46e5"],
    html.theme-dark [style*="background-color: #4f46e5"] {
        background-color: #1e293b !important;
        color: #cbd5e1 !important;
        box-shadow: none !important;
        border-color: #334155 !important;
    }
    html.theme-dark [style*="background-color:#4338ca"],
    html.theme-dark [style*="background-color: #4338ca"] {
        background-color: #283448 !important;
    }
    html.theme-dark [style*="color:#4f46e5"],
    html.theme-dark [style*="color: #4f46e5"] { color: #93c5fd !important; }
    html.theme-dark [style*="color:#6366f1"],
    html.theme-dark [style*="color: #6366f1"] { color: #93c5fd !important; }
    html.theme-dark [style*="color:#818cf8"],
    html.theme-dark [style*="color: #818cf8"] { color: #9ca3af !important; }
    html.theme-dark [style*="color:#3730a3"],
    html.theme-dark [style*="color: #3730a3"] { color: #c7d2fe !important; }
    /* インディゴ薄色背景 (パネルヘッダ / ヒントバナー) */
    html.theme-dark [style*="background-color:#eef2ff"],
    html.theme-dark [style*="background-color: #eef2ff"],
    html.theme-dark [style*="background-color:#ede9fe"] {
        background-color: #1f2433 !important;
        color: #cbd5e1 !important;
    }
    /* ヒント帯 rgba(238,242,255,0.7) も同様 */
    html.theme-dark [style*="background-color:rgba(238,242,255"] {
        background-color: rgba(30,41,59,0.7) !important;
        color: #93c5fd !important;
    }
    /* AI アシスタント本体 (右パネル全体の背景 #eef2ff) */
    html.theme-dark [style*="background-color:#eef2ff"] { background-color: #1a1c20 !important; }
    /* AI パネル左の縦罫 (#c7d2fe / #e0e7ff) */
    html.theme-dark [style*="border-left:1px solid #c7d2fe"],
    html.theme-dark [style*="border-bottom:1px solid #e0e7ff"],
    html.theme-dark [style*="border:1px solid #e0e7ff"],
    html.theme-dark [style*="border:1px solid #c7d2fe"] { border-color: #3f4147 !important; }
    /* skill カードの強い影 */
    html.theme-dark [style*="box-shadow:0 4px 14px rgba(79,70,229"] { box-shadow: 0 1px 4px rgba(0,0,0,0.5) !important; }
    /* AI 結果カード (#0f172a) は元から暗いのでそのまま OK */

    /* ----- ナレッジページのコレクションラベル等 (薄色バッジが見えにくい問題) ----- */
    html.theme-dark [style*="background:#dbeafe"],
    html.theme-dark [style*="background: #dbeafe"],
    html.theme-dark [style*="background-color:#dbeafe"],
    html.theme-dark [style*="background-color: #dbeafe"] {
        background-color: rgba(88,101,242,0.25) !important;
        color: #c7d2fe !important;
    }

    /* ===== AI チャットパネル: メッセージふきだし (emails/index と共通仕様) =====
       Tailwind の flex align-items:stretch で吹き出しが行高さまで引き伸ばされる事故を
       素 CSS で固定する. */
    .rice-ai-msg-row { display: flex; margin-bottom: 10px; align-items: flex-start; }
    .rice-ai-msg-row-user      { justify-content: flex-end;   }
    .rice-ai-msg-row-assistant { justify-content: flex-start; }
    .rice-ai-msg-bubble {
        max-width: 84%;
        padding: 9px 12px;
        font-size: 12.5px;
        line-height: 1.55;
        word-break: break-word;
        overflow-wrap: anywhere;
        align-self: flex-start;
        width: auto;
        flex: 0 0 auto;
    }
    .rice-ai-msg-bubble-user {
        background: #4f46e5; color: #ffffff;
        border-radius: 14px 14px 4px 14px;
    }
    .rice-ai-msg-bubble-assistant {
        background: #ffffff; color: #111827;
        border: 1px solid #e5e7eb;
        border-radius: 14px 14px 14px 4px;
    }
    .rice-ai-msg-body { white-space: pre-wrap; }
    .rice-ai-msg-pending,
    .rice-ai-msg-error {
        display: inline-flex; align-items: center; gap: 6px; font-size: 12px;
    }
    .rice-ai-msg-pending { color: #6b7280; }
    .rice-ai-msg-error   { color: #b91c1c; }
    .rice-ai-msg-actions {
        margin-top: 8px; padding-top: 6px;
        border-top: 1px dashed #e5e7eb;
        display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
        color: #9ca3af; font-size: 10px;
    }
    .rice-ai-msg-action-btn {
        font-weight: 700; padding: 2px 8px; border-radius: 4px;
        background: #f3f4f6; color: #374151;
        border: 1px solid #e5e7eb; cursor: pointer;
    }
    .rice-ai-msg-action-btn:hover { opacity: 0.85; }
    .rice-ai-msg-elapsed { margin-left: auto; }

    /* /スキル / /コレクション の青チップ (emails/index と同じ仕様) */
    .rice-ai-tag {
        background-color: #dbeafe;
        color: #1d4ed8;
        border-radius: 4px;
        padding: 1px 6px;
        margin: 0 1px;
        font-weight: 700;
        font-size: 0.95em;
        box-shadow: inset 0 0 0 1px rgba(29, 78, 216, 0.18);
        white-space: nowrap;
    }
    /* AI チャット入力欄: 普通の textarea として表示. chip 化は履歴側のみ. */
    .rice-ai-input-wrap { position: relative; flex: 1; min-width: 0; }
    .rice-ai-input-wrap textarea {
        width: 100%;
        font-family: inherit;
        font-size: 12px;
        line-height: 1.55;
        padding: 8px 12px;
        background: #f9fafb;
        color: #111827;
        border: 1px solid #e5e7eb; border-radius: 8px;
        outline: none; resize: none;
    }
</style>
@endsection

@section('content')
<div class="flex h-screen w-screen overflow-hidden bg-white text-gray-800 font-sans"
     x-data="composeWindowApp()" x-cloak>

    {{-- 左ペイン: スレッド表示 / 空状態 (リサイズ可能) --}}
    <aside class="h-full overflow-y-auto bg-gray-50 custom-scrollbar shrink-0"
           :style="`width:${leftPaneWidth}px`">

        {{-- 却下情報バナー (下書き編集モードで以前却下されたものの場合) --}}
        <template x-if="rejectionInfo">
            <div class="m-4 p-4 bg-red-50 border-l-4 border-red-400 rounded-lg shadow-sm">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-times-circle text-red-600"></i>
                    <p class="text-xs font-bold text-red-800">この下書きは過去に却下されました</p>
                </div>
                <div class="text-xs text-red-700 space-y-1">
                    <p><span class="font-bold">却下者:</span> <span x-text="rejectionInfo.rejected_by || '不明'"></span></p>
                    <p><span class="font-bold">日時:</span> <span x-text="rejectionInfo.rejected_at"></span></p>
                </div>
                <div class="mt-2 p-2 bg-white border border-red-100 rounded text-xs text-red-900 whitespace-pre-wrap leading-relaxed"
                     x-text="rejectionInfo.reason"></div>
                <p class="text-[10px] text-red-600 mt-2"><i class="fas fa-info-circle mr-1"></i>修正後、再度承認依頼を送信すると元の下書きは削除されます。</p>
            </div>
        </template>

        {{-- 予約中バナー (予約送信のメールを開いた時. 内容確認 + 取消ボタン).
             下書き保存 (save_as_draft=1) すると backend 側で status=draft に戻るが、
             サーバを叩く前に明示的に取り消したい場合のための [予約取消] ボタンを用意する. --}}
        <template x-if="draftIsScheduled">
            <div class="m-4 p-4 bg-indigo-50 border-l-4 border-indigo-400 rounded-lg shadow-sm">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-clock text-indigo-600"></i>
                    <p class="text-xs font-bold text-indigo-800">このメールは予約送信中です</p>
                </div>
                <div class="text-xs text-indigo-700 space-y-1">
                    <p><span class="font-bold">送信予定:</span> <span x-text="draftScheduledLabel"></span></p>
                </div>
                <p class="text-[10px] text-indigo-700 mt-2 leading-relaxed">
                    <i class="fas fa-info-circle mr-1"></i>
                    内容を変更して「下書き保存」または「承認を依頼」を押すと、予約は自動的に取り消されます。
                    取り消さずに日時だけ変更したい場合は「予約送信」ボタンで再保存してください。
                </p>
                <div class="mt-3 flex items-center gap-2">
                    <button type="button" @click="cancelScheduleFromBanner()" :disabled="cancellingSchedule"
                            class="inline-flex items-center gap-1.5 text-[11px] font-bold text-indigo-700 bg-white border border-indigo-300 px-3 py-1.5 rounded-lg hover:bg-indigo-100 transition-all disabled:opacity-50">
                        <i class="fas" :class="cancellingSchedule ? 'fa-spinner fa-spin' : 'fa-ban'"></i>
                        予約を取消して下書きに戻す
                    </button>
                </div>
            </div>
        </template>

        <template x-if="mode === 'compose' && !rejectionInfo">
            <div class="flex flex-col items-center justify-center h-full px-8 text-center">
                <div class="w-20 h-20 bg-white rounded-3xl shadow-xl flex items-center justify-center text-gray-300 mb-6">
                    <i class="fas fa-pen-fancy fa-2x"></i>
                </div>
                <h1 class="text-xl font-bold text-gray-800">新規メッセージ作成</h1>
                <p class="text-sm text-gray-500 mt-3 max-w-sm leading-relaxed">右側で宛先・件名・本文を入力してください。送信すると承認待ちとして登録されます。</p>
            </div>
        </template>
        <template x-if="mode === 'compose' && rejectionInfo">
            <div class="px-6 pb-6">
                <h1 class="text-lg font-bold text-gray-800 mb-2">新規メッセージ (下書き再編集)</h1>
                <p class="text-xs text-gray-500">却下理由を踏まえて本文を修正してから、再度承認依頼を送信してください。</p>
            </div>
        </template>

        <template x-if="mode !== 'compose' && email">
            <div class="p-6 space-y-5">
                <header class="space-y-2 pb-4 border-b border-gray-200">
                    <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider"
                       x-text="mode === 'forward' ? '転送' : (mode === 'reply_all' ? '全員に返信' : '返信')"></p>
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

    {{-- リサイズハンドル (スレッドとドラフトの境界) --}}
    <div class="resize-handle shrink-0"
         @mousedown.prevent="startResizeLeftPane($event)"
         @dblclick="resetLeftPane()"
         title="ドラッグで幅を変更／ダブルクリックでリセット">
    </div>

    {{-- 右ペイン: ドラフトフォーム --}}
    <main class="flex-1 min-w-0 h-full flex flex-col bg-white">
        <header class="compose-window-header shrink-0 px-6 py-4 border-b border-gray-200 bg-white flex items-center justify-between"
                style="position:relative;z-index:30;background-color:#ffffff;">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-md shrink-0">
                    <i class="fas" :class="mode === 'compose' ? 'fa-pen-fancy' : (mode === 'forward' ? 'fa-share' : 'fa-reply')"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="text-base font-bold text-gray-800 truncate" x-text="headerLabel"></h2>
                    <p class="text-xs text-gray-400 mt-0.5" x-show="thread?.subject" x-text="thread?.subject"></p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                {{-- AI アシスタントボタン: 旧色は白背景 + 鮮やかインディゴ文字で「明るすぎる / 浮く」フィードバック.
                     落ち着いた slate / indigo の中間色に調整. open 時もインディゴ濃色に振って彩度を抑える. --}}
                <button @click="toggleAi()"
                        class="px-4 py-2 rounded-lg border text-xs font-bold transition-all flex items-center gap-2 shadow-sm"
                        :style="aiPanelOpen
                            ? 'background-color:#4338ca;color:#ffffff;border:1px solid #3730a3;'
                            : 'background-color:#f1f5f9;color:#4338ca;border:1px solid #cbd5e1;'">
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
                    <template x-if="sendableAccounts.length > 1">
                        <div class="relative">
                            <label class="text-[10px] font-bold text-gray-500 uppercase absolute left-3 top-2 tracking-wider">送信アカウント</label>
                            <select @change="pickSendableAccount($event.target.value)"
                                    class="w-full pt-7 pb-2.5 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition-all font-semibold appearance-none">
                                <template x-for="acc in sendableAccounts" :key="acc.id ?? 'system'">
                                    <option :value="acc.id ?? ''" :selected="(acc.id ?? null) === form.mail_account_id"
                                            x-text="acc.label + ' <' + acc.from_address + '>'"></option>
                                </template>
                            </select>
                        </div>
                    </template>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="relative">
                            <label data-test-id="compose-from-label" class="text-[10px] font-bold text-gray-500 uppercase absolute left-3 top-2 tracking-wider">差出人 (From)</label>
                            <input type="text" x-model="form.from" class="w-full pt-7 pb-2.5 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition-all font-semibold">
                        </div>
                        <div class="relative">
                            <label data-test-id="compose-to-label" class="text-[10px] font-bold text-gray-500 uppercase absolute left-3 top-2 tracking-wider">宛先 (To)</label>
                            <input type="text" x-model="form.to" required class="w-full pt-7 pb-2.5 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition-all font-semibold">
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
                        <div class="flex items-center mb-1.5">
                            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider flex-1">本文</label>
                            {{-- テンプレート挿入 --}}
                            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                <button type="button" @click="open = !open; if(open) loadTemplates()" class="text-[10px] text-blue-600 hover:underline mr-3">📝 テンプレート挿入</button>
                                <div x-show="open" x-cloak class="absolute right-0 top-full mt-1 bg-white border rounded-lg shadow-lg z-10 min-w-[220px] max-h-[300px] overflow-y-auto">
                                    <template x-for="t in templates" :key="t.id">
                                        <button type="button" @click="applyTemplate(t); open = false"
                                                class="block w-full text-left px-3 py-2 text-xs hover:bg-blue-50">
                                            <strong x-text="t.name"></strong>
                                            <span x-show="t.subject" class="block text-gray-400 truncate" x-text="t.subject"></span>
                                        </button>
                                    </template>
                                    <template x-if="templates.length === 0">
                                        <p class="px-3 py-2 text-xs text-gray-400">テンプレ未登録 (プロフィールから追加)</p>
                                    </template>
                                </div>
                            </div>
                            {{-- 署名挿入 --}}
                            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                <button type="button" @click="open = !open; if(open) loadSignatures()" class="text-[10px] text-emerald-600 hover:underline">✍️ 署名挿入</button>
                                <div x-show="open" x-cloak class="absolute right-0 top-full mt-1 bg-white border rounded-lg shadow-lg z-10 min-w-[220px] max-h-[300px] overflow-y-auto">
                                    <template x-for="s in signatures" :key="s.id">
                                        <button type="button" @click="applySignature(s); open = false"
                                                class="block w-full text-left px-3 py-2 text-xs hover:bg-emerald-50">
                                            <strong x-text="s.name"></strong>
                                            <span x-show="s.is_default" class="ml-1 text-[9px] text-amber-600">★</span>
                                            <span class="block text-gray-400 truncate" x-text="s.body.substring(0, 60)"></span>
                                        </button>
                                    </template>
                                    <template x-if="signatures.length === 0">
                                        <p class="px-3 py-2 text-xs text-gray-400">署名未登録 (プロフィールから追加)</p>
                                    </template>
                                </div>
                            </div>
                        </div>
                        {{--
                            本文エディタ (プレーン textarea).
                            旧: Quill リッチエディタで HTML を出していたが、HTML 形式の文章作成は
                            使わないとの要望によりプレーンテキストに統一。
                            - form.body  : 入力テキスト
                            - form.body_html : サーバ送信時に空文字で送る (= text/plain のみ送信)
                        --}}
                        <textarea x-ref="bodyTextarea"
                                  x-model="form.body"
                                  rows="14"
                                  placeholder="返信内容を入力してください..."
                                  class="w-full bg-white border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition-all p-4 leading-relaxed"
                                  style="min-height:280px;resize:vertical;line-height:1.8;font-size:14px;color:#1f2937;background:#ffffff;"></textarea>
                    </div>

                    {{-- 承認者の指定 (カード型UI) --}}
                    <div class="bg-amber-50/40 border border-amber-200 rounded-xl p-4">
                        <label class="text-xs font-bold text-amber-800 mb-2 flex items-center gap-1.5">
                            <i class="fas fa-user-check"></i> 承認依頼を送る相手
                        </label>
                        <div class="flex items-center gap-2">
                            {{-- 選択済みカード --}}
                            <button type="button" @click="approverPickerOpen = true"
                                    class="flex-1 inline-flex items-center justify-between gap-3 bg-white border border-amber-200 rounded-lg px-4 py-2.5 text-left hover:bg-amber-50 transition-all">
                                <div class="flex items-center gap-3 min-w-0">
                                    <template x-if="selectedApprover">
                                        <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 shrink-0 font-bold text-sm"
                                             x-text="(selectedApprover.name || '?').charAt(0)"></div>
                                    </template>
                                    <template x-if="!selectedApprover">
                                        <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 shrink-0">
                                            <i class="fas fa-user-plus text-sm"></i>
                                        </div>
                                    </template>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-gray-800 truncate"
                                           x-text="selectedApprover ? selectedApprover.name : '承認者を選択してください'"></p>
                                        <p class="text-[11px] text-gray-500 truncate"
                                           x-text="selectedApprover ? (selectedApprover.email + (selectedApprover.role === 'admin' ? ' (管理者)' : '')) : '指定なし = 誰でも承認可能'"></p>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right text-amber-500 shrink-0"></i>
                            </button>
                            <button type="button" x-show="selectedApprover" @click="form.approver_id = ''"
                                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 border border-gray-200"
                                    title="承認者をクリア">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </div>
                        <p class="text-[11px] text-amber-700/80 mt-2 leading-relaxed">
                            <i class="fas fa-info-circle mr-1"></i>承認者を指定すると、その人の「承認待ち一覧」のみに表示されます。未指定の場合は誰でも承認可能。
                        </p>
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
                            {{-- 下書き編集モードで引き継いだ既存添付 (削除可) --}}
                            <template x-for="(att, i) in existingAttachments" :key="att.path">
                                <span class="bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-2 border border-emerald-200"
                                      :title="'既存の添付 (下書きから引き継ぎ)'">
                                    <i class="fas fa-paperclip text-emerald-500 text-[10px]"></i>
                                    <span x-text="att.filename" class="max-w-[220px] truncate"></span>
                                    <span class="text-emerald-400" x-text="att.size ? formatBytes(att.size) : ''"></span>
                                    <button type="button" @click="removeExistingAttachment(i)" class="hover:text-red-500" title="この添付を削除"><i class="fas fa-times-circle"></i></button>
                                </span>
                            </template>
                            {{--
                                転送モード時のみ表示: 元メールから引き継ぐ添付の選択チップ群.
                                初期状態は全てチェック済み. クリックでチェックを外せる.
                                チェックを外したものは submit 時に inherit_attachment_ids[] から除外され、
                                pending ストレージへもコピーされない.
                            --}}
                            <template x-if="mode === 'forward'">
                                <template x-for="att in inheritedAttachments" :key="'inh-' + att.id">
                                    <span class="px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-2 border cursor-pointer"
                                          :class="inheritAttachmentIds.includes(att.id)
                                              ? 'bg-amber-50 text-amber-800 border-amber-200'
                                              : 'bg-gray-50 text-gray-400 border-gray-200 line-through'"
                                          @click="const i = inheritAttachmentIds.indexOf(att.id); if (i >= 0) inheritAttachmentIds.splice(i, 1); else inheritAttachmentIds.push(att.id);"
                                          :title="inheritAttachmentIds.includes(att.id) ? '転送に含める (クリックで除外)' : '転送に含めない (クリックで含める)'">
                                        <i class="fas" :class="inheritAttachmentIds.includes(att.id) ? 'fa-paperclip text-amber-500 text-[10px]' : 'fa-ban text-gray-300 text-[10px]'"></i>
                                        <span x-text="att.filename" class="max-w-[220px] truncate"></span>
                                        <span class="text-amber-500" x-text="att.size ? formatBytes(att.size) : ''"></span>
                                    </span>
                                </template>
                            </template>
                            {{-- 新規アップロード分 --}}
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

        </div>

        {{-- AIパネル (右側スライドオーバー) — フォームを圧迫しないようウィンドウ右にオーバレイ。
             Tailwind v4 JIT で未生成の arbitrary value (z-[1500]) を使うと CSS が当たらず、
             非表示時にも画面上にうっすら残ってヘッダー要素のクリックを奪うことがあったため
             全プロパティをインライン style で固定指定する。 --}}
        <div x-show="aiPanelOpen" x-cloak
             style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:1500;background-color:rgba(15,23,42,0.35);"
             x-bind:style="aiPanelOpen ? 'display:flex;position:fixed;top:0;left:0;right:0;bottom:0;z-index:1500;background-color:rgba(15,23,42,0.35);' : 'display:none;'"
             @click.self="aiPanelOpen = false"
             @keydown.escape.window="aiPanelOpen = false">
            <div class="ml-auto h-full flex flex-col relative"
                 x-ref="composeAiPanel"
                 x-init="(function(){const w=parseInt(localStorage.getItem('riceAiChatPanelWidth')||'420',10); if(w>=320 && w<=window.innerWidth-100) \$refs.composeAiPanel.style.width=w+'px';})()"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-x-8 opacity-0"
                 x-transition:enter-end="translate-x-0 opacity-100"
                 style="width:420px;max-width:100vw;background-color:#eef2ff;border-left:1px solid #c7d2fe;box-shadow:-12px 0 30px rgba(15,23,42,0.15);">

                {{-- 左端ドラッグハンドル. ダブルクリックで初期値 420px に戻す. --}}
                <div @mousedown.prevent="(function(ev,panel){const minW=320,maxW=Math.max(minW,window.innerWidth-200); const onMove=(e)=>{panel.style.width=Math.max(minW,Math.min(maxW,window.innerWidth-e.clientX))+'px';}; const onUp=()=>{document.removeEventListener('mousemove',onMove);document.removeEventListener('mouseup',onUp);document.body.style.userSelect='';document.body.style.cursor=''; try{localStorage.setItem('riceAiChatPanelWidth', parseInt(panel.style.width,10)||420);}catch(_){}}; document.body.style.userSelect='none';document.body.style.cursor='col-resize'; document.addEventListener('mousemove',onMove);document.addEventListener('mouseup',onUp);})($event,$refs.composeAiPanel)"
                     @dblclick="$refs.composeAiPanel.style.width='420px'; try{localStorage.setItem('riceAiChatPanelWidth','420');}catch(_){}"
                     title="ドラッグで幅変更 / ダブルクリックで初期値"
                     style="position:absolute;top:0;left:0;bottom:0;width:6px;cursor:col-resize;background:transparent;z-index:5;"
                     onmouseover="this.style.background='rgba(99,102,241,0.35)'"
                     onmouseout="this.style.background='transparent'"></div>

                {{-- ヘッダー --}}
                <div class="shrink-0 px-5 py-3 flex items-center justify-between"
                     style="background-color:#ffffff;border-bottom:1px solid #e0e7ff;">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                             style="background-color:#4f46e5;color:#ffffff;">
                            <i class="fas fa-magic"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-extrabold truncate" style="color:#3730a3;">AIアシスタント</h3>
                            <p class="text-[10px]" style="color:#818cf8;">スキル + コンテキスト分析</p>
                        </div>
                    </div>
                    <button @click="aiPanelOpen = false"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg transition-colors"
                            style="color:#9ca3af;"
                            onmouseover="this.style.backgroundColor='#f3f4f6';this.style.color='#4f46e5';"
                            onmouseout="this.style.backgroundColor='';this.style.color='#9ca3af';"
                            title="閉じる">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                {{-- 本体: 返信/転送 (thread 有り) は AI チャット, 新規 (thread 無し) は従来の 1 ショット UI --}}
                <div class="flex-1 min-h-0 flex flex-col">
                    {{-- AI モデルピッカー (共通) --}}
                    <div class="shrink-0 p-4 border-b space-y-2" style="background:#ffffff;border-bottom:1px solid #e0e7ff;">
                        <label class="text-[10px] font-extrabold uppercase tracking-widest flex items-center gap-1" style="color:#6b7280;">
                            <i class="fas fa-cog text-[9px]"></i>AIモデル
                            <span x-show="aiPickerLoading" class="ml-1" style="color:#9ca3af;">
                                <i class="fas fa-circle-notch fa-spin"></i>
                            </span>
                        </label>
                        <div class="flex items-center gap-1 flex-wrap">
                            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-[10px]" style="background-color:#ffffff;">
                                <button type="button" @click="setAiProvider('ollama')"
                                        :class="aiProvider === 'ollama' ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50'"
                                        class="px-2 py-1 transition-colors">Ollama</button>
                                <button type="button" @click="setAiProvider('claude')"
                                        :class="aiProvider === 'claude' ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50'"
                                        :title="!aiHasClaudeKey ? 'APIキー未設定' : ''"
                                        class="px-2 py-1 transition-colors border-l border-gray-200">Claude</button>
                                <button type="button" @click="setAiProvider('gemini')"
                                        :class="aiProvider === 'gemini' ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50'"
                                        :title="!aiHasGeminiKey ? 'APIキー未設定' : ''"
                                        class="px-2 py-1 transition-colors border-l border-gray-200">Gemini</button>
                            </div>
                            <select x-model="aiModel"
                                    class="flex-1 min-w-0 border border-gray-200 rounded-lg px-2 py-1 text-[11px] text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white">
                                <template x-if="aiCurrentModels.length === 0">
                                    <option value="">モデルなし</option>
                                </template>
                                <template x-for="m in aiCurrentModels" :key="m.id || m">
                                    <option :value="m.id || m" x-text="m.name || m"></option>
                                </template>
                            </select>
                        </div>
                        <template x-if="aiProvider === 'claude' && !aiHasClaudeKey">
                            <p class="text-[10px]" style="color:#d97706;">⚠ Claude APIキー未設定</p>
                        </template>
                        <template x-if="aiProvider === 'gemini' && !aiHasGeminiKey">
                            <p class="text-[10px]" style="color:#d97706;">⚠ Gemini APIキー未設定</p>
                        </template>
                    </div>
                    {{-- (モデルピッカー終わり) --}}

                    {{-- ====== AI チャット (返信/転送モード, thread.id 有り) ====== --}}
                    <template x-if="aiChatThreadId">
                        <div class="flex-1 min-h-0 flex flex-col" style="background:#f8fafc;">
                            {{-- ツールバー --}}
                            <div class="shrink-0 px-3 py-1.5 flex items-center justify-between" style="background:#ffffff;border-bottom:1px solid #e0e7ff;">
                                <span class="text-[10px] font-bold" style="color:#6366f1;">
                                    <i class="fas fa-comments text-[9px]"></i> 返信案チャット
                                </span>
                                <button type="button" @click="resetAiChat()"
                                        :disabled="!aiChat.sessionId"
                                        class="text-[10px] font-bold px-2 py-1 rounded-md disabled:opacity-30"
                                        style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;"
                                        title="この会話を全部消してやり直す">
                                    <i class="fas fa-undo text-[9px]"></i> リセット
                                </button>
                            </div>
                            {{-- メッセージリスト --}}
                            <div id="compose-ai-chat-messages" class="flex-1 overflow-y-auto custom-scrollbar" style="padding:12px 10px;">
                                <template x-if="aiChat.loading && aiChat.messages.length === 0">
                                    <div class="flex items-center justify-center py-8" style="color:#6366f1;">
                                        <i class="fas fa-circle-notch fa-spin mr-2"></i>
                                        <span class="text-[11px]">読み込み中...</span>
                                    </div>
                                </template>
                                <template x-if="!aiChat.loading && aiChat.messages.length === 0">
                                    <div class="text-center py-8" style="color:#9ca3af;">
                                        <i class="fas fa-comments fa-2x mb-2" style="color:#e0e7ff;"></i>
                                        <p class="text-xs font-bold" style="color:#4b5563;">返信案を AI と相談しながら作ります</p>
                                        <p class="text-[10px] mt-1.5" style="color:#9ca3af;">
                                            まずはスレッドについて相談してください.<br>
                                            例: 「論点を整理して」「返信のトーンを相談したい」<br>
                                            準備ができたら 「<b>返信を書いて</b>」 と指示すると返信文を出します
                                        </p>
                                    </div>
                                </template>
                                <template x-for="m in aiChat.messages" :key="m.id">
                                    <div class="rice-ai-msg-row"
                                         :class="m.role === 'user' ? 'rice-ai-msg-row-user' : 'rice-ai-msg-row-assistant'">
                                        <div :class="m.role === 'user' ? 'rice-ai-msg-bubble rice-ai-msg-bubble-user' : 'rice-ai-msg-bubble rice-ai-msg-bubble-assistant'">
                                            <template x-if="m.role === 'assistant' && m.status === 'pending'">
                                                <div class="rice-ai-msg-pending">
                                                    <i class="fas fa-circle-notch fa-spin"></i>
                                                    <span>考えています...</span>
                                                </div>
                                            </template>
                                            <template x-if="m.role === 'assistant' && m.status === 'error'">
                                                <div>
                                                    <div class="rice-ai-msg-error">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                        <span x-text="m.error_message || 'エラーが発生しました'"></span>
                                                    </div>
                                                    <button type="button" @click="retryAiChatMessage(m)"
                                                            style="margin-top:6px;font-size:11px;font-weight:700;padding:4px 10px;border-radius:6px;background:#4f46e5;color:#fff;border:0;cursor:pointer;">
                                                        <i class="fas fa-redo text-[9px]"></i> 再送信
                                                    </button>
                                                </div>
                                            </template>
                                            <template x-if="m.status === 'done' || m.role === 'user'">
                                                <div>
                                                    <div class="rice-ai-msg-body"
                                                         x-html="m.role === 'user' ? renderAiChatTaggedHtml(m.content) : _escapeChatHtml(m.content).replace(/\n/g, '<br>')"></div>
                                                    <template x-if="m.role === 'assistant'">
                                                        <div class="rice-ai-msg-actions">
                                                            <button type="button" @click="replaceBodyWithAiChatMessage(m)"
                                                                    class="rice-ai-msg-action-btn"
                                                                    style="background:#4f46e5;color:#fff;border-color:#4338ca;"
                                                                    title="本文をこの内容で置き換える">
                                                                <i class="fas fa-arrow-down"></i> 本文に反映
                                                            </button>
                                                            <button type="button" @click="appendAiChatMessageToBody(m)"
                                                                    class="rice-ai-msg-action-btn"
                                                                    style="background:#e0e7ff;color:#4338ca;border-color:#c7d2fe;"
                                                                    title="本文の末尾に追記する">
                                                                <i class="fas fa-plus"></i> 追記
                                                            </button>
                                                            <button type="button" @click="copyAiChatMessage(m)"
                                                                    class="rice-ai-msg-action-btn"
                                                                    title="この本文をクリップボードにコピー">
                                                                <i class="fas fa-copy"></i> コピー
                                                            </button>
                                                            <span x-show="m.elapsed_ms" class="rice-ai-msg-elapsed"
                                                                  x-text="(m.elapsed_ms / 1000).toFixed(1) + 's'"></span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            {{-- 入力 --}}
                            <div class="shrink-0 p-2.5" style="position:relative;background:#ffffff;border-top:1px solid #e0e7ff;">
                                {{-- /スキル + /コレクション ポップアップ --}}
                                <div x-show="aiChatSlash.open" x-cloak
                                     style="display:none;position:absolute;left:10px;right:10px;bottom:100%;margin-bottom:6px;background:#fff;border:1px solid #c7d2fe;border-radius:8px;box-shadow:0 -8px 24px rgba(15,23,42,0.10);max-height:240px;overflow-y:auto;z-index:10;">
                                    <template x-if="filteredChatSkills().length > 0">
                                        <div>
                                            <p style="padding:6px 10px;font-size:10px;color:#6b7280;font-weight:700;background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                                                <i class="fas fa-bolt text-[9px]" style="color:#4f46e5;"></i> スキル
                                            </p>
                                            <template x-for="(s, k) in filteredChatSkillsObj()" :key="'sk-'+k">
                                                <button type="button" @click="pickChatSlashSkill(k)"
                                                        style="display:block;width:100%;text-align:left;padding:8px 10px;background:#fff;border:0;border-bottom:1px solid #f3f4f6;cursor:pointer;">
                                                    <div style="font-size:12px;font-weight:700;color:#1e1b4b;" x-text="s.name || k"></div>
                                                    <div style="font-size:10px;color:#6b7280;" x-text="s.description || k"></div>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="filteredChatCollections().length > 0">
                                        <div>
                                            <p style="padding:6px 10px;font-size:10px;color:#6b7280;font-weight:700;background:#f0fdf4;border-bottom:1px solid #e5e7eb;border-top:1px solid #e5e7eb;">
                                                <i class="fas fa-folder text-[9px]" style="color:#16a34a;"></i> ナレッジ コレクション
                                            </p>
                                            <template x-for="c in filteredChatCollections()" :key="'col-'+c.name">
                                                <button type="button" @click="pickChatSlashCollection(c.name)"
                                                        style="display:block;width:100%;text-align:left;padding:8px 10px;background:#fff;border:0;border-bottom:1px solid #f3f4f6;cursor:pointer;">
                                                    <div style="font-size:12px;font-weight:700;color:#14532d;" x-text="'/' + c.name"></div>
                                                    <div style="font-size:10px;color:#6b7280;">ナレッジを参照</div>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="filteredChatSkills().length === 0 && filteredChatCollections().length === 0">
                                        <p style="padding:8px;font-size:11px;color:#9ca3af;text-align:center;">該当なし</p>
                                    </template>
                                </div>

                                <div class="flex items-end gap-2">
                                    <div class="rice-ai-input-wrap">
                                        <textarea x-ref="composeChatInput"
                                                  x-model="aiChat.input"
                                                  @input="handleChatSlashInput()"
                                                  @keydown.escape="aiChatSlash.open = false"
                                                  @keydown.ctrl.enter.prevent="sendAiChat()"
                                                  @keydown.meta.enter.prevent="sendAiChat()"
                                                  placeholder="指示を入力 / 「/」でスキル + ナレッジコレクション (Ctrl+Enter で送信)"
                                                  rows="2"></textarea>
                                    </div>
                                    <button type="button" @click="sendAiChat()"
                                            :disabled="aiChat.sending || !aiChat.input.trim()"
                                            class="shrink-0 px-3 py-2 rounded-lg text-xs font-extrabold disabled:opacity-40 disabled:cursor-not-allowed"
                                            style="background:#4f46e5;color:#fff;">
                                        <i class="fas" :class="aiChat.sending ? 'fa-circle-notch fa-spin' : 'fa-paper-plane'"></i>
                                    </button>
                                </div>
                                <p class="text-[9px] mt-1" style="color:#9ca3af;">
                                    Ctrl+Enter で送信 / assistant の応答は「本文に反映」でドラフトに流し込めます
                                </p>
                            </div>
                        </div>
                    </template>

                    {{-- ====== 1 ショット UI (新規メール compose, thread.id 無し) ====== --}}
                    <template x-if="!aiChatThreadId">
                    <div class="flex-1 min-h-0 overflow-y-auto custom-scrollbar p-5 space-y-4">

                    {{-- スキル選択 --}}
                    <div class="space-y-2">
                        <label class="text-[10px] font-extrabold uppercase tracking-widest" style="color:#6b7280;">スキル</label>
                        <div class="space-y-2">
                            <template x-for="(skill, key) in aiSkills" :key="key">
                                <button type="button" @click="aiSkill = key"
                                        class="w-full p-3 rounded-xl text-left transition-all"
                                        :style="aiSkill === key
                                            ? 'background-color:#4f46e5;color:#ffffff;border:1px solid #4f46e5;box-shadow:0 4px 14px rgba(79,70,229,0.35);'
                                            : 'background-color:#ffffff;color:#374151;border:1px solid #e5e7eb;'">
                                    <p class="text-xs font-extrabold" x-text="skill.name"></p>
                                    <p class="text-[10px] mt-1 leading-snug" :style="aiSkill === key ? 'color:rgba(255,255,255,0.85);' : 'color:#9ca3af;'" x-text="skill.description"></p>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- 追加指示 --}}
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-[10px] font-extrabold uppercase tracking-widest" style="color:#6b7280;">追加の指示 (任意)</label>
                            <span class="text-[10px]" style="color:#6366f1;"><kbd class="px-1 bg-white border border-gray-200 rounded text-[9px]">/</kbd> でコレクション挿入</span>
                        </div>
                        <div class="relative prompt-editor-container">
                            <div x-ref="aiPromptHighlight" class="prompt-editor-highlight" aria-hidden="true"
                                 x-html="renderPromptHighlight(aiUserPrompt)"></div>
                            <textarea x-ref="aiPromptArea"
                                      x-model="aiUserPrompt"
                                      @input="onAiPromptInput($event); syncAiHighlightScroll()"
                                      @scroll="syncAiHighlightScroll()"
                                      @keydown="onAiPromptKeyDown($event)"
                                      @blur="setTimeout(() => aiSlash.open = false, 150)"
                                      rows="3" placeholder="例: /(コレクション名) を参照して丁寧に返信。もっと簡潔に。"
                                      class="w-full text-xs rounded-lg p-3 outline-none focus:ring-2 focus:ring-indigo-100 resize-none prompt-editor-input"
                                      style="background-color:#ffffff;border:1px solid #e5e7eb;"></textarea>

                            {{-- スラッシュコマンド候補 --}}
                            <div x-show="aiSlash.open" x-cloak
                                 class="absolute left-0 right-0 z-30 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl max-h-60 overflow-y-auto"
                                 style="top: 100%;">
                                <div class="sticky top-0 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                                    <span><i class="fas fa-folder text-indigo-400 mr-1"></i>ナレッジコレクション</span>
                                    <span class="text-[9px] text-gray-300" x-show="aiSlash.loading">読み込み中...</span>
                                </div>
                                <template x-for="(c, idx) in filteredAiCollections" :key="c.name + idx">
                                    <button type="button"
                                            @mousedown.prevent="insertAiCollection(c.name)"
                                            @mouseenter="aiSlash.activeIdx = idx"
                                            :class="idx === aiSlash.activeIdx ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'"
                                            class="w-full text-left px-3 py-2 text-xs flex items-center gap-2">
                                        <i class="fas fa-folder text-indigo-400 text-[10px]"></i>
                                        <span class="flex-1 font-mono" x-text="c.name"></span>
                                        <span class="text-[10px] text-gray-400" x-text="c.source === 'rag-api' ? 'rag' : 'db'"></span>
                                    </button>
                                </template>
                                <template x-if="!aiSlash.loading && filteredAiCollections.length === 0">
                                    <p class="px-3 py-3 text-xs text-gray-400 text-center">該当するコレクションがありません</p>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- マスキング --}}
                    <label class="flex items-center gap-2 p-3 rounded-lg cursor-pointer"
                           style="background-color:#ffffff;border:1px solid #e5e7eb;">
                        <input type="checkbox" x-model="maskPii" class="w-4 h-4 rounded" style="accent-color:#4f46e5;">
                        <span class="text-xs font-bold" style="color:#374151;">個人情報をマスキングする</span>
                    </label>

                    {{-- 生成ボタン --}}
                    <button type="button" @click="askAi()" :disabled="aiLoading || !canAskAi"
                            class="w-full py-3 rounded-xl font-extrabold text-sm flex items-center justify-center gap-2 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                            style="background-color:#4f46e5;color:#ffffff;box-shadow:0 4px 14px rgba(79,70,229,0.35);"
                            onmouseover="if(!this.disabled)this.style.backgroundColor='#4338ca';"
                            onmouseout="if(!this.disabled)this.style.backgroundColor='#4f46e5';">
                        <i class="fas fa-bolt" :class="aiLoading ? 'animate-spin' : ''"></i>
                        <span x-text="aiLoading ? '分析中...' : 'AI回答を生成する'"></span>
                    </button>

                    {{-- モード説明 --}}
                    <p x-show="mode === 'compose'" class="text-[11px] text-center rounded-lg py-1.5 px-2"
                       style="color:#4f46e5;background-color:rgba(238,242,255,0.7);border:1px solid #e0e7ff;">
                        <i class="fas fa-info-circle mr-1"></i>新規作成: 件名・宛先・現在の本文を踏まえて生成します
                    </p>

                    {{-- 生成結果 --}}
                    <div x-show="aiAnalysis || aiLoading" class="space-y-3">
                        <div class="rounded-2xl p-5"
                             style="background-color:#0f172a;color:#f3f4f6;box-shadow:0 12px 24px rgba(15,23,42,0.25);">
                            <h4 class="text-[10px] font-extrabold uppercase tracking-widest mb-3" style="color:#a5b4fc;">生成結果</h4>
                            <div class="text-sm leading-relaxed whitespace-pre-wrap min-h-[120px]"
                                 style="color:#f3f4f6;" x-text="aiAnalysis?.answer || (aiLoading ? '生成中…' : '')"></div>
                            <div class="mt-4 pt-3 flex flex-wrap gap-2"
                                 style="border-top:1px solid #1e293b;">
                                <template x-if="aiAnalysis?.sources?.kb">
                                    <span class="px-2 py-0.5 text-[9px] font-extrabold rounded"
                                          style="background-color:rgba(34,197,94,0.15);color:#86efac;border:1px solid rgba(34,197,94,0.3);">ナレッジ</span>
                                </template>
                                <template x-if="aiAnalysis?.sources?.reports">
                                    <span class="px-2 py-0.5 text-[9px] font-extrabold rounded"
                                          style="background-color:rgba(59,130,246,0.15);color:#93c5fd;border:1px solid rgba(59,130,246,0.3);">レポート</span>
                                </template>
                            </div>
                            <button type="button" @click="applyAiDraft()" :disabled="!aiAnalysis?.answer"
                                    class="mt-4 w-full py-2.5 rounded-lg text-xs font-extrabold transition-all disabled:opacity-40"
                                    style="background-color:#ffffff;color:#0f172a;"
                                    onmouseover="if(!this.disabled)this.style.backgroundColor='#e0e7ff';"
                                    onmouseout="if(!this.disabled)this.style.backgroundColor='#ffffff';">
                                <i class="fas fa-arrow-down mr-1"></i> 本文に反映する
                            </button>
                        </div>
                    </div>
                    </div>{{-- 1ショット スクロール領域終わり --}}
                    </template>{{-- !aiChatThreadId 終わり --}}
                </div>
            </div>
        </div>

        {{-- フッター: アクション --}}
        <footer class="shrink-0 px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center justify-between gap-3">
            <div class="text-xs text-gray-500 flex items-center gap-3">
                <span x-show="lastSavedLabel"><i class="fas fa-save text-gray-400 mr-1"></i><span x-text="lastSavedLabel"></span></span>
                <button type="button" @click="saveDraftAndClose()" :disabled="savingDraft"
                        class="text-blue-600 hover:text-blue-800 font-bold underline-offset-2 hover:underline disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!savingDraft">下書き保存</span>
                    <span x-show="savingDraft">保存中...</span>
                </button>
            </div>
            <div class="flex items-center gap-2">
                {{--
                    ★ 予約送信ポップオーバ.
                       タップで datetime-local 入力欄を出して「予約」ボタンで pending-emails/{id}/schedule を呼ぶ.
                       送信前に下書き保存しておく必要があるため、まだ id が無い場合は先に保存してから予約.
                --}}
                {{--
                    予約送信ポップオーバ (date picker only). 日時セットで form.scheduled_for を埋め、
                    実送信ボタンは右側の「予約送信」(緑) ボタンに任せる. 「この日時で予約」もそこを叩く.
                    予約送信 = 自己送信 (管理者承認不要). 過去日時の場合は即時送信.
                --}}
                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open"
                            class="bg-white border border-amber-200 text-amber-700 px-3 py-2.5 rounded-lg text-sm font-bold hover:bg-amber-50 transition-all flex items-center gap-2"
                            title="送信日時を指定 (管理者承認なしで指定日時に自動送信)">
                        <i class="fas fa-clock"></i>
                        <span x-show="!scheduledForLabel">予約日時</span>
                        <span x-show="scheduledForLabel" x-text="'予約: ' + scheduledForLabel"></span>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute bottom-full mb-2 right-0 bg-white rounded-xl border border-gray-200 shadow-2xl p-4 w-80 z-[100]">
                        <p class="text-[11px] font-bold text-gray-700 mb-2">送信日時を指定</p>
                        <input type="datetime-local" x-model="form.scheduled_for"
                               :min="scheduledForMinValue"
                               class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm">
                        <p class="text-[10px] text-emerald-700 mt-1.5 font-bold">
                            <i class="fas fa-check-circle mr-1"></i>予約送信は管理者承認なしで指定日時に自動送信されます。
                        </p>
                        <p class="text-[10px] text-gray-500 mt-1 leading-relaxed">
                            送信前に <code class="bg-gray-100 px-1 rounded">下書き / 予約送信</code> 一覧から
                            「予約取消」で本文を見直したり中止することもできます。
                        </p>
                        <div class="flex items-center gap-2 mt-3">
                            <button type="button" @click="form.scheduled_for = ''; open = false;"
                                    class="text-xs text-gray-500 hover:text-gray-700 underline">予約解除</button>
                            <button type="button" @click="scheduleSend().then(() => { if (!sending) open = false; })"
                                    :disabled="!form.scheduled_for || sending"
                                    class="ml-auto bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-md text-xs font-bold disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-paper-plane mr-1"></i>この日時で予約送信
                            </button>
                        </div>
                    </div>
                </div>
                <button type="button" @click="attemptClose()" class="bg-white border border-gray-200 text-gray-600 px-4 py-2.5 rounded-lg text-sm font-bold hover:bg-gray-100 transition-all">閉じる</button>
                {{--
                    自己送信ボタン (= 承認フロー非経由).
                    - 通常は管理者ポリシーが SEND_POLICY_APPROVAL_REQUIRED の場合は非表示.
                    - 例外: 予約送信 (form.scheduled_for あり) は「ユーザが個別に送信する」扱いなのでポリシー無視で常に出す.
                      実送信前に本人が取消できる猶予があるため自己責任で許可している (backend selfSend も同様の判断).
                --}}
                <button type="button" @click="sendNow()" :disabled="!form.body || sending"
                        x-show="!isApprovalRequired || form.scheduled_for"
                        class="bg-emerald-600 text-white px-5 py-2.5 rounded-lg text-sm font-bold shadow hover:bg-emerald-700 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        :title="form.scheduled_for ? ('指定日時 (' + scheduledForLabel + ') に自動送信 (管理者承認なし・取消可能)') : '承認を経由せず今すぐ自分で送信'">
                    <span x-show="!sending && !form.scheduled_for">今すぐ送信</span>
                    <span x-show="!sending && form.scheduled_for">この日時で予約送信</span>
                    <span x-show="sending">送信中...</span>
                    <i class="fas fa-paper-plane" x-show="!sending && !form.scheduled_for"></i>
                    <i class="fas fa-clock" x-show="!sending && form.scheduled_for"></i>
                    <i class="fas fa-spinner animate-spin" x-show="sending"></i>
                </button>
                {{--
                    承認を依頼するボタン.
                    予約送信 (form.scheduled_for あり) の場合は非表示にする.
                    理由: 予約 = ユーザの個別送信なので承認フローには載せない.
                          時刻指定は承認後に承認者が決める仕様 (approval-side で「予約のまま承認」を選べる).
                --}}
                <button type="button" @click="submitDraft()" :disabled="!form.body || sending"
                        x-show="!form.scheduled_for"
                        class="bg-blue-600 text-white px-6 py-2.5 rounded-lg text-sm font-bold shadow-lg hover:bg-blue-700 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        title="承認者に依頼してから送信 (承認後の送信時刻は承認者が決定)">
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

    {{-- 承認者選択モーダル --}}
    <template x-if="approverPickerOpen">
        <div class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-md p-4" @click.self="approverPickerOpen = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col" style="max-height: 80vh;">
                <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-b from-amber-50 to-white">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center text-white">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900">承認者を選択</h3>
                            <p class="text-xs text-gray-500">この人の「承認待ち一覧」に表示されます</p>
                        </div>
                    </div>
                    <input type="text" x-model="approverSearch" placeholder="名前またはメールアドレスで検索..."
                           class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-100 focus:border-amber-300">
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar">
                    {{-- 「承認者を指定しない」選択肢 --}}
                    <button type="button" @click="form.approver_id = ''; approverPickerOpen = false;"
                            :class="!form.approver_id ? 'bg-amber-50 border-l-4 border-l-amber-500' : ''"
                            class="w-full px-5 py-3 text-left hover:bg-gray-50 border-b border-gray-100 flex items-center gap-3 transition-all">
                        <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-gray-500">
                            <i class="fas fa-globe"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-gray-800">承認者を指定しない</p>
                            <p class="text-[11px] text-gray-500">誰でも承認可能 (全員のキューに入る)</p>
                        </div>
                        <i x-show="!form.approver_id" class="fas fa-check text-amber-500"></i>
                    </button>

                    <template x-for="u in filteredApprovers" :key="u.id">
                        <button type="button" @click="form.approver_id = u.id; approverPickerOpen = false;"
                                :class="String(form.approver_id) === String(u.id) ? 'bg-amber-50 border-l-4 border-l-amber-500' : ''"
                                class="w-full px-5 py-3 text-left hover:bg-gray-50 border-b border-gray-100 flex items-center gap-3 transition-all">
                            <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-sm shrink-0"
                                 x-text="(u.name || '?').charAt(0)"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-800 truncate" x-text="u.name"></p>
                                <p class="text-[11px] text-gray-500 truncate" x-text="u.email"></p>
                            </div>
                            <template x-if="u.role === 'admin'">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    <i class="fas fa-shield-alt mr-1"></i>管理者
                                </span>
                            </template>
                            <i x-show="String(form.approver_id) === String(u.id)" class="fas fa-check text-amber-500 shrink-0"></i>
                        </button>
                    </template>
                    <template x-if="filteredApprovers.length === 0 && approverSearch">
                        <div class="px-5 py-8 text-center text-gray-400 text-sm">
                            <i class="fas fa-search text-2xl text-gray-300 mb-2 block"></i>
                            該当ユーザーが見つかりません
                        </div>
                    </template>
                </div>
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end">
                    <button @click="approverPickerOpen = false"
                            class="bg-white border border-gray-200 text-gray-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gray-100 transition-all">
                        閉じる
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- 送信前確認モーダル --}}
    <template x-if="sendConfirmOpen">
        <div class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-md p-4" @click.self="sendConfirmOpen = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-b from-blue-50 to-white">
                    <div class="flex items-center gap-2">
                        <div class="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center text-white">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900">承認依頼の送信確認</h3>
                            <p class="text-xs text-gray-500">送信先と承認者を確認してください</p>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-4 space-y-3">
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                        <p class="text-[10px] font-bold text-gray-500 uppercase mb-1">宛先</p>
                        <p class="text-sm font-semibold text-gray-800 break-all" x-text="form.to || '(未入力)'"></p>
                        <p class="text-[10px] font-bold text-gray-500 uppercase mt-2 mb-1">件名</p>
                        <p class="text-sm font-semibold text-gray-800 break-all" x-text="form.subject || '(無題)'"></p>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                        <p class="text-[10px] font-bold text-amber-700 uppercase mb-2">承認依頼を送る相手</p>
                        <template x-if="selectedApprover">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold"
                                     x-text="(selectedApprover.name || '?').charAt(0)"></div>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-gray-900" x-text="selectedApprover.name"></p>
                                    <p class="text-[11px] text-gray-500" x-text="selectedApprover.email"></p>
                                </div>
                            </div>
                        </template>
                        <template x-if="!selectedApprover">
                            <div class="flex items-center gap-2 text-gray-600 text-xs">
                                <i class="fas fa-globe text-gray-400"></i>
                                <span>承認者を指定しない (誰でも承認可能)</span>
                            </div>
                        </template>
                        <button type="button" @click="sendConfirmOpen = false; approverPickerOpen = true;"
                                class="mt-2 text-xs text-amber-700 hover:text-amber-900 font-bold underline">
                            <i class="fas fa-edit mr-1"></i>承認者を変更
                        </button>
                    </div>
                </div>
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end gap-2">
                    <button @click="sendConfirmOpen = false"
                            class="bg-white border border-gray-200 text-gray-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gray-100 transition-all">
                        キャンセル
                    </button>
                    <button @click="sendConfirmOpen = false; submitDraftConfirmed();" :disabled="sending"
                            class="text-white px-5 py-2 rounded-lg text-sm font-bold flex items-center gap-2 disabled:opacity-50"
                            style="background-color:#2563eb;">
                        <i class="fas fa-paper-plane"></i>
                        <span x-text="sending ? '送信中...' : '承認依頼を送信'"></span>
                    </button>
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
                    <button @click="saveDraftAndClose()" :disabled="savingDraft"
                            class="w-full bg-blue-600 text-white py-3 rounded-lg text-sm font-bold hover:bg-blue-700 transition-all flex items-center justify-center gap-2 disabled:opacity-50">
                        <i class="fas fa-save"></i>
                        <span x-show="!savingDraft">下書き保存して閉じる</span>
                        <span x-show="savingDraft">保存中...</span>
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
        approvers: @json($approvers ?? []),
        aiSkills: @json($userAiSkills ?? config('ai_skills.skills', [])),
        // 送信ポリシー (管理者設定. flexible = 自己送信OK / approval_required = 承認必須).
        // サーバから埋め込み、API でも /api/send-policy で取れる. ボタンの出し分けに使う.
        sendPolicy: @json($sendPolicy ?? 'flexible'),
        get isApprovalRequired() { return this.sendPolicy === 'approval_required'; },
        // 下書き編集モード用
        draftId: @json($draftId ?? null),
        draftMemo: @json($draftMemo ?? null),
        // 予約送信中の下書きを開いた時のフラグ + ラベル. 上部バナー表示に使う.
        draftIsScheduled: @json($draftIsScheduled ?? false),
        draftScheduledLabel: @json($draftScheduledLabel ?? null),
        cancellingSchedule: false,
        // 下書きから引き継いだ既存添付 (削除すると keep_attachments[] から外れて送信時に削除される)
        existingAttachments: @json($draftAttachments ?? []),
        // 転送モード時、元メールから引き継ぐ候補添付 (EmailAttachment) のリスト.
        // {id, filename, mime_type, size, url}. 全て初期チェック済みで、ユーザが個別に外せる.
        // チェック中の ID は inheritAttachmentIds 配列で管理 → submit 時に inherit_attachment_ids[] として送る.
        inheritedAttachments: @json($inheritedAttachments ?? []),
        inheritAttachmentIds: (function () {
            const list = @json($inheritedAttachments ?? []);
            return Array.isArray(list) ? list.map(a => a.id).filter(v => v != null) : [];
        })(),
        rejectionInfo: @json($rejectionInfo ?? null),
        form: {
            from:    @json($defaultFrom),
            to:      @json($replyTo),
            cc:      @json($replyCc),
            bcc:     @json($replyBcc),
            subject: @json($replySubject),
            // body = プレーンテキスト (互換 / 検証 / 既存ロジック用)
            // body_html = リッチエディタの HTML (送信 multipart 用)
            // 下書き編集時は draftBody / draftBodyHtml をサーバから初期値として渡される。
            // 転送モード時はサーバ側で組み立てた引用本文を replyBody として受け取り、初期値にする.
            body:      @json($draftBody ?? ($replyBody ?? '')),
            body_html: @json($draftBodyHtml ?? ''),
            approver_id: '',
            // 予約送信用 (タスク #112). datetime-local 入力. 空 = 即時 / 通常承認フロー.
            scheduled_for: @json($draftScheduledFor ?? ''),
            // 個人メールアカウント機能: SMTP送信時に使うアカウント (null=システム既定)
            mail_account_id: null,
        },
        // 送信に使えるアカウント一覧 (システム既定 + 自分のSMTP有効アカウント)
        sendableAccounts: @json($sendableAccounts ?? []),
        pickSendableAccount(idOrEmpty) {
            const id = idOrEmpty === '' || idOrEmpty === null ? null : Number(idOrEmpty);
            const acc = this.sendableAccounts.find(a => (a.id ?? null) === id);
            this.form.mail_account_id = id;
            if (acc) this.form.from = acc.from_address;
        },
        // 旧 Quill 関連 state は残骸として残さない。テキストエリアは form.body と直接バインドされる。
        selectedFiles: [],
        attachmentError: null,
        sending: false,
        savingDraft: false,
        sentCompleted: false,
        closeConfirmOpen: false,
        approverPickerOpen: false,
        approverSearch: '',
        sendConfirmOpen: false,
        aiPanelOpen: false,
        aiSkill: @json(collect($userAiSkills ?? [])->filter(fn($s) => ($s['is_default_reply'] ?? false))->keys()->first() ?? 'reply'),
        aiUserPrompt: '',
        // スラッシュコマンド (ナレッジコレクション挿入)
        aiCollections: [],
        aiCollectionsLoaded: false,
        aiSlash: { open: false, query: '', startPos: 0, activeIdx: 0, loading: false },
        // テンプレート / 署名
        templates: [],
        signatures: [],
        templatesLoaded: false,
        signaturesLoaded: false,
        // 現在本文に挿入されている署名テキスト (差し替え時の検出用)
        currentSignatureText: '',
        aiAnalysis: null,
        aiLoading: false,
        maskPii: true,
        // AI モデルピッカー
        aiPickerLoading: false, aiPickerLoaded: false,
        aiProvider: 'ollama', aiModel: '',
        aiOllamaModels: [], aiClaudeModels: [], aiGeminiModels: [],
        aiHasClaudeKey: false, aiHasGeminiKey: false,

        // AI チャット (返信案を多ターン対話でブラッシュアップ)
        //   - thread.id に紐づくセッション (kind='reply')
        //   - サーバ側で永続化 → 同じスレッドを開き直したら続きから
        //   - assistant の出力に「本文に反映」ボタンを置いてワンクリックでドラフトへ流し込む
        aiChat: {
            sessionId: null,
            messages: [],
            input: '',
            sending: false,
            loading: false,
            pollTimer: null,
        },
        // 入力欄の '/' ポップアップ (スキル + コレクション)
        aiChatSlash: {
            open: false,
            query: '',
            tokenStart: -1,
        },

        // 左ペイン (スレッド) の幅。localStorage に保存。
        leftPaneWidth: (() => {
            const saved = parseInt(localStorage.getItem('composeWindowLeftPaneWidth'), 10);
            if (!isNaN(saved) && saved > 0) return saved;
            return Math.max(420, Math.min(680, Math.floor(window.innerWidth * 0.45)));
        })(),
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
            if (this.mode === 'forward')   return '転送';
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
            // 新規作成 (compose) でも AI を呼べる。返信系は元メールが必要
            if (this.mode === 'compose') return true;
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
        get selectedApprover() {
            if (!this.form.approver_id) return null;
            return this.approvers.find(u => String(u.id) === String(this.form.approver_id)) || null;
        },
        get filteredApprovers() {
            const q = (this.approverSearch || '').trim().toLowerCase();
            if (!q) return this.approvers;
            return this.approvers.filter(u =>
                (u.name || '').toLowerCase().includes(q) ||
                (u.email || '').toLowerCase().includes(q)
            );
        },

        init() {
            this.draftKey = this.buildDraftKey();
            // localStorage の自動復元は廃止。ウィンドウを開く度にサーバ側から渡された値で開始
            // 古い localStorage エントリが残っていれば掃除しておく
            this.clearLocalDraft();
            this.initialBody = this.form.body;

            // 本文エディタは <textarea x-model="form.body"> で Alpine 直接バインド (Quill 廃止).
            // body_html は HTML 編集を廃止したため常に空文字を送る.
            this.form.body_html = '';

            // デフォルト署名を自動挿入 (本文がまだ署名を含んでいなければ)
            this.insertDefaultSignature();

            // 未保存確認 (タブ閉じる/リロード)
            window.addEventListener('beforeunload', (e) => {
                if (!this.sending && !this.sentCompleted && this.isDirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            // ?ai_task=ID で起動された場合、保存済み AI タスクの結果を即ロード
            try {
                const url = new URL(window.location.href);
                const aiTaskId = url.searchParams.get('ai_task');
                if (aiTaskId) {
                    this.loadAiModels();
                    this.resumeAiTask(parseInt(aiTaskId, 10));
                }
            } catch (_) {}
        },

        // ====== 本文エディタ操作ヘルパ (プレーン textarea 版) ======
        //
        // 旧: Quill リッチエディタを使っていたが、HTML 形式の文章作成は不要との要望に
        //     合わせてプレーン textarea に統一。各ヘルパの API は維持しつつ、
        //     裏で this.form.body を直接書き換えるだけ (textarea は x-model で連動)。
        // body_html は送信時に互換維持で空文字を送る。

        // 本文を丸ごと置き換える (signature / template / AI 等から呼ばれる)
        setBodyText(text) {
            this.form.body      = (text === undefined || text === null) ? '' : String(text);
            this.form.body_html = '';
        },
        // HTML 渡しは「タグを剥がしてテキストに」する。互換 API 用 (実質 setBodyText と同じ)
        setBodyHtml(html) {
            const txt = String(html || '').replace(/<\s*br\s*\/?>/gi, "\n")
                .replace(/<\/(p|div|h[1-6]|li|tr)>/gi, "\n").replace(/<[^>]*>/g, '')
                .replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>');
            this.setBodyText(txt);
        },
        // 末尾に追加 (テンプレ挿入 / AI 結果挿入 / 署名挿入)
        appendBodyText(text) {
            if (text === undefined || text === null || text === '') return;
            this.form.body = (this.form.body || '') + String(text);
            this.form.body_html = '';
        },
        appendBodyHtml(html) {
            const txt = String(html || '').replace(/<\s*br\s*\/?>/gi, "\n")
                .replace(/<\/(p|div|h[1-6]|li|tr)>/gi, "\n").replace(/<[^>]*>/g, '')
                .replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>');
            this.appendBodyText(txt);
        },
        // 旧 Quill 用の互換スタブ (no-op)
        _pushFormBodyToEditor() {},
        _plainToSimpleHtml(text) { return String(text || ''); },

        // 既存の AI タスクを取得して AI パネルに表示する
        async resumeAiTask(taskId) {
            this.aiPanelOpen = true;
            this.aiLoading = true;
            this.aiAnalysis = null;
            try {
                const res = await fetch(`/ai-tasks/${taskId}`, { headers: { Accept: 'application/json' } });
                if (!res.ok) {
                    this.toast('AI タスクを読み込めませんでした', 'error');
                    this.aiLoading = false;
                    return;
                }
                const data = await res.json();
                if (data.status === 'done') {
                    this.simulateStreaming({
                        answer: data.answer,
                        skill_used: data.skill_used,
                        sources: data.sources,
                    });
                    this.toast('AI生成結果を読み込みました', 'success');
                } else if (data.status === 'error') {
                    this.aiLoading = false;
                    this.toast('このタスクは失敗していました: ' + (data.error_message || ''), 'error');
                } else {
                    // 処理中の場合、その task_id で再ポーリング (compose-window を一旦閉じて開き直したケース)
                    const finalData = await this._pollAiTask(taskId);
                    if (finalData && finalData.status === 'done') {
                        this.simulateStreaming({
                            answer: finalData.answer,
                            skill_used: data.skill_used,
                            sources: finalData.sources,
                        });
                        this.toast('AI生成が完了しました', 'success');
                    } else if (finalData) {
                        this.toast('AI生成に失敗: ' + (finalData.error_message || ''), 'error');
                        this.aiLoading = false;
                    } else {
                        this.aiLoading = false;
                    }
                }
            } catch (e) {
                this.toast('読み込みエラー: ' + (e.message || ''), 'error');
                this.aiLoading = false;
            }
        },

        buildDraftKey() {
            if (this.mode === 'compose') return 'compose_draft__new';
            if (this.email && this.email.id) return `compose_draft__${this.mode}__${this.email.id}`;
            return 'compose_draft__unknown';
        },
        saveLocalDraft() {
            try {
                const data = {
                    from: this.form.from, to: this.form.to, cc: this.form.cc, bcc: this.form.bcc,
                    subject: this.form.subject, body: this.form.body, savedAt: new Date().toISOString(),
                };
                localStorage.setItem(this.draftKey, JSON.stringify(data));
                this.lastSavedAt = new Date().toLocaleTimeString();
            } catch (_) {}
        },
        loadLocalDraft() {
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
                if (d.body)    this.setBodyText(d.body);
            } catch (_) {}
        },
        clearLocalDraft() {
            try { localStorage.removeItem(this.draftKey); } catch(_) {}
        },

        toast(message, type = 'info') {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, type });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 3500);
        },

        _notifyDesktop(title, body) {
            try {
                if (!('Notification' in window)) return;
                if (document.hasFocus()) return;
                if (Notification.permission === 'granted') {
                    new Notification(title, { body, tag: 'rice-ai-' + Date.now() });
                } else if (Notification.permission !== 'denied') {
                    Notification.requestPermission().then(p => {
                        if (p === 'granted') new Notification(title, { body, tag: 'rice-ai-' + Date.now() });
                    });
                }
            } catch (_) {}
        },

        // ===== AI追加指示テキストエリアのスラッシュコマンド =====
        get filteredAiCollections() {
            const q = (this.aiSlash.query || '').toLowerCase();
            if (!q) return this.aiCollections;
            return this.aiCollections.filter(c => (c.name || '').toLowerCase().includes(q));
        },

        async loadAiCollections() {
            if (this.aiCollectionsLoaded) return;
            this.aiSlash.loading = true;
            try {
                const res = await fetch('/api/knowledge/collections', { headers: { Accept: 'application/json' } });
                if (res.ok) {
                    const data = await res.json();
                    this.aiCollections = data.collections || [];
                }
            } catch (_) {}
            finally {
                this.aiCollectionsLoaded = true;
                this.aiSlash.loading = false;
            }
        },

        onAiPromptInput(e) {
            const ta = e.target;
            const pos = ta.selectionStart;
            const value = ta.value;
            let validIdx = -1;
            for (let i = pos - 1; i >= 0; i--) {
                const ch = value[i];
                if (/\s/.test(ch)) break;
                if (ch === '/') {
                    const prev = value[i - 1];
                    if (i === 0 || /\s/.test(prev)) validIdx = i;
                    break;
                }
            }
            if (validIdx === -1) { this.aiSlash.open = false; return; }
            this.aiSlash.startPos = validIdx;
            this.aiSlash.query    = value.slice(validIdx + 1, pos);
            this.aiSlash.activeIdx = 0;
            this.aiSlash.open = true;
            this.loadAiCollections();
        },

        onAiPromptKeyDown(e) {
            if (!this.aiSlash.open) return;
            const list = this.filteredAiCollections;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.aiSlash.activeIdx = Math.min(this.aiSlash.activeIdx + 1, Math.max(list.length - 1, 0));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.aiSlash.activeIdx = Math.max(this.aiSlash.activeIdx - 1, 0);
            } else if (e.key === 'Enter' || e.key === 'Tab') {
                if (list[this.aiSlash.activeIdx]) {
                    e.preventDefault();
                    this.insertAiCollection(list[this.aiSlash.activeIdx].name);
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                this.aiSlash.open = false;
            }
        },

        insertAiCollection(name) {
            const ta = this.$refs.aiPromptArea;
            if (!ta) return;
            const value = ta.value;
            const pos = ta.selectionStart;
            const before = value.slice(0, this.aiSlash.startPos);
            const after  = value.slice(pos);
            const insertion = '/' + name + ' ';
            const newValue = before + insertion + after;
            this.aiUserPrompt = newValue;
            this.$nextTick(() => {
                const newPos = before.length + insertion.length;
                try { ta.focus(); ta.setSelectionRange(newPos, newPos); } catch (_) {}
                this.syncAiHighlightScroll();
            });
            this.aiSlash.open = false;
        },

        // /コレクション をハイライト
        renderPromptHighlight(text) {
            const esc = (s) => (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const tokenRegex = /(^|[\s])\/([^\s\/\\#?&]+)/gu;
            let result = '', lastIndex = 0;
            for (const m of (text || '').matchAll(tokenRegex)) {
                const start = m.index + m[1].length;
                result += esc((text || '').slice(lastIndex, start));
                const token = m[2];
                result += '<span class="col-tag">/' + esc(token) + '</span>';
                lastIndex = start + 1 + token.length;
            }
            result += esc((text || '').slice(lastIndex));
            if (result.endsWith('\n')) result += ' ';
            return result;
        },
        syncAiHighlightScroll() {
            const ta = this.$refs.aiPromptArea;
            const hi = this.$refs.aiPromptHighlight;
            if (!ta || !hi) return;
            hi.scrollTop = ta.scrollTop;
            hi.scrollLeft = ta.scrollLeft;
        },

        // ===== テンプレート挿入 =====
        async loadTemplates() {
            if (this.templatesLoaded) return;
            try {
                const r = await fetch('/api/user/templates', { headers:{Accept:'application/json'} });
                if (r.ok) this.templates = (await r.json()).templates || [];
            } catch (_) {}
            this.templatesLoaded = true;
        },
        // テンプレート適用 (2026-05 仕様変更):
        //   - 件名は変更しない (テンプレートは「本文の部分挿入」専用と再定義).
        //   - 本文は textarea のカーソル位置に挿入する (上書きしない).
        //   - カーソル取得不能 (textarea がまだ DOM に無い等) の場合は末尾追記にフォールバック.
        applyTemplate(t) {
            const tbody = (t.body || '');
            if (!tbody) {
                this.toast('テンプレート「' + (t.name || '無題') + '」は本文が空です', 'error');
                return;
            }
            const ta = this.$refs.bodyTextarea;
            const current = this.form.body || '';
            // textarea のフォーカス位置にスニペットとして挿入. 既存テキストは保持.
            if (ta && typeof ta.selectionStart === 'number') {
                const start = ta.selectionStart;
                const end   = ta.selectionEnd ?? start;
                const before = current.slice(0, start);
                const after  = current.slice(end);
                this.form.body      = before + tbody + after;
                this.form.body_html = '';
                // 挿入直後のカーソルを本文末尾 (挿入後の位置) に移動 → UX 上「続きが書きやすい」.
                this.$nextTick(() => {
                    if (!ta) return;
                    const pos = before.length + tbody.length;
                    try { ta.focus(); ta.setSelectionRange(pos, pos); } catch (_) {}
                });
            } else {
                // フォールバック: 末尾追記.
                const prefix = current ? '\n\n' : '';
                this.appendBodyText(prefix + tbody);
            }
            this.toast('テンプレート「' + (t.name || '無題') + '」をカーソル位置に挿入しました', 'success');
        },

        // ===== 署名挿入 =====
        async loadSignatures() {
            if (this.signaturesLoaded) return;
            try {
                const r = await fetch('/api/user/signatures', { headers:{Accept:'application/json'} });
                if (r.ok) this.signatures = (await r.json()).signatures || [];
            } catch (_) {}
            this.signaturesLoaded = true;
        },
        // 既存の署名を差し替える (なければ追加する)
        // 注意: 署名はプレーンテキストとして扱う。Quill エディタに HTML として流し込むときは
        // _plainToSimpleHtml を経由するので XSS リスクは無い。
        applySignature(s) {
            const newSig = (s.body || '');
            // 既に挿入済みの署名を本文中から検出して差し替える
            let nextBodyPlain;
            if (this.currentSignatureText && this.form.body && this.form.body.includes(this.currentSignatureText)) {
                nextBodyPlain = this.form.body.split(this.currentSignatureText).join(newSig);
            } else {
                // 未挿入なら末尾に追加 (引用がある場合は引用の直前に挟む)
                nextBodyPlain = this._insertSignatureIntoBody(this.form.body || '', newSig);
            }
            // エディタ全体を新しい plain で置き換える (Quill に同期させるため setBodyText を使う)
            this.setBodyText(nextBodyPlain);
            this.currentSignatureText = newSig;
            this.toast('署名「' + s.name + '」を挿入しました', 'success');
        },
        // 引用 (`> ...` で始まる行) の直前に署名を入れる。引用がなければ末尾。
        _insertSignatureIntoBody(body, sig) {
            if (!sig) return body;
            const lines = body.split('\n');
            // 引用ブロック (連続する `>` 行) の開始位置を探す
            let quoteStart = -1;
            for (let i = 0; i < lines.length; i++) {
                if (/^\s*>/.test(lines[i])) { quoteStart = i; break; }
            }
            if (quoteStart >= 0) {
                const head = lines.slice(0, quoteStart).join('\n').replace(/\n+$/, '');
                const tail = lines.slice(quoteStart).join('\n');
                return (head ? head + '\n\n' : '') + sig + '\n\n' + tail;
            }
            const cleaned = (body || '').replace(/\n+$/, '');
            return (cleaned ? cleaned + '\n\n' : '') + sig;
        },
        // 起動時にデフォルト署名を自動挿入。
        // 挿入後はカーソルを本文先頭 (位置 0) に置く — ユーザは「署名の上に」自然に書き始められる。
        //
        // ★ 重要: 既存ドラフトの再オープン (draftId が立っている) では絶対に挿入しない。
        //   旧実装は `body.includes(signature.body)` で重複チェックしていたが、改行や全角空白の
        //   ちょっとした差で false になり、却下→再生成→再オープンのたびに署名が積み上がって
        //   「メール本文に署名が複数並ぶ」事故が起きていた。
        //   draftId == null の純粋な新規 reply/compose の時だけ挿入することで根本解決する。
        async insertDefaultSignature() {
            try {
                await this.loadSignatures();
                // (1) 既存ドラフト再オープン時は何もしない (=既に署名がある前提)
                if (this.draftId) {
                    // 現在使われている署名を currentSignatureText に登録しておく (差し替え機能用)
                    const matched = (this.signatures || []).find(s => s.body && (this.form.body || '').includes(s.body));
                    if (matched) this.currentSignatureText = matched.body;
                    this._focusQuillAtStart();
                    return;
                }
                // (2) 新規 reply/compose
                const def = (this.signatures || []).find(s => s.is_default);
                if (!def) return;
                // 念のため: 既に本文中に「同一の署名テキストの先頭 12 文字」が含まれていれば
                //   再挿入をスキップ (例: 内部下書きシステムが署名を埋め込んだ場合の二重挿入防止)。
                const defHead = (def.body || '').replace(/\s+/g, '').slice(0, 12);
                const bodyCompact = (this.form.body || '').replace(/\s+/g, '');
                if (defHead && bodyCompact.includes(defHead)) {
                    this.currentSignatureText = def.body || '';
                    this._focusQuillAtStart();
                    return;
                }
                const nextBodyPlain = this._insertSignatureIntoBody(this.form.body || '', def.body || '');
                this.setBodyText(nextBodyPlain);
                this.currentSignatureText = def.body || '';
                // 挿入後はカーソルを本文先頭へ
                this._focusQuillAtStart();
            } catch (_) {}
        },

        // 本文 textarea のカーソルを位置 0 に置きフォーカス。
        // signature / template 挿入後に「署名の上から書き始められる」体験のためのヘルパ。
        // Quill 廃止後は単純な textarea 操作だが、互換性のため旧名のまま残す。
        _focusQuillAtStart() {
            this.$nextTick(() => {
                try {
                    const el = this.$refs.bodyTextarea;
                    if (!el) return;
                    el.focus();
                    // setSelectionRange(0,0) でキャレットを本文先頭に
                    if (typeof el.setSelectionRange === 'function') {
                        el.setSelectionRange(0, 0);
                    }
                    // 念のためスクロールも先頭へ
                    el.scrollTop = 0;
                } catch (_) {}
            });
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

        // 既存添付 (下書きから引き継いだファイル) の削除
        // 配列から外しておくと buildFormData の keep_attachments[] に含まれなくなり、
        // バックエンドで新 pending に引き継がれなくなる
        removeExistingAttachment(i) { this.existingAttachments.splice(i, 1); },

        toggleAi() {
            this.aiPanelOpen = !this.aiPanelOpen;
            if (this.aiPanelOpen) {
                this.loadAiModels();
                // 返信/転送モードで thread.id がある場合は AI チャットも初期化
                if (this.aiChatThreadId && this.aiChat.messages.length === 0) {
                    this.loadAiChat();
                }
            } else {
                this._stopAiChatPoll();
            }
        },
        get aiCurrentModels() {
            if (this.aiProvider === 'claude') return this.aiClaudeModels;
            if (this.aiProvider === 'gemini') return this.aiGeminiModels;
            return this.aiOllamaModels;
        },
        async loadAiModels(force = false) {
            if (this.aiPickerLoaded && !force) return;
            this.aiPickerLoading = true;
            try {
                const res = await fetch('{{ route("chat.models") }}', {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error('models endpoint error');
                const data = await res.json();
                this.aiOllamaModels = (data.ollama || []).map(m => typeof m === 'string' ? { id: m, name: m } : m);
                this.aiClaudeModels = data.claude || [];
                this.aiGeminiModels = data.gemini || [];
                this.aiHasClaudeKey = !!data.has_claude_key;
                this.aiHasGeminiKey = !!data.has_gemini_key;
                this.aiPickerLoaded = true;
                if (!this.aiModel) {
                    const list = this.aiCurrentModels;
                    if (list.length > 0) this.aiModel = list[0].id || list[0];
                }
            } catch (e) {
                // silent
            } finally {
                this.aiPickerLoading = false;
            }
        },
        setAiProvider(p) {
            this.aiProvider = p;
            const list = this.aiCurrentModels;
            this.aiModel = list.length > 0 ? (list[0].id || list[0]) : '';
        },
        async askAi() {
            if (!this.canAskAi) { this.toast('AI を呼び出せる状態ではありません', 'error'); return; }
            this.aiLoading = true; this.aiAnalysis = null;
            try {
                const isCompose = this.mode === 'compose';
                const url = isCompose ? '/emails/ai-compose' : `/emails/${this.email.id}/ai`;
                const basePayload = {
                    prompt:   this.aiUserPrompt,
                    skill:    this.aiSkill,
                    mask_pii: this.maskPii,
                    provider: this.aiProvider || null,
                    model:    this.aiModel    || null,
                };
                const payload = isCompose
                    ? { ...basePayload, subject: this.form.subject, body: this.form.body, to: this.form.to }
                    : basePayload;
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const initial = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.toast('AI生成の開始に失敗しました: ' + (initial.message || res.status), 'error');
                    this.aiLoading = false;
                    return;
                }
                // タスクを受け取ったら完了までポーリング
                const finalData = await this._pollAiTask(initial.task_id);
                if (!finalData) { this.aiLoading = false; return; }
                if (finalData.status === 'error') {
                    const code = finalData.error_code;
                    const msg  = finalData.error_message || 'AI処理でエラーが発生しました';
                    let prefix = '';
                    if (code === 'insufficient_credits') prefix = '【クレジット不足】';
                    else if (code === 'invalid_api_key') prefix = '【APIキー無効】';
                    else if (code === 'rate_limited')    prefix = '【レート制限】';
                    else if (code === 'model_not_found') prefix = '【モデル未存在】';
                    else if (code === 'rag_api_unreachable') prefix = '【RAG API 未起動】';
                    this.toast(prefix + 'AI生成に失敗しました: ' + msg, 'error');
                    this._notifyDesktop('AI生成 失敗', prefix + msg);
                    this.aiLoading = false;
                    return;
                }
                this.simulateStreaming({
                    answer: finalData.answer,
                    skill_used: initial.skill_used,
                    sources: initial.sources,
                });
                this.toast('AI生成が完了しました' + (initial.skill_used ? ' (' + initial.skill_used + ')' : ''), 'success');
                this._notifyDesktop('AI返信 完了', initial.skill_used ? initial.skill_used + ' で生成されました' : '返信案が生成されました');
            } catch (e) {
                this.toast('AI生成に失敗しました: ' + (e.message || ''), 'error');
                this.aiLoading = false;
            }
        },

        // AiTask を done/error までポーリング (最大 180s)
        async _pollAiTask(taskId, maxWaitMs = 180000, intervalMs = 1500) {
            const started = Date.now();
            while (Date.now() - started < maxWaitMs) {
                try {
                    const res = await fetch(`/ai-tasks/${taskId}`, { headers: { Accept: 'application/json' } });
                    if (res.ok) {
                        const data = await res.json();
                        if (data.status === 'done' || data.status === 'error') return data;
                    }
                } catch (_) { /* 一時的なネットワークエラーは継続 */ }
                await new Promise(r => setTimeout(r, intervalMs));
            }
            this.toast('タイムアウト: AI 処理に時間がかかっています', 'error');
            return null;
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
            // AI の回答は plain text 想定。エディタ末尾に挿入する。
            const prefix = this.form.body ? '\n\n' : '';
            this.appendBodyText(prefix + this.aiAnalysis.answer);
            this.toast('本文に反映しました', 'success');
        },

        // ===== AI チャット (返信案を多ターン対話でブラッシュアップ) =====
        get aiChatThreadId() {
            return this.thread?.id || null;
        },
        async openAiChatPanel() {
            // 既存の AI パネルトグルと統合する形で動かす. 開いた直後に履歴を取り直す.
            this.aiPanelOpen = true;
            await this.loadAiChat();
        },
        async loadAiChat() {
            if (!this.aiChatThreadId) return;
            this.aiChat.loading = true;
            try {
                const url = '/threads/' + this.aiChatThreadId + '/ai-chat?kind=reply';
                const r = await fetch(url, { headers: { 'Accept':'application/json' } });
                if (!r.ok) { this.toast('AI チャットの読み込みに失敗しました', 'error'); return; }
                const d = await r.json();
                this.aiChat.sessionId = d.session?.id || null;
                this.aiChat.messages  = Array.isArray(d.messages) ? d.messages : [];
                if (this.aiChat.messages.some(m => m.role === 'assistant' && m.status === 'pending')) {
                    this._startAiChatPoll();
                }
                this.$nextTick(() => this._scrollAiChatToBottom());
            } catch (e) {
                this.toast('通信エラー: ' + e.message, 'error');
            } finally {
                this.aiChat.loading = false;
            }
        },
        // HTML エスケープ (チャット bubble + textarea overlay 共用)
        _escapeChatHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        },
        // '/word' を青チップで wrap した HTML を返す (emails/index と同じ仕様).
        renderAiChatTaggedHtml(text) {
            const esc = (s) => this._escapeChatHtml(s);
            const src = String(text ?? '');
            const re = /(^|[\s\n\t])\/([\p{L}\p{N}_\-.]+)/gu;
            let out = '', last = 0;
            for (const m of src.matchAll(re)) {
                const startName = m.index + m[1].length;
                out += esc(src.slice(last, startName));
                const name = m[2];
                out += '<span class="rice-ai-tag">/' + esc(name) + '</span>';
                last = startName + 1 + name.length;
            }
            out += esc(src.slice(last));
            if (out.endsWith('\n')) out += ' ';
            return out;
        },

        // ===== AI チャット入力欄の '/' ポップアップ (スキル + コレクション) =====
        handleChatSlashInput() {
            const ta = this.$refs.composeChatInput;
            if (!ta) { this.aiChatSlash.open = false; return; }
            const value = ta.value || '';
            const pos = ta.selectionStart ?? value.length;
            let slashIdx = -1;
            for (let i = pos - 1; i >= 0; i--) {
                const ch = value[i];
                if (ch === ' ' || ch === '\n' || ch === '\t') break;
                if (ch === '/') { slashIdx = i; break; }
            }
            if (slashIdx < 0) { this.aiChatSlash.open = false; return; }
            this.aiChatSlash.tokenStart = slashIdx;
            this.aiChatSlash.query      = value.slice(slashIdx + 1, pos);
            this.aiChatSlash.open       = true;
            // コレクション一覧をロード (compose-window 側で既にある loadAiCollections を流用)
            this.loadAiCollections();
        },
        filteredChatSkillsObj() {
            const q = (this.aiChatSlash.query || '').toLowerCase();
            const map = this.aiSkills || {};
            if (!q) return map;
            const out = {};
            for (const k of Object.keys(map)) {
                const name = String(map[k]?.name || '').toLowerCase();
                if (k.toLowerCase().includes(q) || name.includes(q)) out[k] = map[k];
            }
            return out;
        },
        filteredChatSkills() {
            return Object.keys(this.filteredChatSkillsObj());
        },
        filteredChatCollections() {
            const q = (this.aiChatSlash.query || '').toLowerCase();
            const all = this.aiCollections || [];
            if (!q) return all;
            return all.filter(c => String(c.name || '').toLowerCase().includes(q));
        },
        pickChatSlashSkill(key) {
            const ta = this.$refs.composeChatInput;
            const slot = this.aiChatSlash;
            if (ta && slot.tokenStart >= 0) {
                const value = ta.value || '';
                const pos = ta.selectionStart ?? value.length;
                const inserted = '/' + key + ' ';
                this.aiChat.input = value.slice(0, slot.tokenStart) + inserted + value.slice(pos);
                this.$nextTick(() => {
                    if (ta) {
                        const newPos = slot.tokenStart + inserted.length;
                        try { ta.setSelectionRange(newPos, newPos); ta.focus(); } catch (_) {}
                    }
                });
            }
            this.aiChatSlash.open = false;
            this.toast('スキル: ' + (this.aiSkills?.[key]?.name || key) + ' を選択', 'info');
        },
        pickChatSlashCollection(name) {
            const ta = this.$refs.composeChatInput;
            const slot = this.aiChatSlash;
            if (ta && slot.tokenStart >= 0) {
                const value = ta.value || '';
                const pos = ta.selectionStart ?? value.length;
                const inserted = '/' + name + ' ';
                this.aiChat.input = value.slice(0, slot.tokenStart) + inserted + value.slice(pos);
                this.$nextTick(() => {
                    if (ta) {
                        const newPos = slot.tokenStart + inserted.length;
                        try { ta.setSelectionRange(newPos, newPos); ta.focus(); } catch (_) {}
                    }
                });
            }
            this.aiChatSlash.open = false;
            this.toast('コレクション: /' + name + ' を本文に挿入', 'info');
        },

        // テキスト内の '/スキル名' を検出. 本文は そのまま 残し, skillKey だけ別途返す.
        // (チャット履歴に何のスキルで投げたかが見えるようにするため. emails/index と同じ仕様)
        _extractSkillFromText(raw) {
            const text = String(raw ?? '');
            const skills = this.aiSkills || {};
            const re = /(^|[\s\n\t])\/([\p{L}\p{N}_-]+)/u;
            const m = text.match(re);
            if (!m) return { text, skillKey: null };
            const candidate = m[2].toLowerCase();
            let hit = null;
            for (const key of Object.keys(skills)) {
                if (key.toLowerCase() === candidate) { hit = key; break; }
            }
            if (!hit) {
                for (const key of Object.keys(skills)) {
                    if (key.toLowerCase().includes(candidate)) { hit = key; break; }
                }
            }
            if (!hit) {
                for (const key of Object.keys(skills)) {
                    const name = String(skills[key]?.name || '').toLowerCase();
                    if (name.includes(candidate)) { hit = key; break; }
                }
            }
            return { text, skillKey: hit };
        },

        async sendAiChat() {
            if (this.aiChat.sending) return;
            if (!this.aiChatThreadId) {
                this.toast('新規メール作成では AI チャットは利用できません (返信/転送のみ)', 'error');
                return;
            }
            // テキスト中の '/スキル名' / '/コレクション名' を検出してフロント側で処理
            const raw = (this.aiChat.input || '').trim();
            const ext = this._extractSkillFromText(raw);
            const text = ext.text.trim();
            if (text === '') return;
            this.aiChat.sending = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                let url;
                // モデルピッカー / スキルの変更を 毎ターン サーバに反映させるため
                // followUp でも provider / model / skill を送る.
                const common = {
                    message:  text,
                    provider: this.aiProvider || null,
                    model:    this.aiModel    || null,
                };
                if (ext.skillKey) common.skill = ext.skillKey;
                let body;
                if (this.aiChat.sessionId) {
                    url  = '/ai-chat-sessions/' + this.aiChat.sessionId + '/messages';
                    body = JSON.stringify(common);
                } else {
                    url  = '/threads/' + this.aiChatThreadId + '/ai-chat';
                    body = JSON.stringify({ kind: 'reply', ...common });
                }
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': csrf },
                    body,
                });
                if (!r.ok) {
                    const j = await r.json().catch(() => ({}));
                    this.toast(j.message || ('HTTP ' + r.status), 'error');
                    return;
                }
                const d = await r.json();
                if (d.session?.id) this.aiChat.sessionId = d.session.id;
                if (d.user)      this.aiChat.messages.push(d.user);
                if (d.assistant) this.aiChat.messages.push(d.assistant);
                this.aiChat.input = '';
                this.$nextTick(() => this._scrollAiChatToBottom());
                this._startAiChatPoll();
            } catch (e) {
                this.toast('通信エラー: ' + e.message, 'error');
            } finally {
                this.aiChat.sending = false;
            }
        },
        async retryAiChatMessage(assistantMessage) {
            if (!this.aiChat.sessionId) return;
            const idx = this.aiChat.messages.findIndex(m => m.id === assistantMessage.id);
            let lastUserContent = '';
            for (let i = idx - 1; i >= 0; i--) {
                if (this.aiChat.messages[i].role === 'user') {
                    lastUserContent = this.aiChat.messages[i].content || '';
                    break;
                }
            }
            if (!lastUserContent) return;
            this.aiChat.input = lastUserContent;
            this.toast('もう一度送信しています…', 'info');
            await this.sendAiChat();
        },
        async resetAiChat() {
            if (!this.aiChat.sessionId) {
                this.aiChat.messages = [];
                return;
            }
            if (!confirm('この会話履歴をすべて削除します. 取り消しはできません. よろしいですか?')) return;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const r = await fetch('/ai-chat-sessions/' + this.aiChat.sessionId, {
                    method: 'DELETE',
                    headers: { 'Accept':'application/json', 'X-CSRF-TOKEN': csrf },
                });
                if (!r.ok) { this.toast('削除に失敗しました', 'error'); return; }
                this.aiChat.sessionId = null;
                this.aiChat.messages  = [];
                this._stopAiChatPoll();
                this.toast('会話履歴を削除しました', 'success');
            } catch (e) {
                this.toast('通信エラー: ' + e.message, 'error');
            }
        },
        applyAiChatMessageToBody(m) {
            if (!m || !m.content) return;
            const prefix = this.form.body ? '\n\n' : '';
            this.appendBodyText(prefix + m.content);
            this.toast('本文に反映しました', 'success');
        },
        appendAiChatMessageToBody(m) {
            // 末尾に追加 (改行 1 つ挟む)
            if (!m || !m.content) return;
            const prefix = this.form.body ? '\n\n' : '';
            this.appendBodyText(prefix + m.content);
            this.toast('本文に追記しました', 'success');
        },
        replaceBodyWithAiChatMessage(m) {
            if (!m || !m.content) return;
            this.form.body = m.content;
            this.toast('本文を置き換えました', 'success');
            // 本文エリアにフォーカス
            this.$nextTick(() => {
                const el = this.$refs.bodyTextarea;
                if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); }
            });
        },
        copyAiChatMessage(m) {
            if (!m || !m.content) return;
            try {
                navigator.clipboard.writeText(m.content);
                this.toast('コピーしました', 'success');
            } catch (_) { this.toast('コピーに失敗しました', 'error'); }
        },
        _startAiChatPoll() {
            this._stopAiChatPoll();
            this.aiChat.pollTimer = setInterval(async () => {
                if (!this.aiChatThreadId || !this.aiChat.sessionId) {
                    this._stopAiChatPoll();
                    return;
                }
                try {
                    const url = '/threads/' + this.aiChatThreadId + '/ai-chat?kind=reply';
                    const r = await fetch(url, { headers: { 'Accept':'application/json' } });
                    if (!r.ok) return;
                    const d = await r.json();
                    const next = Array.isArray(d.messages) ? d.messages : [];
                    this.aiChat.messages = next;
                    const hasPending = next.some(m => m.role === 'assistant' && m.status === 'pending');
                    if (!hasPending) {
                        this._stopAiChatPoll();
                        this.$nextTick(() => this._scrollAiChatToBottom());
                    }
                } catch (_) {}
            }, 2000);
        },
        _stopAiChatPoll() {
            if (this.aiChat.pollTimer) {
                clearInterval(this.aiChat.pollTimer);
                this.aiChat.pollTimer = null;
            }
        },
        _scrollAiChatToBottom() {
            const el = document.getElementById('compose-ai-chat-messages');
            if (el) el.scrollTop = el.scrollHeight;
        },

        // FormDataを構築
        // body は互換のためのプレーンテキスト、body_html は Quill が出した HTML。
        // サーバ側は body_html を主、body をフォールバックとして扱う。
        buildFormData(asDraft = false) {
            const fd = new FormData();
            fd.append('body',      this.form.body      || (asDraft ? '(下書き)' : ''));
            fd.append('body_html', this.form.body_html || '');
            fd.append('to', this.form.to || '');
            fd.append('from_address', this.form.from || '');
            if (this.form.mail_account_id !== null && this.form.mail_account_id !== undefined) {
                fd.append('mail_account_id', this.form.mail_account_id);
            }
            fd.append('cc', this.form.cc || '');
            fd.append('bcc', this.form.bcc || '');
            fd.append('subject', this.form.subject || (asDraft ? '(下書き)' : ''));
            if (this.form.approver_id) fd.append('approver_id', this.form.approver_id);
            if (this.draftId) fd.append('draft_id', this.draftId);
            if (asDraft) fd.append('save_as_draft', '1');
            // 下書き編集時、削除されずに残っている既存添付のパスを送信
            // (UI で削除されたものは existingAttachments から外れているので含まれない。
            //  全て削除した場合は keep_attachments を送らないが、backend は draft_id を見て
            //  「keep_attachments が空 = 全削除」と解釈する)
            this.existingAttachments.forEach(att => fd.append('keep_attachments[]', att.path));
            this.selectedFiles.forEach(f => fd.append('attachments[]', f));
            // 転送モード: ユーザがチェックを残した「元メールから引き継ぐ添付」の ID を送信.
            // controller 側はこれらをファイル実体ごと pending ストレージへコピーする.
            if (this.mode === 'forward') {
                (this.inheritAttachmentIds || []).forEach(id => fd.append('inherit_attachment_ids[]', id));
            }
            return fd;
        },

        getSubmitUrl() {
            // 転送モード: /emails/{id}/forward に送る. controller 側で subject + inherit_attachment_ids を解釈する.
            if (this.mode === 'forward' && this.email && this.email.id) {
                return `/emails/${this.email.id}/forward`;
            }
            return (this.mode === 'compose' || !this.email)
                ? '/emails/compose'
                : `/emails/${this.email.id}/reply`;
        },

        // 通常送信 (承認依頼)
        // 「承認を依頼する」ボタン押下 → 送信確認モーダルを開く
        async submitDraft() {
            if (!this.form.body || this.sending) return;
            // 必須項目の最低限チェック
            if (!this.form.to) {
                this.toast('宛先 (To) を入力してください', 'error');
                return;
            }
            this.sendConfirmOpen = true;
        },

        // モーダルで「承認依頼を送信」を押下 → 実際に送信
        async submitDraftConfirmed() {
            if (!this.form.body || this.sending) return;
            this.sending = true;
            try {
                const res = await fetch(this.getSubmitUrl(), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    body: this.buildFormData(false),
                });
                let data = {};
                try { data = await res.json(); } catch(_) {}

                if (res.ok) {
                    this.clearLocalDraft();
                    this.notifyOpener();
                    const approverName = this.selectedApprover ? this.selectedApprover.name : null;
                    this.toast(approverName
                        ? `${approverName} さんに承認依頼を送信しました`
                        : '承認待ちとして送信しました', 'success');
                    // beforeunload を抑止するため、close する前にフラグを立てる
                    this.sentCompleted = true;
                    this.setBodyText('');           // Quill エディタも空に
                    this.form.subject = '';
                    this.selectedFiles = [];
                    setTimeout(() => {
                        try { window.close(); } catch(_) {}
                    }, 400);
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

        // ===== 自己送信 (Self-Send) =====
        // 「今すぐ送信」 / 「この日時で予約送信」ボタン押下時に呼ばれる.
        // 承認フローを経由せず、作成者自身の判断で送信する.
        // 管理者が send_policy = approval_required にしている場合はサーバ側が 403 を返す.
        // 流れ:
        //   1. 入力チェック (to / subject / body)
        //   2. saveDraftToServer() で下書きを保存 → pending_id を得る
        //   3. POST /pending-emails/{id}/self-send (scheduled_for 任意)
        //      - scheduled_for 空: 即時送信 (mode=immediate)
        //      - scheduled_for 未来: 予約に切替 (mode=scheduled)
        //   4. 成功なら opener に通知して閉じる
        // 予約中バナーから「予約を取消して下書きに戻す」を押した時のハンドラ.
        // POST /pending-emails/{id}/unschedule → 成功で draftIsScheduled をクリアし
        // 現在のウィンドウはそのまま編集続行 (form.scheduled_for もクリア).
        async cancelScheduleFromBanner() {
            if (!this.draftId || this.cancellingSchedule) return;
            if (!confirm(`予約送信 (${this.draftScheduledLabel || ''}) を取り消して下書きに戻しますか？`)) return;
            this.cancellingSchedule = true;
            try {
                const res = await fetch(`/pending-emails/${this.draftId}/unschedule`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.status === 'error') {
                    this.toast(data.message || '予約取消に失敗しました', 'error');
                    return;
                }
                this.toast('予約を取り消し、下書きに戻しました', 'success');
                this.draftIsScheduled = false;
                this.draftScheduledLabel = null;
                this.form.scheduled_for = '';
                this.notifyOpener();
            } catch (e) {
                this.toast('予約取消に失敗しました: ' + (e.message || ''), 'error');
            } finally {
                this.cancellingSchedule = false;
            }
        },

        async sendNow() {
            if (this.sending) return;
            if (!this.form.body) { this.toast('本文を入力してください', 'error'); return; }
            if (!this.form.to)   { this.toast('宛先 (To) を入力してください', 'error'); return; }
            if (!this.form.subject) { this.toast('件名を入力してください', 'error'); return; }

            // 予約日時のバリデーション (任意)
            let scheduledIso = '';
            if (this.form.scheduled_for) {
                const when = new Date(this.form.scheduled_for);
                if (isNaN(when.getTime())) {
                    this.toast('送信日時が不正です', 'error');
                    return;
                }
                if (when.getTime() <= Date.now() - 60 * 1000) {
                    this.toast('予約日時は現在以降を指定してください', 'error');
                    return;
                }
                scheduledIso = this.form.scheduled_for;
            }

            this.sending = true;
            try {
                // (1) まず下書きとして保存して pending_id を得る
                //    既に draftId がある場合は更新されるだけ.
                const draftRes = await fetch(this.getSubmitUrl(), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    body: this.buildFormData(true),
                });
                let draftData = {};
                try { draftData = await draftRes.json(); } catch(_) {}
                if (!draftRes.ok) {
                    if (draftRes.status === 422 && draftData.errors) {
                        const errs = Object.values(draftData.errors).flat().join('\n');
                        this.toast('入力エラー: ' + errs, 'error');
                    } else {
                        this.toast('下書き保存に失敗しました: ' + (draftData.message || `HTTP ${draftRes.status}`), 'error');
                    }
                    this.sending = false;
                    return;
                }
                const pendingId = this.draftId || draftData.id;
                if (!pendingId) {
                    this.toast('下書きID取得失敗 (保存はされています)', 'error');
                    this.sending = false;
                    return;
                }
                this.draftId = pendingId;

                // (2) 自己送信エンドポイントを叩く
                const fd = new FormData();
                if (scheduledIso) fd.append('scheduled_for', scheduledIso);
                const sendRes = await fetch(`/pending-emails/${pendingId}/self-send`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    body: fd,
                });
                let sendData = {};
                try { sendData = await sendRes.json(); } catch(_) {}

                if (sendRes.ok) {
                    this.clearLocalDraft();
                    this.notifyOpener();
                    const msg = sendData.mode === 'scheduled'
                        ? `${this.scheduledForLabel} に予約しました`
                        : '送信しました';
                    this.toast(msg, 'success');
                    this.sentCompleted = true;
                    this.setBodyText('');
                    this.form.subject = '';
                    this.selectedFiles = [];
                    setTimeout(() => { try { window.close(); } catch(_) {} }, 400);
                } else if (sendRes.status === 403) {
                    // ポリシー (approval_required) で弾かれた
                    this.toast(sendData.message || '管理者の設定により、送信には承認が必要です. 「承認を依頼」を使ってください.', 'error');
                    // ポリシーを最新に更新してボタン表示を切替
                    this.sendPolicy = 'approval_required';
                } else if (sendRes.status === 422) {
                    this.toast('送信できません: ' + (sendData.message || '入力内容に誤りがあります'), 'error');
                } else {
                    this.toast('送信失敗: ' + (sendData.message || sendData.error || `HTTP ${sendRes.status}`), 'error');
                }
            } catch (e) {
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            } finally {
                this.sending = false;
            }
        },

        // 下書き保存（サーバー）
        async saveDraftToServer() {
            if (this.savingDraft) return;
            this.savingDraft = true;
            try {
                const res = await fetch(this.getSubmitUrl(), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    body: this.buildFormData(true),
                });
                let data = {};
                try { data = await res.json(); } catch(_) {}
                if (!res.ok) throw new Error(data.message || data.error || `HTTP ${res.status}`);
                this.lastSavedAt = new Date().toLocaleTimeString();
                this.toast('下書きを保存しました', 'success');
                return data;
            } catch (e) {
                this.toast('下書き保存に失敗: ' + (e.message || ''), 'error');
                throw e;
            } finally {
                this.savingDraft = false;
            }
        },

        // ===== 予約送信 (Scheduled Send) =====
        // ★ 仕様 (2026-05 改定): 予約送信は「ユーザの個別送信」扱い. 管理者承認は不要.
        //   - form.scheduled_for が未来日時なら sendNow() に委譲し、/pending-emails/{id}/self-send
        //     経由で status=scheduled に遷移 (送信は cron が拾う).
        //   - 管理者ポリシーが approval_required でも、予約送信は承認バイパスで OK
        //     (backend selfSend が hasFutureSchedule 時にポリシーチェックをスキップ).
        async scheduleSend() {
            if (this.sending) return;
            if (!this.form.scheduled_for) { this.toast('送信日時を指定してください', 'error'); return; }
            const when = new Date(this.form.scheduled_for);
            if (isNaN(when.getTime()) || when.getTime() <= Date.now() - 60 * 1000) {
                this.toast('予約日時は現在以降を指定してください', 'error');
                return;
            }
            if (!this.form.to) { this.toast('宛先が空のため予約できません', 'error'); return; }
            if (!this.form.subject) { this.toast('件名が空のため予約できません', 'error'); return; }

            // 予約送信 = 自己送信フロー. 承認依頼は経由しない (sendNow が /self-send を叩く).
            await this.sendNow();
        },
        // 表示用ラベル ("M/D HH:MM"). form.scheduled_for が空なら空文字.
        get scheduledForLabel() {
            const s = this.form.scheduled_for;
            if (!s) return '';
            try {
                const d = new Date(s);
                if (isNaN(d.getTime())) return s;
                return d.getMonth() + 1 + '/' + d.getDate() + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
            } catch (_) { return s; }
        },
        // datetime-local の min 属性 (今から 1 分後)
        get scheduledForMinValue() {
            const d = new Date(Date.now() + 60 * 1000);
            const pad = (n) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
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
            this.sentCompleted = true; // beforeunload 抑止
            try { window.close(); } catch(_) {}
        },
        async saveDraftAndClose() {
            try {
                await this.saveDraftToServer();
                this.clearLocalDraft();
                this.setBodyText('');           // Quill エディタも空に
                this.selectedFiles = [];
                this.closeConfirmOpen = false;
                this.sentCompleted = true; // beforeunload 抑止
                // メール一覧 (opener) に下書き保存完了を通知してから閉じる
                try {
                    if (window.opener && !window.opener.closed) {
                        window.opener.postMessage({ type: 'rice-mail-draft-saved', mode: this.mode }, window.location.origin);
                    }
                } catch (_) {}
                try { window.close(); } catch(_) {}
            } catch (_) {
                // エラー時は閉じない
            }
        },
        discardAndClose() {
            this.clearLocalDraft();
            this.setBodyText('');           // Quill エディタも空に
            this.selectedFiles = [];
            this.closeConfirmOpen = false;
            this.sentCompleted = true; // beforeunload 抑止
            try { window.close(); } catch(_) {}
        },

        // 左ペイン (スレッド) のドラッグリサイズ
        startResizeLeftPane(e) {
            const startX = e.clientX;
            const startW = this.leftPaneWidth;
            const minW = 280;
            const maxW = Math.max(minW + 1, window.innerWidth - 360); // 右側に最低 360px 残す
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            const onMove = (me) => {
                const next = startW + (me.clientX - startX);
                this.leftPaneWidth = Math.max(minW, Math.min(maxW, next));
            };
            const onUp = () => {
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                try { localStorage.setItem('composeWindowLeftPaneWidth', this.leftPaneWidth); } catch(_) {}
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },
        // ダブルクリックでデフォルトに戻す
        resetLeftPane() {
            this.leftPaneWidth = Math.max(420, Math.min(680, Math.floor(window.innerWidth * 0.45)));
            try { localStorage.setItem('composeWindowLeftPaneWidth', this.leftPaneWidth); } catch(_) {}
        },
    };
}
</script>

<style>
[x-cloak] { display: none !important; }
.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* ===== プロンプト編集: /コレクション をグレーチップとして可視化 ===== */
.prompt-editor-container { position: relative; background-color: #ffffff; border-radius: 0.5rem; }
.prompt-editor-highlight,
.prompt-editor-input {
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Hiragino Kaku Gothic ProN", "Yu Gothic", sans-serif;
    font-size: 0.75rem;            /* text-xs */
    line-height: 1.5;
    padding: 0.75rem;              /* p-3 */
    letter-spacing: normal;
    word-spacing: normal;
    tab-size: 4;
}
.prompt-editor-highlight {
    position: absolute;
    inset: 0;
    border: 1px solid transparent;
    border-radius: 0.5rem;
    pointer-events: none;
    white-space: pre-wrap;
    word-wrap: break-word;
    overflow-y: auto;
    color: #111827;
    background: transparent;
    z-index: 1;
}
.prompt-editor-input {
    position: relative;
    z-index: 2;
    background: transparent !important;
    color: transparent !important;
    -webkit-text-fill-color: transparent;
    caret-color: #111827;
}
.prompt-editor-input::selection { background-color: rgba(99, 102, 241, 0.25); color: transparent; }
.prompt-editor-highlight .col-tag {
    background-color: #e5e7eb;
    color: #374151;
    border-radius: 4px;
    padding: 1px 2px;
    margin: 0 -1px;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.04);
}

/* スレッド ↔ ドラフト の境界リサイズハンドル */
.resize-handle {
    width: 6px;
    height: 100%;
    background-color: #e5e7eb;
    cursor: col-resize;
    transition: background-color 0.15s;
    position: relative;
    user-select: none;
}
.resize-handle:hover,
.resize-handle:active {
    background-color: #2563eb;
}
.resize-handle::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 2px;
    height: 32px;
    background: #9ca3af;
    border-radius: 1px;
    opacity: .6;
}
.resize-handle:hover::after { background: #ffffff; opacity: 1; }
</style>
@endsection

{{-- Quill リッチエディタは廃止 (プレーン textarea に変更) のため CDN ロード不要 --}}
