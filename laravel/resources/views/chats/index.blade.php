@extends('layouts.app')
@section('title', 'チャット一覧 - Rice')

@section('css')
<style>
    body.chats-page { overflow: hidden !important; }
    body.chats-page .content-header { display: none !important; }
    body.chats-page .main-footer { display: none !important; }
    body.chats-page .content-wrapper {
        padding: 0 !important;
        overflow: hidden !important;
    }
    body.chats-page .content,
    body.chats-page .content > .container-fluid {
        padding: 0 !important; margin: 0 !important; max-width: none !important; width: 100% !important;
        height: calc(100vh - 3.5rem) !important; min-height: 0 !important; overflow: hidden !important; background:#f9fafb;
    }
    body.chats-page .content > .container-fluid { height: 100% !important; }

    .chats-root { height:100%; width:100%; min-width:0; min-height:0; overflow:hidden; display:flex; }
    [x-cloak] { display:none !important; }

    /* ===== ライト系統 (アプリの既存スタイル) ===== */
    .chat-sidebar      {
        background:#ffffff; color:#374151; border-right:1px solid #e5e7eb;
        transition: width 0.2s ease;
    }
    /* 折りたたみ時: 32px の細長いバー、中身は非表示 */
    .chat-sidebar.is-collapsed { overflow:hidden; }
    .chat-sidebar.is-collapsed > *:not(.sidebar-collapse-toggle) { display:none !important; }
    .chat-sidebar.is-collapsed .chat-resize { display:none !important; }
    /* 折りたたみトグル (常時右上隅・円形) */
    .sidebar-collapse-toggle {
        position:absolute; top:6px; right:6px; z-index:20;
        width:22px; height:22px;
        background:#ffffff; border:1px solid #e5e7eb; border-radius:50%;
        color:#6b7280; font-size:10px; cursor:pointer;
        display:inline-flex; align-items:center; justify-content:center;
        box-shadow:0 1px 3px rgba(0,0,0,0.08);
        transition:background 0.15s, color 0.15s;
        padding:0;
    }
    .sidebar-collapse-toggle:hover { background:#f3f4f6; color:#111827; }
    .chat-sidebar-head { background:#ffffff; border-bottom:1px solid #e5e7eb; }
    .chat-sidebar-section {
        padding: 8px 10px 2px; font-size:10px; font-weight:800;
        color:#9ca3af; text-transform:uppercase; letter-spacing:0.08em;
        display:flex; align-items:center; justify-content:space-between;
    }
    .chat-channel {
        color:#4b5563; padding:4px 8px; border-radius:6px; cursor:pointer;
        display:flex; align-items:center; gap:6px; margin:1px 6px;
        font-size:13px; min-height:28px;
    }
    .chat-channel:hover     { background:#f3f4f6; color:#111827; }
    .chat-channel.active    { background:#eff6ff; color:#1d4ed8; font-weight:700; border-left:3px solid #2563eb; padding-left:7px; }
    .chat-channel .hash     { color:#9ca3af; font-weight:700; }
    .chat-channel.active .hash { color:#1d4ed8; }
    .chat-channel .name     { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .chat-channel .badge-mention {
        background:#f59e0b; color:#fff; font-size:10px; font-weight:800;
        border-radius:8px; min-width:18px; height:18px; padding:0 5px;
        display:inline-flex; align-items:center; justify-content:center;
    }
    .chat-channel .badge-count {
        background:#e5e7eb; color:#4b5563; font-size:10px; font-weight:700;
        border-radius:8px; min-width:18px; height:16px; padding:0 5px;
        display:inline-flex; align-items:center; justify-content:center;
    }
    .chat-channel.active .badge-count { background:#2563eb; color:#fff; }
    /* バンドル先スレッドの「メール件数」バッジ — status 別に色分け.
       受信=青 / 保留=琥珀 / 承認待ち=橙. (メール一覧と同色で統一感). */
    .chat-channel .badge-email-unread {
        background:#3b82f6; color:#fff; font-size:10px; font-weight:800;
        border-radius:8px; min-width:18px; height:18px; padding:0 5px;
        display:inline-flex; align-items:center; justify-content:center;
        gap:3px; line-height:1;
    }
    .chat-channel .badge-email-unread i { font-size:8px; }
    .chat-channel.active .badge-email-unread { background:#1d4ed8; }
    .chat-channel .badge-email-hold {
        background:#f59e0b; color:#fff; font-size:10px; font-weight:800;
        border-radius:8px; min-width:18px; height:18px; padding:0 5px;
        display:inline-flex; align-items:center; justify-content:center;
        gap:3px; line-height:1;
    }
    .chat-channel .badge-email-hold i { font-size:8px; }
    .chat-channel.active .badge-email-hold { background:#d97706; }
    .chat-channel .badge-email-pending {
        background:#f97316; color:#fff; font-size:10px; font-weight:800;
        border-radius:8px; min-width:18px; height:18px; padding:0 5px;
        display:inline-flex; align-items:center; justify-content:center;
        gap:3px; line-height:1;
    }
    .chat-channel .badge-email-pending i { font-size:8px; }
    .chat-channel.active .badge-email-pending { background:#c2410c; }

    .chat-main         { background:#f9fafb; flex:1; display:flex; flex-direction:column; min-width:0; min-height:0; }
    .chat-header       { background:#ffffff; border-bottom:1px solid #e5e7eb; color:#111827; padding:6px 12px; display:flex; align-items:center; gap:8px; min-height:38px; flex-shrink:0; flex-wrap:wrap; position:relative; z-index:5; }
    .chat-header .hash { color:#9ca3af; font-weight:700; font-size:18px; }
    .chat-header h2    { color:#111827; font-weight:700; font-size:14px; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin:0; }
    .chat-header .meta { color:#9ca3af; font-size:11px; }
    /* メッセージ上部余白を削除 */
    .chat-messages     { background:#f9fafb; flex:1; overflow-y:auto; padding:0; min-height:0; }
    .chat-messages::-webkit-scrollbar { width:6px; }
    .chat-messages::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:4px; }
    .chat-messages::-webkit-scrollbar-thumb:hover { background:#9ca3af; }

    /* ===== メッセージ行 (Discord 風レイアウト — 色は当アプリのライト系) ===== */
    .msg-row {
        position:relative;
        padding: 4px 16px 4px 72px;
        min-height: 26px;
        line-height: 1.45;
        display: block;
    }
    /* 連投 (同分内・同一ユーザー) はタイトに */
    .msg-row.compact { padding: 1px 16px 1px 72px; min-height: 22px; }
    /* メッセージグループ毎の上余白 (compact は除く) — 背景色も延びる */
    .msg-row:not(.compact) { padding-top: 14px; }
    /* 奇数/偶数の交互背景 (連投は同色) */
    .msg-row.msg-bg-a { background-color: #ffffff; }
    .msg-row.msg-bg-b { background-color: #f8fafc; }
    .msg-row:hover { background-color: #eef2f6; }
    /* アバター: 左端の丸いイニシャルアイコン */
    .msg-row .avatar {
        display:flex !important;
        position:absolute; left:16px; top:14px;
        width:40px; height:40px; border-radius:50%;
        align-items:center; justify-content:center;
        color:#ffffff; font-weight:700; font-size:16px;
        flex-shrink:0;
        box-shadow:0 1px 2px rgba(0,0,0,0.08);
    }
    .msg-row.compact .avatar { display:none !important; }
    /* ヘッダー (名前 + 時刻) */
    .msg-row .ts-header {
        display:flex; align-items:baseline; gap:6px;
        line-height:1.2; margin-bottom:2px;
    }
    .msg-row.compact .ts-header { display:none; }
    .msg-row .author { color:#0f172a; font-weight:700; font-size:14px; }
    .msg-row .ts     { color:#94a3b8; font-size:11px; }
    /* 本文 — ヘッダー直下、アバター横に回り込み (msg-row の padding-left でアバター幅を確保済み) */
    .msg-row > .body {
        display:block !important;
        color:#1e293b; font-size:14px; line-height:1.45;
        white-space:normal; word-wrap:break-word;
        margin:0 !important; padding:0 !important;
        text-indent:0 !important;
    }
    .msg-row > .body > * {
        white-space:pre-wrap;
        margin:0; padding:0; text-indent:0;
    }
    /* compact 時の時刻 (ホバーで左端に小さく表示) */
    .msg-row .floating-ts {
        display:none;
        position:absolute; left:16px; top:50%; transform:translateY(-50%);
        width:40px; text-align:center;
        color:#94a3b8; font-size:10px;
    }
    .msg-row.compact:hover .floating-ts { display:block; }
    /* ホバー時のアクションボタン群 (返信/引用/削除) */
    .msg-row .msg-actions {
        position:absolute; right:12px; top:-12px; opacity:0; transition:opacity 0.15s;
        background:#ffffff; border:1px solid #e5e7eb; border-radius:8px;
        display:flex; padding:2px;
        box-shadow:0 2px 6px rgba(0,0,0,0.06);
    }
    .msg-row:hover .msg-actions { opacity:1; }
    .msg-row .msg-action-btn {
        background:none; border:none; color:#6b7280; padding:4px 8px; font-size:12px;
        border-radius:6px; cursor:pointer;
    }
    .msg-row .msg-action-btn:hover { background:#f3f4f6; color:#111827; }
    .msg-row .msg-action-btn.msg-action-del:hover { color:#dc2626; background:#fef2f2; }

    /* 返信中バナー */
    .reply-banner {
        display:flex; align-items:center; gap:10px;
        background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px;
        padding:8px 12px; margin-bottom:6px;
    }

    /* 絵文字ピッカー */
    .emoji-pop {
        position:absolute; bottom:calc(100% + 8px); right:16px;
        background:#ffffff; border:1px solid #e5e7eb; border-radius:10px;
        padding:8px; z-index:60; width:300px;
        box-shadow:0 10px 25px rgba(0,0,0,0.15);
    }
    .emoji-pop-head { font-size:10px; color:#6b7280; font-weight:700; text-transform:uppercase; padding:2px 4px 6px; }
    .emoji-grid { display:grid; grid-template-columns:repeat(8, 1fr); gap:2px; max-height:240px; overflow-y:auto; }
    .emoji-btn {
        background:none; border:none; padding:6px; cursor:pointer;
        font-size:18px; border-radius:6px; line-height:1;
    }
    .emoji-btn:hover { background:#f3f4f6; }

    /* リアクションピル */
    .reactions-row {
        display:flex; flex-wrap:wrap; gap:4px; margin-top:6px;
    }
    .reaction-pill {
        display:inline-flex; align-items:center; gap:4px;
        background:#f3f4f6; border:1px solid #e5e7eb;
        padding:2px 8px; border-radius:12px;
        font-size:12px; cursor:pointer; line-height:1.2;
    }
    .reaction-pill:hover { background:#e0e7ff; border-color:#a5b4fc; }
    .reaction-pill.reaction-mine { background:#dbeafe; border-color:#93c5fd; color:#1e40af; }
    .reaction-pill .emoji { font-size:14px; }
    .reaction-pill .count { font-weight:700; font-size:11px; }
    .msg-row .mention-self {
        background:#fef3c7; color:#92400e; border:1px solid #fde68a; font-weight:700; font-size:10px;
        padding:1px 5px; border-radius:3px; margin-left:6px;
    }
    .msg-row .mention-tag {
        background:#dbeafe; color:#1e40af; padding:0 3px; border-radius:3px; font-weight:600;
    }
    /* 自分宛メンションの行ハイライト (薄オレンジ) */
    .msg-row.is-mentioned-me {
        background:#fff7ed;
        border-left:3px solid #f97316;
        padding-left:69px;
    }
    .msg-row.is-mentioned-me:hover { background:#ffedd5; }
    .msg-row.is-mentioned-me.compact { padding-left:69px; }

    /* 添付ファイル */
    .chat-attachments { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
    .chat-att-image {
        max-width:200px; max-height:160px; border-radius:8px;
        border:1px solid #e5e7eb; cursor:zoom-in; object-fit:cover;
    }
    .chat-att-file {
        display:inline-flex; align-items:center; gap:6px;
        background:#ffffff; border:1px solid #e5e7eb; border-radius:8px;
        padding:6px 10px; font-size:12px; color:#374151; text-decoration:none;
        max-width:280px;
    }
    .chat-att-file:hover { background:#f3f4f6; color:#111827; border-color:#d1d5db; }
    .chat-att-file .name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:200px; font-weight:600; }
    .chat-att-file .size { color:#9ca3af; font-size:10px; }

    /* 入力欄: 選択中ファイルプレビュー */
    .chat-pending-files { display:flex; flex-wrap:wrap; gap:6px; padding:8px 0 4px; }
    .chat-pending-file {
        display:inline-flex; align-items:center; gap:4px;
        background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af;
        padding:3px 8px; border-radius:6px; font-size:11px; font-weight:600;
    }
    .chat-pending-file button { background:none; border:none; color:#1e40af; padding:0 2px; cursor:pointer; }
    .chat-pending-file button:hover { color:#dc2626; }

    .date-divider {
        display:flex; align-items:center;
        margin:16px 16px 8px;
        font-size:12px; color:#9ca3af; text-transform:uppercase; letter-spacing:0.05em;
    }
    .date-divider::before, .date-divider::after { content:''; flex:1; height:1px; background:#e5e7eb; }
    .date-divider span { padding:0 12px; font-weight:700; }

    /* 入力エリア (top padding 0 でメッセージ末尾と密接させる) */
    .chat-input-wrap   { background:#f9fafb; padding:0 16px 12px; flex-shrink:0; }
    .chat-input-box    {
        background:#ffffff; border:1px solid #e5e7eb; border-radius:10px;
        display:flex; align-items:center; gap:6px; padding:8px 12px;
        box-shadow:0 1px 2px rgba(0,0,0,0.04);
    }
    .chat-input-box:focus-within { border-color:#93c5fd; box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
    .chat-input-box textarea {
        flex:1; background:transparent; border:none; outline:none; resize:none;
        color:#111827; font-size:15px; line-height:1.5; max-height:200px;
        padding:4px 0;
    }
    .chat-input-box textarea::placeholder { color:#9ca3af; font-size:15px; }
    /* ボタン群 (添付/絵文字/送信) のサイズを揃える */
    .chat-input-box button {
        font-size:15px; line-height:1; padding:6px;
        display:inline-flex; align-items:center; justify-content:center;
        height:30px; width:30px; border-radius:6px;
    }
    .chat-input-box button i { font-size:15px; line-height:1; }
    /* ヒント (送信方法説明) — 全要素のフォントサイズと縦位置を統一 */
    .chat-send-hint    { color:#9ca3af; font-size:11px; line-height:1.8; padding:6px 4px 0; }
    .chat-send-hint kbd {
        background:#f3f4f6; border:1px solid #e5e7eb; padding:0 5px;
        border-radius:3px; font-size:10px; line-height:1.5; color:#4b5563;
        font-family:inherit; display:inline-block; vertical-align:baseline;
    }
    .chat-send-hint i { font-size:11px; line-height:1; vertical-align:baseline; margin:0 1px; }
    .chat-send-hint span { font-size:11px; }

    .chat-empty {
        flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center;
        color:#9ca3af; padding:24px;
    }
    .chat-empty .icon { color:#e5e7eb; font-size:48px; margin-bottom:12px; }
    .chat-empty h3 { color:#374151; font-size:16px; font-weight:700; margin-bottom:4px; }
    .chat-empty p  { font-size:13px; }

    /* メンション候補 */
    .mention-pop {
        position:absolute; bottom:100%; left:0; right:0; margin-bottom:8px;
        background:#ffffff; border:1px solid #e5e7eb; border-radius:8px;
        overflow:hidden; max-height:220px; overflow-y:auto; z-index:50;
        box-shadow:0 10px 25px rgba(0,0,0,0.1);
    }
    .mention-pop .head { padding:6px 12px; font-size:10px; color:#9ca3af; text-transform:uppercase; background:#f9fafb; border-bottom:1px solid #e5e7eb; font-weight:700; }
    .mention-pop .item { padding:6px 12px; display:flex; align-items:center; gap:8px; cursor:pointer; color:#374151; }
    .mention-pop .item:hover, .mention-pop .item.active { background:#eff6ff; color:#1d4ed8; }
    .mention-pop .item .av { width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:#fff; font-size:11px; }
    .mention-pop .item .info { flex:1; min-width:0; font-size:13px; }
    .mention-pop .item .info p { margin:0; line-height:1.2; }
    .mention-pop .item .info .email { font-size:10px; color:#9ca3af; }

    /* 検索 */
    .chat-search input {
        background:#f9fafb; border:1px solid #e5e7eb; outline:none; color:#111827;
        font-size:12px; padding:6px 10px; border-radius:6px; width:100%;
    }
    .chat-search input:focus { border-color:#93c5fd; box-shadow:0 0 0 2px rgba(59,130,246,0.1); }
    .chat-search input::placeholder { color:#9ca3af; }

    /* リサイズハンドル (左サイドバー右端) */
    .chat-resize {
        position:absolute; top:0; right:0; width:3px; height:100%;
        cursor:col-resize; z-index:5; background:transparent;
    }
    .chat-resize:hover, .chat-resize:active { background:#3b82f6; }

    /* 元スレッドパネル本体 (Alpine :style="width:..." と併用するため静的スタイルはクラス化) */
    .orig-thread-panel {
        border-left: 1px solid #e5e7eb;
        background: #fafafa;
        display: flex;
        flex-direction: column;
        position: relative;
        flex-shrink: 0;
    }

    /* 元スレッドパネル リサイズハンドル (左端) — 視覚的に分かりやすく */
    .orig-resize-handle {
        position:absolute; top:0; left:-5px;
        width:10px; height:100%;
        cursor:col-resize; z-index:80;
        display:flex; align-items:center; justify-content:center;
        background:transparent; transition:background-color 0.15s;
    }
    .orig-resize-handle::before {
        content:''; position:absolute; top:0; left:4px;
        width:2px; height:100%; background:#d1d5db; transition:background-color 0.15s;
    }
    .orig-resize-handle:hover, .orig-resize-handle.is-resizing {
        background:rgba(59,130,246,0.08);
    }
    .orig-resize-handle:hover::before, .orig-resize-handle.is-resizing::before {
        background:#2563eb;
    }
    .orig-resize-grip {
        position:relative; z-index:1; width:4px; height:48px;
        border-radius:2px; background:transparent;
        display:flex; flex-direction:column; align-items:center; justify-content:space-between;
        padding:6px 0; pointer-events:none;
    }
    .orig-resize-handle:hover .orig-resize-grip,
    .orig-resize-handle.is-resizing .orig-resize-grip {
        background:#ffffff; box-shadow:0 1px 3px rgba(0,0,0,0.15);
    }
    .orig-resize-handle:hover .orig-resize-grip::before,
    .orig-resize-handle:hover .orig-resize-grip::after,
    .orig-resize-handle.is-resizing .orig-resize-grip::before,
    .orig-resize-handle.is-resizing .orig-resize-grip::after {
        content:''; width:2px; height:2px; border-radius:50%; background:#2563eb;
    }
    body.orig-resizing { cursor:col-resize !important; user-select:none !important; }

    /* ===== サイドバー: ホバー時の非表示ボタン (badge を避けるため flex 末尾に配置) ===== */
    .chat-channel .chat-hide-btn {
        margin-left:auto;
        background:transparent; border:none; color:#9ca3af; padding:2px 5px;
        border-radius:4px; cursor:pointer; opacity:0;
        transition:opacity 0.15s, background-color 0.15s, color 0.15s;
        display:inline-flex; align-items:center; justify-content:center;
        flex-shrink:0;
    }
    .chat-channel:hover .chat-hide-btn { opacity:1; }
    .chat-channel .chat-hide-btn:hover { background:#fee2e2; color:#dc2626; }
    .chat-channel.active .chat-hide-btn { color:#93c5fd; }
    .chat-channel.active .chat-hide-btn:hover { background:rgba(255,255,255,0.2); color:#fff; }
    /* ピン留めボタン (常時表示・ピン留め時は橙) */
    .chat-channel .chat-pin-btn {
        background:transparent; border:none; padding:2px 4px;
        border-radius:4px; cursor:pointer; flex-shrink:0;
        display:inline-flex; align-items:center; justify-content:center;
        transition:background-color 0.15s, color 0.15s, transform 0.1s;
    }
    .chat-channel .chat-pin-btn:hover { background:#fef3c7; transform:scale(1.15); }
    .chat-channel.active .chat-pin-btn:hover { background:rgba(255,255,255,0.2); }
    /* 自分が非表示にしている行: 薄く + 取り消し線 + 「非表示中」バッジ */
    .chat-channel.is-hidden { opacity:0.55; }
    .chat-channel.is-hidden .name { text-decoration:line-through; color:#6b7280; }
    .chat-hidden-badge {
        display:inline-flex; align-items:center; gap:3px;
        background:#fee2e2; color:#b91c1c; border:1px solid #fecaca;
        font-size:9px; font-weight:700; padding:0 5px; border-radius:9999px;
        flex-shrink:0; line-height:1.4;
    }
    .chat-channel.active .chat-hidden-badge { background:rgba(255,255,255,0.2); color:#fff; border-color:rgba(255,255,255,0.3); }

    /* ===== 全体ビュー: コンテキストバッジ (#ルーム名 / 件名) ===== */
    .msg-context-badge {
        display:inline-block; font-size:10px; font-weight:700;
        padding:1px 6px; border-radius:4px; margin-right:6px;
        background:#eff6ff; color:#1d4ed8; border:1px solid #dbeafe;
    }
    .msg-context-badge.thread { background:#f5f3ff; color:#6d28d9; border-color:#ddd6fe; }

    /* ===== ルームヘッダー: バンドル管理 ===== */
    .bundle-chip {
        display:inline-flex; align-items:center; gap:4px;
        background:#f3f4f6; color:#374151; border:1px solid #e5e7eb;
        font-size:11px; font-weight:600;
        padding:2px 8px; border-radius:999px;
    }
    .bundle-chip button {
        background:none; border:none; color:#9ca3af; padding:0; cursor:pointer; font-size:10px;
    }
    .bundle-chip button:hover { color:#dc2626; }

    .bundle-modal-overlay {
        position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:300;
        display:flex; align-items:center; justify-content:center;
    }
    .bundle-modal {
        background:#fff; border-radius:12px; width:480px; max-width:92vw;
        max-height:80vh; display:flex; flex-direction:column;
        box-shadow:0 20px 60px rgba(0,0,0,0.25);
    }
    .bundle-modal-head { padding:14px 18px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:8px; }
    .bundle-modal-body { flex:1; overflow-y:auto; padding:8px; }
    .bundle-thread-row {
        display:flex; align-items:center; gap:8px; padding:8px 10px;
        border-radius:8px; cursor:pointer; font-size:12px;
    }
    .bundle-thread-row:hover { background:#eff6ff; }
    .bundle-thread-row.is-attached { background:#dbeafe; color:#1e3a8a; }

    /* ===== 新規ルーム作成: 公開範囲セレクター ===== */
    .room-vis-opt {
        flex:1; display:flex; align-items:flex-start; gap:8px;
        padding:10px; border:2px solid #e5e7eb; border-radius:8px;
        cursor:pointer; background:#fff; transition:all 0.15s;
    }
    .room-vis-opt:hover { border-color:#bfdbfe; background:#f8fafc; }
    .room-vis-opt.is-active { border-color:#2563eb; background:#eff6ff; }
    .room-vis-opt input[type=radio] { margin-top:3px; }
</style>
@endsection

@section('content')
<script>
    document.body.classList.add('chats-page');
    window.addEventListener('beforeunload', function() { document.body.classList.remove('chats-page'); });
</script>

{{--
  チャット一覧 グローバルショートカット:
    J / K            : サイドバー (rooms + threads + 全体チャット) を順送り / 逆送り
    Esc              : 開いている入力やパネルを閉じる
    /                : 検索ボックスへフォーカス
    ?                : ヘルプモーダル
  入力欄や IME 入力中、モーダル表示中は無効化.
--}}
<div class="chats-root" x-data="chatHubApp()" x-cloak
     @keydown.window="onGlobalKey($event)">

    {{-- ===== 左サイドバー ===== --}}
    <aside class="chat-sidebar flex flex-col shrink-0 relative"
           :class="{ 'is-collapsed': sidebarCollapsed }"
           :style="'width:' + (sidebarCollapsed ? 32 : panelWidth) + 'px;'">
        {{-- 折りたたみトグル (常時表示・上部右端) --}}
        <button @click="toggleSidebar()" class="sidebar-collapse-toggle"
                :title="sidebarCollapsed ? '展開' : '折りたたむ'">
            <i class="fas" :class="sidebarCollapsed ? 'fa-angle-double-right' : 'fa-angle-double-left'"></i>
        </button>
        <div class="chat-sidebar-head" style="padding:6px 34px 6px 10px;">
            <div class="d-flex align-items-center" style="gap:6px;margin-bottom:5px;">
                <h3 class="flex-1 mb-0" style="color:#111827;font-size:12px;font-weight:700;">チャット</h3>
                <button @click="load()" :class="loading ? 'fa-spin' : ''"
                        style="color:#9ca3af;background:none;border:none;font-size:11px;"
                        title="更新"><i class="fas fa-sync-alt"></i></button>
            </div>
            <div class="chat-search">
                <input type="text" x-model="searchQuery" @input.debounce.300ms="load()" placeholder="検索...">
            </div>
            <div class="d-flex" style="gap:4px;margin-top:5px;">
                <button @click="filter = 'all'; load()"
                        :style="filter === 'all' ? 'background:#2563eb;color:#fff;' : 'background:#f3f4f6;color:#4b5563;'"
                        style="border:none;border-radius:4px;padding:3px 8px;font-size:11px;font-weight:600;flex:1;">すべて</button>
                <button @click="filter = 'mentioned'; load()"
                        :style="filter === 'mentioned' ? 'background:#2563eb;color:#fff;' : 'background:#f3f4f6;color:#4b5563;'"
                        style="border:none;border-radius:4px;padding:3px 8px;font-size:11px;font-weight:600;flex:1;">自分宛</button>
            </div>
            {{-- 非表示も表示するトグル --}}
            <div class="d-flex align-items-center" style="gap:6px;margin-top:5px;">
                <label style="font-size:11px;color:#6b7280;display:inline-flex;align-items:center;gap:4px;cursor:pointer;">
                    <input type="checkbox" x-model="showHidden" @change="load()" style="margin:0;cursor:pointer;">
                    <span>非表示も表示</span>
                </label>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto py-1" style="min-height:0;">

            {{-- 全体 (固定の特別エントリ - 全ルーム + 全スレッドのメッセージを時系列マージ表示) --}}
            <div @click="selectAll()"
                 :class="selected?.kind === 'all' ? 'chat-channel active' : 'chat-channel'"
                 style="margin-top:6px;"
                 title="全ルーム/全スレッドのメッセージを時系列で表示">
                <i class="fas fa-globe" style="font-size:11px;color:#3b82f6;"></i>
                <span class="name" style="font-weight:700;">全体チャット</span>
            </div>

            {{-- 共有ルーム (折りたたみ可) --}}
            <div class="chat-sidebar-section" style="cursor:pointer;" @click="toggleSharedRoomsCollapsed()">
                <span><i class="fas" :class="sharedRoomsCollapsed ? 'fa-chevron-right' : 'fa-chevron-down'" style="font-size:8px;margin-right:4px;"></i>共有ルーム</span>
                <button @click.stop="openCreateRoomModal()" style="background:none;border:none;color:#6b7280;font-size:11px;" title="新規ルーム"><i class="fas fa-plus"></i></button>
            </div>
            <template x-for="r in (sharedRoomsCollapsed ? [] : sharedRooms)" :key="'shared-' + r.id">
                <div @click="selectRoom(r)"
                     :class="(isRoomActive(r) ? 'chat-channel active' : 'chat-channel') + (r.is_hidden ? ' is-hidden' : '')"
                     :style="(r._depth || 0) > 0 ? 'padding-left:' + (8 + ((r._depth||0) * 12)) + 'px;' : ''">
                    <button @click.stop="togglePinChatRow('room', r)"
                            class="chat-pin-btn"
                            :title="r.is_pinned_chat ? 'ピン留めを解除' : 'ピン留め'"
                            :style="r.is_pinned_chat ? 'color:#f59e0b;' : 'color:#d1d5db;'">
                        <i class="fas fa-thumbtack" style="font-size:9px;"></i>
                    </button>
                    {{-- 子ルームを持っていれば折りたたみシェブロン --}}
                    <button x-show="r._hasChildren" @click.stop="toggleRoomBranch(r.id)"
                            class="chat-pin-btn"
                            style="color:#9ca3af;"
                            :title="roomBranchCollapsed[r.id] ? '子ルームを表示' : '子ルームを折りたたむ'">
                        <i class="fas" :class="roomBranchCollapsed[r.id] ? 'fa-chevron-right' : 'fa-chevron-down'" style="font-size:8px;"></i>
                    </button>
                    <span class="hash" x-show="!r.is_pinned_chat && !r._hasChildren">#</span>
                    <span class="name" x-text="r.name"></span>
                    <span class="chat-hidden-badge" x-show="r.is_hidden" title="自分で非表示にしているルームです">
                        <i class="fas fa-eye-slash" style="font-size:8px;"></i>非表示中
                    </span>
                    {{-- バンドル先スレッドの件数バッジ (status 別色分け: 受信=青/保留=琥珀/承認待ち=橙).
                         backend が inbox_email_count/hold/pending を返すなら 3 バッジ、無いなら合計 1 個 (互換). --}}
                    <template x-if="r.inbox_email_count !== undefined">
                        <span style="display:inline-flex;gap:3px;flex-shrink:0;">
                            <span class="badge-email-unread" x-show="r.inbox_email_count > 0"
                                  :title="'受信 ' + r.inbox_email_count + ' 件'">
                                <i class="fas fa-envelope"></i><span x-text="r.inbox_email_count"></span>
                            </span>
                            <span class="badge-email-hold" x-show="r.hold_email_count > 0"
                                  :title="'保留 ' + r.hold_email_count + ' 件'">
                                <i class="fas fa-pause"></i><span x-text="r.hold_email_count"></span>
                            </span>
                            <span class="badge-email-pending" x-show="r.pending_email_count > 0"
                                  :title="'承認待ち ' + r.pending_email_count + ' 件'">
                                <i class="fas fa-hourglass-half"></i><span x-text="r.pending_email_count"></span>
                            </span>
                        </span>
                    </template>
                    <template x-if="r.inbox_email_count === undefined">
                        <span class="badge-email-unread" x-show="r.received_email_count > 0"
                              :title="'受信スレッド ' + r.received_email_count + ' 件'">
                            <i class="fas fa-envelope"></i><span x-text="r.received_email_count"></span>
                        </span>
                    </template>
                    <span class="badge-mention" x-show="r.mention_count > 0" x-text="r.mention_count" title="未読メンション"></span>
                    <span class="badge-count" x-show="r.mention_count === 0 && r.unread_count > 0" x-text="r.unread_count" title="未読チャット"></span>
                    <button @click.stop="toggleHide('room', r.id, r.is_hidden)" class="chat-hide-btn"
                            :title="r.is_hidden ? '表示に戻す' : '非表示にする'">
                        <i class="fas" :class="r.is_hidden ? 'fa-eye' : 'fa-eye-slash'" style="font-size:10px;"></i>
                    </button>
                </div>
            </template>
            <template x-if="!sharedRoomsCollapsed && !loading && sharedRooms.length === 0">
                <p class="text-center py-2" style="color:#9ca3af;font-size:11px;" x-text="searchQuery ? '該当なし' : '共有ルームなし'"></p>
            </template>

            {{-- 個人ルーム (折りたたみ可) --}}
            <div class="chat-sidebar-section" style="cursor:pointer;" @click="togglePersonalRoomsCollapsed()">
                <span><i class="fas" :class="personalRoomsCollapsed ? 'fa-chevron-right' : 'fa-chevron-down'" style="font-size:8px;margin-right:4px;"></i>個人ルーム</span>
                <button @click.stop="openCreateRoomModal(true)" style="background:none;border:none;color:#a78bfa;font-size:11px;" title="新規個人ルーム"><i class="fas fa-plus"></i></button>
            </div>
            <template x-for="r in (personalRoomsCollapsed ? [] : personalRooms)" :key="'personal-' + r.id">
                <div @click="selectRoom(r)"
                     :class="(isRoomActive(r) ? 'chat-channel active' : 'chat-channel') + (r.is_hidden ? ' is-hidden' : '')"
                     :style="(r._depth || 0) > 0 ? 'padding-left:' + (8 + ((r._depth||0) * 12)) + 'px;' : ''">
                    <button @click.stop="togglePinChatRow('room', r)"
                            class="chat-pin-btn"
                            :title="r.is_pinned_chat ? 'ピン留めを解除' : 'ピン留め'"
                            :style="r.is_pinned_chat ? 'color:#f59e0b;' : 'color:#d1d5db;'">
                        <i class="fas fa-thumbtack" style="font-size:9px;"></i>
                    </button>
                    {{-- 子ルームを持っていれば折りたたみシェブロン --}}
                    <button x-show="r._hasChildren" @click.stop="toggleRoomBranch(r.id)"
                            class="chat-pin-btn" style="color:#9ca3af;"
                            :title="roomBranchCollapsed[r.id] ? '子ルームを表示' : '子ルームを折りたたむ'">
                        <i class="fas" :class="roomBranchCollapsed[r.id] ? 'fa-chevron-right' : 'fa-chevron-down'" style="font-size:8px;"></i>
                    </button>
                    <i class="fas fa-lock" style="font-size:9px;color:#a78bfa;" x-show="!r.is_pinned_chat && !r._hasChildren" title="個人ルーム"></i>
                    <span class="name" x-text="r.name"></span>
                    <span class="chat-hidden-badge" x-show="r.is_hidden" title="自分で非表示にしているルームです">
                        <i class="fas fa-eye-slash" style="font-size:8px;"></i>非表示中
                    </span>
                    {{-- バンドル先スレッドの件数バッジ (status 別色分け: 受信/保留/承認待ち).
                         完了スレッドはバックエンド側で集計除外済み. --}}
                    <template x-if="r.inbox_email_count !== undefined">
                        <span style="display:inline-flex;gap:3px;flex-shrink:0;">
                            <span class="badge-email-unread" x-show="r.inbox_email_count > 0"
                                  :title="'受信 ' + r.inbox_email_count + ' 件'">
                                <i class="fas fa-envelope"></i><span x-text="r.inbox_email_count"></span>
                            </span>
                            <span class="badge-email-hold" x-show="r.hold_email_count > 0"
                                  :title="'保留 ' + r.hold_email_count + ' 件'">
                                <i class="fas fa-pause"></i><span x-text="r.hold_email_count"></span>
                            </span>
                            <span class="badge-email-pending" x-show="r.pending_email_count > 0"
                                  :title="'承認待ち ' + r.pending_email_count + ' 件'">
                                <i class="fas fa-hourglass-half"></i><span x-text="r.pending_email_count"></span>
                            </span>
                        </span>
                    </template>
                    <template x-if="r.inbox_email_count === undefined">
                        <span class="badge-email-unread" x-show="r.received_email_count > 0"
                              :title="'受信スレッド ' + r.received_email_count + ' 件'">
                            <i class="fas fa-envelope"></i><span x-text="r.received_email_count"></span>
                        </span>
                    </template>
                    <span class="badge-mention" x-show="r.mention_count > 0" x-text="r.mention_count" title="未読メンション"></span>
                    <span class="badge-count" x-show="r.mention_count === 0 && r.unread_count > 0" x-text="r.unread_count" title="未読"></span>
                    <button @click.stop="toggleHide('room', r.id, r.is_hidden)" class="chat-hide-btn"
                            :title="r.is_hidden ? '表示に戻す' : '非表示にする'">
                        <i class="fas" :class="r.is_hidden ? 'fa-eye' : 'fa-eye-slash'" style="font-size:10px;"></i>
                    </button>
                </div>
            </template>
            <template x-if="!personalRoomsCollapsed && !loading && personalRooms.length === 0">
                <p class="text-center py-2" style="color:#9ca3af;font-size:11px;" x-text="searchQuery ? '該当なし' : '個人ルームなし'"></p>
            </template>

            {{-- スレッド (折りたたみ可・縦スクロール) + 「ルーム未設定」フィルタ切替 --}}
            <div class="chat-sidebar-section" style="cursor:pointer;display:flex;align-items:center;gap:4px;" @click="toggleThreadsCollapsed()">
                <span><i class="fas" :class="threadsCollapsed ? 'fa-chevron-right' : 'fa-chevron-down'" style="font-size:8px;margin-right:4px;"></i>スレッド</span>
                <span style="color:#9ca3af;font-size:10px;font-weight:600;flex:1;" x-text="visibleThreadsForSidebar.length"></span>
                {{-- 「ルーム未設定」フィルタトグル。
                     ON にすると、どのルームにも参加していないスレッドだけサイドバーに残る
                     (= 整理されていないスレッドの掘り起こし用)。 --}}
                <button type="button" @click.stop="toggleOnlyUnroomed()"
                        :class="onlyUnroomedThreads ? 'is-on' : ''"
                        :style="'border:none;padding:1px 6px;border-radius:4px;font-size:9px;font-weight:700;cursor:pointer;'
                                + (onlyUnroomedThreads ? 'background:#f59e0b;color:#fff;' : 'background:transparent;color:#9ca3af;')"
                        :title="onlyUnroomedThreads ? '未設定フィルタを解除' : 'どのルームにも未登録のスレッドだけ表示'">
                    <i class="fas fa-folder-minus" style="margin-right:2px;"></i>未設定
                </button>
            </div>
            <template x-if="!threadsCollapsed && loading">
                <div class="text-center py-3" style="color:#3b82f6;"><i class="fas fa-circle-notch fa-spin"></i></div>
            </template>
            <template x-if="!threadsCollapsed">
                <div style="max-height:340px;overflow-y:auto;"
                     id="chat-sidebar-threads-scroll">
                <template x-for="t in visibleThreadsForSidebar" :key="'thread-' + t.id">
                <div @click="selectThread(t)"
                     :class="(isThreadActive(t) ? 'chat-channel active' : 'chat-channel') + (t.is_hidden ? ' is-hidden' : '')">
                    {{-- ピン留めトグル (クリックでオン/オフ) --}}
                    <button @click.stop="togglePinChatRow('thread', t)"
                            class="chat-pin-btn"
                            :title="t.is_pinned_chat ? 'ピン留めを解除' : 'ピン留め'"
                            :style="t.is_pinned_chat ? 'color:#f59e0b;' : 'color:#d1d5db;'">
                        <i class="fas fa-thumbtack" style="font-size:9px;"></i>
                    </button>
                    <i class="fas fa-envelope" style="font-size:10px;opacity:0.7;" x-show="!t.is_pinned_chat"></i>
                    <span class="name" x-text="t.subject"></span>
                    <span class="chat-hidden-badge" x-show="t.is_hidden" title="自分で非表示にしているスレッドです (新着で自動再表示)">
                        <i class="fas fa-eye-slash" style="font-size:8px;"></i>非表示中
                    </span>
                    <span class="badge-mention" x-show="t.mention_count > 0" x-text="t.mention_count" title="未読メンション"></span>
                    <span class="badge-count" x-show="t.mention_count === 0 && t.unread_count > 0" x-text="t.unread_count" title="未読"></span>
                    {{-- ルームに追加 (ホバー時) --}}
                    <button @click.stop="openChatAddToRoom(t.id)" class="chat-hide-btn"
                            title="ルームに追加">
                        <i class="fas fa-link" style="font-size:10px;"></i>
                    </button>
                    <button @click.stop="toggleHide('thread', t.id, t.is_hidden)" class="chat-hide-btn"
                            :title="t.is_hidden ? '表示に戻す' : '非表示にする'">
                        <i class="fas" :class="t.is_hidden ? 'fa-eye' : 'fa-eye-slash'" style="font-size:10px;"></i>
                    </button>
                </div>
            </template>
                </div>
            </template>
            <template x-if="!threadsCollapsed && !loading && visibleThreadsForSidebar.length === 0">
                <p class="text-center py-2" style="color:#9ca3af;font-size:11px;" x-text="searchQuery ? '該当なし' : 'スレッドなし'"></p>
            </template>
        </div>

        <div class="chat-resize" @mousedown.prevent="startResize($event)"></div>
    </aside>

    {{-- ===== 右メインエリア ===== --}}
    <main class="chat-main">

        {{-- 未選択 --}}
        <div x-show="!selected" class="chat-empty">
            <i class="fas fa-comments icon"></i>
            <h3>チャットを選択してください</h3>
            <p>左のリストからルームまたはスレッドを選びます</p>
        </div>

        {{-- 選択中 --}}
        <template x-if="selected">
            <div class="flex flex-col h-full" style="height:100%;">
                {{-- ヘッダ --}}
                <div class="chat-header">
                    <span class="hash" x-show="selected.kind === 'room' && !selected.is_private">#</span>
                    <i class="fas fa-lock" x-show="selected.kind === 'room' && selected.is_private" style="color:#a78bfa;" title="個人ルーム"></i>
                    <i class="fas fa-envelope" x-show="selected.kind === 'thread'" style="color:#9ca3af;"></i>
                    <i class="fas fa-globe" x-show="selected.kind === 'all'" style="color:#9ca3af;"></i>
                    <h2 x-text="selected.kind === 'all' ? '全体チャット' : (selected.kind === 'room' ? selected.name : selected.subject)"></h2>

                    {{-- メッセージ簡易検索 (現在のチャット内をフィルタ) --}}
                    <div style="display:flex;align-items:center;gap:4px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:6px;padding:2px 8px;max-width:220px;">
                        <i class="fas fa-search" style="color:#9ca3af;font-size:10px;"></i>
                        <input type="text" x-model="messageSearchQuery"
                               placeholder="メッセージ内を検索..."
                               style="background:transparent;border:none;outline:none;font-size:11px;color:#374151;width:140px;">
                        <button x-show="messageSearchQuery" @click="messageSearchQuery = ''" style="background:none;border:none;color:#9ca3af;padding:0;font-size:10px;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    {{-- ピン留めトグル (全体には不要) --}}
                    <button x-show="selected.kind !== 'all'" @click="togglePinSelected()"
                            style="background:none;border:none;padding:4px 8px;border-radius:4px;font-size:13px;"
                            :style="selected.is_pinned_chat ? 'color:#f59e0b;' : 'color:#9ca3af;'"
                            onmouseover="this.style.backgroundColor='#f3f4f6'"
                            onmouseout="this.style.backgroundColor='transparent'"
                            :title="selected.is_pinned_chat ? 'ピン留めを解除' : 'ピン留め'">
                        <i class="fas fa-thumbtack"></i>
                    </button>
                    {{-- スレッド: 元スレッド (全メール) サイドパネルトグル --}}
                    <button x-show="selected.kind === 'thread'" @click="toggleOrigThread()"
                            style="background:#2563eb;color:#fff;border:none;font-size:11px;padding:4px 10px;border-radius:6px;font-weight:600;"
                            title="このスレッドの関連メールをサイドパネルで表示">
                        <i class="fas fa-list-ul"></i> 関連メール
                    </button>
                    {{-- ルーム: スレッドをまとめる (バンドル管理) --}}
                    <button x-show="selected.kind === 'room'" @click="openBundleModal()"
                            style="background:#10b981;color:#fff;border:none;font-size:11px;padding:4px 10px;border-radius:6px;font-weight:600;"
                            title="このルームにメールスレッドを追加する">
                        <i class="fas fa-link"></i> スレッドを追加<span x-show="bundledThreads.length > 0" x-text="' (' + bundledThreads.length + ')'"></span>
                    </button>
                    {{-- ルーム: このチャットのメール (バンドルされたスレッドのメール一覧) --}}
                    <button x-show="selected.kind === 'room' && bundledThreads.length > 0"
                            @click="openRoomEmailsPanel()"
                            style="background:#7c3aed;color:#fff;border:none;font-size:11px;padding:4px 10px;border-radius:6px;font-weight:600;"
                            title="このチャットに紐付くメールをサイドパネルで表示">
                        <i class="fas fa-envelope-open-text"></i> このチャットのメール
                    </button>
                    {{-- ルームレポート/メモ起動ボタンはユーザ要望で削除済 (パネル自体は別経路から開ける場合のために残置). --}}
                    {{-- 横断ナビ / Wiki は画面右上 (プロフィール横) のグローバル navbar に集約済み --}}
                    {{-- ルーム編集 (名前 / 公開範囲)。個人ルームは作成者のみ表示。共有ルームは閲覧者なら誰でも可 --}}
                    <button x-show="selected.kind === 'room' && (!selected.is_private || selected.created_by_user_id === myId)"
                            @click="openEditRoomModal()" title="ルーム編集"
                            style="color:#9ca3af;background:none;border:none;padding:4px 8px;border-radius:4px;font-size:11px;"
                            onmouseover="this.style.color='#2563eb';this.style.backgroundColor='#eff6ff'"
                            onmouseout="this.style.color='#9ca3af';this.style.backgroundColor='transparent'">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button x-show="selected.kind === 'room'" @click="deleteRoom()" title="ルーム削除"
                            style="color:#9ca3af;background:none;border:none;padding:4px 8px;border-radius:4px;font-size:11px;"
                            onmouseover="this.style.color='#dc2626';this.style.backgroundColor='#fef2f2'"
                            onmouseout="this.style.color='#9ca3af';this.style.backgroundColor='transparent'">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>

                {{-- 紐付けされたスレッド (バンドル) のチップ表示。
                     折りたたみ時はラベル＋件数 + 展開ボタンのみ。展開時は全チップ表示。 --}}
                <div x-show="selected.kind === 'room' && bundledThreads.length > 0"
                     class="bundle-band"
                     style="padding:6px 16px;background:#f9fafb;border-bottom:1px solid #e5e7eb;display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                    <span style="font-size:10px;color:#6b7280;font-weight:700;text-transform:uppercase;">
                        束ねたスレッド
                        <span style="color:#9ca3af;font-weight:600;text-transform:none;margin-left:2px;"
                              x-text="'(' + bundledThreads.length + ')'"></span>
                        :
                    </span>
                    <template x-for="bt in visibleBundleChips" :key="bt.id">
                        <span class="bundle-chip">
                            <i class="fas fa-envelope" style="font-size:9px;color:#9ca3af;"></i>
                            <span x-text="bt.subject"></span>
                            <button @click="detachThread(bt.id)" title="紐付けを外す"><i class="fas fa-times"></i></button>
                        </span>
                    </template>
                    {{-- 展開 / たたむ トグル --}}
                    <button type="button"
                            @click="toggleBundleBandExpanded()"
                            style="background:#ffffff;border:1px dashed #cbd5e1;color:#475569;font-size:10px;font-weight:700;padding:3px 10px;border-radius:999px;cursor:pointer;display:inline-flex;align-items:center;gap:4px;"
                            onmouseover="this.style.backgroundColor='#eff6ff';this.style.borderColor='#60a5fa';this.style.color='#1d4ed8';"
                            onmouseout="this.style.backgroundColor='#ffffff';this.style.borderColor='#cbd5e1';this.style.color='#475569';"
                            :title="bundleBandExpanded ? '束ねたスレッド一覧を隠す' : ('束ねたスレッド ' + bundledThreads.length + ' 件を展開')">
                        <i class="fas" :class="bundleBandExpanded ? 'fa-chevron-up' : 'fa-chevron-down'" style="font-size:8px;"></i>
                        <span x-show="!bundleBandExpanded">展開</span>
                        <span x-show="bundleBandExpanded">たたむ</span>
                    </button>
                </div>

                {{-- スレッド選択時: このスレッドが束ねられている (= 参加している) ルームを表示 --}}
                <div x-show="selected.kind === 'thread' && threadParentRooms.length > 0"
                     style="padding:6px 16px;background:#f0f9ff;border-bottom:1px solid #e0f2fe;display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                    <span style="font-size:10px;color:#0369a1;font-weight:700;text-transform:uppercase;">参加中のルーム:</span>
                    <template x-for="pr in threadParentRooms" :key="'parent-room-' + pr.id">
                        <button type="button" @click="selectRoom(pr)" class="bundle-chip"
                                style="background:#ffffff;border:1px solid #bae6fd;color:#0369a1;cursor:pointer;"
                                onmouseover="this.style.backgroundColor='#e0f2fe';"
                                onmouseout="this.style.backgroundColor='#ffffff';"
                                title="このルームに移動">
                            <i class="fas fa-lock" x-show="pr.is_private" style="font-size:9px;color:#a78bfa;"></i>
                            <i class="fas fa-hashtag" x-show="!pr.is_private" style="font-size:9px;color:#9ca3af;"></i>
                            <span x-text="pr.name"></span>
                        </button>
                    </template>
                </div>
                <div x-show="selected.kind === 'thread' && threadParentRooms.length === 0"
                     style="padding:4px 16px;background:#fafafa;border-bottom:1px solid #f3f4f6;font-size:10px;color:#9ca3af;font-weight:600;">
                    <i class="fas fa-info-circle" style="margin-right:4px;"></i>このスレッドはまだどのルームにも参加していません
                </div>

                {{-- メッセージ --}}
                <div class="chat-messages" x-ref="msgList" id="chat-hub-messages" @scroll.passive="onMessagesScroll($event)">
                    <template x-if="chatLoading">
                        <div class="text-center py-3" style="color:#3b82f6;"><i class="fas fa-circle-notch fa-spin fa-lg"></i></div>
                    </template>
                    <template x-if="!chatLoading && comments.length === 0">
                        <div class="chat-empty" style="flex:1;">
                            <i class="fas fa-hashtag icon"></i>
                            <h3 x-text="(selected.kind === 'room' ? '#' : '') + (selected.kind === 'room' ? selected.name : selected.subject)"></h3>
                            <p>最初のメッセージで会話を始めましょう。</p>
                        </div>
                    </template>

                    <template x-for="(m, idx) in comments" :key="m.id">
                        <div x-show="messageMatches(m)">
                            <template x-if="shouldShowDate(idx) && messageMatches(m)">
                                <div class="date-divider"><span x-text="dateLabel(m.created_at)"></span></div>
                            </template>
                            <div :class="(isCompact(idx) ? 'msg-row compact' : 'msg-row') + ' ' + msgGroupBg(idx) + (isMentionedToMe(m.content) ? ' is-mentioned-me' : '')" :id="'comment-' + m.id">
                                <template x-if="!isCompact(idx)">
                                    <div class="avatar" :style="'background-color:' + avatarColor(m.user_id)" x-text="(m.author||'?').charAt(0).toUpperCase()"></div>
                                </template>
                                <template x-if="isCompact(idx)">
                                    <span class="floating-ts" x-text="(m.created_at || '').substring(11)"></span>
                                </template>
                                <template x-if="!isCompact(idx)">
                                    <div class="ts-header">
                                        <span class="author" x-text="m.author"></span>
                                        <span class="ts" x-text="m.created_at"></span>
                                        <template x-if="isMentionedToMe(m.content)">
                                            <span class="mention-self">@あなた宛</span>
                                        </template>
                                    </div>
                                </template>
                                <div class="body" x-show="m.content || m.context_label">
                                    {{-- 全体ビュー時のコンテキストバッジ --}}
                                    <template x-if="selected.kind === 'all' && m.context_label">
                                        <a :href="m.context_kind === 'room' ? ('#room-' + m.context_id) : ('#thread-' + m.context_id + '&comment=' + m.id)"
                                           @click.prevent="jumpToContext(m)"
                                           :class="m.context_kind === 'thread' ? 'msg-context-badge thread' : 'msg-context-badge'"
                                           x-text="m.context_label"
                                           :title="m.context_kind === 'room' ? 'ルームへ移動' : 'スレッドチャットへ移動'"></a>
                                    </template>
                                    <span x-html="renderMentions(m.content)"></span>
                                </div>
                                {{-- 添付ファイル --}}
                                <div x-show="(m.attachments || []).length > 0" class="chat-attachments">
                                    <template x-for="a in (m.attachments || [])" :key="a.id">
                                        <div>
                                            <template x-if="a.is_image">
                                                <a :href="a.url" :title="a.filename" target="_blank">
                                                    <img :src="a.inline_url" :alt="a.filename" class="chat-att-image">
                                                </a>
                                            </template>
                                            <template x-if="!a.is_image">
                                                <a :href="a.url" class="chat-att-file" :title="a.filename">
                                                    <i class="fas fa-paperclip"></i>
                                                    <span class="name" x-text="a.filename"></span>
                                                    <span class="size" x-text="formatBytes(a.size)"></span>
                                                </a>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                                {{-- リアクションピル --}}
                                <div x-show="(m.reactions || []).length > 0" class="reactions-row">
                                    <template x-for="r in (m.reactions || [])" :key="r.emoji">
                                        <button type="button" @click="toggleReaction(m, r.emoji)"
                                                :class="r.me ? 'reaction-pill reaction-mine' : 'reaction-pill'">
                                            <span class="emoji" x-text="r.emoji"></span>
                                            <span class="count" x-text="r.count"></span>
                                        </button>
                                    </template>
                                </div>
                                {{-- 関連リソースへのインラインボタン群 (元メール / 元スレッドチャット) --}}
                                <template x-if="m.email_id || m.thread_id || (selected.kind === 'all' && m.context_kind === 'thread' && m.context_id)">
                                    <div style="margin-top:4px;display:flex;gap:6px;flex-wrap:wrap;">
                                        <button x-show="m.email_id" type="button" @click="openOrigEmailById(m.email_id, m.thread_id || (m.context_kind === 'thread' ? m.context_id : null))"
                                                style="display:inline-flex;align-items:center;gap:4px;background:#ede9fe;color:#5b21b6;border:1px solid #ddd6fe;font-size:11px;font-weight:600;padding:2px 8px;border-radius:6px;cursor:pointer;"
                                                onmouseover="this.style.backgroundColor='#5b21b6';this.style.color='#ffffff';"
                                                onmouseout="this.style.backgroundColor='#ede9fe';this.style.color='#5b21b6';"
                                                title="このチャットに紐付くメールをサイドパネルで開く">
                                            <i class="fas fa-envelope-open-text" style="font-size:9px;"></i>
                                            関連メール
                                        </button>
                                        <button x-show="(selected.kind === 'room' && m.thread_id) || (selected.kind === 'all' && m.context_kind === 'thread' && m.context_id)"
                                                type="button"
                                                @click="openThreadChatPreview(m.thread_id || m.context_id, m.id)"
                                                style="display:inline-flex;align-items:center;gap:4px;background:#e0f2fe;color:#075985;border:1px solid #bae6fd;font-size:11px;font-weight:600;padding:2px 8px;border-radius:6px;cursor:pointer;"
                                                onmouseover="this.style.backgroundColor='#075985';this.style.color='#ffffff';"
                                                onmouseout="this.style.backgroundColor='#e0f2fe';this.style.color='#075985';"
                                                title="このコメントの元になっているチャットをサイドパネルでプレビュー">
                                            <i class="fas fa-comments" style="font-size:9px;"></i>
                                            関連チャット
                                        </button>
                                    </div>
                                </template>
                                {{-- ホバーアクション群 (返信・引用・リアクション・削除) --}}
                                <div class="msg-actions">
                                    <button class="msg-action-btn" @click.stop="openReactionPicker(m, $event)" title="リアクション">
                                        <i class="far fa-smile"></i>
                                    </button>
                                    <button class="msg-action-btn" @click="replyTo(m)" title="返信">
                                        <i class="fas fa-reply"></i>
                                    </button>
                                    <button class="msg-action-btn" @click="quoteMessage(m)" title="引用">
                                        <i class="fas fa-quote-right"></i>
                                    </button>
                                    <template x-if="m.is_author">
                                        <button class="msg-action-btn msg-action-del" @click="deleteMessage(m.id)" title="削除"><i class="fas fa-trash"></i></button>
                                    </template>
                                </div>
                            </div>
                            {{-- ルームで、いずれのスレッドにも紐付かないコメントの直下に注意書き --}}
                            <template x-if="selected.kind === 'room' && !m.thread_id">
                                <div style="margin:2px 10px 6px 82px;padding:4px 8px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;color:#075985;font-size:10px;display:flex;align-items:flex-start;gap:5px;line-height:1.3;">
                                    <i class="fas fa-info-circle" style="color:#0284c7;flex-shrink:0;margin-top:1px;font-size:9px;"></i>
                                    <span>
                                        このメッセージは
                                        <strong x-text="(selected.is_private ? '🔒 ' : '#') + (selected.name || '')"></strong>
                                        内のみに記録され、メールスレッドや他のチャットには表示されません<span x-show="selected.is_private">。あなただけが閲覧できます</span>。
                                    </span>
                                </div>
                            </template>
                        </div>
                    </template>

                </div>

                {{-- 全体表示時の注意 --}}
                <template x-if="selected.kind === 'all'">
                    <div style="padding:10px 16px;background:#fffbeb;border-top:1px solid #fde68a;font-size:11px;color:#92400e;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-info-circle"></i>
                        全体ビューでは新規投稿はできません。各メッセージの「返信」を押すと、元のチャットへ移動して返信できます。
                    </div>
                </template>

                {{-- 入力欄 (全体ビューでは非表示) --}}
                <div x-show="selected.kind !== 'all'" class="chat-input-wrap relative">
                    {{-- 最新へスクロールするボタン --}}
                    <button x-show="scrolledUp" x-cloak
                            @click="scrollToBottom(); scrolledUp = false"
                            title="最新メッセージへ"
                            style="position:absolute;right:18px;bottom:calc(100% + 6px);z-index:30;background:#2563eb;color:#fff;border:none;border-radius:999px;width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(37,99,235,0.4);cursor:pointer;"
                            onmouseover="this.style.backgroundColor='#1d4ed8'"
                            onmouseout="this.style.backgroundColor='#2563eb'">
                        <i class="fas fa-arrow-down"></i>
                    </button>
                    {{-- 返信中バナー --}}
                    <template x-if="replyingTo">
                        <div class="reply-banner">
                            <i class="fas fa-reply" style="color:#3b82f6;"></i>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-bold" style="color:#1e40af;margin:0;">
                                    <span x-text="replyingTo.author"></span> へ返信中
                                </p>
                                <p class="text-[11px] truncate" style="color:#475569;margin:0;" x-text="replyingTo.content"></p>
                            </div>
                            <button @click="cancelReply()" style="color:#64748b;background:none;border:none;padding:4px;" title="返信をキャンセル"><i class="fas fa-times"></i></button>
                        </div>
                    </template>

                    {{-- 絵文字ピッカー --}}
                    <div x-show="emojiOpen" x-cloak @click.outside="emojiOpen = false" class="emoji-pop">
                        <div class="emoji-pop-head">絵文字</div>
                        <div class="emoji-grid">
                            <template x-for="e in emojis" :key="e">
                                <button type="button" class="emoji-btn" @click="insertEmoji(e)" x-text="e"></button>
                            </template>
                        </div>
                    </div>

                    <div x-show="mentionOpen && mentionMatches.length > 0" class="mention-pop" x-cloak>
                        <div class="head">@メンション (↑↓ Enter Esc)</div>
                        <template x-for="(u, i) in mentionMatches" :key="u.id">
                            <div :class="i === mentionIndex ? 'item active' : 'item'"
                                 @click.stop="pickMention(u)" @mouseenter="mentionIndex = i">
                                <div class="av" :style="'background-color:' + avatarColor(u.id)" x-text="(u.name||'?').charAt(0)"></div>
                                <div class="info">
                                    <p x-text="u.name"></p>
                                    <p class="email" x-text="u.email"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- 選択中の添付ファイル --}}
                    <div x-show="pendingFiles.length > 0" class="chat-pending-files">
                        <template x-for="(f, i) in pendingFiles" :key="i">
                            <span class="chat-pending-file">
                                <i class="fas fa-paperclip"></i>
                                <span x-text="f.name"></span>
                                <span style="color:#6b7280;" x-text="'(' + formatBytes(f.size) + ')'"></span>
                                <button type="button" @click="removePendingFile(i)" title="除外"><i class="fas fa-times"></i></button>
                            </span>
                        </template>
                    </div>

                    <div class="chat-input-box">
                        <textarea x-ref="ta" x-model="input" rows="1" maxlength="5000"
                                  @input="onChatInput($event); autoresize($event)"
                                  @keydown="onKeydown($event)"
                                  :placeholder="(selected.kind === 'room' ? '#' : '') + (selected.kind === 'room' ? selected.name : (selected.subject || 'チャット')) + ' へメッセージを送信'"></textarea>
                        {{-- ファイル添付ボタン --}}
                        <input type="file" x-ref="fileInput" multiple style="display:none;" @change="onFilesPicked($event)">
                        <button type="button" @click="$refs.fileInput.click()"
                                style="background:none;border:none;color:#6b7280;"
                                onmouseover="this.style.backgroundColor='#f3f4f6';this.style.color='#2563eb';"
                                onmouseout="this.style.backgroundColor='transparent';this.style.color='#6b7280';"
                                title="ファイルを添付">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        {{-- 絵文字ボタン --}}
                        <button type="button" @click.stop="emojiOpen = !emojiOpen"
                                style="background:none;border:none;color:#6b7280;"
                                onmouseover="this.style.backgroundColor='#f3f4f6';this.style.color='#3b82f6';"
                                onmouseout="this.style.backgroundColor='transparent';this.style.color='#6b7280';"
                                title="絵文字 (Ctrl+;)">
                            <i class="far fa-smile"></i>
                        </button>
                        <button @click="send()" :disabled="(!input?.trim() && pendingFiles.length === 0) || sending"
                                style="background:none;border:none;color:#2563eb;"
                                onmouseover="if(!this.disabled){this.style.backgroundColor='#eff6ff';this.style.color='#1d4ed8';}"
                                onmouseout="this.style.backgroundColor='transparent';this.style.color='#2563eb';"
                                title="送信 (Ctrl+Enter)"
                                class="disabled:opacity-30">
                            <i class="fas" :class="sending ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                        </button>
                    </div>
                    <div class="chat-send-hint">
                        <kbd>Ctrl</kbd>+<kbd>Enter</kbd> 送信 / <kbd>Enter</kbd> 改行 / <span style="color:#1e40af;font-weight:600;">@名前</span> でメンション / <kbd>Ctrl</kbd>+<kbd>;</kbd> 絵文字 / <i class="fas fa-paperclip"></i> 添付 (最大10ファイル, 各10MB)
                    </div>
                </div>
            </div>
        </template>
    </main>

    {{-- ルーム レポート サイドパネル (共有/個人どちらも利用可)。
         個人ルームでは作成者本人だけが閲覧/編集できる「自分専用メモ」として動作する。 --}}
    <aside x-show="roomReportPanelOpen" x-cloak
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="translate-x-8 opacity-0"
           x-transition:enter-end="translate-x-0 opacity-100"
           class="orig-thread-panel"
           :style="'width:' + roomDocPanelWidth + 'px;'">
        <div @mousedown.prevent="startResizeRoomDoc($event)"
             @dblclick="roomDocPanelWidth = 500; localStorage.setItem('chatRoomDocPanelWidth','500')"
             title="ドラッグで幅変更"
             class="orig-resize-handle"
             :class="{ 'is-resizing': roomDocResizing }">
            <div class="orig-resize-grip"></div>
        </div>
        <div style="background:#ffffff;border-bottom:1px solid #e5e7eb;padding:10px 14px;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-chart-bar" style="color:#f59e0b;"></i>
            <div style="flex:1;min-width:0;">
                {{-- 個人ルームの場合は「(自分専用)」、共有なら「(共有)」を表示して扱いを明示 --}}
                <p style="margin:0;font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;"
                   x-text="'レポート ' + (selected?.is_private ? '(自分専用)' : '(共有)')"></p>
                <p style="margin:0;font-size:12px;font-weight:700;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    x-text="selected?.name || ''"></p>
            </div>
            <span x-show="roomReportUpdatedAt" style="font-size:10px;color:#9ca3af;" x-text="'最終更新: ' + roomReportUpdatedAt"></span>
            <button @click="roomReportPanelOpen = false" style="background:none;border:none;color:#6b7280;padding:4px;" title="閉じる"><i class="fas fa-times"></i></button>
        </div>
        <div class="flex-1" style="display:flex;flex-direction:column;padding:10px 12px;background:#fffbeb;min-height:0;">
            <template x-if="roomReportLoading">
                <p class="text-center py-4" style="color:#9ca3af;font-size:12px;"><i class="fas fa-circle-notch fa-spin mr-1"></i>読み込み中...</p>
            </template>
            <template x-if="!roomReportLoading">
                <textarea x-model="roomReportContent" placeholder="ルームのメモを記録..."
                          style="flex:1;width:100%;min-height:300px;background:#ffffff;border:1px solid #fde68a;border-radius:8px;padding:10px 12px;font-size:13px;line-height:1.6;color:#1f2937;outline:none;resize:none;font-family:inherit;"></textarea>
            </template>
            <div style="margin-top:8px;display:flex;align-items:center;justify-content:flex-end;gap:8px;">
                <span x-show="roomReportSaved" style="font-size:11px;color:#059669;"><i class="fas fa-check-circle"></i> 保存しました</span>
                {{-- 注意: Alpine の :style 文字列バインディングは静的 style 属性を上書きする。
                     背景色も :style 側にまとめる (通常状態で背景が消えてボタンが見えなくなるのを防ぐ)。 --}}
                <button @click="saveRoomReport()" :disabled="roomReportSaving || roomReportLoading"
                        :style="'background:#f59e0b;color:#fff;border:none;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;' + ((roomReportSaving || roomReportLoading) ? 'opacity:0.5;cursor:not-allowed;' : '')">
                    <i class="fas" :class="roomReportSaving ? 'fa-spinner fa-spin' : 'fa-save'"></i> 保存
                </button>
            </div>
        </div>
    </aside>

    {{-- 共有ルーム: Wiki サイドパネル --}}
    <aside x-show="roomWikiPanelOpen" x-cloak
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="translate-x-8 opacity-0"
           x-transition:enter-end="translate-x-0 opacity-100"
           class="orig-thread-panel"
           :style="'width:' + roomDocPanelWidth + 'px;'">
        <div @mousedown.prevent="startResizeRoomDoc($event)"
             @dblclick="roomDocPanelWidth = 500; localStorage.setItem('chatRoomDocPanelWidth','500')"
             title="ドラッグで幅変更"
             class="orig-resize-handle"
             :class="{ 'is-resizing': roomDocResizing }">
            <div class="orig-resize-grip"></div>
        </div>
        <div style="background:#ffffff;border-bottom:1px solid #e5e7eb;padding:10px 14px;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-book" style="color:#0ea5e9;"></i>
            <div style="flex:1;min-width:0;">
                <p style="margin:0;font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;">Wiki (共有)</p>
                <p style="margin:0;font-size:12px;font-weight:700;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    x-text="selected?.name || ''"></p>
            </div>
            <span x-show="roomWikiUpdatedAt" style="font-size:10px;color:#9ca3af;" x-text="'最終更新: ' + roomWikiUpdatedAt"></span>
            <button @click="roomWikiPanelOpen = false" style="background:none;border:none;color:#6b7280;padding:4px;" title="閉じる"><i class="fas fa-times"></i></button>
        </div>
        <div class="flex-1" style="display:flex;flex-direction:column;padding:10px 12px;background:#f0f9ff;min-height:0;">
            <template x-if="roomWikiLoading">
                <p class="text-center py-4" style="color:#9ca3af;font-size:12px;"><i class="fas fa-circle-notch fa-spin mr-1"></i>読み込み中...</p>
            </template>
            <template x-if="!roomWikiLoading">
                <textarea x-model="roomWikiContent" placeholder="手順・ナレッジ・FAQ などを記録..."
                          style="flex:1;width:100%;min-height:300px;background:#ffffff;border:1px solid #bae6fd;border-radius:8px;padding:10px 12px;font-size:13px;line-height:1.6;color:#1f2937;outline:none;resize:none;font-family:inherit;"></textarea>
            </template>
            <div style="margin-top:8px;display:flex;align-items:center;justify-content:flex-end;gap:8px;">
                <span x-show="roomWikiSaved" style="font-size:11px;color:#059669;"><i class="fas fa-check-circle"></i> 保存しました</span>
                {{-- 同上: 背景色も :style 側に統合して static style の上書き問題を回避 --}}
                <button @click="saveRoomWiki()" :disabled="roomWikiSaving || roomWikiLoading"
                        :style="'background:#0ea5e9;color:#fff;border:none;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;' + ((roomWikiSaving || roomWikiLoading) ? 'opacity:0.5;cursor:not-allowed;' : '')">
                    <i class="fas" :class="roomWikiSaving ? 'fa-spinner fa-spin' : 'fa-save'"></i> 保存
                </button>
            </div>
        </div>
    </aside>

    {{-- 「このチャットのメール」サイドパネル (ルームに束ねられたスレッドのメール一覧) --}}
    <aside x-show="roomEmailsPanelOpen" x-cloak
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="translate-x-8 opacity-0"
           x-transition:enter-end="translate-x-0 opacity-100"
           class="orig-thread-panel"
           :style="'width:' + roomEmailsPanelWidth + 'px;'">
        <div @mousedown.prevent="startResizeRoomEmails($event)"
             @dblclick="roomEmailsPanelWidth = 460; localStorage.setItem('chatRoomEmailsPanelWidth','460')"
             title="ドラッグで幅変更"
             class="orig-resize-handle"
             :class="{ 'is-resizing': roomEmailsResizing }">
            <div class="orig-resize-grip"></div>
        </div>
        <div style="background:#ffffff;border-bottom:1px solid #e5e7eb;padding:10px 14px;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-envelope-open-text" style="color:#7c3aed;"></i>
            <div style="flex:1;min-width:0;">
                <p style="margin:0;font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;">このチャットのメール</p>
                <p style="margin:0;font-size:12px;font-weight:700;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    x-text="(selected && selected.kind === 'room' ? selected.name : '') + ' (' + roomEmails.length + '件)'"></p>
            </div>
            <button @click="roomEmailsPanelOpen = false" style="background:none;border:none;color:#6b7280;padding:4px;" title="閉じる"><i class="fas fa-times"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto" style="padding:8px 12px;background:#f9fafb;">
            <template x-if="roomEmailsLoading">
                <p class="text-center py-4" style="color:#9ca3af;font-size:12px;"><i class="fas fa-circle-notch fa-spin mr-1"></i>読み込み中...</p>
            </template>
            <template x-if="!roomEmailsLoading && roomEmails.length === 0">
                <div class="text-center py-6" style="color:#9ca3af;font-size:12px;">
                    <i class="fas fa-inbox fa-2x mb-2" style="opacity:0.3;"></i>
                    <p style="margin:0;">束ねられたスレッドにメールがありません</p>
                </div>
            </template>
            <template x-for="e in roomEmails" :key="e.id">
                <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:8px 10px;margin-bottom:6px;">
                    <p style="margin:0 0 2px;font-weight:700;color:#111827;font-size:12px;" x-text="e.subject || '(件名なし)'"></p>
                    <p style="margin:0;font-size:10px;color:#6b7280;line-height:1.4;">
                        <strong style="color:#9ca3af;">From:</strong> <span x-text="e.from_label || e.from_address || ''"></span>
                        <span x-show="e.received_at" style="color:#9ca3af;margin-left:6px;" x-text="e.received_at"></span>
                    </p>
                    <p x-show="e.thread_subject && e.thread_subject !== e.subject"
                       style="margin:2px 0 0;font-size:10px;color:#7c3aed;"
                       x-text="'スレッド: ' + e.thread_subject"></p>
                    <pre :style="emailBodyStyle(e.id, 120)" style="margin:6px 0 0;" x-text="e.plain_body || '(本文なし)'"></pre>
                    <button type="button" @click="toggleEmailBody(e.id)"
                            x-show="(e.plain_body || '').length > 200"
                            style="margin-top:4px;background:none;border:none;color:#2563eb;font-size:10px;font-weight:600;cursor:pointer;padding:2px 4px;"
                            :title="isEmailBodyExpanded(e.id) ? '本文を折りたたむ' : '本文を全部見る'">
                        <i class="fas" :class="isEmailBodyExpanded(e.id) ? 'fa-chevron-up' : 'fa-chevron-down'" style="font-size:9px;"></i>
                        <span x-text="isEmailBodyExpanded(e.id) ? '折りたたむ' : '本文をすべて表示'"></span>
                    </button>
                    <div style="margin-top:4px;display:flex;gap:6px;align-items:center;">
                        <a :href="'/?thread=' + e.thread_id" target="_blank"
                           style="display:inline-flex;align-items:center;gap:4px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;font-size:10px;font-weight:600;padding:2px 6px;border-radius:4px;text-decoration:none;"
                           title="メール画面でこのスレッドを開く">
                            <i class="fas fa-external-link-alt" style="font-size:8px;"></i>
                            メール画面で開く
                        </a>
                        <span x-show="e.attachments_count > 0" style="display:inline-flex;align-items:center;gap:3px;font-size:10px;color:#6b7280;">
                            <i class="fas fa-paperclip" style="font-size:9px;"></i>
                            <span x-text="e.attachments_count + ' 件'"></span>
                        </span>
                    </div>
                </div>
            </template>
        </div>
    </aside>

    {{-- 元スレッドチャット プレビューパネル (ルーム/全体から覗き見) — 左端ドラッグでリサイズ可 --}}
    <aside x-show="threadChatPreviewOpen" x-cloak
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="translate-x-8 opacity-0"
           x-transition:enter-end="translate-x-0 opacity-100"
           class="orig-thread-panel"
           :style="'width:' + threadChatPreviewWidth + 'px;'">
        {{-- リサイズハンドル (左端) --}}
        <div @mousedown.prevent="startResizeThreadChatPreview($event)"
             @dblclick="threadChatPreviewWidth = 420; localStorage.setItem('chatPreviewPanelWidth','420')"
             title="ドラッグで幅変更 / ダブルクリックでデフォルトに戻す"
             class="orig-resize-handle"
             :class="{ 'is-resizing': threadChatPreviewResizing }">
            <div class="orig-resize-grip"></div>
        </div>
        <div style="background:#ffffff;border-bottom:1px solid #e5e7eb;padding:10px 14px;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-comments" style="color:#10b981;"></i>
            <div style="flex:1;min-width:0;">
                <p style="margin:0;font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;">関連チャット</p>
                <p style="margin:0;font-size:12px;font-weight:700;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    x-text="threadChatPreviewSubject || '(件名なし)'"
                    :title="threadChatPreviewSubject"></p>
            </div>
            <button @click="jumpToChatThread(threadChatPreviewThreadId, threadChatPreviewFocusId); threadChatPreviewOpen = false;"
                    class="btn btn-sm btn-primary"
                    style="font-size:11px;padding:4px 10px;"
                    title="このスレッドのチャットへ移動">
                <i class="fas fa-external-link-alt"></i> 開く
            </button>
            <button @click="threadChatPreviewOpen = false" style="background:none;border:none;color:#6b7280;padding:4px;" title="閉じる"><i class="fas fa-times"></i></button>
        </div>
        <div id="thread-chat-preview-body" class="flex-1 overflow-y-auto" style="padding:8px 12px;background:#f9fafb;">
            <template x-if="threadChatPreviewLoading">
                <p class="text-center py-4" style="color:#9ca3af;font-size:12px;"><i class="fas fa-circle-notch fa-spin mr-1"></i>読み込み中...</p>
            </template>
            <template x-if="!threadChatPreviewLoading && threadChatPreviewComments.length === 0">
                <p class="text-center py-4" style="color:#9ca3af;font-size:12px;">コメントはまだありません</p>
            </template>
            <template x-for="m in threadChatPreviewComments" :key="m.id">
                <div :id="'preview-comment-' + m.id"
                     :style="(m.id === threadChatPreviewFocusId ? 'background:#fff7ed;border-left:3px solid #f97316;' : 'background:#ffffff;border:1px solid #e5e7eb;') + 'border-radius:6px;padding:6px 10px;margin-bottom:6px;'">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;">
                        <strong style="color:#111827;font-size:12px;font-weight:700;" x-text="m.author"></strong>
                        <span style="color:#9ca3af;font-size:10px;" x-text="m.created_at"></span>
                    </div>
                    <div style="color:#1f2937;font-size:13px;line-height:1.4;white-space:pre-wrap;word-wrap:break-word;" x-html="renderMentions(m.content)"></div>
                </div>
            </template>
        </div>

        {{-- 返信入力欄 (元スレッドに直接投稿) --}}
        <div style="border-top:1px solid #e5e7eb;background:#ffffff;padding:8px 10px;">
            <div style="display:flex;align-items:flex-end;gap:6px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:6px 8px;">
                <textarea id="thread-chat-preview-ta"
                          x-model="threadChatPreviewInput"
                          rows="1"
                          maxlength="5000"
                          @keydown.prevent.ctrl.enter="sendThreadChatPreviewReply()"
                          @keydown.prevent.meta.enter="sendThreadChatPreviewReply()"
                          @input="$event.target.style.height='auto'; $event.target.style.height = Math.min($event.target.scrollHeight, 140) + 'px';"
                          :placeholder="'#' + (threadChatPreviewSubject || 'スレッド') + ' へ返信 (Ctrl+Enter で送信)'"
                          style="flex:1;background:transparent;border:none;outline:none;resize:none;font-size:13px;line-height:1.4;color:#1f2937;max-height:140px;min-height:20px;"></textarea>
                {{-- 同上: 背景色も :style 側に統合 --}}
                <button @click="sendThreadChatPreviewReply()"
                        :disabled="!threadChatPreviewInput?.trim() || threadChatPreviewSending"
                        title="送信 (Ctrl+Enter)"
                        :style="'background:#2563eb;color:#fff;border:none;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;' + ((!threadChatPreviewInput?.trim() || threadChatPreviewSending) ? 'opacity:0.5;cursor:not-allowed;' : '')">
                    <i class="fas" :class="threadChatPreviewSending ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                </button>
            </div>
            <p style="margin:4px 2px 0;font-size:10px;color:#9ca3af;">投稿は元のチャットスレッドに保存されます。<kbd style="background:#f3f4f6;border:1px solid #e5e7eb;padding:0 4px;border-radius:3px;font-size:9px;">Ctrl</kbd>+<kbd style="background:#f3f4f6;border:1px solid #e5e7eb;padding:0 4px;border-radius:3px;font-size:9px;">Enter</kbd>で送信</p>
        </div>
    </aside>

    {{-- 元スレッド プレビューパネル (右側スライドイン) — 左端をドラッグでリサイズ可能 --}}
    <aside x-show="origEmailOpen" x-cloak
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="translate-x-8 opacity-0"
           x-transition:enter-end="translate-x-0 opacity-100"
           class="orig-thread-panel"
           :style="'width:' + origEmailPanelWidth + 'px;'">
        {{-- リサイズハンドル (左端) — 視覚化された掴みやすいバー --}}
        <div @mousedown.prevent="startResizeOrigEmail($event)"
             @dblclick="origEmailPanelWidth = 420; localStorage.setItem('chatOrigEmailPanelWidth','420')"
             title="ドラッグで幅変更 / ダブルクリックでデフォルトに戻す"
             class="orig-resize-handle"
             :class="{ 'is-resizing': origResizing }">
            <div class="orig-resize-grip"></div>
        </div>
        <div style="background:#ffffff;border-bottom:1px solid #e5e7eb;padding:10px 14px;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-list-ul" style="color:#3b82f6;"></i>
            <strong style="flex:1;font-size:13px;">関連メール</strong>
            <a :href="`/?thread=${origEmailThreadId || selected?.id}`"
               class="btn btn-sm btn-primary"
               style="font-size:11px;padding:4px 10px;"
               title="メール画面で開く">
                <i class="fas fa-external-link-alt"></i> メール画面へ
            </a>
            <button @click="origEmailOpen = false" style="background:none;border:none;color:#6b7280;padding:4px;" title="閉じる"><i class="fas fa-times"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto p-3" style="font-size:12px;color:#374151;" x-ref="origPanelBody">
            <template x-if="origEmailLoading">
                <p class="text-center py-4" style="color:#9ca3af;"><i class="fas fa-circle-notch fa-spin mr-1"></i>読み込み中...</p>
            </template>
            <template x-if="!origEmailLoading && (origEmails || []).length > 0">
                <div>
                    {{-- スレッド件名 (header) + メール数 --}}
                    <div style="margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #e5e7eb;">
                        <p style="font-weight:700;color:#111827;font-size:13px;margin:0;"
                           x-text="origThreadSubject || (selected?.kind === 'thread' ? (selected?.subject || '(件名なし)') : '(件名なし)')"></p>
                        <p style="color:#6b7280;font-size:11px;margin:2px 0 0;" x-text="(origEmails || []).length + ' 件のメール'"></p>
                    </div>
                    {{-- 各メールカード (古い順) --}}
                    <template x-for="(e, ei) in (origEmails || [])" :key="e.id">
                        <div :id="'orig-email-' + e.id"
                             :style="origEmailFocusId === e.id
                                 ? 'background:#fff7ed;border:2px solid #f59e0b;border-radius:8px;padding:10px;margin-bottom:10px;'
                                 : 'background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px;margin-bottom:10px;'">
                            <p style="font-weight:700;color:#111827;font-size:12px;margin:0 0 4px;" x-text="e.subject || '(件名なし)'"></p>
                            <p style="color:#6b7280;font-size:10px;margin:1px 0;">
                                <strong style="color:#9ca3af;">From:</strong>
                                <span x-text="e.from_label || e.from_address || ''"></span>
                            </p>
                            <p style="color:#6b7280;font-size:10px;margin:1px 0;" x-show="e.to_address">
                                <strong style="color:#9ca3af;">To:</strong>
                                <span x-text="e.to_address"></span>
                            </p>
                            <p style="color:#9ca3af;font-size:10px;margin:1px 0 6px;" x-text="e.received_at"></p>
                            <pre :style="emailBodyStyle(e.id, 240)" x-text="e.plain_body || '(本文なし)'"></pre>
                            <button type="button" @click="toggleEmailBody(e.id)"
                                    x-show="(e.plain_body || '').length > 300"
                                    style="margin-top:6px;background:none;border:none;color:#2563eb;font-size:11px;font-weight:600;cursor:pointer;padding:2px 4px;"
                                    :title="isEmailBodyExpanded(e.id) ? '本文を折りたたむ' : '本文を全部見る'">
                                <i class="fas" :class="isEmailBodyExpanded(e.id) ? 'fa-chevron-up' : 'fa-chevron-down'" style="font-size:10px;"></i>
                                <span x-text="isEmailBodyExpanded(e.id) ? '折りたたむ' : '本文をすべて表示'"></span>
                            </button>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="!origEmailLoading && (origEmails || []).length === 0">
                <p class="text-center py-4" style="color:#9ca3af;">メールを取得できませんでした</p>
            </template>
        </div>
    </aside>

    {{-- 「スレッドをルームに追加」モーダル — 中央配置 --}}
    <div x-show="chatAddToRoomOpen" x-cloak>
        <div @click="chatAddToRoomOpen = false"
             style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);z-index:9998;"></div>
        <div class="bundle-modal" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:420px;z-index:9999;">
            <div class="bundle-modal-head">
                <i class="fas fa-link" style="color:#2563eb;"></i>
                <strong style="flex:1;font-size:13px;color:#111827;">スレッドをルームに追加</strong>
                <button @click="chatAddToRoomOpen = false" style="background:none;border:none;color:#9ca3af;padding:4px;font-size:14px;"><i class="fas fa-times"></i></button>
            </div>
            <div class="bundle-modal-body">
                {{--
                    新規ルームをインラインで作成 (prompt() ではなくフォーム化)。
                    名前を打つと、下に部分一致サジェスト + 既存ルームのフィルタが連動して表示される。
                --}}
                <div style="padding:4px 8px 10px;border-bottom:1px solid #f3f4f6;margin-bottom:8px;">
                    <label style="font-size:10px;color:#9ca3af;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;">
                        新規ルームを作成して追加
                    </label>
                    <input type="text" x-model="chatAddToRoomNewName"
                           placeholder="新規ルーム名 (入力すると下に既存候補も出ます)"
                           style="margin-top:4px;width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:6px 9px;font-size:12px;outline:none;">

                    {{-- 部分一致サジェスト (2 文字以上)。クリックでそのルームへ追加 --}}
                    <template x-if="chatAddToRoomSimilarRooms.length > 0">
                        <div style="margin-top:6px;border:1px solid #fde68a;background:#fffbeb;border-radius:6px;padding:6px 8px;">
                            <p style="margin:0 0 3px;font-size:9px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.04em;">
                                <i class="fas fa-info-circle" style="margin-right:3px;"></i>似た名前のルームがあります (クリックで追加)
                            </p>
                            <div style="display:flex;flex-direction:column;gap:2px;max-height:120px;overflow-y:auto;">
                                <template x-for="r in chatAddToRoomSimilarRooms" :key="'cadd-sim-' + r.id">
                                    <button type="button" @click="confirmChatAddToRoom(r)"
                                            style="display:flex;align-items:center;gap:5px;width:100%;text-align:left;background:#fff;border:1px solid #fde68a;border-radius:4px;padding:4px 7px;cursor:pointer;font-size:11px;color:#1f2937;"
                                            onmouseover="this.style.backgroundColor='#fef3c7';"
                                            onmouseout="this.style.backgroundColor='#fff';">
                                        <i :class="r.is_private ? 'fas fa-lock' : 'fas fa-hashtag'"
                                           style="font-size:9px;width:11px;text-align:center;"
                                           :style="r.is_private ? 'color:#a78bfa;' : 'color:#6b7280;'"></i>
                                        <span style="flex:1;font-weight:600;" x-text="r.name"></span>
                                        <span style="font-size:8px;color:#9ca3af;font-weight:700;text-transform:uppercase;"
                                              x-text="r.is_private ? '個人' : '共有'"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div style="margin-top:8px;display:flex;gap:6px;">
                        <button type="button"
                                @click="createChatRoomAndAttach(false)"
                                :disabled="!chatAddToRoomNewName?.trim() || chatAddToRoomCreating"
                                :style="'flex:1;background:#2563eb;color:#fff;border:none;padding:6px 8px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;' + ((!chatAddToRoomNewName?.trim() || chatAddToRoomCreating) ? 'opacity:0.5;cursor:not-allowed;' : '')">
                            <i class="fas" :class="chatAddToRoomCreating ? 'fa-spinner fa-spin' : 'fa-globe'"></i> 共有で作成
                        </button>
                        <button type="button"
                                @click="createChatRoomAndAttach(true)"
                                :disabled="!chatAddToRoomNewName?.trim() || chatAddToRoomCreating"
                                :style="'flex:1;background:#7c3aed;color:#fff;border:none;padding:6px 8px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;' + ((!chatAddToRoomNewName?.trim() || chatAddToRoomCreating) ? 'opacity:0.5;cursor:not-allowed;' : '')">
                            <i class="fas" :class="chatAddToRoomCreating ? 'fa-spinner fa-spin' : 'fa-lock'"></i> 個人で作成
                        </button>
                    </div>
                </div>

                <p style="margin:8px 10px 4px;font-size:10px;color:#9ca3af;font-weight:700;text-transform:uppercase;">共有ルームから選ぶ</p>
                <template x-for="r in chatAddToRoomFilteredShared" :key="'cadd-sh-' + r.id">
                    <div @click="confirmChatAddToRoom(r)" class="bundle-thread-row">
                        <span class="hash" style="color:#9ca3af;font-weight:700;width:14px;text-align:center;">#</span>
                        <span style="flex:1;font-weight:600;" x-text="r.name"></span>
                    </div>
                </template>
                <template x-if="chatAddToRoomFilteredShared.length === 0">
                    <p style="text-align:center;color:#9ca3af;font-size:11px;padding:6px;"
                       x-text="chatAddToRoomNewName ? '該当する共有ルームはありません' : 'なし'"></p>
                </template>
                <p style="margin:14px 10px 4px;font-size:10px;color:#9ca3af;font-weight:700;text-transform:uppercase;">個人ルームから選ぶ</p>
                <template x-for="r in chatAddToRoomFilteredPersonal" :key="'cadd-pr-' + r.id">
                    <div @click="confirmChatAddToRoom(r)" class="bundle-thread-row">
                        <i class="fas fa-lock" style="font-size:9px;color:#a78bfa;width:14px;text-align:center;"></i>
                        <span style="flex:1;font-weight:600;" x-text="r.name"></span>
                    </div>
                </template>
                <template x-if="chatAddToRoomFilteredPersonal.length === 0">
                    <p style="text-align:center;color:#9ca3af;font-size:11px;padding:6px;"
                       x-text="chatAddToRoomNewName ? '該当する個人ルームはありません' : 'なし'"></p>
                </template>
            </div>
        </div>
    </div>

    {{-- 新規ルーム作成モーダル (公開/個人を選べる) — 中央配置 --}}
    <div x-show="createRoomOpen" x-cloak>
        <div @click="createRoomOpen = false"
             style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);z-index:9998;"></div>
        <div class="bundle-modal" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:420px;z-index:9999;">
            <div class="bundle-modal-head">
                <i class="fas fa-plus-circle" style="color:#2563eb;"></i>
                <strong style="flex:1;font-size:13px;color:#111827;">新規ルーム</strong>
                <button @click="createRoomOpen = false" style="background:none;border:none;color:#9ca3af;padding:4px;font-size:14px;"><i class="fas fa-times"></i></button>
            </div>
            <div style="padding:14px 18px;">
                <label style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;">ルーム名</label>
                <input type="text" x-model="newRoomName" @keydown.enter="submitCreateRoom()"
                       placeholder="例: 案件A 進行管理"
                       style="margin-top:4px;width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:7px 10px;font-size:13px;outline:none;">

                {{--
                    重複チェック (インクリメンタル) — 入力した名前と部分一致する既存ルームを提示し、
                    入力したキーワードに部分一致する既存ルームを提示して重複作成を防ぐ。
                    ・大文字小文字を無視した substring 一致
                    ・共有/個人を別セクションで表示 (どちらも自分が閲覧可なもののみ)
                    ・候補が 0 件の場合はセクションごと非表示
                    ・候補をクリックすると、モーダルを閉じてそのルームへ移動
                --}}
                <template x-if="similarRoomsForNewName.length > 0">
                    <div style="margin-top:10px;border:1px solid #fde68a;background:#fffbeb;border-radius:6px;padding:8px 10px;">
                        <p style="margin:0 0 4px;font-size:10px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.04em;">
                            <i class="fas fa-info-circle" style="margin-right:4px;"></i>
                            似た名前のルームがあります (クリックで参加)
                        </p>
                        <div style="display:flex;flex-direction:column;gap:2px;max-height:140px;overflow-y:auto;">
                            <template x-for="r in similarRoomsForNewName" :key="'sim-' + r.id">
                                <button type="button"
                                        @click="selectExistingRoomFromCreate(r)"
                                        style="display:flex;align-items:center;gap:6px;width:100%;text-align:left;background:#fff;border:1px solid #fde68a;border-radius:4px;padding:5px 8px;cursor:pointer;font-size:12px;color:#1f2937;transition:background .12s;"
                                        onmouseover="this.style.backgroundColor='#fef3c7';"
                                        onmouseout="this.style.backgroundColor='#fff';"
                                        :title="(r.is_private ? '個人ルーム: ' : '共有ルーム: ') + r.name + ' に移動'">
                                    <i :class="r.is_private ? 'fas fa-lock' : 'fas fa-hashtag'"
                                       style="font-size:10px;width:12px;text-align:center;"
                                       :style="r.is_private ? 'color:#a78bfa;' : 'color:#6b7280;'"></i>
                                    <span style="flex:1;font-weight:600;" x-text="r.name"></span>
                                    <span style="font-size:9px;color:#9ca3af;font-weight:700;text-transform:uppercase;"
                                          x-text="r.is_private ? '個人' : '共有'"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <label style="display:block;margin-top:14px;font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;">公開範囲</label>
                <div style="display:flex;gap:8px;margin-top:6px;">
                    <label class="room-vis-opt" :class="!newRoomIsPrivate ? 'is-active' : ''">
                        <input type="radio" :checked="!newRoomIsPrivate" @change="newRoomIsPrivate = false" style="margin:0;">
                        <div>
                            <p style="margin:0;font-weight:700;font-size:12px;"><i class="fas fa-globe" style="margin-right:4px;color:#3b82f6;"></i>全員共有</p>
                            <p style="margin:2px 0 0;font-size:10px;color:#6b7280;">他のユーザーにも表示されます</p>
                        </div>
                    </label>
                    <label class="room-vis-opt" :class="newRoomIsPrivate ? 'is-active' : ''">
                        <input type="radio" :checked="newRoomIsPrivate" @change="newRoomIsPrivate = true" style="margin:0;">
                        <div>
                            <p style="margin:0;font-weight:700;font-size:12px;"><i class="fas fa-lock" style="margin-right:4px;color:#a78bfa;"></i>個人用</p>
                            <p style="margin:2px 0 0;font-size:10px;color:#6b7280;">あなただけに表示されます</p>
                        </div>
                    </label>
                </div>
            </div>
            <div style="padding:10px 14px;border-top:1px solid #f3f4f6;display:flex;justify-content:flex-end;gap:8px;">
                <button @click="createRoomOpen = false"
                        style="background:#fff;border:1px solid #e5e7eb;color:#4b5563;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;">キャンセル</button>
                <button @click="submitCreateRoom()" :disabled="!newRoomName?.trim() || creatingRoom"
                        style="background:#2563eb;color:#fff;border:none;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:700;"
                        class="disabled:opacity-50">
                    <i class="fas" :class="creatingRoom ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                    作成
                </button>
            </div>
        </div>
    </div>

    {{-- ルーム編集モーダル (名前 / 公開範囲) — 作成モーダルと同じ構造 --}}
    <div x-show="editRoomOpen" x-cloak>
        <div @click="editRoomOpen = false"
             style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);z-index:9998;"></div>
        <div class="bundle-modal" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:420px;z-index:9999;">
            <div class="bundle-modal-head">
                <i class="fas fa-pen" style="color:#2563eb;"></i>
                <strong style="flex:1;font-size:13px;color:#111827;">ルームを編集</strong>
                <button @click="editRoomOpen = false" style="background:none;border:none;color:#9ca3af;padding:4px;font-size:14px;"><i class="fas fa-times"></i></button>
            </div>
            <div style="padding:14px 18px;">
                <label style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;">ルーム名</label>
                <input type="text" x-model="editRoomName" @keydown.enter="submitEditRoom()"
                       placeholder="例: 案件A 進行管理"
                       style="margin-top:4px;width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:7px 10px;font-size:13px;outline:none;">

                {{-- 公開範囲は「自分が作成者」の時だけ変更可能。
                     他人の共有ルームを勝手に個人化されると元の作成者から見えなくなるので、
                     作成者以外には rad-only な現状表示にとどめる。 --}}
                <template x-if="editRoomIsCreator">
                    <div>
                        <label style="display:block;margin-top:14px;font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;">公開範囲</label>
                        <div style="display:flex;gap:8px;margin-top:6px;">
                            <label class="room-vis-opt" :class="!editRoomIsPrivate ? 'is-active' : ''">
                                <input type="radio" :checked="!editRoomIsPrivate" @change="editRoomIsPrivate = false" style="margin:0;">
                                <div>
                                    <p style="margin:0;font-weight:700;font-size:12px;"><i class="fas fa-globe" style="margin-right:4px;color:#3b82f6;"></i>全員共有</p>
                                    <p style="margin:2px 0 0;font-size:10px;color:#6b7280;">他のユーザーにも表示されます</p>
                                </div>
                            </label>
                            <label class="room-vis-opt" :class="editRoomIsPrivate ? 'is-active' : ''">
                                <input type="radio" :checked="editRoomIsPrivate" @change="editRoomIsPrivate = true" style="margin:0;">
                                <div>
                                    <p style="margin:0;font-weight:700;font-size:12px;"><i class="fas fa-lock" style="margin-right:4px;color:#a78bfa;"></i>個人用</p>
                                    <p style="margin:2px 0 0;font-size:10px;color:#6b7280;">あなただけに表示されます</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </template>
                <template x-if="!editRoomIsCreator">
                    <p style="margin-top:14px;font-size:11px;color:#6b7280;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:6px;padding:8px 10px;">
                        <i class="fas fa-info-circle" style="margin-right:4px;color:#9ca3af;"></i>
                        公開範囲はルーム作成者のみ変更できます。
                    </p>
                </template>
            </div>
            <div style="padding:10px 14px;border-top:1px solid #f3f4f6;display:flex;justify-content:flex-end;gap:8px;">
                <button @click="editRoomOpen = false"
                        style="background:#fff;border:1px solid #e5e7eb;color:#4b5563;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;">キャンセル</button>
                <button @click="submitEditRoom()" :disabled="!editRoomName?.trim() || editingRoom"
                        style="background:#2563eb;color:#fff;border:none;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:700;"
                        class="disabled:opacity-50">
                    <i class="fas" :class="editingRoom ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                    保存
                </button>
            </div>
        </div>
    </div>

    {{-- ルームにスレッドを追加するモーダル — 中央配置 --}}
    <div x-show="bundleModalOpen" x-cloak>
        <div @click="bundleModalOpen = false"
             style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);z-index:9998;"></div>
        <div class="bundle-modal" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999;">
            <div class="bundle-modal-head">
                <i class="fas fa-link" style="color:#10b981;"></i>
                <strong style="flex:1;font-size:13px;color:#111827;">スレッドを追加</strong>
                <button @click="bundleModalOpen = false" style="background:none;border:none;color:#9ca3af;padding:4px;font-size:14px;"><i class="fas fa-times"></i></button>
            </div>
            <div style="padding:10px 14px;border-bottom:1px solid #f3f4f6;">
                <input type="text" x-model="bundleSearchQuery" @input.debounce.250ms="loadPickableThreads()"
                       placeholder="件名で検索..."
                       style="width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:6px 10px;font-size:12px;outline:none;">
            </div>
            <div class="bundle-modal-body">
                <template x-if="pickableLoading">
                    <p class="text-center py-3" style="color:#9ca3af;font-size:11px;"><i class="fas fa-spinner fa-spin"></i> 読み込み中...</p>
                </template>
                <template x-for="pt in pickableThreads" :key="pt.id">
                    <div class="bundle-thread-row"
                         :class="bundledThreads.find(b => b.id === pt.id) ? 'is-attached' : ''"
                         @click="bundledThreads.find(b => b.id === pt.id) ? detachThread(pt.id) : attachThread(pt.id)">
                        <i class="fas" :class="bundledThreads.find(b => b.id === pt.id) ? 'fa-check-square' : 'fa-square'"
                           style="color:#10b981;width:14px;"></i>
                        <div style="flex:1;min-width:0;">
                            <p style="margin:0;font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="pt.subject"></p>
                            <p style="margin:0;font-size:10px;color:#9ca3af;" x-text="pt.last_email_at || ''"></p>
                        </div>
                    </div>
                </template>
                <template x-if="!pickableLoading && pickableThreads.length === 0">
                    <p class="text-center py-4" style="color:#9ca3af;font-size:11px;">該当スレッドなし</p>
                </template>
            </div>
        </div>
    </div>

    {{-- リアクションピッカー (複数選択可・閉じるボタンで明示クローズ) --}}
    <div x-show="reactionPickerOpen" x-cloak
         @click.outside="reactionPickerOpen = false"
         :style="'position:fixed;left:' + reactionPickerX + 'px;top:' + reactionPickerY + 'px;z-index:200;'"
         style="background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;padding:8px;width:280px;box-shadow:0 10px 25px rgba(0,0,0,0.15);">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:2px 4px 6px;">
            <span style="font-size:10px;color:#6b7280;font-weight:700;text-transform:uppercase;">リアクション (複数可)</span>
            <button type="button" @click="reactionPickerOpen = false"
                    style="background:none;border:none;color:#9ca3af;padding:2px 4px;cursor:pointer;font-size:11px;"
                    onmouseover="this.style.color='#374151';"
                    onmouseout="this.style.color='#9ca3af';"
                    title="閉じる"><i class="fas fa-times"></i></button>
        </div>
        <div style="display:grid;grid-template-columns:repeat(8,1fr);gap:2px;max-height:240px;overflow-y:auto;">
            <template x-for="e in reactionEmojis" :key="e">
                <button type="button" @click="pickReaction(e)"
                        :style="isReactionMine(reactionPickerTarget, e)
                            ? 'background:#dbeafe;border:1px solid #93c5fd;padding:6px;cursor:pointer;font-size:18px;border-radius:6px;line-height:1;'
                            : 'background:none;border:1px solid transparent;padding:6px;cursor:pointer;font-size:18px;border-radius:6px;line-height:1;'"
                        onmouseover="if(!this.style.background.includes('219')) this.style.backgroundColor='#f3f4f6'"
                        onmouseout="if(this.style.background.includes('rgb(243') || this.style.background === 'rgb(243, 244, 246)') this.style.backgroundColor='transparent'"
                        x-text="e"></button>
            </template>
        </div>
    </div>
</div>

<script>
function chatHubApp() {
    return {
        // 一覧
        threads: [], rooms: [], loading: false,
        filter: 'all', searchQuery: '',
        // スレッド/ルームセクションの折りたたみ
        threadsCollapsed: (() => { try { return JSON.parse(localStorage.getItem('chatThreadsCollapsed') || 'false'); } catch(_) { return false; } })(),
        // 「ルーム未設定」フィルタの ON/OFF。 localStorage で永続化 (再ロード後も維持)。
        // ON のときはサイドバーのスレッド一覧を「どのルームにも未登録のものだけ」に絞る。
        onlyUnroomedThreads: (() => { try { return JSON.parse(localStorage.getItem('chatOnlyUnroomedThreads') || 'false'); } catch(_) { return false; } })(),
        sharedRoomsCollapsed: (() => { try { return JSON.parse(localStorage.getItem('chatSharedRoomsCollapsed') || 'false'); } catch(_) { return false; } })(),
        personalRoomsCollapsed: (() => { try { return JSON.parse(localStorage.getItem('chatPersonalRoomsCollapsed') || 'false'); } catch(_) { return false; } })(),
        // 階層化したルームの各ノードの折りたたみ状態 (true = 子ルームを隠す).
        roomBranchCollapsed: (() => { try { return JSON.parse(localStorage.getItem('chatRoomBranchCollapsed') || '{}'); } catch(_) { return {}; } })(),
        // 選択 (kind = 'thread' | 'room')
        selected: null,
        // メッセージ
        comments: [], chatLoading: false, sending: false, input: '',
        // スレッド/ルーム毎の入力ドラフト + 返信状態を保持
        // (別スレッドへ切り替えても入力内容を保持し、他スレッドに紛れ込ませない)
        inputDrafts: {},
        replyingDrafts: {},
        // 現在のチャット内をフィルタするためのメッセージ検索
        messageSearchQuery: '',
        // 選択中の添付ファイル (送信前)
        pendingFiles: [],
        // 「最新へ」ボタンの表示制御 (一番下にいないとき true)
        scrolledUp: false,
        // メンション
        users: [], mentionOpen: false, mentionMatches: [], mentionIndex: 0, mentionStart: -1, mentionQuery: '',
        // 返信・引用・絵文字
        replyingTo: null,
        emojiOpen: false,
        emojis: ['😀','😁','😂','🤣','😅','😊','😍','🥰','😘','😎','🤩','🤔','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','🤐','😯','😪','😫','🥱','😴','😌','😛','😜','😝','🤤','😒','😓','😔','😕','🙃','🤑','😲','☹️','🙁','😖','😞','😟','😤','😢','😭','😦','😧','😨','😩','🤯','😬','😰','😱','🥵','🥶','😳','🤪','😵','🥴','😠','😡','🤬','😷','🤒','🤕','🤢','🤮','🥳','😇','🤓','🧐','😈','👻','💀','👋','👌','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','👍','👎','👊','✊','🤛','🤜','👏','🙌','👐','🤲','🙏','💪','💯','🔥','⭐','🎉','✅','❌','⚠️','❓','❗','💬','📌','📅','📞','📧','📎','📂','✏️','📝','💼','🚀','💡','💰','🏠','🍙'],
        // リアクション
        reactionEmojis: ['👍','❤️','😂','🎉','🔥','👏','😢','😮','😡','✅','❌','💯','🙏','💪','⭐','👀','🤔','👌','🙌','💬'],
        reactionPickerOpen: false,
        reactionPickerTarget: null,
        reactionPickerX: 0,
        reactionPickerY: 0,
        // 元スレッド/元メール プレビュー
        origEmailOpen: false,
        origEmails: [],           // スレッド内の全メール (received_at 昇順)
        origEmailFocusId: null,   // ハイライト対象のメール ID (null = 全体表示)
        // 本文を「折りたたみ解除して全文表示」するメール ID の集合
        // (関連メールパネル / 束ねられたスレッドメールパネル 両方で共用)
        expandedEmailBodies: [],
        origEmailLoading: false,
        origEmailPanelWidth: parseInt(localStorage.getItem('chatOrigEmailPanelWidth') || '420', 10),
        origResizing: false,
        // 元スレッドを別スレッド (ルームのバンドル元) で表示するための ID
        origEmailThreadId: null,
        _origLoadedThreadId: null,
        origThreadSubject: '',
        // 「元スレッド」プレビュー (ルームから元のチャットスレッドを覗き見)
        threadChatPreviewOpen: false,
        threadChatPreviewThreadId: null,
        threadChatPreviewSubject: '',
        threadChatPreviewComments: [],
        threadChatPreviewLoading: false,
        threadChatPreviewFocusId: null,
        threadChatPreviewInput: '',
        threadChatPreviewSending: false,
        threadChatPreviewWidth: parseInt(localStorage.getItem('chatPreviewPanelWidth') || '420', 10),
        threadChatPreviewResizing: false,
        myId: {{ auth()->id() ?? 'null' }}, myName: @js(auth()->user()->name ?? ''),
        csrfToken: document.querySelector('meta[name="csrf-token"]').content,
        // 左パネル幅
        panelWidth: parseInt(localStorage.getItem('chatHubPanelWidth') || '280', 10),
        // サイドバーの折りたたみ状態 (localStorage に永続化)
        sidebarCollapsed: JSON.parse(localStorage.getItem('chatSidebarCollapsed') || 'false'),
        // 非表示も表示するトグル (per-user, localStorage 記憶)
        showHidden: JSON.parse(localStorage.getItem('chatShowHidden') || 'false'),
        hiddenThreadIds: [],
        hiddenRoomIds: [],
        // ルームバンドル (このルームに紐付くスレッド)
        bundledThreads: [],
        // 束ねたスレッドの帯: 折りたたみ状態 (デフォルト = 折りたたみ済み)。
        // 折りたたみ時はチップを 1 件も表示せず「束ねたスレッド (N)」のヘッダだけ。
        bundleBandExpanded: (() => { try { return JSON.parse(localStorage.getItem('chatBundleBandExpanded') || 'false'); } catch(_) { return false; } })(),
        bundleModalOpen: false,
        bundleSearchQuery: '',
        pickableThreads: [],
        pickableLoading: false,
        // 新規ルーム作成モーダル
        createRoomOpen: false,
        newRoomName: '',
        newRoomIsPrivate: false,
        creatingRoom: false,
        // ルーム編集モーダル
        editRoomOpen: false,
        editRoomId: null,
        editRoomName: '',
        editRoomIsPrivate: false,
        editRoomIsCreator: false,  // ログインユーザーが対象ルームの作成者か (公開範囲フィールドの可視性を制御)
        editingRoom: false,
        // 「スレッドをルームに追加」モーダル
        chatAddToRoomOpen: false,
        chatAddToRoomThreadId: null,
        // インライン新規作成用 (旧 prompt() の代替)
        chatAddToRoomNewName: '',
        chatAddToRoomCreating: false,
        // 「このチャットのメール」パネル (ルームに束ねられたスレッドのメール一覧)
        roomEmailsPanelOpen: false,
        roomEmailsLoading: false,
        roomEmails: [],
        roomEmailsPanelWidth: parseInt(localStorage.getItem('chatRoomEmailsPanelWidth') || '460', 10),
        roomEmailsResizing: false,
        // ルーム関連: レポート/Wiki パネル (共有/個人どちらも利用可)
        roomReportPanelOpen: false,
        roomReportContent: '',
        roomReportUpdatedAt: '',
        roomReportLoading: false,
        roomReportSaving: false,
        roomReportSaved: false,
        roomWikiPanelOpen: false,
        roomWikiContent: '',
        roomWikiUpdatedAt: '',
        roomWikiLoading: false,
        roomWikiSaving: false,
        roomWikiSaved: false,
        roomDocPanelWidth: parseInt(localStorage.getItem('chatRoomDocPanelWidth') || '500', 10),
        roomDocResizing: false,

        async init() {
            try {
                await Promise.all([this.load(), this.loadUsers()]);
                // 通知リンク (`/chats#thread-123&comment=456` または `?thread=123&comment=456` ) から自動選択
                await this.applyHashSelection();
                // hash 変化 (通知の再クリック) にも対応
                window.addEventListener('hashchange', () => this.applyHashSelection());
            } catch (e) {
                // init で例外が出ても画面 (x-cloak) が解除されずに真っ白になるのを回避
                console.error('chatHubApp init failed:', e);
            }
        },

        // URL hash または query から thread/comment を抽出して自動選択 + スクロール
        async applyHashSelection() {
            let threadId = null, roomId = null, commentId = null;
            try {
                // hash パターン: #thread-123&comment=456 or #room-12
                const h = (window.location.hash || '').replace(/^#/, '');
                if (h) {
                    h.split('&').forEach(part => {
                        const tm = part.match(/^thread-(\d+)$/);     if (tm) threadId  = parseInt(tm[1], 10);
                        const rm = part.match(/^room-(\d+)$/);       if (rm) roomId    = parseInt(rm[1], 10);
                        const cm = part.match(/^comment[=:-](\d+)$/);if (cm) commentId = parseInt(cm[1], 10);
                    });
                }
                // query パターン: ?thread=123&comment=456&room=12
                const q = new URL(window.location.href).searchParams;
                if (!threadId && q.get('thread')) threadId = parseInt(q.get('thread'), 10);
                if (!roomId && q.get('room'))     roomId   = parseInt(q.get('room'), 10);
                if (!commentId && q.get('comment')) commentId = parseInt(q.get('comment'), 10);
            } catch (_) {}

            if (threadId) {
                let t = this.threads.find(x => x.id === threadId);
                if (!t) {
                    // チャットコメントがまだないスレッドはサイドバー (listThreads) に出ない。
                    // メタデータを /threads/{id} から取得して最小のスレッドオブジェクトを組み立て、
                    // 一覧 (this.threads) にも追加する (チャット投稿前でも選択中スレッドが見えるように)
                    try {
                        const r = await fetch(`/threads/${threadId}`, { headers:{Accept:'application/json'} });
                        if (r.ok) {
                            const d = await r.json();
                            if (d.thread) {
                                t = {
                                    id: d.thread.id,
                                    kind: 'thread',
                                    subject: d.thread.subject || '(無題)',
                                    is_pinned: !!d.thread.is_pinned,
                                    is_pinned_chat: false,
                                    is_hidden: false,
                                    comment_count: 0,
                                    unread_count: 0,
                                    mention_count: 0,
                                    last_comment: null,
                                };
                                this.threads = [t, ...this.threads];
                            }
                        }
                    } catch (_) {}
                }
                if (t) { await this.selectThread(t); }
            } else if (roomId) {
                let r = this.rooms.find(x => x.id === roomId);
                if (!r) {
                    // 通常 /chats/threads が visibleTo($me) で返してくるが、
                    // 非表示フラグ・キャッシュずれ等で取り逃すことがあるため、
                    // メール側と同じ /api/chat-rooms 一覧から拾い直すフォールバック
                    try {
                        const res = await fetch('/api/chat-rooms', { headers: { 'Accept': 'application/json' } });
                        if (res.ok) {
                            const d = await res.json();
                            const found = (d.rooms || []).find(x => Number(x.id) === Number(roomId));
                            if (found) {
                                r = { kind: 'room', is_hidden: false, is_pinned_chat: false, ...found };
                                this.rooms = [r, ...this.rooms];
                            }
                        }
                    } catch (_) {}
                }
                if (r) {
                    await this.selectRoom(r);
                } else {
                    console.warn('chats: ?room=' + roomId + ' は可視ルーム一覧に見つかりませんでした');
                }
            }
            if (commentId) {
                // メッセージ描画後にハイライト + スクロール
                this.$nextTick(() => setTimeout(() => this.scrollToComment(commentId), 200));
            }
        },

        scrollToComment(commentId) {
            const el = document.getElementById('comment-' + commentId);
            if (!el) return;
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // 簡易ハイライト
            el.style.transition = 'background-color 0.6s';
            const orig = el.style.backgroundColor;
            el.style.backgroundColor = '#fef3c7';
            setTimeout(() => { el.style.backgroundColor = orig; }, 2200);
        },
        async loadUsers() {
            try {
                const r = await fetch('/users', { headers:{Accept:'application/json'} });
                if (r.ok) {
                    const data = await r.json();
                    this.users = data.users || data || [];
                }
            } catch (_) {}
        },
        async load() {
            this.loading = true;
            try {
                localStorage.setItem('chatShowHidden', JSON.stringify(this.showHidden));
                const params = new URLSearchParams();
                if (this.searchQuery) params.set('q', this.searchQuery);
                if (this.filter === 'mentioned') params.set('mentioned', '1');
                if (this.showHidden) params.set('show_hidden', '1');
                const r = await fetch('/chats/threads?' + params.toString(), { headers:{Accept:'application/json'} });
                if (r.ok) {
                    const d = await r.json();
                    this.hiddenThreadIds = d.hidden_threads || [];
                    this.hiddenRoomIds   = d.hidden_rooms || [];
                    this.threads = (d.threads || []).map(t => ({
                        kind:'thread',
                        is_hidden: this.hiddenThreadIds.includes(Number(t.id)),
                        ...t,
                    }));
                    this.rooms   = (d.rooms   || []).map(r => ({
                        kind:'room',
                        is_hidden: this.hiddenRoomIds.includes(Number(r.id)),
                        ...r,
                    }));
                }
            } catch (_) {}
            this.loading = false;
        },

        // 全体ビュー (読み取り専用)
        // 現在の入力 (本文 + 返信状態) を選択中のコンテキストへ保存
        _saveCurrentDraft() {
            if (!this.selected) return;
            const key = this._contextKey(this.selected);
            if (!key) return;
            if ((this.input || '').length > 0) {
                this.inputDrafts[key] = this.input;
            } else {
                delete this.inputDrafts[key];
            }
            if (this.replyingTo) {
                this.replyingDrafts[key] = this.replyingTo;
            } else {
                delete this.replyingDrafts[key];
            }
        },
        // コンテキストごとのドラフトを復元 (なければクリア)
        _restoreDraftFor(sel) {
            const key = this._contextKey(sel);
            this.input = (key && this.inputDrafts[key]) ? this.inputDrafts[key] : '';
            this.replyingTo = (key && this.replyingDrafts[key]) ? this.replyingDrafts[key] : null;
            this.$nextTick(() => {
                if (this.$refs.ta) {
                    this.$refs.ta.style.height = 'auto';
                    if (this.input) this.$refs.ta.style.height = Math.min(this.$refs.ta.scrollHeight, 200) + 'px';
                }
            });
        },
        _contextKey(sel) {
            if (!sel) return '';
            if (sel.kind === 'all') return 'all';
            return `${sel.kind}-${sel.id ?? 'na'}`;
        },

        // ===== メール本文の折りたたみ / 全文表示 =====
        // 関連メールパネル / 束ねスレッドメールパネル の両方で同じ集合を共用する
        isEmailBodyExpanded(emailId) {
            return this.expandedEmailBodies.includes(emailId);
        },
        toggleEmailBody(emailId) {
            if (!emailId && emailId !== 0) return;
            const idx = this.expandedEmailBodies.indexOf(emailId);
            if (idx >= 0) {
                this.expandedEmailBodies.splice(idx, 1);
            } else {
                this.expandedEmailBodies.push(emailId);
            }
        },
        // <pre> の max-height 切替用 inline-style 文字列を返す
        emailBodyStyle(emailId, baseMaxHeight) {
            const base = 'background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px;font-family:inherit;font-size:11px;line-height:1.5;white-space:pre-wrap;word-break:break-word;color:#1f2937;margin:0;';
            const heightPart = this.isEmailBodyExpanded(emailId)
                ? 'max-height:none;overflow-y:visible;'
                : `max-height:${baseMaxHeight}px;overflow-y:auto;`;
            return base + heightPart;
        },

        async selectAll() {
            this._saveCurrentDraft();
            this.selected = { kind: 'all', name: '全体チャット', is_pinned_chat: false };
            this._restoreDraftFor(this.selected);
            this.origEmails = []; this.origEmailFocusId = null;
            this.bundledThreads = [];
            // 横断ナビ: 全体ビュー時はルーム/スレッド選択を全て解除
            try {
                localStorage.removeItem('currentRoomId');
                localStorage.removeItem('currentThreadId');
            } catch (_) {}
            this.chatLoading = true;
            this.comments = [];
            try {
                const r = await fetch('/api/chats/all-messages', { headers:{Accept:'application/json'} });
                if (r.ok) {
                    const d = await r.json();
                    this.comments = d.comments || [];
                }
                this.$nextTick(() => this.scrollToBottom());
            } catch (_) {}
            this.chatLoading = false;
        },

        // 全体ビューのコンテキストバッジクリックで該当スレッド/ルームへジャンプ
        async jumpToContext(m) {
            if (!m.context_id) return;
            if (m.context_kind === 'room') {
                const r = this.rooms.find(x => x.id === m.context_id);
                if (r) { await this.selectRoom(r); }
            } else {
                const t = this.threads.find(x => x.id === m.context_id);
                if (t) {
                    await this.selectThread(t);
                    this.$nextTick(() => setTimeout(() => this.scrollToComment(m.id), 200));
                }
            }
        },

        // ===== 非表示/表示 =====
        async toggleHide(type, id, isHidden) {
            try {
                const url = isHidden ? '/api/chats/unhide' : '/api/chats/hide';
                const r = await fetch(url, {
                    method:'POST',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                    body: JSON.stringify({ type, id }),
                });
                if (!r.ok) throw new Error();
                await this.load();
            } catch (_) {
                alert('非表示の更新に失敗しました');
            }
        },

        // ===== ルームバンドル (スレッドまとめ) =====
        openBundleModal() {
            if (!this.selected || this.selected.kind !== 'room') return;
            this.bundleSearchQuery = '';
            this.bundleModalOpen = true;
            this.loadPickableThreads();
        },
        async loadPickableThreads() {
            this.pickableLoading = true;
            try {
                const params = new URLSearchParams();
                if (this.bundleSearchQuery) params.set('q', this.bundleSearchQuery);
                const r = await fetch('/api/chat-rooms/_/pickable-threads?' + params.toString(), { headers:{Accept:'application/json'} });
                if (r.ok) {
                    const d = await r.json();
                    this.pickableThreads = d.threads || [];
                }
            } catch (_) {}
            this.pickableLoading = false;
        },
        async loadBundledThreads() {
            if (!this.selected || this.selected.kind !== 'room') {
                this.bundledThreads = [];
                return;
            }
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/threads`, { headers:{Accept:'application/json'} });
                if (r.ok) {
                    const d = await r.json();
                    this.bundledThreads = d.threads || [];
                }
            } catch (_) {}
        },
        async attachThread(threadId) {
            if (!this.selected || this.selected.kind !== 'room') return;
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/threads`, {
                    method:'POST',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                    body: JSON.stringify({ thread_id: threadId }),
                });
                if (!r.ok) throw new Error();
                await this.loadBundledThreads();
                await this.loadComments();
            } catch (_) { alert('紐付けに失敗しました'); }
        },
        async detachThread(threadId) {
            if (!this.selected || this.selected.kind !== 'room') return;
            const isShared = !this.selected.is_private;
            const bt = (this.bundledThreads || []).find(b => Number(b.id) === Number(threadId));
            const subject = bt?.subject || '(件名なし)';
            const msg = isShared
                ? '⚠ 共有ルームからスレッドを外します\n\n'
                + 'ルーム名: # ' + (this.selected.name || '') + '\n'
                + 'スレッド: ' + subject + '\n\n'
                + 'このルームに参加している他のメンバー全員からも、このスレッドが見えなくなります。\n'
                + '本当に外しますか?'
                : '個人ルームからスレッドを外します。\n\nスレッド: ' + subject + '\n\nよろしいですか?';
            if (!confirm(msg)) return;
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/threads/${threadId}`, {
                    method:'DELETE',
                    headers:{'Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                });
                if (!r.ok) throw new Error();
                await this.loadBundledThreads();
                await this.loadComments();
            } catch (_) { alert('紐付け解除に失敗しました'); }
        },

        // ============= グローバルショートカット =============
        // J/K で サイドバー (共有ルーム → 個人ルーム → スレッド一覧) を順送り / 逆送り.
        // メール画面と同じ感覚で使えるようにする. 入力欄やモーダル中は無効.
        onGlobalKey(e) {
            const tag = (e.target?.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
            if (e.target?.isContentEditable) return;
            // モーダル系の Alpine state が真なら J/K を奪わない (Esc のみ通す).
            const modalOpen = this.createRoomModalOpen || this.editRoomOpen || this.mergeModalOpen
                              || this.deleteConfirmOpen || this.aiSummaryOpen || this.attachmentsModalOpen
                              || this.knowledgeModalOpen || this.roomReportPanelOpen || this.roomWikiPanelOpen;
            if (modalOpen) {
                if (e.key === 'Escape') {
                    // 個別 Esc ハンドラがある UI もあるが、最終フォールバックとして消化
                    e.preventDefault();
                    this.createRoomModalOpen = false;
                    this.editRoomOpen = false;
                    this.mergeModalOpen = false;
                    this.deleteConfirmOpen = false;
                }
                return;
            }
            const ctrlOrCmd = e.ctrlKey || e.metaKey;
            // Ctrl+Z (チャット画面の戻す対象は今のところピン/非表示の取消程度なので軽い扱い).
            if (ctrlOrCmd && (e.key === 'z' || e.key === 'Z') && !e.shiftKey) {
                e.preventDefault();
                // 今のところチャット画面では巻き戻しスタックなし. ノーオプ.
                return;
            }
            if (ctrlOrCmd || e.altKey) return;

            switch (e.key) {
                case 'j': case 'J':
                    e.preventDefault(); this._navChatSidebar(+1); break;
                case 'k': case 'K':
                    e.preventDefault(); this._navChatSidebar(-1); break;
                case '/':
                    e.preventDefault();
                    const el = document.querySelector('input[type="text"][placeholder*="検索"], input[type="search"]');
                    if (el) el.focus();
                    break;
                case 'Escape':
                    if (this.origEmailOpen) { e.preventDefault(); this.origEmailOpen = false; }
                    break;
                case '?':
                    e.preventDefault();
                    if (typeof window.riceShowKeyboardShortcuts === 'function') window.riceShowKeyboardShortcuts();
                    break;
            }
        },
        // サイドバーの選択候補を「共有ルーム → 個人ルーム → スレッド一覧」の順に
        // フラット配列化して J/K でなめる.
        _navChatSidebar(dir) {
            const flat = [];
            (this.sharedRooms || []).forEach(r => flat.push({ kind: 'room', data: r }));
            (this.personalRooms || []).forEach(r => flat.push({ kind: 'room', data: r }));
            (this.threads || []).forEach(t => flat.push({ kind: 'thread', data: t }));
            if (flat.length === 0) return;
            let idx = flat.findIndex(x => this.selected
                && this.selected.kind === x.kind && this.selected.id === x.data.id);
            if (idx === -1) {
                idx = dir > 0 ? 0 : flat.length - 1;
            } else {
                idx = Math.max(0, Math.min(flat.length - 1, idx + dir));
            }
            const target = flat[idx];
            if (target.kind === 'room') this.selectRoom(target.data);
            else this.selectThread(target.data);
        },

        async selectRoom(r) {
            // 同じルームを再度クリックしたら全体チャットに戻すトグル動作
            if (this.selected?.kind === 'room' && this.selected?.id === r.id) {
                return this.selectAll();
            }
            this._saveCurrentDraft();
            this.selected = r;
            this._restoreDraftFor(r);
            // スレッド固有のキャッシュをリセット
            this.origEmails = [];
            this.origEmailFocusId = null;
            // 横断ナビ用にカレントルームを保存 + スレッド側はクリア
            try {
                localStorage.setItem('currentRoomId', String(r.id));
                localStorage.removeItem('currentThreadId');
            } catch (_) {}
            await Promise.all([this.loadComments(), this.loadBundledThreads()]);
            // loadComments がサーバ側で last_read_at を更新するので、サイドバーの未読バッジを更新するため再読込
            this.load();
        },
        async selectThread(t) {
            this._saveCurrentDraft();
            this.selected = t;
            this._restoreDraftFor(t);
            // 別スレッドに切り替えたら元メールキャッシュをリセット
            this.origEmails = [];
            this.origEmailFocusId = null;
            this.bundledThreads = [];
            // 横断ナビ: スレッドビュー → カレントスレッドを保存 / ルーム側はクリア
            try {
                localStorage.setItem('currentThreadId', String(t.id));
                localStorage.removeItem('currentRoomId');
            } catch (_) {}
            await this.loadComments();
        },
        async loadComments() {
            if (!this.selected) return;
            this.chatLoading = true;
            this.comments = [];
            try {
                const url = this.selected.kind === 'room'
                    ? `/api/chat-rooms/${this.selected.id}/messages`
                    : `/threads/${this.selected.id}/comments`;
                const r = await fetch(url, { headers:{Accept:'application/json'} });
                if (r.ok) {
                    const d = await r.json();
                    this.comments = d.comments || [];
                }
                this.$nextTick(() => this.scrollToBottom());
            } catch (_) {}
            this.chatLoading = false;
        },

        // ===== 返信・引用・絵文字 =====
        async replyTo(m) {
            // 全体ビューからの返信は元のスレッド/ルームへ移動してから返信状態に
            if (this.selected?.kind === 'all') {
                await this.jumpToContextThenAct(m, 'reply');
                return;
            }
            // ルームでバンドル元スレッドのコメントに返信 → そのチャットスレッドへジャンプして続行
            if (this.selected?.kind === 'room' && m.thread_id) {
                const enriched = { ...m, context_kind: 'thread', context_id: m.thread_id };
                await this.jumpToContextThenAct(enriched, 'reply');
                return;
            }
            // 通常の返信 (現在のスレッド/ルームに留まる)
            this.replyingTo = m;
            const mentionPrefix = '@' + (m.author || '') + ' ';
            if (!this.input.startsWith(mentionPrefix)) {
                this.input = mentionPrefix + this.input;
            }
            this.$nextTick(() => { try { this.$refs.ta.focus(); } catch (_) {} });
        },
        cancelReply() { this.replyingTo = null; },
        async quoteMessage(m) {
            // 全体ビューからの引用も元のスレッド/ルームに移動してから引用状態に
            if (this.selected?.kind === 'all') {
                await this.jumpToContextThenAct(m, 'quote');
                return;
            }
            // ルームでバンドル元スレッドのコメント → そのチャットスレッドへジャンプ
            if (this.selected?.kind === 'room' && m.thread_id) {
                const enriched = { ...m, context_kind: 'thread', context_id: m.thread_id };
                await this.jumpToContextThenAct(enriched, 'quote');
                return;
            }
            // 「> @作者: 本文」を入力欄の末尾に挿入
            const lines = (m.content || '').split('\n').map(l => '> ' + l).join('\n');
            const quote = '> ' + (m.author || '') + ':\n' + lines + '\n\n';
            this.input = (this.input ? this.input + '\n' : '') + quote;
            this.$nextTick(() => {
                try {
                    this.$refs.ta.focus();
                    const len = this.input.length;
                    this.$refs.ta.setSelectionRange(len, len);
                } catch (_) {}
                if (this.$refs.ta) this.autoresize({ target: this.$refs.ta });
            });
        },

        // 「元スレッド」プレビュー: ルームに居たまま、元のチャットスレッドのコメントをサイドパネルで閲覧
        async openThreadChatPreview(threadId, focusCommentId = null) {
            if (!threadId) return;
            // 既に同じスレッドを開いてる場合 → 閉じる
            if (this.threadChatPreviewOpen && this.threadChatPreviewThreadId === threadId) {
                this.threadChatPreviewOpen = false;
                this.threadChatPreviewThreadId = null;
                this.threadChatPreviewInput = '';
                return;
            }
            this.threadChatPreviewOpen = true;
            this.threadChatPreviewThreadId = threadId;
            this.threadChatPreviewFocusId = focusCommentId;
            this.threadChatPreviewComments = [];
            this.threadChatPreviewInput = '';
            this.threadChatPreviewLoading = true;
            // 件名はサイドバーの threads から推測 (なければ別途取得)
            const t = this.threads.find(x => x.id === threadId);
            this.threadChatPreviewSubject = t ? t.subject : '';
            try {
                const r = await fetch(`/threads/${threadId}/comments`, { headers:{Accept:'application/json'} });
                if (r.ok) {
                    const d = await r.json();
                    this.threadChatPreviewComments = d.comments || [];
                }
                if (!this.threadChatPreviewSubject) {
                    try {
                        const tr = await fetch(`/threads/${threadId}`, { headers:{Accept:'application/json'} });
                        if (tr.ok) {
                            const td = await tr.json();
                            this.threadChatPreviewSubject = td.thread?.subject || '';
                        }
                    } catch (_) {}
                }
                // フォーカス対象コメントがあれば、返信用に @author を入力欄にプリフィル
                if (focusCommentId) {
                    const focusMsg = this.threadChatPreviewComments.find(c => c.id === focusCommentId);
                    if (focusMsg && focusMsg.author) {
                        this.threadChatPreviewInput = '@' + focusMsg.author + ' ';
                    }
                }
            } catch (_) {}
            this.threadChatPreviewLoading = false;
            this.$nextTick(() => {
                if (this.threadChatPreviewFocusId) {
                    const el = document.getElementById('preview-comment-' + this.threadChatPreviewFocusId);
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    const body = document.getElementById('thread-chat-preview-body');
                    if (body) body.scrollTop = body.scrollHeight;
                }
                // 入力欄にフォーカス
                const ta = document.getElementById('thread-chat-preview-ta');
                if (ta && this.threadChatPreviewInput) {
                    try {
                        ta.focus();
                        const len = ta.value.length;
                        ta.setSelectionRange(len, len);
                    } catch (_) {}
                }
            });
        },

        // プレビューパネルから直接、元スレッドチャットへ投稿
        async sendThreadChatPreviewReply() {
            const text = (this.threadChatPreviewInput || '').trim();
            if (!text || this.threadChatPreviewSending || !this.threadChatPreviewThreadId) return;
            this.threadChatPreviewSending = true;
            try {
                const r = await fetch(`/threads/${this.threadChatPreviewThreadId}/comments`, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ content: text }),
                });
                if (!r.ok) {
                    const data = await r.json().catch(() => ({}));
                    alert('送信失敗: ' + (data.message || r.status));
                    return;
                }
                const d = await r.json();
                if (d && d.comment) {
                    this.threadChatPreviewComments.push(d.comment);
                }
                this.threadChatPreviewInput = '';
                this.threadChatPreviewFocusId = d?.comment?.id || null;
                this.$nextTick(() => {
                    const body = document.getElementById('thread-chat-preview-body');
                    if (body) body.scrollTop = body.scrollHeight;
                });
                // 元のルーム/全体ビューの comments もリロード (バンドルされたスレッドの新着が反映されるよう)
                if (this.selected) {
                    if (this.selected.kind === 'room') this.loadComments();
                    else if (this.selected.kind === 'all') this.selectAll();
                }
            } catch (e) {
                alert('通信エラー: ' + e.message);
            } finally {
                this.threadChatPreviewSending = false;
            }
        },

        // 指定したチャットスレッドへ移動 (任意でコメントへスクロール)
        async jumpToChatThread(threadId, commentId = null) {
            if (!threadId) return;
            const t = this.threads.find(x => x.id === threadId);
            if (!t) {
                alert('対象のチャットスレッドが見つかりません (非表示やチャット未投稿の可能性)');
                return;
            }
            await this.selectThread(t);
            if (commentId) {
                this.$nextTick(() => setTimeout(() => this.scrollToComment(commentId), 350));
            }
        },

        // 全体ビューで返信/引用を押したとき、元のチャットへ移動してから動作を継続
        async jumpToContextThenAct(m, action) {
            if (!m.context_id || !m.context_kind) {
                alert('元のチャットを特定できませんでした');
                return;
            }
            if (m.context_kind === 'room') {
                const r = this.rooms.find(x => x.id === m.context_id);
                if (!r) {
                    alert('元のルームが見つかりません (非表示や個人ルームの可能性)');
                    return;
                }
                await this.selectRoom(r);
            } else {
                const t = this.threads.find(x => x.id === m.context_id);
                if (!t) {
                    alert('元のスレッドが見つかりません');
                    return;
                }
                await this.selectThread(t);
            }
            // selectXxx で comments が再読込される。該当 m を見つけて action 実行。
            this.$nextTick(() => setTimeout(() => {
                const target = (this.comments || []).find(c => c.id === m.id);
                if (target) {
                    this.scrollToComment(m.id);
                    if (action === 'reply') this.replyTo(target);
                    else if (action === 'quote') this.quoteMessage(target);
                }
            }, 350));
        },
        insertEmoji(e) {
            const ta = this.$refs.ta;
            if (!ta) { this.input = (this.input || '') + e; return; }
            const pos = ta.selectionStart;
            const before = this.input.slice(0, pos);
            const after  = this.input.slice(pos);
            this.input = before + e + after;
            this.$nextTick(() => {
                try {
                    ta.focus();
                    const np = pos + e.length;
                    ta.setSelectionRange(np, np);
                } catch (_) {}
            });
        },

        // ===== 添付ファイル =====
        onFilesPicked(e) {
            const files = Array.from(e.target.files || []);
            const max = 10, maxBytes = 10 * 1024 * 1024;
            for (const f of files) {
                if (this.pendingFiles.length >= max) { alert('添付は最大' + max + 'ファイルまでです'); break; }
                if (f.size > maxBytes) { alert(`「${f.name}」は10MBを超えています`); continue; }
                this.pendingFiles.push(f);
            }
            // 同じファイルを再選択できるように input をリセット
            e.target.value = '';
        },
        removePendingFile(i) {
            this.pendingFiles.splice(i, 1);
        },
        formatBytes(n) {
            n = Number(n) || 0;
            if (n < 1024) return n + 'B';
            if (n < 1024 * 1024) return (n / 1024).toFixed(1) + 'KB';
            return (n / 1024 / 1024).toFixed(1) + 'MB';
        },

        async send() {
            let text = (this.input || '').trim();
            const hasFiles = this.pendingFiles.length > 0;
            if ((!text && !hasFiles) || this.sending || !this.selected) return;
            // 返信中なら本文先頭に「> @author: 引用」を付加 (短く)
            if (this.replyingTo && text) {
                const preview = (this.replyingTo.content || '').split('\n').slice(0, 3).join('\n');
                text = '> ' + (this.replyingTo.author || '') + ':\n> ' +
                       preview.replace(/\n/g, '\n> ') + '\n\n' + text;
            }
            this.sending = true;
            try {
                const url = this.selected.kind === 'room'
                    ? `/api/chat-rooms/${this.selected.id}/messages`
                    : `/threads/${this.selected.id}/comments`;
                let r;
                if (hasFiles) {
                    const fd = new FormData();
                    if (text) fd.append('content', text);
                    this.pendingFiles.forEach(f => fd.append('files[]', f));
                    r = await fetch(url, {
                        method:'POST',
                        headers:{'Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                        body: fd,
                    });
                } else {
                    r = await fetch(url, {
                        method:'POST',
                        headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                        body: JSON.stringify({ content: text }),
                    });
                }
                if (!r.ok) {
                    const err = await r.json().catch(() => ({}));
                    alert(err.message || '送信失敗');
                    return;
                }
                const d = await r.json();
                this.comments.push(d.comment);
                this.input = '';
                this.pendingFiles = [];
                this.replyingTo = null;
                this.$nextTick(() => {
                    if (this.$refs.ta) this.$refs.ta.style.height = 'auto';
                    this.scrollToBottom();
                });
                // 一覧側のプレビューも反映
                if (this.selected.kind === 'thread') {
                    const t = this.threads.find(x => x.id === this.selected.id);
                    if (t) { t.comment_count = (t.comment_count||0) + 1; }
                }
            } catch (e) { alert('通信エラー: ' + e.message); }
            finally { this.sending = false; }
        },

        async deleteMessage(id) {
            if (!confirm('このメッセージを削除しますか？')) return;
            try {
                // thread_comments テーブルはルーム/スレッド両方で共有 → 同じ削除エンドポイント
                const r = await fetch(`/thread-comments/${id}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':this.csrfToken, Accept:'application/json'} });
                if (r.ok || r.status === 204) {
                    this.comments = this.comments.filter(c => c.id !== id);
                }
            } catch (_) {}
        },

        openCreateRoomModal(defaultPrivate = false) {
            this.newRoomName = '';
            this.newRoomIsPrivate = !!defaultPrivate;
            this.createRoomOpen = true;
        },

        // 新規ルーム作成中、入力した名前と部分一致する既存ルーム候補。
        // - 大文字小文字を無視した substring 一致
        // - 完全一致は除外しない (同名重複も気付かせるため、ボタンとして提示)
        // - 共有を先、個人を後ろにして最大 8 件
        // 2 文字未満の入力では出さない (1 文字だと全件マッチで意味が無い)
        get similarRoomsForNewName() {
            const q = (this.newRoomName || '').trim().toLowerCase();
            if (q.length < 2) return [];
            const matches = (this.rooms || []).filter(r =>
                (r.name || '').toLowerCase().includes(q)
            );
            // 共有 → 個人 の順で並べる
            const sorted = matches.sort((a, b) => {
                const ap = a.is_private ? 1 : 0;
                const bp = b.is_private ? 1 : 0;
                if (ap !== bp) return ap - bp;
                return (a.name || '').localeCompare(b.name || '');
            });
            return sorted.slice(0, 8);
        },

        // 候補ルームのクリック: モーダルを閉じてそのルームへ移動
        async selectExistingRoomFromCreate(room) {
            if (!room) return;
            this.createRoomOpen = false;
            this.newRoomName = '';
            // selectRoom はサイドバー由来の click と同じ動線を辿る (チャット履歴ロード等)
            await this.selectRoom(room);
        },

        // ルームを公開/個人で分けて取得
        get _chatLocalQuery() { return (this.searchQuery || '').toLowerCase().trim(); },
        // 新着優先ソート用. メール画面と同じ並び順に統一して、画面間でルーム位置がブレないように。
        //   1. メンション未読あり
        //   2. チャット未読 or 受信メールあり
        //   3. それ以外
        _roomNewMailRank(r) {
            const mention = Number(r.mention_count || 0);
            const mail    = Number(r.received_email_count || 0);
            const chat    = Number(r.unread_count    || 0);
            if (mention > 0) return 0;
            if (mail > 0 || chat > 0) return 1;
            return 2;
        },
        _sortRoomsByNewMail(rooms) {
            return rooms.slice().sort((a, b) => {
                const ra = this._roomNewMailRank(a);
                const rb = this._roomNewMailRank(b);
                if (ra !== rb) return ra - rb;
                const sa = Number(a.received_email_count || 0)
                         + Number(a.unread_count    || 0)
                         + Number(a.mention_count   || 0);
                const sb = Number(b.received_email_count || 0)
                         + Number(b.unread_count    || 0)
                         + Number(b.mention_count   || 0);
                if (sa !== sb) return sb - sa;
                return (a.name || '').localeCompare(b.name || '', 'ja');
            });
        },
        // 階層化したルーム配列を DFS で展開し depth 属性を付与する.
        // roomBranchCollapsed[id] = true の枝はサイドバーで折りたたまれて子孫を出力しない.
        _walkRoomTree(rooms) {
            const idSet = new Set(rooms.map(r => Number(r.id)));
            const byParent = new Map();
            for (const r of rooms) {
                const pid = r.parent_room_id && idSet.has(Number(r.parent_room_id))
                    ? String(r.parent_room_id) : 'root';
                if (!byParent.has(pid)) byParent.set(pid, []);
                byParent.get(pid).push(r);
            }
            const out = [];
            const dfs = (key, depth) => {
                for (const r of (byParent.get(key) || [])) {
                    const hasChildren = byParent.has(String(r.id));
                    out.push({ ...r, _depth: depth, _hasChildren: hasChildren });
                    if (hasChildren && !this.roomBranchCollapsed[r.id]) {
                        dfs(String(r.id), depth + 1);
                    }
                }
            };
            dfs('root', 0);
            return out;
        },
        toggleRoomBranch(id) {
            this.roomBranchCollapsed = { ...this.roomBranchCollapsed, [id]: !this.roomBranchCollapsed[id] };
            try { localStorage.setItem('chatRoomBranchCollapsed', JSON.stringify(this.roomBranchCollapsed)); } catch(_) {}
        },
        // 選択中の親ルームの子孫 ID 集合 (青い "active" ハイライトを子にも広げるため).
        get _selectedRoomDescendants() {
            const all = (this.rooms || []);
            const id = this.selected?.kind === 'room' ? Number(this.selected.id) : null;
            if (!id) return new Set();
            const byParent = new Map();
            for (const r of all) {
                const pid = r.parent_room_id ? Number(r.parent_room_id) : null;
                if (pid !== null) {
                    if (!byParent.has(pid)) byParent.set(pid, []);
                    byParent.get(pid).push(Number(r.id));
                }
            }
            const out = new Set([id]);
            const q = [id];
            while (q.length) {
                const cur = q.shift();
                for (const c of (byParent.get(cur) || [])) {
                    if (!out.has(c)) { out.add(c); q.push(c); }
                }
            }
            return out;
        },
        // ルーム行を青ハイライトすべきか. 選択中の自身 or 選択中の子孫.
        isRoomInSelection(r) {
            if (!r) return false;
            return this._selectedRoomDescendants.has(Number(r.id));
        },
        get sharedRooms() {
            const q = this._chatLocalQuery;
            let base = (this.rooms || []).filter(r => !r.is_private);
            if (q) base = base.filter(r => (r.name || '').toLowerCase().includes(q));
            // 階層展開 (親 → 子 → 孫 を DFS で並べる, 親の直下に子が来る).
            return this._walkRoomTree(base);
        },
        get personalRooms() {
            const q = this._chatLocalQuery;
            let base = (this.rooms || []).filter(r => r.is_private);
            if (q) base = base.filter(r => (r.name || '').toLowerCase().includes(q));
            // 個人ルームも階層展開. 新着優先のソートはそれぞれの階層内で適用したいので
            // _walkRoomTree 後に深さを保ったまま使う.
            return this._walkRoomTree(this._sortRoomsByNewMail(base));
        },
        // サイドバー表示用 (検索クエリ + 折りたたみ + 非表示も適用)
        // ・ルーム選択中はそのルームのバンドル先スレッドのみに絞る
        // ・「ルーム未設定」フィルタが ON なら、どのルームにも入っていないスレッドだけ残す
        get visibleThreadsForSidebar() {
            const q = this._chatLocalQuery;
            let base = (this.threads || []);
            if (this.selected?.kind === 'room') {
                const bundleIds = new Set((this.bundledThreads || []).map(b => Number(b.id)));
                base = base.filter(t => bundleIds.has(Number(t.id)));
            } else if (this.onlyUnroomedThreads) {
                const inAnyRoom = this._allChatBundledThreadIds;
                base = base.filter(t => !inAnyRoom.has(Number(t.id)));
            }
            return q ? base.filter(t => (t.subject || '').toLowerCase().includes(q)) : base;
        },

        // 全ルームのバンドルスレッド ID 集合 (Set)。
        // 「ルーム未設定」フィルタで、どのルームにも入っていないスレッドを判別するために使う。
        // /chats/threads 経由で取得した rooms 配列の各 room.bundled_thread_ids をマージする。
        get _allChatBundledThreadIds() {
            const set = new Set();
            for (const r of (this.rooms || [])) {
                for (const tid of (r.bundled_thread_ids || [])) {
                    set.add(Number(tid));
                }
            }
            return set;
        },

        // 選択中スレッドが束ねられている (参加している) ルーム一覧
        get threadParentRooms() {
            if (this.selected?.kind !== 'thread') return [];
            const tid = this.selected.id;
            return (this.rooms || []).filter(r =>
                Array.isArray(r.bundled_thread_ids) && r.bundled_thread_ids.includes(tid)
            );
        },

        // ===== バンドル連動ハイライト (ルーム選択中→そのバンドルスレッド / スレッド選択中→所属ルーム を同じ青色に) =====
        get bundleHighlightedThreadIds() {
            if (this.selected?.kind !== 'room') return new Set();
            const r = (this.rooms || []).find(x => x.id === this.selected.id);
            return new Set(r?.bundled_thread_ids || []);
        },
        get bundleHighlightedRoomIds() {
            if (this.selected?.kind !== 'thread') return new Set();
            const tid = this.selected.id;
            return new Set((this.rooms || [])
                .filter(r => Array.isArray(r.bundled_thread_ids) && r.bundled_thread_ids.includes(tid))
                .map(r => r.id));
        },
        isThreadActive(t) {
            return (this.selected?.kind === 'thread' && this.selected?.id === t.id)
                || this.bundleHighlightedThreadIds.has(t.id);
        },
        isRoomActive(r) {
            // 親ルームが選択されている時はその子孫もアクティブとして青く表示する
            // (= 親を見ると配下のスレッド・チャットも含まれることをサイドバーで可視化).
            return (this.selected?.kind === 'room' && this._selectedRoomDescendants.has(Number(r.id)))
                || this.bundleHighlightedRoomIds.has(r.id);
        },
        // ルームレポート (共有/個人どちらも利用可)。
        // 個人ルームの場合は canSeeRoom (作成者のみ) で守られているため、自分専用メモとして機能する。
        async openRoomReportPanel() {
            if (!this.selected || this.selected.kind !== 'room') return;
            if (this.roomReportPanelOpen) { this.roomReportPanelOpen = false; return; }
            this.roomReportPanelOpen = true;
            // 他の関連パネル (Wiki) は閉じる
            this.roomWikiPanelOpen = false;
            this.roomReportLoading = true;
            this.roomReportContent = '';
            this.roomReportUpdatedAt = '';
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/report`, { headers:{Accept:'application/json'} });
                if (r.ok) {
                    const d = await r.json();
                    this.roomReportContent = d.content || '';
                    this.roomReportUpdatedAt = d.updated_at || '';
                }
            } catch (_) {}
            this.roomReportLoading = false;
        },
        async saveRoomReport() {
            if (!this.selected || this.selected.kind !== 'room') return;
            this.roomReportSaving = true; this.roomReportSaved = false;
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/report`, {
                    method:'PUT',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                    body: JSON.stringify({ content: this.roomReportContent }),
                });
                if (!r.ok) { alert('保存失敗'); return; }
                const d = await r.json();
                this.roomReportUpdatedAt = d.updated_at || '';
                this.roomReportSaved = true;
                setTimeout(() => { this.roomReportSaved = false; }, 2000);
            } catch (e) { alert('通信エラー: ' + e.message); }
            finally { this.roomReportSaving = false; }
        },

        // Wiki: 共有/個人どちらも利用可
        async openRoomWikiPanel() {
            if (!this.selected || this.selected.kind !== 'room') return;
            if (this.roomWikiPanelOpen) { this.roomWikiPanelOpen = false; return; }
            this.roomWikiPanelOpen = true;
            this.roomReportPanelOpen = false;
            this.roomWikiLoading = true;
            this.roomWikiContent = '';
            this.roomWikiUpdatedAt = '';
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/wiki`, { headers:{Accept:'application/json'} });
                if (r.ok) {
                    const d = await r.json();
                    this.roomWikiContent = d.content || '';
                    this.roomWikiUpdatedAt = d.updated_at || '';
                }
            } catch (_) {}
            this.roomWikiLoading = false;
        },
        async saveRoomWiki() {
            if (!this.selected || this.selected.kind !== 'room') return;
            this.roomWikiSaving = true; this.roomWikiSaved = false;
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/wiki`, {
                    method:'PUT',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                    body: JSON.stringify({ content: this.roomWikiContent }),
                });
                if (!r.ok) { alert('保存失敗'); return; }
                const d = await r.json();
                this.roomWikiUpdatedAt = d.updated_at || '';
                this.roomWikiSaved = true;
                setTimeout(() => { this.roomWikiSaved = false; }, 2000);
            } catch (e) { alert('通信エラー: ' + e.message); }
            finally { this.roomWikiSaving = false; }
        },
        startResizeRoomDoc(e) {
            const startX = e.clientX, startW = this.roomDocPanelWidth;
            this.roomDocResizing = true;
            document.body.classList.add('orig-resizing');
            const onMove = (me) => {
                const delta = startX - me.clientX;
                this.roomDocPanelWidth = Math.max(320, Math.min(window.innerWidth - 360, startW + delta));
            };
            const onUp = () => {
                this.roomDocResizing = false;
                document.body.classList.remove('orig-resizing');
                try { localStorage.setItem('chatRoomDocPanelWidth', String(this.roomDocPanelWidth)); } catch(_) {}
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onUp);
        },

        // 「このチャットのメール」パネル
        async openRoomEmailsPanel() {
            if (!this.selected || this.selected.kind !== 'room') return;
            if (this.roomEmailsPanelOpen) {
                this.roomEmailsPanelOpen = false;
                return;
            }
            this.roomEmailsPanelOpen = true;
            this.roomEmailsLoading = true;
            this.roomEmails = [];
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/emails`, { headers:{Accept:'application/json'} });
                if (r.ok) {
                    const d = await r.json();
                    this.roomEmails = d.emails || [];
                }
            } catch (_) {}
            this.roomEmailsLoading = false;
        },
        startResizeRoomEmails(e) {
            const startX = e.clientX, startW = this.roomEmailsPanelWidth;
            this.roomEmailsResizing = true;
            document.body.classList.add('orig-resizing');
            const onMove = (me) => {
                const delta = startX - me.clientX;
                this.roomEmailsPanelWidth = Math.max(300, Math.min(window.innerWidth - 360, startW + delta));
            };
            const onUp = () => {
                this.roomEmailsResizing = false;
                document.body.classList.remove('orig-resizing');
                try { localStorage.setItem('chatRoomEmailsPanelWidth', String(this.roomEmailsPanelWidth)); } catch(_) {}
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onUp);
        },

        // チャット側からも「スレッドをルームに追加」を実施できる
        openChatAddToRoom(threadId) {
            this.chatAddToRoomThreadId = threadId;
            this.chatAddToRoomNewName = '';  // 前回入力を引き継がない
            this.chatAddToRoomOpen = true;
        },

        // ====== モーダル内のリスト絞り込み + 部分一致サジェスト ======
        // 入力名で sharedRooms / personalRooms をリアルタイムにフィルタする。
        // 「ルーム名で絞り込み」と「重複検出」を同じ入力フィールドで兼ねる UX。
        get _chatAddToRoomQuery() {
            return (this.chatAddToRoomNewName || '').trim().toLowerCase();
        },
        get chatAddToRoomFilteredShared() {
            const q = this._chatAddToRoomQuery;
            const base = (this.rooms || []).filter(r => !r.is_private);
            return q ? base.filter(r => (r.name || '').toLowerCase().includes(q)) : base;
        },
        get chatAddToRoomFilteredPersonal() {
            const q = this._chatAddToRoomQuery;
            const base = (this.rooms || []).filter(r => r.is_private);
            return q ? base.filter(r => (r.name || '').toLowerCase().includes(q)) : base;
        },
        get chatAddToRoomSimilarRooms() {
            const q = this._chatAddToRoomQuery;
            if (q.length < 2) return [];
            const matches = (this.rooms || []).filter(r => (r.name || '').toLowerCase().includes(q));
            return matches.sort((a, b) => {
                const ap = a.is_private ? 1 : 0;
                const bp = b.is_private ? 1 : 0;
                if (ap !== bp) return ap - bp;
                return (a.name || '').localeCompare(b.name || '');
            }).slice(0, 8);
        },

        // 新規ルーム作成 → 対象スレッドを自動的にそのルームへ追加。
        // 旧版は prompt() を出していたが、モーダル内の入力フィールドから値を取る形に変更。
        // 入力中の部分一致サジェストにより、似た既存ルームへワンクリックで合流できる。
        async createChatRoomAndAttach(isPrivate = false) {
            if (!this.chatAddToRoomThreadId) return;
            const name = (this.chatAddToRoomNewName || '').trim();
            if (!name) { alert('ルーム名を入力してください'); return; }
            if (this.chatAddToRoomCreating) return;
            this.chatAddToRoomCreating = true;
            try {
                const r = await fetch('/api/chat-rooms', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ name, is_private: !!isPrivate }),
                });
                if (!r.ok) { alert('ルーム作成に失敗しました'); return; }
                const data = await r.json();
                if (!data?.room?.id) { alert('作成結果が不正です'); return; }
                await this.load(); // サイドバーのルーム一覧をリロード
                await this.confirmChatAddToRoom(data.room);
            } catch (e) {
                alert('通信エラー: ' + e.message);
            } finally {
                this.chatAddToRoomCreating = false;
            }
        },

        async confirmChatAddToRoom(room) {
            if (!this.chatAddToRoomThreadId || !room) return;
            try {
                const r = await fetch(`/api/chat-rooms/${room.id}/threads`, {
                    method:'POST',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                    body: JSON.stringify({ thread_id: this.chatAddToRoomThreadId }),
                });
                if (!r.ok) { alert('追加失敗'); return; }
                this.chatAddToRoomOpen = false;
                this.chatAddToRoomThreadId = null;
                this.chatAddToRoomNewName = '';     // 次回起動時に前回入力を引き継がない
                // ルーム一覧 (this.rooms) を再取得して bundled_thread_ids を更新する。
                // 「ルーム未設定」フィルタが ON のときに、今追加したスレッドがリストから
                // すぐに外れるようにするため必須。
                await this.load();
                // 現在開いているルームが追加先なら表示を更新
                if (this.selected?.kind === 'room' && this.selected.id === room.id) {
                    await Promise.all([this.loadComments(), this.loadBundledThreads()]);
                }
            } catch (e) { alert('通信エラー: ' + e.message); }
        },

        async submitCreateRoom() {
            const name = (this.newRoomName || '').trim();
            if (!name || this.creatingRoom) return;
            this.creatingRoom = true;
            try {
                const r = await fetch('/api/chat-rooms', {
                    method:'POST',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                    body: JSON.stringify({ name, is_private: this.newRoomIsPrivate }),
                });
                if (!r.ok) { alert('作成失敗'); return; }
                this.createRoomOpen = false;
                await this.load();
                const data = await r.json();
                const room = this.rooms.find(x => x.id === data.room.id);
                if (room) await this.selectRoom(room);
            } finally { this.creatingRoom = false; }
        },
        // 旧 createRoom (互換用に残しておく)
        async createRoom() { this.openCreateRoomModal(); },

        // ===== ルーム編集 =====
        openEditRoomModal() {
            if (!this.selected || this.selected.kind !== 'room') return;
            this.editRoomId        = this.selected.id;
            this.editRoomName      = this.selected.name || '';
            this.editRoomIsPrivate = !!this.selected.is_private;
            this.editRoomIsCreator = (this.selected.created_by_user_id ?? null) === this.myId;
            this.editRoomOpen      = true;
        },
        async submitEditRoom() {
            const name = (this.editRoomName || '').trim();
            if (!name || this.editingRoom || !this.editRoomId) return;
            this.editingRoom = true;
            try {
                // 公開範囲は作成者のみ送る (サーバ側でも弾くが、UI 側でも余計な値を送らない)
                const body = { name };
                if (this.editRoomIsCreator) body.is_private = !!this.editRoomIsPrivate;
                const r = await fetch(`/api/chat-rooms/${this.editRoomId}`, {
                    method:'PUT',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                    body: JSON.stringify(body),
                });
                if (!r.ok) {
                    let msg = '保存に失敗しました';
                    try { msg = (await r.json()).error || msg; } catch (_) {}
                    alert(msg);
                    return;
                }
                const data = await r.json();
                this.editRoomOpen = false;

                // ローカルの rooms 配列と選択中ルームを反映 (再フェッチでも良いが、即時反映の体感を優先)
                const updated = data.room || {};
                const idx = this.rooms.findIndex(x => x.id === this.editRoomId);
                if (idx >= 0) {
                    this.rooms[idx] = { ...this.rooms[idx], ...updated };
                }
                if (this.selected?.kind === 'room' && this.selected.id === this.editRoomId) {
                    this.selected = { ...this.selected, ...updated };
                }
                // 公開範囲を変えた可能性があるので、表示一覧の整合性のために load も走らせる
                await this.load();
            } catch (e) {
                alert('通信エラー: ' + e.message);
            } finally { this.editingRoom = false; }
        },
        async deleteRoom() {
            if (!this.selected || this.selected.kind !== 'room') return;
            if (!confirm(`#${this.selected.name} を削除しますか？`)) return;
            await fetch(`/api/chat-rooms/${this.selected.id}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':this.csrfToken}});
            this.selected = null; this.comments = [];
            this.load();
        },

        // ===== 表示加工 =====
        // 同分内・同一ユーザーの連投はコンパクト表示
        isCompact(idx) {
            if (idx === 0) return false;
            const prev = this.comments[idx - 1];
            const m = this.comments[idx];
            if (!prev || !m) return false;
            if (this.shouldShowDate(idx)) return false;
            return prev.user_id === m.user_id && prev.created_at === m.created_at;
        },
        // ヘッダー検索でメッセージをフィルタ (内容 or 投稿者にマッチ)
        messageMatches(m) {
            const q = (this.messageSearchQuery || '').trim().toLowerCase();
            if (!q) return true;
            const content = (m.content || '').toLowerCase();
            const author = (m.author || '').toLowerCase();
            const ctx = (m.context_label || '').toLowerCase();
            return content.includes(q) || author.includes(q) || ctx.includes(q);
        },

        // メッセージグループ毎に交互背景色 (同分内連投は同じグループ色で連投感維持)
        msgGroupBg(idx) {
            let count = 0;
            for (let i = 0; i <= idx; i++) {
                if (!this.isCompact(i)) count++;
            }
            return (count % 2 === 1) ? 'msg-bg-a' : 'msg-bg-b';
        },
        // 直前メッセージと日付が違えば区切りを表示
        shouldShowDate(idx) {
            if (idx === 0) return true;
            const prev = this.comments[idx - 1];
            const m = this.comments[idx];
            return (prev?.created_at || '').substring(0, 10) !== (m?.created_at || '').substring(0, 10);
        },
        // YYYY/MM/DD 部分を 今日 / 昨日 / 日付 に整形
        dateLabel(createdAt) {
            const d = (createdAt || '').toString();
            const datePart = d.substring(0, 10);
            const today = new Date().toDateString();
            const yest  = new Date(Date.now() - 86400000).toDateString();
            try {
                const iso = d.replace(/\//g, '-').replace(' ', 'T');
                const dObj = new Date(iso);
                if (!isNaN(dObj)) {
                    if (dObj.toDateString() === today) return '今日';
                    if (dObj.toDateString() === yest) return '昨日';
                }
            } catch (_) {}
            return datePart;
        },
        avatarColor(userId) {
            // アプリ系統色のアバターパレット
            const palette = ['#3b82f6','#10b981','#f59e0b','#ec4899','#8b5cf6','#06b6d4','#ef4444','#84cc16'];
            return palette[(userId || 0) % palette.length];
        },
        renderMentions(text) {
            if (!text) return '';
            const esc = s => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            // 先頭・末尾の空白 (改行・タブ・半角/全角スペース) を除去
            // 連続改行は1つに、行頭の連続空白も最小化して左詰めにする
            const stripped = String(text)
                .replace(/^[\s　]+/, '')
                .replace(/[\s　]+$/, '')
                .replace(/\n{3,}/g, '\n\n')          // 3行以上の空行 → 1行の空行
                .replace(/(^|\n)[ \t　]+/g, '$1');   // 各行頭の半角/全角スペース・タブを削除
            return esc(stripped).replace(/@([^\s@.,!?。、]+)/g, '<span class="mention-tag">@$1</span>');
        },
        isMentionedToMe(text) {
            if (!text || !this.myName) return false;
            return new RegExp('@' + this.myName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?=[\\s\\n.,!?。、]|$)', 'u').test(text);
        },

        // ===== 入力ハンドル =====
        onChatInput(e) {
            const ta = e.target;
            const pos = ta.selectionStart;
            const val = ta.value;
            // @ 候補
            let start = -1;
            for (let i = pos - 1; i >= 0; i--) {
                const ch = val[i];
                if (ch === '@') {
                    const prev = val[i - 1];
                    if (i === 0 || /\s/.test(prev)) start = i;
                    break;
                }
                if (/\s/.test(ch)) break;
            }
            if (start === -1) { this.mentionOpen = false; return; }
            this.mentionStart = start;
            this.mentionQuery = val.slice(start + 1, pos).toLowerCase();
            const list = (this.users || []).filter(u => u.id !== this.myId && (u.name || '').toLowerCase().includes(this.mentionQuery));
            this.mentionMatches = list.slice(0, 8);
            this.mentionIndex = 0;
            this.mentionOpen = this.mentionMatches.length > 0;
        },
        closeMention() { this.mentionOpen = false; },
        pickMention(u) {
            if (!u || this.mentionStart < 0) { this.closeMention(); return; }
            const ta = this.$refs.ta;
            const val = ta.value;
            const pos = ta.selectionStart;
            const before = val.slice(0, this.mentionStart);
            const after  = val.slice(pos);
            const insertion = '@' + u.name + ' ';
            this.input = before + insertion + after;
            this.$nextTick(() => {
                const np = before.length + insertion.length;
                try { ta.focus(); ta.setSelectionRange(np, np); } catch (_) {}
            });
            this.closeMention();
        },
        onKeydown(e) {
            if (this.mentionOpen && this.mentionMatches.length > 0) {
                if (e.key === 'ArrowDown') { e.preventDefault(); this.mentionIndex = Math.min(this.mentionIndex + 1, this.mentionMatches.length - 1); return; }
                if (e.key === 'ArrowUp')   { e.preventDefault(); this.mentionIndex = Math.max(this.mentionIndex - 1, 0); return; }
                if (e.key === 'Enter')     { e.preventDefault(); this.pickMention(this.mentionMatches[this.mentionIndex]); return; }
                if (e.key === 'Escape')    { e.preventDefault(); this.closeMention(); return; }
            }
            // Ctrl/Cmd + Enter で送信、それ以外の Enter は改行
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                this.send();
                return;
            }
            // Ctrl/Cmd + ; で絵文字ピッカー
            if (e.key === ';' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                this.emojiOpen = !this.emojiOpen;
                return;
            }
            // Esc で返信キャンセル
            if (e.key === 'Escape' && this.replyingTo && !this.emojiOpen) {
                this.cancelReply();
            }
        },
        autoresize(e) {
            const ta = e.target;
            ta.style.height = 'auto';
            ta.style.height = Math.min(ta.scrollHeight, 200) + 'px';
        },
        scrollToBottom() {
            const el = this.$refs.msgList;
            if (el) el.scrollTop = el.scrollHeight;
        },
        // ===== 束ねたスレッドの帯: 折りたたみ / 展開 =====
        // 折りたたみ時 (デフォルト) はチップを出さず「束ねたスレッド (N)」だけ。
        // 件数が多くなると帯がパンクするので、明示的に「展開」した時だけ全件出す。
        toggleBundleBandExpanded() {
            this.bundleBandExpanded = !this.bundleBandExpanded;
            try { localStorage.setItem('chatBundleBandExpanded', JSON.stringify(this.bundleBandExpanded)); } catch (_) {}
        },
        get visibleBundleChips() {
            return this.bundleBandExpanded ? (this.bundledThreads || []) : [];
        },
        // スクロール検知: 一番下から80px以上離れたら「最新へ」ボタンを表示
        onMessagesScroll(e) {
            const el = e.target;
            if (!el) return;
            const distance = el.scrollHeight - (el.scrollTop + el.clientHeight);
            this.scrolledUp = distance > 80;
        },

        // ===== ピン留め =====
        async togglePinSelected() {
            if (!this.selected) return;
            try {
                const r = await fetch('/api/chats/pin', {
                    method:'POST',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                    body: JSON.stringify({ type: this.selected.kind, id: this.selected.id }),
                });
                if (!r.ok) { alert('ピン操作に失敗しました'); return; }
                const d = await r.json();
                this.selected.is_pinned_chat = !!d.pinned;
                // 一覧の該当行も更新 + 並び替えのため再読込
                const list = this.selected.kind === 'thread' ? this.threads : this.rooms;
                const row = list.find(x => x.id === this.selected.id);
                if (row) row.is_pinned_chat = this.selected.is_pinned_chat;
                await this.load();
            } catch (e) { alert('通信エラー: ' + e.message); }
        },
        toggleThreadsCollapsed() {
            this.threadsCollapsed = !this.threadsCollapsed;
            try { localStorage.setItem('chatThreadsCollapsed', JSON.stringify(this.threadsCollapsed)); } catch(_) {}
        },
        // 「ルーム未設定」フィルタの on/off。 toggle ボタンから呼ぶ。
        // 状態は localStorage に永続化。
        toggleOnlyUnroomed() {
            this.onlyUnroomedThreads = !this.onlyUnroomedThreads;
            try {
                localStorage.setItem('chatOnlyUnroomedThreads', JSON.stringify(this.onlyUnroomedThreads));
            } catch(_) {}
        },
        toggleSharedRoomsCollapsed() {
            this.sharedRoomsCollapsed = !this.sharedRoomsCollapsed;
            try { localStorage.setItem('chatSharedRoomsCollapsed', JSON.stringify(this.sharedRoomsCollapsed)); } catch(_) {}
        },
        togglePersonalRoomsCollapsed() {
            this.personalRoomsCollapsed = !this.personalRoomsCollapsed;
            try { localStorage.setItem('chatPersonalRoomsCollapsed', JSON.stringify(this.personalRoomsCollapsed)); } catch(_) {}
        },
        // サイドバー一覧上の行 (選択中でない行) からピン留めをトグル
        async togglePinChatRow(type, row) {
            if (!row || !row.id) return;
            try {
                const r = await fetch('/api/chats/pin', {
                    method:'POST',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                    body: JSON.stringify({ type, id: row.id }),
                });
                if (!r.ok) { alert('ピン操作に失敗しました'); return; }
                const d = await r.json();
                row.is_pinned_chat = !!d.pinned;
                if (this.selected && this.selected.kind === type && this.selected.id === row.id) {
                    this.selected.is_pinned_chat = row.is_pinned_chat;
                }
                await this.load();
            } catch (e) { alert('通信エラー: ' + e.message); }
        },

        // ===== 元スレッド / 元メール プレビュー =====
        // トップ右の「元スレッド」ボタン: 開いてれば必ず閉じる / 閉じていれば全体ビューで開く
        async toggleOrigThread() {
            if (!this.selected || this.selected.kind !== 'thread') return;
            if (this.origEmailOpen) {
                // 開いてる状態 (全体表示/特定メール表示問わず) → 閉じる
                this.origEmailOpen = false;
                this.origEmailFocusId = null;
                return;
            }
            // 閉じていれば全体ビューで開く
            this.origEmailFocusId = null;
            this.origEmailThreadId = this.selected.id;
            this.origEmailOpen = true;
            if (!this.origEmails.length) await this.loadOrigEmails(this.selected.id);
            this.$nextTick(() => {
                if (this.$refs.origPanelBody) this.$refs.origPanelBody.scrollTop = 0;
            });
        },
        // チャット行の「元メール」ボタン: 同じメールにフォーカス中なら閉じる / それ以外は開いて該当メールにフォーカス
        async openOrigEmailById(emailId, threadId = null) {
            if (!emailId || !this.selected) return;
            // ルーム/スレッドどちらでも動くように
            const tid = threadId || (this.selected.kind === 'thread' ? this.selected.id : null);
            if (!tid) return;
            // 同じメールを開いてる状態 → 閉じる
            if (this.origEmailOpen && this.origEmailFocusId === emailId && this.origEmailThreadId === tid) {
                this.origEmailOpen = false;
                this.origEmailFocusId = null;
                return;
            }
            this.origEmailFocusId = emailId;
            this.origEmailThreadId = tid;
            this.origEmailOpen = true;
            // 別スレッドに切り替わったらメールキャッシュをクリアして再ロード
            if (this.origEmails.length === 0 || this._origLoadedThreadId !== tid) {
                this.origEmails = [];
                await this.loadOrigEmails(tid);
            }
            this.$nextTick(() => this.scrollOrigToFocus());
        },
        // ルームコメントの「元スレッド」を表示 (バンドルされたスレッドのコメントなど)
        async showOrigThreadForComment(m) {
            if (!m || !m.thread_id) return false;
            // 既に同じスレッドを表示中ならフォーカスのみ更新
            if (this.origEmailOpen && this.origEmailThreadId === m.thread_id) {
                if (m.email_id) {
                    this.origEmailFocusId = m.email_id;
                    this.$nextTick(() => this.scrollOrigToFocus());
                }
                return true;
            }
            this.origEmailFocusId = m.email_id || null;
            this.origEmailThreadId = m.thread_id;
            this.origEmailOpen = true;
            this.origEmails = [];
            await this.loadOrigEmails(m.thread_id);
            this.$nextTick(() => {
                if (this.origEmailFocusId) this.scrollOrigToFocus();
                else if (this.$refs.origPanelBody) this.$refs.origPanelBody.scrollTop = 0;
            });
            return true;
        },
        async loadOrigEmails(threadId = null) {
            const tid = threadId || (this.selected?.kind === 'thread' ? this.selected.id : null);
            if (!tid) return;
            this.origEmailLoading = true;
            this.origEmails = [];
            try {
                const r = await fetch(`/threads/${tid}`, { headers:{Accept:'application/json'} });
                if (r.ok) {
                    const d = await r.json();
                    const emails = d.emails || d.thread?.emails || [];
                    this.origEmails = [...emails].sort((a,b) => (a.received_at||'').localeCompare(b.received_at||''));
                    this._origLoadedThreadId = tid;
                    // ルームでの参照用にスレッド件名を保持
                    this.origThreadSubject = d.thread?.subject || '';
                }
            } catch (_) {}
            this.origEmailLoading = false;
        },
        // フォーカス対象のメールカードまでスクロール
        scrollOrigToFocus() {
            if (!this.origEmailFocusId) return;
            // 描画完了を待つため少し遅延 (x-for レンダリング後)
            setTimeout(() => {
                const el = document.getElementById('orig-email-' + this.origEmailFocusId);
                const container = this.$refs.origPanelBody;
                if (!el || !container) return;
                // scrollIntoView は祖先スクロールコンテナまで動かしてしまい、
                // チャット本体側 (chat-main 系) のレイアウトが崩れるケースがあるため、
                // origPanelBody だけを手動でスクロールする (smooth)
                const offset = el.getBoundingClientRect().top - container.getBoundingClientRect().top + container.scrollTop;
                container.scrollTo({ top: Math.max(0, offset - 8), behavior: 'smooth' });
            }, 80);
        },

        // ===== リアクション =====
        openReactionPicker(m, event) {
            this.reactionPickerTarget = m;
            const rect = (event.currentTarget || event.target).getBoundingClientRect();
            // ピッカーが画面右に切れないよう左寄せ
            this.reactionPickerX = Math.max(8, Math.min(window.innerWidth - 296, rect.left - 280));
            this.reactionPickerY = Math.max(8, rect.top - 8);
            this.reactionPickerOpen = true;
        },
        async pickReaction(emoji) {
            if (!this.reactionPickerTarget) { this.reactionPickerOpen = false; return; }
            const target = this.reactionPickerTarget;
            // ピッカーを閉じずに複数選択を許可。連続でクリックすればトグル動作。
            await this.toggleReaction(target, emoji);
        },
        // 指定メッセージで現在のユーザーが既にそのリアクションを付けているか
        isReactionMine(m, emoji) {
            if (!m || !emoji) return false;
            const r = (m.reactions || []).find(r => r.emoji === emoji);
            return !!(r && (r.me || r.mine));
        },
        async toggleReaction(m, emoji) {
            if (!m || !emoji) return;
            try {
                const r = await fetch(`/api/comments/${m.id}/reactions`, {
                    method:'POST',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                    body: JSON.stringify({ emoji }),
                });
                if (!r.ok) return;
                const d = await r.json();
                m.reactions = d.reactions || [];
            } catch (_) {}
        },

        // 元スレッドチャット プレビューパネルのリサイズ (左端ドラッグ)
        startResizeThreadChatPreview(e) {
            const startX = e.clientX, startW = this.threadChatPreviewWidth;
            this.threadChatPreviewResizing = true;
            document.body.classList.add('orig-resizing');
            const onMove = (me) => {
                const delta = startX - me.clientX;
                this.threadChatPreviewWidth = Math.max(280, Math.min(window.innerWidth - 360, startW + delta));
            };
            const onUp = () => {
                this.threadChatPreviewResizing = false;
                document.body.classList.remove('orig-resizing');
                try { localStorage.setItem('chatPreviewPanelWidth', String(this.threadChatPreviewWidth)); } catch (_) {}
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onUp);
        },

        // ===== 元スレッドパネル / チャット境界のリサイズ =====
        startResizeOrigEmail(e) {
            const startX = e.clientX, startW = this.origEmailPanelWidth;
            this.origResizing = true;
            document.body.classList.add('orig-resizing');
            // ポインタイベントを使ってマウス/タッチ/ペン対応
            const onMove = (me) => {
                // 左へドラッグ = パネル拡大 / 右へドラッグ = パネル縮小
                const delta = startX - me.clientX;
                this.origEmailPanelWidth = Math.max(280, Math.min(window.innerWidth - 360, startW + delta));
            };
            const onUp = () => {
                this.origResizing = false;
                document.body.classList.remove('orig-resizing');
                localStorage.setItem('chatOrigEmailPanelWidth', String(this.origEmailPanelWidth));
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
            };
            // mousemove と pointermove を両方リッスン (環境差を吸収)
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onUp);
        },

        // サイドバーの折りたたみトグル (永続化)
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            try { localStorage.setItem('chatSidebarCollapsed', JSON.stringify(this.sidebarCollapsed)); } catch (_) {}
        },

        // ===== サイドバーのリサイズ =====
        startResize(e) {
            const startX = e.clientX, startW = this.panelWidth;
            const prevUS = document.body.style.userSelect;
            document.body.style.userSelect = 'none'; document.body.style.cursor = 'col-resize';
            const onMove = (me) => { this.panelWidth = Math.max(200, Math.min(500, startW + (me.clientX - startX))); };
            const onUp = () => {
                localStorage.setItem('chatHubPanelWidth', String(this.panelWidth));
                document.body.style.userSelect = prevUS; document.body.style.cursor = '';
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },
    };
}
</script>
@endsection
