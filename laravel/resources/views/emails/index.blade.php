@extends('layouts.app')
@section('title', 'Rice Mail - 受信トレイ')

@section('css')
<style>
    /* メール画面はナビバー分だけ引いた高さに固定し、ボタンが切れないようにする */
    .content-header { display: none !important; }
    .content, .content > .container-fluid {
        padding: 0 !important;
        max-width: 100% !important;
        height: calc(100vh - 3.5rem); /* AdminLTE navbar height */
        overflow: hidden;
    }

    /* ===== メール画面 左サイドバー: ルーム一覧 (チャット画面と同じデザイン) ===== */
    .mail-rooms-sidebar {
        background:#ffffff; color:#374151;
        border-right:1px solid #e5e7eb;
        transition: width 0.2s ease;
        display: flex; flex-direction: column;
        position: relative;
        flex-shrink: 0;
    }
    .mail-rooms-sidebar.is-collapsed { overflow:hidden; }
    .mail-rooms-sidebar.is-collapsed > *:not(.mail-rooms-collapse-toggle) { display:none !important; }
    .mail-rooms-head { padding:6px 34px 6px 10px; border-bottom:1px solid #e5e7eb; }
    .mail-rooms-head h3 { color:#111827; font-size:12px; font-weight:700; margin:0; }
    .mail-rooms-section {
        padding: 8px 10px 2px; font-size:10px; font-weight:800;
        color:#9ca3af; text-transform:uppercase; letter-spacing:0.08em;
    }
    /* 50 音グループ見出し (共有ルームのあいうえお順モード時のみ表示) */
    .mail-kana-header { transition: background 0.15s; }
    .mail-kana-header:hover { background:#f9fafb; color:#6b7280; }
    body.theme-dark .mail-kana-header { color:#9aa0a6 !important; }
    body.theme-dark .mail-kana-header:hover { background:#2b2d31 !important; color:#dcddde !important; }
    .mail-room-item {
        color:#4b5563; padding:4px 8px; border-radius:6px; cursor:pointer;
        display:flex; align-items:center; gap:6px; margin:1px 6px;
        font-size:12px; min-height:26px;
    }
    .mail-room-item:hover { background:#f3f4f6; color:#111827; }
    .mail-room-item.active {
        background:#eff6ff; color:#1d4ed8; font-weight:700;
        border-left:3px solid #2563eb; padding-left:5px;
    }
    .mail-room-item .hash { color:#9ca3af; font-weight:700; }
    .mail-room-item.active .hash { color:#1d4ed8; }
    .mail-room-item .name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .mail-rooms-collapse-toggle {
        position:absolute; top:6px; right:6px; z-index:20;
        width:22px; height:22px;
        background:#ffffff; border:1px solid #e5e7eb; border-radius:50%;
        color:#6b7280; font-size:10px; cursor:pointer;
        display:inline-flex; align-items:center; justify-content:center;
        box-shadow:0 1px 3px rgba(0,0,0,0.08); padding:0;
        transition: background 0.15s, color 0.15s, border-color 0.15s;
    }
    .mail-rooms-collapse-toggle:hover { background:#f3f4f6; color:#111827; }
    /* ダークモード: 白丸 + 灰枠だとサイドバー濃灰背景に対し明るすぎる
       (catch-all の [style*="background:#ffffff"] では拾えないので class で個別指定) */
    html.theme-dark .mail-rooms-collapse-toggle {
        background: var(--rd-bg-3) !important;
        border-color: var(--rd-border) !important;
        color: var(--rd-text-mute) !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.45) !important;
    }
    html.theme-dark .mail-rooms-collapse-toggle:hover {
        background: var(--rd-bg-hover) !important;
        border-color: var(--rd-border) !important;
        color: #ffffff !important;
    }

    /* ルーム行のホバー時削除ボタン */
    .mail-room-item .mail-room-del-btn {
        margin-left:auto;
        background:transparent; border:none; color:#9ca3af; padding:2px 4px;
        border-radius:4px; cursor:pointer; opacity:0;
        transition:opacity 0.15s, background-color 0.15s, color 0.15s;
        display:inline-flex; align-items:center; justify-content:center;
        flex-shrink:0;
    }
    .mail-room-item:hover .mail-room-del-btn { opacity:1; }
    .mail-room-item .mail-room-del-btn:hover { background:#fee2e2; color:#dc2626; }
    /* スレッド行のピン留めボタン (常時表示・ピン留め時は色付き) */
    .mail-thread-pin-btn {
        background:transparent; border:none; padding:2px 4px;
        border-radius:4px; cursor:pointer;
        display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
        transition:background-color 0.15s, color 0.15s, transform 0.1s;
    }
    .mail-thread-pin-btn:hover { background:#fef3c7; transform:scale(1.15); }
    /* ルームに追加リンクボタン (ホバー時表示) */
    .mail-room-item .mail-room-link-btn {
        background:transparent; border:none; color:#9ca3af; padding:2px 4px;
        border-radius:4px; cursor:pointer; opacity:0;
        transition:opacity 0.15s, background-color 0.15s, color 0.15s;
        display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
    }
    .mail-room-item:hover .mail-room-link-btn { opacity:1; }
    .mail-room-item .mail-room-link-btn:hover { background:#eff6ff; color:#2563eb; }
    /* 自分が非表示にしている行: 薄く + 取り消し線 */
    .mail-room-item.is-hidden { opacity:0.55; }
    .mail-room-item.is-hidden .name { text-decoration:line-through; color:#6b7280; }

    /* スレッド内検索のマッチハイライト */
    mark.thread-search-mark {
        background: #fde68a; color: #78350f;
        padding: 0 1px; border-radius: 2px; font-weight: 700;
    }
    mark.thread-search-mark.is-current {
        background: #f59e0b; color: #ffffff;
        outline: 2px solid #ea580c;
    }
    /* 現在の検索対象メールに当てる一瞬のフラッシュ枠 */
    @keyframes searchFlash {
        0%   { box-shadow: 0 0 0 0 rgba(59,130,246,.0); }
        25%  { box-shadow: 0 0 0 4px rgba(59,130,246,.45); }
        100% { box-shadow: 0 0 0 0 rgba(59,130,246,.0); }
    }
    .search-flash { animation: searchFlash 0.8s ease-out; }
    /* バンドル先スレッドの「メール件数」バッジ — status 別に色分け.
       ・受信 (inbox) → 青 (青系で「新着・要対応」を表現)
       ・保留 (hold)  → 琥珀 (黄味で「待機・後で確認」を表現)
       ・承認待ち     → 橙 (青と保留の中間色)
       要望: 「受信、保留でバッチの色を変えてください」 */
    .mail-room-item .badge-email-unread {
        background:#3b82f6; color:#fff; font-size:10px; font-weight:800;
        border-radius:8px; min-width:18px; height:18px; padding:0 5px;
        display:inline-flex; align-items:center; justify-content:center;
        gap:3px; line-height:1; flex-shrink:0;
    }
    .mail-room-item .badge-email-unread i { font-size:8px; }
    .mail-room-item.active .badge-email-unread { background:#1d4ed8; }
    /* 保留バッジ: 琥珀色 (Tailwind amber-500) */
    .mail-room-item .badge-email-hold {
        background:#f59e0b; color:#fff; font-size:10px; font-weight:800;
        border-radius:8px; min-width:18px; height:18px; padding:0 5px;
        display:inline-flex; align-items:center; justify-content:center;
        gap:3px; line-height:1; flex-shrink:0;
    }
    .mail-room-item .badge-email-hold i { font-size:8px; }
    .mail-room-item.active .badge-email-hold { background:#d97706; }
    /* 承認待ち: 橙系 (Tailwind orange-500) */
    .mail-room-item .badge-email-pending {
        background:#f97316; color:#fff; font-size:10px; font-weight:800;
        border-radius:8px; min-width:18px; height:18px; padding:0 5px;
        display:inline-flex; align-items:center; justify-content:center;
        gap:3px; line-height:1; flex-shrink:0;
    }
    .mail-room-item .badge-email-pending i { font-size:8px; }
    .mail-room-item.active .badge-email-pending { background:#c2410c; }
    /* チャット未読 / メンションバッジ。chats 画面と統一感のあるオレンジ (mention) / グレー (count) */
    .mail-room-item .badge-mention {
        background:#f59e0b; color:#fff; font-size:10px; font-weight:800;
        border-radius:8px; min-width:18px; height:18px; padding:0 5px;
        display:inline-flex; align-items:center; justify-content:center;
        line-height:1; flex-shrink:0;
    }
    .mail-room-item .badge-count {
        background:#e5e7eb; color:#4b5563; font-size:10px; font-weight:700;
        border-radius:8px; min-width:18px; height:16px; padding:0 5px;
        display:inline-flex; align-items:center; justify-content:center;
        line-height:1; flex-shrink:0;
    }
    .mail-room-item.active .badge-count { background:#2563eb; color:#fff; }
    body.theme-dark .mail-room-item .badge-count { background:#3b3f47; color:#dcddde; }
    body.theme-dark .mail-room-item.active .badge-count { background:#1d4ed8; color:#fff; }

    /* ===== メール一覧の同期ボタン (failure 視認用) =====
       Tailwind の JIT に依存せず inline CSS で確実に色を出す。 */
    .sync-btn {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px; height: 36px;
        border-radius: 8px;
        border: none;
        background: transparent;
        cursor: pointer;
        transition: background-color .15s, color .15s, box-shadow .15s;
    }
    .sync-btn-normal { color:#9ca3af; }
    .sync-btn-normal:hover { color:#2563eb; background:#f3f4f6; }
    /* 失敗時: 赤背景 + パルスアニメ + リング */
    .sync-btn-error {
        background-color:#dc2626 !important;
        color:#ffffff !important;
        box-shadow:0 0 0 3px rgba(248,113,113,0.45);
        animation: sync-btn-pulse 1.4s ease-in-out infinite;
    }
    .sync-btn-error:hover { background-color:#b91c1c !important; }
    @keyframes sync-btn-pulse {
        0%, 100% { box-shadow:0 0 0 3px rgba(248,113,113,0.45); }
        50%      { box-shadow:0 0 0 6px rgba(248,113,113,0.10); }
    }
    /* 部分成功 警告: 黄背景 + リング */
    .sync-btn-warning {
        background-color:#f59e0b !important;
        color:#ffffff !important;
        box-shadow:0 0 0 3px rgba(252,211,77,0.45);
    }
    .sync-btn-warning:hover { background-color:#d97706 !important; }
    /* 連続失敗カウントバッジ */
    .sync-btn-badge {
        position:absolute; top:-4px; right:-4px;
        display:inline-flex; align-items:center; justify-content:center;
        min-width:18px; height:18px; padding:0 4px;
        background:#7f1d1d; color:#ffffff;
        font-size:10px; font-weight:900; line-height:1;
        border-radius:9999px;
        border:1.5px solid #ffffff;
    }
    /* ダークモード調整 */
    html.theme-dark .sync-btn-normal { color: var(--rd-text-mute) !important; }
    html.theme-dark .sync-btn-normal:hover { background: var(--rd-bg-hover) !important; color:#ffffff !important; }
    html.theme-dark .sync-btn-badge { border-color: var(--rd-bg-2); }

    /* ===== 複数選択アクションバー (薄いヘッダー + 縦ドロップダウンメニュー) ===== */
    .bulk-action-bar {
        position: absolute; inset: 0 0 auto 0; z-index: 100;
        background: linear-gradient(135deg,#2563eb 0%,#3b82f6 100%);
        color: #fff;
        display: flex; align-items: center; gap: 10px;
        padding: 6px 10px;
        box-shadow: 0 2px 8px rgba(37,99,235,0.22);
        animation: slideDown 0.2s ease-out;
        height: 40px;
    }
    @keyframes slideDown { from { transform: translateY(-100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .bulk-action-info {
        flex: 1; min-width: 0;
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 11px; font-weight: 700; white-space: nowrap;
    }
    .bulk-action-info i { font-size: 13px; }
    .bulk-action-info strong { font-size: 14px; font-weight: 900; }
    .bulk-action-menu-trigger {
        display: inline-flex; align-items: center; gap: 5px;
        background: rgba(255,255,255,0.18); color: #fff;
        border: 1px solid rgba(255,255,255,0.25);
        font-size: 11px; font-weight: 700;
        padding: 5px 10px; border-radius: 6px;
        cursor: pointer; white-space: nowrap;
        transition: background-color 0.12s;
    }
    .bulk-action-menu-trigger:hover,
    .bulk-action-menu-trigger.is-open { background: rgba(255,255,255,0.3); }
    .bulk-action-menu {
        position: absolute; top: calc(100% + 4px); right: 0;
        z-index: 110;
        min-width: 200px;
        background: #ffffff; color: #0f172a;
        border: 1px solid #e2e8f0; border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.18);
        padding: 6px;
        overflow: hidden;
    }
    .bulk-action-menu-section { display: flex; flex-direction: column; gap: 1px; }
    .bulk-action-menu-head {
        margin: 6px 8px 4px; font-size: 9px; font-weight: 800;
        color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;
    }
    .bulk-action-menu-item {
        display: flex; align-items: center; gap: 8px;
        background: transparent; border: none; color: #0f172a;
        padding: 7px 10px; border-radius: 6px;
        font-size: 12px; font-weight: 600; cursor: pointer; text-align: left;
        width: 100%;
        transition: background-color 0.1s;
    }
    .bulk-action-menu-item:hover { background: #f1f5f9; }
    .bulk-action-menu-item i { width: 14px; text-align: center; font-size: 11px; }
    .bulk-action-menu-item-danger { color: #dc2626; }
    .bulk-action-menu-item-danger:hover { background: #fef2f2; }
    .bulk-action-menu-item-danger i { color: #dc2626 !important; }
    .bulk-action-menu-divider { height: 1px; background: #f1f5f9; margin: 4px 6px; }
    .bulk-action-close {
        background: transparent; color: #fff;
        border: 1px solid rgba(255,255,255,0.25);
        width: 28px; height: 28px;
        border-radius: 6px; cursor: pointer; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .bulk-action-close:hover { background: rgba(255,255,255,0.18); }
    /* ダークモード */
    html.theme-dark .bulk-action-menu {
        background: var(--rd-bg-2) !important;
        border-color: var(--rd-border) !important;
        color: var(--rd-text);
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    html.theme-dark .bulk-action-menu-head { color: var(--rd-text-dim) !important; }
    html.theme-dark .bulk-action-menu-item { color: var(--rd-text) !important; }
    html.theme-dark .bulk-action-menu-item:hover { background: var(--rd-bg-hover) !important; }
    html.theme-dark .bulk-action-menu-item-danger { color: #ff9a9c !important; }
    html.theme-dark .bulk-action-menu-item-danger:hover { background: rgba(237,66,69,0.18) !important; }
    html.theme-dark .bulk-action-menu-divider { background: var(--rd-border) !important; }
    html.theme-dark .bulk-action-bar { background: linear-gradient(135deg, var(--rd-bg-3), var(--rd-bg-2)) !important; }

    /* ===== 統一モーダル (ルーム作成 / ルームに追加) ===== */
    .rice-modal-backdrop {
        position: fixed; inset: 0;
        background: rgba(15,23,42,0.55); backdrop-filter: blur(2px);
        z-index: 9998;
    }
    .rice-modal {
        position: fixed; top: 50%; left: 50%;
        transform: translate(-50%,-50%);
        background: #ffffff;
        border-radius: 14px;
        display: flex; flex-direction: column;
        box-shadow: 0 24px 60px rgba(0,0,0,0.32);
        z-index: 9999;
        overflow: hidden;
    }
    .rice-modal-head {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid #f1f5f9;
    }
    .rice-modal-head-icon {
        width: 36px; height: 36px; flex-shrink: 0;
        border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 14px;
    }
    .rice-modal-head-text { flex: 1; min-width: 0; }
    .rice-modal-head-text h3 { margin: 0; font-size: 15px; font-weight: 800; color: #0f172a; line-height: 1.3; }
    .rice-modal-head-text p { margin: 3px 0 0; font-size: 11px; color: #64748b; line-height: 1.5; }
    .rice-modal-close {
        background: transparent; border: none; color: #94a3b8;
        width: 30px; height: 30px; border-radius: 8px; cursor: pointer; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 13px;
    }
    .rice-modal-close:hover { background: #f1f5f9; color: #334155; }
    .rice-modal-body { padding: 18px; }
    .rice-modal-foot {
        display: flex; justify-content: flex-end; gap: 8px;
        padding: 12px 18px;
        border-top: 1px solid #f1f5f9;
        background: #f8fafc;
    }
    .rice-field { margin-bottom: 14px; }
    .rice-field:last-child { margin-bottom: 0; }
    .rice-field > label {
        display: block; font-size: 12px; font-weight: 700; color: #475569;
        margin-bottom: 6px;
    }
    .rice-input {
        width: 100%;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 9px 12px;
        font-size: 13px;
        color: #0f172a;
        outline: none;
        transition: border-color .15s, box-shadow .15s, background-color .15s;
    }
    .rice-input:focus { background:#fff; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.18); }
    .rice-radio-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .rice-radio-card {
        position: relative;
        display: flex; align-items: center; gap: 10px;
        background: #fff; border: 2px solid #e2e8f0;
        border-radius: 10px; padding: 12px;
        text-align: left; cursor: pointer;
        transition: border-color .15s, background-color .15s;
    }
    .rice-radio-card:hover { background: #f8fafc; }
    .rice-radio-card.is-selected { border-color: #2563eb; background: #eff6ff; }
    .rice-radio-card-icon {
        width: 32px; height: 32px; flex-shrink: 0;
        border-radius: 8px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 13px;
    }
    .rice-radio-card-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .rice-radio-card-body strong { font-size: 12px; font-weight: 700; color: #0f172a; }
    .rice-radio-card-body span { font-size: 10px; color: #64748b; line-height: 1.4; }
    .rice-radio-check { position: absolute; top: 6px; right: 6px; color: #2563eb; font-size: 14px; }
    .rice-btn-primary {
        background: linear-gradient(135deg,#2563eb,#3b82f6); color: #fff;
        border: none; padding: 8px 16px; border-radius: 8px;
        font-size: 13px; font-weight: 700; cursor: pointer;
        display: inline-flex; align-items: center; gap: 6px;
        box-shadow: 0 2px 6px rgba(37,99,235,0.25);
        transition: transform .1s, box-shadow .15s;
    }
    .rice-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(37,99,235,0.35); }
    .rice-btn-primary:disabled { transform: none !important; box-shadow: none !important; }
    .rice-btn-secondary {
        background: #fff; color: #475569;
        border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 8px;
        font-size: 13px; font-weight: 600; cursor: pointer;
    }
    .rice-btn-secondary:hover { background: #f8fafc; color: #0f172a; }

    /* スレッドをルームに追加モーダル用 */
    .rice-room-create-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
    .rice-room-create-card {
        display: flex; align-items: center; gap: 10px;
        background: #f8fafc; border: 1px dashed #cbd5e1;
        border-radius: 10px; padding: 12px;
        text-align: left; cursor: pointer;
        transition: border-color .15s, background-color .15s, transform .1s;
    }
    .rice-room-create-card:hover { background:#eff6ff; border-color:#3b82f6; transform: translateY(-1px); }
    .rice-room-create-icon {
        width: 32px; height: 32px; flex-shrink: 0;
        border-radius: 8px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 13px;
    }
    .rice-room-create-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .rice-room-create-text strong { font-size: 12px; font-weight: 700; color: #0f172a; }
    .rice-room-create-text span { font-size: 10px; color: #64748b; line-height: 1.4; }

    .rice-room-list-section { margin-top: 6px; }
    .rice-room-list-head {
        margin: 12px 0 6px;
        font-size: 11px; font-weight: 700; color: #64748b;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .rice-room-list-head-icon { font-size: 10px; color: #3b82f6; }
    .rice-room-list-item {
        width: 100%;
        display: flex; align-items: center; gap: 8px;
        background: transparent; border: 1px solid transparent;
        border-radius: 8px; padding: 8px 10px;
        font-size: 12px; font-weight: 600; color: #0f172a;
        cursor: pointer; text-align: left;
        transition: background-color .12s, border-color .12s;
    }
    .rice-room-list-item:hover { background: #eff6ff; border-color: #bfdbfe; }
    .rice-room-list-hash { color: #94a3b8; font-weight: 700; width: 14px; text-align: center; }
    .rice-room-list-lock { font-size: 10px; color: #a78bfa; width: 14px; text-align: center; }
    .rice-room-list-empty { text-align: center; color: #94a3b8; font-size: 11px; padding: 8px; margin: 0; }

    /* ===== ダークモード対応 ===== */
    html.theme-dark .rice-modal { background: var(--rd-bg-2) !important; border: 1px solid var(--rd-border) !important; }
    html.theme-dark .rice-modal-head { border-bottom-color: var(--rd-border) !important; }
    html.theme-dark .rice-modal-head-text h3 { color: var(--rd-text) !important; }
    html.theme-dark .rice-modal-head-text p { color: var(--rd-text-dim) !important; }
    html.theme-dark .rice-modal-close { color: var(--rd-text-dim) !important; }
    html.theme-dark .rice-modal-close:hover { background: var(--rd-bg-hover) !important; color: #fff !important; }
    html.theme-dark .rice-modal-foot { background: var(--rd-bg-3) !important; border-top-color: var(--rd-border) !important; }
    html.theme-dark .rice-field > label { color: var(--rd-text-mute) !important; }
    html.theme-dark .rice-input { background: var(--rd-bg-3) !important; border-color: var(--rd-border) !important; color: var(--rd-text) !important; }
    html.theme-dark .rice-input:focus { background: var(--rd-bg-3) !important; border-color: var(--rd-brand) !important; box-shadow: 0 0 0 3px rgba(88,101,242,0.25) !important; }
    html.theme-dark .rice-radio-card { background: var(--rd-bg-3) !important; border-color: var(--rd-border) !important; }
    html.theme-dark .rice-radio-card:hover { background: var(--rd-bg-hover) !important; }
    html.theme-dark .rice-radio-card.is-selected { background: rgba(88,101,242,0.15) !important; border-color: var(--rd-brand) !important; }
    html.theme-dark .rice-radio-card-body strong { color: var(--rd-text) !important; }
    html.theme-dark .rice-radio-card-body span { color: var(--rd-text-dim) !important; }
    html.theme-dark .rice-radio-check { color: var(--rd-brand) !important; }
    html.theme-dark .rice-btn-primary { background: linear-gradient(135deg, var(--rd-brand), var(--rd-brand-h)) !important; box-shadow: 0 2px 6px rgba(0,0,0,0.4) !important; }
    html.theme-dark .rice-btn-secondary { background: var(--rd-bg-3) !important; color: var(--rd-text) !important; border-color: var(--rd-border) !important; }
    html.theme-dark .rice-btn-secondary:hover { background: var(--rd-bg-hover) !important; }
    html.theme-dark .rice-room-create-card { background: var(--rd-bg-3) !important; border-color: var(--rd-border) !important; }
    html.theme-dark .rice-room-create-card:hover { background: var(--rd-bg-hover) !important; border-color: var(--rd-brand) !important; }
    html.theme-dark .rice-room-create-text strong { color: var(--rd-text) !important; }
    html.theme-dark .rice-room-create-text span { color: var(--rd-text-dim) !important; }
    html.theme-dark .rice-room-list-head { color: var(--rd-text-dim) !important; }
    html.theme-dark .rice-room-list-item { color: var(--rd-text) !important; }
    html.theme-dark .rice-room-list-item:hover { background: var(--rd-bg-hover) !important; border-color: var(--rd-border) !important; }
    html.theme-dark .rice-room-list-hash,
    html.theme-dark .rice-room-list-lock { color: var(--rd-text-dim) !important; }
    html.theme-dark .rice-room-list-empty { color: var(--rd-text-dim) !important; }

    /* サイドバー右端のリサイズハンドル */
    .mail-rooms-resize {
        position:absolute; top:0; right:0; width:4px; height:100%;
        cursor:col-resize; z-index:5;
        background:transparent; transition:background-color 0.15s;
    }
    .mail-rooms-resize:hover, .mail-rooms-resize.is-resizing { background:#3b82f6; }
    body.mail-rooms-resizing { cursor:col-resize !important; user-select:none !important; }
</style>
@endsection

@section('content')
<div class="flex bg-white overflow-hidden text-gray-800 font-sans"
     style="height:calc(100vh - 3.5rem)"
     x-data="emailApp()"
     {{-- グローバル navbar の「?」ボタンから dispatch される。 ショートカットヘルプを開く. --}}
     @open-shortcuts-help.window="shortcutsModalOpen = true"
     x-cloak>

    {{-- ルーム作成モーダル --}}
    <div x-show="mailCreateRoomOpen" x-cloak>
        <div @click="mailCreateRoomOpen = false" class="rice-modal-backdrop"></div>
        <div class="rice-modal" style="width:480px;max-width:94vw;">
            <div class="rice-modal-head">
                <div class="rice-modal-head-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-plus"></i></div>
                <div class="rice-modal-head-text">
                    <h3>新しいルームを作成</h3>
                    <p>関係者とまとめて共有する場や、自分用のメモ用ルームを作れます。</p>
                </div>
                <button @click="mailCreateRoomOpen = false" class="rice-modal-close" title="閉じる"><i class="fas fa-times"></i></button>
            </div>
            <div class="rice-modal-body">
                <div class="rice-field">
                    <label>ルーム名</label>
                    <input type="text" x-model="mailNewRoomName" @keydown.enter="submitMailCreateRoom()"
                           placeholder="例: 案件A 進行管理"
                           class="rice-input" autofocus>
                    {{-- 重複防止: 名前と部分一致する既存ルームを最大 8 件提示。
                         入力したキーワードに部分一致する既存ルームを表示し、重複作成を防ぐ。 --}}
                    <template x-if="mailSimilarRoomsForNewName.length > 0">
                        <div style="margin-top:8px;border:1px solid #fde68a;background:#fffbeb;border-radius:6px;padding:8px 10px;">
                            <p style="margin:0 0 4px;font-size:10px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.04em;">
                                <i class="fas fa-info-circle" style="margin-right:4px;"></i>似た名前のルームがあります (クリックで開く)
                            </p>
                            <div style="display:flex;flex-direction:column;gap:2px;max-height:140px;overflow-y:auto;">
                                <template x-for="r in mailSimilarRoomsForNewName" :key="'mail-sim-' + r.id">
                                    <button type="button" @click="selectExistingMailRoomFromCreate(r)"
                                            style="display:flex;align-items:center;gap:6px;width:100%;text-align:left;background:#fff;border:1px solid #fde68a;border-radius:4px;padding:5px 8px;cursor:pointer;font-size:12px;color:#1f2937;"
                                            onmouseover="this.style.backgroundColor='#fef3c7';"
                                            onmouseout="this.style.backgroundColor='#fff';">
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
                </div>
                <div class="rice-field">
                    <label>公開範囲</label>
                    <div class="rice-radio-grid">
                        <button type="button" @click="mailNewRoomIsPrivate = false"
                                :class="!mailNewRoomIsPrivate ? 'rice-radio-card is-selected' : 'rice-radio-card'">
                            <div class="rice-radio-card-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-globe"></i></div>
                            <div class="rice-radio-card-body">
                                <strong>全員共有</strong>
                                <span>他のメンバーにも表示されます</span>
                            </div>
                            <i class="fas fa-check-circle rice-radio-check" x-show="!mailNewRoomIsPrivate"></i>
                        </button>
                        <button type="button" @click="mailNewRoomIsPrivate = true"
                                :class="mailNewRoomIsPrivate ? 'rice-radio-card is-selected' : 'rice-radio-card'">
                            <div class="rice-radio-card-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-lock"></i></div>
                            <div class="rice-radio-card-body">
                                <strong>個人用</strong>
                                <span>あなただけに表示されます</span>
                            </div>
                            <i class="fas fa-check-circle rice-radio-check" x-show="mailNewRoomIsPrivate"></i>
                        </button>
                    </div>
                </div>
                {{-- 親ルーム (フォルダ構成). 検索ドロップダウン形式 — 名前入力で候補絞り込み.
                     未選択 = ルート (親なし). --}}
                <div class="rice-field">
                    <label>親ルーム (任意)</label>
                    <div style="position:relative;" x-data="{ open: false }"
                         @click.outside="open = false">
                        <input type="text"
                               :value="mailNewRoomParentLabel"
                               @focus="open = true"
                               @input="mailNewRoomParentSearch = $event.target.value; mailNewRoomParentLabel = $event.target.value; open = true"
                               placeholder="(なし = ルート) ルーム名で検索…"
                               class="rice-input">
                        <button type="button" x-show="mailNewRoomParentId"
                                @click.stop="mailNewRoomParentId = null; mailNewRoomParentLabel = ''; mailNewRoomParentSearch = ''"
                                title="親を解除 (ルートにする)"
                                style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ca3af;cursor:pointer;font-size:12px;">
                            <i class="fas fa-times-circle"></i>
                        </button>
                        <div x-show="open && mailParentSearchResults.length > 0" x-cloak
                             style="position:absolute;left:0;right:0;top:100%;margin-top:4px;max-height:240px;overflow-y:auto;background:#ffffff;border:1px solid #d1d5db;border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,0.12);z-index:50;">
                            <template x-for="r in mailParentSearchResults" :key="'newroom-parent-' + r.id">
                                <button type="button"
                                        @click.stop="mailNewRoomParentId = r.id; mailNewRoomParentLabel = r.name; mailNewRoomParentSearch = ''; open = false"
                                        style="display:flex;align-items:center;gap:8px;width:100%;text-align:left;padding:8px 12px;background:#fff;border:0;border-bottom:1px solid #f3f4f6;cursor:pointer;font-size:12px;color:#1f2937;"
                                        onmouseover="this.style.backgroundColor='#f9fafb';"
                                        onmouseout="this.style.backgroundColor='#fff';">
                                    <i :class="r.is_private ? 'fas fa-lock' : 'fas fa-hashtag'"
                                       style="font-size:10px;"
                                       :style="r.is_private ? 'color:#a78bfa;' : 'color:#6b7280;'"></i>
                                    <span style="flex:1;" x-text="r.name"></span>
                                    <span style="font-size:9px;color:#9ca3af;font-weight:700;text-transform:uppercase;"
                                          x-text="r.is_private ? '個人' : '共有'"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <p style="font-size:10px;color:#6b7280;margin-top:4px;line-height:1.4;">
                        ここに指定したルームの中 (フォルダ配下) にこのルームが作られます。空ならルート扱い.
                        最大 <span x-text="ROOM_MAX_DEPTH"></span> 階層まで.
                    </p>
                </div>
            </div>
            <div class="rice-modal-foot">
                <button @click="mailCreateRoomOpen = false" class="rice-btn-secondary">キャンセル</button>
                <button @click="submitMailCreateRoom()"
                        :disabled="!mailNewRoomName?.trim() || mailCreatingRoom"
                        class="rice-btn-primary"
                        :style="(!mailNewRoomName?.trim() || mailCreatingRoom) ? 'opacity:0.5;cursor:not-allowed;' : ''">
                    <i class="fas" :class="mailCreatingRoom ? 'fa-spinner fa-spin' : 'fa-plus'"></i>
                    ルームを作成
                </button>
            </div>
        </div>
    </div>

    {{-- ルーム編集モーダル — 名前と公開範囲を変更 --}}
    <div x-show="mailEditRoomOpen" x-cloak>
        <div @click="mailEditRoomOpen = false" class="rice-modal-backdrop"></div>
        <div class="rice-modal" style="width:480px;max-width:94vw;">
            <div class="rice-modal-head">
                <div class="rice-modal-head-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-pen"></i></div>
                <div class="rice-modal-head-text">
                    <h3>ルームを編集</h3>
                    <p>名前と公開範囲を変更できます。</p>
                </div>
                <button @click="mailEditRoomOpen = false" class="rice-modal-close" title="閉じる"><i class="fas fa-times"></i></button>
            </div>
            <div class="rice-modal-body">
                <div class="rice-field">
                    <label>ルーム名</label>
                    <input type="text" x-model="mailEditRoomName" @keydown.enter="submitMailEditRoom()"
                           placeholder="ルーム名" class="rice-input" autofocus>
                </div>
                {{-- 公開範囲: 共有ルームは全員変更可, 個人ルームは作成者のみ.
                     mailEditPublicityAllowed が true の時だけ編集 UI を出す. --}}
                <template x-if="mailEditPublicityAllowed">
                    <div class="rice-field">
                        <label>公開範囲</label>
                        <div class="rice-radio-grid">
                            <button type="button" @click="mailEditRoomIsPrivate = false"
                                    :class="!mailEditRoomIsPrivate ? 'rice-radio-card is-selected' : 'rice-radio-card'">
                                <div class="rice-radio-card-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-globe"></i></div>
                                <div class="rice-radio-card-body"><strong>全員共有</strong><span>他のメンバーにも表示</span></div>
                                <i class="fas fa-check-circle rice-radio-check" x-show="!mailEditRoomIsPrivate"></i>
                            </button>
                            <button type="button" @click="mailEditRoomIsPrivate = true"
                                    :class="mailEditRoomIsPrivate ? 'rice-radio-card is-selected' : 'rice-radio-card'">
                                <div class="rice-radio-card-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-lock"></i></div>
                                <div class="rice-radio-card-body"><strong>個人用</strong><span>あなただけに表示</span></div>
                                <i class="fas fa-check-circle rice-radio-check" x-show="mailEditRoomIsPrivate"></i>
                            </button>
                        </div>
                    </div>
                </template>
                <template x-if="!mailEditPublicityAllowed">
                    <p style="font-size:11px;color:#6b7280;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:6px;padding:8px 10px;">
                        <i class="fas fa-info-circle" style="margin-right:4px;color:#9ca3af;"></i>
                        個人ルームの公開範囲は作成者のみ変更できます。
                    </p>
                </template>

                {{-- ==================== 親ルーム (フォルダ構成) ==================== --}}
                <div style="margin-top:16px;padding-top:12px;border-top:1px solid #e5e7eb;">
                    <label style="font-size:12px;font-weight:800;color:#374151;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-folder-tree" style="color:#7c3aed;font-size:11px;"></i>
                        親ルーム (フォルダ構成)
                    </label>
                    <p style="font-size:10px;color:#6b7280;margin:4px 0 8px;line-height:1.5;">
                        このルームを別のルームの中に入れると、親ルームを開いたとき配下のスレッドとチャットもまとめて見えます。
                    </p>
                    {{-- 検索付きドロップダウン. mailEditParentSearch を入力 → availableParentRoomsSearched に絞り込み --}}
                    <div style="display:flex;gap:6px;align-items:flex-start;">
                        <div style="flex:1;position:relative;" x-data="{ open: false }" @click.outside="open = false">
                            <input type="text"
                                   :value="mailEditParentLabel"
                                   @focus="open = true"
                                   @input="mailEditParentSearch = $event.target.value; mailEditParentLabel = $event.target.value; open = true"
                                   placeholder="(なし = ルート) ルーム名で検索…"
                                   style="width:100%;border:1px solid #d1d5db;border-radius:6px;padding:5px 28px 5px 10px;font-size:11px;background:#fff;color:#1f2937;outline:none;">
                            <button type="button" x-show="mailEditRoomParentId"
                                    @click.stop="mailEditRoomParentId = ''; mailEditParentLabel = ''; mailEditParentSearch = ''"
                                    title="親を解除 (ルートにする)"
                                    style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ca3af;cursor:pointer;font-size:11px;">
                                <i class="fas fa-times-circle"></i>
                            </button>
                            <div x-show="open && availableParentRoomsSearched.length > 0" x-cloak
                                 style="position:absolute;left:0;right:0;top:100%;margin-top:4px;max-height:240px;overflow-y:auto;background:#ffffff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 8px 24px rgba(15,23,42,0.12);z-index:50;">
                                <template x-for="r in availableParentRoomsSearched" :key="'edit-parent-' + r.id">
                                    <button type="button"
                                            @click.stop="mailEditRoomParentId = String(r.id); mailEditParentLabel = r.name; mailEditParentSearch = ''; open = false"
                                            style="display:flex;align-items:center;gap:8px;width:100%;text-align:left;padding:6px 10px;background:#fff;border:0;border-bottom:1px solid #f3f4f6;cursor:pointer;font-size:11px;color:#1f2937;"
                                            onmouseover="this.style.backgroundColor='#f9fafb';"
                                            onmouseout="this.style.backgroundColor='#fff';">
                                        <i :class="r.is_private ? 'fas fa-lock' : 'fas fa-hashtag'"
                                           style="font-size:9px;" :style="r.is_private ? 'color:#a78bfa;' : 'color:#6b7280;'"></i>
                                        <span style="flex:1;" x-text="r.name"></span>
                                        <span style="font-size:9px;color:#9ca3af;font-weight:700;text-transform:uppercase;"
                                              x-text="r.is_private ? '個人' : '共有'"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <button type="button" @click="submitRoomParentChange()"
                                style="background:#7c3aed;color:#fff;border:0;border-radius:6px;padding:5px 12px;font-size:11px;font-weight:700;cursor:pointer;flex-shrink:0;">
                            <i class="fas fa-check"></i> 反映
                        </button>
                    </div>
                </div>

                {{-- ==================== ルームマージ ==================== --}}
                <div style="margin-top:16px;padding-top:12px;border-top:1px solid #e5e7eb;">
                    <label style="font-size:12px;font-weight:800;color:#374151;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-compress-alt" style="color:#dc2626;font-size:11px;"></i>
                        他のルームに統合 (マージ)
                    </label>
                    <p style="font-size:10px;color:#6b7280;margin:4px 0 8px;line-height:1.5;">
                        このルームの <strong>スレッド・チャット・子ルーム・ルール・Wiki</strong> をすべて指定先に移し、このルーム自体は削除します。<br>
                        <span style="color:#dc2626;">⚠ 取り消せません。</span>
                    </p>
                    <div style="display:flex;gap:6px;align-items:flex-start;">
                        <div style="flex:1;position:relative;" x-data="{ open: false }" @click.outside="open = false">
                            <input type="text"
                                   :value="mailEditMergeLabel"
                                   @focus="open = true"
                                   @input="mailEditMergeSearch = $event.target.value; mailEditMergeLabel = $event.target.value; open = true"
                                   placeholder="マージ先のルーム名で検索…"
                                   style="width:100%;border:1px solid #d1d5db;border-radius:6px;padding:5px 28px 5px 10px;font-size:11px;background:#fff;color:#1f2937;outline:none;">
                            <button type="button" x-show="mailEditRoomMergeTargetId"
                                    @click.stop="mailEditRoomMergeTargetId = ''; mailEditMergeLabel = ''; mailEditMergeSearch = ''"
                                    title="選択解除"
                                    style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ca3af;cursor:pointer;font-size:11px;">
                                <i class="fas fa-times-circle"></i>
                            </button>
                            <div x-show="open && availableMergeTargetsSearched.length > 0" x-cloak
                                 style="position:absolute;left:0;right:0;top:100%;margin-top:4px;max-height:240px;overflow-y:auto;background:#ffffff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 8px 24px rgba(15,23,42,0.12);z-index:50;">
                                <template x-for="r in availableMergeTargetsSearched" :key="'edit-merge-' + r.id">
                                    <button type="button"
                                            @click.stop="mailEditRoomMergeTargetId = String(r.id); mailEditMergeLabel = r.name; mailEditMergeSearch = ''; open = false"
                                            style="display:flex;align-items:center;gap:8px;width:100%;text-align:left;padding:6px 10px;background:#fff;border:0;border-bottom:1px solid #f3f4f6;cursor:pointer;font-size:11px;color:#1f2937;"
                                            onmouseover="this.style.backgroundColor='#fef2f2';"
                                            onmouseout="this.style.backgroundColor='#fff';">
                                        <i :class="r.is_private ? 'fas fa-lock' : 'fas fa-hashtag'"
                                           style="font-size:9px;" :style="r.is_private ? 'color:#a78bfa;' : 'color:#6b7280;'"></i>
                                        <span style="flex:1;" x-text="r.name"></span>
                                        <span style="font-size:9px;color:#9ca3af;font-weight:700;text-transform:uppercase;"
                                              x-text="r.is_private ? '個人' : '共有'"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <button type="button" @click="submitRoomMerge()"
                                :disabled="!mailEditRoomMergeTargetId"
                                :style="!mailEditRoomMergeTargetId ? 'opacity:0.4;cursor:not-allowed;background:#dc2626;color:#fff;border:0;border-radius:6px;padding:5px 12px;font-size:11px;font-weight:700;flex-shrink:0;' : 'background:#dc2626;color:#fff;border:0;border-radius:6px;padding:5px 12px;font-size:11px;font-weight:700;cursor:pointer;flex-shrink:0;'">
                            <i class="fas fa-compress-alt"></i> マージ実行
                        </button>
                    </div>
                </div>

                {{-- ==================== 振り分けルール (パターン/フィルタ) ==================== --}}
                <div style="margin-top:16px;padding-top:12px;border-top:1px solid #e5e7eb;">
                    <label style="font-size:12px;font-weight:800;color:#374151;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-filter" style="color:#2563eb;font-size:11px;"></i>
                        振り分けルール
                        <span x-show="routingRules.length > 0" style="font-size:10px;font-weight:700;color:#9ca3af;"
                              x-text="'(' + routingRules.length + ')'"></span>
                    </label>
                    <p style="font-size:10px;color:#6b7280;margin:4px 0 8px;line-height:1.5;">
                        指定したパターンに一致する受信メールを自動でこのルームへ振り分けます。
                    </p>

                    {{-- 既存ルール一覧 --}}
                    <template x-if="routingRules.length > 0">
                        <div style="display:flex;flex-direction:column;gap:4px;margin-bottom:10px;max-height:200px;overflow-y:auto;">
                            <template x-for="r in routingRules" :key="'rule-' + r.id">
                                <div style="display:flex;align-items:center;gap:6px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:6px 8px;font-size:11px;"
                                     :style="!r.enabled ? 'opacity:0.5;' : ''">
                                    <span style="background:#dbeafe;color:#1e40af;padding:1px 6px;border-radius:4px;font-size:9px;font-weight:700;flex-shrink:0;"
                                          x-text="r.type_label"></span>
                                    <code style="flex:1;color:#1f2937;font-weight:600;background:transparent;font-family:monospace;font-size:11px;word-break:break-all;"
                                          x-text="r.pattern"></code>
                                    <span x-show="r.match_count > 0"
                                          style="font-size:9px;color:#6b7280;flex-shrink:0;"
                                          :title="'最終マッチ: ' + (r.last_matched_at || '?')"
                                          x-text="r.match_count + ' 件'"></span>
                                    <button type="button" @click="toggleRoutingRule(r)"
                                            :title="r.enabled ? 'このルールを一時停止' : '再有効化'"
                                            style="background:none;border:0;cursor:pointer;padding:2px 4px;color:#9ca3af;font-size:11px;flex-shrink:0;"
                                            onmouseover="this.style.color='#1d4ed8';"
                                            onmouseout="this.style.color='#9ca3af';">
                                        <i class="fas" :class="r.enabled ? 'fa-toggle-on' : 'fa-toggle-off'"></i>
                                    </button>
                                    <button type="button" @click="deleteRoutingRule(r)"
                                            title="削除"
                                            style="background:none;border:0;cursor:pointer;padding:2px 4px;color:#9ca3af;font-size:10px;flex-shrink:0;"
                                            onmouseover="this.style.color='#dc2626';"
                                            onmouseout="this.style.color='#9ca3af';">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="routingRules.length === 0 && !routingRulesLoading">
                        <p style="font-size:10px;color:#9ca3af;font-style:italic;margin-bottom:8px;">
                            まだルールがありません。下で追加してください。
                        </p>
                    </template>

                    {{-- 追加フォーム --}}
                    <div style="display:flex;gap:4px;align-items:stretch;">
                        <select x-model="newRoutingRuleType"
                                style="border:1px solid #d1d5db;border-radius:6px;padding:5px 8px;font-size:11px;background:#fff;color:#1f2937;min-width:130px;">
                            <option value="from_address">差出人 (完全一致)</option>
                            <option value="from_domain">差出人ドメイン</option>
                            <option value="subject_contains">件名に含む</option>
                            <option value="to_contains">宛先 (To) に含む</option>
                        </select>
                        <input type="text" x-model="newRoutingRulePattern"
                               :placeholder="routingRulePlaceholder"
                               @keydown.enter.prevent="addRoutingRule()"
                               style="flex:1;border:1px solid #d1d5db;border-radius:6px;padding:5px 10px;font-size:11px;background:#fff;color:#1f2937;font-family:monospace;">
                        <button type="button" @click="addRoutingRule()"
                                :disabled="!newRoutingRulePattern?.trim() || routingRulesSubmitting"
                                style="background:#2563eb;color:#fff;border:0;border-radius:6px;padding:5px 14px;font-size:11px;font-weight:700;cursor:pointer;flex-shrink:0;"
                                :style="(!newRoutingRulePattern?.trim() || routingRulesSubmitting) ? 'opacity:0.5;cursor:not-allowed;' : ''"
                                title="このパターンを追加 (Enter)">
                            <i class="fas" :class="routingRulesSubmitting ? 'fa-spinner fa-spin' : 'fa-plus'"></i>
                            追加
                        </button>
                    </div>
                </div>
            </div>
            <div class="rice-modal-foot">
                <button @click="mailEditRoomOpen = false" class="rice-btn-secondary">キャンセル</button>
                <button @click="submitMailEditRoom()"
                        :disabled="!mailEditRoomName?.trim() || mailEditingRoom"
                        class="rice-btn-primary"
                        :style="(!mailEditRoomName?.trim() || mailEditingRoom) ? 'opacity:0.5;cursor:not-allowed;' : ''">
                    <i class="fas" :class="mailEditingRoom ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                    保存
                </button>
            </div>
        </div>
    </div>

    {{-- 「スレッドをルームに追加」モーダル.
         キーボード:
           ↑/↓ / J/K → ルーム候補のハイライト移動
           Enter        → ハイライト中の候補で確定 (confirmAddToRoom)
           Esc          → モーダルを閉じる
         (キー入力フィールド「新規ルーム名」がフォーカス時のみ、 J/K は通常入力として扱う) --}}
    <div x-show="addToRoomOpen" x-cloak
         @keydown.escape.window="if (addToRoomOpen) addToRoomOpen = false"
         @keydown.window="
            if (!addToRoomOpen) return;
            // input/textarea にフォーカスがあるときは J/K は文字入力. ただし矢印 / Enter / Esc は通す.
            const tag = ($event.target?.tagName || '').toLowerCase();
            const inInput = (tag === 'input' || tag === 'textarea');
            const key = $event.key;
            if (key === 'ArrowDown' || (!inInput && (key === 'j' || key === 'J'))) {
                $event.preventDefault();
                addToRoomNavHighlight(+1);
            } else if (key === 'ArrowUp' || (!inInput && (key === 'k' || key === 'K'))) {
                $event.preventDefault();
                addToRoomNavHighlight(-1);
            } else if (key === 'Enter' && !inInput) {
                $event.preventDefault();
                addToRoomConfirmHighlight();
            }
         ">
        <div @click="addToRoomOpen = false" class="rice-modal-backdrop"></div>
        <div class="rice-modal" style="width:480px;max-width:94vw;max-height:80vh;">
            <div class="rice-modal-head">
                <div class="rice-modal-head-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-link"></i></div>
                <div class="rice-modal-head-text">
                    <h3 x-text="addToRoomThreadIds.length > 1 ? ('スレッドをルームに追加 (' + addToRoomThreadIds.length + '件)') : 'スレッドをルームに追加'"></h3>
                    <p>追加先のルームを選んでください。新規作成もできます。</p>
                </div>
                <button @click="addToRoomOpen = false" class="rice-modal-close" title="閉じる"><i class="fas fa-times"></i></button>
            </div>
            {{-- 追加対象のスレッド一覧.
                 旧 UI では件数だけだったので「どのスレッドを操作しているか」が見えず不安だった.
                 各スレッドの件名と差出人を、最大 5 件まで折りたたまずに表示する.
                 6 件以上ある時は「他 N 件」を出し、クリックで全件展開できる. --}}
            <div x-show="addToRoomTargetThreads.length > 0"
                 style="padding:10px 18px;background:#f0f9ff;border-bottom:1px solid #bae6fd;">
                <p style="margin:0 0 6px;font-size:10px;font-weight:800;color:#075985;text-transform:uppercase;letter-spacing:0.05em;">
                    <i class="fas fa-envelope" style="margin-right:4px;"></i>
                    対象スレッド (<span x-text="addToRoomTargetThreads.length"></span> 件)
                </p>
                <div style="display:flex;flex-direction:column;gap:4px;max-height:140px;overflow-y:auto;">
                    <template x-for="t in (addToRoomShowAllTargets ? addToRoomTargetThreads : addToRoomTargetThreads.slice(0, 5))"
                              :key="'add-target-' + t.id">
                        <div style="display:flex;align-items:flex-start;gap:6px;background:#ffffff;border:1px solid #bae6fd;border-radius:6px;padding:5px 8px;font-size:11px;">
                            <i class="fas fa-envelope-open" style="font-size:10px;color:#0284c7;margin-top:2px;flex-shrink:0;"></i>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:700;color:#0c4a6e;word-break:break-all;line-height:1.4;"
                                     x-text="t.subject || '(件名なし)'"></div>
                                <div style="font-size:10px;color:#64748b;margin-top:1px;line-height:1.3;truncate;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                     x-text="t._fromLabel || ''"></div>
                            </div>
                        </div>
                    </template>
                    <button type="button" x-show="!addToRoomShowAllTargets && addToRoomTargetThreads.length > 5"
                            @click="addToRoomShowAllTargets = true"
                            style="background:none;border:none;color:#075985;font-size:10px;font-weight:700;cursor:pointer;text-decoration:underline;padding:2px;">
                        他 <span x-text="addToRoomTargetThreads.length - 5"></span> 件を表示
                    </button>
                </div>
            </div>
            <div class="rice-modal-body" style="flex:1;overflow-y:auto;max-height:60vh;">
                {{--
                    新規ルーム作成 (インライン入力)。
                    旧実装は createRoomAndAttach() が prompt() を出していたため、
                    入力中に「似た既存ルームありますよ」の提示ができなかった。
                    ここでは名前を入力フィールドで受け取り、入力に応じて
                    addToRoomSimilarRooms に部分一致候補が出る (クリックで既存に追加)。
                    最終的な作成は「共有で作成」「個人で作成」のいずれかを押す。
                --}}
                <div style="padding:4px 0 12px;border-bottom:1px solid #f3f4f6;margin-bottom:12px;">
                    <label style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;">
                        新規ルームを作成して追加
                    </label>
                    {{--
                      L 押下でモーダルが開いた瞬間にここへフォーカスが入る。
                      @keydown.stop でグローバルショートカット (J/K 等) との衝突を防ぐ
                      (入力中の文字が誤動作の引き金にならない)。
                      矢印キー (↑/↓) は入力中もモーダル内ナビに割り当て、Enter は確定、Esc は閉じる。
                    --}}
                    <input type="text" x-model="addToRoomNewName"
                           x-ref="addToRoomNameInput"
                           placeholder="新規ルーム名 (入力すると似たルームを下に表示 / ↑↓ で選択 / Enter で追加)"
                           class="rice-input"
                           style="margin-top:4px;width:100%;"
                           @keydown.stop
                           @keydown.escape.prevent="addToRoomOpen = false"
                           @keydown.enter.prevent="addToRoomConfirmHighlight()"
                           @keydown.arrow-down.prevent="addToRoomNavHighlight(1)"
                           @keydown.arrow-up.prevent="addToRoomNavHighlight(-1)">

                    {{-- 部分一致サジェスト (2 文字以上で発動)。
                         クリックするとそのルームへ confirmAddToRoom で追加。 --}}
                    <template x-if="addToRoomSimilarRooms.length > 0">
                        <div style="margin-top:8px;border:1px solid #fde68a;background:#fffbeb;border-radius:6px;padding:8px 10px;">
                            <p style="margin:0 0 4px;font-size:10px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.04em;">
                                <i class="fas fa-info-circle" style="margin-right:4px;"></i>似た名前のルームがあります (クリックで追加)
                            </p>
                            <div style="display:flex;flex-direction:column;gap:2px;max-height:140px;overflow-y:auto;">
                                <template x-for="r in addToRoomSimilarRooms" :key="'add-sim-' + r.id">
                                    <button type="button" @click="confirmAddToRoom(r)"
                                            style="display:flex;align-items:center;gap:6px;width:100%;text-align:left;background:#fff;border:1px solid #fde68a;border-radius:4px;padding:5px 8px;cursor:pointer;font-size:12px;color:#1f2937;"
                                            onmouseover="this.style.backgroundColor='#fef3c7';"
                                            onmouseout="this.style.backgroundColor='#fff';">
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

                    <div style="margin-top:10px;display:flex;gap:8px;">
                        <button type="button"
                                @click="createRoomAndAttach(false)"
                                :disabled="!addToRoomNewName?.trim() || addToRoomCreating"
                                class="rice-btn-primary"
                                :style="'flex:1;background:#2563eb;color:#fff;' + ((!addToRoomNewName?.trim() || addToRoomCreating) ? 'opacity:0.5;cursor:not-allowed;' : '')"
                                title="入力した名前で共有ルームを作成し、スレッドを追加">
                            <i class="fas" :class="addToRoomCreating ? 'fa-spinner fa-spin' : 'fa-globe'"></i>
                            共有で作成
                        </button>
                        <button type="button"
                                @click="createRoomAndAttach(true)"
                                :disabled="!addToRoomNewName?.trim() || addToRoomCreating"
                                class="rice-btn-primary"
                                :style="'flex:1;background:#7c3aed;color:#fff;' + ((!addToRoomNewName?.trim() || addToRoomCreating) ? 'opacity:0.5;cursor:not-allowed;' : '')"
                                title="入力した名前で個人ルームを作成し、スレッドを追加">
                            <i class="fas" :class="addToRoomCreating ? 'fa-spinner fa-spin' : 'fa-lock'"></i>
                            個人で作成
                        </button>
                    </div>
                </div>

                {{-- ===== 振り分けルールの追加 =====
                     旧: 同モーダル内のチェックボックスで一括だったが「追加できたか分からない」
                          という UX 課題があったため、ルーム選択/作成完了後に
                          別ウィンドウ (riceRoutingFollowupModal) を必ず出す方式に変更.
                          ここではトグル UI を撤去し、補足文だけにする. --}}
                <div style="padding:8px 10px;margin-bottom:12px;border:1px dashed #cbd5e1;border-radius:8px;background:#fafbfd;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-info-circle" style="color:#2563eb;font-size:14px;flex-shrink:0;"></i>
                    <p style="margin:0;font-size:11px;color:#374151;line-height:1.5;">
                        <strong>追加後に振り分けルールを設定するか聞きます</strong>。<br>
                        <span style="color:#6b7280;">「同じ差出人 / 同じドメインのメールは今後自動でこのルームへ」など、続けて条件登録できます (スキップも可).</span>
                    </p>
                </div>
                {{-- 旧チェックボックスは無効化されたが、互換性のため state は残してある (= false 固定) --}}
                <template x-if="false">
                    <div>
                    <template x-if="addToRoomCreateRule">
                        <div style="margin-top:8px;display:flex;flex-direction:column;gap:6px;">
                            <div style="display:flex;gap:4px;align-items:stretch;">
                                <select x-model="addToRoomRuleType"
                                        style="border:1px solid #d1d5db;border-radius:6px;padding:5px 8px;font-size:11px;background:#fff;color:#1f2937;min-width:130px;">
                                    <option value="from_address">差出人 (完全一致)</option>
                                    <option value="from_domain">差出人ドメイン</option>
                                    <option value="subject_contains">件名に含む</option>
                                    <option value="to_contains">宛先 (To) に含む</option>
                                </select>
                                <input type="text" x-model="addToRoomRulePattern"
                                       :placeholder="addToRoomRulePlaceholder"
                                       @keydown.stop
                                       style="flex:1;border:1px solid #d1d5db;border-radius:6px;padding:5px 10px;font-size:11px;background:#fff;color:#1f2937;font-family:monospace;">
                            </div>
                            {{-- スレッドから値を引用するためのクイックフィルチップ群.
                                 From / To / Cc / Bcc + 件名 + 各ドメインをグループ表示.
                                 クリック → タイプ自動切替 + パターン欄に転記 (その後編集可能). --}}
                            <div style="display:flex;flex-direction:column;gap:4px;font-size:10px;">
                                <p style="color:#6b7280;font-weight:700;margin:0;">スレッドから引用 (クリックで入力欄へ転記):</p>

                                {{-- ===== From: 差出人 ===== --}}
                                <div x-show="addToRoomGuessFromAddress || addToRoomGuessFromDomain"
                                     style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">
                                    <span style="color:#6b7280;font-weight:700;min-width:32px;">From:</span>
                                    <button type="button"
                                            x-show="addToRoomGuessFromAddress"
                                            @click="useRuleQuickFill('from_address', addToRoomGuessFromAddress)"
                                            style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                            onmouseover="this.style.background='#dbeafe';"
                                            onmouseout="this.style.background='#eff6ff';"
                                            :title="addToRoomGuessFromAddress">
                                        <i class="fas fa-at" style="font-size:9px;margin-right:3px;"></i>
                                        <span x-text="addToRoomGuessFromAddress"></span>
                                    </button>
                                    <button type="button"
                                            x-show="addToRoomGuessFromDomain"
                                            @click="useRuleQuickFill('from_domain', addToRoomGuessFromDomain)"
                                            style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                            onmouseover="this.style.background='#d1fae5';"
                                            onmouseout="this.style.background='#ecfdf5';"
                                            :title="addToRoomGuessFromDomain">
                                        <i class="fas fa-globe" style="font-size:9px;margin-right:3px;"></i>
                                        <span x-text="addToRoomGuessFromDomain"></span>
                                    </button>
                                </div>

                                {{-- ===== To: 宛先 (複数 + 各ドメイン) ===== --}}
                                <div x-show="addToRoomGuessToList.length > 0"
                                     style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">
                                    <span style="color:#6b7280;font-weight:700;min-width:32px;">To:</span>
                                    <template x-for="to in addToRoomGuessToList" :key="'to-addr-' + to">
                                        <button type="button"
                                                @click="useRuleQuickFill('to_contains', to)"
                                                style="background:#f3e8ff;border:1px solid #d8b4fe;color:#6b21a8;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                                onmouseover="this.style.background='#e9d5ff';"
                                                onmouseout="this.style.background='#f3e8ff';"
                                                :title="to">
                                            <i class="fas fa-paper-plane" style="font-size:9px;margin-right:3px;"></i>
                                            <span x-text="to"></span>
                                        </button>
                                    </template>
                                    <template x-for="td in addToRoomGuessToDomainList" :key="'to-dom-' + td">
                                        <button type="button"
                                                @click="useRuleQuickFill('from_domain', td)"
                                                style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                                onmouseover="this.style.background='#d1fae5';"
                                                onmouseout="this.style.background='#ecfdf5';"
                                                :title="td">
                                            <i class="fas fa-globe" style="font-size:9px;margin-right:3px;"></i>
                                            <span x-text="td"></span>
                                        </button>
                                    </template>
                                </div>

                                {{-- ===== Cc: ===== --}}
                                <div x-show="addToRoomGuessCcList.length > 0"
                                     style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">
                                    <span style="color:#6b7280;font-weight:700;min-width:32px;">Cc:</span>
                                    <template x-for="cc in addToRoomGuessCcList" :key="'cc-addr-' + cc">
                                        <button type="button"
                                                @click="useRuleQuickFill('to_contains', cc)"
                                                style="background:#fce7f3;border:1px solid #fbcfe8;color:#9d174d;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                                onmouseover="this.style.background='#fbcfe8';"
                                                onmouseout="this.style.background='#fce7f3';"
                                                :title="cc">
                                            <i class="fas fa-copy" style="font-size:9px;margin-right:3px;"></i>
                                            <span x-text="cc"></span>
                                        </button>
                                    </template>
                                    <template x-for="ccd in addToRoomGuessCcDomainList" :key="'cc-dom-' + ccd">
                                        <button type="button"
                                                @click="useRuleQuickFill('from_domain', ccd)"
                                                style="background:#dcfce7;border:1px solid #86efac;color:#166534;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                                onmouseover="this.style.background='#bbf7d0';"
                                                onmouseout="this.style.background='#dcfce7';"
                                                :title="ccd">
                                            <i class="fas fa-globe-asia" style="font-size:9px;margin-right:3px;"></i>
                                            <span x-text="ccd"></span>
                                        </button>
                                    </template>
                                </div>

                                {{-- ===== Bcc: ===== --}}
                                <div x-show="addToRoomGuessBccList.length > 0"
                                     style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">
                                    <span style="color:#6b7280;font-weight:700;min-width:32px;">Bcc:</span>
                                    <template x-for="bcc in addToRoomGuessBccList" :key="'bcc-addr-' + bcc">
                                        <button type="button"
                                                @click="useRuleQuickFill('to_contains', bcc)"
                                                style="background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                                onmouseover="this.style.background='#fde68a';"
                                                onmouseout="this.style.background='#fef3c7';"
                                                :title="bcc">
                                            <i class="fas fa-user-secret" style="font-size:9px;margin-right:3px;"></i>
                                            <span x-text="bcc"></span>
                                        </button>
                                    </template>
                                    <template x-for="bd in addToRoomGuessBccDomainList" :key="'bcc-dom-' + bd">
                                        <button type="button"
                                                @click="useRuleQuickFill('from_domain', bd)"
                                                style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                                onmouseover="this.style.background='#d1fae5';"
                                                onmouseout="this.style.background='#ecfdf5';"
                                                :title="bd">
                                            <i class="fas fa-globe" style="font-size:9px;margin-right:3px;"></i>
                                            <span x-text="bd"></span>
                                        </button>
                                    </template>
                                </div>

                                {{-- ===== Subject: ===== --}}
                                <div x-show="addToRoomGuessSubject"
                                     style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">
                                    <span style="color:#6b7280;font-weight:700;min-width:32px;">件名:</span>
                                    <button type="button"
                                            @click="useRuleQuickFill('subject_contains', addToRoomGuessSubject)"
                                            style="background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                            onmouseover="this.style.background='#fde68a';"
                                            onmouseout="this.style.background='#fef3c7';"
                                            :title="addToRoomGuessSubject">
                                        <i class="fas fa-heading" style="font-size:9px;margin-right:3px;"></i>
                                        <span x-text="addToRoomGuessSubject"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                    <p x-show="addToRoomCreateRule" style="margin:6px 0 0;font-size:10px;color:#6b7280;">
                        確定すると、追加先のルーム (既存または新規) にこのルールが登録され、過去メールも遡及して取り込まれます。
                    </p>
                </div>
                </template>{{-- /旧チェックボックス no-op ラッパ --}}

                <div class="rice-room-list-section">
                    <p class="rice-room-list-head">
                        <i class="fas fa-globe rice-room-list-head-icon"></i>共有ルームから選ぶ
                        <span style="font-size:9px;color:#9ca3af;font-weight:600;margin-left:6px;">
                            (<kbd class="rice-kbd">J</kbd>/<kbd class="rice-kbd">K</kbd> または <kbd class="rice-kbd">↑</kbd>/<kbd class="rice-kbd">↓</kbd> で選択、 <kbd class="rice-kbd">Enter</kbd> で追加)
                        </span>
                    </p>
                    <template x-for="(r, idx) in addToRoomFilteredShared" :key="'add-sh-' + r.id">
                        <button type="button"
                                @click="confirmAddToRoom(r)"
                                @mouseenter="addToRoomHighlightId = r.id"
                                class="rice-room-list-item"
                                :data-room-id="r.id"
                                :style="addToRoomHighlightId === r.id ? 'background:#dbeafe;border-color:#3b82f6;box-shadow:inset 0 0 0 2px #3b82f6;' : ''">
                            <span class="rice-room-list-hash">#</span>
                            <span x-text="r.name"></span>
                            <i x-show="addToRoomHighlightId === r.id" class="fas fa-arrow-right" style="margin-left:auto;color:#3b82f6;font-size:10px;"></i>
                        </button>
                    </template>
                    <template x-if="addToRoomFilteredShared.length === 0">
                        <p class="rice-room-list-empty" x-text="addToRoomNewName ? '該当する共有ルームはありません' : '共有ルームはありません'"></p>
                    </template>
                </div>
                <div class="rice-room-list-section">
                    <p class="rice-room-list-head"><i class="fas fa-lock rice-room-list-head-icon" style="color:#a78bfa;"></i>個人ルームから選ぶ</p>
                    <template x-for="r in addToRoomFilteredPersonal" :key="'add-pr-' + r.id">
                        <button type="button"
                                @click="confirmAddToRoom(r)"
                                @mouseenter="addToRoomHighlightId = r.id"
                                class="rice-room-list-item"
                                :data-room-id="r.id"
                                :style="addToRoomHighlightId === r.id ? 'background:#ede9fe;border-color:#a78bfa;box-shadow:inset 0 0 0 2px #a78bfa;' : ''">
                            <i class="fas fa-lock rice-room-list-lock"></i>
                            <span x-text="r.name"></span>
                            <i x-show="addToRoomHighlightId === r.id" class="fas fa-arrow-right" style="margin-left:auto;color:#a78bfa;font-size:10px;"></i>
                        </button>
                    </template>
                    <template x-if="addToRoomFilteredPersonal.length === 0">
                        <p class="rice-room-list-empty" x-text="addToRoomNewName ? '該当する個人ルームはありません' : '個人ルームはありません'"></p>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         振り分けルール フォローアップ モーダル
         スレッドをルームに追加 (新規/既存問わず) した直後に開く小さなモーダル.
         「同じ条件のメールを今後自動でこのルームに振り分けますか?」
         スキップしても OK. 適用したら成功トーストで明示する.
         ============================================================ --}}
    <div x-show="routingFollowupOpen" x-cloak
         style="position:fixed;inset:0;z-index:2010;display:flex;align-items:center;justify-content:center;padding:16px;background-color:rgba(15,23,42,0.55);"
         @click.self="closeRoutingFollowup()"
         @keydown.escape.window="if (routingFollowupOpen) closeRoutingFollowup()">
        <div style="width:100%;max-width:560px;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.4);display:flex;flex-direction:column;max-height:90vh;">
            {{-- ヘッダー --}}
            <div style="padding:14px 18px;background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);border-bottom:1px solid #bfdbfe;display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:10px;background-color:#2563eb;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:14px;">
                    <i class="fas fa-filter"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <h3 style="margin:0;font-size:13px;font-weight:900;color:#1e3a8a;">振り分けルールを追加しますか?</h3>
                    <p style="margin:2px 0 0;font-size:11px;color:#1d4ed8;">
                        ルーム: <strong style="color:#1e3a8a;" x-text="routingFollowupRoomName"></strong>
                        <span x-show="routingFollowupRoomCreated" style="margin-left:6px;font-size:10px;background:#10b981;color:#fff;padding:1px 6px;border-radius:8px;">新規作成しました</span>
                        <span x-show="!routingFollowupRoomCreated && routingFollowupAttachedCount > 0" style="margin-left:6px;font-size:10px;background:#3b82f6;color:#fff;padding:1px 6px;border-radius:8px;"
                              x-text="routingFollowupAttachedCount + ' 件追加しました'"></span>
                    </p>
                </div>
                <button type="button" @click="closeRoutingFollowup()"
                        style="width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;background:none;border:none;color:#6b7280;cursor:pointer;border-radius:6px;"
                        title="閉じる (ルール追加しない)">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            {{-- 本文 --}}
            <div style="flex:1;overflow-y:auto;padding:14px 18px;">
                <p style="margin:0 0 12px;font-size:12px;color:#374151;line-height:1.6;">
                    今後 <strong>同じ条件のメール</strong> を自動でこのルームへ振り分けたい場合、ルールを追加してください。
                    過去のメールにも遡って適用されます。
                </p>

                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <label style="display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin:0;">条件</label>
                    <button type="button" @click="rfBuilderMode = !rfBuilderMode"
                            :title="rfBuilderMode ? '単一条件に戻す' : 'AND/OR 複合条件を作る'"
                            style="margin-left:auto;background:#fff;border:1px solid #d1d5db;color:#374151;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                        <i class="fas" :class="rfBuilderMode ? 'fa-chevron-up' : 'fa-sitemap'" style="font-size:10px;"></i>
                        <span x-text="rfBuilderMode ? '単一条件に戻す' : 'AND/OR 複合条件'"></span>
                    </button>
                </div>

                {{-- (A) 単一条件 (従来通り) --}}
                <div x-show="!rfBuilderMode" style="display:flex;gap:6px;align-items:center;">
                    <select x-model="routingFollowupType"
                            style="border:1px solid #d1d5db;border-radius:6px;padding:6px 10px;font-size:12px;background:#fff;color:#1f2937;min-width:200px;">
                        <option value="any_address">メールアドレス (From/To/Cc 全部)</option>
                        <option value="any_domain">ドメイン (From/To/Cc 全部)</option>
                        <option value="from_address">差出人 (From のみ・完全一致)</option>
                        <option value="from_domain">差出人ドメイン (From のみ)</option>
                        <option value="subject_contains">件名に含む</option>
                        <option value="to_contains">宛先 (To/Cc/Bcc) に含む</option>
                    </select>
                    <input type="text" x-model="routingFollowupPattern"
                           :placeholder="addToRoomRulePlaceholder"
                           style="flex:1;border:1px solid #d1d5db;border-radius:6px;padding:6px 10px;font-size:12px;background:#fff;color:#1f2937;font-family:monospace;">
                </div>

                {{--
                    (B) AND/OR 複合条件ビルダー (新).
                    ルートグループ (logic=and/or) と子 (リーフ or サブグループ) で構成.
                    深さは 2 段まで (ルート → 1 段ネスト) — 業務メール振り分けはこれで十分.
                    rooms/index.blade.php のビルダーと同じ概念 + 同じ API 形式 (POST conditions).
                --}}
                <div x-show="rfBuilderMode" x-cloak style="border:1px solid #e5e7eb;border-radius:8px;padding:8px;background:#fff;">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                        <span style="font-size:11px;color:#6b7280;font-weight:700;">全体は</span>
                        <select x-model="rfBuilderTree.logic"
                                style="border:1px solid #d1d5db;border-radius:6px;padding:3px 8px;font-size:11px;background:#fff;color:#1f2937;">
                            <option value="and">AND (すべて一致)</option>
                            <option value="or">OR (いずれか一致)</option>
                        </select>
                        <span style="font-size:11px;color:#6b7280;">で評価</span>
                    </div>

                    <template x-for="(item, idx) in rfBuilderTree.items" :key="'rf-b-' + idx">
                        <div style="border-left:2px solid #cbd5e1;padding-left:8px;margin-bottom:6px;">
                            {{-- リーフノード --}}
                            <div x-show="!item.logic" style="display:flex;gap:4px;align-items:center;">
                                <select x-model="item.type"
                                        style="border:1px solid #d1d5db;border-radius:6px;padding:4px 6px;font-size:11px;background:#fff;color:#1f2937;min-width:140px;">
                                    <option value="any_address">アドレス (全部)</option>
                                    <option value="any_domain">ドメイン (全部)</option>
                                    <option value="from_address">差出人 (From)</option>
                                    <option value="from_domain">差出人ドメイン</option>
                                    <option value="subject_contains">件名に含む</option>
                                    <option value="to_contains">宛先に含む</option>
                                </select>
                                <input type="text" x-model="item.pattern" placeholder="パターン" maxlength="500"
                                       style="flex:1;border:1px solid #d1d5db;border-radius:6px;padding:4px 8px;font-size:11px;background:#fff;color:#1f2937;font-family:monospace;">
                                <button type="button" @click="rfBuilderRemoveItem(idx)" title="削除"
                                        style="background:none;border:0;color:#dc2626;cursor:pointer;padding:2px 6px;font-size:11px;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>

                            {{-- サブグループノード (1 段ネスト) --}}
                            <div x-show="item.logic" style="border:1px solid #e5e7eb;border-radius:6px;padding:6px;background:#f9fafb;">
                                <div style="display:flex;align-items:center;gap:4px;margin-bottom:4px;">
                                    <span style="font-size:10px;color:#6b7280;font-weight:700;">サブグループ:</span>
                                    <select x-model="item.logic"
                                            style="border:1px solid #d1d5db;border-radius:6px;padding:2px 6px;font-size:11px;background:#fff;color:#1f2937;">
                                        <option value="and">AND</option>
                                        <option value="or">OR</option>
                                    </select>
                                    <button type="button" @click="rfBuilderRemoveItem(idx)" title="サブグループ削除"
                                            style="margin-left:auto;background:none;border:0;color:#dc2626;cursor:pointer;padding:2px 6px;font-size:11px;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <template x-for="(sub, subIdx) in item.items" :key="'rf-b-' + idx + '-' + subIdx">
                                    <div style="display:flex;gap:4px;align-items:center;margin-bottom:4px;">
                                        <select x-model="sub.type"
                                                style="border:1px solid #d1d5db;border-radius:6px;padding:3px 6px;font-size:11px;background:#fff;color:#1f2937;min-width:130px;">
                                            <option value="any_address">アドレス</option>
                                            <option value="any_domain">ドメイン</option>
                                            <option value="from_address">From</option>
                                            <option value="from_domain">From ドメイン</option>
                                            <option value="subject_contains">件名に含む</option>
                                            <option value="to_contains">宛先に含む</option>
                                        </select>
                                        <input type="text" x-model="sub.pattern" placeholder="パターン" maxlength="500"
                                               style="flex:1;border:1px solid #d1d5db;border-radius:6px;padding:3px 8px;font-size:11px;background:#fff;color:#1f2937;font-family:monospace;">
                                        <button type="button" @click="rfBuilderRemoveSubItem(idx, subIdx)" title="削除"
                                                style="background:none;border:0;color:#dc2626;cursor:pointer;padding:2px 6px;font-size:11px;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </template>
                                <button type="button" @click="rfBuilderAddSubItem(idx)"
                                        style="background:#fff;border:1px dashed #cbd5e1;color:#374151;border-radius:6px;padding:3px 10px;font-size:10px;font-weight:700;cursor:pointer;">
                                    <i class="fas fa-plus" style="font-size:9px;"></i> サブグループに条件追加
                                </button>
                            </div>
                        </div>
                    </template>

                    <div style="display:flex;gap:6px;margin-top:4px;">
                        <button type="button" @click="rfBuilderAddItem()"
                                style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-plus" style="font-size:10px;"></i> 条件追加
                        </button>
                        <button type="button" @click="rfBuilderAddGroup()"
                                style="background:#f5f3ff;border:1px solid #ddd6fe;color:#6d28d9;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-folder-plus" style="font-size:10px;"></i> サブグループ追加
                        </button>
                    </div>
                </div>

                <p style="margin:12px 0 4px;font-size:10px;color:#6b7280;font-weight:700;">スレッドから引用 (クリックで入力欄に転記してから編集できます):</p>

                {{--
                    ★ quick-fill チップは新仕様で全部「any_address / any_domain」(= From/To/Cc/Bcc 横断) にデフォルトで設定する.
                       「info1@example.com が From でも To でも Cc でも Acme チームへ」という要望に対応する形.
                       From のみ / 件名 / 宛先のみ などのストリクトな指定はセレクタから手動で切替可能.
                --}}

                {{-- ===== From: 差出人 (アドレス + ドメイン) ===== --}}
                <div x-show="addToRoomGuessFromAddress || addToRoomGuessFromDomain"
                     style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;margin-bottom:4px;">
                    <span style="color:#6b7280;font-weight:700;font-size:10px;min-width:36px;">From:</span>
                    <button type="button" x-show="addToRoomGuessFromAddress"
                            @click="useRoutingFollowupQuickFill('any_address', addToRoomGuessFromAddress)"
                            style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            :title="'アドレス完全一致 (From/To/Cc 横断): ' + addToRoomGuessFromAddress">
                        <i class="fas fa-at" style="font-size:9px;margin-right:3px;"></i><span x-text="addToRoomGuessFromAddress"></span>
                    </button>
                    <button type="button" x-show="addToRoomGuessFromDomain"
                            @click="useRoutingFollowupQuickFill('any_domain', addToRoomGuessFromDomain)"
                            style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            :title="'ドメイン (From/To/Cc 横断): ' + addToRoomGuessFromDomain">
                        <i class="fas fa-globe" style="font-size:9px;margin-right:3px;"></i><span x-text="addToRoomGuessFromDomain"></span>
                    </button>
                </div>

                {{-- ===== To: 宛先 (複数アドレス + 各ドメイン) ===== --}}
                <div x-show="addToRoomGuessToList.length > 0"
                     style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;margin-bottom:4px;">
                    <span style="color:#6b7280;font-weight:700;font-size:10px;min-width:36px;">To:</span>
                    <template x-for="to in addToRoomGuessToList" :key="'rfu-to-addr-' + to">
                        <button type="button" @click="useRoutingFollowupQuickFill('any_address', to)"
                                style="background:#f3e8ff;border:1px solid #d8b4fe;color:#6b21a8;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                :title="'アドレス完全一致 (From/To/Cc 横断): ' + to">
                            <i class="fas fa-paper-plane" style="font-size:9px;margin-right:3px;"></i><span x-text="to"></span>
                        </button>
                    </template>
                    <template x-for="td in addToRoomGuessToDomainList" :key="'rfu-to-dom-' + td">
                        <button type="button" @click="useRoutingFollowupQuickFill('any_domain', td)"
                                style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                :title="'ドメイン (From/To/Cc 横断): ' + td">
                            <i class="fas fa-globe" style="font-size:9px;margin-right:3px;"></i><span x-text="td"></span>
                        </button>
                    </template>
                </div>

                {{-- ===== Cc: ===== --}}
                <div x-show="addToRoomGuessCcList.length > 0"
                     style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;margin-bottom:4px;">
                    <span style="color:#6b7280;font-weight:700;font-size:10px;min-width:36px;">Cc:</span>
                    <template x-for="cc in addToRoomGuessCcList" :key="'rfu-cc-addr-' + cc">
                        <button type="button" @click="useRoutingFollowupQuickFill('any_address', cc)"
                                style="background:#fce7f3;border:1px solid #fbcfe8;color:#9d174d;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                :title="'アドレス完全一致 (From/To/Cc 横断): ' + cc">
                            <i class="fas fa-copy" style="font-size:9px;margin-right:3px;"></i><span x-text="cc"></span>
                        </button>
                    </template>
                    <template x-for="ccd in addToRoomGuessCcDomainList" :key="'rfu-cc-dom-' + ccd">
                        <button type="button" @click="useRoutingFollowupQuickFill('any_domain', ccd)"
                                style="background:#dcfce7;border:1px solid #86efac;color:#166534;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                :title="'ドメイン (From/To/Cc 横断): ' + ccd">
                            <i class="fas fa-globe-asia" style="font-size:9px;margin-right:3px;"></i><span x-text="ccd"></span>
                        </button>
                    </template>
                </div>

                {{-- ===== Bcc: ===== --}}
                <div x-show="addToRoomGuessBccList.length > 0"
                     style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;margin-bottom:4px;">
                    <span style="color:#6b7280;font-weight:700;font-size:10px;min-width:36px;">Bcc:</span>
                    <template x-for="bcc in addToRoomGuessBccList" :key="'rfu-bcc-addr-' + bcc">
                        <button type="button" @click="useRoutingFollowupQuickFill('any_address', bcc)"
                                style="background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                :title="'アドレス完全一致 (From/To/Cc 横断): ' + bcc">
                            <i class="fas fa-user-secret" style="font-size:9px;margin-right:3px;"></i><span x-text="bcc"></span>
                        </button>
                    </template>
                    <template x-for="bd in addToRoomGuessBccDomainList" :key="'rfu-bcc-dom-' + bd">
                        <button type="button" @click="useRoutingFollowupQuickFill('any_domain', bd)"
                                style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                :title="'ドメイン (From/To/Cc 横断): ' + bd">
                            <i class="fas fa-globe" style="font-size:9px;margin-right:3px;"></i><span x-text="bd"></span>
                        </button>
                    </template>
                </div>

                {{-- ===== Subject (件名) ===== --}}
                <div x-show="addToRoomGuessSubject"
                     style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;margin-bottom:4px;">
                    <span style="color:#6b7280;font-weight:700;font-size:10px;min-width:36px;">件名:</span>
                    <button type="button"
                            @click="useRoutingFollowupQuickFill('subject_contains', addToRoomGuessSubject)"
                            style="background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;max-width:380px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            :title="addToRoomGuessSubject">
                        <i class="fas fa-heading" style="font-size:9px;margin-right:3px;"></i><span x-text="addToRoomGuessSubject"></span>
                    </button>
                </div>

                {{-- 追加済みルール (このセッションで作成した分) を確認できるリスト --}}
                <template x-if="routingFollowupAddedRules.length > 0">
                    <div style="margin-top:14px;padding:10px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
                        <p style="margin:0 0 6px;font-size:11px;font-weight:900;color:#166534;">
                            <i class="fas fa-check-circle" style="margin-right:4px;"></i>
                            追加済みルール (<span x-text="routingFollowupAddedRules.length"></span> 件)
                        </p>
                        <template x-for="(rule, ri) in routingFollowupAddedRules" :key="'rfu-added-' + ri">
                            <div style="display:flex;align-items:center;gap:6px;padding:4px 0;font-size:11px;color:#15803d;">
                                <span style="background:#10b981;color:#fff;padding:1px 6px;border-radius:4px;font-size:9px;font-weight:700;" x-text="rule.type_label"></span>
                                <code style="flex:1;background:transparent;color:#15803d;font-family:monospace;font-size:11px;" x-text="rule.pattern"></code>
                                <span x-show="rule.backfilled > 0" style="font-size:10px;color:#15803d;font-weight:700;"
                                      x-text="'過去 ' + rule.backfilled + ' 件取り込み'"></span>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            {{-- フッター --}}
            <div style="padding:12px 18px;border-top:1px solid #e5e7eb;background:#f9fafb;display:flex;align-items:center;justify-content:space-between;gap:8px;">
                <button type="button" @click="closeRoutingFollowup()"
                        style="background:#fff;color:#374151;border:1px solid #d1d5db;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;">
                    スキップして閉じる
                </button>
                <div style="display:flex;align-items:center;gap:6px;">
                    {{-- ビルダーモードでは rfBuilderValid を、単一条件モードでは routingFollowupPattern を、それぞれ送信可否判定に使う. --}}
                    <button type="button" @click="submitRoutingFollowup(true)"
                            :disabled="(rfBuilderMode ? !rfBuilderValid : !routingFollowupPattern?.trim()) || routingFollowupSaving"
                            :style="((rfBuilderMode ? !rfBuilderValid : !routingFollowupPattern?.trim()) || routingFollowupSaving) ? 'opacity:0.5;cursor:not-allowed;background:#6b7280;color:#fff;border:0;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:700;' : 'background:#6b7280;color:#fff;border:0;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;'"
                            title="追加してこのモーダルを開いたままにする (続けてルールを追加できる)">
                        <i class="fas fa-plus"></i> 追加 (続けて入力)
                    </button>
                    <button type="button" @click="submitRoutingFollowup(false)"
                            :disabled="(rfBuilderMode ? !rfBuilderValid : !routingFollowupPattern?.trim()) || routingFollowupSaving"
                            :style="((rfBuilderMode ? !rfBuilderValid : !routingFollowupPattern?.trim()) || routingFollowupSaving) ? 'opacity:0.5;cursor:not-allowed;background:#2563eb;color:#fff;border:0;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:700;' : 'background:#2563eb;color:#fff;border:0;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;'"
                            title="追加して閉じる">
                        <i class="fas" :class="routingFollowupSaving ? 'fa-spinner fa-spin' : 'fa-check'"></i> 追加して完了
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{--
        ============================================================
        迷惑メール ブロックルール 追加 モーダル (markSelectedAsSpam の後続)
        ============================================================
        ルーム振り分け側の routingFollowup と同じ思想:
          - 「件名 / 差出人 / 宛先 / Cc / Bcc」の値を quick-fill チップで取得し
          - type を選んで pattern を編集して保存
          - 過去にも遡ってマッチさせるかは MailBlockRule の判定は将来分のみ (バックフィル不要).
        スレッドはすでに spam にマーク済の状態でこのモーダルが開く.
    --}}
    <div x-show="spamRuleFollowupOpen" x-cloak
         style="position:fixed;inset:0;z-index:2010;display:flex;align-items:center;justify-content:center;padding:16px;background-color:rgba(15,23,42,0.55);"
         @click.self="closeSpamRuleFollowup()"
         @keydown.escape.window="if (spamRuleFollowupOpen) closeSpamRuleFollowup()">
        <div style="width:100%;max-width:560px;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.4);display:flex;flex-direction:column;max-height:90vh;">
            <div style="padding:14px 18px;background:linear-gradient(135deg,#fee2e2 0%,#fecaca 100%);border-bottom:1px solid #fca5a5;display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:10px;background-color:#dc2626;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:14px;">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <h3 style="margin:0;font-size:13px;font-weight:900;color:#991b1b;">迷惑メール ブロックルールを追加しますか?</h3>
                    <p style="margin:2px 0 0;font-size:11px;color:#b91c1c;">
                        今後同じ条件のメールを自動で迷惑メール扱いにできます (件名・宛先・CC など何でも)
                    </p>
                </div>
                <button type="button" @click="closeSpamRuleFollowup()"
                        style="width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;background:none;border:none;color:#6b7280;cursor:pointer;border-radius:6px;"
                        title="閉じる (ルール追加しない)">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div style="flex:1;overflow-y:auto;padding:14px 18px;">
                <p style="margin:0 0 12px;font-size:12px;color:#374151;line-height:1.6;">
                    このスレッドはすでに迷惑メールに振り分け済みです。<br>
                    <strong>同じ条件のメール</strong>を今後も自動で迷惑メールにしたい場合、ルールを追加してください。
                </p>

                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <label style="display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin:0;">条件</label>
                    <button type="button" @click="srBuilderMode = !srBuilderMode"
                            :title="srBuilderMode ? '単一条件に戻す' : 'AND/OR 複合条件を作る'"
                            style="margin-left:auto;background:#fff;border:1px solid #d1d5db;color:#374151;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                        <i class="fas" :class="srBuilderMode ? 'fa-chevron-up' : 'fa-sitemap'" style="font-size:10px;"></i>
                        <span x-text="srBuilderMode ? '単一条件に戻す' : 'AND/OR 複合条件'"></span>
                    </button>
                </div>

                {{-- (A) 単一条件 (従来通り) --}}
                <div x-show="!srBuilderMode" style="display:flex;gap:6px;align-items:center;">
                    <select x-model="spamRuleFollowupType"
                            style="border:1px solid #d1d5db;border-radius:6px;padding:6px 10px;font-size:12px;background:#fff;color:#1f2937;min-width:200px;">
                        <option value="sender_address">送信元アドレス (完全一致)</option>
                        <option value="sender_domain">送信元ドメイン</option>
                        <option value="recipient_address">宛先アドレス (To/Cc/Bcc 完全一致)</option>
                        <option value="recipient_domain">宛先ドメイン (To/Cc/Bcc)</option>
                        <option value="recipient_contains">宛先に含む (To/Cc/Bcc 部分一致)</option>
                        <option value="subject_keyword">件名キーワード (部分一致)</option>
                        <option value="body_keyword">本文キーワード (部分一致)</option>
                    </select>
                    <input type="text" x-model="spamRuleFollowupPattern"
                           placeholder="例: spam@example.com / event-info / @bad-domain.tld"
                           style="flex:1;border:1px solid #d1d5db;border-radius:6px;padding:6px 10px;font-size:12px;background:#fff;color:#1f2937;font-family:monospace;">
                </div>

                {{--
                    (B) AND/OR ビルダー (新). routingFollowup / rooms admin と同形.
                    ルートグループ (logic=and/or) + 子 (リーフ or サブグループ). 深さ 2 まで.
                --}}
                <div x-show="srBuilderMode" x-cloak style="border:1px solid #e5e7eb;border-radius:8px;padding:8px;background:#fff;">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                        <span style="font-size:11px;color:#6b7280;font-weight:700;">全体は</span>
                        <select x-model="srBuilderTree.logic"
                                style="border:1px solid #d1d5db;border-radius:6px;padding:3px 8px;font-size:11px;background:#fff;color:#1f2937;">
                            <option value="and">AND (すべて一致)</option>
                            <option value="or">OR (いずれか一致)</option>
                        </select>
                        <span style="font-size:11px;color:#6b7280;">で評価</span>
                    </div>
                    <template x-for="(item, idx) in srBuilderTree.items" :key="'sr-b-' + idx">
                        <div style="border-left:2px solid #fda4af;padding-left:8px;margin-bottom:6px;">
                            {{-- リーフ --}}
                            <div x-show="!item.logic" style="display:flex;gap:4px;align-items:center;">
                                <select x-model="item.type"
                                        style="border:1px solid #d1d5db;border-radius:6px;padding:4px 6px;font-size:11px;background:#fff;color:#1f2937;min-width:160px;">
                                    <option value="sender_address">送信元アドレス</option>
                                    <option value="sender_domain">送信元ドメイン</option>
                                    <option value="recipient_address">宛先アドレス</option>
                                    <option value="recipient_domain">宛先ドメイン</option>
                                    <option value="recipient_contains">宛先に含む</option>
                                    <option value="subject_keyword">件名キーワード</option>
                                    <option value="body_keyword">本文キーワード</option>
                                </select>
                                <input type="text" x-model="item.pattern" placeholder="パターン" maxlength="255"
                                       style="flex:1;border:1px solid #d1d5db;border-radius:6px;padding:4px 8px;font-size:11px;background:#fff;color:#1f2937;font-family:monospace;">
                                <button type="button" @click="srBuilderRemoveItem(idx)" title="削除"
                                        style="background:none;border:0;color:#dc2626;cursor:pointer;padding:2px 6px;font-size:11px;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            {{-- サブグループ --}}
                            <div x-show="item.logic" style="border:1px solid #e5e7eb;border-radius:6px;padding:6px;background:#fef2f2;">
                                <div style="display:flex;align-items:center;gap:4px;margin-bottom:4px;">
                                    <span style="font-size:10px;color:#6b7280;font-weight:700;">サブグループ:</span>
                                    <select x-model="item.logic"
                                            style="border:1px solid #d1d5db;border-radius:6px;padding:2px 6px;font-size:11px;background:#fff;color:#1f2937;">
                                        <option value="and">AND</option>
                                        <option value="or">OR</option>
                                    </select>
                                    <button type="button" @click="srBuilderRemoveItem(idx)" title="サブグループ削除"
                                            style="margin-left:auto;background:none;border:0;color:#dc2626;cursor:pointer;padding:2px 6px;font-size:11px;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <template x-for="(sub, subIdx) in item.items" :key="'sr-b-' + idx + '-' + subIdx">
                                    <div style="display:flex;gap:4px;align-items:center;margin-bottom:4px;">
                                        <select x-model="sub.type"
                                                style="border:1px solid #d1d5db;border-radius:6px;padding:3px 6px;font-size:11px;background:#fff;color:#1f2937;min-width:150px;">
                                            <option value="sender_address">送信元アドレス</option>
                                            <option value="sender_domain">送信元ドメイン</option>
                                            <option value="recipient_address">宛先アドレス</option>
                                            <option value="recipient_domain">宛先ドメイン</option>
                                            <option value="recipient_contains">宛先に含む</option>
                                            <option value="subject_keyword">件名キーワード</option>
                                            <option value="body_keyword">本文キーワード</option>
                                        </select>
                                        <input type="text" x-model="sub.pattern" placeholder="パターン" maxlength="255"
                                               style="flex:1;border:1px solid #d1d5db;border-radius:6px;padding:3px 8px;font-size:11px;background:#fff;color:#1f2937;font-family:monospace;">
                                        <button type="button" @click="srBuilderRemoveSubItem(idx, subIdx)" title="削除"
                                                style="background:none;border:0;color:#dc2626;cursor:pointer;padding:2px 6px;font-size:11px;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </template>
                                <button type="button" @click="srBuilderAddSubItem(idx)"
                                        style="background:#fff;border:1px dashed #cbd5e1;color:#374151;border-radius:6px;padding:3px 10px;font-size:10px;font-weight:700;cursor:pointer;">
                                    <i class="fas fa-plus" style="font-size:9px;"></i> サブグループに条件追加
                                </button>
                            </div>
                        </div>
                    </template>
                    <div style="display:flex;gap:6px;margin-top:4px;">
                        <button type="button" @click="srBuilderAddItem()"
                                style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-plus" style="font-size:10px;"></i> 条件追加
                        </button>
                        <button type="button" @click="srBuilderAddGroup()"
                                style="background:#fff7ed;border:1px solid #fed7aa;color:#c2410c;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-folder-plus" style="font-size:10px;"></i> サブグループ追加
                        </button>
                    </div>
                </div>

                <p style="margin:12px 0 4px;font-size:10px;color:#6b7280;font-weight:700;">スレッドから引用 (クリックで入力欄に転記してから編集できます):</p>

                {{-- From: 差出人 (アドレス + ドメイン) --}}
                <div x-show="addToRoomGuessFromAddress || addToRoomGuessFromDomain"
                     style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;margin-bottom:4px;">
                    <span style="color:#6b7280;font-weight:700;font-size:10px;min-width:36px;">From:</span>
                    <button type="button" x-show="addToRoomGuessFromAddress"
                            @click="useSpamRuleFollowupQuickFill('sender_address', addToRoomGuessFromAddress)"
                            style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            :title="addToRoomGuessFromAddress">
                        <i class="fas fa-at" style="font-size:9px;margin-right:3px;"></i><span x-text="addToRoomGuessFromAddress"></span>
                    </button>
                    <button type="button" x-show="addToRoomGuessFromDomain"
                            @click="useSpamRuleFollowupQuickFill('sender_domain', addToRoomGuessFromDomain)"
                            style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            :title="addToRoomGuessFromDomain">
                        <i class="fas fa-globe" style="font-size:9px;margin-right:3px;"></i><span x-text="addToRoomGuessFromDomain"></span>
                    </button>
                </div>

                {{-- To: 宛先 (複数アドレス + 各ドメイン) --}}
                <div x-show="addToRoomGuessToList.length > 0"
                     style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;margin-bottom:4px;">
                    <span style="color:#6b7280;font-weight:700;font-size:10px;min-width:36px;">To:</span>
                    <template x-for="to in addToRoomGuessToList" :key="'srfu-to-addr-' + to">
                        <button type="button" @click="useSpamRuleFollowupQuickFill('recipient_address', to)"
                                style="background:#f3e8ff;border:1px solid #d8b4fe;color:#6b21a8;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                :title="to">
                            <i class="fas fa-paper-plane" style="font-size:9px;margin-right:3px;"></i><span x-text="to"></span>
                        </button>
                    </template>
                    <template x-for="td in addToRoomGuessToDomainList" :key="'srfu-to-dom-' + td">
                        <button type="button" @click="useSpamRuleFollowupQuickFill('recipient_domain', td)"
                                style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                :title="td">
                            <i class="fas fa-globe" style="font-size:9px;margin-right:3px;"></i><span x-text="td"></span>
                        </button>
                    </template>
                </div>

                {{-- Cc: --}}
                <div x-show="addToRoomGuessCcList.length > 0"
                     style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;margin-bottom:4px;">
                    <span style="color:#6b7280;font-weight:700;font-size:10px;min-width:36px;">Cc:</span>
                    <template x-for="cc in addToRoomGuessCcList" :key="'srfu-cc-addr-' + cc">
                        <button type="button" @click="useSpamRuleFollowupQuickFill('recipient_address', cc)"
                                style="background:#fce7f3;border:1px solid #fbcfe8;color:#9d174d;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                :title="cc">
                            <i class="fas fa-copy" style="font-size:9px;margin-right:3px;"></i><span x-text="cc"></span>
                        </button>
                    </template>
                    <template x-for="ccd in addToRoomGuessCcDomainList" :key="'srfu-cc-dom-' + ccd">
                        <button type="button" @click="useSpamRuleFollowupQuickFill('recipient_domain', ccd)"
                                style="background:#dcfce7;border:1px solid #86efac;color:#166534;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;font-family:monospace;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                :title="ccd">
                            <i class="fas fa-globe-asia" style="font-size:9px;margin-right:3px;"></i><span x-text="ccd"></span>
                        </button>
                    </template>
                </div>

                {{-- Subject 件名 --}}
                <div x-show="addToRoomGuessSubject"
                     style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;margin-bottom:4px;">
                    <span style="color:#6b7280;font-weight:700;font-size:10px;min-width:36px;">件名:</span>
                    <button type="button"
                            @click="useSpamRuleFollowupQuickFill('subject_keyword', addToRoomGuessSubject)"
                            style="background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:4px;padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;max-width:380px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            :title="addToRoomGuessSubject">
                        <i class="fas fa-heading" style="font-size:9px;margin-right:3px;"></i><span x-text="addToRoomGuessSubject"></span>
                    </button>
                </div>

                {{-- 追加済みルール (このセッションで作成した分) --}}
                <template x-if="spamRuleFollowupAddedRules.length > 0">
                    <div style="margin-top:14px;padding:10px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;">
                        <p style="margin:0 0 6px;font-size:11px;font-weight:900;color:#991b1b;">
                            <i class="fas fa-check-circle" style="margin-right:4px;"></i>
                            追加済みルール (<span x-text="spamRuleFollowupAddedRules.length"></span> 件)
                        </p>
                        <template x-for="(rule, ri) in spamRuleFollowupAddedRules" :key="'srfu-added-' + ri">
                            <div style="display:flex;align-items:center;gap:6px;padding:4px 0;font-size:11px;color:#7f1d1d;">
                                <span style="background:#dc2626;color:#fff;padding:1px 6px;border-radius:4px;font-size:9px;font-weight:700;" x-text="rule.type_label"></span>
                                <code style="flex:1;background:transparent;color:#7f1d1d;font-family:monospace;font-size:11px;" x-text="rule.pattern"></code>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div style="padding:12px 18px;border-top:1px solid #e5e7eb;background:#f9fafb;display:flex;align-items:center;justify-content:space-between;gap:8px;">
                <button type="button" @click="closeSpamRuleFollowup()"
                        style="background:#fff;color:#374151;border:1px solid #d1d5db;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;">
                    スキップして閉じる
                </button>
                <div style="display:flex;align-items:center;gap:6px;">
                    {{-- ビルダーモード時は srBuilderValid を、単一条件時は spamRuleFollowupPattern を判定に使う --}}
                    <button type="button" @click="submitSpamRuleFollowup(true)"
                            :disabled="(srBuilderMode ? !srBuilderValid : !spamRuleFollowupPattern?.trim()) || spamRuleFollowupSaving"
                            :style="((srBuilderMode ? !srBuilderValid : !spamRuleFollowupPattern?.trim()) || spamRuleFollowupSaving) ? 'opacity:0.5;cursor:not-allowed;background:#6b7280;color:#fff;border:0;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:700;' : 'background:#6b7280;color:#fff;border:0;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;'"
                            title="追加して入力欄を続けて使えるようにする">
                        <i class="fas fa-plus"></i> 追加 (続けて入力)
                    </button>
                    <button type="button" @click="submitSpamRuleFollowup(false)"
                            :disabled="(srBuilderMode ? !srBuilderValid : !spamRuleFollowupPattern?.trim()) || spamRuleFollowupSaving"
                            :style="((srBuilderMode ? !srBuilderValid : !spamRuleFollowupPattern?.trim()) || spamRuleFollowupSaving) ? 'opacity:0.5;cursor:not-allowed;background:#dc2626;color:#fff;border:0;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:700;' : 'background:#dc2626;color:#fff;border:0;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;'"
                            title="追加して閉じる">
                        <i class="fas" :class="spamRuleFollowupSaving ? 'fa-spinner fa-spin' : 'fa-check'"></i> 追加して完了
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- メインコンテンツエリア --}}
    <div class="flex flex-1 min-w-0 overflow-hidden">

        {{-- 左サイドバー: ルーム一覧 (チャット画面と同じ概念) --}}
        <aside class="mail-rooms-sidebar"
               :class="{ 'is-collapsed': mailRoomsCollapsed }"
               :style="'width:' + (mailRoomsCollapsed ? 32 : mailRoomsWidth) + 'px;'">
            {{-- 折りたたみトグル --}}
            <button @click="toggleMailRoomsSidebar()" class="mail-rooms-collapse-toggle"
                    :title="mailRoomsCollapsed ? '展開' : '折りたたむ'">
                <i class="fas" :class="mailRoomsCollapsed ? 'fa-angle-double-right' : 'fa-angle-double-left'"></i>
            </button>

            <div class="mail-rooms-head">
                <h3>ルーム</h3>
            </div>

            {{-- 共有 / 個人 切替タブ (個人受信箱機能) --}}
            <div style="display:flex;border-bottom:1px solid #e5e7eb;background:#f9fafb;">
                <button @click="setInboxScope('shared')"
                        :style="inboxScope === 'shared'
                            ? 'flex:1;padding:8px 4px;font-size:11px;font-weight:700;border:none;background:#fff;color:#2563eb;border-bottom:2px solid #2563eb;cursor:pointer;position:relative;'
                            : 'flex:1;padding:8px 4px;font-size:11px;font-weight:600;border:none;background:transparent;color:#6b7280;border-bottom:2px solid transparent;cursor:pointer;position:relative;'">
                    <i class="fas fa-users" style="margin-right:4px;"></i>共有メール
                    <span x-show="sharedInboxCount > 0"
                          x-text="sharedInboxCount > 99 ? '99+' : sharedInboxCount"
                          style="display:inline-block;margin-left:6px;background:#2563eb;color:#fff;font-size:9px;font-weight:700;border-radius:9px;padding:1px 6px;min-width:16px;line-height:1.2;"></span>
                </button>
                <button @click="setInboxScope('personal')"
                        :style="inboxScope === 'personal'
                            ? 'flex:1;padding:8px 4px;font-size:11px;font-weight:700;border:none;background:#fff;color:#2563eb;border-bottom:2px solid #2563eb;cursor:pointer;position:relative;'
                            : 'flex:1;padding:8px 4px;font-size:11px;font-weight:600;border:none;background:transparent;color:#6b7280;border-bottom:2px solid transparent;cursor:pointer;position:relative;'">
                    <i class="fas fa-user" style="margin-right:4px;"></i>個人メール
                    <span x-show="personalInboxCount > 0"
                          x-text="personalInboxCount > 99 ? '99+' : personalInboxCount"
                          style="display:inline-block;margin-left:6px;background:#2563eb;color:#fff;font-size:9px;font-weight:700;border-radius:9px;padding:1px 6px;min-width:16px;line-height:1.2;"></span>
                </button>
            </div>

            {{-- 個人メール時に複数アカウントあれば切替プルダウン --}}
            <template x-if="inboxScope === 'personal' && personalMailAccounts.length >= 2">
                <div style="padding:6px 8px;border-bottom:1px solid #f3f4f6;background:#fafafa;">
                    <select :value="selectedPersonalAccountId === null ? 'all' : String(selectedPersonalAccountId)"
                            @change="setPersonalAccount($event.target.value)"
                            style="width:100%;background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:4px 8px;font-size:11px;outline:none;font-weight:600;color:#374151;">
                        <option value="all">すべての個人アカウント</option>
                        <template x-for="a in personalMailAccounts" :key="a.id">
                            <option :value="String(a.id)" x-text="a.name + ' (' + a.email_address + ')'"></option>
                        </template>
                    </select>
                </div>
            </template>

            {{-- ルーム/スレッド 横断検索 --}}
            <div style="padding:6px 8px;border-bottom:1px solid #f3f4f6;">
                <div style="position:relative;">
                    <i class="fas fa-search" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:10px;"></i>
                    <input type="text" x-model="sidebarSearchQuery"
                           placeholder="ルーム/スレッド検索"
                           style="width:100%;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:4px 8px 4px 24px;font-size:11px;outline:none;">
                    <button x-show="sidebarSearchQuery" @click="sidebarSearchQuery = ''"
                            style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ca3af;font-size:10px;padding:2px;"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto py-1" style="min-height:0;">
                {{-- すべて (フィルタなし). 件数は status 別に色分け (受信=青/保留=琥珀/承認待ち=橙). --}}
                <div @click="setRoomFilter('all')"
                     :class="emailRoomFilterId === 'all' ? 'mail-room-item active' : 'mail-room-item'"
                     title="ルームフィルターを外して全スレッドを表示">
                    <i class="fas fa-inbox" style="font-size:11px;color:#3b82f6;"></i>
                    <span class="name" style="font-weight:700;">すべて</span>
                    <span style="display:inline-flex;gap:3px;flex-shrink:0;">
                        <span class="badge-email-unread" x-show="globalInboxCount > 0"
                              :title="'全体: 受信 ' + globalInboxCount + ' 件'">
                            <i class="fas fa-envelope"></i><span x-text="globalInboxCount"></span>
                        </span>
                        <span class="badge-email-hold" x-show="globalHoldCount > 0"
                              :title="'全体: 保留 ' + globalHoldCount + ' 件'">
                            <i class="fas fa-pause"></i><span x-text="globalHoldCount"></span>
                        </span>
                        <span class="badge-email-pending" x-show="globalPendingCount > 0"
                              :title="'全体: 承認待ち ' + globalPendingCount + ' 件'">
                            <i class="fas fa-hourglass-half"></i><span x-text="globalPendingCount"></span>
                        </span>
                        {{-- 後方互換: 旧 API レスポンス (内訳なし) のときは合計バッジ 1 個だけ表示 --}}
                        <span class="badge-email-unread"
                              x-show="(globalInboxCount + globalHoldCount + globalPendingCount) === 0 && globalReceivedCount > 0"
                              :title="'全体の受信スレッド ' + globalReceivedCount + ' 件'">
                            <i class="fas fa-envelope"></i><span x-text="globalReceivedCount"></span>
                        </span>
                    </span>
                </div>

                {{-- ルーム未設定: どのルームにも紐付いていないスレッドだけを表示. 同様に status 別色分け. --}}
                <div @click="setRoomFilter('none')"
                     :class="emailRoomFilterId === 'none' ? 'mail-room-item active' : 'mail-room-item'"
                     title="どのルームにも未登録のスレッドだけ表示">
                    <i class="fas fa-folder-minus" style="font-size:11px;color:#f59e0b;"></i>
                    <span class="name" style="font-weight:700;">ルーム未設定</span>
                    <span style="display:inline-flex;gap:3px;flex-shrink:0;">
                        <span class="badge-email-unread" x-show="unroutedInboxCount > 0"
                              :title="'未振り分け: 受信 ' + unroutedInboxCount + ' 件'">
                            <i class="fas fa-envelope"></i><span x-text="unroutedInboxCount"></span>
                        </span>
                        <span class="badge-email-hold" x-show="unroutedHoldCount > 0"
                              :title="'未振り分け: 保留 ' + unroutedHoldCount + ' 件'">
                            <i class="fas fa-pause"></i><span x-text="unroutedHoldCount"></span>
                        </span>
                        <span class="badge-email-pending" x-show="unroutedPendingCount > 0"
                              :title="'未振り分け: 承認待ち ' + unroutedPendingCount + ' 件'">
                            <i class="fas fa-hourglass-half"></i><span x-text="unroutedPendingCount"></span>
                        </span>
                        {{-- 後方互換 --}}
                        <span class="badge-email-unread"
                              x-show="(unroutedInboxCount + unroutedHoldCount + unroutedPendingCount) === 0 && unroutedReceivedCount > 0"
                              :title="'未振り分けの受信スレッド ' + unroutedReceivedCount + ' 件'">
                            <i class="fas fa-envelope"></i><span x-text="unroutedReceivedCount"></span>
                        </span>
                    </span>
                </div>

                {{-- 共有ルーム (折りたたみ可 / 並び順切替可) --}}
                <div class="mail-rooms-section" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;"
                     @click="toggleSharedRoomsCollapsed()">
                    <span><i class="fas" :class="sharedRoomsCollapsed ? 'fa-chevron-right' : 'fa-chevron-down'" style="font-size:8px;margin-right:4px;"></i>共有ルーム</span>
                    <div style="display:flex;align-items:center;gap:6px;">
                        {{-- 並び順トグル: 更新順 ⇄ あいうえお順 (50 音グルーピング) --}}
                        <button @click.stop="toggleSharedRoomSortMode()"
                                :title="sharedRoomSortMode === 'aiueo' ? '更新順に切替' : 'あいうえお順 (50 音グループ) に切替'"
                                style="background:none;border:none;color:#6b7280;font-size:11px;padding:0;cursor:pointer;">
                            <i class="fas" :class="sharedRoomSortMode === 'aiueo' ? 'fa-sort-alpha-down' : 'fa-clock-rotate-left'"></i>
                        </button>
                        <button @click.stop="openMailCreateRoom(false)" title="新規共有ルームを作成"
                                style="background:none;border:none;color:#6b7280;font-size:11px;padding:0;cursor:pointer;">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                {{-- 「あいうえお」モード時はセクション見出し (あ行/か行/…) と通常行を混在で流す.
                     `display:contents` の外側 div で wrap して、内部 template の二分岐で
                     見出しと行を切り替える. --}}
                <template x-for="item in (sharedRoomsCollapsed ? [] : filteredSharedRoomsForRender)" :key="item.key">
                    <div style="display:contents;">
                        <template x-if="item.kind === 'header'">
                            <div @click.stop="toggleKanaGroup(item.row)"
                                 class="mail-kana-header"
                                 style="padding:4px 12px;font-size:10px;font-weight:800;color:#9ca3af;cursor:pointer;display:flex;align-items:center;gap:6px;user-select:none;letter-spacing:0.05em;">
                                <i class="fas" :class="kanaGroupCollapsed[item.row] ? 'fa-chevron-right' : 'fa-chevron-down'" style="font-size:8px;"></i>
                                <span x-text="item.row"></span>
                                <span style="color:#6b7280;font-weight:700;" x-text="'(' + item.count + ')'"></span>
                            </div>
                        </template>
                        <template x-if="item.kind === 'room'">
                            <div @click="setRoomFilter(String(item.data.id))"
                                 :class="(isMailRoomInSelection(item.data) ? 'mail-room-item active' : 'mail-room-item') + (isRoomHidden(item.data) ? ' is-hidden' : '')"
                                 :style="'position:relative;padding-left:' + (8 + ((item.depth||0) * 12)) + 'px;'">
                                {{-- ピン留めボタン (per-user). 個別にピン留めできるので他ユーザに影響しない. --}}
                                <button type="button" @click.stop="togglePinSidebarRoom(item.data)"
                                        class="mail-room-link-btn"
                                        :title="item.data.is_pinned_chat ? 'ピン留めを解除' : 'ピン留め (自分だけ)'"
                                        :style="'background:none;border:none;cursor:pointer;padding:0 4px;color:' + (item.data.is_pinned_chat ? '#f59e0b' : '#d1d5db') + ';'">
                                    <i class="fas fa-thumbtack" style="font-size:9px;"></i>
                                </button>
                                {{-- 親子関係: 子を持つルームは折りたたみシェブロン. それ以外は # マーク. --}}
                                <template x-if="item.hasChildren">
                                    <button type="button" @click.stop="toggleRoomBranch(item.data.id)"
                                            class="mail-room-chevron"
                                            style="background:none;border:none;color:#6b7280;font-size:8px;padding:0 4px 0 0;cursor:pointer;"
                                            :title="roomBranchCollapsed[item.data.id] ? '子ルームを表示' : '子ルームを折りたたむ'">
                                        <i class="fas" :class="roomBranchCollapsed[item.data.id] ? 'fa-chevron-right' : 'fa-chevron-down'"></i>
                                    </button>
                                </template>
                                <template x-if="!item.hasChildren && !item.data.is_pinned_chat">
                                    <span class="hash" :style="(item.depth||0) > 0 ? 'color:#a78bfa;' : ''">#</span>
                                </template>
                                <span class="name" x-text="item.data.name"></span>
                                {{-- バンドル先スレッドの件数バッジを status 別に色分け表示.
                                     受信 (inbox)=青 / 保留 (hold)=琥珀 / 承認待ち=橙. 0 件のものは出さない.
                                     後方互換: inbox_email_count が無い古いレスポンスは received_email_count を青で表示. --}}
                                <template x-if="item.data.inbox_email_count !== undefined">
                                    <span style="display:inline-flex;gap:3px;flex-shrink:0;">
                                        <span class="badge-email-unread" x-show="item.data.inbox_email_count > 0"
                                              :title="'受信 ' + item.data.inbox_email_count + ' 件'">
                                            <i class="fas fa-envelope"></i><span x-text="item.data.inbox_email_count"></span>
                                        </span>
                                        <span class="badge-email-hold" x-show="item.data.hold_email_count > 0"
                                              :title="'保留 ' + item.data.hold_email_count + ' 件'">
                                            <i class="fas fa-pause"></i><span x-text="item.data.hold_email_count"></span>
                                        </span>
                                        <span class="badge-email-pending" x-show="item.data.pending_email_count > 0"
                                              :title="'承認待ち ' + item.data.pending_email_count + ' 件'">
                                            <i class="fas fa-hourglass-half"></i><span x-text="item.data.pending_email_count"></span>
                                        </span>
                                    </span>
                                </template>
                                <template x-if="item.data.inbox_email_count === undefined">
                                    <span class="badge-email-unread" x-show="item.data.received_email_count > 0"
                                          :title="'受信スレッド ' + item.data.received_email_count + ' 件'">
                                        <i class="fas fa-envelope"></i><span x-text="item.data.received_email_count"></span>
                                    </span>
                                </template>
                                {{-- チャット未読 / メンションバッジ (chats 画面と同じシグナル) --}}
                                <span class="badge-mention" x-show="(item.data.mention_chat_count||0) > 0"
                                      x-text="item.data.mention_chat_count" title="未読メンション"></span>
                                <span class="badge-count"
                                      x-show="(item.data.mention_chat_count||0) === 0 && (item.data.unread_chat_count||0) > 0"
                                      x-text="item.data.unread_chat_count" title="未読チャット"></span>
                                <span x-show="isRoomHidden(item.data)" class="chat-hidden-badge" title="自分で非表示にしているルームです">
                                    <i class="fas fa-eye-slash" style="font-size:8px;"></i>非表示中
                                </span>
                                <button x-show="isRoomHidden(item.data)" @click.stop="unhideRoom(item.data.id)" class="mail-room-link-btn"
                                        style="opacity:1;color:#059669;"
                                        title="再表示">
                                    <i class="fas fa-undo" style="font-size:9px;"></i>
                                </button>
                                <button x-show="!isRoomHidden(item.data)" @click.stop="toggleHideRoom(item.data.id)" class="mail-room-link-btn"
                                        title="このルームを非表示にする">
                                    <i class="fas fa-eye-slash" style="font-size:9px;"></i>
                                </button>
                                {{-- ルーム編集ボタン (共有ルームは閲覧者なら全員、個人ルームは作成者のみ) --}}
                                <button x-show="canEditMailRoom(item.data)"
                                        @click.stop="openMailEditRoom(item.data)" class="mail-room-link-btn"
                                        title="このルームを編集">
                                    <i class="fas fa-pen" style="font-size:9px;"></i>
                                </button>
                                <button @click.stop="deleteEmailRoom(item.data, $event)" class="mail-room-del-btn"
                                        title="このルームを削除">
                                    <i class="fas fa-times" style="font-size:9px;"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="!sharedRoomsCollapsed && filteredSharedRooms.length === 0">
                    <p class="text-center" style="color:#9ca3af;font-size:11px;padding:6px;" x-text="sidebarSearchQuery ? '該当なし' : 'なし'"></p>
                </template>

                {{-- 個人ルーム (折りたたみ可) --}}
                <div class="mail-rooms-section" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;"
                     @click="togglePersonalRoomsCollapsed()">
                    <span><i class="fas" :class="personalRoomsCollapsed ? 'fa-chevron-right' : 'fa-chevron-down'" style="font-size:8px;margin-right:4px;"></i>個人ルーム</span>
                    <button @click.stop="openMailCreateRoom(true)" title="新規個人ルームを作成"
                            style="background:none;border:none;color:#a78bfa;font-size:11px;padding:0;cursor:pointer;">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <template x-for="r in (personalRoomsCollapsed ? [] : filteredPersonalRooms)" :key="'mail-personal-' + r.id">
                    <div @click="setRoomFilter(String(r.id))"
                         :class="(isMailRoomInSelection(r) ? 'mail-room-item active' : 'mail-room-item') + (isRoomHidden(r) ? ' is-hidden' : '')"
                         style="position:relative;">
                        {{-- ピン留めボタン (per-user). 個人ルームでもピン位置は自分だけのもの. --}}
                        <button type="button" @click.stop="togglePinSidebarRoom(r)"
                                class="mail-room-link-btn"
                                :title="r.is_pinned_chat ? 'ピン留めを解除' : 'ピン留め (自分だけ)'"
                                :style="'background:none;border:none;cursor:pointer;padding:0 4px;color:' + (r.is_pinned_chat ? '#f59e0b' : '#d1d5db') + ';'">
                            <i class="fas fa-thumbtack" style="font-size:9px;"></i>
                        </button>
                        <i class="fas fa-lock" style="font-size:9px;color:#a78bfa;" x-show="!r.is_pinned_chat"></i>
                        <span class="name" x-text="r.name"></span>
                        {{-- バンドル先スレッドの件数バッジ (status 別色分け) --}}
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
                        {{-- チャット未読 / メンションバッジ (chats 画面と同じシグナル) --}}
                        <span class="badge-mention" x-show="(r.mention_chat_count||0) > 0"
                              x-text="r.mention_chat_count" title="未読メンション"></span>
                        <span class="badge-count"
                              x-show="(r.mention_chat_count||0) === 0 && (r.unread_chat_count||0) > 0"
                              x-text="r.unread_chat_count" title="未読チャット"></span>
                        <span x-show="isRoomHidden(r)" class="chat-hidden-badge" title="自分で非表示にしているルームです">
                            <i class="fas fa-eye-slash" style="font-size:8px;"></i>非表示中
                        </span>
                        <button x-show="isRoomHidden(r)" @click.stop="unhideRoom(r.id)" class="mail-room-link-btn"
                                style="opacity:1;color:#059669;"
                                title="再表示">
                            <i class="fas fa-undo" style="font-size:9px;"></i>
                        </button>
                        <button x-show="!isRoomHidden(r)" @click.stop="toggleHideRoom(r.id)" class="mail-room-link-btn"
                                title="このルームを非表示にする">
                            <i class="fas fa-eye-slash" style="font-size:9px;"></i>
                        </button>
                        {{-- ルーム編集 (個人ルームは作成者のみ表示) --}}
                        <button x-show="canEditMailRoom(r)"
                                @click.stop="openMailEditRoom(r)" class="mail-room-link-btn"
                                title="このルームを編集">
                            <i class="fas fa-pen" style="font-size:9px;"></i>
                        </button>
                        <button @click.stop="deleteEmailRoom(r, $event)" class="mail-room-del-btn"
                                title="このルームを削除">
                            <i class="fas fa-times" style="font-size:9px;"></i>
                        </button>
                    </div>
                </template>
                <template x-if="!personalRoomsCollapsed && filteredPersonalRooms.length === 0">
                    <p class="text-center" style="color:#9ca3af;font-size:11px;padding:6px;" x-text="sidebarSearchQuery ? '該当なし' : 'なし'"></p>
                </template>

                {{-- ===== スレッド一覧 (折りたたみ可・縦スクロール) ===== --}}
                <div class="mail-rooms-section" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;"
                     @click="toggleSidebarThreadsCollapsed()">
                    <span><i class="fas" :class="sidebarThreadsCollapsed ? 'fa-chevron-right' : 'fa-chevron-down'" style="font-size:8px;margin-right:4px;"></i>スレッド</span>
                    <span x-show="visibleSidebarThreads.length > 0" style="color:#9ca3af;font-size:9px;font-weight:600;"
                          x-text="visibleSidebarThreads.length + '件'"></span>
                </div>
                <template x-if="!sidebarThreadsCollapsed">
                    <div style="max-height:320px;overflow-y:auto;">
                        <template x-for="t in visibleSidebarThreads" :key="'mail-th-' + t.id">
                            <div @click="loadThread(t.id)"
                                 :class="isSidebarThreadActive(t) ? 'mail-room-item active' : 'mail-room-item'"
                                 :title="t.subject"
                                 style="position:relative;">
                                <button @click.stop="togglePin(t.id)"
                                        class="mail-thread-pin-btn"
                                        :title="t.is_pinned ? 'ピン留め解除' : 'ピン留め'"
                                        :style="t.is_pinned ? 'color:#f59e0b;' : 'color:#d1d5db;'">
                                    <i class="fas fa-thumbtack" style="font-size:9px;"></i>
                                </button>
                                <span class="name" x-text="t.subject || '(件名なし)'"></span>
                                {{-- ルームに追加 (ホバー時表示) --}}
                                <button @click.stop="openAddToRoomModal(t.id)" class="mail-room-link-btn"
                                        title="このスレッドをルームに追加">
                                    <i class="fas fa-link" style="font-size:9px;"></i>
                                </button>
                                {{-- 非表示にする (サイドバーのみ非表示。メール一覧は影響なし) --}}
                                <button @click.stop="toggleHideThread(t.id)" class="mail-room-del-btn"
                                        title="このスレッドをサイドバーで非表示にする (メール一覧には影響しません)">
                                    <i class="fas fa-eye-slash" style="font-size:9px;"></i>
                                </button>
                            </div>
                        </template>
                        <template x-if="visibleSidebarThreads.length === 0 && sidebarSearchQuery">
                            <p class="text-center" style="color:#9ca3af;font-size:11px;padding:6px;">該当なし</p>
                        </template>
                        <template x-if="visibleSidebarThreads.length === 0 && !sidebarSearchQuery && (hiddenSidebarThreadIds.length === 0 || !showHiddenSidebarThreads)">
                            <p class="text-center" style="color:#9ca3af;font-size:11px;padding:6px;">なし</p>
                        </template>
                    </div>
                </template>

                {{-- 非表示も表示 トグル + 非表示中スレッド一覧 --}}
                <template x-if="!sidebarThreadsCollapsed">
                    <div>
                        <button type="button" @click="toggleShowHiddenSidebarThreads()"
                                style="width:calc(100% - 12px);margin:4px 6px;background:#f3f4f6;border:1px solid #e5e7eb;color:#4b5563;font-size:10px;font-weight:700;padding:4px 6px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:space-between;"
                                :title="showHiddenSidebarThreads ? '非表示中を隠す' : '非表示中も表示'">
                            <span>
                                <i class="fas" :class="showHiddenSidebarThreads ? 'fa-eye' : 'fa-eye-slash'" style="font-size:10px;margin-right:4px;"></i>
                                <span x-text="showHiddenSidebarThreads ? '非表示を隠す' : '非表示も表示'"></span>
                            </span>
                            <span x-show="hiddenSidebarThreadIds.length > 0"
                                  style="background:#fee2e2;color:#b91c1c;border-radius:9999px;padding:0 6px;font-size:9px;"
                                  x-text="hiddenSidebarThreadIds.length"></span>
                        </button>
                        <template x-if="showHiddenSidebarThreads">
                            <div style="max-height:200px;overflow-y:auto;">
                                <template x-for="t in hiddenVisibleSidebarThreads" :key="'mail-th-hidden-' + t.id">
                                    <div :class="String(selectedThreadId) === String(t.id) ? 'mail-room-item active' : 'mail-room-item'"
                                         style="position:relative;opacity:0.65;"
                                         @click="loadThread(t.id)"
                                         :title="t.subject">
                                        <i class="fas fa-eye-slash" style="font-size:9px;color:#dc2626;"></i>
                                        <span class="name" x-text="t.subject || '(件名なし)'" style="text-decoration:line-through;"></span>
                                        <button @click.stop="unhideSidebarThread(t.id)" class="mail-room-del-btn"
                                                style="opacity:1;color:#059669;"
                                                title="再表示">
                                            <i class="fas fa-undo" style="font-size:9px;"></i>
                                        </button>
                                    </div>
                                </template>
                                <template x-if="hiddenVisibleSidebarThreads.length === 0">
                                    <p class="text-center" style="color:#9ca3af;font-size:11px;padding:6px;">
                                        <span x-show="!sidebarSearchQuery">非表示中のスレッドはありません</span>
                                        <span x-show="sidebarSearchQuery">該当なし</span>
                                    </p>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
            {{-- 右端のリサイズハンドル (折りたたみ中は無効) --}}
            <div x-show="!mailRoomsCollapsed"
                 @mousedown.prevent="startResizeMailRooms($event)"
                 @dblclick="mailRoomsWidth = 200; try { localStorage.setItem('mailRoomsWidth', '200'); } catch(_) {}"
                 class="mail-rooms-resize"
                 :class="{ 'is-resizing': mailRoomsResizing }"
                 title="ドラッグで幅変更 / ダブルクリックで初期値に戻す"></div>
        </aside>

        {{-- スレッド一覧 --}}
        <div class="flex flex-col flex-shrink-0 overflow-hidden bg-white border-r border-gray-200 relative z-20 shadow-sm"
             :style="'width:' + threadWidth + 'px'">

            {{-- 操作ヘッダー (2段構成) --}}
            <div class="shrink-0 px-4 py-3 border-b border-gray-200 bg-white flex flex-col gap-2 relative">

                {{-- 1段目: 担当者フィルター (人アイコン + セレクト、検索と同じ見た目) --}}
                <div class="flex items-center gap-2 px-1 min-w-0">
                    <i class="fas fa-user-circle text-gray-300 text-[11px] shrink-0" title="担当者"></i>
                    <select @change="setAssigneeFilter($event.target.value)"
                            class="flex-1 bg-gray-50 border-0 rounded-lg px-3 py-1.5 text-[11px] font-bold text-gray-700 focus:ring-2 focus:ring-blue-100 outline-none shadow-inner cursor-pointer min-w-0">
                        <option value="all">全員を表示</option>
                        <option value="none">未設定</option>
                        <template x-for="user in users" :key="user.id">
                            <option :value="user.id" :selected="assigneeFilterId == user.id" x-text="user.name"></option>
                        </template>
                    </select>
                </div>

                {{-- 検索ボックス (件名/本文) — ルーム選択中はそのルーム内で絞り込み --}}
                <div class="flex items-center gap-2 px-1 min-w-0">
                    <i class="fas fa-search text-gray-300 text-[10px] shrink-0"></i>
                    <input type="text" x-model="searchQuery"
                           @input.debounce.300ms="loadThreads()"
                           :placeholder="emailRoomFilterId === 'none'
                                            ? '未設定スレッド内で検索...'
                                            : (emailRoomFilterId !== 'all' ? 'ルーム内で検索...' : '件名/本文で検索...')"
                           class="flex-1 bg-gray-50 border-0 rounded-lg px-3 py-1.5 text-[11px] font-medium text-gray-700 focus:ring-2 focus:ring-blue-100 outline-none shadow-inner min-w-0">
                    <button x-show="searchQuery" @click="searchQuery = ''; loadThreads()" class="shrink-0 text-gray-300 hover:text-red-500" title="クリア">
                        <i class="fas fa-times text-[10px]"></i>
                    </button>
                </div>

                {{-- 横断ナビは画面右上 (プロフィール横) のグローバル navbar に集約済み --}}

                {{-- 2段目: 左=同期+全表示トグル / 右=ピン+新規作成 --}}
                <div class="flex items-center justify-between gap-2">

                    {{-- 左寄せ: 同期 + 全表示トグル --}}
                    <div class="flex items-center gap-2">
                        {{-- 同期ボタン (失敗 / 部分成功時はビジュアル変化 + バッジ)
                             - <template x-if> はブラウザによって button 内で空 DOM になることがあるため
                               全部 <i> + x-show に切り替えて確実に視認できるようにする。
                             - クリック動作は Alpine のメソッドに集約。 --}}
                        <button @click="onSyncButtonClick()"
                                class="sync-btn"
                                :class="persistentSyncError ? 'sync-btn-error' : (persistentSyncWarning ? 'sync-btn-warning' : 'sync-btn-normal')"
                                :title="syncButtonTitle()">
                            {{-- 通常 (エラー/警告なし): 回転アイコン --}}
                            <i class="fas fa-sync-alt text-sm"
                               x-show="!persistentSyncError && !persistentSyncWarning"
                               :class="fetching ? 'animate-spin' : ''"
                               :style="fetching ? 'color:#2563eb;' : ''"></i>
                            {{-- 失敗状態: 警告三角 --}}
                            <i class="fas fa-exclamation-triangle text-sm"
                               x-show="persistentSyncError" x-cloak></i>
                            {{-- 部分成功状態: 警告丸 --}}
                            <i class="fas fa-exclamation-circle text-sm"
                               x-show="!persistentSyncError && persistentSyncWarning" x-cloak></i>
                            {{-- 連続失敗回数バッジ (2 回以上失敗してたら表示) --}}
                            <span class="sync-btn-badge"
                                  x-show="persistentSyncError && persistentSyncError.consecutive_failures && persistentSyncError.consecutive_failures > 1"
                                  x-cloak
                                  x-text="(persistentSyncError && persistentSyncError.consecutive_failures > 99) ? '99+' : (persistentSyncError ? persistentSyncError.consecutive_failures : '')"></span>
                        </button>

                        {{-- 全表示トグル (translateY でインラインに 10px 下げる) --}}
                        <label class="h-9 inline-flex items-center cursor-pointer" title="全ステータスを表示"
                               style="transform:translateY(10px);">
                            <input type="checkbox" id="all-status-toggle" :checked="allStatusMode" @change="toggleAllStatus()" class="sr-only">
                            <span style="position:relative;display:inline-block;width:44px;height:24px;border-radius:9999px;transition:background-color .2s;"
                                  :style="{ backgroundColor: allStatusMode ? '#2563eb' : '#e5e7eb' }">
                                <span style="position:absolute;top:2px;left:2px;width:20px;height:20px;background:#ffffff;border:1px solid #d1d5db;border-radius:9999px;box-shadow:0 1px 2px rgba(0,0,0,.1);transition:transform .2s;"
                                      :style="{ transform: allStatusMode ? 'translateX(20px)' : 'translateX(0)' }"></span>
                            </span>
                        </label>
                    </div>

                    {{-- 右寄せ: 選択モード + ピン + 新規作成 --}}
                    <div class="flex items-center gap-2">
                        {{--
                            複数選択モードに入る明示的なトグルボタン。
                            これまでは「行の長押し (500ms)」でしか selectionMode に入れず、
                            「マージや一括操作のやり方が分からない」という導線の悪さがあった。
                            このボタンで selectionMode を明示的に on/off できる。
                            on の時はバルクアクションバーが出てメニューからマージ等が選べる。
                        --}}
                        <button @click="toggleSelectionMode()"
                                :class="selectionMode ? 'bg-blue-100 text-blue-700 border-blue-200' : 'bg-white text-gray-400 border-gray-200 hover:bg-gray-50 hover:text-blue-600'"
                                class="h-9 w-9 inline-flex items-center justify-center rounded-lg border transition-all"
                                :title="selectionMode ? '複数選択モードを終了' : '複数選択モード (マージ・一括操作用)'">
                            <i class="fas fa-check-square text-sm"></i>
                        </button>

                        {{-- ピン留めボタン --}}
                        <button @click="togglePinnedOnly()"
                                :class="pinnedOnlyMode ? 'bg-amber-100 text-amber-600 border-amber-200' : 'bg-white text-gray-400 border-gray-200 hover:bg-gray-50 hover:text-amber-600'"
                                class="h-9 w-9 inline-flex items-center justify-center rounded-lg border transition-all"
                                title="ピン留めのみ表示">
                            <i class="fas fa-thumbtack text-sm"></i>
                        </button>

                        {{-- 新規作成ボタン --}}
                        {{-- キーボードショートカット ヘルプはグローバル navbar の「?」アイコンから開けます。
                             メール画面では Alpine ルートが @open-shortcuts-help.window をリッスンしています。 --}}
                        <button @click="openCompose()"
                                class="compose-btn h-9 w-9 inline-flex items-center justify-center rounded-lg transition-all"
                                style="background-color:#2563eb;color:#ffffff;border:1px solid #2563eb;box-shadow:0 1px 2px rgba(0,0,0,.06);"
                                title="新規作成 (新しいウィンドウ) — C">
                            <i class="fas fa-edit text-sm" style="color:#ffffff"></i>
                        </button>
                    </div>
                </div>
            </div>

            {{-- 複数選択アクションバー (薄いヘッダー + 縦メニュー方式) --}}
            <template x-if="selectionMode">
                <div class="bulk-action-bar" x-data="{ menuOpen: false }">
                    <div class="bulk-action-info">
                        <i class="fas fa-check-square"></i>
                        <strong x-text="selectedThreadIds.length"></strong>
                        <span>件選択中</span>
                        <span style="color:#9ca3af;font-size:11px;margin-left:2px;"
                              x-text="'/ ' + (threads.length || 0) + ' 件'"></span>
                    </div>
                    {{-- 全選択 / 全解除 トグル. 表示中スレッド (this.threads) を対象にする.
                         キーボードは Ctrl+A でも動くようにグローバルハンドラ側で拾う. --}}
                    <button type="button"
                            @click="toggleSelectAllVisible()"
                            :title="allVisibleThreadsSelected ? '表示中スレッドの選択をすべて外す' : '表示中スレッドをすべて選択 (Ctrl+A)'"
                            style="background:#ffffff;border:1px solid #cbd5e1;color:#1f2937;font-size:11px;font-weight:700;padding:5px 12px;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;"
                            onmouseover="this.style.backgroundColor='#eff6ff';this.style.borderColor='#60a5fa';this.style.color='#1d4ed8';"
                            onmouseout="this.style.backgroundColor='#ffffff';this.style.borderColor='#cbd5e1';this.style.color='#1f2937';">
                        <i class="fas" :class="allVisibleThreadsSelected ? 'fa-square' : 'fa-check-square'" style="font-size:11px;"></i>
                        <span x-text="allVisibleThreadsSelected ? '全解除' : '全選択'"></span>
                        <kbd class="rice-kbd" style="font-size:9px;padding:1px 5px;">Ctrl+A</kbd>
                    </button>
                    <div style="position:relative;">
                        <button @click="menuOpen = !menuOpen"
                                class="bulk-action-menu-trigger"
                                :class="{ 'is-open': menuOpen }">
                            <i class="fas fa-bolt"></i>
                            <span>アクション</span>
                            <i class="fas fa-chevron-down" style="font-size:9px;margin-left:2px;"></i>
                        </button>
                        <div x-show="menuOpen" @click.outside="menuOpen = false" x-cloak
                             class="bulk-action-menu">
                            <div class="bulk-action-menu-section">
                                <p class="bulk-action-menu-head">ステータス変更</p>
                                <button @click="updateSelectedStatus('inbox'); menuOpen = false" class="bulk-action-menu-item">
                                    <i class="fas fa-undo" style="color:#3b82f6;"></i><span>未対応に戻す</span>
                                </button>
                                <button @click="updateSelectedStatus('hold'); menuOpen = false" class="bulk-action-menu-item">
                                    <i class="fas fa-pause" style="color:#f59e0b;"></i><span>保留にする</span>
                                </button>
                                <button @click="updateSelectedStatus('completed'); menuOpen = false" class="bulk-action-menu-item">
                                    <i class="fas fa-check" style="color:#10b981;"></i><span>完了にする</span>
                                </button>
                                <button @click="updateSelectedStatus('no_action'); menuOpen = false" class="bulk-action-menu-item">
                                    <i class="fas fa-ban" style="color:#6b7280;"></i><span>対応不要にする</span>
                                </button>
                            </div>
                            <div class="bulk-action-menu-divider"></div>
                            <div class="bulk-action-menu-section">
                                <p class="bulk-action-menu-head">ピン留め</p>
                                <button @click="batchPinSelected(true); menuOpen = false" class="bulk-action-menu-item">
                                    <i class="fas fa-thumbtack" style="color:#f59e0b;"></i><span>一括ピン留め</span>
                                </button>
                                <button @click="batchPinSelected(false); menuOpen = false" class="bulk-action-menu-item">
                                    <i class="fas fa-thumbtack" style="color:#9ca3af;"></i><span>ピン留め解除</span>
                                </button>
                            </div>
                            <div class="bulk-action-menu-divider"></div>
                            <div class="bulk-action-menu-section">
                                <p class="bulk-action-menu-head">整理</p>
                                <button @click="openAddToRoomModal(selectedThreadIds); menuOpen = false" class="bulk-action-menu-item">
                                    <i class="fas fa-link" style="color:#2563eb;"></i><span>ルームに追加</span>
                                </button>
                                {{--
                                    マージボタン.
                                    @click は Alpine 経由でも、 onclick は vanilla JS で window イベントを撃つ。
                                    どちらかが効けば動く二重保険。 さらに onclick の console.log で
                                    「クリック自体が発火しているか」を DevTools で確認できる.
                                --}}
                                <button type="button"
                                        x-show="selectedThreadIds.length > 1"
                                        @click="mergeSelected(); menuOpen = false"
                                        onclick="console.log('[merge] button click fired (onclick)'); window.dispatchEvent(new CustomEvent('open-merge-modal'));"
                                        class="bulk-action-menu-item">
                                    <i class="fas fa-object-group" style="color:#7c3aed;"></i><span>マージ</span>
                                </button>
                            </div>
                            <div class="bulk-action-menu-divider"></div>
                            <div class="bulk-action-menu-section">
                                <p class="bulk-action-menu-head">迷惑メール</p>
                                <button @click="bulkMarkSpam(); menuOpen = false" class="bulk-action-menu-item">
                                    <i class="fas fa-shield-alt" style="color:#dc2626;"></i><span>迷惑メールに振り分け</span>
                                </button>
                            </div>
                            <div class="bulk-action-menu-divider"></div>
                            <button @click="batchDeleteSelected(); menuOpen = false" class="bulk-action-menu-item bulk-action-menu-item-danger">
                                <i class="fas fa-trash"></i><span>削除</span>
                            </button>
                        </div>
                    </div>
                    <button @click="cancelSelection()" class="bulk-action-close" title="選択モードを解除">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </template>

            {{-- 束ねたスレッド (ルーム選択中のみ表示。チャット画面と同じく解除可能)
                 件数が多いと帯がパンクして見にくいので、デフォルトでは先頭 10 件のみ表示し、
                 「+N 件」ボタンで全件展開できるようにする (展開状態は localStorage に保存)。 --}}
            <div x-show="emailRoomFilterId !== 'all' && emailRoomBundledThreads.length > 0"
                 class="shrink-0 bundle-band"
                 style="padding:6px 12px;background:#f9fafb;border-bottom:1px solid #e5e7eb;display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                <span style="font-size:10px;color:#6b7280;font-weight:700;text-transform:uppercase;">
                    束ねたスレッド
                    <span x-show="emailRoomBundledThreads.length > 0" style="color:#9ca3af;font-weight:600;text-transform:none;margin-left:2px;"
                          x-text="'(' + emailRoomBundledThreads.length + ')'"></span>
                    :
                </span>
                {{--
                    束ねたスレッドのチップ。マウスオーバー時に hoveredThreadId をセットして、
                    メインリストに表示されていない (= ステータスフィルタで弾かれた等の) スレッドでも
                    その上にマウスを置けば D / E / L 等のショートカットが効くようにする保険。
                    クリックで loadThread() を呼びスレッドを開く (チップ自体が UI として
                    クリック導線になる)。
                --}}
                <template x-for="bt in visibleBundleChips" :key="'mail-bundle-' + bt.id">
                    <span class="bundle-chip"
                          @mouseenter="hoveredThreadId = bt.id"
                          @mouseleave="hoveredThreadId = null"
                          @click="loadThread(bt.id)"
                          style="cursor:pointer;">
                        <i class="fas fa-envelope" style="font-size:9px;color:#9ca3af;"></i>
                        <span x-text="bt.subject"></span>
                        <button @click.stop="detachEmailRoomThread(bt.id)" title="紐付けを外す"><i class="fas fa-times"></i></button>
                    </span>
                </template>
                {{-- 「展開する」 / 「たたむ」 トグル。
                     折りたたみ時はチップを 1 件も出さず、件数だけのコンパクト表示にして、
                     ユーザがボタンを押した時だけ全件展開する (要望: 「先頭もなにも表示せず数字だけに」)。 --}}
                <button type="button"
                        x-show="emailRoomBundledThreads.length > 0"
                        @click="toggleBundleBandExpanded()"
                        style="background:#ffffff;border:1px dashed #cbd5e1;color:#475569;font-size:10px;font-weight:700;padding:3px 10px;border-radius:999px;cursor:pointer;display:inline-flex;align-items:center;gap:4px;"
                        onmouseover="this.style.backgroundColor='#eff6ff';this.style.borderColor='#60a5fa';this.style.color='#1d4ed8';"
                        onmouseout="this.style.backgroundColor='#ffffff';this.style.borderColor='#cbd5e1';this.style.color='#475569';"
                        :title="bundleBandExpanded ? '束ねたスレッド一覧を隠す' : ('束ねたスレッド ' + emailRoomBundledThreads.length + ' 件を展開')">
                    <i class="fas" :class="bundleBandExpanded ? 'fa-chevron-up' : 'fa-chevron-down'" style="font-size:8px;"></i>
                    <span x-show="!bundleBandExpanded">展開</span>
                    <span x-show="bundleBandExpanded">たたむ</span>
                </button>
            </div>

            {{-- ステータスタブ (件数連動: 対応不要・承認待ち・迷惑メールは 0 件なら非表示) --}}
            <div class="status-tab-band shrink-0 px-3 py-2 border-b border-gray-200 bg-gray-50 flex items-center gap-2">
                <div class="flex items-center gap-1 bg-gray-200/50 p-1 rounded-xl shadow-inner flex-1 overflow-hidden">
                    <template x-for="tab in visibleStatusTabs" :key="'tab-' + tab">
                        <button @click="setLeftTab(tab)"
                                :class="leftTab === tab ? (tab === 'spam' ? 'bg-white shadow text-red-600' : 'bg-white shadow text-blue-600') : 'text-gray-500 hover:text-gray-800'"
                                class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all truncate"
                                :title="tab === 'spam' ? '迷惑メール (ブロックルール一致 / 手動指定)' : ''">
                            <span x-text="statusLabels[tab]"></span>
                            <span x-show="(statusCounts[tab]||0) > 0"
                                  style="margin-left:4px;font-size:9px;color:#9ca3af;"
                                  x-text="'(' + statusCounts[tab] + ')'"></span>
                        </button>
                    </template>
                </div>
                {{-- 隠れているタブを呼び出すための ・・・ メニュー --}}
                <div style="position:relative;" x-data="{ open: false }" @click.outside="open = false"
                     x-show="hiddenStatusTabs.length > 0" x-cloak>
                    <button @click="open = !open" class="p-2 text-gray-400 hover:text-blue-600" title="非表示のタブを表示">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                    <div x-show="open" x-cloak
                         style="position:absolute;right:0;top:100%;margin-top:4px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,0.12);padding:4px;min-width:160px;z-index:20;">
                        <template x-for="tab in hiddenStatusTabs" :key="'hidden-tab-' + tab">
                            <button @click="setLeftTab(tab); open = false"
                                    class="w-full text-left px-3 py-1.5 text-[11px] font-bold rounded hover:bg-gray-50 inline-flex items-center justify-between"
                                    style="display:flex;width:100%;"
                                    :style="tab === 'spam' ? 'color:#b91c1c;' : 'color:#374151;'">
                                <span x-text="statusLabels[tab]"></span>
                                <span style="font-size:9px;color:#9ca3af;font-weight:700;"
                                      x-text="'(' + (statusCounts[tab] || 0) + ')'"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <button @click="toggleSort()" class="p-2 text-gray-400 hover:text-blue-600">
                    <i class="fas" :class="sortOrder === 'desc' ? 'fa-sort-amount-down' : 'fa-sort-amount-up'"></i>
                </button>
            </div>

            {{--
                ゴミ箱ビュー (leftTab === 'trash' のとき表示).
                スレッド単位 / 個別メール単位の 2 タブを持ち、各行に「復元」「完全削除」ボタン.
                30 日経過後は purge コマンドで自動完全削除されるため、表示時に残日数を出す.
            --}}
            <div x-show="leftTab === 'trash'" x-cloak class="flex-1 min-h-0 overflow-y-auto bg-white custom-scrollbar relative">
                <div class="px-3 py-2 border-b border-gray-200 bg-gray-50 flex items-center gap-2">
                    <button @click="loadTrash('thread')"
                            :class="trashKind === 'thread' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:bg-gray-100'"
                            class="px-3 py-1.5 rounded-lg text-[11px] font-black">スレッド</button>
                    <button @click="loadTrash('email')"
                            :class="trashKind === 'email' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:bg-gray-100'"
                            class="px-3 py-1.5 rounded-lg text-[11px] font-black">個別メール</button>
                    <button @click="loadTrash()" class="ml-auto p-1.5 text-gray-400 hover:text-blue-600" title="再読み込み">
                        <i class="fas fa-redo text-[11px]"></i>
                    </button>
                </div>
                <div class="px-3 py-2 text-[10px] text-gray-500 bg-amber-50 border-b border-amber-200">
                    <i class="fas fa-info-circle text-amber-500"></i>
                    ゴミ箱に入って <span x-text="trashRetentionDays"></span> 日経過すると自動的に完全削除されます.
                </div>
                <div x-show="trashLoading" class="px-3 py-4 text-center text-gray-400 text-[11px]">
                    <i class="fas fa-spinner fa-spin"></i> 読み込み中...
                </div>
                <div x-show="!trashLoading && trashItems.length === 0" class="px-3 py-8 text-center text-gray-400 text-[12px]">
                    ゴミ箱は空です.
                </div>
                <template x-for="item in trashItems" :key="(trashKind === 'email' ? 'e' : 't') + '-' + item.id">
                    <div class="px-3 py-2 border-b border-gray-100 hover:bg-gray-50">
                        <div class="flex items-start gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] font-bold text-gray-800 truncate"
                                     x-text="item.subject || item.thread_subject || '(件名なし)'"></div>
                                <div class="text-[10px] text-gray-500 mt-0.5 truncate">
                                    <template x-if="trashKind === 'email'">
                                        <span>
                                            <span x-text="item.from_label || '(差出人不明)'"></span>
                                            <span x-show="item.received_at"> · <span x-text="item.received_at"></span></span>
                                        </span>
                                    </template>
                                    <template x-if="trashKind === 'thread'">
                                        <span>
                                            <span x-show="item.customer_name" x-text="item.customer_name"></span>
                                            <span x-show="item.last_email_at"> · <span x-text="item.last_email_at"></span></span>
                                        </span>
                                    </template>
                                </div>
                                <div class="text-[9px] text-gray-400 mt-1">
                                    削除日時: <span x-text="item.trashed_at || '-'"></span>
                                    <span class="ml-2" x-show="item.days_left !== null && item.days_left !== undefined">
                                        <i class="fas fa-clock"></i>
                                        あと <span x-text="Math.max(0, Math.floor(item.days_left))"></span> 日で自動削除
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1 shrink-0">
                                <button @click="restoreFromTrash(item)"
                                        class="px-2 py-1 text-[10px] font-black bg-blue-50 text-blue-700 hover:bg-blue-100 rounded">
                                    <i class="fas fa-undo"></i> 復元
                                </button>
                                <button @click="hardDeleteFromTrash(item)"
                                        class="px-2 py-1 text-[10px] font-black bg-red-50 text-red-700 hover:bg-red-100 rounded">
                                    <i class="fas fa-trash"></i> 完全削除
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- 仮想スクロールリスト (通常タブ: ゴミ箱以外) --}}
            <div x-show="leftTab !== 'trash'" class="flex-1 min-h-0 overflow-y-auto bg-white custom-scrollbar relative" id="email-list-container" @scroll.passive="handleScroll()">
                <div :style="'height: ' + totalListHeight + 'px; position: relative;'">
                    <div :style="'transform: translateY(' + listPaddingTop + 'px)'">
                        <template x-for="thread in visibleThreads" :key="thread.id">
                            <div @mousedown="startLongPress(thread, $event)"
                                 @mouseup="cancelLongPress()"
                                 @mouseleave="cancelLongPress(); hoveredThreadId = null"
                                 @mouseenter="hoveredThreadId = thread.id"
                                 @click="if(!isLongPressing){ selectionMode ? toggleSelection(thread) : loadThread(thread.id) }"
                                 class="email-item group/row w-full cursor-pointer border-b border-gray-100 hover:bg-blue-50 transition-all duration-200 thread-list-row relative overflow-hidden"
                                 :style="'height: ' + virtualScroll.rowHeight + 'px;overflow:hidden;'"
                                 :class="(selectedThreadId === thread.id ? 'bg-blue-50 ring-1 ring-inset ring-blue-200' : (selectedThreadIds.includes(thread.id) ? 'bg-blue-50/50' : ''))
                                         + (hoveredThreadId === thread.id && selectedThreadId !== thread.id ? ' ring-2 ring-inset ring-amber-300' : '')">

                                {{--
                                    ホバー時に表示するクイックアクション。
                                    スレッドを開かなくても 完了 / 迷惑メール / ルーム追加 / 削除 を
                                    一覧から直接実行できるようにする (Gmail / Spark 風).
                                    spam 状態のスレッドではボタンの意味が変わるので、
                                    通常時とは色 / アイコン / ハンドラが切り替わる.
                                --}}
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 z-10 flex items-center gap-1 opacity-0 group-hover/row:opacity-100 transition-all"
                                     x-show="!selectionMode">
                                    {{-- 完了 (spam なら受信箱に戻すボタンに切替) --}}
                                    <button x-show="thread.status !== 'spam' && thread.status !== 'completed'"
                                            @click.stop="quickUpdateStatus(thread, 'completed')"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:text-green-600 hover:border-green-200 hover:bg-green-50 shadow-sm"
                                            title="完了にする (E)">
                                        <i class="fas fa-check-double text-xs"></i>
                                    </button>
                                    <button x-show="thread.status === 'completed'"
                                            @click.stop="quickUpdateStatus(thread, 'inbox')"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-green-50 border border-green-200 text-green-600 hover:text-green-700 hover:border-green-300 hover:bg-green-100 shadow-sm"
                                            title="受信箱に戻す (I)">
                                        <i class="fas fa-undo text-xs"></i>
                                    </button>
                                    {{-- 迷惑メール (spam なら解除ボタンに切替) --}}
                                    <button x-show="thread.status !== 'spam'"
                                            @click.stop="quickMarkSpam(thread)"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:text-orange-600 hover:border-orange-200 hover:bg-orange-50 shadow-sm"
                                            title="迷惑メールに振り分け (S)">
                                        <i class="fas fa-shield-alt text-xs"></i>
                                    </button>
                                    <button x-show="thread.status === 'spam'"
                                            @click.stop="quickUnmarkSpam(thread)"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-600 hover:text-emerald-700 hover:border-emerald-300 hover:bg-emerald-100 shadow-sm"
                                            title="迷惑メールを解除">
                                        <i class="fas fa-undo text-xs"></i>
                                    </button>
                                    <button @click.stop="openAddToRoomModal(thread.id)"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50 shadow-sm"
                                            title="ルームに追加 (L)">
                                        <i class="fas fa-link text-xs"></i>
                                    </button>
                                    <button @click.stop="deleteThreadById(thread.id, thread.subject)"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:text-red-500 hover:border-red-200 hover:bg-red-50 shadow-sm"
                                            title="このスレッドを削除 (Del)">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>

                                <div class="px-5 py-2 flex flex-col justify-center h-full gap-1">
                                    {{-- 1段目: 送信者 (メールアドレスを主表示。
                                         from_address に "@" が無い場合は実体がアドレスではないので表示名扱いに切り替え、
                                         小さい (送信元アドレスなし) 注記を付けて状態を見える化する。 --}}
                                    <div class="flex items-center gap-2 min-w-0">
                                        <template x-if="selectionMode">
                                            <input type="checkbox" class="w-3.5 h-3.5 text-blue-600 border-gray-300 rounded focus:ring-blue-500 shrink-0"
                                                   :checked="selectedThreadIds.includes(thread.id)" @click.stop="toggleSelection(thread)">
                                        </template>
                                        <i x-show="thread.is_pinned" class="fas fa-thumbtack text-amber-500 text-[11px] shrink-0"></i>
                                        <i x-show="thread.thread_merges_count > 0" class="fas fa-object-group text-blue-500 text-[11px] shrink-0" title="マージ済み"></i>
                                        <template x-if="thread.latest_email?.from_address && thread.latest_email.from_address.includes('@')">
                                            <span class="text-[13px] font-bold text-gray-900 truncate min-w-0"
                                                  :title="(thread.latest_email?.from_label ? thread.latest_email.from_label + ' ' : '') + '<' + thread.latest_email.from_address + '>'"
                                                  x-text="thread.latest_email.from_address"></span>
                                        </template>
                                        <template x-if="!thread.latest_email?.from_address || !thread.latest_email.from_address.includes('@')">
                                            <span class="text-[13px] text-gray-900 truncate min-w-0 inline-flex items-baseline gap-1"
                                                  :title="thread.latest_email
                                                    ? ((thread.latest_email.from_label || thread.latest_email.from_address || '送信者情報なし') + ' — メールヘッダから差出人アドレスを取得できませんでした')
                                                    : 'メール本文の取り込みに失敗した可能性があります (孤児スレッド)'">
                                                {{-- ケース A: latest_email は存在するが from が空/不正 — 取得できた表示名を出して "(アドレスなし)" を添える --}}
                                                <template x-if="thread.latest_email">
                                                    <span class="inline-flex items-baseline gap-1 min-w-0">
                                                        <span class="font-bold truncate"
                                                              x-text="thread.latest_email.from_label || thread.latest_email.from_address || '(差出人未設定)'"></span>
                                                        <span class="text-[10px] italic text-gray-400 shrink-0">(アドレスなし)</span>
                                                    </span>
                                                </template>
                                                {{-- ケース B: latest_email が null — 通常は has('emails') フィルタで一覧に出ないはずだが
                                                     何らかの理由で残ったケースをはっきり表示 (孤児スレッドの可視化) --}}
                                                <template x-if="!thread.latest_email">
                                                    <span class="inline-flex items-baseline gap-1 min-w-0">
                                                        <span class="font-bold truncate italic text-amber-600">(取り込みエラー)</span>
                                                        <span class="text-[10px] italic text-gray-400 shrink-0">メールなし</span>
                                                    </span>
                                                </template>
                                            </span>
                                        </template>
                                    </div>

                                    {{-- 2段目: 件名 (フル幅・最大2行) --}}
                                    <div class="text-[13px] text-gray-700 font-medium leading-snug break-words"
                                         style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"
                                         x-text="thread.subject"></div>

                                    {{-- 3段目: 日時 (単独行).
                                         ラベル/バッジ群と分離して読みやすくする (ユーザ要望).
                                         日付は常に 1 行目に表示し、ラベルや担当者バッジは 4段目に降ろす. --}}
                                    <div class="flex items-center min-h-[18px]">
                                        <span class="text-[11px] text-gray-400 font-medium inline-flex items-center gap-1">
                                            <i class="fas fa-clock text-[9px]"></i>
                                            <span x-text="thread.last_email_at"></span>
                                        </span>
                                    </div>

                                    {{-- 4段目: ラベル/バッジ (未読チャット / ステータス / 担当者 / ルーム / +N).
                                         折り返し許容 (flex-wrap) でバッジ数が多くてもクリップせず全て見える. --}}
                                    <div class="flex items-center gap-1.5 min-h-[18px] flex-wrap">
                                        {{-- 未読チャットバッジ --}}
                                        <template x-if="thread.unread_chat_count > 0">
                                            <span class="px-2 py-0.5 rounded-full text-[9px] 2xl:text-[10px] font-black border inline-flex items-center gap-1 shadow-sm animate-pulse"
                                                  style="background:#fef3c7;color:#92400e;border-color:#fde68a;"
                                                  :title="'未読チャット ' + thread.unread_chat_count + ' 件'">
                                                <i class="fas fa-comment-dots"></i>
                                                <span x-text="thread.unread_chat_count"></span>
                                            </span>
                                        </template>

                                        {{-- ステータスバッジ (全表示モード時) --}}
                                        <template x-if="allStatusMode">
                                            <span class="px-2 py-0.5 rounded text-[8px] 2xl:text-[10px] font-black uppercase shadow-sm border inline-flex items-center"
                                                :class="{
                                                    'bg-blue-100 text-blue-700 border-blue-200': thread.status === 'inbox' || !thread.status,
                                                    'bg-amber-100 text-amber-800 border-amber-200': thread.status === 'hold',
                                                    'bg-green-100 text-green-800 border-green-200': thread.status === 'completed',
                                                    'bg-gray-100 text-gray-700 border-gray-200': thread.status === 'no_action',
                                                    'bg-orange-100 text-orange-800 border-orange-200': thread.status === 'pending'
                                                }"
                                                x-text="statusLabels[thread.status] || '受信'"></span>
                                        </template>

                                        {{-- 担当者 --}}
                                        <span x-show="thread.assignee"
                                              class="bg-gray-100 px-2 py-0.5 rounded text-[9px] 2xl:text-[10px] font-black text-gray-600 border border-gray-200 inline-flex items-center gap-1 shadow-sm">
                                            <i class="fas fa-user-circle text-gray-400"></i>
                                            <span x-text="thread.assignee?.name"></span>
                                        </span>

                                        {{-- バンドル先ルーム (「すべてのルーム」表示時のみ).
                                             行内表示は **先頭 1 件のみ** + 残りは "+N" 展開ボタン.
                                             名前は ellipsis で短く出し、 +N をクリックすると全件ポップオーバ. --}}
                                        <template x-if="emailRoomFilterId === 'all' && (thread.bundled_rooms || []).length > 0">
                                            <button type="button" @click.stop="setRoomFilter(String((thread.bundled_rooms || [])[0].id))"
                                                    class="px-2 py-0.5 rounded text-[9px] 2xl:text-[10px] font-black border inline-flex items-center gap-1 shadow-sm shrink-0"
                                                    :style="((thread.bundled_rooms || [])[0].is_private
                                                        ? 'background:#ede9fe;color:#6d28d9;border-color:#ddd6fe;'
                                                        : 'background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;') + 'max-width:140px;overflow:hidden;'"
                                                    :title="((thread.bundled_rooms || [])[0].is_private ? '個人ルーム: ' : '共有ルーム: ') + (thread.bundled_rooms || [])[0].name + ' に絞り込む'">
                                                <i :class="(thread.bundled_rooms || [])[0].is_private ? 'fas fa-lock' : 'fas fa-hashtag'" style="font-size:8px;"></i>
                                                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:110px;display:inline-block;vertical-align:middle;"
                                                      x-text="(thread.bundled_rooms || [])[0].name"></span>
                                            </button>
                                        </template>
                                        {{-- 1 個を超えるルームがある場合の "+N" 展開ボタン.
                                             クリックで全ルームをフローティング パネルに展開する. --}}
                                        <template x-if="emailRoomFilterId === 'all' && (thread.bundled_rooms || []).length > 1">
                                            <button type="button"
                                                    @click.stop="openRoomChipPopover(thread, $event)"
                                                    class="px-1.5 py-0.5 rounded text-[9px] 2xl:text-[10px] font-black border inline-flex items-center shrink-0"
                                                    style="background:#f3f4f6;color:#4b5563;border-color:#e5e7eb;cursor:pointer;"
                                                    :title="'残り ' + ((thread.bundled_rooms || []).length - 1) + ' 件のルームを表示'"
                                                    x-text="'+' + ((thread.bundled_rooms || []).length - 1)"></button>
                                        </template>

                                    </div>
                                </div>
                                <div x-show="selectedThreadId === thread.id" class="absolute left-0 top-0 w-1.5 h-full bg-blue-600"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <div class="absolute top-0 right-0 w-1 h-full cursor-col-resize hover:bg-blue-500 z-50" @mousedown.prevent="startResizeThreadList($event)"></div>
        </div>

        {{-- ワークスペース (右ペイン) --}}
        <div class="flex-1 flex flex-col min-w-0 bg-white z-10 relative">

            <div x-show="!selectedThread" class="flex-1 flex flex-col items-center justify-center bg-gray-50 px-6">
                <div class="w-20 h-20 bg-white rounded-3xl shadow-xl flex items-center justify-center text-gray-300 mb-6">
                    <i class="fas fa-envelope-open-text fa-2x"></i>
                </div>
                <p class="text-base font-semibold text-gray-700">メールを選択してください</p>
                <p class="text-xs text-gray-400 mt-2 max-w-xs text-center leading-relaxed">左の一覧から選ぶと、ここに本文が表示されます。新しく書き始めるには右上の「新規作成」ボタンを押してください。</p>
            </div>

            <div x-show="selectedThread" class="flex-1 flex flex-col h-full overflow-hidden animate-in fade-in duration-300">
                {{-- ヘッダー --}}
                <div class="shrink-0 border-b border-gray-200 bg-white z-20 flex flex-col">
                    {{-- 1行目: アクションボタン --}}
                    <div class="px-5 py-2 flex items-center justify-between border-b border-gray-100 bg-white">
                        <div class="flex items-center gap-1">
                            {{-- 前/次ナビゲーション --}}
                            <div class="flex items-center gap-0.5 pr-2 mr-1 border-r border-gray-100" x-show="selectedThread">
                                <button @click="goToPrevThread()" title="前のスレッド"
                                    class="icon-btn text-gray-400 hover:text-blue-600 hover:bg-blue-50">
                                    <i class="fas fa-chevron-up text-xs"></i>
                                </button>
                                <button @click="goToNextThread()" title="次のスレッド"
                                    class="icon-btn text-gray-400 hover:text-blue-600 hover:bg-blue-50">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </button>
                            </div>
                            {{-- メインアクション --}}
                            <div class="flex items-center gap-1" x-show="selectedThread">
                                <button @click="updateThreadStatus(selectedThread, 'completed')" title="完了にする (E)"
                                    class="icon-btn bg-green-50 text-green-600 hover:bg-green-600 hover:text-white">
                                    <i class="fas fa-check-double text-xs"></i>
                                </button>
                                <button @click="updateThreadStatus(selectedThread, 'no_action')" title="対応不要にする"
                                    class="icon-btn bg-gray-50 text-gray-600 hover:bg-gray-500 hover:text-white">
                                    <i class="fas fa-ban text-xs"></i>
                                </button>
                                {{--
                                    迷惑メールボタン (スレッド上部).
                                    通常 → 振り分け / spam → 解除 にトグル表示。
                                    既存の三点リーダ内にもあるが、 ワンクリックで使えるよう
                                    主要アクションバーにも出す.
                                --}}
                                <button x-show="selectedThread?.status !== 'spam'"
                                        @click="markSelectedAsSpam()" title="迷惑メールに振り分け (S)"
                                        class="icon-btn bg-orange-50 text-orange-600 hover:bg-orange-600 hover:text-white">
                                    <i class="fas fa-shield-alt text-xs"></i>
                                </button>
                                <button x-show="selectedThread?.status === 'spam'"
                                        @click="unmarkSelectedAsSpam()" title="迷惑メールを解除"
                                        class="icon-btn bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white">
                                    <i class="fas fa-undo text-xs"></i>
                                </button>
                                <button @click="if(threadEmails.length > 0) openReplyForEmail(threadEmails[0])" title="返信 (新しいウィンドウ) (R)"
                                    class="icon-btn bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white">
                                    <i class="fas fa-reply text-xs"></i>
                                </button>
                                <button @click="if(threadEmails.length > 0) openReplyForEmail(threadEmails[0], true)" title="全員に返信 (新しいウィンドウ) (Shift+R)"
                                    class="icon-btn text-blue-400 border border-blue-100 hover:bg-blue-50 hover:text-blue-600">
                                    <i class="fas fa-reply-all text-xs"></i>
                                </button>
                                {{-- 転送: スレッドの最新メールを対象に Fwd: ウィンドウを開く --}}
                                <button @click="if(threadEmails.length > 0) openForwardForEmail(threadEmails[0])" title="転送 (新しいウィンドウ)"
                                    class="icon-btn text-emerald-500 border border-emerald-100 hover:bg-emerald-50 hover:text-emerald-700">
                                    <i class="fas fa-share text-xs"></i>
                                </button>

                                {{-- チャット切替ボタン (このスレッド専用のチャット - 未読のみバッジ表示) --}}
                                <button @click="toggleChatPanel()" title="このスレッド全体のチャット"
                                    :class="chatOpen ? 'bg-gray-800 text-white border-gray-800' : 'bg-gray-100 text-gray-700 border-gray-200 hover:bg-gray-200'"
                                    class="h-9 inline-flex items-center gap-1.5 px-3 rounded-lg border text-xs font-bold transition-all relative">
                                    <i class="fas fa-hashtag"></i>
                                    <span>チャット</span>
                                    <span x-show="threadChatUnread > 0"
                                          class="ml-0.5 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-black animate-pulse"
                                          style="background-color:#f59e0b;color:#fff;"
                                          x-text="threadChatUnread"
                                          title="未読チャット数"></span>
                                </button>

                                {{-- 担当者トグル (スレッド上部に独立配置) --}}
                                <div class="relative" x-data="{ assigneeOpen: false }">
                                    <button @click="assigneeOpen = !assigneeOpen" @click.away="assigneeOpen = false"
                                        :class="selectedThread?.assignee ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50'"
                                        class="h-9 inline-flex items-center gap-1.5 px-3 rounded-lg border text-[11px] font-bold transition-all"
                                        title="担当者を変更">
                                        <i class="fas fa-user-circle"></i>
                                        <span class="max-w-[120px] truncate" x-text="selectedThread?.assignee?.name || '担当者未設定'"></span>
                                        <i class="fas fa-chevron-down text-[9px] opacity-60"></i>
                                    </button>
                                    <div x-show="assigneeOpen" x-transition
                                         class="absolute top-full left-0 mt-2 w-56 bg-white border border-gray-200 rounded-2xl shadow-2xl z-[100] overflow-hidden py-2">
                                        <div class="px-4 py-2 text-[9px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100">担当者を選択</div>
                                        <div class="max-h-56 overflow-y-auto custom-scrollbar">
                                            <button @click="updateAssignee(null); assigneeOpen = false"
                                                    :class="!selectedThread?.assigned_user_id ? 'bg-blue-50 text-blue-600' : 'text-gray-500 hover:bg-gray-50'"
                                                    class="w-full text-left px-4 py-2 text-[10px] font-bold italic flex items-center justify-between transition-colors">
                                                <span><i class="fas fa-user-slash mr-2 text-gray-400"></i>未設定</span>
                                                <i x-show="!selectedThread?.assigned_user_id" class="fas fa-check text-blue-500"></i>
                                            </button>
                                            <template x-for="user in users" :key="user.id">
                                                <button @click="updateAssignee(user.id); assigneeOpen = false"
                                                        :class="selectedThread?.assigned_user_id == user.id ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-blue-50'"
                                                        class="w-full text-left px-4 py-2 text-[10px] font-bold flex items-center justify-between transition-colors">
                                                    <span class="flex items-center gap-2"><i class="fas fa-user-circle text-gray-400"></i><span x-text="user.name"></span></span>
                                                    <i x-show="selectedThread?.assigned_user_id == user.id" class="fas fa-check text-blue-500"></i>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                {{-- 三点リーダーメニュー (担当者以外のアクション) --}}
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" @click.away="open = false" title="その他のアクション"
                                        class="icon-btn text-gray-400 border border-gray-200 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50">
                                        <i class="fas fa-ellipsis-h text-xs"></i>
                                    </button>
                                    <div x-show="open" x-transition class="absolute top-full left-0 mt-2 w-56 bg-white border border-gray-200 rounded-2xl shadow-2xl z-[100] overflow-hidden py-2">
                                        <button @click="updateThreadStatus(selectedThread, 'inbox'); open = false" class="w-full text-left px-4 py-2.5 text-[11px] font-black text-gray-600 hover:bg-blue-50 flex items-center gap-3 transition-colors uppercase tracking-widest">
                                            <i class="fas fa-undo text-blue-400"></i> 未対応 (受信へ)
                                        </button>
                                        <button @click="updateThreadStatus(selectedThread, 'hold'); open = false" class="w-full text-left px-4 py-2.5 text-[11px] font-black text-gray-600 hover:bg-blue-50 flex items-center gap-3 transition-colors uppercase tracking-widest">
                                            <i class="fas fa-pause text-amber-400"></i> 保留
                                        </button>
                                        <button @click="updateThreadStatus(selectedThread, 'no_action'); open = false" class="w-full text-left px-4 py-2.5 text-[11px] font-black text-gray-600 hover:bg-blue-50 flex items-center gap-3 transition-colors uppercase tracking-widest">
                                            <i class="fas fa-ban text-gray-400"></i> 対応不要
                                        </button>
                                        <button @click="togglePin(); open = false" class="w-full text-left px-4 py-2.5 text-[11px] font-black text-gray-600 hover:bg-blue-50 flex items-center gap-3 transition-colors uppercase tracking-widest">
                                            <i class="fas fa-thumbtack text-amber-500"></i> ピン留め
                                        </button>
                                        <button @click="openAddToRoomModal(selectedThreadId); open = false"
                                                class="w-full text-left px-4 py-2.5 text-[11px] font-black text-blue-600 hover:bg-blue-50 flex items-center gap-3 transition-colors uppercase tracking-widest">
                                            <i class="fas fa-link text-blue-500"></i> ルームに追加
                                        </button>
                                        <div class="border-t border-gray-100 my-1"></div>
                                        {{-- 迷惑メール: 現状が spam でなければ「振り分け」、spam なら「解除」 --}}
                                        <button x-show="selectedThread?.status !== 'spam'"
                                                @click="markSelectedAsSpam(); open = false"
                                                class="w-full text-left px-4 py-2.5 text-[11px] font-black text-red-600 hover:bg-red-50 flex items-center gap-3 transition-colors uppercase tracking-widest">
                                            <i class="fas fa-shield-alt text-red-500"></i> 迷惑メールに振り分け
                                        </button>
                                        <button x-show="selectedThread?.status === 'spam'"
                                                @click="unmarkSelectedAsSpam(); open = false"
                                                class="w-full text-left px-4 py-2.5 text-[11px] font-black text-emerald-600 hover:bg-emerald-50 flex items-center gap-3 transition-colors uppercase tracking-widest">
                                            <i class="fas fa-undo text-emerald-500"></i> 迷惑メールを解除
                                        </button>
                                        <div class="border-t border-gray-100 my-1"></div>
                                        <button @click="deleteSelectedThread(); open = false"
                                                class="w-full text-left px-4 py-2.5 text-[11px] font-black text-red-600 hover:bg-red-50 flex items-center gap-3 transition-colors uppercase tracking-widest">
                                            <i class="fas fa-trash text-red-500"></i> 削除
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            {{--
                                スレッド内検索 (このスレッドのメールだけをインクリメンタルにフィルタ)。
                                対象フィールド: 件名 / 差出人 / 宛先 / Cc / 本文 (plain_body) — 全て大文字小文字を無視。
                                ヒットしたメールは自動展開 (expandedEmailIds に追加) して中身を即座に確認できる。
                                Esc キーでクリア。
                            --}}
                            <div class="relative flex items-center" style="background:#f3f4f6;border:1px solid #e5e7eb;border-radius:6px;padding:2px 8px;max-width:320px;">
                                <i class="fas fa-search" style="color:#9ca3af;font-size:10px;"></i>
                                {{--
                                    スレッド内検索:
                                      - インクリメンタルにフィルタ
                                      - 入力中は threadInnerSearchIndex=0 にリセット (先頭マッチへ自動移動)
                                      - Enter / ↓ で次へ, Shift+Enter / ↑ で前へ
                                      - Esc でクリア
                                --}}
                                <input type="text" x-model="threadInnerSearchQuery"
                                       @input="_resetSearchIndex(); $nextTick(() => _scrollToCurrentMatch())"
                                       @keydown.escape.prevent="threadInnerSearchQuery = ''"
                                       @keydown.enter.prevent="$event.shiftKey ? gotoNextMatch(-1) : gotoNextMatch(1)"
                                       @keydown.arrow-down.prevent="gotoNextMatch(1)"
                                       @keydown.arrow-up.prevent="gotoNextMatch(-1)"
                                       placeholder="このスレッド内を検索..."
                                       style="background:transparent;border:none;outline:none;font-size:11px;color:#374151;width:140px;margin-left:6px;">
                                {{-- 「現在位置 / ヒット数」表示 --}}
                                <span x-show="threadInnerSearchQuery && filteredThreadEmails.length > 0"
                                      class="shrink-0 ml-1 px-1.5 rounded text-[9px] font-bold"
                                      style="background:#dbeafe;color:#1d4ed8;"
                                      :title="filteredThreadEmails.length + ' 件ヒット (Enter で次へ / Shift+Enter で前へ)'"
                                      x-text="(filteredThreadEmails.length === 0 ? 0 : (threadInnerSearchIndex + 1)) + '/' + filteredThreadEmails.length"></span>
                                {{-- 前/次 ボタン --}}
                                <button x-show="threadInnerSearchQuery && filteredThreadEmails.length > 1"
                                        @click="gotoNextMatch(-1)" type="button"
                                        style="background:none;border:none;color:#6b7280;padding:0 2px;font-size:10px;"
                                        title="前のマッチ (Shift+Enter / ↑)">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                                <button x-show="threadInnerSearchQuery && filteredThreadEmails.length > 1"
                                        @click="gotoNextMatch(1)" type="button"
                                        style="background:none;border:none;color:#6b7280;padding:0 2px;font-size:10px;"
                                        title="次のマッチ (Enter / ↓)">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                <button x-show="threadInnerSearchQuery" @click="threadInnerSearchQuery = ''"
                                        type="button"
                                        style="background:none;border:none;color:#9ca3af;padding:0 0 0 4px;font-size:10px;"
                                        title="検索クリア (Esc)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <button @click="closeWorkspace()" title="閉じる"
                                class="icon-btn text-gray-400 border border-gray-200 hover:text-red-500 hover:border-red-200 hover:bg-red-50">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </div>
                    </div>
                    {{-- 2行目: 件名 + AI要約 + 承認状態バッジ --}}
                    <div class="px-6 py-2.5 flex items-start gap-2.5 min-w-0">
                        <div class="w-7 h-7 bg-blue-600 rounded-lg flex items-center justify-center text-white shrink-0 mt-0.5">
                            <i class="fas fa-envelope text-[11px]"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start gap-2 flex-wrap">
                                {{--
                                    スレッド件名: ワークスペースのヘッダ.
                                    旧: text-sm (14px) で目立たなかったため、
                                    本文より大きく (= スレッド全体の主題が一目で分かる) サイズに引き上げ.
                                --}}
                                <h2 class="font-extrabold text-gray-800 min-w-0"
                                    style="font-size:26px;word-break:break-word;overflow-wrap:anywhere;line-height:1.3;"
                                    x-text="selectedThread?.subject"></h2>
                                {{-- AI要約 (右側スライドインのチャット形式. 追加指示で何度でもブラッシュアップ可能).
                                     AI返信案は返信ウィンドウ側の AI アシスタントで行うのでここには出さない. --}}
                                <button type="button"
                                        @click="openAiChat('summary')"
                                        :disabled="!threadEmails.length"
                                        class="btn-ai-summary inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold transition-colors shrink-0 disabled:opacity-40 disabled:cursor-not-allowed"
                                        title="このスレッドを AI で要約 (追加指示でブラッシュアップ可)">
                                    <i class="fas fa-magic text-[9px]"></i>
                                    AI要約
                                </button>
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                <template x-if="pendingApprovals.some(p => p.status === 'pending')">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest bg-amber-100 text-amber-700 border border-amber-200">
                                        <i class="fas fa-clock"></i> 承認依頼中
                                    </span>
                                </template>
                                <template x-if="!pendingApprovals.some(p => p.status === 'pending') && pendingApprovals.some(p => p.status === 'approved')">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest bg-green-100 text-green-700 border border-green-200">
                                        <i class="fas fa-check-circle"></i> 承認済み
                                    </span>
                                </template>
                            </div>

                            {{--
                                バンドル先ルーム表示 (このスレッドが参加しているルームの一覧)。
                                共有 / 個人で 2 セクションに分けて並べる。
                                チップ本体クリック → そのルームで絞り込み (setRoomFilter)
                                チップ右端の ✕ ボタン → そのルームからこのスレッドを解除 (detachThreadFromRoom)
                                個人ルームは「自分が作成者」のものだけがサーバから返ってくる。
                                ※ HTML 仕様上 button の入れ子は不可なので chip は <span> + クリックハンドラ、
                                  ✕ だけ <button> にして @click.stop で親イベントを止める。
                            --}}
                            <template x-if="(threadBundledRooms.shared.length + threadBundledRooms.private.length) > 0">
                                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                    {{-- 共有ルーム --}}
                                    <template x-if="threadBundledRooms.shared.length > 0">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="text-[9px] font-black uppercase tracking-widest text-blue-500 inline-flex items-center gap-1">
                                                <i class="fas fa-globe"></i>共有ルーム
                                            </span>
                                            <template x-for="r in threadBundledRooms.shared" :key="'shared-room-' + r.id">
                                                <span role="button" tabindex="0"
                                                      @click="setRoomFilter(String(r.id))"
                                                      @keydown.enter.prevent="setRoomFilter(String(r.id))"
                                                      class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-md text-[10px] font-bold border transition-colors cursor-pointer"
                                                      style="background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;"
                                                      onmouseover="this.style.backgroundColor='#dbeafe';"
                                                      onmouseout="this.style.backgroundColor='#eff6ff';"
                                                      :title="'共有ルーム: ' + r.name + ' に絞り込む'">
                                                    <i class="fas fa-hashtag text-[8px]"></i>
                                                    <span x-text="r.name"></span>
                                                    {{--
                                                        どの振り分けルールによってこのスレッドがこのルームに入ったか.
                                                        matched_rule_type が null = 手動で追加 (= L キー / 「ルームに追加」 / 移行前データ).
                                                        サーバから matched_rule_label (差出人/ドメイン/件名/宛先) と
                                                        matched_rule_pattern (実値) を受け取り、チップ内に薄い文字で表示.
                                                    --}}
                                                    <span x-show="r.matched_rule_type"
                                                          class="inline-flex items-center gap-0.5 ml-0.5 px-1 py-0 rounded text-[9px] font-semibold"
                                                          style="background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;"
                                                          :title="'自動振り分け: ' + (r.matched_rule_label || '-') + ' = 「' + (r.matched_rule_pattern || '') + '」' + (r.matched_at ? ' (' + r.matched_at + ')' : '')">
                                                        <i class="fas fa-filter text-[7px]"></i>
                                                        <span x-text="(r.matched_rule_label || '') + ':' + (r.matched_rule_pattern || '')"
                                                              style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;vertical-align:bottom;"></span>
                                                    </span>
                                                    <button type="button"
                                                            @click.stop="detachThreadFromRoom(r)"
                                                            class="ml-0.5 inline-flex items-center justify-center w-4 h-4 rounded hover:bg-blue-200 transition-colors"
                                                            style="color:#1d4ed8;"
                                                            :title="'このスレッドを「' + r.name + '」から外す'">
                                                        <i class="fas fa-times text-[8px]"></i>
                                                    </button>
                                                </span>
                                            </template>
                                        </div>
                                    </template>
                                    {{-- 個人ルーム --}}
                                    <template x-if="threadBundledRooms.private.length > 0">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="text-[9px] font-black uppercase tracking-widest text-purple-500 inline-flex items-center gap-1">
                                                <i class="fas fa-lock"></i>個人ルーム
                                            </span>
                                            <template x-for="r in threadBundledRooms.private" :key="'private-room-' + r.id">
                                                <span role="button" tabindex="0"
                                                      @click="setRoomFilter(String(r.id))"
                                                      @keydown.enter.prevent="setRoomFilter(String(r.id))"
                                                      class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-md text-[10px] font-bold border transition-colors cursor-pointer"
                                                      style="background:#ede9fe;color:#6d28d9;border-color:#ddd6fe;"
                                                      onmouseover="this.style.backgroundColor='#ddd6fe';"
                                                      onmouseout="this.style.backgroundColor='#ede9fe';"
                                                      :title="'個人ルーム: ' + r.name + ' に絞り込む (自分のみ閲覧可)'">
                                                    <i class="fas fa-lock text-[8px]"></i>
                                                    <span x-text="r.name"></span>
                                                    {{--
                                                        どの振り分けルールによってこのスレッドがこのルームに入ったか (個人ルーム版).
                                                        共有ルーム側と同じ仕組み (matched_rule_label / pattern / at) を紫トーンで表示.
                                                    --}}
                                                    <span x-show="r.matched_rule_type"
                                                          class="inline-flex items-center gap-0.5 ml-0.5 px-1 py-0 rounded text-[9px] font-semibold"
                                                          style="background:#ddd6fe;color:#5b21b6;border:1px solid #c4b5fd;"
                                                          :title="'自動振り分け: ' + (r.matched_rule_label || '-') + ' = 「' + (r.matched_rule_pattern || '') + '」' + (r.matched_at ? ' (' + r.matched_at + ')' : '')">
                                                        <i class="fas fa-filter text-[7px]"></i>
                                                        <span x-text="(r.matched_rule_label || '') + ':' + (r.matched_rule_pattern || '')"
                                                              style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;vertical-align:bottom;"></span>
                                                    </span>
                                                    <button type="button"
                                                            @click.stop="detachThreadFromRoom(r)"
                                                            class="ml-0.5 inline-flex items-center justify-center w-4 h-4 rounded hover:bg-purple-200 transition-colors"
                                                            style="color:#6d28d9;"
                                                            :title="'このスレッドを「' + r.name + '」から外す'">
                                                        <i class="fas fa-times text-[8px]"></i>
                                                    </button>
                                                </span>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{--
                                スレッド全体の添付ファイルサマリ.
                                旧UIでは各メールの最下部にしか出ていなかったため、長いスレッドだと埋もれて見落としやすかった.
                                ここでヘッダ直下に横スクロールチップで一覧化し、
                                  - チップ本体クリック → ダウンロード
                                  - 📍ボタン → 該当メールへスクロール (折りたたまれていれば展開)
                                という UX にする.
                            --}}
                            <template x-if="threadAttachments.length > 0">
                                <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1.5">
                                    <span class="text-[9px] font-black uppercase tracking-widest text-amber-600 inline-flex items-center gap-1 shrink-0"
                                          :title="'このスレッドに含まれる添付ファイル: ' + threadAttachments.length + ' 件'">
                                        <i class="fas fa-paperclip"></i>添付<span x-text="'(' + threadAttachments.length + ')'"></span>
                                    </span>
                                    <div class="flex flex-wrap gap-1.5 min-w-0">
                                        <template x-for="att in threadAttachments" :key="'thread-att-' + att.id">
                                            <span class="inline-flex items-stretch rounded-md border overflow-hidden text-[10px] font-bold transition-colors"
                                                  style="background:#fffbeb;color:#92400e;border-color:#fde68a;max-width:260px;">
                                                {{-- 本体: ダウンロードリンク (ファイル名長すぎ対策で ellipsis) --}}
                                                <a :href="att.url" :download="att.filename"
                                                   class="inline-flex items-center gap-1 pl-2 pr-1.5 py-0.5 min-w-0 transition-colors"
                                                   onmouseover="this.style.backgroundColor='#fef3c7';"
                                                   onmouseout="this.style.backgroundColor='transparent';"
                                                   :title="'ダウンロード: ' + att.filename + (att.email_subject ? '\n出典: ' + att.email_subject : '')">
                                                    <i class="fas fa-file text-[9px] shrink-0" style="color:#b45309;"></i>
                                                    <span x-text="att.filename"
                                                          style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;min-width:0;"></span>
                                                </a>
                                                {{-- 該当メールへスクロール --}}
                                                <button type="button"
                                                        @click.stop="scrollToEmail(att.email_id)"
                                                        class="inline-flex items-center justify-center px-1.5 transition-colors shrink-0"
                                                        style="border-left:1px solid #fde68a;color:#b45309;"
                                                        onmouseover="this.style.backgroundColor='#fde68a';"
                                                        onmouseout="this.style.backgroundColor='transparent';"
                                                        :title="'このファイルが添付されたメールへ移動: ' + (att.email_subject || '')">
                                                    <i class="fas fa-location-arrow text-[9px]"></i>
                                                </button>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex-1 flex min-h-0 relative bg-gray-50/30">
                    {{-- スレッド本文ペイン.
                         x-ref="threadEmailsPane" で loadThread 後にスクロールトップへ戻すために参照する.
                         (前のスレッドで下までスクロールした状態で別スレッドに切替えると、
                          ブラウザは同じ DOM の scrollTop を保持してしまい、新スレッドが下まで
                          スクロールされた状態で表示される問題への対策) --}}
                    <div class="flex-1 min-w-0 overflow-y-auto p-10 custom-scrollbar"
                         x-ref="threadEmailsPane">

                        {{-- スレッド表示 --}}
                        <template x-if="selectedThread">
                            <div class="max-w-4xl 2xl:max-w-6xl mx-auto space-y-6">

                                {{-- マージ情報表示: コンパクトな 1 行カード.
                                     旧版は p-4 / rounded-2xl / 大きなアイコンで縦に積もると目立ちすぎたので、
                                     1 行に納めて控えめに表示する (要望:「もう少し控えめにお願いします」). --}}
                                <template x-if="threadMerges.length > 0">
                                    <div class="flex flex-col gap-1">
                                        <template x-for="merge in threadMerges" :key="merge.id">
                                            <div class="flex items-center gap-2 px-2.5 py-1 rounded-md border bg-amber-50/60 border-amber-100"
                                                 style="font-size:11px;">
                                                <i class="fas fa-object-group text-amber-600" style="font-size:10px;"></i>
                                                <span class="text-[9px] font-bold uppercase tracking-wider text-amber-700 shrink-0">マージ済</span>
                                                <span class="flex-1 min-w-0 truncate text-amber-900 font-medium"
                                                      :title="merge.source_subject"
                                                      x-text="merge.source_subject"></span>
                                                <button type="button" @click="unmergeThread(merge.id)"
                                                        class="text-[10px] font-bold text-amber-700 hover:text-white hover:bg-amber-600 border border-amber-200 px-2 py-0.5 rounded transition-colors shrink-0"
                                                        title="このマージを解除する">
                                                    解除
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- 検索一致 0 件のメッセージ (threadInnerSearchQuery が入っているが filtered が空) --}}
                                <template x-if="threadInnerSearchQuery && filteredThreadEmails.length === 0">
                                    <div class="bg-white border border-amber-200 rounded-xl p-6 text-center text-amber-700">
                                        <i class="fas fa-search fa-lg mb-2 opacity-60"></i>
                                        <p class="text-sm font-bold">「<span x-text="threadInnerSearchQuery"></span>」に一致するメールがありません</p>
                                        <p class="text-[11px] text-gray-500 mt-1">件名 / 差出人 / 宛先 / Cc / 本文を検索しています</p>
                                        <button @click="threadInnerSearchQuery = ''" type="button"
                                                class="mt-3 inline-flex items-center gap-1 px-3 py-1 rounded-md bg-amber-100 hover:bg-amber-200 text-amber-800 text-[11px] font-bold">
                                            <i class="fas fa-times"></i>検索をクリア
                                        </button>
                                    </div>
                                </template>

                                {{-- 各メール表示: 件名→宛先→日付の縦積み (アイコン無し)。
                                     threadInnerSearchQuery が入っているときは filteredThreadEmails のみ表示。 --}}
                                <template x-for="email in filteredThreadEmails" :key="email.id">
                                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow transition-shadow group"
                                         :data-email-id="email.id"
                                         :class="threadInnerSearchQuery ? 'ring-2 ring-amber-200' : ''">
                                        <div class="px-4 py-3 cursor-pointer hover:bg-gray-50/50 transition-colors" @click="toggleEmailExpand(email.id)">

                                            {{--
                                                1 段目: 件名のみ (フル幅).
                                                ユーザ要望「ボタンと件名を別の欄に分ける」に対応するため、
                                                旧版で同じ行に並んでいたアクションボタン群は 2 段目に降ろした.
                                                これで長い件名でもボタンに押し潰されず、両方とも読みやすい.
                                            --}}
                                            <div class="flex items-start gap-2 min-w-0">
                                                {{--
                                                    各メールの件名: 本文 (15px) のおよそ 1.5 倍 = 22px.
                                                    旧 30px は太すぎたので落とす. スレッドヘッダ (26px) との差別化も兼ねる.
                                                --}}
                                                <h3 class="font-black text-gray-900 flex-1 min-w-0"
                                                    style="font-size:22px;word-break:break-word;overflow-wrap:anywhere;line-height:1.35;"
                                                    x-html="highlightMatch(email.subject || selectedThread?.subject || '(件名なし)')"></h3>
                                                <i class="fas fa-chevron-down text-gray-300 group-hover:text-blue-500 transition-all shrink-0 text-[12px] mt-2"
                                                   :class="isEmailExpanded(email) ? 'rotate-180' : ''"></i>
                                            </div>

                                            {{--
                                                2 段目: アクションボタン (返信 / 全員 / ナレッジ / チャット / 分離 / 削除).
                                                件名の下に独立した行として配置. 折りたたみ状態でも常に見える.
                                                クリック時の親 (toggleEmailExpand) 発火を抑止するため各ボタンに @click.stop あり.
                                                少ない幅の時は自動で折り返す (flex-wrap).
                                            --}}
                                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                                <button @click.stop="openReplyForEmail(email)"
                                                        class="btn-action btn-action-reply"
                                                        title="返信">
                                                    <i class="fas fa-reply"></i> 返信
                                                </button>
                                                <button @click.stop="openReplyForEmail(email, true)"
                                                        class="btn-action btn-action-replyall"
                                                        title="全員に返信">
                                                    <i class="fas fa-reply-all"></i> 全員
                                                </button>
                                                {{-- 転送 (Forward): 件名 "Fwd: " + 元本文引用 + 添付継承候補 で別ウィンドウを開く. --}}
                                                <button @click.stop="openForwardForEmail(email)"
                                                        class="btn-action btn-action-forward"
                                                        title="転送 (Fwd:)">
                                                    <i class="fas fa-share"></i> 転送
                                                </button>
                                                {{-- このメールをナレッジに登録 --}}
                                                <button @click.stop="openKnowledgeRegister(email)"
                                                        class="btn-action btn-action-knowledge"
                                                        title="このメールをナレッジに登録">
                                                    <i class="fas fa-book"></i> ナレッジ
                                                </button>
                                                {{-- このメールに紐付くチャット (per-email) --}}
                                                <button @click.stop="openEmailChat(email)"
                                                        class="btn-action btn-action-chat"
                                                        title="このメールに関するチャット">
                                                    <i class="fas fa-comment-dots"></i> チャット
                                                </button>
                                                {{-- このメールをスレッドから分離 (削除せず、別スレッドへ移動). --}}
                                                <button @click.stop="detachEmailFromThread(email)"
                                                        class="btn-action btn-action-detach"
                                                        title="このメールを別スレッドとして独立させる (※ 削除ではなく移動: メール本体は新スレッドに残ります)">
                                                    <i class="fas fa-code-branch"></i> 分離
                                                </button>
                                                {{-- このメール 1 通だけ削除 (スレッドからは外れるがスレッド自体は残る). --}}
                                                <button @click.stop="deleteEmailInThread(email)"
                                                        class="btn-action btn-action-delete"
                                                        title="このメールを削除">
                                                    <i class="fas fa-trash"></i> 削除
                                                </button>
                                            </div>

                                            {{-- 2段目: From / To / Cc (メールアドレスを主表示、表示名は補助)
                                                 - from_address に "@" が無い場合は実体としてはメールアドレスでないので、
                                                   from_label (表示名) を主表示にし、その横に「(送信元アドレスなし)」を出す。
                                                 - 表示名と一致する from_address は冗長なので括弧表示しない。 --}}
                                            <div class="mt-1.5 space-y-0.5 text-[13px] text-gray-600">
                                                <div class="truncate" :title="(email.from_label || '') + ' <' + (email.from_address || '') + '>'">
                                                    <span class="text-gray-400 mr-1 inline-block w-7">From:</span>
                                                    <template x-if="email.from_address && email.from_address.includes('@')">
                                                        <span>
                                                            <span class="font-semibold text-gray-800" x-text="email.from_address"></span>
                                                            <span class="text-gray-400 ml-1"
                                                                  x-show="email.from_label && email.from_label !== email.from_address"
                                                                  x-text="'(' + email.from_label + ')'"></span>
                                                        </span>
                                                    </template>
                                                    <template x-if="!email.from_address || !email.from_address.includes('@')">
                                                        <span>
                                                            <span class="font-semibold text-gray-800"
                                                                  x-text="email.from_label || email.from_address || '(差出人未設定)'"></span>
                                                            <span class="ml-1 text-[11px] italic text-gray-400"
                                                                  title="メールヘッダから差出人アドレスを取得できませんでした">(アドレスなし)</span>
                                                        </span>
                                                    </template>
                                                </div>
                                                <div class="truncate" :title="email.to_address">
                                                    <span class="text-gray-400 mr-1 inline-block w-7">To:</span>
                                                    <span x-text="email.to_address || '—'"></span>
                                                </div>
                                                <div class="truncate" x-show="email.cc" :title="email.cc">
                                                    <span class="text-gray-400 mr-1 inline-block w-7">Cc:</span>
                                                    <span x-text="email.cc"></span>
                                                </div>
                                            </div>

                                            {{-- 3段目: 日付 --}}
                                            <div class="mt-1.5 text-[12px] text-gray-400 font-medium" x-text="email.received_at"></div>

                                            {{--
                                                4段目: 添付ファイル (各メール上部に表示).
                                                折りたたみ状態でも見えるようヘッダ領域の中に置く.
                                                チップ本体クリック → ダウンロード (@click.stop で expand トグル抑止).
                                                ファイル名は長すぎを max-width:240px + ellipsis.
                                            --}}
                                            <template x-if="(email.attachments || []).length > 0">
                                                <div class="mt-2 flex flex-wrap items-center gap-1.5"
                                                     @click.stop="">
                                                    <span class="text-[9px] font-black uppercase tracking-widest inline-flex items-center gap-1 shrink-0"
                                                          style="color:#b45309;">
                                                        <i class="fas fa-paperclip text-[9px]"></i>添付<span x-text="'(' + email.attachments.length + ')'"></span>
                                                    </span>
                                                    <template x-for="at in email.attachments" :key="'email-' + email.id + '-att-' + at.id">
                                                        <a :href="at.url" :download="at.filename"
                                                           @click.stop=""
                                                           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border text-[10px] font-bold transition-colors"
                                                           style="background:#fffbeb;color:#92400e;border-color:#fde68a;max-width:240px;"
                                                           onmouseover="this.style.backgroundColor='#fef3c7';"
                                                           onmouseout="this.style.backgroundColor='#fffbeb';"
                                                           :title="'ダウンロード: ' + at.filename">
                                                            <i class="fas fa-file text-[9px] shrink-0" style="color:#b45309;"></i>
                                                            <span x-text="at.filename"
                                                                  style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;min-width:0;"></span>
                                                        </a>
                                                    </template>
                                                </div>
                                            </template>

                                            {{--
                                                5段目: 振り分け先マーカー (各メール上部に表示).
                                                スレッド側の `threadBundledRooms` (shared/private 配列) からバンドル先ルームをすべて表示.
                                                matched_rule_type が埋まっている → 自動振り分けで入ったルーム (フィルタ条件付きで表示)
                                                matched_rule_type が NULL          → 手動で追加 / 監査列追加前から存在 ((手動) 表記)
                                                ユーザ要望「自動振り分けの際、どのフィルタにより振り分けされたのかメールに明記」に応える形.
                                                クリックでルームフィルタ切替 + @click.stop で expand トグル抑止.
                                            --}}
                                            <template x-if="autoRoutedBundledRooms.length > 0">
                                                <div class="mt-2 flex flex-wrap items-center gap-1.5"
                                                     @click.stop="">
                                                    <span class="text-[9px] font-black uppercase tracking-widest inline-flex items-center gap-1 shrink-0"
                                                          style="color:#1e40af;">
                                                        <i class="fas fa-filter text-[9px]"></i>振り分け先
                                                    </span>
                                                    <template x-for="r in autoRoutedBundledRooms" :key="'email-' + email.id + '-rr-' + r.id">
                                                        <span role="button" tabindex="0"
                                                              @click.stop="setRoomFilter(String(r.id))"
                                                              @keydown.enter.prevent.stop="setRoomFilter(String(r.id))"
                                                              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border text-[10px] font-bold transition-colors cursor-pointer"
                                                              :style="r.is_private
                                                                 ? 'background:#ede9fe;color:#5b21b6;border-color:#ddd6fe;'
                                                                 : 'background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;'"
                                                              :title="r.matched_rule_type
                                                                  ? ('ルーム「' + r.name + '」に自動振り分け\nフィルタ: ' + (r.matched_rule_label || '-') + ' = 「' + (r.matched_rule_pattern || '') + '」' + (r.matched_at ? '\n振り分け日時: ' + r.matched_at : ''))
                                                                  : ('ルーム「' + r.name + '」に手動で追加されました\n(振り分けフィルタによる自動取り込みではありません)')">
                                                            <i class="fas fa-hashtag text-[8px]" x-show="!r.is_private"></i>
                                                            <i class="fas fa-lock text-[8px]" x-show="r.is_private"></i>
                                                            <span class="font-bold" x-text="r.name"></span>
                                                            {{-- 自動振り分け: ← フィルタラベル:パターン --}}
                                                            <template x-if="r.matched_rule_type">
                                                                <span class="inline-flex items-center gap-1">
                                                                    <span class="opacity-70">←</span>
                                                                    <span x-text="(r.matched_rule_label || '') + ':' + (r.matched_rule_pattern || '')"
                                                                          style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;vertical-align:bottom;"></span>
                                                                </span>
                                                            </template>
                                                            {{-- 手動追加: (手動) と薄文字で示す --}}
                                                            <template x-if="!r.matched_rule_type">
                                                                <span class="inline-flex items-center gap-1 opacity-70 italic">
                                                                    <i class="fas fa-hand-pointer text-[8px]"></i>
                                                                    <span>手動</span>
                                                                </span>
                                                            </template>
                                                        </span>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>

                                        <div x-show="isEmailExpanded(email)" x-collapse>
                                            <div class="px-6 pb-6 pt-2 border-t border-gray-50">
                                                {{-- 表示形式トグル: テキスト / HTML. 並び順は要望に従い テキスト → HTML.
                                                     HTML が無い場合はテキスト固定なのでトグル自体を隠す. --}}
                                                <div class="flex items-center justify-end gap-2 mb-2 text-[10px]" x-show="email.safe_body_html">
                                                    <span class="text-gray-400">表示:</span>
                                                    <button type="button"
                                                            @click="setEmailViewMode(email.id, 'text')"
                                                            :class="emailViewMode(email.id) === 'text' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-blue-50'"
                                                            class="px-2 py-1 rounded-md border font-bold transition-colors">
                                                        <i class="fas fa-align-left mr-1"></i>テキスト
                                                    </button>
                                                    <button type="button"
                                                            @click="setEmailViewMode(email.id, 'html')"
                                                            :class="emailViewMode(email.id) === 'html' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-blue-50'"
                                                            class="px-2 py-1 rounded-md border font-bold transition-colors">
                                                        <i class="fas fa-code mr-1"></i>HTML
                                                    </button>
                                                </div>

                                                {{-- HTML 表示: sandbox iframe で XSS / クリック追跡 / 外部スクリプトを遮断.
                                                     srcdoc ではなく document.write 経由でレンダリングする (一部の HTML メールで
                                                     srcdoc 経由だと body が空になる事象があったため). 親から contentDocument を
                                                     触れるよう sandbox に allow-same-origin を付与するが、script-src 'none' の
                                                     CSP で JS 実行はメタタグレベルで完全遮断している. --}}
                                                <template x-if="email.safe_body_html && emailViewMode(email.id) === 'html'">
                                                    <iframe
                                                        :id="'email-html-' + email.id"
                                                        class="bg-white rounded-2xl border border-gray-100 w-full"
                                                        sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                                                        referrerpolicy="no-referrer"
                                                        loading="lazy"
                                                        style="min-height:240px;"
                                                        x-init="$nextTick(() => writeEmailIframe($el, email))"></iframe>
                                                </template>
                                                {{-- テキスト表示 (デフォルト or HTML 無し)。
                                                     スレッド内検索クエリがあれば <mark> でハイライト. --}}
                                                <div x-show="!email.safe_body_html || emailViewMode(email.id) === 'text'"
                                                     class="bg-white p-6 rounded-2xl text-gray-700 leading-relaxed font-medium whitespace-pre-wrap text-[15px] 2xl:text-base"
                                                     x-html="highlightMatch(email.plain_body)" style="line-height:1.7;"></div>
                                                <div class="mt-6 pt-4 border-t border-gray-50 flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <template x-if="email.thread_id !== selectedThreadId">
                                                            <div class="flex items-center gap-2">
                                                                <span class="text-[10px] font-black text-amber-600 bg-amber-50 px-2 py-1 rounded-md uppercase border border-amber-100">マージ元操作</span>
                                                                <button @click="updateSingleEmailStatus(email.thread_id, 'completed')" class="text-[10px] bg-green-50 text-green-700 border border-green-200 px-3 py-1.5 rounded-xl hover:bg-green-600 hover:text-white transition-all font-black uppercase shadow-sm">完了</button>
                                                                <button @click="updateSingleEmailStatus(email.thread_id, 'hold')" class="text-[10px] bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1.5 rounded-xl hover:bg-amber-500 hover:text-white transition-all font-black uppercase shadow-sm">保留</button>
                                                                <button @click="togglePin(email.thread_id)" class="text-[10px] bg-gray-50 text-gray-700 border border-gray-200 px-3 py-1.5 rounded-xl hover:bg-gray-600 hover:text-white transition-all font-black uppercase shadow-sm">ピン</button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2 items-center">
                                                        {{-- 添付チップ: 本文側からは「ダウンロードのみ」可能。
                                                             削除は添付ファイル管理画面 (/attachments) からの非表示にのみ対応する。 --}}
                                                        <template x-for="at in email.attachments" :key="at.id">
                                                            <a :href="at.url" :download="at.filename"
                                                               class="flex items-center gap-2 bg-gray-50 border border-gray-100 px-3 py-2 rounded-xl text-[10px] font-black text-blue-600 hover:bg-blue-600 hover:text-white transition-all shadow-sm"
                                                               :title="'ダウンロード: ' + at.filename">
                                                                <i class="fas fa-paperclip"></i><span x-text="at.filename"></span>
                                                            </a>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                    </div>

                    {{-- チャットサイドパネル (スレッド毎) --}}
                    <aside x-show="chatOpen" x-transition:enter="transition ease-out duration-200"
                           x-transition:enter-start="translate-x-4 opacity-0"
                           x-transition:enter-end="translate-x-0 opacity-100"
                           :style="'width:' + chatPanelWidth + 'px'"
                           class="thread-chat-panel shrink-0 flex flex-col overflow-hidden relative">
                        {{-- リサイズハンドル (左端) --}}
                        <div class="absolute top-0 left-0 w-1.5 h-full cursor-col-resize z-50 thread-chat-resize"
                             @mousedown.prevent="startResizeChatPanel($event)"
                             title="ドラッグして幅を変更"></div>
                        {{-- ヘッダ --}}
                        <div class="thread-chat-header shrink-0 px-4 py-3 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <template x-if="chatScope.kind === 'thread'">
                                    <span class="thread-chat-hash">#</span>
                                </template>
                                <template x-if="chatScope.kind === 'email'">
                                    <i class="fas fa-envelope-open-text" style="color:#7c3aed;font-size:14px;"></i>
                                </template>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-sm font-bold" style="color:#111827;"
                                        x-text="chatScope.kind === 'email' ? 'このメールのチャット' : 'スレッド全体のチャット'"></h3>
                                    <p class="text-[10px] truncate" style="color:#6b7280;"
                                       x-text="chatScope.kind === 'email' ? (chatScope.email_subject || '(件名なし)') : (selectedThread?.subject || '')"></p>
                                </div>
                            </div>
                            {{-- スコープ切替トグル --}}
                            <div class="flex rounded-md overflow-hidden text-[10px] font-bold shrink-0"
                                 style="border:1px solid #e5e7eb;">
                                <button @click="setChatScopeThread()"
                                        :style="chatScope.kind === 'thread'
                                            ? 'background:#2563eb;color:#fff;'
                                            : 'background:#ffffff;color:#6b7280;'"
                                        class="px-2 py-1 transition-colors"
                                        title="スレッド全体のチャットに切替">全体</button>
                                <button @click="restoreEmailScope()"
                                        :disabled="chatScope.kind !== 'email' && !lastEmailScope"
                                        :style="chatScope.kind === 'email'
                                            ? 'background:#7c3aed;color:#fff;'
                                            : (lastEmailScope ? 'background:#ffffff;color:#7c3aed;' : 'background:#ffffff;color:#d1d5db;cursor:not-allowed;')"
                                        class="px-2 py-1 transition-colors"
                                        :title="chatScope.kind === 'email'
                                            ? '現在このメール固有のチャットを表示中'
                                            : (lastEmailScope ? '前回開いた『' + (lastEmailScope.email_subject || '(件名なし)') + '』のチャットに戻る' : 'メールの💬チャットボタンから開いてください')">メール</button>
                            </div>
                            <button @click="toggleChatPanel()" class="thread-chat-close" title="閉じる">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </div>

                        {{-- メッセージリスト --}}
                        <div class="thread-chat-messages flex-1 overflow-y-auto custom-scrollbar" id="chat-messages"
                             @scroll.passive="onChatScroll($event)">
                            <template x-if="chatLoading">
                                <div class="flex items-center justify-center py-8" style="color:#3b82f6;">
                                    <i class="fas fa-circle-notch fa-spin"></i>
                                </div>
                            </template>
                            <template x-if="!chatLoading && chatComments.length === 0">
                                <div class="text-center py-12" style="color:#9ca3af;">
                                    <i class="fas fa-hashtag fa-2x mb-3" style="color:#e5e7eb;"></i>
                                    <p class="text-xs font-semibold" style="color:#374151;">まだメッセージがありません</p>
                                    <p class="text-[10px] mt-1">最初のメッセージを送ってみましょう</p>
                                </div>
                            </template>
                            <template x-for="(c, idx) in chatComments" :key="c.id">
                                <div class="msg-row group"
                                     :style="isMentionedToMe(c.content) ? 'background-color:#fff7ed;border-left:3px solid #f97316;' : ''">
                                    <div class="avatar" :style="'background-color:' + threadChatAvatarColor(c.user_id)" x-text="(c.author || '?').charAt(0).toUpperCase()"></div>
                                    <div class="ts-header">
                                        <span class="author" x-text="c.author"></span>
                                        <span class="ts" x-text="c.created_at"></span>
                                        <template x-if="isMentionedToMe(c.content)">
                                            <span class="ml-1 text-[9px] font-black px-1 py-0.5 rounded" style="background-color:#fef3c7;color:#92400e;border:1px solid #fde68a;">@あなた宛</span>
                                        </template>
                                        {{-- どのメールに紐付くか (全体表示時のみ) --}}
                                        <template x-if="chatScope.kind === 'thread' && c.email_id">
                                            <button @click="focusEmailFromChat(c.email_id)"
                                                    class="ml-1 inline-flex items-center gap-1 text-[9px] font-bold px-1.5 py-0.5 rounded transition-colors"
                                                    style="background-color:#ede9fe;color:#5b21b6;border:1px solid #ddd6fe;"
                                                    onmouseover="this.style.backgroundColor='#5b21b6';this.style.color='#ffffff';"
                                                    onmouseout="this.style.backgroundColor='#ede9fe';this.style.color='#5b21b6';"
                                                    :title="'対象メール: ' + (emailSubjectFor(c.email_id) || '(件名なし)') + ' / クリックで絞り込み'">
                                                <i class="fas fa-envelope text-[8px]"></i>
                                                <span class="truncate" style="max-width:120px;" x-text="emailSubjectFor(c.email_id) || '対象メール'"></span>
                                            </button>
                                        </template>
                                    </div>
                                    <div class="body" x-html="renderMentions(c.content, false)" x-show="c.content"></div>
                                    {{-- 添付ファイル --}}
                                    <div x-show="(c.attachments || []).length > 0"
                                         style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
                                        <template x-for="a in (c.attachments || [])" :key="a.id">
                                            <div>
                                                <template x-if="a.is_image">
                                                    <a :href="a.url" :title="a.filename" target="_blank">
                                                        <img :src="a.inline_url" :alt="a.filename"
                                                             style="max-width:200px;max-height:160px;border-radius:8px;border:1px solid #e5e7eb;cursor:zoom-in;object-fit:cover;">
                                                    </a>
                                                </template>
                                                <template x-if="!a.is_image">
                                                    <a :href="a.url" :title="a.filename"
                                                       style="display:inline-flex;align-items:center;gap:6px;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:6px 10px;font-size:12px;color:#374151;text-decoration:none;max-width:240px;">
                                                        <i class="fas fa-paperclip"></i>
                                                        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px;font-weight:600;" x-text="a.filename"></span>
                                                        <span style="color:#9ca3af;font-size:10px;" x-text="formatFileBytes(a.size)"></span>
                                                    </a>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                    <template x-if="c.is_author">
                                        <button @click="deleteChatComment(c.id)"
                                                class="msg-actions opacity-0 group-hover:opacity-100 transition-opacity"
                                                title="削除">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- 最新へスクロールするボタン (上にスクロールしている時のみ表示) --}}
                        <button x-show="chatScrolledUp" x-cloak
                                @click="scrollChatToBottom(false)"
                                title="最新メッセージへ"
                                style="position:absolute;right:14px;bottom:96px;z-index:10;background:#2563eb;color:#fff;border:none;border-radius:999px;width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(37,99,235,0.4);cursor:pointer;"
                                onmouseover="this.style.backgroundColor='#1d4ed8'"
                                onmouseout="this.style.backgroundColor='#2563eb'">
                            <i class="fas fa-arrow-down"></i>
                        </button>

                        {{-- 入力エリア --}}
                        <div class="shrink-0 thread-chat-input-wrap relative">

                            {{-- メンション候補ドロップダウン --}}
                            <template x-if="mentionOpen && mentionMatches.length > 0">
                                <div class="absolute left-3 right-3 bottom-full mb-2 rounded-lg shadow-2xl overflow-hidden max-h-56 overflow-y-auto custom-scrollbar z-50"
                                     style="background:#ffffff;border:1px solid #e5e7eb;">
                                    <div class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest" style="color:#9ca3af;background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                                        @メンション (↑↓ で移動 / Enter で選択 / Esc でキャンセル)
                                    </div>
                                    <template x-for="(u, i) in mentionMatches" :key="u.id">
                                        <button type="button"
                                                @click.stop="pickMention(u)"
                                                @mouseenter="mentionIndex = i"
                                                :style="mentionIndex === i ? 'background-color:#eff6ff;color:#1d4ed8;' : 'color:#374151;'"
                                                class="w-full text-left px-3 py-2 text-sm font-semibold flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs shrink-0"
                                                 :style="'background-color:' + threadChatAvatarColor(u.id) + ';color:#fff;'"
                                                 x-text="(u.name || '?').charAt(0)"></div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate" x-text="u.name"></p>
                                                <p class="text-[10px] truncate" style="color:#9ca3af;" x-text="u.email"></p>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                            </template>

                            {{-- 選択中の添付ファイル --}}
                            <div x-show="chatPendingFiles.length > 0" style="display:flex;flex-wrap:wrap;gap:6px;padding:6px 0;">
                                <template x-for="(f, i) in chatPendingFiles" :key="i">
                                    <span style="display:inline-flex;align-items:center;gap:4px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:600;">
                                        <i class="fas fa-paperclip"></i>
                                        <span x-text="f.name"></span>
                                        <span style="color:#6b7280;" x-text="'(' + formatFileBytes(f.size) + ')'"></span>
                                        <button type="button" @click="removeChatPendingFile(i)" style="background:none;border:none;color:#1e40af;padding:0 2px;cursor:pointer;" title="除外"><i class="fas fa-times"></i></button>
                                    </span>
                                </template>
                            </div>

                            <div class="thread-chat-input-box">
                                <textarea id="chat-input-textarea"
                                          x-model="chatInput"
                                          rows="1"
                                          @input="onChatInput($event); threadChatAutoresize($event)"
                                          @keydown.arrow-up="onMentionKeydown($event, 'up')"
                                          @keydown.arrow-down="onMentionKeydown($event, 'down')"
                                          @keydown.escape="closeMention()"
                                          @keydown="onChatKeydown($event)"
                                          placeholder="メッセージを入力 (@で担当者をメンション)"></textarea>
                                {{-- ファイル添付 --}}
                                <input type="file" x-ref="threadChatFileInput" multiple style="display:none;" @change="onChatFilesPicked($event)">
                                <button @click="$refs.threadChatFileInput.click()"
                                        title="ファイルを添付"
                                        class="thread-chat-send" style="color:#94a3b8;">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                                {{--
                                    送信ボタン.
                                    :disabled の式は chatInput が null/undefined だった時にも壊れないよう
                                    (chatInput || '').trim() で防御. (closeWorkspace 等で空文字に戻すが
                                    一時的に null 状態を踏む race condition で .trim() がエラーになり
                                    Alpine が :disabled の評価に失敗して click が無効化される事象への対策).
                                --}}
                                <button @click="sendChatComment()"
                                        :disabled="(!(chatInput || '').trim() && (chatPendingFiles?.length || 0) === 0) || chatSending"
                                        title="送信 (Ctrl+Enter)"
                                        class="thread-chat-send disabled:opacity-30">
                                    <i class="fas" :class="chatSending ? 'fa-spinner animate-spin' : 'fa-paper-plane'"></i>
                                </button>
                            </div>
                            </div>
                            <p class="text-[10px] mt-1.5" style="color:#949ba4;">
                                <kbd class="thread-chat-kbd">Ctrl</kbd> + <kbd class="thread-chat-kbd">Enter</kbd> で送信 / <kbd class="thread-chat-kbd">Enter</kbd> で改行 / <span style="color:#dbdee1;font-weight:600;">@名前</span> で メンション / <i class="fas fa-paperclip"></i> 添付
                            </p>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>

    {{-- AI要約パネル (右側スライドイン).
         旧: 中央モーダル. 新: 画面右端から幅 520px のパネルがスライドインしてくる UX.
         x-show / Alpine reactivity は内部 UI でも信用できなかったので、表示・モデル選択・
         スキル選択・追加指示まで すべて id 指定の直接 DOM 操作で制御する.
         背後の薄いバックドロップだけ x-show ではなく display 切替で出す. --}}
    <div id="riceAiSummaryBackdrop"
         style="position:fixed;inset:0;z-index:1990;display:none;background-color:rgba(15,23,42,0.25);transition:opacity 0.2s;opacity:0;"
         onclick="riceCloseAiSummaryPanel()"></div>
    <div id="riceAiSummaryModal"
         style="position:fixed;top:0;right:0;bottom:0;width:560px;max-width:96vw;z-index:2000;display:none;background-color:#ffffff;box-shadow:-12px 0 32px rgba(15,23,42,0.18);transform:translateX(100%);transition:transform 0.3s ease-out;flex-direction:column;"
         @keydown.escape.window="riceCloseAiSummaryPanel()">
        <div class="flex flex-col h-full"
             style="background-color:#ffffff;">
            {{-- ヘッダー --}}
            <div class="shrink-0 px-5 py-3 flex items-center justify-between gap-3 border-b border-indigo-100"
                 style="background:linear-gradient(135deg,#eef2ff 0%,#ede9fe 100%);">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="w-9 h-9 rounded-lg inline-flex items-center justify-center shrink-0"
                         style="background-color:#4f46e5;color:#ffffff;">
                        <i class="fas fa-magic"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-extrabold" style="color:#312e81;">AI要約</h3>
                        <p id="riceAiSummarySubject" class="text-[10px] truncate" style="color:#6366f1;"></p>
                    </div>
                </div>
                <button type="button" onclick="riceCloseAiSummaryPanel()"
                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg transition-colors"
                        style="color:#6b7280;"
                        onmouseover="this.style.backgroundColor='#ffffff';this.style.color='#374151';"
                        onmouseout="this.style.backgroundColor='';this.style.color='#6b7280';"
                        title="閉じる">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            {{-- 本文 --}}
            <div class="flex-1 overflow-y-auto custom-scrollbar p-5"
                 style="min-height:0;background-color:#ffffff;">
                {{-- モデル選択ピッカー (Alpine reactivity 不安定対策で直接 DOM 操作版).
                     openThreadSummary → riceRenderAiPickers() でボタンと select を生成する. --}}
                <div class="mb-4 p-3 rounded-xl border" style="background-color:#fafafa;border-color:#e5e7eb;">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest" style="color:#6b7280;">
                            <i class="fas fa-cog text-[9px] mr-1"></i>AIモデル
                        </span>
                        <span id="riceAiPickerLoading" class="text-[10px]" style="display:none;color:#9ca3af;">
                            <i class="fas fa-circle-notch fa-spin"></i> 読み込み中
                        </span>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <div id="riceAiProviderRow" class="flex rounded-lg border border-gray-200 overflow-hidden text-[11px]">
                            {{-- riceRenderAiPickers() で生成 --}}
                        </div>
                        <select id="riceAiModelSelect" onchange="riceOnAiModelChange(this.value)"
                                class="border border-gray-200 rounded-lg px-2 py-1 text-[11px] text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white min-w-[180px]">
                            <option value="">モデルなし</option>
                        </select>
                        <button type="button" id="riceAiSummaryGenerateBtn"
                                onclick="riceTriggerAiSummaryGenerate()"
                                class="ml-auto inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-bold transition-colors disabled:opacity-40"
                                style="background-color:#4f46e5;color:#fff;">
                            <i class="fas fa-bolt"></i> このモデルで生成
                        </button>
                    </div>
                    <p id="riceAiProviderWarning" style="display:none;margin-top:4px;font-size:10px;color:#d97706;"></p>
                </div>

                {{-- スキル選択 + 追加指示. スキルボタンは直接 DOM 生成、プロンプト textarea は
                     プレーン textarea で動作確認できるシンプルな構成にする. --}}
                <div class="mb-4 p-3 rounded-xl border" style="background-color:#fafafa;border-color:#e5e7eb;">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest" style="color:#6b7280;">
                            <i class="fas fa-lightbulb text-[9px] mr-1"></i>スキル / 指示
                        </span>
                        <button type="button" id="riceAiSummaryPromptToggle"
                                onclick="riceToggleAiSummaryPrompt()"
                                class="text-[10px] font-bold underline" style="color:#4f46e5;">追加指示を入力</button>
                    </div>
                    <div id="riceAiSkillsContainer" class="grid gap-1.5"
                         style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));">
                        {{-- riceRenderAiPickers() で生成 --}}
                    </div>
                    <p id="riceAiSkillDescription" class="mt-1.5 text-[10px] leading-snug" style="color:#9ca3af;"></p>

                    <div id="riceAiSummaryPromptSection" style="display:none;margin-top:12px;">
                        <label style="display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin-bottom:4px;">追加指示 (任意)</label>
                        <textarea id="riceAiSummaryPromptArea" rows="3"
                                  placeholder="例: 丁寧な敬語で、技術用語は平易に。"
                                  style="width:100%;border:1px solid #e5e7eb;border-radius:8px;padding:8px 12px;font-size:13px;font-family:'Noto Sans JP',sans-serif;outline:none;resize:vertical;"></textarea>
                        <p style="margin-top:4px;font-size:10px;color:#9ca3af;">スキルの基本指示に加えて、この依頼に固有の指示を追加できます。空でも OK。</p>
                    </div>
                </div>

                {{-- ローディング / エラー / 結果は Alpine x-show だと反映されないケースに何度も
                     遭遇したため、id 指定の直接 DOM 操作で確実に切替える方式に変更. --}}
                {{-- ローディング --}}
                <div id="riceAiSummaryLoadingSection"
                     style="display:none;flex-direction:column;align-items:center;justify-content:center;padding:40px 0;">
                    <i class="fas fa-circle-notch fa-spin fa-2x" style="color:#818cf8;margin-bottom:12px;"></i>
                    <p style="font-size:13px;font-weight:700;color:#4f46e5;">スレッドを分析中...</p>
                    <p id="riceAiSummaryLoadingDetail" style="font-size:11px;color:#9ca3af;margin-top:4px;"></p>
                </div>

                {{-- エラー --}}
                <div id="riceAiSummaryErrorSection"
                     style="display:none;background-color:#fef2f2;color:#b91c1c;border:1px solid #fecaca;border-radius:8px;padding:16px;font-size:13px;white-space:pre-wrap;word-break:break-word;max-height:24rem;overflow-y:auto;"></div>

                {{-- 結果 --}}
                <div id="riceAiSummaryResultSection" style="display:none;">
                    <div id="riceAiSummaryBadges" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:10px;font-weight:700;margin-bottom:12px;"></div>
                    <div id="riceAiSummaryResult"
                         style="border-radius:12px;padding:16px;font-size:13px;line-height:1.7;white-space:pre-wrap;background-color:#f9fafb;color:#111827;border:1px solid #e5e7eb;word-break:break-word;"></div>
                </div>
            </div>

            {{-- フッター. 結果が出るまで コピー/再生成 ボタンは非表示. --}}
            <div class="shrink-0 px-5 py-3 flex items-center justify-between gap-2 border-t border-gray-100"
                 style="background-color:#f9fafb;">
                <button type="button" id="riceAiSummaryCopyBtn" @click="copyThreadSummary()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
                        style="display:none;background-color:#ffffff;color:#374151;border:1px solid #d1d5db;"
                        onmouseover="this.style.backgroundColor='#f3f4f6';"
                        onmouseout="this.style.backgroundColor='#ffffff';">
                    <i id="riceAiSummaryCopyIcon" class="fas fa-copy"></i>
                    <span id="riceAiSummaryCopyLabel">要約をコピー</span>
                </button>
                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" id="riceAiSummaryRegenBtn" @click="loadThreadSummary(true)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors disabled:opacity-50"
                            style="display:none;background-color:#ffffff;color:#374151;border:1px solid #d1d5db;"
                            onmouseover="if(!this.disabled)this.style.backgroundColor='#f3f4f6';"
                            onmouseout="if(!this.disabled)this.style.backgroundColor='#ffffff';">
                        <i class="fas fa-redo text-[10px]"></i> 再生成
                    </button>
                    <button type="button" onclick="riceCloseAiSummaryPanel()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white transition-colors"
                            style="background-color:#4f46e5;"
                            onmouseover="this.style.backgroundColor='#4338ca';"
                            onmouseout="this.style.backgroundColor='#4f46e5';">
                        閉じる
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- AI チャットパネル (右側スライドイン. compose-window の AIアシスタント と
         同じデザイン. 表示/非表示は id 指定 + style 直接書き換えで確実に切替. --}}
    <div id="rice-ai-chat-backdrop"
         onclick="window.riceAiChatClose && window.riceAiChatClose()"
         style="display:none;position:fixed;inset:0;z-index:1990;background-color:rgba(15,23,42,0.35);"></div>
    <div id="rice-ai-chat-panel"
         style="display:none;position:fixed;top:0;right:0;bottom:0;width:420px;max-width:100vw;z-index:2000;background-color:#eef2ff;border-left:1px solid #c7d2fe;box-shadow:-12px 0 30px rgba(15,23,42,0.15);flex-direction:column;">

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
                    <p class="text-[10px]" style="color:#818cf8;">スレッドを基に対話で作成</p>
                </div>
            </div>
            <button type="button"
                    onclick="window.riceAiChatClose && window.riceAiChatClose()"
                    class="w-8 h-8 inline-flex items-center justify-center rounded-lg transition-colors"
                    style="color:#9ca3af;border:0;background:transparent;cursor:pointer;"
                    onmouseover="this.style.backgroundColor='#f3f4f6';this.style.color='#4f46e5';"
                    onmouseout="this.style.backgroundColor='';this.style.color='#9ca3af';"
                    title="閉じる">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="flex-1 min-h-0 flex flex-col">
            {{-- AI モデルピッカー (imperative DOM. Alpine x-for / :class が部分的に効かない事故対策) --}}
            <div class="shrink-0 p-4" style="background:#ffffff;border-bottom:1px solid #e0e7ff;">
                <label style="display:flex;align-items:center;gap:6px;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:0.1em;color:#6b7280;margin-bottom:8px;">
                    <i class="fas fa-cog text-[9px]"></i>AIモデル
                    <span id="rice-ai-chat-model-loading" style="display:none;color:#9ca3af;">
                        <i class="fas fa-circle-notch fa-spin"></i>
                    </span>
                </label>
                {{-- ピッカー本体: openAiChat 内で innerHTML 構築. プロバイダタブ + モデル select --}}
                <div id="rice-ai-chat-model-picker" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <span style="font-size:11px;color:#9ca3af;">読み込み中…</span>
                </div>
                <p id="rice-ai-chat-model-warn" style="display:none;font-size:10px;color:#d97706;margin-top:4px;"></p>
            </div>

            {{-- チャットセクション --}}
            <div class="flex-1 min-h-0 flex flex-col" style="background:#f8fafc;">
                {{-- ツールバー: タイトル + リセット --}}
                <div class="shrink-0 px-3 py-1.5 flex items-center justify-between"
                     style="background:#ffffff;border-bottom:1px solid #e0e7ff;">
                    <span class="text-[10px] font-bold" style="color:#6366f1;">
                        <i class="fas fa-comments text-[9px]"></i>
                        <span x-text="aiChat.kind === 'reply' ? '返信案チャット' : '要約チャット'"></span>
                    </span>
                    <button type="button" @click="resetAiChat()"
                            :disabled="!aiChat.sessionId"
                            class="disabled:opacity-30"
                            style="width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;background:transparent;color:#9ca3af;border:0;border-radius:4px;cursor:pointer;font-size:10px;"
                            onmouseover="if(!this.disabled){this.style.color='#b91c1c';this.style.background='#fee2e2';}"
                            onmouseout="this.style.color='#9ca3af';this.style.background='transparent';"
                            title="この会話を全部消してやり直す">
                        <i class="fas fa-undo"></i>
                    </button>
                </div>

                {{-- メッセージリスト --}}
                <div id="rice-ai-chat-messages" class="flex-1 overflow-y-auto custom-scrollbar" style="padding:12px 10px;">
                    <template x-if="aiChat.messages.length === 0">
                        <div class="text-center py-8" style="color:#9ca3af;">
                            <i class="fas fa-comments fa-2x mb-2" style="color:#e0e7ff;"></i>
                            <p class="text-xs font-bold" style="color:#4b5563;"
                               x-text="aiChat.kind === 'reply' ? '返信案を AI と相談しながら作ります' : 'スレッドを AI と相談しながら要約します'"></p>
                            <p class="text-[10px] mt-1.5" style="color:#9ca3af;">
                                <template x-if="aiChat.kind === 'reply'">
                                    <span>
                                        まずはスレッドについて相談してください.<br>
                                        例: 「論点を整理して」「返信のトーンを相談したい」<br>
                                        準備ができたら 「<b>返信を書いて</b>」 と指示してください
                                    </span>
                                </template>
                                <template x-if="aiChat.kind !== 'reply'">
                                    <span>「3行で要約して」<br>「経緯だけ詳しく」<br>「担当者ごとに分けて」<br>のような指示を送ってください</span>
                                </template>
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
                                    <div class="rice-ai-msg-error">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span x-text="m.error_message || 'エラーが発生しました'"></span>
                                    </div>
                                </template>
                                <template x-if="m.status === 'done' || m.role === 'user'">
                                    <div>
                                        {{-- user 投稿は /tag を青チップで表示, assistant 応答はそのまま (改行のみ保持) --}}
                                        <div class="rice-ai-msg-body"
                                             x-html="m.role === 'user' ? renderAiChatTaggedHtml(m.content) : _escapeHtml(m.content).replace(/\n/g, '<br>')"></div>
                                        <template x-if="m.role === 'assistant'">
                                            <div class="rice-ai-msg-actions">
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

                {{-- 入力欄 (id 指定 + onclick で確実に発火) --}}
                <div class="shrink-0 p-2.5" style="position:relative;background:#ffffff;border-top:1px solid #e0e7ff;">
                    {{-- スキル選択チップ (現在 active なスキルがあれば表示) --}}
                    <div x-show="aiChat.skillKey" x-cloak
                         style="display:none;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
                        <span style="display:inline-flex;align-items:center;gap:4px;background:#eef2ff;color:#4338ca;padding:3px 8px;border-radius:9999px;font-size:10px;font-weight:700;border:1px solid #c7d2fe;">
                            <i class="fas fa-bolt text-[9px]"></i>
                            スキル: <span x-text="(aiSkills && aiSkills[aiChat.skillKey] && aiSkills[aiChat.skillKey].name) || aiChat.skillKey"></span>
                            <button type="button"
                                    onclick="window.riceAiChatClearSkill && window.riceAiChatClearSkill()"
                                    style="margin-left:4px;background:transparent;border:0;color:#4338ca;cursor:pointer;font-size:10px;"
                                    title="スキルをクリア (既定に戻す)">×</button>
                        </span>
                        <span style="font-size:9px;color:#9ca3af;">/ で別のスキルに切り替え</span>
                    </div>

                    {{-- '/' スラッシュコマンドのスキル候補ポップアップ.
                         _riceAiChatRenderSkillSlash() が中身を直接 innerHTML で書き換える. --}}
                    <div id="rice-ai-chat-skill-slash"
                         style="display:none;position:absolute;left:10px;right:10px;bottom:100%;margin-bottom:6px;background:#fff;border:1px solid #c7d2fe;border-radius:8px;box-shadow:0 -8px 24px rgba(15,23,42,0.10);max-height:240px;overflow-y:auto;z-index:10;"></div>

                    <div class="flex items-end gap-2">
                        <div class="rice-ai-input-wrap">
                            {{-- 入力テキストを <span class="rice-ai-tag"> で wrap した HTML を流し込むハイライト層 --}}
                            <div id="rice-ai-chat-input-highlight" class="rice-ai-input-highlight"></div>
                            <textarea id="rice-ai-chat-input"
                                      rows="2"
                                      placeholder="指示を入力 / 「/」 でスキル + ナレッジコレクション選択 (Ctrl+Enter で送信)"
                                      oninput="window.riceAiChatOnInput && window.riceAiChatOnInput(this.value)"
                                      onkeydown="if (event.key === 'Escape') { document.getElementById('rice-ai-chat-skill-slash').style.display='none'; return; } if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') { event.preventDefault(); window.riceAiChatSend && window.riceAiChatSend(); }"
                                      onscroll="(function(t){const h=document.getElementById('rice-ai-chat-input-highlight'); if(h){h.scrollTop=t.scrollTop;h.scrollLeft=t.scrollLeft;}})(this)"></textarea>
                        </div>
                        <button id="rice-ai-chat-send-btn"
                                type="button"
                                onclick="window.riceAiChatSend && window.riceAiChatSend()"
                                class="shrink-0 px-3 py-2 rounded-lg text-xs font-extrabold transition-colors"
                                style="background:#4f46e5;color:#fff;border:0;cursor:pointer;">
                            <i class="fas fa-paper-plane" id="rice-ai-chat-send-icon"></i>
                        </button>
                    </div>
                    <p class="text-[9px] mt-1" style="color:#9ca3af;">
                        Ctrl+Enter で送信 / 「/」 でスキル + ナレッジコレクション指定 / 履歴はスレッドごとに自動保存
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ナレッジ登録パネル (右側スライドイン).
         AI 要約パネルと同じ UX. すべてのボタン / 入力は onclick / oninput で
         id 指定の window helper を呼ぶ. Alpine reactivity に依存しない. --}}
    <div id="riceKnowledgeBackdrop"
         style="position:fixed;inset:0;z-index:1990;display:none;background-color:rgba(15,23,42,0.25);transition:opacity 0.2s;opacity:0;"
         onclick="riceCloseKnowledgePanel()"></div>
    <div id="riceKnowledgeModal"
         style="position:fixed;top:0;right:0;bottom:0;width:560px;max-width:96vw;z-index:2000;display:none;background-color:#ffffff;box-shadow:-12px 0 32px rgba(15,23,42,0.18);transform:translateX(100%);transition:transform 0.3s ease-out;flex-direction:column;">
        <div class="flex flex-col h-full">
            {{-- ヘッダー --}}
            <div class="shrink-0 px-5 py-3 flex items-center justify-between gap-3 border-b border-emerald-100"
                 style="background:linear-gradient(135deg,#ecfdf5 0%,#d1fae5 100%);">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="w-9 h-9 rounded-lg inline-flex items-center justify-center shrink-0" style="background-color:#059669;color:#ffffff;">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-extrabold" style="color:#065f46;">メールをナレッジに登録</h3>
                        <p class="text-[10px]" style="color:#10b981;">登録前に個人情報をマスクしてください</p>
                    </div>
                </div>
                <button type="button" onclick="riceCloseKnowledgePanel()"
                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-white"
                        title="閉じる">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            {{-- 本体 (id ベース DOM 切替) --}}
            <div class="flex-1 overflow-y-auto p-5">
                <div id="riceKnowledgeLoadingSection"
                     style="display:flex;align-items:center;justify-content:center;padding:32px 0;color:#10b981;">
                    <i class="fas fa-circle-notch fa-spin"></i>
                    <span style="margin-left:8px;font-size:13px;font-weight:600;">メール内容を読み込み中…</span>
                </div>
                <div id="riceKnowledgeErrorSection"
                     style="display:none;background-color:#fef2f2;color:#b91c1c;border:1px solid #fecaca;border-radius:8px;padding:12px;font-size:13px;"></div>
                <div id="riceKnowledgeFormSection" style="display:none;">
                    <div style="background-color:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;font-size:11px;color:#92400e;margin-bottom:12px;">
                        <i class="fas fa-exclamation-triangle" style="margin-right:4px;"></i>
                        <span id="riceKnowledgePiiWarning"></span>
                        <p style="margin-top:6px;font-size:10px;">よく使うマスク例:
                            <code style="background:#ffffff;padding:0 4px;border-radius:3px;">[氏名]</code>
                            <code style="background:#ffffff;padding:0 4px;border-radius:3px;">[メール]</code>
                            <code style="background:#ffffff;padding:0 4px;border-radius:3px;">[電話]</code>
                            <code style="background:#ffffff;padding:0 4px;border-radius:3px;">[住所]</code>
                            <button type="button" onclick="riceKnowledgeApplyMask()"
                                    style="margin-left:8px;text-decoration:underline;font-weight:700;background:none;border:none;color:#92400e;cursor:pointer;">自動マスク</button>
                        </p>
                    </div>

                    <label style="display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin-bottom:4px;">タイトル</label>
                    <input type="text" id="riceKnowledgeTitle" maxlength="255"
                           style="width:100%;border:1px solid #e5e7eb;border-radius:8px;padding:8px 12px;font-size:13px;outline:none;">

                    <label style="display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin:12px 0 4px;">コレクション</label>
                    <input type="text" id="riceKnowledgeCollection" maxlength="64" placeholder="default"
                           style="width:100%;border:1px solid #e5e7eb;border-radius:8px;padding:8px 12px;font-size:13px;outline:none;">

                    <div style="display:flex;align-items:center;justify-content:space-between;margin:12px 0 4px;">
                        <label style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;">登録する本文 (編集可)</label>
                        <span id="riceKnowledgeCharCount" style="font-size:10px;color:#9ca3af;">0 字</span>
                    </div>
                    <textarea id="riceKnowledgeContent" rows="14"
                              oninput="document.getElementById('riceKnowledgeCharCount').textContent = (this.value.length) + ' 字';"
                              style="width:100%;border:1px solid #e5e7eb;border-radius:8px;padding:8px 12px;font-size:12px;font-family:'Noto Sans JP',sans-serif;line-height:1.6;resize:vertical;outline:none;"></textarea>
                </div>
            </div>

            {{-- フッター --}}
            <div class="shrink-0 px-5 py-3 border-t border-gray-100 bg-gray-50 flex items-center justify-end gap-2">
                <button type="button" onclick="riceCloseKnowledgePanel()"
                        class="px-3 py-1.5 rounded-lg text-sm text-gray-700 bg-white border border-gray-200 hover:bg-gray-100">キャンセル</button>
                <button type="button" id="riceKnowledgeSubmitBtn" onclick="riceKnowledgeSubmit()"
                        class="px-4 py-1.5 rounded-lg text-sm font-bold text-white disabled:opacity-50 inline-flex items-center gap-1.5"
                        style="background-color:#059669;">
                    <i id="riceKnowledgeSubmitSpinner" class="fas fa-circle-notch fa-spin text-xs" style="display:none;"></i>
                    <span id="riceKnowledgeSubmitLabel">ナレッジに登録</span>
                </button>
            </div>
        </div>
    </div>

    {{-- 永続バナー (バックグラウンド失敗 / 部分成功) — モーダルではなく常時表示 --}}
    <template x-if="persistentSyncError || persistentSyncWarning">
        <div class="fixed top-2 left-1/2 -translate-x-1/2 z-[1900]"
             style="min-width:480px;max-width:90vw;">
            {{-- 失敗バナー (赤) --}}
            <template x-if="persistentSyncError">
                <div class="flex items-start gap-3 rounded-xl shadow-lg px-4 py-3 cursor-pointer"
                     style="background-color:#fef2f2;border:1px solid #fecaca;color:#7f1d1d;"
                     @click="syncError = persistentSyncError"
                     title="クリックで詳細表示">
                    <i class="fas fa-exclamation-triangle mt-0.5" style="color:#dc2626;"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-[12px] font-extrabold mb-0.5" x-text="persistentSyncError.message"></p>
                        <p class="text-[11px] truncate" style="color:#991b1b;" x-text="persistentSyncError.detail"></p>
                        <p x-show="persistentSyncError.consecutive_failures && persistentSyncError.consecutive_failures > 1"
                           class="text-[10px] mt-0.5" style="color:#991b1b;">
                            連続失敗回数: <span x-text="persistentSyncError.consecutive_failures"></span>
                            <span x-show="persistentSyncError.last_fetch_error_at"
                                  x-text="' / 最終失敗: ' + persistentSyncError.last_fetch_error_at"></span>
                        </p>
                    </div>
                    <button type="button"
                            @click.stop="fetchEmails(false)"
                            class="text-[10px] font-bold px-2 py-1 rounded shrink-0"
                            style="background-color:#dc2626;color:#ffffff;"
                            title="再試行">
                        <i class="fas fa-sync-alt mr-1"></i>再試行
                    </button>
                    <button type="button" @click.stop="persistentSyncError = null; persistentSyncErrorAt = 0"
                            class="text-[11px] shrink-0"
                            style="background:transparent;border:none;color:#7f1d1d;"
                            title="閉じる (再ポーリングで再表示される可能性があります)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </template>
            {{-- 部分成功 警告バナー (黄) --}}
            <template x-if="!persistentSyncError && persistentSyncWarning">
                <div class="flex items-start gap-3 rounded-xl shadow-lg px-4 py-3 cursor-pointer"
                     style="background-color:#fffbeb;border:1px solid #fde68a;color:#78350f;"
                     @click="syncError = persistentSyncWarning"
                     title="クリックで詳細表示">
                    <i class="fas fa-exclamation-circle mt-0.5" style="color:#d97706;"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-[12px] font-extrabold mb-0.5" x-text="persistentSyncWarning.message"></p>
                        <p class="text-[11px] truncate" style="color:#92400e;" x-text="persistentSyncWarning.detail"></p>
                    </div>
                    <button type="button" @click.stop="persistentSyncWarning = null"
                            class="text-[11px] shrink-0"
                            style="background:transparent;border:none;color:#78350f;"
                            title="閉じる">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </template>
        </div>
    </template>

    {{-- 同期エラーモーダル --}}
    <template x-if="syncError">
        <div class="fixed inset-0 z-[2000] flex items-center justify-center p-4"
             style="background-color:rgba(15,23,42,0.55);"
             @click.self="syncError = null"
             @keydown.escape.window="syncError = null">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
                {{-- ヘッダー --}}
                <div class="px-5 py-3 flex items-center gap-3 border-b border-red-100"
                     style="background-color:#fef2f2;">
                    <div class="w-9 h-9 rounded-lg inline-flex items-center justify-center shrink-0"
                         style="background-color:#fee2e2;color:#b91c1c;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="text-sm font-extrabold text-red-900" x-text="syncError.message"></h3>
                </div>
                {{-- 本文 --}}
                <div class="px-5 py-4 space-y-3">
                    {{-- メインエラーメッセージ (接続失敗・致命的エラー) --}}
                    <p class="rounded-lg p-3 text-[12px] text-gray-800 leading-relaxed break-all"
                       style="background-color:#f9fafb;border:1px solid #e5e7eb;"
                       x-text="syncError.detail"></p>

                    {{-- 個別メールエラー (1 通単位、複数あり得る) --}}
                    <template x-if="syncError.errors && syncError.errors.length > 0">
                        <div>
                            <p class="text-[11px] font-bold text-gray-600 mb-1 inline-flex items-center gap-1">
                                <i class="fas fa-list-ul text-[10px] text-red-600"></i>
                                <span>取り込みに失敗した個別メール (<span x-text="syncError.errors.length"></span> 件)</span>
                            </p>
                            <div class="space-y-1.5 max-h-56 overflow-y-auto custom-scrollbar pr-1">
                                <template x-for="(err, i) in syncError.errors" :key="i">
                                    <div class="rounded-lg p-2 text-[11px] leading-snug"
                                         style="background-color:#fef2f2;border:1px solid #fecaca;">
                                        <div class="flex items-center gap-2 mb-0.5">
                                            <i class="fas fa-envelope text-[10px]" style="color:#b91c1c;"></i>
                                            <span class="font-bold truncate" style="color:#7f1d1d;flex:1;min-width:0;"
                                                  x-text="err.subject || '(件名なし)'" :title="err.subject || ''"></span>
                                        </div>
                                        <div class="text-[10px] mb-1" style="color:#6b7280;">
                                            <span class="text-gray-500">From:</span>
                                            <span x-text="err.from || '不明'"></span>
                                        </div>
                                        <div class="text-[11px] break-all" style="color:#991b1b;"
                                             x-text="err.error"></div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div x-data="{ expanded: false }" class="text-left" x-show="syncError.stack">
                        <button @click="expanded = !expanded"
                                class="text-[11px] font-bold text-blue-600 hover:text-blue-700 inline-flex items-center gap-1">
                            <i class="fas" :class="expanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                            スタックトレースを表示
                        </button>
                        <div x-show="expanded" x-collapse class="mt-2">
                            <pre class="p-3 rounded-lg text-[10px] overflow-auto max-h-40 custom-scrollbar font-mono leading-relaxed"
                                 style="background-color:#0f172a;color:#cbd5e1;"
                                 x-text="syncError.stack"></pre>
                        </div>
                    </div>
                </div>
                {{-- フッター --}}
                <div class="px-5 py-3 flex items-center justify-end gap-2 border-t border-gray-100"
                     style="background-color:#f9fafb;">
                    <button @click="syncError = null"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
                            style="background-color:#ffffff;color:#374151;border:1px solid #d1d5db;"
                            onmouseover="this.style.backgroundColor='#f3f4f6';"
                            onmouseout="this.style.backgroundColor='#ffffff';">
                        閉じる
                    </button>
                    <button @click="fetchEmails()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white transition-colors"
                            style="background-color:#dc2626;"
                            onmouseover="this.style.backgroundColor='#b91c1c';"
                            onmouseout="this.style.backgroundColor='#dc2626';">
                        <i class="fas fa-sync-alt text-[10px]"></i> リトライ
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{--
        マージモーダル (完全インライン版).
        以下の理由で過去の実装が失敗し続けたため、外部 CSS への依存を全て排除した:
          - Tailwind 任意値 (fixed, inset-0, flex 等) → ビルドされていない環境で当たらない
          - .rice-modal / .rice-modal-backdrop クラス → 同ファイル内定義だが当たらない原因不明
          - Alpine の x-show は display プロパティを上書きするため、 class の display:flex が消える
        対策: <template x-if> で DOM ごと生成/破棄。 描画は全部インライン style で完結。
              これで Alpine が初期化さえできていれば「クリック→true→必ず DOM 出現」 が確定する。
    --}}
    <template x-if="mergeModalOpen">
        <div style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(0,0,0,0.55);"
             @click.self="mergeModalOpen = false"
             @keydown.escape.window="mergeModalOpen = false">
            <div style="background:#ffffff;width:520px;max-width:94vw;max-height:85vh;border-radius:14px;box-shadow:0 24px 60px rgba(0,0,0,0.32);display:flex;flex-direction:column;overflow:hidden;">
                {{-- ヘッダ --}}
                <div style="display:flex;align-items:flex-start;gap:12px;padding:16px 18px;border-bottom:1px solid #f1f5f9;background:#eff6ff;">
                    <div style="width:40px;height:40px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:#dbeafe;color:#2563eb;flex-shrink:0;font-size:16px;">
                        <i class="fas fa-object-group"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <h3 style="margin:0;font-size:14px;font-weight:800;color:#0f172a;">ベースとなるスレッドを選択</h3>
                        <p style="margin:2px 0 0;font-size:11px;color:#64748b;">選択したスレッドの件名がマージ後のスレッド名になります。</p>
                    </div>
                    <button @click="mergeModalOpen = false" type="button"
                            style="background:transparent;border:0;color:#94a3b8;cursor:pointer;padding:4px 6px;font-size:14px;"
                            title="閉じる">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                {{-- ボディ (候補一覧) --}}
                <div style="padding:14px 18px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:8px;">
                    <template x-for="c in mergeCandidates" :key="c.id">
                        <div @click="mergeTargetId = c.id"
                             style="padding:12px 14px;border-radius:10px;border:2px solid;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:12px;transition:all .15s;"
                             :style="mergeTargetId === c.id
                                 ? 'background:#eff6ff;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.15);'
                                 : 'background:#f9fafb;border-color:#e5e7eb;'">
                            <div style="min-width:0;flex:1;">
                                <p style="margin:0;font-size:10px;font-weight:900;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;"
                                   x-text="'ID: ' + c.id + (c.last_email_at ? ' / ' + c.last_email_at : '')"></p>
                                <p style="margin:2px 0 0;font-size:13px;font-weight:700;color:#1f2937;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                                   x-text="c.subject"></p>
                            </div>
                            <div style="flex-shrink:0;width:22px;height:22px;border-radius:50%;border:2px solid;display:flex;align-items:center;justify-content:center;"
                                 :style="mergeTargetId === c.id ? 'border-color:#3b82f6;background:#3b82f6;color:#fff;' : 'border-color:#d1d5db;'">
                                <i x-show="mergeTargetId === c.id" class="fas fa-check" style="font-size:10px;"></i>
                            </div>
                        </div>
                    </template>
                    <template x-if="!mergeCandidates || mergeCandidates.length === 0">
                        <p style="text-align:center;color:#9ca3af;font-size:12px;padding:16px;">
                            <i class="fas fa-exclamation-circle" style="margin-right:4px;"></i>マージ候補がありません。複数選択して再度お試しください。
                        </p>
                    </template>
                </div>

                {{-- フッタ --}}
                <div style="padding:12px 18px;border-top:1px solid #f1f5f9;background:#f8fafc;display:flex;justify-content:flex-end;gap:8px;">
                    <button @click="mergeModalOpen = false" type="button"
                            style="background:#ffffff;border:1px solid #e2e8f0;color:#475569;padding:8px 18px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">
                        キャンセル
                    </button>
                    <button @click="executeMerge()" type="button"
                            :disabled="!mergeTargetId"
                            :style="'border:0;color:#fff;padding:8px 18px;border-radius:8px;font-size:12px;font-weight:800;cursor:pointer;'
                                     + (!mergeTargetId ? 'background:#9ca3af;cursor:not-allowed;opacity:0.6;' : 'background:#2563eb;')">
                        <i class="fas fa-check" style="margin-right:4px;"></i>マージを実行
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{--
        キーボードショートカット ヘルプモーダル (完全インライン版).
        マージモーダルと同じく Tailwind 任意値 (fixed, inset-0, grid-cols-* 等) に
        依存していたため JIT 未ビルド環境で非表示になっていた。 全部インラインへ.
    --}}
    <template x-if="shortcutsModalOpen">
        <div style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(0,0,0,0.55);"
             @click.self="shortcutsModalOpen = false"
             @keydown.escape.window="shortcutsModalOpen = false">
            <div style="background:#ffffff;width:768px;max-width:96vw;max-height:88vh;border-radius:14px;box-shadow:0 24px 60px rgba(0,0,0,0.32);display:flex;flex-direction:column;overflow:hidden;">
                {{-- ヘッダ --}}
                <div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:12px;background:linear-gradient(90deg,#eff6ff,#eef2ff);">
                    <div style="width:40px;height:40px;border-radius:10px;background:#2563eb;color:#fff;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-keyboard"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <h2 style="margin:0;font-size:15px;font-weight:800;color:#1e3a8a;">キーボードショートカット</h2>
                        <p style="margin:2px 0 0;font-size:11px;color:#60a5fa;">Spark / Gmail / Notion の標準的なキーバインドを採用。入力中・モーダル中は無効です。</p>
                    </div>
                    <button @click="shortcutsModalOpen = false" type="button"
                            style="background:transparent;border:0;color:#94a3b8;cursor:pointer;padding:6px 8px;font-size:14px;border-radius:6px;"
                            onmouseover="this.style.background='#f1f5f9';this.style.color='#475569';"
                            onmouseout="this.style.background='transparent';this.style.color='#94a3b8';"
                            title="閉じる (Esc)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                {{-- ボディ (2 列レイアウト、 grid もインラインで) --}}
                <div style="padding:20px;overflow-y:auto;flex:1;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px 32px;">

                    {{-- ナビゲーション --}}
                    <div>
                        <h3 style="margin:0 0 8px;font-size:10px;font-weight:900;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;">ナビゲーション</h3>
                        <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;font-size:12px;color:#374151;">
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>次のスレッド</span><kbd class="rice-kbd">J</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>前のスレッド</span><kbd class="rice-kbd">K</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>検索クリア / 選択解除 / 閉じる</span><kbd class="rice-kbd">Esc</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>メイン検索にフォーカス</span><kbd class="rice-kbd">/</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>このヘルプを開く</span><kbd class="rice-kbd">?</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>直前の操作を元に戻す</span><span style="display:inline-flex;gap:2px;"><kbd class="rice-kbd">Ctrl</kbd>+<kbd class="rice-kbd">Z</kbd></span></li>
                        </ul>
                    </div>

                    {{-- 表示中スレッドへの操作 --}}
                    <div>
                        <h3 style="margin:0 0 8px;font-size:10px;font-weight:900;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;">表示中スレッドへの操作</h3>
                        <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;font-size:12px;color:#374151;">
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>返信</span><kbd class="rice-kbd">R</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>全員に返信</span><span style="display:inline-flex;gap:2px;"><kbd class="rice-kbd">Shift</kbd>+<kbd class="rice-kbd">R</kbd></span></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>完了</span><kbd class="rice-kbd">E</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>保留</span><kbd class="rice-kbd">H</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>受信箱に戻す</span><kbd class="rice-kbd">I</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>ピン留めトグル</span><kbd class="rice-kbd">P</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>迷惑メール</span><kbd class="rice-kbd">S</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>ルームに追加</span><kbd class="rice-kbd">L</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;color:#dc2626;">
                                <span>削除 (確認あり / 選択モード中は一括)</span>
                                <span style="display:inline-flex;gap:2px;align-items:center;">
                                    <kbd class="rice-kbd">D</kbd>
                                    <span style="color:#d1d5db;font-size:10px;">/</span>
                                    <kbd class="rice-kbd">Del</kbd>
                                    <span style="color:#d1d5db;font-size:10px;">/</span>
                                    <kbd class="rice-kbd">Ctrl</kbd>+<kbd class="rice-kbd">Del</kbd>
                                </span>
                            </li>
                        </ul>
                    </div>

                    {{-- 作成 / 同期 --}}
                    <div>
                        <h3 style="margin:0 0 8px;font-size:10px;font-weight:900;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;">作成 / 同期</h3>
                        <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;font-size:12px;color:#374151;">
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>新規メール作成</span><kbd class="rice-kbd">C</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>メール取得 (sync)</span><kbd class="rice-kbd">G</kbd></li>
                        </ul>
                    </div>

                    {{-- ステータスタブ --}}
                    <div>
                        <h3 style="margin:0 0 8px;font-size:10px;font-weight:900;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;">ステータスタブ切替</h3>
                        <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;font-size:12px;color:#374151;">
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>受信</span><kbd class="rice-kbd">1</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>保留</span><kbd class="rice-kbd">2</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>完了</span><kbd class="rice-kbd">3</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>対応不要</span><kbd class="rice-kbd">4</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>承認待ち</span><kbd class="rice-kbd">5</kbd></li>
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>迷惑メール</span><kbd class="rice-kbd">6</kbd></li>
                        </ul>
                    </div>

                    {{-- 複数選択 (フル幅) --}}
                    <div style="grid-column:span 2;">
                        <h3 style="margin:0 0 8px;font-size:10px;font-weight:900;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;">複数選択モード</h3>
                        <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;font-size:12px;color:#374151;">
                            <li style="display:flex;align-items:center;justify-content:space-between;"><span>選択モード on/off</span><kbd class="rice-kbd">V</kbd></li>
                            <li style="font-size:11px;color:#6b7280;">選択モード中: 行クリックで選択 / 解除、<kbd class="rice-kbd">L</kbd> でまとめてルームに追加可</li>
                        </ul>
                    </div>
                </div>

                {{-- フッタ --}}
                <div style="padding:12px 20px;border-top:1px solid #e5e7eb;background:#f9fafb;display:flex;align-items:center;justify-content:space-between;font-size:11px;color:#6b7280;">
                    <span><i class="fas fa-info-circle" style="margin-right:4px;color:#9ca3af;"></i>入力欄やモーダル表示中はショートカットを受け付けません。</span>
                    <span>このウィンドウを閉じる: <kbd class="rice-kbd">Esc</kbd></span>
                </div>
            </div>
        </div>
    </template>

    {{-- ルームバッジ展開ポップオーバ (単一インスタンス, body 直下相当の位置). 行の overflow を超えるよう position:fixed. --}}
    <div x-show="expandedRoomChipThreadId !== null && expandedRoomChipThread"
         x-cloak
         @click.outside="expandedRoomChipThreadId = null"
         @keydown.escape.window="expandedRoomChipThreadId = null"
         :style="'position:fixed;left:' + expandedRoomChipPos.x + 'px;top:' + expandedRoomChipPos.y + 'px;z-index:2500;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,0.18);padding:8px;min-width:220px;max-width:340px;'">
        <div style="display:flex;align-items:center;gap:6px;padding-bottom:6px;border-bottom:1px solid #f3f4f6;">
            <i class="fas fa-link" style="font-size:9px;color:#6b7280;"></i>
            <span style="font-size:10px;font-weight:700;color:#374151;flex:1;"
                  x-text="'紐付くルーム ' + (expandedRoomChipThread?.bundled_rooms?.length || 0) + ' 件'"></span>
            <button type="button" @click.stop="expandedRoomChipThreadId = null"
                    style="background:none;border:none;color:#9ca3af;cursor:pointer;font-size:10px;padding:0 2px;"
                    title="閉じる">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px;">
            <template x-for="r in (expandedRoomChipThread?.bundled_rooms || [])" :key="'popover-r-' + r.id">
                <button type="button"
                        @click.stop="setRoomFilter(String(r.id)); expandedRoomChipThreadId = null;"
                        class="px-2 py-0.5 rounded text-[10px] font-black border inline-flex items-center gap-1"
                        :style="r.is_private
                            ? 'background:#ede9fe;color:#6d28d9;border-color:#ddd6fe;'
                            : 'background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;'"
                        :title="(r.is_private ? '個人ルーム: ' : '共有ルーム: ') + r.name + ' に絞り込む'">
                    <i :class="r.is_private ? 'fas fa-lock' : 'fas fa-hashtag'" style="font-size:8px;"></i>
                    <span x-text="r.name"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- トースト通知 --}}
    <div class="fixed bottom-6 right-6 z-[3000] flex flex-col gap-2 pointer-events-none">
        <template x-for="t in toasts" :key="t.id">
            <div :class="{
                    'bg-green-600 text-white': t.type === 'success',
                    'bg-red-600 text-white': t.type === 'error',
                    'bg-gray-900 text-white': t.type !== 'success' && t.type !== 'error'
                 }"
                 class="px-5 py-3 rounded-xl shadow-2xl text-sm font-semibold flex items-start gap-3 max-w-md pointer-events-auto animate-in slide-in-from-bottom duration-200">
                <i class="fas mt-0.5" :class="t.type === 'success' ? 'fa-check-circle' : (t.type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle')"></i>
                <div class="flex-1 min-w-0">
                    <p class="whitespace-pre-line" x-text="t.message"></p>
                    <template x-if="t.actionLabel">
                        <button type="button" @click="invokeToastAction(t)"
                                class="mt-1.5 inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-white/20 hover:bg-white/30 text-white text-xs font-bold">
                            <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
                            <span x-text="t.actionLabel"></span>
                        </button>
                    </template>
                </div>
                <button type="button" @click="dismissToast(t.id)" class="ml-2 -mr-1 text-white/70 hover:text-white" title="閉じる">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
        </template>
    </div>

</div>

<script>
// ===== AI要約パネル: スライドイン / アウト / オンチェンジ ヘルパ =====
// onclick="..." から呼べるようグローバルに公開する.
// 状態 (aiProvider, aiModel) は emailApp() 側で管理しているので、Alpine スコープを
// 取り出して更新する.
function _riceFindEmailAppScope() {
    const el = document.querySelector('[x-data*="emailApp"]');
    if (el && typeof window.Alpine !== 'undefined' && Alpine.$data) {
        try { return Alpine.$data(el); } catch (_) {}
    }
    return null;
}
window.riceOpenAiSummaryPanel = function() {
    const backdrop = document.getElementById('riceAiSummaryBackdrop');
    const panel    = document.getElementById('riceAiSummaryModal');
    if (!panel) return;
    if (backdrop) { backdrop.style.display = 'block'; }
    panel.style.display = 'flex';
    // 次フレームで transform を 0 に → CSS transition で滑り込み
    requestAnimationFrame(() => {
        if (backdrop) backdrop.style.opacity = '1';
        panel.style.transform = 'translateX(0)';
    });
};
window.riceCloseAiSummaryPanel = function() {
    const backdrop = document.getElementById('riceAiSummaryBackdrop');
    const panel    = document.getElementById('riceAiSummaryModal');
    if (!panel) return;
    panel.style.transform = 'translateX(100%)';
    if (backdrop) backdrop.style.opacity = '0';
    setTimeout(() => {
        panel.style.display = 'none';
        if (backdrop) backdrop.style.display = 'none';
    }, 320);
    // Alpine 側 state も同期で false に
    const s = _riceFindEmailAppScope();
    if (s) s.threadSummaryOpen = false;
};
window.riceOnAiModelChange = function(value) {
    const s = _riceFindEmailAppScope();
    if (s) s.aiModel = value;
};
window.riceTriggerAiSummaryGenerate = function() {
    const s = _riceFindEmailAppScope();
    if (s && typeof s.loadThreadSummary === 'function') s.loadThreadSummary(true);
};
window.riceToggleAiSummaryPrompt = function() {
    const sec = document.getElementById('riceAiSummaryPromptSection');
    const btn = document.getElementById('riceAiSummaryPromptToggle');
    if (!sec) return;
    const showing = sec.style.display !== 'none';
    sec.style.display = showing ? 'none' : 'block';
    if (btn) btn.textContent = showing ? '追加指示を入力' : '追加指示を閉じる';
};

// ===== ナレッジ登録パネル: 同じパターンで slide-in / out / submit / mask =====
window.riceOpenKnowledgePanel = function() {
    const backdrop = document.getElementById('riceKnowledgeBackdrop');
    const panel    = document.getElementById('riceKnowledgeModal');
    if (!panel) return;
    if (backdrop) backdrop.style.display = 'block';
    panel.style.display = 'flex';
    requestAnimationFrame(() => {
        if (backdrop) backdrop.style.opacity = '1';
        panel.style.transform = 'translateX(0)';
    });
};
window.riceCloseKnowledgePanel = function() {
    const backdrop = document.getElementById('riceKnowledgeBackdrop');
    const panel    = document.getElementById('riceKnowledgeModal');
    if (!panel) return;
    panel.style.transform = 'translateX(100%)';
    if (backdrop) backdrop.style.opacity = '0';
    setTimeout(() => {
        panel.style.display = 'none';
        if (backdrop) backdrop.style.display = 'none';
    }, 320);
    const s = _riceFindEmailAppScope();
    if (s) s.knowledgeRegisterOpen = false;
};
// Esc キーでも閉じる
document.addEventListener('keydown', function(ev) {
    if (ev.key !== 'Escape') return;
    const kPanel = document.getElementById('riceKnowledgeModal');
    if (kPanel && kPanel.style.display !== 'none' && kPanel.style.transform === 'translateX(0px)') {
        window.riceCloseKnowledgePanel();
        return;
    }
});
window.riceKnowledgeApplyMask = function() {
    const el = document.getElementById('riceKnowledgeContent');
    if (!el) return;
    let text = el.value;
    text = text.replace(/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/g, '[メール]');
    text = text.replace(/(\d{2,4}-\d{2,4}-\d{4})/g, '[電話]');
    text = text.replace(/(\d{3}-\d{4})(?!\d)/g, '[郵便番号]');
    text = text.replace(/([0-9]{2,4}[\--][0-9]{2,4}[\--][0-9]{4})/g, '[電話]');
    el.value = text;
    const countEl = document.getElementById('riceKnowledgeCharCount');
    if (countEl) countEl.textContent = el.value.length + ' 字';
    const s = _riceFindEmailAppScope();
    if (s && typeof s.toast === 'function') s.toast('連絡先パターンを自動マスクしました', 'info');
};
window.riceKnowledgeSubmit = function() {
    const s = _riceFindEmailAppScope();
    if (s && typeof s.submitKnowledgeRegister === 'function') {
        s.submitKnowledgeRegister();
    }
};

function emailApp() {
    return {
        threadWidth: parseInt(localStorage.getItem('threadWidth')) || (window.innerWidth >= 1920 ? 450 : 380),
        fetching: false,
        // ルームバッジ展開ポップオーバ (行内では overflow:hidden でクリップされるため、
        // 単一インスタンスを body 直下に position:fixed で出す方式).
        expandedRoomChipThreadId: null,
        expandedRoomChipPos: { x: 0, y: 0 },
        selectedThreadId: null, selectedThread: null,
        leftTab: 'inbox', searchQuery: '',
        // ステータスタブ表示制御 (件数連動).
        // 対応不要 / 承認待ち / 迷惑メール は件数 > 0 または現在選択中の時だけサイドに表示する.
        // 受信 / 保留 / 完了 は常に表示.
        statusCounts: { inbox: 0, hold: 0, completed: 0, no_action: 0, pending: 0, spam: 0 },
        allStatusMode: (() => { try { return JSON.parse(localStorage.getItem('allStatusMode')) === true; } catch(_) { return false; } })(),
        pinnedOnlyMode: {{ isset($isPinnedView) && $isPinnedView ? 'true' : 'false' }},
        // 個人受信箱 / 共有プール 切替. 既定は「共有」.
        // 切替えると emails/search に scope パラメータが付与されてフィルタが効く.
        // 個人 = owner_user_id = self / 共有 = owner_user_id IS NULL.
        inboxScope: (() => {
            const v = localStorage.getItem('inboxScope');
            return (v === 'personal' || v === 'shared') ? v : 'shared';
        })(),
        // 共有メール / 個人メール タブ横の inbox 新着バッジ件数
        sharedInboxCount: 0,
        personalInboxCount: 0,
        // 個人メールアカウント一覧 (複数あればプルダウンで切替) + 選択中アカウント ID
        personalMailAccounts: @json($personalMailAccounts ?? []),
        selectedPersonalAccountId: (() => {
            const v = localStorage.getItem('selectedPersonalAccountId');
            return v && v !== 'all' ? Number(v) : null;
        })(),
        setPersonalAccount(idOrNull) {
            const id = idOrNull && idOrNull !== 'all' ? Number(idOrNull) : null;
            this.selectedPersonalAccountId = id;
            try { localStorage.setItem('selectedPersonalAccountId', id ? String(id) : 'all'); } catch (_) {}
            this.loadThreads();
            this.loadEmailRooms();
            this.loadSidebarThreads();
            this.loadInboxScopeBadges();
        },
        assigneeFilterId: localStorage.getItem('assigneeFilterId') || 'all',
        // ルームフィルター (チャットのルーム概念をメール一覧側にも反映)
        emailRoomFilterId: localStorage.getItem('emailRoomFilterId') || 'all',
        emailRoomsShared: [],
        emailRoomsPersonal: [],
        // 「すべて」/「ルーム未設定」タブのバッチ用カウンタ (API から取得).
        // status 別に内訳も保持. 受信 (青) / 保留 (琥珀) / 承認待ち (橙) で色分けバッジ表示.
        globalReceivedCount: 0,
        unroutedReceivedCount: 0,
        globalInboxCount: 0,
        globalHoldCount: 0,
        globalPendingCount: 0,
        unroutedInboxCount: 0,
        unroutedHoldCount: 0,
        unroutedPendingCount: 0,
        // 選択中ルームに紐付けされたスレッド一覧 (チャット画面の bundledThreads と同等)
        emailRoomBundledThreads: [],
        // 自分が非表示にしているルーム ID 集合
        hiddenRoomIds: [],
        // ===== 左サイドバー: ルーム+スレッド共通の検索入力 =====
        sidebarSearchQuery: '',
        // 各セクションの折りたたみ状態 (localStorage に保存)
        sharedRoomsCollapsed: (() => { try { return JSON.parse(localStorage.getItem('emailSharedRoomsCollapsed') || 'false'); } catch(_) { return false; } })(),
        personalRoomsCollapsed: (() => { try { return JSON.parse(localStorage.getItem('emailPersonalRoomsCollapsed') || 'false'); } catch(_) { return false; } })(),
        // 共有ルーム並び順モード: 'updated' (= updated_at desc) または 'aiueo' (50 音グループ + 各行折りたたみ).
        // ユーザが切替できる. 既定は 'updated' で従来の挙動を維持.
        sharedRoomSortMode: localStorage.getItem('emailSharedRoomSortMode') || 'updated',
        // あいうえお (50 音) グループそれぞれの折りたたみ状態. キーは「あ行」「か行」…
        kanaGroupCollapsed: (() => {
            try { return JSON.parse(localStorage.getItem('emailKanaGroupCollapsed') || '{}'); }
            catch(_) { return {}; }
        })(),
        // ルーム階層: 各 ID ごとに子の折りたたみ状態 (true = 閉じる).
        roomBranchCollapsed: (() => {
            try { return JSON.parse(localStorage.getItem('roomBranchCollapsed') || '{}'); }
            catch(_) { return {}; }
        })(),
        // ===== Undo (Ctrl+Z) スタック =====
        // 直前のステータス変更 / ルーム追加・解除 / マージ・解除を取り消すための LIFO 履歴.
        // 各要素は { label: string, undoFn: async fn, ts: number }.
        // テキスト入力 (textarea / input) にフォーカス中はネイティブの undo に任せたいので、
        // グローバル keydown ハンドラ側で入力フォーカス時は早期 return している.
        undoStack: [],
        maxUndoStack: 30,
        sidebarThreadsCollapsed: (() => { try { return JSON.parse(localStorage.getItem('emailSidebarThreadsCollapsed') || 'false'); } catch(_) { return false; } })(),
        // ===== 束ねたスレッドのバンドル帯: 展開/折りたたみ =====
        // 束ねた件数が多いルームで帯がパンクして見にくいので、デフォルトでは先頭 N 件のみ表示。
        // ユーザは「もっと見る」ボタンで全件展開できる。設定は localStorage に保存。
        bundleBandExpanded: (() => { try { return JSON.parse(localStorage.getItem('emailBundleBandExpanded') || 'false'); } catch(_) { return false; } })(),
        bundleBandCollapsedLimit: 10,  // 折りたたみ時の最大表示件数
        // 「非表示も表示」トグル
        showHiddenSidebarThreads: (() => { try { return JSON.parse(localStorage.getItem('emailShowHiddenSidebarThreads') || 'false'); } catch(_) { return false; } })(),
        // 自分が非表示にしているスレッド ID 集合 (サーバから取得)
        hiddenSidebarThreadIds: [],
        // 左サイドバーのスレッド一覧 (メール本体の受信/保留/完了 等の絞り込みとは無関係)
        // チャット / 添付ファイル と同じ /chats/threads エンドポイントを共有
        sidebarThreadList: [],
        mailRoomsCollapsed: (() => { try { return JSON.parse(localStorage.getItem('mailRoomsCollapsed') || 'false'); } catch(_) { return false; } })(),
        mailRoomsWidth: parseInt(localStorage.getItem('mailRoomsWidth') || '200', 10),
        mailRoomsResizing: false,
        // 新規ルーム作成モーダル
        mailCreateRoomOpen: false,
        mailNewRoomName: '',
        // 新規ルーム作成時の親ルーム指定 (検索ドロップダウン).
        mailNewRoomParentId: null,
        mailNewRoomParentLabel: '',
        mailNewRoomParentSearch: '',
        // ルーム階層の最大深さ (1-based). バックエンドの ChatRoom::MAX_DEPTH と同期させる.
        ROOM_MAX_DEPTH: 5,
        mailNewRoomIsPrivate: false,
        mailCreatingRoom: false,
        // ルーム編集モーダル
        mailEditRoomOpen: false,
        mailEditRoomId: null,
        mailEditRoomName: '',
        mailEditRoomIsPrivate: false,
        mailEditRoomIsCreator: false,
        mailEditRoomParentId: '',          // 親ルーム選択 (空文字 = ルート)
        mailEditParentSearch: '',          // 親ルーム検索クエリ
        mailEditParentLabel: '',           // 親ルーム入力欄に表示する文字列 (確定した名前 or 入力中の検索語)
        mailEditRoomMergeTargetId: '',     // マージ先ルーム選択
        mailEditMergeSearch: '',           // マージ先検索クエリ
        mailEditMergeLabel: '',            // マージ先入力欄表示
        mailEditingRoom: false,
        // 編集モーダル内: 振り分けルール (パターン/フィルタ) のロード状態
        routingRules: [],
        routingRulesLoading: false,
        routingRulesSubmitting: false,
        newRoutingRuleType: 'from_address',
        newRoutingRulePattern: '',
        get routingRulePlaceholder() {
            switch (this.newRoutingRuleType) {
                case 'any_address':      return '例: info1@example.com (From/To/Cc どこでも)';
                case 'any_domain':       return '例: example.com (From/To/Cc どこでも)';
                case 'from_address':     return '例: suzuki@univ-x.ac.jp (From のみ)';
                case 'from_domain':      return '例: univ-x.ac.jp (From のみ)';
                case 'subject_contains': return '例: 【○○大学様】';
                case 'to_contains':      return '例: support@';
            }
            return '';
        },
        // 公開範囲の変更権限 (編集モーダル用):
        //   - 個人ルーム → 作成者本人のみ
        //   - 共有ルーム → 全員可
        // (= mailEditRoomIsCreator は別の判定. 個人ルームに対しては作成者でないと変更できない,
        //    共有ルームに対しては全員 OK, を 1 箇所に集約.)
        get mailEditPublicityAllowed() {
            if (!this.mailEditRoomId) return false;
            // mailEditRoomIsPrivate は編集中の値だが、サーバ判定は元の room.is_private に基づく.
            // ここでは元の値 (= openMailEditRoom 時にセットされた値) を見たいので、
            // emailRoomsShared / emailRoomsPersonal から元 room を再取得する.
            const all = [...(this.emailRoomsShared || []), ...(this.emailRoomsPersonal || [])];
            const orig = all.find(r => Number(r.id) === Number(this.mailEditRoomId));
            if (!orig) return false;
            if (orig.is_private) return this.mailEditRoomIsCreator;
            return true;
        },
        // 編集/削除権限の判定に使うログインユーザー ID
        myUserId: @json(auth()->id() ?? null),
        // 「スレッドをルームに追加」モーダル (単一/複数両対応)
        addToRoomOpen: false,
        addToRoomThreadIds: [],
        // 対象スレッドのフルリスト展開トグル (6 件以上ある時の「他 N 件を表示」用)
        addToRoomShowAllTargets: false,
        // モーダル内インライン作成用の入力名と作成中フラグ。
        // 旧 createRoomAndAttach は prompt() を出していたが、
        // 部分一致サジェストを出すためフォーム化した。
        addToRoomNewName: '',
        addToRoomCreating: false,
        // 「ルームに追加と同時に振り分けルールも登録」用の state
        addToRoomCreateRule: false,
        addToRoomRuleType: 'from_address',
        addToRoomRulePattern: '',
        // ===== 振り分けルール フォローアップ モーダル =====
        // 追加完了したルームに対し「ルール作成しますか?」と聞く別ウィンドウ.
        routingFollowupOpen: false,
        routingFollowupRoomId: null,
        routingFollowupRoomName: '',
        routingFollowupRoomCreated: false,    // 新規作成だったか (バッジ表示用)
        routingFollowupAttachedCount: 0,       // このフロー内で何件のスレッドを追加したか
        routingFollowupType: 'from_address',
        routingFollowupPattern: '',
        routingFollowupSaving: false,
        routingFollowupAddedRules: [],         // [{ type, type_label, pattern, backfilled }, ...]

        // ===== AND/OR 複合条件ビルダー (routingFollowup 用) =====
        // rfBuilderMode = true でルートグループ + 子 (リーフ or サブグループ) の編集 UI が出る.
        // 保存時に POST conditions として送信. 深さ 2 (= サブグループまで).
        rfBuilderMode: false,
        rfBuilderTree: { logic: 'or', items: [ { type: 'any_address', pattern: '' } ] },
        rfBuilderAddItem() {
            this.rfBuilderTree.items.push({ type: 'any_address', pattern: '' });
        },
        rfBuilderAddGroup() {
            // ルート OR の中にサブグループ AND (デフォルトで反対 logic) を入れる.
            // (A AND B) OR C のような典型形をすぐ作れるようにするため.
            const subLogic = this.rfBuilderTree.logic === 'and' ? 'or' : 'and';
            this.rfBuilderTree.items.push({
                logic: subLogic,
                items: [
                    { type: 'any_address', pattern: '' },
                    { type: 'any_address', pattern: '' },
                ],
            });
        },
        rfBuilderRemoveItem(idx) {
            this.rfBuilderTree.items.splice(idx, 1);
            if (this.rfBuilderTree.items.length === 0) {
                this.rfBuilderTree.items.push({ type: 'any_address', pattern: '' });
            }
        },
        rfBuilderAddSubItem(idx) {
            const grp = this.rfBuilderTree.items[idx];
            if (grp && Array.isArray(grp.items)) {
                grp.items.push({ type: 'any_address', pattern: '' });
            }
        },
        rfBuilderRemoveSubItem(idx, subIdx) {
            const grp = this.rfBuilderTree.items[idx];
            if (grp && Array.isArray(grp.items)) {
                grp.items.splice(subIdx, 1);
                if (grp.items.length === 0) {
                    this.rfBuilderRemoveItem(idx);
                }
            }
        },
        rfBuilderResetTree() {
            this.rfBuilderTree = { logic: 'or', items: [ { type: 'any_address', pattern: '' } ] };
        },
        get rfBuilderValid() {
            if (!this.rfBuilderTree?.items || this.rfBuilderTree.items.length === 0) return false;
            for (const it of this.rfBuilderTree.items) {
                if (it.logic) {
                    if (!Array.isArray(it.items) || it.items.length === 0) return false;
                    for (const sub of it.items) {
                        if (!sub || !String(sub.pattern || '').trim()) return false;
                    }
                } else {
                    if (!String(it.pattern || '').trim()) return false;
                }
            }
            return true;
        },

        // ===== 迷惑メール ブロックルール フォローアップ モーダル =====
        // 「迷惑メール」マーク後に開く. routing と同じ思想でルール (From/To/Cc/件名/本文 など) を
        // 自由に追加できる. すでにスレッドは spam にマーク済みでこのモーダルが開く.
        spamRuleFollowupOpen: false,
        spamRuleFollowupThreadId: null,        // どのスレッドから開いたか (バルクは null)
        spamRuleFollowupType: 'sender_address',
        spamRuleFollowupPattern: '',
        spamRuleFollowupSaving: false,
        spamRuleFollowupAddedRules: [],        // [{ type, type_label, pattern }, ...]

        // ===== AND/OR ビルダー (spamRuleFollowup 用) =====
        // routingFollowup と同形. POST /api/mail-block-rules に conditions ツリーで送信.
        srBuilderMode: false,
        srBuilderTree: { logic: 'or', items: [ { type: 'sender_address', pattern: '' } ] },
        srBuilderAddItem() {
            this.srBuilderTree.items.push({ type: 'sender_address', pattern: '' });
        },
        srBuilderAddGroup() {
            const subLogic = this.srBuilderTree.logic === 'and' ? 'or' : 'and';
            this.srBuilderTree.items.push({
                logic: subLogic,
                items: [
                    { type: 'sender_address', pattern: '' },
                    { type: 'sender_address', pattern: '' },
                ],
            });
        },
        srBuilderRemoveItem(idx) {
            this.srBuilderTree.items.splice(idx, 1);
            if (this.srBuilderTree.items.length === 0) {
                this.srBuilderTree.items.push({ type: 'sender_address', pattern: '' });
            }
        },
        srBuilderAddSubItem(idx) {
            const grp = this.srBuilderTree.items[idx];
            if (grp && Array.isArray(grp.items)) {
                grp.items.push({ type: 'sender_address', pattern: '' });
            }
        },
        srBuilderRemoveSubItem(idx, subIdx) {
            const grp = this.srBuilderTree.items[idx];
            if (grp && Array.isArray(grp.items)) {
                grp.items.splice(subIdx, 1);
                if (grp.items.length === 0) this.srBuilderRemoveItem(idx);
            }
        },
        srBuilderResetTree() {
            this.srBuilderTree = { logic: 'or', items: [ { type: 'sender_address', pattern: '' } ] };
        },
        get srBuilderValid() {
            if (!this.srBuilderTree?.items || this.srBuilderTree.items.length === 0) return false;
            for (const it of this.srBuilderTree.items) {
                if (it.logic) {
                    if (!Array.isArray(it.items) || it.items.length === 0) return false;
                    for (const sub of it.items) {
                        if (!sub || !String(sub.pattern || '').trim()) return false;
                    }
                } else {
                    if (!String(it.pattern || '').trim()) return false;
                }
            }
            return true;
        },
        // S ショートカット (quickMarkSpam) からモーダルを開いた時、閉じた瞬間に飛ぶべき次スレッド ID.
        // モーダル中はカーソル移動を抑止し、閉じた瞬間に jump させる.
        _spamFollowupPendingJumpId: null,
        // 迷惑メールフォローアップ用の thread キャッシュ. quickMarkSpam が list-row で呼ばれて
        // selectedThread / threadEmails が当該 thread でない場合のフォールバック.
        _spamFollowupThreadCache: null,
        // ルーム振り分けフォローアップ用の thread キャッシュ (同じ思想で defense in depth).
        _routingFollowupThreadCache: null,
        get addToRoomRulePlaceholder() {
            // routingFollowup と addToRoom modal の両方で使う placeholder.
            // routingFollowupType が新タイプの場合も拾えるよう同じ map にしている.
            const t = this.routingFollowupType || this.addToRoomRuleType;
            switch (t) {
                case 'any_address':      return '例: info1@example.com (From/To/Cc どこでも)';
                case 'any_domain':       return '例: example.com (From/To/Cc どこでも)';
                case 'from_address':     return '例: suzuki@univ-x.ac.jp (From のみ)';
                case 'from_domain':      return '例: univ-x.ac.jp (From のみ)';
                case 'subject_contains': return '例: 【○○大学様】';
                case 'to_contains':      return '例: support@';
            }
            return '';
        },
        // ===== クイックフィル: 選択中スレッドから引用できる候補値 =====
        // 最初の選択スレッドの latest_email を起点に from_address / ドメイン / 件名 / to を抽出.
        // 各ゲッタは「値が無ければ空文字」を返す → x-show で対応するチップを出し分ける.
        //
        // ★ ルックアップ順 (前のレイヤで取れたらそれを採用):
        //    1) this.threads (メール一覧キャッシュ)
        //    2) this.emailRoomBundledThreads (ルームのバンドル先)
        //    3) this.selectedThread (今ワークスペースで開いているスレッド)
        //    4) this.threadEmails (selectedThread の最新メールから latest_email を合成)
        //
        // 旧実装は (1) しか見ておらず、迷惑メールにマークした後に this.threads から消えると
        // チップがすべて出なくなる事故になっていた (spam followup モーダルの不具合).
        _addToRoomFirstThread() {
            const firstId = (this.addToRoomThreadIds || [])[0];
            if (!firstId) return null;

            // (0) ★ 呼び出し側からキャッシュされた thread (最優先).
            //     openSpamRuleFollowup(threadId, threadObj) / _openRoutingFollowup で渡される.
            //     これがあれば確実に当たる.
            const cached = this._spamFollowupThreadCache || this._routingFollowupThreadCache;
            if (cached && Number(cached.id) === Number(firstId)) {
                return cached;
            }

            // (1) this.threads (メール一覧)
            let t = (this.threads || []).find(t => t && Number(t.id) === Number(firstId));
            if (t) return t;

            // (2) ルームバンドル
            t = (this.emailRoomBundledThreads || []).find(t => t && Number(t.id) === Number(firstId));
            if (t) return t;

            // (3) ワークスペースで開いているスレッド
            const sel = this.selectedThread;
            if (sel && Number(sel.id) === Number(firstId)) {
                // selectedThread には latest_email が無いこともあるので、
                // ある場合はそのまま, 無ければ threadEmails の最新を合成する.
                if (sel.latest_email) return sel;
                const latest = this._latestThreadEmailObject();
                if (latest) {
                    return { id: sel.id, subject: sel.subject, latest_email: latest };
                }
                return sel;
            }

            // (4) selectedThread が無くても threadEmails があれば合成
            const latest = this._latestThreadEmailObject();
            if (latest) {
                return { id: firstId, subject: latest.subject, latest_email: latest };
            }
            return null;
        },
        // threadEmails (= 開いているスレッドの全メール) から「最新メール」を返す.
        // received_at 降順 → なければ id 降順. クイックフィルチップで使う最低限のフィールドを揃える.
        _latestThreadEmailObject() {
            const emails = this.threadEmails || [];
            if (emails.length === 0) return null;
            const sorted = [...emails].sort((a, b) => {
                const ra = a.received_at ? new Date(a.received_at).getTime() : 0;
                const rb = b.received_at ? new Date(b.received_at).getTime() : 0;
                if (ra !== rb) return rb - ra;
                return (b.id || 0) - (a.id || 0);
            });
            const e = sorted[0];
            return {
                subject:      e.subject || '',
                from_address: e.from_address || '',
                from_label:   e.from_label || '',
                to_address:   e.to_address || '',
                cc:           e.cc || '',
                bcc:          e.bcc || '',
            };
        },
        // 「追加対象のスレッド」一覧 (件名 + 差出人) をモーダル上部に出すための整形配列.
        // メイン一覧 (this.threads) と、ルームのバンドル先 (this.emailRoomBundledThreads) 両方から検索する.
        //   - emailRoomBundledThreads はサブジェクト/差出人を含むので両方ある場合は emailRoomBundledThreads を優先.
        //   - メイン一覧から消えている (= バンドル先しかない) スレッドでも件名が出るように.
        get addToRoomTargetThreads() {
            const ids = this.addToRoomThreadIds || [];
            if (ids.length === 0) return [];
            const fromMain = new Map((this.threads || []).map(t => [Number(t.id), t]));
            const fromBundle = new Map((this.emailRoomBundledThreads || []).map(t => [Number(t.id), t]));
            return ids.map(id => {
                const nid = Number(id);
                const t = fromMain.get(nid) || fromBundle.get(nid) || { id: nid };
                const fromLabel = t.from_label || t.latest_email?.from_label
                    || t.from_address || t.latest_email?.from_address || '';
                return {
                    id: nid,
                    subject: t.subject || t.latest_email?.subject || '(件名なし)',
                    _fromLabel: fromLabel,
                };
            });
        },
        get addToRoomGuessFromAddress() {
            const t = this._addToRoomFirstThread();
            return (t?.latest_email?.from_address || t?.from_address || '').trim();
        },
        get addToRoomGuessFromDomain() {
            const addr = this.addToRoomGuessFromAddress;
            const at = addr.lastIndexOf('@');
            return at >= 0 ? addr.slice(at + 1) : '';
        },
        get addToRoomGuessSubject() {
            const t = this._addToRoomFirstThread();
            let s = (t?.subject || '').trim();
            // Re:/Fwd: と [#TICKET-...] / [#RICE-...] を引用前に剥がす
            s = s.replace(/^(?:\s*(?:Re|RE|re|Fwd|FWD|fwd|Fw|FW)\s*:\s*)+/g, '');
            s = s.replace(/\[#?(?:TICKET|RICE)-\d{1,12}\]\s*/gi, '');
            return s.trim();
        },
        // ----- カンマ区切り文字列 → アドレス配列 (空 / @ 無しを除去, 最大 N 件) -----
        _splitAddrList(raw, max) {
            if (!raw) return [];
            return String(raw).split(',')
                .map(s => s.trim())
                .filter(s => s && s.includes('@'))
                .slice(0, max || 6);
        },
        _extractDomainSet(addrList) {
            const set = new Set();
            for (const a of addrList) {
                const at = a.lastIndexOf('@');
                if (at >= 0) set.add(a.slice(at + 1).toLowerCase());
            }
            return Array.from(set);
        },

        // ===== クイックフィル候補 (To / Cc / Bcc) =====
        get addToRoomGuessToList() {
            const t = this._addToRoomFirstThread();
            return this._splitAddrList(t?.latest_email?.to_address || t?.to_address, 6);
        },
        get addToRoomGuessToDomainList() {
            return this._extractDomainSet(this.addToRoomGuessToList).slice(0, 4);
        },
        // 後方互換: 既存のテンプレートが addToRoomGuessToAddress を参照しているので残す (先頭の To).
        get addToRoomGuessToAddress() {
            return this.addToRoomGuessToList[0] || '';
        },

        get addToRoomGuessCcList() {
            const t = this._addToRoomFirstThread();
            return this._splitAddrList(t?.latest_email?.cc || t?.cc, 6);
        },
        get addToRoomGuessCcDomainList() {
            return this._extractDomainSet(this.addToRoomGuessCcList).slice(0, 4);
        },

        get addToRoomGuessBccList() {
            const t = this._addToRoomFirstThread();
            return this._splitAddrList(t?.latest_email?.bcc || t?.bcc, 6);
        },
        get addToRoomGuessBccDomainList() {
            return this._extractDomainSet(this.addToRoomGuessBccList).slice(0, 4);
        },
        // チップクリック: タイプを切り替えてパターン欄に流し込む
        useRuleQuickFill(type, value) {
            if (!value) return;
            this.addToRoomRuleType = type;
            this.addToRoomRulePattern = String(value);
        },
        // モーダル内のキーボードナビゲーション (J/K) 用. 共有 + 個人 の全候補から
        // 1 つにフォーカスを当てる. Enter で confirmAddToRoom.
        addToRoomHighlightId: null,
        sortOrder: 'desc',
        statusLabels: { inbox: '受信', hold: '保留', completed: '完了', no_action: '対応不要', pending: '承認待ち', spam: '迷惑メール', trash: 'ゴミ箱' },
        // ステータスタブの表示制御:
        //   - 常時表示: inbox / hold / completed
        //   - no_action / pending / spam / trash は普段は非表示. 件数があっても出さない.
        //     ・・・ メニューから明示的に切替えた時だけ表示 (= leftTab がそれ).
        // 「件数連動で出す」より「いつもスッキリ 3 タブ」を優先するユーザの要望に対応.
        get visibleStatusTabs() {
            const always = ['inbox', 'hold', 'completed'];
            const conditional = ['no_action', 'pending', 'spam', 'trash'];
            const out = [...always];
            for (const t of conditional) {
                if (this.leftTab === t) out.push(t);
            }
            return out;
        },
        get hiddenStatusTabs() {
            const conditional = ['no_action', 'pending', 'spam', 'trash'];
            // 現在選択中以外は全部「隠れている」扱い (・・・ メニューから呼び出せる).
            return conditional.filter(t => this.leftTab !== t);
        },

        // ゴミ箱ビュー状態. leftTab === 'trash' のときに右ペインで使う.
        // kind = 'thread' (既定) / 'email'.
        trashKind: 'thread',
        trashItems: [],
        trashRetentionDays: 30,
        trashLoading: false,
        async loadTrash(kind = null) {
            if (kind) this.trashKind = kind;
            this.trashLoading = true;
            try {
                const res = await fetch(`/trash?kind=${encodeURIComponent(this.trashKind)}`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                this.trashItems = data.items || [];
                this.trashRetentionDays = data.retention_days ?? 30;
            } catch (e) {
                this.toast('ゴミ箱の読み込みに失敗しました: ' + (e?.message || ''), 'error');
                this.trashItems = [];
            } finally {
                this.trashLoading = false;
            }
        },
        async restoreFromTrash(item) {
            const url = this.trashKind === 'email'
                ? `/emails/${item.id}/restore`
                : `/threads/${item.id}/restore`;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                this.toast(this.trashKind === 'email' ? 'メールを復元しました' : 'スレッドを復元しました', 'success');
                await this.loadTrash();
            } catch (e) {
                this.toast('復元に失敗しました: ' + (e?.message || ''), 'error');
            }
        },
        async hardDeleteFromTrash(item) {
            if (!confirm(this.trashKind === 'email'
                ? 'このメールを完全に削除します. 元に戻せません. 続行しますか?'
                : 'このスレッドを完全に削除します. 元に戻せません. 続行しますか?')) return;
            const url = this.trashKind === 'email'
                ? `/emails/${item.id}?hard=1`
                : `/threads/${item.id}?hard=1`;
            try {
                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                this.toast('完全に削除しました', 'success');
                await this.loadTrash();
            } catch (e) {
                this.toast('完全削除に失敗しました: ' + (e?.message || ''), 'error');
            }
        },
        threadEmails: [], threadMerges: [], expandedEmailIds: [],
        // スレッド内部のメール個別ナビ用 ([ / ] ショートカット). null = 未選択.
        // スレッドを切替えると closeWorkspace / loadThread でリセットする.
        _focusedEmailId: null,
        // スレッド内検索 (右ペインに表示中のスレッドの全メールを横断検索)。
        // 入力するとリアルタイムに filteredThreadEmails が絞り込まれる。
        threadInnerSearchQuery: '',
        // キーボードショートカット ヘルプモーダルの開閉
        shortcutsModalOpen: false,
        // メール本文の表示形式 (HTML/テキスト): {email_id: 'html'|'text'}。HTML がある場合のデフォルトは 'html'
        emailViewModes: {},
        // AI要約 (スレッド全体)
        threadSummaryOpen: false, threadSummaryLoading: false, threadSummary: null,
        threadSummaryError: '', threadSummaryCopied: false,

        // AI チャット (要約 / 返信案 をブラッシュアップする多ターン対話)
        // - スレッド × kind=summary|reply で 1 セッション (サーバ側で永続化)
        // - 右側スライドインパネルとして表示. 既存サイドビューは崩さない.
        aiChat: {
            open: false,
            kind: 'summary',            // 'summary' / 'reply'
            sessionId: null,
            messages: [],
            input: '',
            sending: false,             // 送信直後 (assistant pending 中) の連打防止
            pollTimer: null,
            modelPick: '',
            provider: null,
            model:    null,
            // スキル選択 (送信時に skill: <key> として渡し, サーバが system_prompt を切り替える)
            skillKey: null,
            // ナレッジ コレクション一覧 (/api/knowledge/collections のキャッシュ)
            collections: [],
            collectionsLoaded: false,
            // '/' スラッシュコマンドポップアップ
            skillSlash: {
                open: false,
                query: '',      // '/' の後ろのフィルタ文字列
                activeIdx: 0,
                tokenStart: -1, // 入力テキスト内の '/' の位置 (削除用)
            },
        },
        // AI モデルピッカー (要約共通)
        aiPickerLoading: false, aiPickerLoaded: false,
        aiProvider: 'ollama', aiModel: '',
        aiOllamaModels: [], aiClaudeModels: [], aiGeminiModels: [],
        aiHasClaudeKey: false, aiHasGeminiKey: false,
        // ナレッジ登録 (メール本文を編集してから登録)
        knowledgeRegisterOpen: false, knowledgeLoading: false, knowledgeSaving: false,
        knowledgeForm: null, knowledgeError: '',
        // AI 要約スキル / プロンプト編集 (ユーザー個別、show_in_summary=true のみ)
        aiSkills: @json($userSummarySkills ?? $userAiSkills ?? config('ai_skills.skills', [])),
        summarySkill: localStorage.getItem('summarySkill') || @json(collect($userSummarySkills ?? [])->filter(fn($s) => ($s['is_default_summary'] ?? false))->keys()->first() ?? array_key_first($userSummarySkills ?? []) ?? 'summarize'),
        summaryUserPrompt: '',
        summaryShowPrompt: false,
        // 要約モーダル用スラッシュコマンド
        summarySlash: { open: false, query: '', startPos: 0, activeIdx: 0, loading: false },
        summaryCollections: [],
        summaryCollectionsLoaded: false,
        // チャット関連 (スレッド毎)
        chatOpen: false, chatComments: [], chatLoading: false, chatInput: '', chatSending: false,
        chatPollIntervalId: null,
        chatPanelWidth: parseInt(localStorage.getItem('chatPanelWidth') || '360', 10),
        // チャット開閉後にバッジを抑制するための明示フラグ (true の間は 0)
        _chatReadJustNow: false,
        // チャットスコープ: 'thread' = スレッド全体 / 'email' = 特定メール
        chatScope: { kind: 'thread', email_id: null, email_subject: '', email_from: '' },
        // 直近に開いた email スコープ (「メール」トグルで復元するため)
        lastEmailScope: null,
        // 最新へスクロールボタン用 (ユーザーがスクロールアップしている時に表示)
        chatScrolledUp: false,
        // スレッド全体チャット添付
        chatPendingFiles: [],
        // @メンション機能
        mentionOpen: false, mentionQuery: '', mentionStart: -1, mentionIndex: 0,
        selectionMode: false, selectedThreadIds: [], longPressTimer: null, isLongPressing: false,
        // マウスオーバー中の行の ID. ショートカット (E, S, D, L 等) の対象を
        // 「ホバー中の行」 → 「選択中の行」 の順で解決するため.
        // Gmail / Spark 風の「カーソル位置にアクション」UX を実現する.
        hoveredThreadId: null,
        mergeModalOpen: false, mergeTargetId: null,
        // mergeSelected 時にキャッシュする「候補スレッドのスナップショット」。
        // loadThreads 等で threads 配列が更新されても、モーダル内の件名表示が壊れないようにする。
        // 構造: [{ id, subject, last_email_at }]
        // 注意: 旧名 _mergeCandidates だったが、 Alpine 3 で _ 始まりは reserved の懸念があるため改名。
        mergeCandidates: [],
        threads: [], threadsLoading: false, syncError: null,
        // 永続表示: ページを開きっぱなしのバックグラウンド取得 / scheduler / 過去の取得が失敗していた場合のバナー用。
        // モーダル (syncError) は手動操作にだけ出すが、これはどんなときも見える上部バナー。
        persistentSyncError: null,
        // ↑ がセットされた時刻 (epoch ms)。これ以降の自動クリアは無視するためのタイムスタンプ。
        // 仕組み: webklex は POP3 で「認証失敗を握り潰して次の fetch では 200/0 件」と返すケースがあり、
        // その「自称成功」でエラーバナーが消えるのを防ぐ。クリアは「ユーザの X 押下」または
        // 「実際に取り込み件数 > 0」のときのみ許可する。
        persistentSyncErrorAt: 0,
        // 取得は成功したが個別メールでエラーがあったとき (= 部分成功) の警告バナー
        persistentSyncWarning: null,
        // バックグラウンド失敗時、最初の 1 回だけモーダルを出す (連続失敗で何度も出ないように)。
        // 一度成功すると null に戻り、また失敗したら出す。
        bgErrorAlreadyShown: false,
        users: [],
        pendingApprovals: [],
        // 選択中スレッドが所属しているルーム { shared: [...], private: [...] }
        // スレッド詳細ヘッダに「どのルームに参加しているか」を表示するため
        threadBundledRooms: { shared: [], private: [] },
        toasts: [],
        virtualScroll: { startIndex: 0, endIndex: 30, rowHeight: 128, viewportHeight: 600, buffer: 10 },
        pollIntervalId: null, pollFailCount: 0, basePollDelay: 60000, maxPollDelay: 300000, currentPollDelay: 60000,

        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },
        // スレッド上部チャットの未読件数 (チャットを開いている / 開いた直後は 0)
        get threadChatUnread() {
            if (this.chatOpen || this._chatReadJustNow) return 0;
            const row = (this.threads || []).find(t => t.id === this.selectedThreadId);
            return row?.unread_chat_count || 0;
        },
        jsonHeaders() {
            return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' };
        },
        toast(message, type = 'info', opts = {}) {
            const id = Date.now() + Math.random();
            const ttl = opts.ttl ?? (opts.actionLabel ? 12000 : 3500);  // アクション付きは長め
            this.toasts.push({
                id, message, type,
                actionLabel: opts.actionLabel ?? null,
                actionUrl:   opts.actionUrl   ?? null,
                actionFn:    opts.actionFn    ?? null,
            });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, ttl);
        },
        dismissToast(id) { this.toasts = this.toasts.filter(t => t.id !== id); },
        invokeToastAction(t) {
            if (t.actionFn) { try { t.actionFn(); } catch (_) {} }
            if (t.actionUrl) { window.open(t.actionUrl, '_blank'); }
            this.dismissToast(t.id);
        },

        // OS のデスクトップ通知 (許可済みかつタブ非フォーカス時のみ)
        _notifyDesktop(title, body, opts = {}) {
            try {
                if (!('Notification' in window)) return;
                const open = (perm) => {
                    if (perm !== 'granted') return;
                    const n = new Notification(title, { body, tag: opts.tag || ('rice-ai-' + Date.now()) });
                    if (opts.onClick) {
                        n.onclick = (e) => { e.preventDefault(); window.focus(); opts.onClick(); n.close(); };
                    }
                };
                if (document.hasFocus() && !opts.force) return;
                if (Notification.permission === 'granted') open('granted');
                else if (Notification.permission !== 'denied') Notification.requestPermission().then(open);
            } catch (_) {}
        },

        // ===== 左サイドバー: 検索クエリで共有/個人ルーム / スレッドを横断絞り込み =====
        get _sidebarQuery() { return (this.sidebarSearchQuery || '').toLowerCase().trim(); },
        // ルームを「新着優先」で並べる比較関数.
        // 優先順位:
        //   1. メンション未読あり (mention_chat_count > 0)
        //   2. 受信メールあり (received_email_count > 0) または チャット未読あり (unread_chat_count > 0)
        //   3. それ以外
        // 同じ階層内では、件数が多い方を上、最後に名前で安定ソート。
        // 旧実装はサーバ側の updated_at 順だったため、新着が来ても順位が変わらず
        // 「どこに新着があるか分かりにくい」状態だった。
        _roomNewMailRank(r) {
            const mention = Number(r.mention_chat_count || 0);
            const mail    = Number(r.received_email_count || 0);
            const chat    = Number(r.unread_chat_count    || 0);
            if (mention > 0) return 0;          // 最優先
            if (mail > 0 || chat > 0) return 1; // 新着あり
            return 2;                            // 静かなルーム
        },
        _sortRoomsByNewMail(rooms) {
            return rooms.slice().sort((a, b) => {
                const ra = this._roomNewMailRank(a);
                const rb = this._roomNewMailRank(b);
                if (ra !== rb) return ra - rb;
                // 同階層内: バッチ件数の合計が多い方を上 (新着が多い順)
                const sa = Number(a.received_email_count || 0)
                         + Number(a.unread_chat_count    || 0)
                         + Number(a.mention_chat_count   || 0);
                const sb = Number(b.received_email_count || 0)
                         + Number(b.unread_chat_count    || 0)
                         + Number(b.mention_chat_count   || 0);
                if (sa !== sb) return sb - sa;
                // 最後は名前で安定ソート
                return (a.name || '').localeCompare(b.name || '', 'ja');
            });
        },
        get filteredSharedRooms() {
            const q = this._sidebarQuery;
            const hidden = new Set(this.hiddenRoomIds || []);
            let base = this.emailRoomsShared || [];
            // 非表示も表示モードが OFF の時は除外
            if (!this.showHiddenSidebarThreads) base = base.filter(r => !hidden.has(Number(r.id)));
            if (q) base = base.filter(r => (r.name || '').toLowerCase().includes(q));
            // 共有ルームはサーバ側の更新順 (updated_at desc) をそのまま採用.
            // ただし「自分が」ピン留めしたルームは常に先頭に並べる (per-user 並び替え).
            // ピン留めはユーザ毎の状態なので、他メンバーから見える順序は変わらない.
            base = [...base].sort((a, b) => {
                const ap = a.is_pinned_chat ? 1 : 0;
                const bp = b.is_pinned_chat ? 1 : 0;
                if (ap !== bp) return bp - ap; // ピン留めを先頭へ
                return 0; // 同順位は元の (updated_at desc) 並びを維持
            });
            return base;
        },

        // ===== Undo (Ctrl+Z) ヘルパ =====
        // 各 mutation サイト (ステータス変更 / ルーム追加・解除 / マージ・解除) が
        // 「逆操作の closure」を積む. Ctrl+Z で 1 つ pop して await 実行.
        _pushUndoAction(label, undoFn) {
            if (typeof undoFn !== 'function') return;
            this.undoStack.push({ label, undoFn, ts: Date.now() });
            if (this.undoStack.length > this.maxUndoStack) this.undoStack.shift();
        },
        async undoLastAction() {
            const action = this.undoStack.pop();
            if (!action) {
                this.toast('元に戻せる操作がありません', 'info');
                return;
            }
            try {
                await action.undoFn();
                this.toast('元に戻しました: ' + action.label, 'success');
            } catch (e) {
                // 再 pop しない (二重 retry で状態が更に崩れるのを避ける)
                this.toast('戻せませんでした: ' + (e?.message || e), 'error');
            }
        },

        // ===== 共有ルームの並び順切替 (更新順 / あいうえお順) =====
        // 「あいうえお」モードでは行 (あ行・か行・…・漢字・A-Z・その他) ごとに
        // セクション化し、各セクションを折りたたみ可能にする。
        toggleSharedRoomSortMode() {
            this.sharedRoomSortMode = this.sharedRoomSortMode === 'aiueo' ? 'updated' : 'aiueo';
            try { localStorage.setItem('emailSharedRoomSortMode', this.sharedRoomSortMode); } catch(_) {}
        },
        toggleKanaGroup(row) {
            this.kanaGroupCollapsed = { ...this.kanaGroupCollapsed, [row]: !this.kanaGroupCollapsed[row] };
            try { localStorage.setItem('emailKanaGroupCollapsed', JSON.stringify(this.kanaGroupCollapsed)); } catch(_) {}
        },
        // ルーム名の先頭文字から所属する 50 音「行」を判定.
        // カタカナ → ひらがな範囲へ正規化したうえで range 比較.
        // 記号は読み飛ばす. 該当しなければ「漢字」「A-Z」「0-9」「その他」のいずれか.
        _kanaRowOf(name) {
            if (!name) return 'その他';
            let s = String(name).normalize('NFKC').replace(/^[\s\p{P}\p{S}]+/u, '');
            if (!s) return 'その他';
            const c = s[0];
            const code = c.codePointAt(0);
            let h = c;
            if (code >= 0x30A1 && code <= 0x30F6) {
                // カタカナ → ひらがなコードへシフト (0x60 差).
                h = String.fromCodePoint(code - 0x60);
            }
            const code2 = h.codePointAt(0);
            if (code2 >= 0x3041 && code2 <= 0x3096) {
                const ranges = [
                    ['あ行', 'ぁ', 'お'],
                    ['か行', 'か', 'ご'],
                    ['さ行', 'さ', 'ぞ'],
                    ['た行', 'た', 'ど'],
                    ['な行', 'な', 'の'],
                    ['は行', 'は', 'ぽ'],
                    ['ま行', 'ま', 'も'],
                    ['や行', 'ゃ', 'よ'],
                    ['ら行', 'ら', 'ろ'],
                    ['わ行', 'ゎ', 'ん'],
                ];
                for (const [row, lo, hi] of ranges) {
                    if (code2 >= lo.codePointAt(0) && code2 <= hi.codePointAt(0)) return row;
                }
            }
            if (/[A-Za-z]/.test(c)) return 'A-Z';
            if (/[0-9]/.test(c)) return '0-9';
            // 漢字 (CJK 統合漢字 / 拡張) の簡易判定
            if (/\p{Script=Han}/u.test(c)) return '漢字';
            return 'その他';
        },
        // 共有ルームを表示用にフラット化した配列を返す.
        // - 更新順モードの時: [{kind:'room', data:r, key}, ...]
        // - あいうえおモードの時: [{kind:'header', row, count, key}, {kind:'room', data:r, key}, ...]
        // x-for で 1 ループで処理できるよう header と room を同じ配列に流す.
        // 階層化したルームを DFS で展開する.
        //   - data: room, depth: number(0=ルート), hasChildren: bool
        //   - 折りたたみ状態は this.roomBranchCollapsed[id] = true で「閉じる」.
        _walkRoomTree(rooms) {
            const byParent = new Map(); // parent_id (or 'root') -> [room, ...]
            const idSet = new Set(rooms.map(r => Number(r.id)));
            for (const r of rooms) {
                // 親が現在の filtered 集合に居ない場合はルート扱いする (検索フィルタ等で
                // 一部だけ残ったケースで「孤立して見えなくなる」のを防ぐ).
                const pid = r.parent_room_id && idSet.has(Number(r.parent_room_id))
                    ? String(r.parent_room_id) : 'root';
                if (!byParent.has(pid)) byParent.set(pid, []);
                byParent.get(pid).push(r);
            }
            // 各層をサイドバーソート (新着優先) で安定化
            for (const [k, list] of byParent) {
                byParent.set(k, this._sortRoomsByNewMail(list));
            }
            const out = [];
            const dfs = (parentKey, depth) => {
                const list = byParent.get(parentKey) || [];
                for (const r of list) {
                    const childKey = String(r.id);
                    const hasChildren = byParent.has(childKey);
                    out.push({ kind: 'room', data: r, depth, hasChildren, key: 'r-' + r.id });
                    if (hasChildren && !this.roomBranchCollapsed[r.id]) {
                        dfs(childKey, depth + 1);
                    }
                }
            };
            dfs('root', 0);
            return out;
        },
        toggleRoomBranch(id) {
            this.roomBranchCollapsed = { ...this.roomBranchCollapsed, [id]: !this.roomBranchCollapsed[id] };
            try { localStorage.setItem('roomBranchCollapsed', JSON.stringify(this.roomBranchCollapsed)); } catch(_) {}
        },

        get filteredSharedRoomsForRender() {
            const base = this.filteredSharedRooms || [];
            if (this.sharedRoomSortMode === 'aiueo') {
                // 50 音モードでは階層を一旦無視してフラットに並べる (グループ重視).
                const order = ['あ行','か行','さ行','た行','な行','は行','ま行','や行','ら行','わ行','漢字','A-Z','0-9','その他'];
                const groups = {};
                for (const r of base) {
                    const row = this._kanaRowOf(r.name);
                    if (!groups[row]) groups[row] = [];
                    groups[row].push(r);
                }
                const coll = new Intl.Collator('ja');
                for (const k of Object.keys(groups)) {
                    groups[k].sort((a, b) => coll.compare(a.name || '', b.name || ''));
                }
                const out = [];
                for (const row of order) {
                    if (!groups[row] || groups[row].length === 0) continue;
                    out.push({ kind: 'header', row, count: groups[row].length, key: 'h-' + row });
                    if (!this.kanaGroupCollapsed[row]) {
                        for (const r of groups[row]) out.push({ kind: 'room', data: r, depth: 0, hasChildren: false, key: 'r-' + r.id });
                    }
                }
                return out;
            }
            // 通常モード: 階層ツリーで描画 (親 → 子 → 孫 を DFS).
            return this._walkRoomTree(base);
        },
        get filteredPersonalRooms() {
            const q = this._sidebarQuery;
            const hidden = new Set(this.hiddenRoomIds || []);
            let base = this.emailRoomsPersonal || [];
            if (!this.showHiddenSidebarThreads) base = base.filter(r => !hidden.has(Number(r.id)));
            if (q) base = base.filter(r => (r.name || '').toLowerCase().includes(q));
            // 個人ルームは自分専用空間なので新着を上に出して気づきやすくする.
            // ピン留め (per-user) があれば最優先で先頭に並べる.
            const sorted = this._sortRoomsByNewMail(base);
            return [...sorted].sort((a, b) => {
                const ap = a.is_pinned_chat ? 1 : 0;
                const bp = b.is_pinned_chat ? 1 : 0;
                if (ap !== bp) return bp - ap;
                return 0;
            });
        },
        isRoomHidden(r) { return (this.hiddenRoomIds || []).includes(Number(r.id)); },

        // 親ルームが選択されている時、子ルームもサイドバーで青く表示する.
        // 親 A を開く = A + B + C の中身を見る、という UX を視覚化するため.
        get _selectedMailRoomDescendants() {
            const id = this.emailRoomFilterId;
            if (!id || id === 'all' || id === 'none') return new Set();
            const all = [...(this.emailRoomsShared || []), ...(this.emailRoomsPersonal || [])];
            const target = Number(id);
            const byParent = new Map();
            for (const r of all) {
                const pid = r.parent_room_id ? Number(r.parent_room_id) : null;
                if (pid !== null) {
                    if (!byParent.has(pid)) byParent.set(pid, []);
                    byParent.get(pid).push(Number(r.id));
                }
            }
            const out = new Set([target]);
            const q = [target];
            while (q.length) {
                const cur = q.shift();
                for (const c of (byParent.get(cur) || [])) {
                    if (!out.has(c)) { out.add(c); q.push(c); }
                }
            }
            return out;
        },
        isMailRoomInSelection(r) {
            if (!r) return false;
            return this._selectedMailRoomDescendants.has(Number(r.id));
        },
        get sidebarThreads() {
            // pinned 優先 (メール本体の絞り込みとは独立した sidebarThreadList を使用)
            let arr = (this.sidebarThreadList || []).slice();
            // ルームフィルタの値で挙動を分岐:
            //  - 'all': フィルタなし
            //  - 'none': どのルームにも属していないスレッドだけ
            //  - 数値 ID: そのルームに束ねられたスレッドだけ
            if (this.emailRoomFilterId === 'none') {
                const inAnyRoom = this._allBundledThreadIds;
                arr = arr.filter(t => !inAnyRoom.has(Number(t.id)));
            } else if (this.emailRoomFilterId && this.emailRoomFilterId !== 'all') {
                const bundleIds = new Set((this.emailRoomBundledThreads || []).map(b => Number(b.id)));
                arr = arr.filter(t => bundleIds.has(Number(t.id)));
            }
            arr.sort((a, b) => (b.is_pinned ? 1 : 0) - (a.is_pinned ? 1 : 0));
            return arr;
        },

        // 全ルームのバンドルスレッド ID 集合 (Set)。
        // 「ルーム未設定」フィルタでサイドバー側スレッドをクライアント絞り込みするために使う。
        // /api/chat-rooms のレスポンス (emailRoomsShared / emailRoomsPersonal の各 room.bundled_thread_ids)
        // から動的に算出する。 emailRoomsShared/Personal が変わる度に再計算 (computed property)。
        get _allBundledThreadIds() {
            const set = new Set();
            const collect = (rooms) => {
                for (const r of (rooms || [])) {
                    for (const tid of (r.bundled_thread_ids || [])) {
                        set.add(Number(tid));
                    }
                }
            };
            collect(this.emailRoomsShared);
            collect(this.emailRoomsPersonal);
            return set;
        },
        // 通常 (非表示でない) のスレッドのみ
        get visibleSidebarThreads() {
            const q = this._sidebarQuery;
            const hidden = new Set(this.hiddenSidebarThreadIds || []);
            return this.sidebarThreads.filter(t => !hidden.has(Number(t.id)))
                .filter(t => !q || (t.subject || '').toLowerCase().includes(q));
        },
        // サイドバーのスレッド行が「選択中」に見えるかの判定
        // - 開いているスレッド (selectedThreadId と一致)
        // - もしくは ルーム選択中で、そのルームに束ねられているスレッド
        isSidebarThreadActive(t) {
            if (String(this.selectedThreadId) === String(t.id)) return true;
            if (this.emailRoomFilterId && this.emailRoomFilterId !== 'all') {
                return (this.emailRoomBundledThreads || []).some(b => Number(b.id) === Number(t.id));
            }
            return false;
        },

        // 非表示中のスレッドのみ (ルーム選択中はそのルームのバンドル先のみ)
        get hiddenVisibleSidebarThreads() {
            const q = this._sidebarQuery;
            const hidden = new Set(this.hiddenSidebarThreadIds || []);
            return this.sidebarThreads.filter(t => hidden.has(Number(t.id)))
                .filter(t => !q || (t.subject || '').toLowerCase().includes(q));
        },

        // ===== AI要約モーダルの追加指示用スラッシュコマンド =====
        get filteredSummaryCollections() {
            const q = (this.summarySlash.query || '').toLowerCase();
            if (!q) return this.summaryCollections;
            const prefix = [], rest = [];
            this.summaryCollections.forEach(c => {
                const name = (c.name || '').toLowerCase();
                if (name.startsWith(q)) prefix.push(c);
                else if (name.includes(q)) rest.push(c);
            });
            return [...prefix, ...rest];
        },
        async loadSummaryCollections() {
            if (this.summaryCollectionsLoaded) return;
            this.summarySlash.loading = true;
            try {
                const res = await fetch('/api/knowledge/collections', { headers: { Accept: 'application/json' } });
                if (res.ok) { this.summaryCollections = (await res.json()).collections || []; }
            } catch (_) {}
            this.summaryCollectionsLoaded = true;
            this.summarySlash.loading = false;
        },
        onSummaryPromptInput(e) {
            const ta = e.target, pos = ta.selectionStart, value = ta.value;
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
            if (validIdx === -1) { this.summarySlash.open = false; return; }
            this.summarySlash.startPos = validIdx;
            this.summarySlash.query    = value.slice(validIdx + 1, pos);
            this.summarySlash.activeIdx = 0;
            this.summarySlash.open = true;
            this.loadSummaryCollections();
        },
        onSummaryPromptKeyDown(e) {
            if (!this.summarySlash.open) return;
            const list = this.filteredSummaryCollections;
            if (e.key === 'ArrowDown') { e.preventDefault(); this.summarySlash.activeIdx = Math.min(this.summarySlash.activeIdx + 1, Math.max(list.length - 1, 0)); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); this.summarySlash.activeIdx = Math.max(this.summarySlash.activeIdx - 1, 0); }
            else if (e.key === 'Enter' || e.key === 'Tab') {
                if (list[this.summarySlash.activeIdx]) { e.preventDefault(); this.insertSummaryCollection(list[this.summarySlash.activeIdx].name); }
            } else if (e.key === 'Escape') { e.preventDefault(); this.summarySlash.open = false; }
        },
        insertSummaryCollection(name) {
            const ta = this.$refs.summaryPromptArea;
            if (!ta) return;
            const value = ta.value, pos = ta.selectionStart;
            const before = value.slice(0, this.summarySlash.startPos);
            const after  = value.slice(pos);
            const insertion = '/' + name + ' ';
            this.summaryUserPrompt = before + insertion + after;
            this.$nextTick(() => {
                const newPos = before.length + insertion.length;
                try { ta.focus(); ta.setSelectionRange(newPos, newPos); } catch (_) {}
                this.syncSummaryHighlightScroll();
            });
            this.summarySlash.open = false;
        },
        renderSummaryHighlight(text) {
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
        syncSummaryHighlightScroll() {
            const ta = this.$refs.summaryPromptArea;
            const hi = this.$refs.summaryPromptHighlight;
            if (!ta || !hi) return;
            hi.scrollTop = ta.scrollTop;
            hi.scrollLeft = ta.scrollLeft;
        },

        // バックグラウンドで他ウィンドウ (compose-window 等) が走らせた AI タスクの完了を監視
        _aiBackgroundPoller: null,
        _aiLastSeenId: parseInt(localStorage.getItem('aiLastSeenTaskId') || '0', 10),
        startAiBackgroundPoll() {
            if (this._aiBackgroundPoller) return;
            const poll = async () => {
                try {
                    const res = await fetch(`/ai-tasks/recent?since_id=${this._aiLastSeenId}`, { headers: { Accept: 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    (data.tasks || []).forEach(t => {
                        // 既に同じ画面内 (この emails/index の loadThreadSummary など) で開いていたタスクは toast 済みなので、
                        // 「自分が直接ハンドルしていない reply_assist / compose_assist」を中心に通知する。
                        // ここでは reply_assist のみ toast を出す (要約は本画面で完結している)
                        if (t.task_type === 'reply_assist' && t.related_email_id) {
                            const url = `/emails/${t.related_email_id}/reply-window?ai_task=${t.id}`;
                            if (t.status === 'done') {
                                this.toast(
                                    `AI返信が完了しました${t.skill_used ? ' (' + t.skill_used + ')' : ''}`,
                                    'success',
                                    {
                                        actionLabel: '返信を開く',
                                        actionUrl: url,
                                    }
                                );
                                this._notifyDesktop('AI返信 完了', 'クリックして返信を開く', {
                                    force: true,
                                    tag: 'rice-ai-reply-' + t.id,
                                    onClick: () => window.open(url, '_blank'),
                                });
                            } else if (t.status === 'error') {
                                this.toast(
                                    'AI返信に失敗: ' + (t.error_message || '不明なエラー'),
                                    'error',
                                    { actionLabel: '返信を開く', actionUrl: url }
                                );
                            }
                        }
                        this._aiLastSeenId = Math.max(this._aiLastSeenId, t.id);
                    });
                    localStorage.setItem('aiLastSeenTaskId', String(this._aiLastSeenId));
                } catch (_) { /* 一時的なエラーは無視 */ }
            };
            // 5 秒ごとにポーリング (バックグラウンドの compose-window 完了検知用)
            this._aiBackgroundPoller = setInterval(poll, 5000);
            // 初回も即実行 (ページロード時に既に done のタスクがあれば即通知)
            poll();
        },

        async init() {
            // AI チャットパネル用の window helper を設置 (Alpine が部分的に死んでいても
            // 送信できるようにする最終防衛線). 内側は emailApp() の状態を直接いじる.
            try {
                const self = this;
                window.riceAiChatOnInput = function (value) {
                    self.aiChat.input = String(value ?? '');
                    self._riceAiChatHandleSlash();
                    self._riceAiChatRenderInputHighlight();
                };
                window.riceAiChatSend = function () {
                    try { self.sendAiChat(); }
                    catch (e) { console.error('[ai-chat] send failed', e); }
                };
                window.riceAiChatClose = function () {
                    try { self.closeAiChat(); } catch (_) {}
                    const p = document.getElementById('rice-ai-chat-panel');
                    const b = document.getElementById('rice-ai-chat-backdrop');
                    if (p) p.style.display = 'none';
                    if (b) b.style.display = 'none';
                };
                window.riceAiChatPickSkill = function (key) {
                    self.pickAiChatSkill(key);
                };
                window.riceAiChatClearSkill = function () {
                    self.aiChat.skillKey = null;
                };
                window.riceAiChatPickCollection = function (name) {
                    self.pickAiChatCollection(name);
                };
                window.riceAiChatSetProvider = function (p) {
                    self.setAiProvider(p);
                    self._renderAiChatModelPicker();
                };
                window.riceAiChatSetModel = function (m) {
                    self.aiModel = m;
                };
            } catch (e) { console.warn('[ai-chat] window helper setup failed', e); }

            // ===== 真っ白問題対策 =====
            // init() のどこかで例外が出ると後続が全部止まり、画面が「データ取得中で空」の状態で凍結する.
            // (リダイレクト直後にネットワークや認証の一瞬の揺らぎでこの状態に陥ると報告あり.)
            // 以下の方針で防御する:
            //   1) クエリ反映 / 状態補正は早期 try で囲む
            //   2) リサイズ&スクロール周りの DOM バインディングは **データ取得より先** に貼っておく
            //      → これでロード失敗時も空のリストはちゃんと描画される.
            //   3) Promise.all → Promise.allSettled に変更 (1 つの API 失敗で全部止まらないように)
            //   4) ?thread=N 自動オープン等は失敗しても画面表示自体には影響させない
            //   5) どこかで throw されてもページが反応するよう、最外殻に try/catch を巻く

            // クエリパラメータ `?room=<id>` で受け渡された場合は先に反映 (他画面からの往来用)
            try {
                const rawRoom = new URL(window.location.href).searchParams.get('room');
                const roomId = rawRoom ? parseInt(rawRoom, 10) : null;
                if (roomId && !Number.isNaN(roomId)) {
                    this.emailRoomFilterId = String(roomId);
                    try {
                        localStorage.setItem('emailRoomFilterId', String(roomId));
                        localStorage.setItem('currentRoomId', String(roomId));
                    } catch (_) {}
                } else {
                    // ローカルに保持しているフィルタを横断ナビ共通キーに反映
                    try {
                        const lf = localStorage.getItem('emailRoomFilterId');
                        if (lf && lf !== 'all') localStorage.setItem('currentRoomId', String(lf));
                    } catch (_) {}
                }
            } catch (_) {}

            // ★ ページロード時に既にルームが選ばれている場合も「全ステータス」表示を強制 ON。
            //    setRoomFilter() は init では呼ばれないので、ここで明示的に同じ補正を行う。
            //    こうしないと reload 後 'inbox' タブで completed スレッドが見えず、
            //    ショートカット (J/K/D/E/L) の対象が this.threads に入らない事故が再発する。
            try {
                if (this.emailRoomFilterId
                    && this.emailRoomFilterId !== 'all'
                    && this.emailRoomFilterId !== 'none') {
                    if (!this.allStatusMode) {
                        this.allStatusMode = true;
                        try { localStorage.setItem('allStatusMode', JSON.stringify(true)); } catch (_) {}
                    }
                }
            } catch (_) {}

            // ★ Promise.all より先にやる「壊れても良い」UI バインディング
            //    後続の await が転んでもこれらは確実に貼られていてほしいので、ここに前倒し.
            try {
                window.addEventListener('resize', () => { try { this.updateVirtualViewport(); } catch (_) {} });
                this.$nextTick(() => { try { this.updateVirtualViewport(); } catch (_) {} });
            } catch (_) {}

            // データロード: allSettled で「どれか 1 つ失敗しても他は通す」.
            // それぞれの loadXxx() 内には try/catch があるが、ネットワーク層 (offline / DNS) で
            // fetch そのものが reject するケースを想定して二重に守る.
            try {
                const results = await Promise.allSettled([
                    this.loadThreads(),
                    this.loadUsers(),
                    this.loadEmailRooms(),
                    this.loadSidebarThreads(),
                    this.loadEmailRoomBundledThreads(),
                    this.loadInboxScopeBadges()
                ]);
                results.forEach((r, i) => {
                    if (r.status === 'rejected') {
                        const name = ['loadThreads','loadUsers','loadEmailRooms','loadSidebarThreads','loadEmailRoomBundledThreads'][i];
                        console.error('[emails] init load step failed:', name, r.reason);
                    }
                });
            } catch (e) {
                // allSettled は基本 reject しないが、念のため.
                console.error('[emails] init Promise.allSettled threw:', e);
            }

            // ロード後に再計算 (リストが入ってからの実寸合わせ).
            try { this.$nextTick(() => { try { this.updateVirtualViewport(); } catch (_) {} }); } catch (_) {}

            // 以降の subsystem セットアップは各々 try/catch で囲み、
            // 1 つでも失敗したら他に波及しないようにする (真っ白問題対策).

            // 直近の取得ステータスを問い合わせ、前回ポーリングが失敗していたなら
            // 永続バナーを表示する。以降は 60 秒おきに自動更新。
            try {
                this.refreshFetchStatus();
                setInterval(() => { try { this.refreshFetchStatus(); } catch (_) {} }, 60 * 1000);
            } catch (e) { console.error('[emails] refreshFetchStatus setup failed:', e); }

            // 別ウィンドウ (compose-window) で走った AI タスクの完了をバックグラウンドポーリング
            try { this.startAiBackgroundPoll(); } catch (e) { console.error('[emails] startAiBackgroundPoll failed:', e); }

            // クエリパラメータ `?thread=<id>` で指定されたスレッドを自動表示
            // (チャット画面の「元メールを開く」や添付ファイル画面の件名リンク等から
            //  ?thread=N で飛んできた際に、該当スレッドを自動でロードしてワークスペースに表示)
            try {
                const url = new URL(window.location.href);
                const raw = url.searchParams.get('thread');
                const threadId = raw ? parseInt(raw, 10) : null;
                if (threadId && !Number.isNaN(threadId)) {
                    console.log('[emails] auto-open thread from URL param:', threadId);
                    // await しない: loadThread が遅くても/失敗しても init を止めないため.
                    this.loadThread(threadId).catch(e => console.error('[emails] auto-open thread failed:', e));
                }
            } catch (e) {
                console.error('[emails] ?thread= 自動オープン失敗:', e);
            }

            // 作成専用ウィンドウからの送信完了 / 下書き保存通知を購読
            try {
                window.addEventListener('message', (event) => {
                    try {
                        if (event.origin !== window.location.origin) return;
                        if (!event.data) return;
                        if (event.data.type === 'rice-mail-sent') {
                            this.fetchEmails(true);
                            this.toast('メールを送信しました', 'success');
                            if (this.selectedThreadId) {
                                this.loadThread(this.selectedThreadId);
                            }
                        } else if (event.data.type === 'rice-mail-draft-saved') {
                            // 下書き保存後はメール一覧を再読み込みして承認待ち件数等を更新
                            this.loadThreads();
                            this.toast('下書きを保存しました', 'success');
                        }
                    } catch (e) { console.error('[emails] message handler failed:', e); }
                });
            } catch (e) { console.error('[emails] message listener bind failed:', e); }

            try { this.setupPolling(); } catch (e) { console.error('[emails] setupPolling failed:', e); }

            // bfcache (戻る/進むキャッシュ) 復帰時に、ポーリング再開 + データ最新化.
            // bfcache 復帰では init() は走らないため明示的にハンドラを貼る.
            // 失敗時はサイレント (どちらにせよユーザは別操作でデータをリロード可能).
            try {
                window.addEventListener('pageshow', (ev) => {
                    if (ev && ev.persisted) {
                        // bfcache から復帰: 古いデータを表示している可能性が高いので強制再取得.
                        try { this.stopPolling(); } catch (_) {}
                        try {
                            this.loadThreads();
                            this.loadEmailRooms();
                            this.loadSidebarThreads();
                            this.loadEmailRoomBundledThreads();
                        } catch (_) {}
                        try { this.setupPolling(); } catch (_) {}
                    }
                });
            } catch (e) { console.error('[emails] pageshow handler bind failed:', e); }

            // キーボードショートカットを有効化 (Spark / Gmail / Notion ベース)。
            // テキスト入力やモーダル表示中は誤動作しないよう _onGlobalKeydown 側でガード。
            try {
                this._boundKeydown = this._onGlobalKeydown.bind(this);
                window.addEventListener('keydown', this._boundKeydown);
            } catch (e) { console.error('[emails] keydown listener bind failed:', e); }

            // グローバル navbar の「キーボード ?」アイコンからの open-shortcuts-help イベントを受信。
            // (Alpine の @open-shortcuts-help.window と二重にして、どちらか一方でも動くように)
            try {
                this._boundOpenShortcuts = () => { this.shortcutsModalOpen = true; };
                window.addEventListener('open-shortcuts-help', this._boundOpenShortcuts);
            } catch (_) {}

            // マージモーダル: バルク action メニューの「マージ」ボタンが
            // 二重保険で onclick から window イベントを撃ってくる。 Alpine の @click が
            // 何らかの理由で動かないケースでも、このリスナーで確実にモーダルを開く。
            try {
                this._boundOpenMergeModal = () => {
                    console.log('[merge] window event received, opening merge modal');
                    this.mergeSelected();
                };
                window.addEventListener('open-merge-modal', this._boundOpenMergeModal);
            } catch (_) {}
        },

        setupPolling() {
            this.startPolling();
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.fetchEmails(true);
                    this.startPolling();
                } else {
                    this.stopPolling();
                }
            });
        },
        startPolling() {
            this.stopPolling();
            this.pollIntervalId = setTimeout(async () => {
                await this.fetchEmails(true);
                this.startPolling();
            }, this.currentPollDelay);
        },
        // ====== キーボードショートカット (Spark / Gmail / Notion 風) ======
        //
        // 「すべて単一キー」を基本にしつつ、削除のような破壊的操作だけは
        //   Ctrl/Cmd + Del を要求する保守的設計。
        //
        // テキスト入力中 (input/textarea/contenteditable) や、何らかのモーダルが
        //   開いている時は ハンドリング自体をスキップ (本来のフォーカス挙動を阻害しない)。
        //
        // 入力検出: e.target.tagName を見て INPUT/TEXTAREA/SELECT、または
        //   isContentEditable をチェック。
        _isTextInputFocused(target) {
            if (!target) return false;
            const tag = (target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
            if (target.isContentEditable) return true;
            return false;
        },
        _anyModalOpen() {
            // emails/index.blade.php に実在するモーダル state だけを列挙。
            // (compose-window 側にあるが当画面には無い state は参照しない)
            return !!(
                this.mergeModalOpen
                || this.addToRoomOpen
                || this.mailCreateRoomOpen
                || this.mailEditRoomOpen
                || this.threadSummaryOpen
                || this.shortcutsModalOpen
                || this.knowledgeRegisterOpen
            );
        },
        // メインのメール検索ボックス (左ペイン上) にフォーカス。 `/` で発動。
        _focusMainSearch() {
            try {
                // ヘッダの searchQuery 入力 (id 指定が無いので class / placeholder で探す)
                const el = document.querySelector('input[x-model="searchQuery"]');
                if (el && typeof el.focus === 'function') {
                    el.focus();
                    el.select && el.select();
                }
            } catch (_) {}
        },

        _onGlobalKeydown(e) {
            // ===== 診断ログ (修飾キー単体や Tab などは無視してログを荒らさない) =====
            // 何故ショートカットが効かないかを Console で切り分けるためのログ.
            // 動作確認後にこのブロックは削除して構わない.
            if (!['Shift','Control','Alt','Meta','Tab','CapsLock','ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key)) {
                const inInput = this._isTextInputFocused(e.target);
                const inModal = this._anyModalOpen();
                console.log('[shortcut] key=' + e.key
                    + ' tag=' + (e.target?.tagName || '?')
                    + ' inInput=' + inInput
                    + ' inModal=' + inModal
                    + (inModal ? ' (modals:'
                        + (this.mergeModalOpen ? ' merge' : '')
                        + (this.addToRoomOpen ? ' addToRoom' : '')
                        + (this.mailCreateRoomOpen ? ' mailCreateRoom' : '')
                        + (this.mailEditRoomOpen ? ' mailEditRoom' : '')
                        + (this.threadSummaryOpen ? ' threadSummary' : '')
                        + (this.shortcutsModalOpen ? ' shortcuts' : '')
                        + (this.knowledgeRegisterOpen ? ' knowledgeRegister' : '')
                        + ')'
                        : '')
                );
            }

            // テキスト入力中 / モーダル表示中はショートカット無効
            if (this._isTextInputFocused(e.target)) {
                // ただし Esc は入力欄から抜けるためにも有効にしておく (任意)
                if (e.key === 'Escape') {
                    try { e.target.blur(); } catch (_) {}
                }
                return;
            }
            if (this._anyModalOpen()) {
                // モーダル中の Esc は閉じる動作に任せる (それぞれのモーダルが @keydown.escape で処理)
                return;
            }

            const ctrlOrCmd = e.ctrlKey || e.metaKey;

            // ====== ショートカット対象 thread の解決 ======
            // 優先順:
            //   1) 選択モード中で複数選択あり → そのまま (一括処理)
            //   2) マウスオーバー中の行 (hoveredThreadId) → ホバー対象
            //   3) スレッド詳細で開いている行 (selectedThreadId)  → 開いている対象
            //   どれにも該当しなければ null (アクション無視)
            const targetId = (this.selectionMode && this.selectedThreadIds.length > 0)
                ? null   // 選択モード優先: 各アクションで selectedThreadIds[] を参照
                : (this.hoveredThreadId || this.selectedThreadId || null);
            // 通常は this.threads から検索。
            // ルームフィルタ中はステータスタブで弾かれて this.threads に居ない
            // ケースがあるため、bundle chip 経由 (emailRoomBundledThreads) にも fallback する。
            // これで「ルームを設定するとショートカットが効かない」事故を回避。
            let targetThread = null;
            if (targetId) {
                targetThread = (this.threads || []).find(t => t.id === targetId)
                    || (this.emailRoomBundledThreads || []).find(t => t.id === targetId)
                    || null;
            }

            // Ctrl+Z: 直前のステータス変更 / ルーム追加・解除 / マージ・解除を取り消す.
            // Shift+Ctrl+Z (redo) は未実装. テキスト入力中はこの関数が早期 return しているので
            //   textarea のネイティブ undo が普通に効く (干渉なし).
            if (ctrlOrCmd && (e.key === 'z' || e.key === 'Z') && !e.shiftKey) {
                e.preventDefault();
                this.undoLastAction();
                return;
            }

            // Ctrl+A: 選択モード中なら「表示中スレッドを全選択 / 全解除」のトグル.
            // 選択モードでなければブラウザ既定 (= ページ全文選択) を残す.
            if (ctrlOrCmd && (e.key === 'a' || e.key === 'A')) {
                if (this.selectionMode || (this.threads && this.threads.length > 0)) {
                    e.preventDefault();
                    if (!this.selectionMode) this.selectionMode = true;
                    this.toggleSelectAllVisible();
                    return;
                }
            }

            // Ctrl+Del または Ctrl+Backspace で削除 (確認ダイアログあり)
            if (ctrlOrCmd && (e.key === 'Delete' || e.key === 'Backspace')) {
                if (this.selectionMode && this.selectedThreadIds.length > 0) {
                    e.preventDefault();
                    this.batchDeleteSelected();
                } else if (targetThread) {
                    e.preventDefault();
                    this.deleteThreadById(targetThread.id, targetThread.subject);
                }
                return;
            }

            // ===== ここから単一キーショートカット =====
            // ★ Ctrl / Cmd / Alt が押されている場合はブラウザ既定 (リロード, タブ操作等) に譲る.
            //    例: Ctrl+Shift+R (ハードリロード) を 'R' (返信) で奪わない.
            //    Ctrl+A と Ctrl+Del の正規な拡張はこの関数の上で既に処理済みなのでここはスキップで OK.
            if (ctrlOrCmd || e.altKey) {
                return;
            }

            // ナビゲーション用の有効スレッド一覧.
            // ルーム絞り込み中は this.threads がステータスで弾かれて空になる
            // ケースがあるため、その場合は束ねたスレッド (emailRoomBundledThreads) を使う.
            const navList = (this.threads && this.threads.length > 0)
                ? this.threads
                : (this.emailRoomFilterId && this.emailRoomFilterId !== 'all' && this.emailRoomFilterId !== 'none'
                    ? (this.emailRoomBundledThreads || [])
                    : []);

            // 単一キーショートカット
            switch (e.key) {
                case 'j': // 次のスレッド (Gmail / Vim 流)
                    e.preventDefault();
                    if (!this.selectedThreadId && navList.length > 0) {
                        // 未選択なら一番上を開く (ナビ開始)
                        this.loadThread(navList[0].id);
                        this.scrollThreadIntoView(navList[0].id);
                    } else if (this.selectedThreadId) {
                        // navList から次を探す。 this.threads にあるならそのまま、
                        // bundle fallback の時は navList を使う。
                        const idx = navList.findIndex(t => t.id === this.selectedThreadId);
                        if (idx !== -1 && idx < navList.length - 1) {
                            this.loadThread(navList[idx + 1].id);
                            this.scrollThreadIntoView(navList[idx + 1].id);
                        } else {
                            this.goToNextThread();
                        }
                    }
                    break;
                case 'k': // 前のスレッド
                    e.preventDefault();
                    if (!this.selectedThreadId && navList.length > 0) {
                        // 未選択なら一番下を開く (逆方向のナビ開始)
                        const last = navList[navList.length - 1];
                        this.loadThread(last.id);
                        this.scrollThreadIntoView(last.id);
                    } else if (this.selectedThreadId) {
                        const idx = navList.findIndex(t => t.id === this.selectedThreadId);
                        if (idx > 0) {
                            this.loadThread(navList[idx - 1].id);
                            this.scrollThreadIntoView(navList[idx - 1].id);
                        } else {
                            this.goToPrevThread();
                        }
                    }
                    break;
                case 'Escape':
                    // 優先順:
                    //   1) スレッド内検索が入っていればそれを解除
                    //   2) 選択モードなら解除
                    //   3) ワークスペース (スレッド詳細) を閉じる
                    if (this.threadInnerSearchQuery) {
                        e.preventDefault();
                        this.threadInnerSearchQuery = '';
                    } else if (this.selectionMode) {
                        e.preventDefault();
                        this.cancelSelection();
                    } else if (this.selectedThreadId) {
                        e.preventDefault();
                        this.closeWorkspace();
                    }
                    break;
                case 'r': // 返信 (R は表示中スレッドにのみ作用. ホバー対象だと threadEmails が読まれていないため)
                    if (this.selectedThreadId && this.threadEmails.length) {
                        e.preventDefault();
                        this.openReplyForEmail(this.threadEmails[0], false);
                    }
                    break;
                case 'R': // Shift+R: 全員返信 (同上)
                    if (this.selectedThreadId && this.threadEmails.length) {
                        e.preventDefault();
                        this.openReplyForEmail(this.threadEmails[0], true);
                    }
                    break;
                case 'e': // 完了 (ホバー or 開いている対象 / 選択モード時は一括)
                    if (this.selectionMode && this.selectedThreadIds.length > 0) {
                        e.preventDefault();
                        this.updateSelectedStatus('completed');
                    } else if (targetThread) {
                        e.preventDefault();
                        this.quickUpdateStatus(targetThread, 'completed');
                    }
                    break;
                case 'h': // 保留
                    if (this.selectionMode && this.selectedThreadIds.length > 0) {
                        e.preventDefault();
                        this.updateSelectedStatus('hold');
                    } else if (targetThread) {
                        e.preventDefault();
                        this.quickUpdateStatus(targetThread, 'hold');
                    }
                    break;
                case 'i': // 受信箱に戻す
                    if (this.selectionMode && this.selectedThreadIds.length > 0) {
                        e.preventDefault();
                        this.updateSelectedStatus('inbox');
                    } else if (targetThread) {
                        e.preventDefault();
                        this.quickUpdateStatus(targetThread, 'inbox');
                    }
                    break;
                case 'p': // ピン留めトグル
                    if (targetThread) {
                        e.preventDefault();
                        this.togglePin(targetThread.id);
                    }
                    break;
                case 's': // 迷惑メールに振り分け
                    if (this.selectionMode && this.selectedThreadIds.length > 0) {
                        e.preventDefault();
                        this.bulkMarkSpam();
                    } else if (targetThread && targetThread.status !== 'spam') {
                        e.preventDefault();
                        this.quickMarkSpam(targetThread);
                    }
                    break;
                case 'l': case 'L': // ルームに追加
                    if (this.selectionMode && this.selectedThreadIds.length > 0) {
                        e.preventDefault();
                        this.openAddToRoomModal(this.selectedThreadIds);
                    } else if (targetThread) {
                        e.preventDefault();
                        this.openAddToRoomModal(targetThread.id);
                    }
                    break;
                case 'c': // 新規作成
                    e.preventDefault();
                    this.openCompose();
                    break;
                case 'v': // 複数選択モードトグル
                    e.preventDefault();
                    this.toggleSelectionMode();
                    break;
                case '/': // メインの検索フォーカス
                    e.preventDefault();
                    this._focusMainSearch();
                    break;
                case '?': // ヘルプ (Alpine 経由とバニラ DOM の両方で開く)
                    e.preventDefault();
                    this.shortcutsModalOpen = true;
                    // バニラ版もトリガ. layouts/app.blade.php で window に登録された関数.
                    // Alpine モーダルがビルド/CSS の問題で出ない時の保険.
                    if (typeof window.riceShowKeyboardShortcuts === 'function') {
                        window.riceShowKeyboardShortcuts();
                    }
                    break;
                case 'Delete': // Del 単体でも削除 (Ctrl+Del と同等)
                case 'd':      // 'd' / 'D' でも削除. 選択モード中なら一括削除
                case 'D':
                    {
                        e.preventDefault();
                        // 選択モード中で複数選択されているなら一括削除を優先
                        if (this.selectionMode && Array.isArray(this.selectedThreadIds) && this.selectedThreadIds.length > 0) {
                            this.batchDeleteSelected();
                        } else if (targetThread) {
                            // ホバー or 開いている対象を削除. deleteThreadById は id + subject を受ける.
                            this.deleteThreadById(targetThread.id, targetThread.subject);
                        }
                    }
                    break;
                case '1': case '2': case '3': case '4': case '5': case '6':
                    // ステータスタブの直接切替 (Spark の "Smart Inbox" 風)
                    {
                        const tabs = ['inbox', 'hold', 'completed', 'no_action', 'pending', 'spam'];
                        const idx = parseInt(e.key, 10) - 1;
                        if (tabs[idx] !== undefined) {
                            e.preventDefault();
                            this.setLeftTab(tabs[idx]);
                        }
                    }
                    break;
                case 'g': // メール同期 (sync). 'g' = get
                    e.preventDefault();
                    this.fetchEmails(false);
                    break;

                // ===== スレッド内のメール個別ナビ =====
                // J/K がスレッド間の移動なので、スレッド内部の個別メール (1 スレッドに複数 reply
                // がある場合) は別キーで移動. キーボードで [ ] (角括弧) を使う.
                //   ]  → 次のメール (時系列で新しい方向 = 配列の前のインデックスに進む)
                //        ※ threadEmails は received_at desc で並んでいる
                //   [  → 前のメール
                //   1-9 (Shift と組み合わさず、 ステータスタブ切替 1-6 と被るが
                //         スレッド表示中で「メール件数 ≥ 7」の時のみ Shift+数字でジャンプ)
                case ']':
                    if (this.selectedThreadId && (this.threadEmails || []).length > 1) {
                        e.preventDefault();
                        this._navIntraThreadEmail(+1);
                    }
                    break;
                case '[':
                    if (this.selectedThreadId && (this.threadEmails || []).length > 1) {
                        e.preventDefault();
                        this._navIntraThreadEmail(-1);
                    }
                    break;
            }
        },

        // スレッド内部のメール間を移動 (現在の focusEmailId をたどって ± 1).
        // 該当メールカードを #email-card-<id> 相当の DOM (data-email-id) にスクロールする.
        _navIntraThreadEmail(dir) {
            const emails = this.threadEmails || [];
            if (emails.length === 0) return;
            const ids = emails.map(e => e.id);
            let idx = -1;
            if (this._focusedEmailId != null) idx = ids.indexOf(this._focusedEmailId);
            if (idx === -1) {
                // 未選択時の起点: dir>0 なら先頭 (最新) / dir<0 なら末尾 (最古)
                idx = dir > 0 ? 0 : (emails.length - 1);
            } else {
                idx = Math.max(0, Math.min(emails.length - 1, idx + dir));
            }
            this._focusedEmailId = ids[idx];
            this.$nextTick(() => {
                const el = document.querySelector('[data-email-id="' + this._focusedEmailId + '"]');
                if (!el) return;
                // ★ スクロール位置の合わせ方:
                //   block:'center' (旧) は本文の中央が画面中央に来るため、長文メールでは件名/差出人ヘッダが
                //   画面外に追いやられる. ユーザ要望でメールカードの「ヘッダ」を画面上部に揃える.
                //   - scroll-container = x-ref="threadEmailsPane" を直接スクロール (block:'start' 相当)
                //   - ペインの padding-top (40px / p-10) を考慮して 16px だけ余白を残し、
                //     カードの上端 (= 件名/差出人) を視認できる位置に持ち上げる.
                //   - スクロール container がもし見つからない場合は scrollIntoView({block:'start'}) にフォールバック.
                const pane = this.$refs.threadEmailsPane || el.closest('.overflow-y-auto');
                if (pane) {
                    // pane の top に対する el の相対 top を算出. getBoundingClientRect で
                    // 動的サイズ変動 (展開状態) にも追従する.
                    const targetTop = pane.scrollTop + (el.getBoundingClientRect().top - pane.getBoundingClientRect().top);
                    // 16px の余白でメールカード上部が完全に見える位置に止める.
                    pane.scrollTo({ top: Math.max(0, targetTop - 16), behavior: 'smooth' });
                } else {
                    el.scrollIntoView({ block: 'start', behavior: 'smooth' });
                }
                // 一時的にハイライト (CSS ring) を付ける
                el.classList.add('ring-2', 'ring-blue-300');
                setTimeout(() => el.classList.remove('ring-2', 'ring-blue-300'), 1200);
            });
        },

        stopPolling() {
            if (this.pollIntervalId) {
                clearTimeout(this.pollIntervalId);
                this.pollIntervalId = null;
            }
        },
        async loadUsers() {
            try {
                const res = await fetch('/users', { headers: { 'Accept': 'application/json' } });
                if (res.status === 401) { window.location.href = '/login'; return; }
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                this.users = await res.json();
            } catch(e) { console.error('ユーザーリストの取得に失敗', e); }
        },

        async loadThreads(isBackground = false) {
            if (!isBackground) this.threadsLoading = true;
            const params = new URLSearchParams({
                all_status: this.allStatusMode ? '1' : '0',
                is_pinned: this.pinnedOnlyMode ? '1' : '0',
                status: this.leftTab,
                sort_order: this.sortOrder,
                scope: this.inboxScope || 'shared',
            });
            if (this.assigneeFilterId !== 'all') params.append('assigned_user_id', this.assigneeFilterId);
            if (this.emailRoomFilterId && this.emailRoomFilterId !== 'all') params.append('chat_room_id', this.emailRoomFilterId);
            if (this.searchQuery && this.searchQuery.trim()) params.append('q', this.searchQuery.trim());
            // 個人モードでアカウントが選択されていれば絞り込み
            if ((this.inboxScope || 'shared') === 'personal' && this.selectedPersonalAccountId) {
                params.append('mail_account_id', String(this.selectedPersonalAccountId));
            }

            try {
                const res = await fetch('/emails/search?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                if (res.status === 401) { window.location.href = '/login'; return; }
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const json = await res.json();
                this.threads = Array.isArray(json) ? json : [];
                this.handleScroll();
                // ステータスタブの表示制御用に件数も並行取得 (フィルタは同条件).
                this.loadStatusCounts();
            } catch(e) {
                console.error('スレッド一覧の取得に失敗', e);
                if (!isBackground) this.toast('一覧の取得に失敗しました', 'error');
            } finally {
                if (!isBackground) this.threadsLoading = false;
            }
        },
        // ステータス別件数を非同期取得. /emails/status-counts.
        // タブ表示の出し分けに使う. 失敗してもサイレント (現在の値を維持).
        async loadStatusCounts() {
            const params = new URLSearchParams({
                is_pinned: this.pinnedOnlyMode ? '1' : '0',
                scope: this.inboxScope || 'shared',
            });
            if (this.assigneeFilterId !== 'all') params.append('assigned_user_id', this.assigneeFilterId);
            if (this.emailRoomFilterId && this.emailRoomFilterId !== 'all') params.append('chat_room_id', this.emailRoomFilterId);
            if (this.searchQuery && this.searchQuery.trim()) params.append('q', this.searchQuery.trim());
            if ((this.inboxScope || 'shared') === 'personal' && this.selectedPersonalAccountId) {
                params.append('mail_account_id', String(this.selectedPersonalAccountId));
            }
            try {
                const res = await fetch('/emails/status-counts?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const json = await res.json();
                this.statusCounts = {
                    inbox:     Number(json.inbox     || 0),
                    hold:      Number(json.hold      || 0),
                    completed: Number(json.completed || 0),
                    no_action: Number(json.no_action || 0),
                    pending:   Number(json.pending   || 0),
                    spam:      Number(json.spam      || 0),
                };
            } catch (_) {}
        },

        toggleAllStatus() {
            this.allStatusMode = !this.allStatusMode;
            try { localStorage.setItem('allStatusMode', JSON.stringify(this.allStatusMode)); } catch(_) {}
            this.loadThreads();
        },
        togglePinnedOnly() {
            this.pinnedOnlyMode = !this.pinnedOnlyMode;
            this.loadThreads();
        },
        setAssigneeFilter(id) { this.assigneeFilterId = id; localStorage.setItem('assigneeFilterId', id); this.loadThreads(); },
        setInboxScope(scope) {
            if (scope !== 'shared' && scope !== 'personal') return;
            if (this.inboxScope === scope) return;
            this.inboxScope = scope;
            localStorage.setItem('inboxScope', scope);
            // 開いていたスレッドが新しい scope では見えなくなる (or 権限的に NG な)
            // 可能性が高いので、必ずワークスペースを閉じてリセットする。
            // closeWorkspace は selectedThread / selectedThreadId / 返信ドラフト等もクリアする。
            if (this.selectedThread || this.selectedThreadId) {
                if (typeof this.closeWorkspace === 'function') {
                    this.closeWorkspace();
                } else {
                    this.selectedThread = null;
                    this.selectedThreadId = null;
                }
            }
            this.loadThreads();
            // ルーム件数バッジも scope 連動で再集計したいので一緒にリロード
            this.loadEmailRooms();
            // サイドバーのスレッド一覧も scope で再フェッチ
            this.loadSidebarThreads();
            // バッジ件数も同時に最新化
            this.loadInboxScopeBadges();
        },
        setRoomFilter(id) {
            // 同じルームを再度クリックしたら "すべて" に切り替えるトグル動作
            if (id !== 'all' && String(this.emailRoomFilterId) === String(id)) {
                id = 'all';
            }
            this.emailRoomFilterId = id;
            try { localStorage.setItem('emailRoomFilterId', String(id)); } catch (_) {}
            // 横断ナビ用に共通キーへ同期 ('all' 時はクリア)
            try {
                if (id && id !== 'all') localStorage.setItem('currentRoomId', String(id));
                else localStorage.removeItem('currentRoomId');
            } catch (_) {}
            // ★ 特定ルームに絞り込んだら「全ステータス」表示を自動 ON にする。
            // 既定の `inbox` タブだけだと、ルーム内の completed / no_action のスレッドが
            // メインリストから消えてしまい、サイドバーに見えているのに
            // J/K/D/E/L 等のショートカットの対象 (this.threads) に入らず
            // 「ルームを設定するとショートカットが効かない」状態になっていた。
            // ステータスタブを明示クリックすれば setLeftTab() 側で allStatusMode は OFF に戻る。
            if (id && id !== 'all' && id !== 'none') {
                if (!this.allStatusMode) {
                    this.allStatusMode = true;
                    try { localStorage.setItem('allStatusMode', JSON.stringify(true)); } catch (_) {}
                }
            }
            this.loadThreads();
            // ルーム選択時はそのルームに束ねられたスレッドも取得 (チップ表示用)
            this.loadEmailRoomBundledThreads();
        },
        async loadEmailRoomBundledThreads() {
            // 'all' (フィルタなし) と 'none' (ルーム未設定フィルタ) は対象ルームが無いので空に。
            if (!this.emailRoomFilterId
                || this.emailRoomFilterId === 'all'
                || this.emailRoomFilterId === 'none') {
                this.emailRoomBundledThreads = [];
                return;
            }
            try {
                const r = await fetch(`/api/chat-rooms/${this.emailRoomFilterId}/threads`, { headers: { Accept:'application/json' } });
                if (!r.ok) { this.emailRoomBundledThreads = []; return; }
                const d = await r.json();
                this.emailRoomBundledThreads = d.threads || [];
            } catch (_) { this.emailRoomBundledThreads = []; }
        },
        async detachEmailRoomThread(threadId) {
            if (!this.emailRoomFilterId || this.emailRoomFilterId === 'all') return;
            // 選択中ルームの情報を取得 (共有 / 個人 判定 + 名前)
            const allRooms = [...(this.emailRoomsShared || []), ...(this.emailRoomsPersonal || [])];
            const room = allRooms.find(r => String(r.id) === String(this.emailRoomFilterId));
            const isShared = room && !room.is_private;
            const bt = (this.emailRoomBundledThreads || []).find(b => Number(b.id) === Number(threadId));
            const subject = bt?.subject || '(件名なし)';
            const msg = isShared
                ? '⚠ 共有ルームからスレッドを外します\n\n'
                + 'ルーム名: # ' + (room?.name || '') + '\n'
                + 'スレッド: ' + subject + '\n\n'
                + 'このルームに参加している他のメンバー全員からも、このスレッドが見えなくなります。\n'
                + '本当に外しますか?'
                : '個人ルームからスレッドを外します。\n\nスレッド: ' + subject + '\n\nよろしいですか?';
            if (!confirm(msg)) return;
            // ★ Undo 用: ルーム ID と CSRF を closure で保持
            const undoRoomId = this.emailRoomFilterId;
            const undoRoomName = room?.name || '';
            const csrf = this.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;
            try {
                const r = await fetch(`/api/chat-rooms/${this.emailRoomFilterId}/threads/${threadId}`, {
                    method: 'DELETE',
                    headers: { 'Accept':'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                });
                if (!r.ok) { this.toast('紐付け解除に失敗しました', 'error'); return; }
                this.emailRoomBundledThreads = this.emailRoomBundledThreads.filter(t => t.id !== threadId);
                this.toast('紐付けを解除しました', 'success');
                // メール一覧は紐付け済みスレッドのみ表示しているため再取得
                this.loadThreads();
                // 件数バッジをリアルタイム更新 (紐付け解除でルームのカウントが減る)
                this.loadEmailRooms();

                // Undo: 同じスレッドを同じルームに再リンク
                this._pushUndoAction(
                    `ルーム「${undoRoomName}」から外す`,
                    async () => {
                        const r2 = await fetch(`/api/chat-rooms/${undoRoomId}/threads`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ thread_id: threadId }),
                        });
                        if (!r2.ok) throw new Error('再リンクできませんでした');
                        this.loadEmailRoomBundledThreads();
                        this.loadEmailRooms();
                        this.loadThreads();
                    }
                );
            } catch (_) { this.toast('通信エラー', 'error'); }
        },
        toggleMailRoomsSidebar() {
            this.mailRoomsCollapsed = !this.mailRoomsCollapsed;
            try { localStorage.setItem('mailRoomsCollapsed', JSON.stringify(this.mailRoomsCollapsed)); } catch (_) {}
        },
        toggleSidebarThreadsCollapsed() {
            this.sidebarThreadsCollapsed = !this.sidebarThreadsCollapsed;
            try { localStorage.setItem('emailSidebarThreadsCollapsed', JSON.stringify(this.sidebarThreadsCollapsed)); } catch (_) {}
        },
        // ===== 束ねたスレッドのバンドル帯: 展開トグル =====
        toggleBundleBandExpanded() {
            this.bundleBandExpanded = !this.bundleBandExpanded;
            try { localStorage.setItem('emailBundleBandExpanded', JSON.stringify(this.bundleBandExpanded)); } catch (_) {}
        },
        // 表示するチップ配列。折りたたみ時は「何も表示しない (件数だけ)」、展開時は全件。
        // ユーザ要望: 折りたたみ時は先頭何件かもチラ見せせず、ラベル＋件数だけにする。
        get visibleBundleChips() {
            const arr = this.emailRoomBundledThreads || [];
            return this.bundleBandExpanded ? arr : [];
        },
        // 折りたたみ時に隠している件数。0 ならボタンを出さない (= 帯自体が出ない)。
        get hiddenBundleChipsCount() {
            const total = (this.emailRoomBundledThreads || []).length;
            return this.bundleBandExpanded ? 0 : total;
        },
        toggleSharedRoomsCollapsed() {
            this.sharedRoomsCollapsed = !this.sharedRoomsCollapsed;
            try { localStorage.setItem('emailSharedRoomsCollapsed', JSON.stringify(this.sharedRoomsCollapsed)); } catch (_) {}
        },
        togglePersonalRoomsCollapsed() {
            this.personalRoomsCollapsed = !this.personalRoomsCollapsed;
            try { localStorage.setItem('emailPersonalRoomsCollapsed', JSON.stringify(this.personalRoomsCollapsed)); } catch (_) {}
        },
        toggleShowHiddenSidebarThreads() {
            this.showHiddenSidebarThreads = !this.showHiddenSidebarThreads;
            try { localStorage.setItem('emailShowHiddenSidebarThreads', JSON.stringify(this.showHiddenSidebarThreads)); } catch (_) {}
        },
        // サイドバー専用のスレッド一覧をサーバから取得 (チャット/添付と同じエンドポイント)
        // メール本体の status / 担当者などのフィルタとは無関係に「全スレッド」を引く
        // 共有メール/個人メール タブ横の inbox 件数バッジを更新する。
        // 両方のスコープで /emails/status-counts を叩いて inbox 件数を取得する.
        async loadInboxScopeBadges() {
            try {
                const [shared, personal] = await Promise.all([
                    fetch('/emails/status-counts?scope=shared',   { headers: { Accept:'application/json' } }),
                    fetch('/emails/status-counts?scope=personal', { headers: { Accept:'application/json' } }),
                ]);
                if (shared.ok) {
                    const d = await shared.json();
                    this.sharedInboxCount = Number(d.inbox || 0);
                }
                if (personal.ok) {
                    const d = await personal.json();
                    this.personalInboxCount = Number(d.inbox || 0);
                }
            } catch (_) {}
        },
        async loadSidebarThreads() {
            try {
                // show_hidden=1 で非表示スレッドも含めて取得 (クライアント側でフィルタ)
                // scope を渡してサイドバーも個人/共有で切り替える
                const scope = this.inboxScope || 'shared';
                let url = '/chats/threads?show_hidden=1&scope=' + encodeURIComponent(scope);
                if (scope === 'personal' && this.selectedPersonalAccountId) {
                    url += '&mail_account_id=' + this.selectedPersonalAccountId;
                }
                const r = await fetch(url, { headers: { Accept:'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                this.sidebarThreadList = (d.threads || []).map(t => ({
                    id: t.id,
                    subject: t.subject,
                    is_pinned: !!t.is_pinned,
                }));
                this.hiddenSidebarThreadIds = (d.hidden_threads || []).map(Number);
            } catch (_) {}
        },
        // サイドバー専用: スレッドを「非表示」にする (メール一覧の中央カラムには影響しない)
        async toggleHideThread(id) {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const r = await fetch('/api/chats/hide', {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf },
                    body: JSON.stringify({ type: 'thread', id }),
                });
                if (!r.ok) { this.toast('非表示にできませんでした', 'error'); return; }
                // サイドバー側だけ非表示扱いに ( this.threads = メイン一覧 はそのまま)
                const nid = Number(id);
                if (!this.hiddenSidebarThreadIds.includes(nid)) this.hiddenSidebarThreadIds.push(nid);
                this.toast('スレッドを非表示にしました (新着で再表示されます)', 'success');
            } catch (_) { this.toast('通信エラー', 'error'); }
        },
        // 非表示を解除して再表示
        async unhideSidebarThread(id) {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const r = await fetch('/api/chats/unhide', {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf },
                    body: JSON.stringify({ type: 'thread', id }),
                });
                if (!r.ok) { this.toast('再表示に失敗しました', 'error'); return; }
                this.hiddenSidebarThreadIds = this.hiddenSidebarThreadIds.filter(x => Number(x) !== Number(id));
                this.toast('スレッドを再表示しました', 'success');
            } catch (_) { this.toast('通信エラー', 'error'); }
        },
        startResizeMailRooms(e) {
            const startX = e.clientX, startW = this.mailRoomsWidth;
            this.mailRoomsResizing = true;
            document.body.classList.add('mail-rooms-resizing');
            const onMove = (me) => {
                const delta = me.clientX - startX;
                this.mailRoomsWidth = Math.max(120, Math.min(500, startW + delta));
            };
            const onUp = () => {
                this.mailRoomsResizing = false;
                document.body.classList.remove('mail-rooms-resizing');
                try { localStorage.setItem('mailRoomsWidth', String(this.mailRoomsWidth)); } catch (_) {}
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
        openMailCreateRoom(isPrivate = false) {
            this.mailNewRoomName = '';
            this.mailNewRoomIsPrivate = !!isPrivate;
            // 親ルーム選択リセット
            this.mailNewRoomParentId = null;
            this.mailNewRoomParentLabel = '';
            this.mailNewRoomParentSearch = '';
            this.mailCreateRoomOpen = true;
        },
        // 親ルーム選択ドロップダウン用の検索結果 (新規作成モーダル).
        // 入力 (mailNewRoomParentLabel) で部分一致したルームを最大 30 件返す.
        // 共有 → 個人 の順でソートし、個人ルームは作成者本人のもののみ.
        // 階層制限: 新規ルームは parent.depth + 1 <= MAX_DEPTH の親しか選べない.
        get mailParentSearchResults() {
            const all = [...(this.emailRoomsShared || []), ...(this.emailRoomsPersonal || [])];
            const q = (this.mailNewRoomParentLabel || '').toLowerCase().trim();
            const maxParentDepth = this.ROOM_MAX_DEPTH - 1; // 子を作れる最深部
            const filtered = all
                // 共有/個人 のミックスは自由 (要望どおり)
                .filter(r => (Number(r.depth || 1) <= maxParentDepth));
            const matched = q
                ? filtered.filter(r => (r.name || '').toLowerCase().includes(q))
                : filtered;
            return matched
                .sort((a, b) => {
                    const ap = a.is_private ? 1 : 0;
                    const bp = b.is_private ? 1 : 0;
                    if (ap !== bp) return ap - bp;
                    return (a.name || '').localeCompare(b.name || '', 'ja');
                })
                .slice(0, 30);
        },
        async submitMailCreateRoom() {
            const name = (this.mailNewRoomName || '').trim();
            if (!name || this.mailCreatingRoom) return;
            this.mailCreatingRoom = true;
            try {
                const r = await fetch('/api/chat-rooms', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        name,
                        is_private: this.mailNewRoomIsPrivate,
                        parent_room_id: this.mailNewRoomParentId || null,
                    }),
                });
                if (!r.ok) { alert('作成失敗'); return; }
                const data = await r.json();
                this.mailCreateRoomOpen = false;
                await this.loadEmailRooms();
                if (data?.room?.id) this.setRoomFilter(String(data.room.id));
            } catch (e) {
                alert('通信エラー: ' + e.message);
            } finally { this.mailCreatingRoom = false; }
        },

        // ====== ルーム作成時の重複サジェスト ======
        // 名前と部分一致する既存ルームを最大 8 件返す。 2 文字未満では空 (1 文字だと全件マッチで無意味)。
        // 共有 → 個人 の順で並べる。
        get mailSimilarRoomsForNewName() {
            const q = (this.mailNewRoomName || '').trim().toLowerCase();
            if (q.length < 2) return [];
            const allRooms = [...(this.emailRoomsShared || []), ...(this.emailRoomsPersonal || [])];
            const matches = allRooms.filter(r => (r.name || '').toLowerCase().includes(q));
            return matches.sort((a, b) => {
                const ap = a.is_private ? 1 : 0;
                const bp = b.is_private ? 1 : 0;
                if (ap !== bp) return ap - bp;
                return (a.name || '').localeCompare(b.name || '');
            }).slice(0, 8);
        },
        // サジェストクリック: モーダルを閉じて該当ルームへフィルタを切替
        selectExistingMailRoomFromCreate(room) {
            if (!room) return;
            this.mailCreateRoomOpen = false;
            this.mailNewRoomName = '';
            this.setRoomFilter(String(room.id));
        },

        // ====== ルーム編集 ======
        // 編集ボタン表示の権限判定:
        //   - 共有ルーム → 閲覧者なら誰でも編集 OK
        //   - 個人ルーム → 作成者のみ
        canEditMailRoom(room) {
            if (!room) return false;
            if (!room.is_private) return true;
            return room.created_by_user_id != null
                && Number(room.created_by_user_id) === Number(this.myUserId);
        },
        openMailEditRoom(room) {
            if (!room) return;
            this.mailEditRoomId        = room.id;
            this.mailEditRoomName      = room.name || '';
            this.mailEditRoomIsPrivate = !!room.is_private;
            this.mailEditRoomParentId  = room.parent_room_id ? String(room.parent_room_id) : '';
            // 親ラベル: 既存の親ルーム名を解決して入力欄に表示
            if (this.mailEditRoomParentId) {
                const all = [...(this.emailRoomsShared || []), ...(this.emailRoomsPersonal || [])];
                const p = all.find(x => String(x.id) === this.mailEditRoomParentId);
                this.mailEditParentLabel = p?.name || '';
            } else {
                this.mailEditParentLabel = '';
            }
            this.mailEditParentSearch    = '';
            this.mailEditRoomMergeTargetId = '';
            this.mailEditMergeSearch     = '';
            this.mailEditMergeLabel      = '';
            this.mailEditRoomIsCreator = (room.created_by_user_id != null
                && Number(room.created_by_user_id) === Number(this.myUserId));
            this.mailEditRoomOpen      = true;
            // 振り分けルールをロード
            this.routingRules = [];
            this.newRoutingRulePattern = '';
            this.loadRoutingRules();
        },

        // ===== 親ルーム / マージ先 候補の検索フィルタ =====
        // availableParentRooms (= 自分と子孫を除いた候補) に対し検索クエリで絞り込み.
        get availableParentRoomsSearched() {
            const base = this.availableParentRooms || [];
            const q = (this.mailEditParentLabel || '').toLowerCase().trim();
            if (!q) return base.slice(0, 50);
            return base.filter(r => (r.name || '').toLowerCase().includes(q)).slice(0, 50);
        },
        get availableMergeTargetsSearched() {
            const base = this.availableParentRooms || [];
            const q = (this.mailEditMergeLabel || '').toLowerCase().trim();
            if (!q) return base.slice(0, 50);
            return base.filter(r => (r.name || '').toLowerCase().includes(q)).slice(0, 50);
        },

        // ===== ルーム階層: 親変更 / マージ =====
        // 親として選択可能なルーム一覧 (自分自身と自分の子孫を除外).
        // 加えて MAX_DEPTH 制限: newParent.depth + me.subtree_max_depth <= MAX_DEPTH の親のみ.
        get availableParentRooms() {
            const all = [...(this.emailRoomsShared || []), ...(this.emailRoomsPersonal || [])];
            const meId = Number(this.mailEditRoomId);
            if (!meId) return all;
            const me = all.find(r => Number(r.id) === meId);
            const meIsPrivate = me ? !!me.is_private : false;
            const meSubtreeMax = me ? Number(me.subtree_max_depth || 1) : 1;
            // 自分の子孫 ID 集合を BFS で求める (ループ防止)
            const childrenMap = new Map();
            for (const r of all) {
                const pid = r.parent_room_id ? Number(r.parent_room_id) : null;
                if (pid !== null) {
                    if (!childrenMap.has(pid)) childrenMap.set(pid, []);
                    childrenMap.get(pid).push(Number(r.id));
                }
            }
            const bad = new Set([meId]);
            const queue = [meId];
            while (queue.length) {
                const cur = queue.shift();
                for (const c of (childrenMap.get(cur) || [])) {
                    if (!bad.has(c)) { bad.add(c); queue.push(c); }
                }
            }
            const maxParentDepth = this.ROOM_MAX_DEPTH - meSubtreeMax;
            return all
                .filter(r => !bad.has(Number(r.id)))
                // 共有/個人 のミックスは自由 (要望: 親子で公開範囲は独立可)
                .filter(r => Number(r.depth || 1) <= maxParentDepth); // 階層オーバー防止
        },

        async submitRoomParentChange() {
            if (!this.mailEditRoomId) return;
            const newParent = (this.mailEditRoomParentId === '' || this.mailEditRoomParentId === null)
                ? null : Number(this.mailEditRoomParentId);
            try {
                const r = await fetch(`/api/chat-rooms/${this.mailEditRoomId}/move`, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ parent_room_id: newParent }),
                });
                if (!r.ok) {
                    const d = await r.json().catch(() => ({}));
                    this.toast(d.error || '親ルームの変更に失敗しました', 'error');
                    return;
                }
                this.toast('親ルームを変更しました', 'success');
                await this.loadEmailRooms();
            } catch (e) { this.toast('通信エラー: ' + e.message, 'error'); }
        },

        async submitRoomMerge() {
            if (!this.mailEditRoomId) return;
            const targetId = Number(this.mailEditRoomMergeTargetId || 0);
            if (!targetId) { this.toast('マージ先のルームを選んでください', 'error'); return; }
            const all = [...(this.emailRoomsShared || []), ...(this.emailRoomsPersonal || [])];
            const targetName = all.find(r => Number(r.id) === targetId)?.name || '?';
            if (!confirm(`このルームを「${targetName}」に統合します。\n\n中身 (スレッド / チャット / 子ルーム / ルール / Wiki) は ${targetName} に引き継がれ、このルーム自体は削除されます。\n\nよろしいですか?`)) return;
            try {
                const r = await fetch(`/api/chat-rooms/${this.mailEditRoomId}/merge`, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ target_room_id: targetId }),
                });
                if (!r.ok) {
                    const d = await r.json().catch(() => ({}));
                    this.toast(d.error || 'マージに失敗しました', 'error');
                    return;
                }
                this.toast('ルームをマージしました', 'success');
                this.mailEditRoomOpen = false;
                await this.loadEmailRooms();
                // 現在ルームが消えたので 'all' に戻す
                if (String(this.emailRoomFilterId) === String(this.mailEditRoomId)) {
                    this.setRoomFilter('all');
                }
                this.loadThreads();
            } catch (e) { this.toast('通信エラー: ' + e.message, 'error'); }
        },

        // ===== 振り分けルール (パターン/フィルタ) CRUD =====
        async loadRoutingRules() {
            if (!this.mailEditRoomId) return;
            this.routingRulesLoading = true;
            try {
                const r = await fetch(`/api/chat-rooms/${this.mailEditRoomId}/routing-rules`,
                    { headers: { Accept:'application/json' } });
                if (r.ok) {
                    const d = await r.json();
                    this.routingRules = d.rules || [];
                }
            } catch (_) {}
            this.routingRulesLoading = false;
        },
        async addRoutingRule() {
            const pattern = (this.newRoutingRulePattern || '').trim();
            if (!pattern || this.routingRulesSubmitting || !this.mailEditRoomId) return;
            this.routingRulesSubmitting = true;
            try {
                const r = await fetch(`/api/chat-rooms/${this.mailEditRoomId}/routing-rules`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ type: this.newRoutingRuleType, pattern, enabled: true }),
                });
                if (!r.ok) {
                    let msg = '追加に失敗しました';
                    try { msg = (await r.json()).error || msg; } catch (_) {}
                    this.toast(msg, 'error');
                    return;
                }
                const d = await r.json();
                this.newRoutingRulePattern = '';
                await this.loadRoutingRules();
                // 既存メールへ遡及適用された件数 → トースト
                const n = d.backfilled || 0;
                if (n > 0) {
                    this.toast(`ルールを追加し、過去メール ${n} 件を取り込みました`, 'success');
                    // ルーム件数バッジ & 一覧を更新
                    this.loadEmailRooms();
                    if (String(this.emailRoomFilterId) === String(this.mailEditRoomId)) {
                        this.loadEmailRoomBundledThreads();
                        this.loadThreads();
                    }
                } else {
                    this.toast('ルールを追加しました', 'success');
                }
            } catch (e) {
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            } finally {
                this.routingRulesSubmitting = false;
            }
        },
        async toggleRoutingRule(rule) {
            if (!rule || !this.mailEditRoomId) return;
            const next = !rule.enabled;
            try {
                const r = await fetch(`/api/chat-rooms/${this.mailEditRoomId}/routing-rules/${rule.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ enabled: next }),
                });
                if (!r.ok) { this.toast('変更に失敗しました', 'error'); return; }
                rule.enabled = next;
            } catch (e) { this.toast('通信エラー: ' + (e.message || ''), 'error'); }
        },
        async deleteRoutingRule(rule) {
            if (!rule || !this.mailEditRoomId) return;
            if (!confirm(`ルール「${rule.type_label}: ${rule.pattern}」を削除しますか?\n(過去に振り分け済みのメールはそのまま残ります)`)) return;
            try {
                const r = await fetch(`/api/chat-rooms/${this.mailEditRoomId}/routing-rules/${rule.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
                if (!r.ok) { this.toast('削除に失敗しました', 'error'); return; }
                this.routingRules = this.routingRules.filter(x => x.id !== rule.id);
            } catch (e) { this.toast('通信エラー: ' + (e.message || ''), 'error'); }
        },
        async submitMailEditRoom() {
            const name = (this.mailEditRoomName || '').trim();
            if (!name || this.mailEditingRoom || !this.mailEditRoomId) return;
            this.mailEditingRoom = true;
            try {
                // 公開範囲: 共有ルームは全員変更可, 個人ルームは作成者のみ.
                // (mailEditPublicityAllowed で判定. サーバ側 update() も同じロジックで再防御)
                const body = { name };
                if (this.mailEditPublicityAllowed) body.is_private = !!this.mailEditRoomIsPrivate;
                const r = await fetch(`/api/chat-rooms/${this.mailEditRoomId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(body),
                });
                if (!r.ok) {
                    let msg = '保存に失敗しました';
                    try { msg = (await r.json()).error || msg; } catch (_) {}
                    alert(msg);
                    return;
                }
                this.mailEditRoomOpen = false;
                await this.loadEmailRooms();
            } catch (e) {
                alert('通信エラー: ' + e.message);
            } finally {
                this.mailEditingRoom = false;
            }
        },

        // 「+N」バッジクリック: 残りのルームをポップオーバで表示する
        openRoomChipPopover(thread, ev) {
            if (this.expandedRoomChipThreadId === thread.id) {
                this.expandedRoomChipThreadId = null;
                return;
            }
            try {
                const rect = ev.currentTarget.getBoundingClientRect();
                // ボタンの直下に出す. 画面右端からはみ出す場合は左寄せ.
                const POPOVER_W = 340;
                let x = rect.left;
                if (x + POPOVER_W > window.innerWidth - 8) {
                    x = Math.max(8, window.innerWidth - POPOVER_W - 8);
                }
                this.expandedRoomChipPos = { x, y: rect.bottom + 4 };
            } catch (_) {
                this.expandedRoomChipPos = { x: 0, y: 0 };
            }
            this.expandedRoomChipThreadId = thread.id;
        },
        get expandedRoomChipThread() {
            if (this.expandedRoomChipThreadId == null) return null;
            return (this.threads || []).find(t => t.id === this.expandedRoomChipThreadId) || null;
        },

        // 「スレッドをルームに追加」 — 単一 ID または 配列を受け付ける
        openAddToRoomModal(idOrIds) {
            const ids = Array.isArray(idOrIds) ? idOrIds.slice() : [idOrIds];
            this.addToRoomThreadIds = ids.filter(id => id != null);
            // 対象スレッド一覧は最大 5 件まで初期表示、それ以降は「他 N 件」.
            this.addToRoomShowAllTargets = false;
            // モーダルを開く度に「新規作成用の入力」をリセット (前回の入力を引き継がない)
            this.addToRoomNewName = '';
            // 振り分けルール作成セクションもリセット.
            // 選択中スレッドの from_address を予め引いて差出人パターンの初期値に入れておく.
            this.addToRoomCreateRule = false;
            this.addToRoomRuleType = 'from_address';
            this.addToRoomRulePattern = '';
            try {
                const firstId = this.addToRoomThreadIds[0];
                if (firstId) {
                    const t = (this.threads || []).find(x => x.id === firstId);
                    const guess = t?.latest_email?.from_address || t?.from_address || '';
                    if (guess) this.addToRoomRulePattern = guess;
                }
            } catch (_) {}
            this.addToRoomOpen = true;
            // モーダル open 直後の DOM 反映後に:
            //   (1) キーボードナビの初期ハイライト
            //   (2) 入力欄にフォーカス (L 押下後すぐ名前を入力開始できるようにする)
            // 注: input には @keydown.stop が付いているのでグローバル J/K ナビは
            //     入力中に発火せず、入力中の文字がショートカットに食われる事故も無い。
            this.$nextTick(() => {
                const shared = this.addToRoomFilteredShared || [];
                const personal = this.addToRoomFilteredPersonal || [];
                this.addToRoomHighlightId = shared.length > 0
                    ? shared[0].id
                    : (personal.length > 0 ? personal[0].id : null);
                // 入力欄を一発フォーカス。x-ref が未準備の極端なタイミングでも
                // querySelector フォールバックで救う。
                try {
                    const el = this.$refs.addToRoomNameInput
                        || document.querySelector('input[x-ref="addToRoomNameInput"]');
                    if (el && typeof el.focus === 'function') {
                        el.focus();
                        if (typeof el.select === 'function') el.select();
                    }
                } catch (_) {}
            });
        },

        // モーダル内 J/K/↑/↓ ナビゲーション用. 全候補を [...shared, ...personal] の順で配列化.
        get _addToRoomAllVisible() {
            return [...(this.addToRoomFilteredShared || []), ...(this.addToRoomFilteredPersonal || [])];
        },
        // 矢印キー or J/K で次/前の候補へ. delta = +1 / -1
        addToRoomNavHighlight(delta) {
            const arr = this._addToRoomAllVisible;
            if (arr.length === 0) return;
            let idx = arr.findIndex(r => r.id === this.addToRoomHighlightId);
            if (idx < 0) idx = 0;
            else idx = Math.max(0, Math.min(arr.length - 1, idx + delta));
            this.addToRoomHighlightId = arr[idx].id;
            // ハイライト中の行が可視範囲外なら scrollIntoView (モーダル内のスクロール対応)
            this.$nextTick(() => {
                const el = document.querySelector(`[data-room-id="${this.addToRoomHighlightId}"]`);
                if (el && typeof el.scrollIntoView === 'function') {
                    el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            });
        },
        // Enter キーでハイライト中の候補を確定
        addToRoomConfirmHighlight() {
            const arr = this._addToRoomAllVisible;
            const r = arr.find(x => x.id === this.addToRoomHighlightId);
            if (r) this.confirmAddToRoom(r);
        },

        // ====== モーダル内: ルームのフィルタリング & 部分一致サジェスト ======
        //
        // 入力名 (addToRoomNewName) でリストを絞り込みつつ、
        // 「似た既存ルームがある」ことを上部のサジェストにも別表示する。
        //  - 何も入力していない時: 全件表示 / サジェスト無し
        //  - 入力時: substring 一致だけに絞る (大文字小文字無視)
        //  - サジェストはさらに「共有→個人」で並び替え、最大 8 件
        get _addToRoomQueryNormalized() {
            return (this.addToRoomNewName || '').trim().toLowerCase();
        },
        get addToRoomFilteredShared() {
            const q = this._addToRoomQueryNormalized;
            const base = this.emailRoomsShared || [];
            return q ? base.filter(r => (r.name || '').toLowerCase().includes(q)) : base;
        },
        get addToRoomFilteredPersonal() {
            const q = this._addToRoomQueryNormalized;
            const base = this.emailRoomsPersonal || [];
            return q ? base.filter(r => (r.name || '').toLowerCase().includes(q)) : base;
        },
        // 上部に出すサジェスト一覧 (2 文字以上で発動。フィルタ済みリストと同じ集合だが、
        // 一覧の前に「似たルームあり」と強調表示するために独立に管理)
        get addToRoomSimilarRooms() {
            const q = this._addToRoomQueryNormalized;
            if (q.length < 2) return [];
            const allRooms = [...(this.emailRoomsShared || []), ...(this.emailRoomsPersonal || [])];
            const matches = allRooms.filter(r => (r.name || '').toLowerCase().includes(q));
            return matches.sort((a, b) => {
                const ap = a.is_private ? 1 : 0;
                const bp = b.is_private ? 1 : 0;
                if (ap !== bp) return ap - bp;
                return (a.name || '').localeCompare(b.name || '');
            }).slice(0, 8);
        },

        // 新規ルームを作成して、対象スレッドをそこへ追加。
        // 旧版は prompt() で名前を聞いていたが、モーダル内のテキストフィールドから受け取る形に変更。
        // 入力中に部分一致サジェストが出るため、重複作成を防ぎつつ既存ルームへワンクリックで合流できる。
        async createRoomAndAttach(isPrivate = false) {
            const ids = (this.addToRoomThreadIds || []).slice();
            if (ids.length === 0) return;
            const name = (this.addToRoomNewName || '').trim();
            if (!name) {
                this.toast('新規ルーム名を入力してください', 'error');
                return;
            }
            if (this.addToRoomCreating) return;
            this.addToRoomCreating = true;
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            try {
                const r = await fetch('/api/chat-rooms', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ name, is_private: !!isPrivate }),
                });
                if (!r.ok) { alert('ルーム作成に失敗しました'); return; }
                const data = await r.json();
                if (!data?.room?.id) { alert('作成結果が不正です'); return; }
                // ルーム一覧をリロードして、新ルームに対象スレッドを一括追加.
                // フォローアップで「新規作成バッジ」を出すためのフラグを立てておく.
                this._addToRoomJustCreated = true;
                await this.loadEmailRooms();
                await this.confirmAddToRoom(data.room);
            } catch (e) {
                alert('通信エラー: ' + e.message);
            } finally {
                this.addToRoomCreating = false;
            }
        },

        async confirmAddToRoom(room) {
            const ids = this.addToRoomThreadIds || [];
            if (ids.length === 0 || !room) return;
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            let failCount = 0;
            // ★ Undo 用に「成功追加した thread id」を記録
            const addedIds = [];
            try {
                for (const threadId of ids) {
                    const r = await fetch(`/api/chat-rooms/${room.id}/threads`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ thread_id: threadId }),
                    });
                    if (!r.ok) failCount++;
                    else addedIds.push(threadId);
                }
                if (failCount > 0) alert(`${ids.length - failCount}件追加 / ${failCount}件失敗`);

                // Undo: 追加した分をルームから外す
                // (このフローでルーティングルールも登録されたケースは Undo 対象外.
                //  ルール登録の取り消しは「ルーム編集 → ルール削除」で行ってもらう.)
                if (addedIds.length > 0) {
                    const undoRoomId = room.id;
                    const undoRoomName = room.name;
                    this._pushUndoAction(
                        `ルーム「${undoRoomName}」へ追加 (${addedIds.length} 件)`,
                        async () => {
                            for (const tid of addedIds) {
                                await fetch(`/api/chat-rooms/${undoRoomId}/threads/${tid}`, {
                                    method: 'DELETE',
                                    headers: { 'Accept':'application/json', 'X-CSRF-TOKEN': csrf },
                                });
                            }
                            this.loadEmailRoomBundledThreads();
                            this.loadEmailRooms();
                            this.loadThreads();
                        }
                    );
                }

                // 成功件数のトースト (続いてフォローアップモーダルが開くのでシンプルに)
                if (addedIds.length > 0) {
                    this.toast('ルーム「' + (room.name || '') + '」に ' + addedIds.length + ' 件追加しました', 'success');
                }

                // メインモーダルを閉じる前にスナップショットを取る
                const wasCreated = !!this._addToRoomJustCreated;
                this._addToRoomJustCreated = false;
                const attachedCount = addedIds.length;

                this.addToRoomOpen = false;
                this.addToRoomThreadIds_snapshot = (this.addToRoomThreadIds || []).slice();
                // addToRoomThreadIds はクイックフィルチップが参照しているので、ここでは消さず
                //   フォローアップを閉じる時にクリアする (チップにスレッド情報を残すため).
                this.addToRoomNewName = '';
                this.addToRoomCreateRule = false;
                this.addToRoomRulePattern = '';
                this.addToRoomHighlightId = null;
                if (this.selectionMode) this.cancelSelection();
                this.loadEmailRoomBundledThreads();
                this.loadEmailRooms();
                this.loadThreads();

                // ★ ルール追加のフォローアップ モーダルを開く.
                //   失敗 (addedIds=0) の時は出さない (追加できなかったのにルール聞いても意味がない).
                if (addedIds.length > 0) {
                    this._openRoutingFollowup(room, wasCreated, attachedCount);
                }
            } catch (e) { alert('通信エラー: ' + e.message); }
        },

        // ===== 振り分けルール フォローアップ =====
        // ★ openSpamRuleFollowup と同様に、addToRoomThreadIds に対応する thread 本体が
        //   this.threads / this.emailRoomBundledThreads から見つからない場合の保険として
        //   _routingFollowupThreadCache に積んでおく.
        //   通常は confirmAddToRoom 経由で addToRoomThreadIds が生きているので
        //   step (1) で当たるが、念のための defense in depth.
        _openRoutingFollowup(room, created = false, attachedCount = 0) {
            this.routingFollowupRoomId   = room.id;
            this.routingFollowupRoomName = room.name || '';
            this.routingFollowupRoomCreated = !!created;
            this.routingFollowupAttachedCount = attachedCount;
            this.routingFollowupAddedRules = [];

            // クイックフィルチップが addToRoomThreadIds の thread を見つけられない時のフォールバックを構築:
            //   選択中スレッドが addToRoomThreadIds に含まれていれば、その latest_email を合成して
            //   _routingFollowupThreadCache に積む.
            try {
                const firstId = (this.addToRoomThreadIds || [])[0];
                if (firstId) {
                    // selectedThread が一致する場合: threadEmails から最新を合成.
                    if (this.selectedThread && Number(this.selectedThread.id) === Number(firstId)) {
                        const e0 = (this.threadEmails || [])[0];
                        if (e0) {
                            this._routingFollowupThreadCache = {
                                id: firstId,
                                subject: this.selectedThread.subject || e0.subject,
                                latest_email: {
                                    subject:      e0.subject || '',
                                    from_address: e0.from_address || '',
                                    from_label:   e0.from_label || '',
                                    to_address:   e0.to_address || '',
                                    cc:           e0.cc || '',
                                    bcc:          e0.bcc || '',
                                },
                            };
                        }
                    }
                }
            } catch (_) {}

            // 初期値: スレッドの from_address をプリセットしつつ、
            // タイプは「any_address (From/To/Cc 全部を見るアドレス完全一致)」を既定にする.
            // これでユーザは「同じアドレスがどこに出ても」マッチするルールを 1 クリックで追加できる.
            const guessAddr = this.addToRoomGuessFromAddress || '';
            this.routingFollowupType = 'any_address';
            this.routingFollowupPattern = guessAddr;
            this.routingFollowupOpen = true;
        },
        useRoutingFollowupQuickFill(type, value) {
            // ビルダーモード時はチップクリックで「ルートグループの末尾に新しい条件を追加」する.
            // 既存リーフが空文字なら上書き (= 最初のチップクリックで空のスロットが埋まる).
            if (this.rfBuilderMode) {
                const items = this.rfBuilderTree.items;
                const emptyIdx = items.findIndex(it => !it.logic && !String(it.pattern || '').trim());
                if (emptyIdx >= 0) {
                    items[emptyIdx] = { type, pattern: value || '' };
                } else {
                    items.push({ type, pattern: value || '' });
                }
                return;
            }
            // 単一条件モード: 従来通り入力欄に転記.
            this.routingFollowupType = type;
            this.routingFollowupPattern = value || '';
        },
        async submitRoutingFollowup(keepOpen = false) {
            if (!this.routingFollowupRoomId) return;
            this.routingFollowupSaving = true;
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const typeLabels = {
                from_address:     '差出人',
                from_domain:      'ドメイン (From)',
                subject_contains: '件名',
                to_contains:      '宛先',
                any_address:      'アドレス',
                any_domain:       'ドメイン',
            };

            // ビルダーモード: conditions ツリーで送信. 入力済みパターン全部を trim() して送る.
            // 単一条件モード: 従来の type+pattern を送信.
            let body, summaryLabel, summaryPattern;
            if (this.rfBuilderMode) {
                if (!this.rfBuilderValid) {
                    this.routingFollowupSaving = false;
                    this.toast('未入力の条件があります', 'error');
                    return;
                }
                const clean = (n) => {
                    if (n.logic) {
                        return { logic: n.logic, items: (n.items || []).map(clean) };
                    }
                    return { type: n.type, pattern: String(n.pattern || '').trim() };
                };
                const tree = clean(this.rfBuilderTree);
                body = { conditions: tree, enabled: true };
                summaryLabel = (tree.logic === 'and') ? 'AND 複合' : 'OR 複合';
                // 追加済みリストに出すパターン: 各リーフを「type=pattern」形式で連結.
                const describe = (n) => {
                    if (n.type) return `${(typeLabels[n.type] || n.type)}=${n.pattern}`;
                    const items = (n.items || []).map(describe);
                    const sep = ` ${(n.logic || 'or').toUpperCase()} `;
                    return items.length > 1 ? `( ${items.join(sep)} )` : (items[0] || '');
                };
                summaryPattern = describe(tree);
            } else {
                const pattern = (this.routingFollowupPattern || '').trim();
                if (!pattern) { this.routingFollowupSaving = false; return; }
                body = { type: this.routingFollowupType, pattern, enabled: true };
                summaryLabel = typeLabels[this.routingFollowupType] || this.routingFollowupType;
                summaryPattern = pattern;
            }

            try {
                const r = await fetch(`/api/chat-rooms/${this.routingFollowupRoomId}/routing-rules`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(body),
                });
                if (!r.ok) {
                    const d = await r.json().catch(() => ({}));
                    this.toast(d.error || d.message || 'ルール登録に失敗しました', 'error');
                    return;
                }
                const data = await r.json().catch(() => ({}));
                const n = data.backfilled || 0;
                this.routingFollowupAddedRules.push({
                    type: body.type || 'compound',
                    type_label: summaryLabel,
                    pattern: summaryPattern,
                    backfilled: n,
                });
                this.toast(n > 0
                    ? `振り分けルールを追加 (過去 ${n} 件取り込み)`
                    : '振り分けルールを追加しました', 'success');
                this.loadEmailRooms();
                this.loadEmailRoomBundledThreads();
                this.loadThreads();
                // 続けて入力する: ビルダー / 単一条件それぞれの入力をリセット. モーダルは維持.
                if (keepOpen) {
                    if (this.rfBuilderMode) {
                        this.rfBuilderResetTree();
                    } else {
                        this.routingFollowupPattern = '';
                    }
                } else {
                    this.closeRoutingFollowup();
                }
            } catch (e) {
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            } finally {
                this.routingFollowupSaving = false;
            }
        },
        closeRoutingFollowup() {
            this.routingFollowupOpen = false;
            this.routingFollowupRoomId = null;
            this.routingFollowupRoomName = '';
            this.routingFollowupPattern = '';
            this.routingFollowupAddedRules = [];
            this.routingFollowupRoomCreated = false;
            this.routingFollowupAttachedCount = 0;
            // ビルダー状態もモーダル閉じ時にリセット (次回開いた時に前回の中身が残らないよう).
            this.rfBuilderMode = false;
            this.rfBuilderResetTree();
            // クイックフィルチップ用に温存していた addToRoomThreadIds をここでクリア
            this.addToRoomThreadIds = [];
            this._routingFollowupThreadCache = null;
        },

        // ===== 迷惑メール ブロックルール フォローアップ モーダル =====
        // markSelectedAsSpam / quickMarkSpam の直後に「ブロックルールを追加しますか?」を開く.
        // ルーム振り分け側の routingFollowup と同じ思想で、quick-fill チップから自由にパターンを編集できる.
        //
        // ★ 引数:
        //    threadId : スレッド ID
        //    threadObj: (オプション) スレッド本体. quickMarkSpam のように list-row 経由で呼ばれた場合は
        //               selectedThread が一致しない事があるため, 呼び出し側が thread を持っているなら
        //               そのまま渡してもらって _addToRoomFirstThread の検索を確実に当てる.
        //               threadObj.latest_email があれば即座に from/to/cc/subject チップが描画される.
        openSpamRuleFollowup(threadId, threadObj = null) {
            this.spamRuleFollowupThreadId = threadId || null;
            this.spamRuleFollowupAddedRules = [];
            // クイックフィル候補を取得するため、選択中スレッドを addToRoomThreadIds に詰めておく.
            // (addToRoomGuessFromAddress 等のゲッターはこれを起点に動く)
            if (threadId) {
                this.addToRoomThreadIds = [threadId];
            }
            // ★ 呼び出し側から渡された thread を「クイックフィル候補のキャッシュ」に積む.
            //    selectedThread / threadEmails が無くてもチップが必ず描画されるようにする.
            this._spamFollowupThreadCache = threadObj || null;
            // 初期値: from_address をプリセット (一番よくあるケース)
            const guessAddr = this.addToRoomGuessFromAddress || '';
            this.spamRuleFollowupType = 'sender_address';
            this.spamRuleFollowupPattern = guessAddr;
            this.spamRuleFollowupOpen = true;
        },
        useSpamRuleFollowupQuickFill(type, value) {
            // ビルダーモード時はチップクリックを「空スロット埋め or 末尾追加」として扱う.
            if (this.srBuilderMode) {
                const items = this.srBuilderTree.items;
                const emptyIdx = items.findIndex(it => !it.logic && !String(it.pattern || '').trim());
                if (emptyIdx >= 0) {
                    items[emptyIdx] = { type, pattern: value || '' };
                } else {
                    items.push({ type, pattern: value || '' });
                }
                return;
            }
            this.spamRuleFollowupType = type;
            this.spamRuleFollowupPattern = value || '';
        },
        async submitSpamRuleFollowup(keepOpen = false) {
            this.spamRuleFollowupSaving = true;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const typeLabels = {
                sender_address:     '送信元アドレス',
                sender_domain:      '送信元ドメイン',
                recipient_address:  '宛先アドレス',
                recipient_domain:   '宛先ドメイン',
                recipient_contains: '宛先に含む',
                subject_keyword:    '件名',
                body_keyword:       '本文',
            };

            // 送信ボディの組み立て: モード別に conditions か type+pattern.
            let body, summaryType, summaryLabel, summaryPattern;
            if (this.srBuilderMode) {
                if (!this.srBuilderValid) {
                    this.spamRuleFollowupSaving = false;
                    this.toast('未入力の条件があります', 'error');
                    return;
                }
                const clean = (n) => {
                    if (n.logic) return { logic: n.logic, items: (n.items || []).map(clean) };
                    return { type: n.type, pattern: String(n.pattern || '').trim() };
                };
                const tree = clean(this.srBuilderTree);
                body = { conditions: tree, enabled: true };
                summaryType  = 'compound';
                summaryLabel = (tree.logic === 'and') ? 'AND 複合' : 'OR 複合';
                const describe = (n) => {
                    if (n.type) return `${(typeLabels[n.type] || n.type)}=${n.pattern}`;
                    const items = (n.items || []).map(describe);
                    const sep = ` ${(n.logic || 'or').toUpperCase()} `;
                    return items.length > 1 ? `( ${items.join(sep)} )` : (items[0] || '');
                };
                summaryPattern = describe(tree);
            } else {
                const pattern = (this.spamRuleFollowupPattern || '').trim();
                if (!pattern) { this.spamRuleFollowupSaving = false; return; }
                body = { type: this.spamRuleFollowupType, pattern, enabled: true };
                summaryType    = this.spamRuleFollowupType;
                summaryLabel   = typeLabels[this.spamRuleFollowupType] || this.spamRuleFollowupType;
                summaryPattern = pattern;
            }

            try {
                const r = await fetch('/api/mail-block-rules', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(body),
                });
                if (!r.ok) {
                    const d = await r.json().catch(() => ({}));
                    this.toast(d.message || 'ブロックルール登録に失敗しました', 'error');
                    return;
                }
                this.spamRuleFollowupAddedRules.push({
                    type: summaryType,
                    type_label: summaryLabel,
                    pattern: summaryPattern,
                });
                this.toast('ブロックルールを追加しました', 'success');
                // 続けて入力するなら入力欄をリセットしてモーダル維持.
                if (keepOpen) {
                    if (this.srBuilderMode) this.srBuilderResetTree();
                    else this.spamRuleFollowupPattern = '';
                } else {
                    this.closeSpamRuleFollowup();
                }
            } catch (e) {
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            } finally {
                this.spamRuleFollowupSaving = false;
            }
        },
        closeSpamRuleFollowup() {
            this.spamRuleFollowupOpen = false;
            this.spamRuleFollowupThreadId = null;
            this.spamRuleFollowupPattern = '';
            this.spamRuleFollowupAddedRules = [];
            // ビルダー状態もリセット.
            this.srBuilderMode = false;
            this.srBuilderResetTree();
            this.addToRoomThreadIds = [];
            this._spamFollowupThreadCache = null;
            // quickMarkSpam (S ショートカット) 経由でモーダルが開いていた場合、
            // 閉じる時点で「次のスレッドへ自動ジャンプ」を実行する.
            // (これがないと S → モーダル閉じ後にカーソルが残って次操作で混乱する)
            if (this._spamFollowupPendingJumpId) {
                const jumpId = this._spamFollowupPendingJumpId;
                this._spamFollowupPendingJumpId = null;
                try {
                    this.loadThread(jumpId).then(() => {
                        try { this.scrollThreadIntoView(jumpId); } catch (_) {}
                    });
                } catch (_) {}
            }
        },

        // ルーム削除 (個人/共有とも、作成者本人は削除可)
        async deleteEmailRoom(room, $event) {
            if ($event) $event.stopPropagation();
            if (!room || !room.id) return;
            if (!confirm(`「${room.name}」を削除しますか?\n(他のユーザーや、束ねたスレッドの紐付けも解除されます)`)) return;
            try {
                const r = await fetch(`/api/chat-rooms/${room.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
                if (!r.ok) {
                    const data = await r.json().catch(() => ({}));
                    alert('削除失敗: ' + (data.error || r.status));
                    return;
                }
                if (String(this.emailRoomFilterId) === String(room.id)) {
                    this.setRoomFilter('all');
                }
                await this.loadEmailRooms();
            } catch (e) { alert('通信エラー: ' + e.message); }
        },
        async loadEmailRooms() {
            try {
                const scope = this.inboxScope || 'shared';
                let url = '/api/chat-rooms?scope=' + encodeURIComponent(scope);
                if (scope === 'personal' && this.selectedPersonalAccountId) {
                    url += '&mail_account_id=' + this.selectedPersonalAccountId;
                }
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const d = await res.json();
                const rooms = d.rooms || [];
                this.emailRoomsShared = rooms.filter(r => !r.is_private);
                this.emailRoomsPersonal = rooms.filter(r => r.is_private);
                this.hiddenRoomIds = (d.hidden_rooms || []).map(Number);
                // 「すべて」「ルーム未設定」タブにも未読バッチを出すための合計値。
                // どのルームにも紐付いていない inbox スレッドが完全に隠れてしまうと、
                // レポートの 受信 N 件と画面表示が乖離するので必ず数値を見せる。
                this.globalReceivedCount   = Number(d.global_received_count   || 0);
                this.unroutedReceivedCount = Number(d.unrouted_received_count || 0);
                // status 別 内訳 (受信/保留/承認待ち) — バッジ色分け用.
                this.globalInboxCount      = Number(d.global_inbox_count      || 0);
                this.globalHoldCount       = Number(d.global_hold_count       || 0);
                this.globalPendingCount    = Number(d.global_pending_count    || 0);
                this.unroutedInboxCount    = Number(d.unrouted_inbox_count    || 0);
                this.unroutedHoldCount     = Number(d.unrouted_hold_count     || 0);
                this.unroutedPendingCount  = Number(d.unrouted_pending_count  || 0);
                // 選択中ルームが現在の可視リストから消えていたら "all" にリセット
                // ('none' は特殊フィルタなのでこの自動リセットの対象外)
                const allIds = rooms.map(r => String(r.id));
                if (this.emailRoomFilterId !== 'all'
                    && this.emailRoomFilterId !== 'none'
                    && !allIds.includes(String(this.emailRoomFilterId))) {
                    this.emailRoomFilterId = 'all';
                    try { localStorage.setItem('emailRoomFilterId', 'all'); } catch (_) {}
                }
            } catch (_) {}
        },
        async toggleHideRoom(id) {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const r = await fetch('/api/chats/hide', {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf },
                    body: JSON.stringify({ type: 'room', id }),
                });
                if (!r.ok) { this.toast('ルーム非表示に失敗しました', 'error'); return; }
                const nid = Number(id);
                if (!this.hiddenRoomIds.includes(nid)) this.hiddenRoomIds.push(nid);
                // 非表示にしたルームが選択中だった場合は「すべて」に戻す
                if (String(this.emailRoomFilterId) === String(id)) this.setRoomFilter('all');
                this.toast('ルームを非表示にしました', 'success');
            } catch (_) { this.toast('通信エラー', 'error'); }
        },
        async unhideRoom(id) {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const r = await fetch('/api/chats/unhide', {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf },
                    body: JSON.stringify({ type: 'room', id }),
                });
                if (!r.ok) { this.toast('再表示に失敗しました', 'error'); return; }
                this.hiddenRoomIds = this.hiddenRoomIds.filter(x => Number(x) !== Number(id));
                this.toast('ルームを再表示しました', 'success');
            } catch (_) { this.toast('通信エラー', 'error'); }
        },

        // 共有/個人ルームのピン留めをトグル (per-user).
        // バックエンドは UserChatPin (user_id, pinnable_type='room', pinnable_id) を作成/削除.
        // 同じルームを他のユーザがピン留めしても自分の表示には影響しない.
        async togglePinSidebarRoom(room) {
            if (!room || !room.id) return;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const r = await fetch('/api/chats/pin', {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf },
                    body: JSON.stringify({ type: 'room', id: room.id }),
                });
                if (!r.ok) { this.toast('ピン操作に失敗しました', 'error'); return; }
                const d = await r.json();
                room.is_pinned_chat = !!d.pinned;
                this.toast(d.pinned ? 'ルームをピン留めしました' : 'ピン留めを解除しました', 'success');
            } catch (_) { this.toast('通信エラー', 'error'); }
        },
        toggleSort() { this.sortOrder = (this.sortOrder === 'desc' ? 'asc' : 'desc'); this.loadThreads(); },

        // ===== 同期ボタンの動作 / 見た目を集約 =====
        // 失敗 / 警告 がある場合はクリックで詳細モーダルを開く。
        // 何も無い場合は新規に取得を実行。
        onSyncButtonClick() {
            if (this.persistentSyncError) {
                this.syncError = this.persistentSyncError;
                return;
            }
            if (this.persistentSyncWarning) {
                this.syncError = this.persistentSyncWarning;
                return;
            }
            this.fetchEmails();
        },
        syncButtonClasses() {
            if (this.persistentSyncError) {
                return 'text-white bg-red-600 hover:bg-red-700 ring-2 ring-red-300 animate-pulse';
            }
            if (this.persistentSyncWarning) {
                return 'text-white bg-amber-500 hover:bg-amber-600 ring-2 ring-amber-200';
            }
            return 'text-gray-400 hover:text-blue-600 hover:bg-gray-50';
        },
        syncButtonTitle() {
            if (this.persistentSyncError) {
                return '⚠ メール同期に失敗しています。クリックで詳細表示\n' + (this.persistentSyncError.detail || '');
            }
            if (this.persistentSyncWarning) {
                return '一部メール取り込みに失敗。クリックで詳細\n' + (this.persistentSyncWarning.detail || '');
            }
            return '一覧を更新';
        },

        async fetchEmails(isBackground = false) {
            if (this.fetching) {
                // 既に取得中の場合は無音にせず、ユーザに「まだ前のが走ってるよ」と通知
                if (!isBackground) this.toast('現在メール取得中です。完了までお待ちください', 'info');
                return;
            }
            if (!isBackground) {
                this.fetching = true;
                this.syncError = null;
            }
            try {
                const res = await fetch('/emails/fetch', { method: 'POST', headers: this.jsonHeaders() });
                let data = {};
                try { data = await res.json(); } catch(_) {}

                if (!res.ok) {
                    // サーバ側エラー (接続不能等): 個別エラーリストもまとめて表示する
                    const err = new Error(data.error || data.message || `HTTP ${res.status}`);
                    err.serverData = data;
                    throw err;
                }

                await this.loadThreads(isBackground);

                // 新着メールが取り込まれていればルーム件数バッジも更新.
                // (新規メールが届くと received_email_count が増えるため)
                if ((data.count ?? 0) > 0) {
                    this.loadEmailRooms();
                }

                // 取り込み成功だが個別エラーがあった場合は警告として表示
                if ((data.error_count || 0) > 0) {
                    const partial = {
                        message: '取り込みは完了しましたが、一部のメールでエラーが発生しました',
                        detail:  `成功: ${data.count ?? 0} 件 / 失敗: ${data.error_count} 件`,
                        errors:  data.errors || [],
                        stack:   '',
                    };
                    if (!isBackground) this.syncError = partial;
                    this.persistentSyncWarning = partial;   // バナー表示は常時更新
                } else {
                    this.persistentSyncWarning = null;       // 完全成功で警告クリア
                }

                // 永続エラーバナーを自動でクリアするのは「動作している証拠」がある時だけ:
                //  A. data.count > 0   (実際に新規メールが取り込まれた)
                //  B. 直近 30 秒以内に新規エラーがセットされていない
                // 上記いずれかを満たさない場合は、バナーをキープ (= ユーザが X で閉じるか手動で再試行するまで)。
                // 理由: webklex が POP3 で認証失敗を握り潰し「200 / 0 件」を返したときに、
                //       バナーが瞬間で消えるのを防ぐ。
                const recentErrorMs = this.persistentSyncErrorAt
                    ? Date.now() - this.persistentSyncErrorAt
                    : Infinity;
                const importedAny = (data.count ?? 0) > 0;
                if (importedAny || recentErrorMs > 30 * 1000) {
                    this.persistentSyncError   = null;
                    this.persistentSyncErrorAt = 0;
                    this.bgErrorAlreadyShown   = false;
                }
                // ↑ 条件を満たさない (= 0 件 + 直近にエラーあり) なら、バナーは保持。
                //   信頼できる成功シグナル (= 実取り込み or 十分時間経過) が出るまで待つ。

                this.pollFailCount = 0;
                this.currentPollDelay = this.basePollDelay;

                // 手動取得 (= ユーザが同期ボタンを押した) の場合は必ずフィードバックを出す。
                // 「無音で 0 件」は UX 上「壊れた」と区別が付かないため、件数を必ずトーストで通知。
                // backfilled は「既存メールのうち前回うまく取れなかったフィールドを再パースで救済した件数」。
                // 新規取り込みが 0 でも backfill があれば「修復した」旨を表示する。
                if (!isBackground) {
                    const count = data.count ?? 0;
                    const errCount = data.error_count ?? 0;
                    const backfilled = data.backfilled ?? 0;
                    const backfillNote = backfilled > 0 ? ` / 既存 ${backfilled} 件を再取得で更新` : '';
                    if (count === 0 && errCount === 0 && backfilled === 0) {
                        this.toast('新しいメールはありません', 'info');
                    } else if (count === 0 && errCount === 0 && backfilled > 0) {
                        this.toast(`新着なし。既存 ${backfilled} 件を再取得で更新しました`, 'success');
                    } else if (count > 0 && errCount === 0) {
                        this.toast(`${count} 件取り込みました${backfillNote}`, 'success');
                    } else if (count > 0 && errCount > 0) {
                        this.toast(`${count} 件取り込み、${errCount} 件失敗${backfillNote}。詳細はバナーをクリック`, 'warning');
                    } else {
                        this.toast(`${errCount} 件のメールでエラーが発生しました${backfillNote}`, 'error');
                    }
                }
            } catch (e) {
                const sd = e.serverData || {};
                const detailParts = [];
                if (e.message) detailParts.push(e.message);
                if (sd.connection_error && sd.connection_error !== e.message) detailParts.push(`(接続詳細: ${sd.connection_error})`);
                const errObj = {
                    message: 'メールサーバーとの同期に失敗しました',
                    detail:  detailParts.join(' ') || '原因不明のエラー',
                    errors:  sd.errors || [],
                    stack:   e.stack || '',
                    consecutive_failures: sd.consecutive_failures ?? null,
                };

                if (!isBackground) {
                    // 手動取得失敗 → 必ずモーダル
                    this.syncError = errObj;
                } else if (!this.bgErrorAlreadyShown) {
                    // バックグラウンド失敗の「最初の 1 回」もモーダルで強制通知
                    // (それ以降は永続バナーだけ更新。バナーをクリックで再びモーダル可)
                    this.syncError = errObj;
                    this.bgErrorAlreadyShown = true;
                }
                // バックグラウンド失敗は無音にせず永続バナーにも格納
                // (連続失敗回数はサーバ側で蓄積した値を表示)
                this.persistentSyncError   = errObj;
                this.persistentSyncErrorAt = Date.now();   // ← セット時刻を記録 (30秒以内は自動クリア禁止)
                this.persistentSyncWarning = null;

                this.pollFailCount = Math.min(this.pollFailCount + 1, 8);
                const delay = this.basePollDelay * Math.pow(2, this.pollFailCount);
                this.currentPollDelay = Math.min(delay, this.maxPollDelay);
            } finally {
                if (!isBackground) this.fetching = false;
                // 取得後は必ずサーバ側の永続ステータスを再取得して、
                // 「サーバ側で記録されたエラー (= webklex の握り潰し検出 / 過去ポーリング失敗)」を必ず拾う
                this.refreshFetchStatus({ openModalIfFailing: !isBackground });
            }
        },

        // 直近のサーバ側保存ステータスを問い合わせて、ページロード時に
        // 「前回ポーリングが失敗していたか」を判断する。失敗していれば
        // persistentSyncError に詰めて常設バナーを出す。
        // openModalIfFailing=true なら、失敗状態を確認したときに syncError モーダルも開く。
        async refreshFetchStatus(opts = {}) {
            try {
                const res = await fetch('/emails/fetch-status', { headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                const s = await res.json();
                if (s.is_failing) {
                    // サーバが失敗中と判定 → 永続バナーを表示
                    const errObj = {
                        message: 'メールサーバーとの同期に失敗しています',
                        detail:  s.last_fetch_error || '原因不明のエラー',
                        errors:  [],
                        stack:   '',
                        consecutive_failures: s.consecutive_failures,
                        last_fetch_error_at:  s.last_fetch_error_at,
                    };
                    this.persistentSyncError = errObj;
                    // 手動操作直後にステータスが失敗を示すなら、モーダルも開いて確実に通知
                    if (opts.openModalIfFailing) {
                        this.syncError = errObj;
                    }
                }
                // is_failing=false のときは「ローカルに既にセット済みのエラーを消さない」。
                //   - catch ブロックが直前に persistentSyncError を set した直後に
                //     fetch-status が呼ばれて is_failing=false が返るタイミング競合があり、
                //     誤ってバナーを消してしまっていた。
                //   - クリアは fetchEmails の成功パス (`this.persistentSyncError = null`)
                //     にのみ任せる。これで「実際に取得が成功」したときだけ消える。
            } catch (e) {
                // ステータス取得自体が失敗した = 多分セッション切れ or 通信不可。
                // これも沈黙させずトーストで通知する。
                console.warn('refreshFetchStatus failed:', e);
                if (opts.openModalIfFailing) {
                    this.toast('取得ステータスの確認に失敗しました: ' + (e.message || ''), 'error');
                }
            }
        },

        startLongPress(thread, e) {
            if (e.target.closest('button') || e.target.tagName.toLowerCase() === 'button') return;
            this.isLongPressing = false;
            this.longPressTimer = setTimeout(() => {
                this.isLongPressing = true;
                // 長押しで直接、複数選択モードに入って該当スレッドを選択
                this.selectionMode = true;
                if (!this.selectedThreadIds.includes(thread.id)) {
                    this.toggleSelection(thread);
                }
            }, 500);
        },
        cancelLongPress() { clearTimeout(this.longPressTimer); },
        toggleSelection(thread) {
            if (this.selectedThreadIds.includes(thread.id)) {
                this.selectedThreadIds = this.selectedThreadIds.filter(id => id !== thread.id);
            } else {
                this.selectedThreadIds.push(thread.id);
            }
            this.selectionMode = this.selectedThreadIds.length > 0;
        },
        cancelSelection() { this.selectionMode = false; this.selectedThreadIds = []; },

        // ===== 一括選択 (全選択 / 全解除) =====
        // 「全選択」は現在のフィルタ条件で見えているスレッドだけを対象にする。
        // (this.threads = 受信箱 / 保留 / 完了 等のステータスタブ + ルーム + 検索を適用済みの集合)
        // これを on にすると selectionMode も自動で on になり、bulk-action-bar が出る。
        get allVisibleThreadsSelected() {
            if (!Array.isArray(this.threads) || this.threads.length === 0) return false;
            const set = new Set(this.selectedThreadIds);
            return this.threads.every(t => set.has(t.id));
        },
        // 全選択トグル. 既に全部選ばれていれば全解除する.
        toggleSelectAllVisible() {
            if (!Array.isArray(this.threads) || this.threads.length === 0) return;
            if (this.allVisibleThreadsSelected) {
                // 全解除 (選択モードは継続するか? ここでは継続。 cancelSelection を呼びたい場合はそれを使う)
                this.selectedThreadIds = [];
            } else {
                this.selectedThreadIds = this.threads.map(t => t.id);
                this.selectionMode = true; // 念のため
            }
        },

        // ツールバーの「複数選択」ボタン用。
        // - on の時は cancelSelection と同じく解除
        // - off の時は selectionMode を on にする (まだ何も選択されていない状態でメニューを開ける)
        toggleSelectionMode() {
            if (this.selectionMode) {
                this.cancelSelection();
            } else {
                this.selectionMode = true;
                // selectedThreadIds は空のまま。 ユーザはチェックボックスで選択を始める。
            }
        },

        async updateSelectedStatus(status) {
            try {
                // ★ Undo 用に各スレッドの旧 status を記録 (バルク逆操作で復元)
                const prevStatuses = this.selectedThreadIds.map(id => {
                    const t = this.threads.find(t => t.id === id);
                    return { id, status: t?.status };
                }).filter(x => x.status !== undefined && x.status !== status);

                this.selectedThreadIds.forEach(id => {
                    const thread = this.threads.find(t => t.id === id);
                    if (thread) thread.status = status;
                });
                let hasError = false;
                for (let id of this.selectedThreadIds) {
                    const res = await fetch(`/threads/${id}/status`, { method: 'PUT', headers: this.jsonHeaders(), body: JSON.stringify({ status }) });
                    if (!res.ok) hasError = true;
                }
                if (hasError) throw new Error('Some updates failed');
                this.toast(`${this.selectedThreadIds.length}件のステータスを更新しました`, 'success');

                // Undo: 各スレッドを元 status に戻す
                if (prevStatuses.length > 0) {
                    this._pushUndoAction(
                        `${prevStatuses.length} 件のステータス変更`,
                        async () => {
                            for (const { id, status: oldStatus } of prevStatuses) {
                                await fetch(`/threads/${id}/status`, {
                                    method: 'PUT',
                                    headers: this.jsonHeaders(),
                                    body: JSON.stringify({ status: oldStatus }),
                                });
                            }
                            await this.loadThreads();
                            this.loadEmailRooms();
                        }
                    );
                }

                this.cancelSelection(); await this.loadThreads();
                // 件数バッジをリアルタイム更新 (一括ステータス変更後)
                this.loadEmailRooms();
            } catch(e) {
                this.toast('更新に失敗しました', 'error');
                await this.loadThreads();
            }
        },

        async batchPinSelected(pinStatus) {
            try {
                this.selectedThreadIds.forEach(id => {
                    const thread = this.threads.find(t => t.id === id);
                    if (thread) thread.is_pinned = pinStatus;
                });
                let hasError = false;
                for (let id of this.selectedThreadIds) {
                    const res = await fetch(`/threads/${id}/pin`, { method: 'POST', headers: this.jsonHeaders(), body: JSON.stringify({ is_pinned: pinStatus }) });
                    if (!res.ok) hasError = true;
                }
                if (hasError) throw new Error('Some updates failed');
                this.toast(pinStatus ? 'ピン留めしました' : 'ピン留めを解除しました', 'success');
                this.cancelSelection(); await this.loadThreads();
            } catch(e) {
                this.toast('更新に失敗しました', 'error');
                await this.loadThreads();
            }
        },

        async batchDeleteSelected() {
            if (!confirm('選択したメールを削除しますか？')) return;
            try {
                let hasError = false;
                for (let id of this.selectedThreadIds) {
                    const res = await fetch(`/threads/${id}`, { method: 'DELETE', headers: this.jsonHeaders() });
                    if (!res.ok) hasError = true;
                }
                if (hasError) throw new Error('Some deletes failed');
                this.toast(`${this.selectedThreadIds.length}件削除しました`, 'success');
                this.cancelSelection(); await this.loadThreads();
                // 件数バッジをリアルタイム更新 (一括削除後)
                this.loadEmailRooms();
                // ルーム選択中ならバンドル先一覧も再フェッチ (削除されたスレッドをチップから消す)
                if (this.emailRoomFilterId && this.emailRoomFilterId !== 'all' && this.emailRoomFilterId !== 'none') {
                    this.loadEmailRoomBundledThreads();
                }
            } catch(e) {
                this.toast('削除に失敗しました', 'error');
                await this.loadThreads();
            }
        },

        mergeSelected() {
            // 防御チェック: 2 件未満は中断
            if (!Array.isArray(this.selectedThreadIds) || this.selectedThreadIds.length < 2) {
                this.toast('マージするには 2 件以上のスレッドを選択してください', 'error');
                return;
            }
            console.log('[merge] mergeSelected called. selectedThreadIds=', this.selectedThreadIds);

            // 候補スレッドのスナップショット
            const ts = (this.threads || []);
            this.mergeCandidates = this.selectedThreadIds.map(id => {
                const t = ts.find(x => x.id === id);
                return {
                    id,
                    subject:       t?.subject || '(件名なし)',
                    last_email_at: t?.last_email_at || '',
                };
            });
            this.mergeTargetId = this.selectedThreadIds[0];
            this.mergeModalOpen = true;

            // 過去に Alpine の x-show / x-if + Tailwind 任意値 + .rice-modal クラスの
            // どれもがモーダルを表示できなかったため、最終手段として
            //   バニラ JS で document.body にモーダル DOM を直接 append する.
            // Alpine の reactivity が壊れていても、 CSS が壊れていても、
            // この経路だけは確実に画面に出る.
            this._renderMergeModalVanilla();
        },

        // バニラ JS によるマージモーダル DOM 構築・差し替え.
        // すでに表示中なら削除して再構築 (state 変化に追従).
        _renderMergeModalVanilla() {
            const EXISTING_ID = 'rice-merge-modal-vanilla';
            // 既存があれば削除 (再描画)
            const old = document.getElementById(EXISTING_ID);
            if (old) old.remove();

            const overlay = document.createElement('div');
            overlay.id = EXISTING_ID;
            overlay.setAttribute('style',
                'position:fixed;top:0;left:0;right:0;bottom:0;z-index:999999;' +
                'display:flex;align-items:center;justify-content:center;padding:16px;' +
                'background:rgba(0,0,0,0.55);'
            );
            // 背景クリックで閉じる
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.remove();
                    this.mergeModalOpen = false;
                }
            });

            const modal = document.createElement('div');
            modal.setAttribute('style',
                'background:#ffffff;width:520px;max-width:94vw;max-height:85vh;' +
                'border-radius:14px;box-shadow:0 24px 60px rgba(0,0,0,0.32);' +
                'display:flex;flex-direction:column;overflow:hidden;'
            );

            // ===== ヘッダ =====
            const head = document.createElement('div');
            head.setAttribute('style',
                'display:flex;align-items:flex-start;gap:12px;padding:16px 18px;' +
                'border-bottom:1px solid #f1f5f9;background:#eff6ff;'
            );
            head.innerHTML = `
                <div style="width:40px;height:40px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:#dbeafe;color:#2563eb;flex-shrink:0;font-size:16px;">
                    <i class="fas fa-object-group"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <h3 style="margin:0;font-size:14px;font-weight:800;color:#0f172a;">ベースとなるスレッドを選択</h3>
                    <p style="margin:2px 0 0;font-size:11px;color:#64748b;">選択したスレッドの件名がマージ後のスレッド名になります。</p>
                </div>
            `;
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.setAttribute('style', 'background:transparent;border:0;color:#94a3b8;cursor:pointer;padding:4px 6px;font-size:14px;');
            closeBtn.title = '閉じる';
            closeBtn.innerHTML = '<i class="fas fa-times"></i>';
            closeBtn.addEventListener('click', () => {
                overlay.remove();
                this.mergeModalOpen = false;
            });
            head.appendChild(closeBtn);
            modal.appendChild(head);

            // ===== ボディ (候補一覧) =====
            const body = document.createElement('div');
            body.setAttribute('style', 'padding:14px 18px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:8px;');

            const renderCards = () => {
                body.innerHTML = '';
                if (!this.mergeCandidates || this.mergeCandidates.length === 0) {
                    body.innerHTML = '<p style="text-align:center;color:#9ca3af;font-size:12px;padding:16px;"><i class="fas fa-exclamation-circle" style="margin-right:4px;"></i>マージ候補がありません</p>';
                    return;
                }
                this.mergeCandidates.forEach(c => {
                    const isSelected = this.mergeTargetId === c.id;
                    const card = document.createElement('div');
                    card.setAttribute('style',
                        'padding:12px 14px;border-radius:10px;border:2px solid;' +
                        'cursor:pointer;display:flex;align-items:center;justify-content:space-between;' +
                        'gap:12px;transition:all .15s;' +
                        (isSelected
                            ? 'background:#eff6ff;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.15);'
                            : 'background:#f9fafb;border-color:#e5e7eb;')
                    );
                    const subjectSafe = (c.subject || '').replace(/[&<>"']/g, ch => ({
                        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                    }[ch]));
                    card.innerHTML = `
                        <div style="min-width:0;flex:1;">
                            <p style="margin:0;font-size:10px;font-weight:900;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;">ID: ${c.id}${c.last_email_at ? ' / ' + c.last_email_at : ''}</p>
                            <p style="margin:2px 0 0;font-size:13px;font-weight:700;color:#1f2937;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${subjectSafe}</p>
                        </div>
                        <div style="flex-shrink:0;width:22px;height:22px;border-radius:50%;border:2px solid;display:flex;align-items:center;justify-content:center;${isSelected ? 'border-color:#3b82f6;background:#3b82f6;color:#fff;' : 'border-color:#d1d5db;'}">
                            ${isSelected ? '<i class="fas fa-check" style="font-size:10px;"></i>' : ''}
                        </div>
                    `;
                    card.addEventListener('click', () => {
                        this.mergeTargetId = c.id;
                        renderCards();
                        updateExecuteButton();
                    });
                    body.appendChild(card);
                });
            };
            modal.appendChild(body);

            // ===== フッタ =====
            const foot = document.createElement('div');
            foot.setAttribute('style', 'padding:12px 18px;border-top:1px solid #f1f5f9;background:#f8fafc;display:flex;justify-content:flex-end;gap:8px;');

            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.setAttribute('style', 'background:#ffffff;border:1px solid #e2e8f0;color:#475569;padding:8px 18px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;');
            cancelBtn.textContent = 'キャンセル';
            cancelBtn.addEventListener('click', () => {
                overlay.remove();
                this.mergeModalOpen = false;
            });

            const executeBtn = document.createElement('button');
            executeBtn.type = 'button';
            executeBtn.innerHTML = '<i class="fas fa-check" style="margin-right:4px;"></i>マージを実行';
            const updateExecuteButton = () => {
                const enabled = !!this.mergeTargetId;
                executeBtn.disabled = !enabled;
                executeBtn.setAttribute('style',
                    'border:0;color:#fff;padding:8px 18px;border-radius:8px;font-size:12px;font-weight:800;cursor:pointer;' +
                    (enabled ? 'background:#2563eb;' : 'background:#9ca3af;cursor:not-allowed;opacity:0.6;')
                );
            };
            executeBtn.addEventListener('click', async () => {
                executeBtn.disabled = true;
                try {
                    await this.executeMerge();
                } finally {
                    // モーダルは executeMerge 内で this.mergeModalOpen = false にしてるが、
                    // バニラ DOM は連動しないので明示削除
                    overlay.remove();
                }
            });

            foot.appendChild(cancelBtn);
            foot.appendChild(executeBtn);
            modal.appendChild(foot);

            overlay.appendChild(modal);
            document.body.appendChild(overlay);
            renderCards();
            updateExecuteButton();

            // Esc キーで閉じる
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    overlay.remove();
                    this.mergeModalOpen = false;
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);

            console.log('[merge] vanilla DOM modal appended to body');
        },

        async executeMerge() {
            const targetId = this.mergeTargetId;
            if (!targetId) { this.toast('ベースとなるスレッドを選択してください', 'error'); return; }
            const sourceIds = (this.selectedThreadIds || []).filter(id => id !== targetId);
            if (sourceIds.length === 0) {
                this.toast('マージ元のスレッドが選ばれていません (ベースと別のスレッドを 1 件以上選択してください)', 'error');
                return;
            }

            let okCount = 0;
            const failures = [];
            // ★ Undo 用に「成功した merge」の id 一覧を覚える
            const createdMergeIds = [];
            for (let id of sourceIds) {
                try {
                    const res = await fetch(`/threads/${targetId}/merge`, {
                        method: 'POST',
                        headers: this.jsonHeaders(),
                        body: JSON.stringify({ merge_thread_id: id })
                    });
                    let data = null;
                    try { data = await res.json(); } catch (_) {}
                    if (res.ok) {
                        okCount++;
                        if (data && data.merge_id) createdMergeIds.push(data.merge_id);
                    } else {
                        const msg = (data && (data.message || data.error))
                            || `HTTP ${res.status}`;
                        failures.push({ id, msg });
                    }
                } catch (e) {
                    failures.push({ id, msg: '通信エラー: ' + (e.message || '') });
                }
            }

            this.mergeModalOpen = false;
            this.cancelSelection();
            await this.loadThreads();
            // ベーススレッドを開き直して反映を可視化
            try { await this.loadThread(targetId); } catch (_) {}

            // Undo: 作成した ThreadMerge を全て DELETE
            if (createdMergeIds.length > 0) {
                this._pushUndoAction(
                    `${createdMergeIds.length} 件のマージ`,
                    async () => {
                        for (const mid of createdMergeIds) {
                            await fetch(`/thread-merges/${mid}`, {
                                method: 'DELETE',
                                headers: this.jsonHeaders(),
                            });
                        }
                        await this.loadThreads();
                        if (this.selectedThreadId) {
                            try { await this.loadThread(this.selectedThreadId); } catch (_) {}
                        }
                    }
                );
            }

            if (failures.length === 0) {
                this.toast(`${okCount} 件をマージしました`, 'success');
            } else if (okCount > 0) {
                // 部分成功 — 失敗理由を見せる
                const detail = failures.map(f => `#${f.id}: ${f.msg}`).slice(0, 3).join('\n');
                this.toast(`${okCount} 件マージ、${failures.length} 件失敗\n${detail}`, 'error');
            } else {
                const detail = failures.map(f => `#${f.id}: ${f.msg}`).slice(0, 3).join('\n');
                this.toast(`マージに失敗しました\n${detail}`, 'error');
            }
        },

        async unmergeThread(mergeId) {
            if (!confirm('マージを解除しますか？')) return;
            // ★ Undo 用に、解除する merge の source / target を事前に拾っておく.
            //   現在開いているスレッド = target. selectedThreadMerges から source 側 (= source_thread_id_original) を得る.
            let undoSourceId = null;
            let undoTargetId = this.selectedThreadId;
            try {
                const merges = this.selectedThreadMerges || this.threadMerges || [];
                const m = merges.find(x => Number(x.id) === Number(mergeId));
                if (m) undoSourceId = m.source_thread_id_original || m.source_thread_id || null;
            } catch (_) {}
            try {
                const res = await fetch(`/thread-merges/${mergeId}`, { method: 'DELETE', headers: this.jsonHeaders() });
                if (res.ok) {
                    this.toast('マージを解除しました', 'success');
                    await this.loadThread(this.selectedThreadId);
                    await this.loadThreads();
                    // Undo: 同じ source/target で再マージ
                    if (undoSourceId && undoTargetId) {
                        this._pushUndoAction(
                            `マージ解除 #${undoSourceId}`,
                            async () => {
                                const r = await fetch(`/threads/${undoTargetId}/merge`, {
                                    method: 'POST',
                                    headers: this.jsonHeaders(),
                                    body: JSON.stringify({ merge_thread_id: undoSourceId })
                                });
                                if (!r.ok) throw new Error('再マージに失敗しました');
                                await this.loadThreads();
                                try { await this.loadThread(undoTargetId); } catch (_) {}
                            }
                        );
                    }
                } else {
                    this.toast('解除に失敗しました', 'error');
                }
            } catch(e) { this.toast('解除に失敗しました', 'error'); }
        },

        // 単一スレッドを削除 (三点リーダから — 現在開いているスレッド)
        async deleteSelectedThread() {
            if (!this.selectedThreadId) return;
            return this.deleteThreadById(this.selectedThreadId, this.selectedThread?.subject);
        },

        // 「迷惑メール」操作 (三点リーダから)
        // - まず「本当に迷惑メールにするか」の確認
        // - その後「送信元アドレスをブロックルールにも追加するか」を別途確認
        // どちらの確認も [キャンセル] でその後の処理は止まる/省略される.
        async markSelectedAsSpam() {
            if (!this.selectedThreadId) return;
            const subject = this.selectedThread?.subject || '(件名なし)';
            const from = this.selectedThread?.latest_email?.from_address || '';
            const threadId = this.selectedThreadId;

            // 1) 迷惑メール化の本確認. キャンセルで完全中止.
            const confirmMsg = `このスレッドを迷惑メールに振り分けます。\n\n`
                + `件名: ${subject}\n`
                + (from ? `差出人: ${from}\n` : '')
                + `\n[OK] で続行 / [キャンセル] で操作を中止します.`;
            if (!confirm(confirmMsg)) return;

            try {
                // スレッドを spam にマーク (ルール自動登録は無し: 後続モーダルで自由に追加させる)
                const res = await fetch(`/threads/${threadId}/mark-spam`, {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({ add_rule: false }),
                });
                if (!res.ok) { this.toast('迷惑メール振り分けに失敗しました', 'error'); return; }
                this.toast('迷惑メールに振り分けました', 'success');
                if (this.selectedThread) this.selectedThread.status = 'spam';
                // ★ モーダルのチップ用に「latest_email を含む thread オブジェクト」を構築して渡す.
                //   threadEmails が降順ソートされている前提なので [0] が最新.
                //   loadThreads が走ると this.threads から消えるため、消える前にキャプチャする手も使えるが
                //   selectedThread + threadEmails から組み立てる方が確実.
                let threadObjForFollowup = null;
                try {
                    const e0 = (this.threadEmails || [])[0];
                    if (e0) {
                        threadObjForFollowup = {
                            id: threadId,
                            subject: this.selectedThread?.subject || e0.subject,
                            latest_email: {
                                subject:      e0.subject || '',
                                from_address: e0.from_address || '',
                                from_label:   e0.from_label || '',
                                to_address:   e0.to_address || '',
                                cc:           e0.cc || '',
                                bcc:          e0.bcc || '',
                            },
                        };
                    }
                } catch (_) {}
                await this.loadThreads();
                // ★ ルームと同じ思想の「ブロックルール追加しますか?」モーダルを開く.
                //   ここで件名 / 宛先 / Cc / Bcc / 本文キーワード等を quick-fill から選べる.
                //   スキップで閉じればルール無しでもスレッドは spam のまま.
                this.openSpamRuleFollowup(threadId, threadObjForFollowup);
            } catch (e) {
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            }
        },

        async unmarkSelectedAsSpam() {
            if (!this.selectedThreadId) return;
            try {
                const res = await fetch(`/threads/${this.selectedThreadId}/unmark-spam`, {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                });
                if (!res.ok) { this.toast('迷惑メール解除に失敗しました', 'error'); return; }
                this.toast('迷惑メールから解除しました', 'success');
                if (this.selectedThread) this.selectedThread.status = 'inbox';
                await this.loadThreads();
            } catch (e) {
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            }
        },

        // 選択中スレッドを一括で迷惑メールに振り分ける。
        // - 1 件版 (markSelectedAsSpam) と違い、ブロックルール登録 (add_rule) の
        //   送信元別ダイアログを 1 件ずつ出すと煩雑なので、
        //   「全件まとめてルール登録するか?」を最初に 1 回だけ聞く。
        // - サーバ側 /threads/{thread}/mark-spam を逐次呼び出し
        //   (失敗したものは件数を集計してまとめて 1 度だけ通知)。
        async bulkMarkSpam() {
            const ids = (this.selectedThreadIds || []).slice();
            if (ids.length === 0) return;
            if (!confirm(`選択中の ${ids.length} 件を迷惑メールに振り分けます。よろしいですか？`)) return;
            const addRule = confirm(
                '各メールの送信元アドレスを今後のブロックリストにも登録しますか?\n' +
                '[OK] → 各スレッドの送信元アドレスをブロックルールとして自動登録\n' +
                '[キャンセル] → 今回のスレッドだけ迷惑メール扱いにする'
            );

            let ok = 0, ng = 0, addedRules = 0;
            const headers = this.jsonHeaders();
            for (const id of ids) {
                try {
                    const res = await fetch(`/threads/${id}/mark-spam`, {
                        method: 'POST',
                        headers,
                        body: JSON.stringify({ add_rule: addRule, rule_type: 'sender_address' }),
                    });
                    if (res.ok) {
                        ok++;
                        try {
                            const d = await res.json();
                            if (d.created_rule) addedRules++;
                        } catch (_) {}
                    } else {
                        ng++;
                    }
                } catch (_) {
                    ng++;
                }
            }

            // ローカル更新 → 完了通知 → 一覧の再読み込みで spam を除外
            this.cancelSelection();
            if (this.selectedThread && ids.includes(this.selectedThreadId)) {
                this.selectedThread.status = 'spam';
            }
            const msgParts = [`${ok}件を迷惑メールに振り分けました`];
            if (addedRules > 0) msgParts.push(`(${addedRules}件のブロックルールを追加)`);
            if (ng > 0) msgParts.push(`/ ${ng}件は失敗`);
            this.toast(msgParts.join(' '), ng > 0 ? 'error' : 'success');
            await this.loadThreads();
            // 件数バッジをリアルタイム更新 (一括迷惑メール振り分け後)
            this.loadEmailRooms();
        },

        // ====== メール一覧の行ホバー用クイックアクション ======
        //
        // updateThreadStatus / markSelectedAsSpam は「現在開いているスレッド」を対象にした
        // 既存のメソッド. リストのホバーから任意のスレッドを対象にしたい場合は、
        // この quick* ラッパーを使う.
        // 一覧再フェッチを抑えたいので、楽観的に thread.status をその場で書き換えた上で
        // サーバ更新する.
        async quickUpdateStatus(thread, status) {
            if (!thread || !thread.id) return;
            const oldStatus = thread.status;
            const wasSelected = this.selectedThreadId === thread.id;

            // 「次に開くべきスレッド」を変更前に確定しておく.
            // 完了/保留/迷惑等にして現在のタブから消えた時、自動で次の (なければ前の) スレッドへ飛ぶため.
            // ルーム絞り込み中で this.threads が空 / 部分集合のケースでも動くよう
            // emailRoomBundledThreads にもフォールバックする.
            let nextThreadIdAfterChange = null;
            if (wasSelected) {
                const navList = (this.threads && this.threads.length > 0)
                    ? this.threads
                    : (this.emailRoomFilterId && this.emailRoomFilterId !== 'all' && this.emailRoomFilterId !== 'none'
                        ? (this.emailRoomBundledThreads || [])
                        : []);
                const idx = navList.findIndex(t => t.id === thread.id);
                if (idx !== -1) {
                    if (idx < navList.length - 1) nextThreadIdAfterChange = navList[idx + 1].id;
                    else if (idx > 0) nextThreadIdAfterChange = navList[idx - 1].id;
                }
            }

            thread.status = status;  // 楽観的更新 (即座にアイコンが入れ替わる)
            try {
                const res = await fetch(`/threads/${thread.id}/status`, {
                    method: 'PUT',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({ status }),
                });
                if (!res.ok) {
                    thread.status = oldStatus;  // ロールバック
                    this.toast('ステータス更新に失敗しました', 'error');
                    return;
                }
                // 現在開いているスレッドなら selectedThread 側も同期
                if (this.selectedThread && this.selectedThread.id === thread.id) {
                    this.selectedThread.status = status;
                }
                const label = this.statusLabels[status] || status;
                this.toast(`「${label}」に変更しました`, 'success');

                // 現在のタブから外れるステータス変更だと一覧から消えるので再フェッチ.
                // (allStatusMode が ON ならどのステータスでも一覧に残るので不要)
                const willDisappear = !this.allStatusMode && status !== this.leftTab;
                if (willDisappear) {
                    await this.loadThreads(true);
                }

                // 件数バッジ (received_email_count) をリアルタイム更新.
                // completed への変更 / completed から復帰 / ルームへの所属変化など、
                // どんな状況でも変わり得るのでステータス変更時は常に再取得する.
                // (バッジは completed を除外集計しているため特に effect が大きい)
                this.loadEmailRooms();

                // ★ Gmail/Spark 流: ステータス変更ショートカット直後は **常に次のスレッドへ自動ジャンプ**.
                //    旧実装は「現スレッドが一覧から消えた時だけ」ジャンプしていたが、
                //    ルーム絞り込み中は allStatusMode が強制 ON なのでスレッドが消えず、
                //    結果としてジャンプしないバグになっていた。
                //    変更後: wasSelected かつ次のスレッドが特定できていれば常に飛ぶ。
                //    (受信→受信のような実質変更なしの場合も飛ぶが、 E/H/S を連打する用途に合致)
                if (wasSelected && nextThreadIdAfterChange != null) {
                    try {
                        await this.loadThread(nextThreadIdAfterChange);
                        this.scrollThreadIntoView(nextThreadIdAfterChange);
                    } catch (_) { /* navigation 失敗は致命的でないので握り潰す */ }
                }
            } catch (e) {
                thread.status = oldStatus;
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            }
        },

        // リストホバー / S ショートカット からの迷惑メール振り分け.
        //   既存の markSelectedAsSpam は「現在開いているスレッド」専用だったので、
        //   任意の thread を引数で受ける版.
        // ★ ユーザ要望「S 押下時もルームと同じくフィルタルール設定ウィンドウを出して」に応えるため、
        //   旧 2 つ目の confirm() ダイアログを廃止し、ルームと同じ spamRuleFollowup モーダルを開く.
        async quickMarkSpam(thread) {
            if (!thread || !thread.id) return;
            const fromAddr = thread.latest_email?.from_address || '';
            const subject = thread.subject || '(件名なし)';

            // ステップ 1: そもそも迷惑メールにするかの確認.
            //   ここで [キャンセル] を押したら何もせず終了.
            const confirmMsg = `このスレッドを迷惑メールに振り分けます。\n\n`
                + `件名: ${subject}\n`
                + (fromAddr ? `差出人: ${fromAddr}\n` : '')
                + `\n[OK] で続行 / [キャンセル] で操作を中止します.`;
            if (!confirm(confirmMsg)) return;

            const oldStatus = thread.status;
            const wasSelected = this.selectedThreadId === thread.id;

            // 「次に開くべきスレッド」を変更前に確定
            let nextThreadIdAfterChange = null;
            if (wasSelected) {
                const navList = (this.threads && this.threads.length > 0)
                    ? this.threads
                    : (this.emailRoomFilterId && this.emailRoomFilterId !== 'all' && this.emailRoomFilterId !== 'none'
                        ? (this.emailRoomBundledThreads || [])
                        : []);
                const idx = navList.findIndex(t => t.id === thread.id);
                if (idx !== -1) {
                    if (idx < navList.length - 1) nextThreadIdAfterChange = navList[idx + 1].id;
                    else if (idx > 0) nextThreadIdAfterChange = navList[idx - 1].id;
                }
            }

            thread.status = 'spam';
            try {
                // ステップ 2: サーバへ「spam にする (ルール自動登録は無し)」を送る.
                //   ルールの追加は後続モーダルで好きな条件 (件名/CC/宛先 など) で自由に追加できる.
                const res = await fetch(`/threads/${thread.id}/mark-spam`, {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({ add_rule: false }),
                });
                if (!res.ok) {
                    thread.status = oldStatus;
                    this.toast('迷惑メール振り分けに失敗しました', 'error');
                    return;
                }
                if (this.selectedThread && this.selectedThread.id === thread.id) {
                    this.selectedThread.status = 'spam';
                }
                this.toast('迷惑メールに振り分けました', 'success');

                // spam タブ以外なら、迷惑メールに移ったスレッドはリストから消えるべきなので再フェッチ
                if (this.leftTab !== 'spam') await this.loadThreads(true);
                // 件数バッジをリアルタイム更新
                this.loadEmailRooms();

                // ステップ 3: ★ ルームと同じ思想の「ブロックルール追加しますか?」モーダルを開く.
                //   quick-fill (From / From ドメイン / To / Cc / Bcc / 件名) から自由に条件を組める.
                //   このモーダルでは「次のスレッドへ自動ジャンプ」はしない (ユーザがモーダルを閉じてから動く).
                //   モーダルをスキップで閉じた場合のみ、自動ジャンプを行う.
                this._spamFollowupPendingJumpId = (wasSelected && nextThreadIdAfterChange != null) ? nextThreadIdAfterChange : null;
                // thread オブジェクト本体も渡す (list-row 経由なので selectedThread と一致しないことがある).
                // thread.latest_email を含むので、モーダルのクイックフィルチップが確実に描画される.
                this.openSpamRuleFollowup(thread.id, thread);
            } catch (e) {
                thread.status = oldStatus;
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            }
        },

        // リストホバーから迷惑メール解除
        async quickUnmarkSpam(thread) {
            if (!thread || !thread.id) return;
            const oldStatus = thread.status;
            thread.status = 'inbox';
            try {
                const res = await fetch(`/threads/${thread.id}/unmark-spam`, {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                });
                if (!res.ok) {
                    thread.status = oldStatus;
                    this.toast('迷惑メール解除に失敗しました', 'error');
                    return;
                }
                if (this.selectedThread && this.selectedThread.id === thread.id) {
                    this.selectedThread.status = 'inbox';
                }
                this.toast('迷惑メールから解除しました', 'success');
                // spam タブ表示中なら、解除されたスレッドは消えるので再フェッチ
                if (this.leftTab === 'spam') await this.loadThreads(true);
                // 件数バッジをリアルタイム更新
                this.loadEmailRooms();
            } catch (e) {
                thread.status = oldStatus;
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            }
        },

        // 任意のスレッドを ID で削除 (リスト行のホバー削除ボタン用)
        async deleteThreadById(id, subject) {
            if (!id) return;
            const label = subject || '(無題)';
            if (!confirm(`「${label}」を削除します。よろしいですか？\n\n※スレッド内のメールも一緒に削除されます。`)) return;

            const wasSelected = this.selectedThreadId === id;
            // 次のスレッドを変更前に確定. ルーム絞り込み中で this.threads が部分集合の場合は
            // emailRoomBundledThreads にもフォールバックする.
            let nextThreadId = null;
            if (wasSelected) {
                const navList = (this.threads && this.threads.length > 0)
                    ? this.threads
                    : (this.emailRoomFilterId && this.emailRoomFilterId !== 'all' && this.emailRoomFilterId !== 'none'
                        ? (this.emailRoomBundledThreads || [])
                        : []);
                const idx = navList.findIndex(t => t.id === id);
                if (idx !== -1) {
                    if (idx < navList.length - 1) nextThreadId = navList[idx + 1].id;
                    else if (idx > 0) nextThreadId = navList[idx - 1].id;
                }
            }

            try {
                const res = await fetch(`/threads/${id}`, {
                    method: 'DELETE',
                    headers: this.jsonHeaders(),
                });
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    this.toast(data.message || '削除に失敗しました', 'error');
                    return;
                }
                this.toast('メールを削除しました', 'success');

                // 削除したのが現在表示中のスレッドならワークスペースを閉じる (loadThread が成功すれば後で次へ移る)
                if (wasSelected) {
                    this.closeWorkspace();
                }
                // 選択モードなら選択リストからも除外
                if (this.selectedThreadIds.includes(id)) {
                    this.selectedThreadIds = this.selectedThreadIds.filter(x => x !== id);
                    this.selectionMode = this.selectedThreadIds.length > 0;
                }
                await this.loadThreads();
                // ルームに紐付くスレッド一覧も再フェッチ (削除されたスレッドをチップから消す)
                if (this.emailRoomFilterId && this.emailRoomFilterId !== 'all' && this.emailRoomFilterId !== 'none') {
                    this.loadEmailRoomBundledThreads();
                }
                // 件数バッジをリアルタイム更新 (削除されたメール分が減る)
                this.loadEmailRooms();
                // 次のスレッドへ自動ジャンプ.
                // this.threads にあればそれを優先 (画面追従もできる)、無ければ navList の次でも開く.
                if (wasSelected && nextThreadId) {
                    if (this.threads.find(t => t.id === nextThreadId)) {
                        this.loadThread(nextThreadId);
                        this.scrollThreadIntoView(nextThreadId);
                    } else if (this.emailRoomBundledThreads
                               && this.emailRoomBundledThreads.find(t => t.id === nextThreadId)) {
                        // ルームのバンドル先にだけ残っている場合
                        this.loadThread(nextThreadId);
                    }
                }
            } catch (e) {
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            }
        },

        // スレッド内の 1 通を別スレッドへ分離する (★ 削除はしない / メール本体は新スレッドへ移動するだけ).
        // - POST /emails/{id}/detach
        // - 新スレッドが作られて email.thread_id が付け替えられる.
        // - 親スレッドの残メールはそのまま. 親が空になっても自動削除はしない.
        // - 成功後は親スレッドの表示を更新し、ユーザに「分離先スレッドを開く?」を聞く.
        async detachEmailFromThread(email) {
            if (!email || !email.id) return;
            const subjectPreview = (email.subject || '(件名なし)').slice(0, 60);
            const fromAddr = email.from_label || email.from_address || '';
            const msg = `このメールをスレッドから分離して、独立したスレッドに移動します.\n\n`
                + `件名: ${subjectPreview}\n`
                + (fromAddr ? `差出人: ${fromAddr}\n` : '')
                + `\n※ メール本体は削除されません. 新スレッドとして残り、スレッド一覧から開けます.\n`
                + `\n[OK] で実行 / [キャンセル] で中止`;
            if (!confirm(msg)) return;

            try {
                const res = await fetch(`/emails/${email.id}/detach`, {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                });
                if (!res.ok) {
                    let m = '分離に失敗しました';
                    try { const d = await res.json(); if (d.message) m += ': ' + d.message; } catch (_) {}
                    this.toast(m, 'error');
                    return;
                }
                const data = await res.json();
                // 削除ではなく「移動」であることが伝わるトーストにする.
                // 新スレッドのチケット番号があれば表示する (見つけやすくするため).
                const ticket = data.new_thread_ticket_number ? ` (Ticket #${data.new_thread_ticket_number})` : '';
                this.toast(`別スレッド${ticket} に移動しました — 削除されていません`, 'success');

                // ローカル状態: 分離したメールを現スレッド表示から取り除く.
                this.threadEmails = (this.threadEmails || []).filter(e => e.id !== email.id);

                // 一覧側を更新 (新スレッドが追加されている).
                await this.loadThreads();
                if (this.emailRoomFilterId && this.emailRoomFilterId !== 'all' && this.emailRoomFilterId !== 'none') {
                    this.loadEmailRoomBundledThreads();
                }
                this.loadEmailRooms();

                // 親スレッドが空になっていれば閉じる. (削除ではなく「全メール分離した結果」なので明示).
                if (data.original_thread_empty) {
                    this.toast('元スレッドのメールはすべて別スレッドへ移動済', 'success');
                    this.closeWorkspace?.();
                    return;
                }

                // 分離先スレッドを開くか確認 (キャンセル時も新スレッドはスレッド一覧から参照可能).
                if (data.new_thread_id) {
                    const subj = data.new_thread_subject ? `\n件名: ${data.new_thread_subject}` : '';
                    const open = confirm(`分離した新スレッド${ticket} を開きますか?${subj}\n\n[キャンセル] で元スレッドに残ります (新スレッドはスレッド一覧から開けます)`);
                    if (open) {
                        await this.loadThread(data.new_thread_id);
                        this.scrollThreadIntoView?.(data.new_thread_id);
                    }
                }
            } catch (e) {
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            }
        },

        // スレッド内の 1 通だけ削除する.
        // - email.id を DELETE /emails/{id}
        // - 残メールが 0 になればサーバ側で親スレッドも消える (thread_deleted フラグ).
        // - 表示中スレッドが消えた時は次スレッドへ自動ジャンプ.
        async deleteEmailInThread(email) {
            if (!email || !email.id) return;
            const subjectPreview = (email.subject || this.selectedThread?.subject || '(件名なし)').slice(0, 60);
            const msg = `このメール 1 通を削除します. よろしいですか?\n\n件名: ${subjectPreview}\n差出人: ${email.from_label || email.from_address || ''}\n\n※ スレッドに残メールが無くなった場合はスレッド自体も削除されます.\n※ この操作は元に戻せません.`;
            if (!confirm(msg)) return;
            const wasOnlySelectedEmail = (this.threadEmails || []).length <= 1;
            const currentThreadId = this.selectedThreadId;
            try {
                const res = await fetch(`/emails/${email.id}`, {
                    method: 'DELETE',
                    headers: this.jsonHeaders(),
                });
                if (!res.ok) { this.toast('削除に失敗しました', 'error'); return; }
                let data = {};
                try { data = await res.json(); } catch (_) {}

                this.toast('メールを 1 通削除しました', 'success');

                if (data.thread_deleted) {
                    // 親スレッドごと消えたので一覧を再フェッチして次へ送る (deleteThreadById と同じ流儀).
                    await this.loadThreads();
                    if (this.emailRoomFilterId && this.emailRoomFilterId !== 'all' && this.emailRoomFilterId !== 'none') {
                        this.loadEmailRoomBundledThreads();
                    }
                    this.loadEmailRooms();
                    // 残っている次の thread があれば自動で開く. 無ければ workspace を閉じる.
                    const next = (this.threads || []).find(t => t.id !== currentThreadId);
                    if (next) { this.loadThread(next.id); }
                    else { this.closeWorkspace?.(); }
                } else {
                    // スレッドはまだ残っているのでローカル配列から消して再描画.
                    this.threadEmails = (this.threadEmails || []).filter(e => e.id !== email.id);
                    // 一覧側の last_email_at 等を更新するため軽く再フェッチ.
                    await this.loadThreads();
                    this.loadEmailRooms();
                }
            } catch (e) {
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            }
        },

        async togglePin(threadId = null) {
            const id = threadId || this.selectedThreadId;
            if (!id) return;
            try {
                const res = await fetch(`/threads/${id}/pin`, { method: 'POST', headers: this.jsonHeaders() });
                if (!res.ok) { this.toast('ピン留めに失敗しました', 'error'); return; }
                const data = await res.json();
                if (this.selectedThreadId === id && this.selectedThread) this.selectedThread.is_pinned = data.is_pinned;
                // サイドバー側のキャッシュも即時反映 (再読込前に並びを更新)
                const sidebarRow = (this.sidebarThreadList || []).find(t => t.id === id);
                if (sidebarRow) sidebarRow.is_pinned = !!data.is_pinned;
                this.toast(data.is_pinned ? 'ピン留めしました' : 'ピン留めを解除しました', 'success');
                // 中央リスト + サイドバー両方を再取得
                await Promise.all([this.loadThreads(), this.loadSidebarThreads()]);
            } catch(e) { this.toast('ピン留めに失敗しました', 'error'); }
        },

        async updateAssignee(userId, threadId = null) {
            const id = threadId || this.selectedThreadId;
            if (!id) return;
            try {
                const res = await fetch(`/threads/${id}/assignee`, {
                    method: 'PUT',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({ assigned_user_id: userId })
                });
                if (!res.ok) { this.toast('担当者の更新に失敗しました', 'error'); return; }
                if (this.selectedThreadId === id && this.selectedThread) {
                    const user = this.users.find(u => u.id == userId);
                    this.selectedThread.assigned_user_id = userId;
                    this.selectedThread.assignee = user ? { id: user.id, name: user.name } : null;
                }
                this.toast('担当者を更新しました', 'success');
                await this.loadThreads();
            } catch(e) { this.toast('担当者の更新に失敗しました', 'error'); }
        },

        handleScroll() {
            const container = document.getElementById('email-list-container');
            if (!container) return;
            const start = Math.floor(container.scrollTop / this.virtualScroll.rowHeight);
            this.virtualScroll.startIndex = Math.max(0, start - this.virtualScroll.buffer);
            this.virtualScroll.endIndex = Math.min(this.threads.length, start + 15 + this.virtualScroll.buffer);
        },

        updateVirtualViewport() {
            const container = document.getElementById('email-list-container');
            if (container) { this.virtualScroll.viewportHeight = container.offsetHeight || 600; this.handleScroll(); }
        },

        get visibleThreads() { return this.threads.slice(this.virtualScroll.startIndex, this.virtualScroll.endIndex); },
        get totalListHeight() { return this.threads.length * this.virtualScroll.rowHeight; },
        get listPaddingTop() { return this.virtualScroll.startIndex * this.virtualScroll.rowHeight; },

        setLeftTab(tab) {
            this.leftTab = tab;
            // ステータスタブを選んだら「全ステータス表示」は自動OFF
            if (this.allStatusMode) {
                this.allStatusMode = false;
                try { localStorage.setItem('allStatusMode', JSON.stringify(false)); } catch (e) {}
            }
            // ゴミ箱タブは専用ビュー (/trash) を読みに行く. 通常のスレッド一覧 API は使わない.
            if (tab === 'trash') {
                this.loadTrash();
            } else {
                this.loadThreads();
            }
        },
        goToPrevThread() {
            const idx = this.threads.findIndex(t => t.id === this.selectedThreadId);
            if (idx > 0) {
                const nextId = this.threads[idx - 1].id;
                this.loadThread(nextId);
                // 仮想スクロールリストでも選択行を追従させる (画面外なら可視範囲へ)
                this.scrollThreadIntoView(nextId);
            }
        },
        goToNextThread() {
            const idx = this.threads.findIndex(t => t.id === this.selectedThreadId);
            if (idx !== -1 && idx < this.threads.length - 1) {
                const nextId = this.threads[idx + 1].id;
                this.loadThread(nextId);
                this.scrollThreadIntoView(nextId);
            } else {
                this.closeWorkspace();
            }
        },

        /**
         * メール一覧の仮想スクロールで、指定スレッドが現在の可視範囲外なら
         * 行を可視範囲に入れるだけスクロールする (= 「追従」)。
         * - 既に可視範囲内なら何もしない (無駄なジャンプを避ける)
         * - 上にはみ出している場合: その行が一番上に来る位置へ
         * - 下にはみ出している場合: その行が一番下に来る位置へ
         * - スクロール後に handleScroll() を呼んで visibleThreads を再計算
         *   (smooth scroll 中でも DOM が描画されるよう、scroll イベントに任せる)
         */
        scrollThreadIntoView(threadId) {
            if (threadId == null) return;
            const idx = this.threads.findIndex(t => t.id === threadId);
            if (idx < 0) return;
            const container = document.getElementById('email-list-container');
            if (!container) return;

            const rowH        = this.virtualScroll.rowHeight || 128;
            const rowTop      = idx * rowH;
            const rowBottom   = rowTop + rowH;
            const viewTop     = container.scrollTop;
            const viewBottom  = viewTop + (container.clientHeight || container.offsetHeight || 0);

            // 行の上下に少し余白 (rowH 分) を取って「ギリギリ可視」を回避
            const margin = Math.min(rowH, 24);

            let nextScrollTop = null;
            if (rowTop < viewTop + margin) {
                // 上にはみ出 → 行頭が見える位置
                nextScrollTop = Math.max(0, rowTop - margin);
            } else if (rowBottom > viewBottom - margin) {
                // 下にはみ出 → 行末が見える位置
                nextScrollTop = rowBottom - (container.clientHeight || container.offsetHeight || 0) + margin;
            }
            if (nextScrollTop === null) return; // 既に可視

            // smooth scroll は仮想リスト的に途中行が空っぽに見える瞬間が出るので
            // 動きの大きさで切り替え: 行 3 つ以内のジャンプは smooth、それ以上は瞬時
            const delta = Math.abs(nextScrollTop - viewTop);
            const behavior = delta > rowH * 3 ? 'auto' : 'smooth';
            try {
                container.scrollTo({ top: nextScrollTop, behavior });
            } catch (_) {
                container.scrollTop = nextScrollTop;
            }
            // スクロール直後に可視範囲を再計算 (smooth の場合は scroll イベントが
            // 連続発火するので handleScroll() 側で逐次更新される。瞬時の場合の保険)
            this.$nextTick(() => this.handleScroll());
        },

        // 新規作成ウィンドウを開く
        openCompose() {
            const win = window.open('{{ route('emails.composeWindow') }}', '_blank');
            if (!win) {
                this.toast('ポップアップがブロックされました。ブラウザの設定を確認してください。', 'error');
            }
        },

        // 返信 / 全員返信ウィンドウを開く
        openReplyForEmail(email, all = false) {
            if (!email || !email.id) return;
            const url = `/emails/${email.id}/reply-window${all ? '?all=1' : ''}`;
            const win = window.open(url, '_blank');
            if (!win) {
                this.toast('ポップアップがブロックされました。ブラウザの設定を確認してください。', 'error');
            }
        },

        // 転送 (Forward). 別ウィンドウで compose-window を mode='forward' で開く.
        // 元メールの引用 + 添付継承候補 + Fwd: 件名がコントローラ側で組み立て済み.
        openForwardForEmail(email) {
            if (!email || !email.id) return;
            const url = `/emails/${email.id}/forward-window`;
            const win = window.open(url, '_blank');
            if (!win) {
                this.toast('ポップアップがブロックされました。ブラウザの設定を確認してください。', 'error');
            }
        },

        // ワークスペースを閉じる (スレッド閲覧の終了のみ)
        closeWorkspace() {
            this.selectedThread = null;
            this.selectedThreadId = null;
            // 横断ナビ: スレッド未選択状態に
            try { localStorage.removeItem('currentThreadId'); } catch (_) {}
            this.threadEmails = [];
            this.threadMerges = [];
            this.expandedEmailIds = [];
            this.pendingApprovals = [];
            this.threadBundledRooms = { shared: [], private: [] };
            // スレッド内検索クエリもクリア (次に別スレッドを開いた時に引き継がないように)
            this.threadInnerSearchQuery = '';
            // 個別メールフォーカスもリセット
            this._focusedEmailId = null;
            this.chatOpen = false;
            this.chatComments = [];
            this.chatInput = '';
            this.chatPendingFiles = [];
            this.chatScope = { kind: 'thread', email_id: null, email_subject: '', email_from: '' };
            this.lastEmailScope = null;
            this.chatScrolledUp = false;
            this.stopChatPolling();
            this.closeMention();
        },

        // ============= AI モデルピッカー (共通) =============
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
                // 初期値: なければ provider 用の先頭モデル
                if (!this.aiModel) {
                    const list = this.aiCurrentModels;
                    if (list.length > 0) this.aiModel = list[0].id || list[0];
                }
            } catch (e) {
                // 失敗時はサイレント (空配列のまま)
            } finally {
                this.aiPickerLoading = false;
            }
        },
        setAiProvider(p) {
            this.aiProvider = p;
            const list = this.aiCurrentModels;
            this.aiModel = list.length > 0 ? (list[0].id || list[0]) : '';
        },

        // ============= ナレッジ登録 (メール本文) =============
        // 引数なし → スレッドの最新メールを使う / 引数あり → そのメールを使う
        // ===== ナレッジ登録モーダル =====
        // 内部 UI は Alpine の reactivity に依存せず、id 指定で直接 DOM を更新する方式.
        // 過去にモーダル全体が x-show + Tailwind 任意値 で表示されない / 状態は変わるが
        // UI が反応しない事象を何度も踏んだため、最後まで安定するこの方式を採用.
        //
        // セクション:
        //   #riceKnowledgeLoadingSection : 読み込み中スピナー
        //   #riceKnowledgeErrorSection   : 取得失敗時のメッセージ
        //   #riceKnowledgeFormSection    : 編集フォーム本体
        //   #riceKnowledgeTitle / Content / Collection : 入力フィールド
        //   #riceKnowledgeSubmitBtn / Spinner / Label   : 送信ボタン
        async openKnowledgeRegister(emailArg = null) {
            let target = emailArg;
            if (!target?.id) {
                const emails = this.threadEmails || [];
                if (emails.length === 0) { this.toast('スレッドにメールがありません', 'error'); return; }
                target = emails.find(e => e.id) || emails[0];
            }
            if (!target?.id) { this.toast('登録対象のメールが見つかりません', 'error'); return; }

            // 右側スライドインで開く
            if (window.riceOpenKnowledgePanel) window.riceOpenKnowledgePanel();
            // セクションの可視状態を初期化: ローディング表示のみ.
            this._setKnowledgeSections({ loading: true });
            // 登録ボタンの label / spinner も既定状態に戻す.
            this._setKnowledgeSubmit({ saving: false });
            // 後で submit する時に必要なので email_id を覚えておく.
            this._knowledgeEmailId = target.id;
            this.knowledgeRegisterOpen = true;

            try {
                const res = await fetch(`/knowledge/from-email/${target.id}`, { headers: { Accept: 'application/json' } });
                if (!res.ok) {
                    this._setKnowledgeSections({ error: 'メール内容を取得できませんでした (HTTP ' + res.status + ')' });
                    return;
                }
                const data = await res.json();
                // フォームを populate
                const titleEl   = document.getElementById('riceKnowledgeTitle');
                const contentEl = document.getElementById('riceKnowledgeContent');
                const colEl     = document.getElementById('riceKnowledgeCollection');
                const piiEl     = document.getElementById('riceKnowledgePiiWarning');
                const countEl   = document.getElementById('riceKnowledgeCharCount');
                if (titleEl)   titleEl.value   = data.default_title || '';
                if (contentEl) contentEl.value = data.editable_content || '';
                if (colEl)     colEl.value     = 'default';
                if (piiEl)     piiEl.textContent = data.suggested_pii_warning || '';
                if (countEl)   countEl.textContent = (contentEl?.value?.length || 0) + ' 字';
                this._setKnowledgeSections({ form: true });
            } catch (e) {
                this._setKnowledgeSections({ error: '通信エラー: ' + (e.message || '') });
            }
        },

        _setKnowledgeSections({ loading = false, error = '', form = false }) {
            const l = document.getElementById('riceKnowledgeLoadingSection');
            const e = document.getElementById('riceKnowledgeErrorSection');
            const f = document.getElementById('riceKnowledgeFormSection');
            if (l) l.style.display = loading ? 'flex' : 'none';
            if (e) {
                e.style.display = error ? 'block' : 'none';
                if (error) e.textContent = error;
            }
            if (f) f.style.display = form ? 'block' : 'none';
        },
        _setKnowledgeSubmit({ saving = false }) {
            const btn = document.getElementById('riceKnowledgeSubmitBtn');
            const sp  = document.getElementById('riceKnowledgeSubmitSpinner');
            const lbl = document.getElementById('riceKnowledgeSubmitLabel');
            if (btn) btn.disabled = saving;
            if (sp)  sp.style.display = saving ? 'inline-block' : 'none';
            if (lbl) lbl.textContent  = saving ? '登録中…' : 'ナレッジに登録';
        },

        // 簡易な PII 自動マスク (本文 textarea を直接書き換える)
        applyMaskHeuristics() {
            const el = document.getElementById('riceKnowledgeContent');
            if (!el) return;
            let text = el.value;
            text = text.replace(/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/g, '[メール]');
            text = text.replace(/(\d{2,4}-\d{2,4}-\d{4})/g, '[電話]');
            text = text.replace(/(\d{3}-\d{4})(?!\d)/g, '[郵便番号]');
            text = text.replace(/([０-９]{2,4}[\-－][０-９]{2,4}[\-－][０-９]{4})/g, '[電話]');
            el.value = text;
            const countEl = document.getElementById('riceKnowledgeCharCount');
            if (countEl) countEl.textContent = el.value.length + ' 字';
            this.toast('連絡先パターンを自動マスクしました', 'info');
        },

        async submitKnowledgeRegister() {
            const titleEl   = document.getElementById('riceKnowledgeTitle');
            const contentEl = document.getElementById('riceKnowledgeContent');
            const colEl     = document.getElementById('riceKnowledgeCollection');
            const title   = (titleEl?.value   || '').trim();
            const content = (contentEl?.value || '').trim();
            const collection = (colEl?.value || 'default').trim() || 'default';
            if (!content) { this._setKnowledgeSections({ form: true, error: '本文が空です' }); return; }
            if (!title)   { this._setKnowledgeSections({ form: true, error: 'タイトルを入力してください' }); return; }
            if (!this._knowledgeEmailId) { this._setKnowledgeSections({ form: true, error: '対象メールが特定できません' }); return; }

            this._setKnowledgeSubmit({ saving: true });
            try {
                const res = await fetch(`/knowledge/from-email/${this._knowledgeEmailId}`, {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({ title, content, collection }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this._setKnowledgeSections({ form: true, error: data.message || ('登録に失敗しました (HTTP ' + res.status + ')') });
                    return;
                }
                this.toast(data.message || 'ナレッジに登録しました', 'success');
                // スライドアウトで閉じる
                if (window.riceCloseKnowledgePanel) window.riceCloseKnowledgePanel();
            } catch (e) {
                this._setKnowledgeSections({ form: true, error: '通信エラー: ' + (e.message || '') });
            } finally {
                this._setKnowledgeSubmit({ saving: false });
            }
        },

        // ============= AI要約 (スレッド全体) =============
        // 内部 UI 切替 (ローディング / エラー / 結果 / コピー / 再生成) は id 指定の
        // 直接 DOM 操作で行う. Alpine 内部 state も同期で更新しておく (フッタの
        // ボタンや既存ロジックが参照する場合があるため).
        _setAiSummarySections({ loading = false, error = '', result = null }) {
            const lo = document.getElementById('riceAiSummaryLoadingSection');
            const er = document.getElementById('riceAiSummaryErrorSection');
            const re = document.getElementById('riceAiSummaryResultSection');
            const copyBtn  = document.getElementById('riceAiSummaryCopyBtn');
            const regenBtn = document.getElementById('riceAiSummaryRegenBtn');
            if (lo) lo.style.display = loading ? 'flex' : 'none';
            if (er) {
                er.style.display = error ? 'block' : 'none';
                if (error) er.textContent = error;
            }
            if (re) re.style.display = result ? 'block' : 'none';
            // 結果が出ているときだけ「コピー」「再生成」ボタンを出す.
            const showActions = !!result && !error;
            if (copyBtn)  copyBtn.style.display  = showActions ? 'inline-flex' : 'none';
            if (regenBtn) regenBtn.style.display = showActions ? 'inline-flex' : 'none';
            if (regenBtn) regenBtn.disabled = loading;

            if (result) {
                // 結果本文を流し込み
                const body = document.getElementById('riceAiSummaryResult');
                if (body) body.textContent = result.summary || '';
                // バッジ群を組み立てる
                const badges = document.getElementById('riceAiSummaryBadges');
                if (badges) {
                    const parts = [];
                    if (typeof result.email_count === 'number') {
                        parts.push(`<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;"><i class="fas fa-envelope"></i>${result.email_count} 通</span>`);
                    }
                    if (result.skill_name) {
                        parts.push(`<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;"><i class="fas fa-magic"></i>${result.skill_name}</span>`);
                    }
                    if (result.ticket) {
                        parts.push(`<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;background:#fef3c7;color:#92400e;border:1px solid #fde68a;"><i class="fas fa-hashtag"></i>${result.ticket}</span>`);
                    }
                    badges.innerHTML = parts.join('');
                }
            }
        },

        // ===== AI チャット (要約 / 返信案 を多ターンでブラッシュアップ) =====
        // 既存の riceAiSummaryModal と同じ理由で表示制御は imperative.
        // Alpine x-show + Tailwind + 大型 layout の組合せで display:flex が復活しない
        // 事故が出るため, openAiChat / closeAiChat で直接 style.display を書く.
        _setAiChatVisible(visible) {
            const bd = document.getElementById('rice-ai-chat-backdrop');
            const pn = document.getElementById('rice-ai-chat-panel');
            if (bd) bd.style.display = visible ? 'block' : 'none';
            if (pn) pn.style.display = visible ? 'flex'  : 'none';
        },
        // AI モデルピッカーを imperative DOM で描画.
        // Alpine x-for / :class が不安定なので素 HTML で再描画する.
        _renderAiChatModelPicker() {
            const host = document.getElementById('rice-ai-chat-model-picker');
            if (!host) return;
            const esc = s => this._escapeHtml(String(s ?? ''));
            const provider = this.aiProvider || 'ollama';
            const models   = (provider === 'claude') ? (this.aiClaudeModels || [])
                            : (provider === 'gemini') ? (this.aiGeminiModels || [])
                            : (this.aiOllamaModels || []);
            const currentModel = this.aiModel || (models[0]?.id || models[0] || '');
            // タブ
            const tab = (key, label) => {
                const active = provider === key;
                return `<button type="button"
                            onclick="window.riceAiChatSetProvider && window.riceAiChatSetProvider('${key}')"
                            style="padding:4px 10px;font-size:11px;font-weight:700;border:1px solid #e5e7eb;cursor:pointer;${active
                                ? 'background:#1f2937;color:#fff;border-color:#1f2937;'
                                : 'background:#fff;color:#6b7280;'}">${esc(label)}</button>`;
            };
            // モデル select
            let opts = '';
            if (models.length === 0) {
                opts = '<option value="">モデルなし</option>';
            } else {
                opts = models.map(m => {
                    const id = m?.id || m;
                    const nm = m?.name || m?.id || m;
                    const sel = String(id) === String(currentModel) ? ' selected' : '';
                    return `<option value="${esc(id)}"${sel}>${esc(nm)}</option>`;
                }).join('');
            }
            host.innerHTML = `
                <div style="display:inline-flex;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
                    ${tab('ollama', 'Ollama')}${tab('claude', 'Claude')}${tab('gemini', 'Gemini')}
                </div>
                <select id="rice-ai-chat-model-select"
                        onchange="window.riceAiChatSetModel && window.riceAiChatSetModel(this.value)"
                        style="flex:1 1 140px;min-width:140px;border:1px solid #e5e7eb;border-radius:8px;padding:5px 8px;font-size:12px;background:#fff;outline:none;">
                    ${opts}
                </select>
            `;
            // 現在モデル state を select に合わせて更新
            if (!this.aiModel && models.length > 0) {
                this.aiModel = (models[0].id || models[0]);
            }
            // APIキー警告
            const warn = document.getElementById('rice-ai-chat-model-warn');
            if (warn) {
                if (provider === 'claude' && !this.aiHasClaudeKey) {
                    warn.style.display = 'block'; warn.textContent = '⚠ Claude APIキー未設定';
                } else if (provider === 'gemini' && !this.aiHasGeminiKey) {
                    warn.style.display = 'block'; warn.textContent = '⚠ Gemini APIキー未設定';
                } else {
                    warn.style.display = 'none'; warn.textContent = '';
                }
            }
        },
        async openAiChat(kind) {
            console.info('[ai-chat] openAiChat called kind=', kind, 'selectedThreadId=', this.selectedThreadId);
            if (!this.selectedThreadId) {
                this.toast('スレッドを選択してください', 'error');
                return;
            }
            if (kind !== 'summary' && kind !== 'reply') kind = 'summary';
            if (this.aiChat.kind !== kind) this.aiChat.messages = [];
            this.aiChat.kind = kind;
            this.aiChat.open = true;
            this._setAiChatVisible(true);
            console.info('[ai-chat] panel open');
            // モデル一覧を初回だけロード (キャッシュされる) + imperative にピッカー描画
            const loadEl = document.getElementById('rice-ai-chat-model-loading');
            if (loadEl) loadEl.style.display = 'inline-block';
            try { await this.loadAiModels(); } catch (_) {}
            if (loadEl) loadEl.style.display = 'none';
            this._renderAiChatModelPicker();
            this._riceAiChatRenderInputHighlight();
            await this.loadAiChat();

            // ★ AI要約モードはオープン時点で自動で初回要約を走らせる.
            //   (AI返信モードは「呼び出されるまで書かない」仕様なので何もしない)
            if (kind === 'summary' && this.aiChat.messages.length === 0 && !this.aiChat.sessionId) {
                await this._autoSummarize();
            }

            this.$nextTick(() => this._scrollAiChatToBottom());
        },
        // AI要約: 初回オープン時に自動で要約を生成する.
        // 内部的に「このスレッドを要約してください」を user メッセージとして送る.
        async _autoSummarize() {
            if (!this.selectedThreadId || this.aiChat.sessionId) return;
            const seed = 'このスレッドを日本語で要約してください.';
            const ta = document.getElementById('rice-ai-chat-input');
            // textarea 表示は空のままで, 直接 send 経路の引数として seed を渡す.
            this.aiChat.input = seed;
            if (ta) ta.value = ''; // 表示上は空 (履歴は user メッセージで残る)
            await this.sendAiChat();
        },
        // ===== AI チャット: スキル選択 (/コマンド) =====
        // 文中どこでも '/' を打つと, その後ろのテキストをクエリにして候補リストを出す.
        // 候補クリックで aiChat.skillKey が確定し, 入力テキストから '/...' は除去される.
        // 送信時にもテキスト内の '/スキル名' を自動検出してスキルに反映する (popup 非経由でも OK).
        _riceAiChatHandleSlash() {
            const ta = document.getElementById('rice-ai-chat-input');
            const value = (ta && ta.value) || this.aiChat.input || '';
            const pos = ta ? (ta.selectionStart ?? value.length) : value.length;
            // カーソル前で最後の '/' を文中どこからでも探す.
            // '/' 〜 カーソル の間に空白/改行があれば popup は閉じる (= 区切り).
            let slashIdx = -1;
            for (let i = pos - 1; i >= 0; i--) {
                const ch = value[i];
                if (ch === ' ' || ch === '\n' || ch === '\t') break;
                if (ch === '/') { slashIdx = i; break; }
            }
            if (slashIdx < 0) {
                this.aiChat.skillSlash.open = false;
                this._riceAiChatRenderSkillSlash();
                return;
            }
            this.aiChat.skillSlash.open       = true;
            this.aiChat.skillSlash.query      = value.slice(slashIdx + 1, pos);
            this.aiChat.skillSlash.tokenStart = slashIdx;
            this.aiChat.skillSlash.activeIdx  = 0;
            // popup を開くタイミングで collections も一度だけロード
            this._loadAiChatCollections();
            this._riceAiChatRenderSkillSlash();
        },
        async _loadAiChatCollections() {
            if (this.aiChat.collectionsLoaded) return;
            this.aiChat.collectionsLoaded = true;
            try {
                const r = await fetch('/api/knowledge/collections', { headers: { Accept: 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                this.aiChat.collections = Array.isArray(d.collections) ? d.collections : [];
                // ロード完了したら再描画 (popup 開きっぱなしなら反映)
                if (this.aiChat.skillSlash.open) this._riceAiChatRenderSkillSlash();
            } catch (_) {}
        },
        _filteredAiChatCollections() {
            const q = (this.aiChat.skillSlash.query || '').toLowerCase();
            const all = this.aiChat.collections || [];
            if (!q) return all;
            return all.filter(c => String(c.name || '').toLowerCase().includes(q));
        },
        // 送信前にテキストから '/スキル名' トークンを検出する.
        // 仕様変更: '/スキル名' は本文に残したまま skillKey だけ別途返す.
        //   理由: チャット履歴に何で指示したかを残す + LLM 側でユーザ意図が明示できる.
        // 戻り値: { text: そのままの本文, skillKey: 検出したスキル (なければ null) }
        _riceAiChatExtractSkillFromText(raw) {
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
            // hit してもしなくてもテキストはそのまま返す (本文に /skillname を残す).
            return { text, skillKey: hit };
        },
        // スキル候補をフィルタ (キーまたは name に query が部分一致).
        _filteredAiChatSkills() {
            const q = (this.aiChat.skillSlash.query || '').toLowerCase();
            const items = [];
            const map = this.aiSkills || {};
            for (const key of Object.keys(map)) {
                const s = map[key] || {};
                const name = String(s.name || key);
                if (!q || key.toLowerCase().includes(q) || name.toLowerCase().includes(q)) {
                    items.push({ key, name, description: s.description || '' });
                }
            }
            return items;
        },
        // スキル候補 + コレクション候補ポップアップを直接 DOM で描画.
        _riceAiChatRenderSkillSlash() {
            const wrap = document.getElementById('rice-ai-chat-skill-slash');
            if (!wrap) return;
            if (!this.aiChat.skillSlash.open) {
                wrap.style.display = 'none';
                wrap.innerHTML = '';
                return;
            }
            const skills = this._filteredAiChatSkills();
            const cols   = this._filteredAiChatCollections();
            const parts  = [];

            // 1) スキルセクション (skillKey に反映 / system_prompt 切替用)
            if (skills.length > 0) {
                parts.push('<p style="padding:6px 10px;font-size:10px;color:#6b7280;font-weight:700;background:#f9fafb;border-bottom:1px solid #e5e7eb;"><i class="fas fa-bolt text-[9px]" style="color:#4f46e5;"></i> スキル</p>');
                for (const it of skills) {
                    const safeKey = String(it.key).replace(/'/g, "\\'");
                    parts.push(`
                        <button type="button" onclick="window.riceAiChatPickSkill('${safeKey}')"
                                style="display:block;width:100%;text-align:left;padding:8px 10px;background:#fff;border:0;border-bottom:1px solid #f3f4f6;cursor:pointer;">
                            <div style="font-size:12px;font-weight:700;color:#1e1b4b;">${this._escapeHtml(it.name)}</div>
                            <div style="font-size:10px;color:#6b7280;">${this._escapeHtml(it.description || it.key)}</div>
                        </button>
                    `);
                }
            }

            // 2) コレクションセクション (本文に /(name) を挿入し, バックエンドで KB 展開される)
            if (cols.length > 0) {
                parts.push('<p style="padding:6px 10px;font-size:10px;color:#6b7280;font-weight:700;background:#f0fdf4;border-bottom:1px solid #e5e7eb;border-top:1px solid #e5e7eb;"><i class="fas fa-folder text-[9px]" style="color:#16a34a;"></i> ナレッジ コレクション</p>');
                for (const c of cols) {
                    const safeName = String(c.name).replace(/'/g, "\\'");
                    const count = (c.documents || c.url_count || c.count) ?? '';
                    parts.push(`
                        <button type="button" onclick="window.riceAiChatPickCollection('${safeName}')"
                                style="display:block;width:100%;text-align:left;padding:8px 10px;background:#fff;border:0;border-bottom:1px solid #f3f4f6;cursor:pointer;">
                            <div style="font-size:12px;font-weight:700;color:#14532d;">/${this._escapeHtml(c.name)}</div>
                            <div style="font-size:10px;color:#6b7280;">ナレッジを参照${count !== '' ? ' (' + this._escapeHtml(String(count)) + ' 件)' : ''}</div>
                        </button>
                    `);
                }
            }

            if (parts.length === 0) {
                wrap.style.display = 'block';
                wrap.innerHTML = '<p style="padding:8px;font-size:11px;color:#9ca3af;text-align:center;">該当するスキル / コレクションがありません</p>';
                return;
            }

            wrap.style.display = 'block';
            wrap.innerHTML = parts.join('');
        },
        _escapeHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        },
        // テキストの '/word' トークンを <span class="rice-ai-tag">/word</span> に変換した HTML を返す.
        // スキル / コレクション 両方を同じ青チップで描画する (見た目を統一).
        // チャットふきだし (x-html) + textarea オーバーレイ で再利用する.
        renderAiChatTaggedHtml(text) {
            const esc = (s) => this._escapeHtml(s);
            const src = String(text ?? '');
            const re = /(^|[\s\n\t])\/([\p{L}\p{N}_\-.]+)/gu;
            let out = '', last = 0;
            for (const m of src.matchAll(re)) {
                const startName = m.index + m[1].length; // '/' の位置
                out += esc(src.slice(last, startName));
                const name = m[2];
                out += '<span class="rice-ai-tag">/' + esc(name) + '</span>';
                last = startName + 1 + name.length;
            }
            out += esc(src.slice(last));
            // textarea オーバーレイ用に末尾改行のときも 1 文字保持
            if (out.endsWith('\n')) out += ' ';
            return out;
        },
        _riceAiChatRenderInputHighlight() {
            const hi = document.getElementById('rice-ai-chat-input-highlight');
            const ta = document.getElementById('rice-ai-chat-input');
            if (!hi) return;
            hi.innerHTML = this.renderAiChatTaggedHtml((ta && ta.value) || this.aiChat.input || '');
            if (ta) {
                hi.scrollTop  = ta.scrollTop;
                hi.scrollLeft = ta.scrollLeft;
            }
        },
        // スキル選択時: テキストに '/skillkey ' を残す (= 自分でタイプしたのと同じ状態にする).
        //   ・ユーザは続けて「詳細指示」を書ける
        //   ・チャット履歴にも /skillkey が残ってどのスキルで投げたかが見える
        //   ・送信時は サーバ側に skill = key を別途送る + 本文に /skillkey が含まれていても
        //     LLM プロンプトは「スキルの system_prompt + 本文 (詳細指示)」で構築
        pickAiChatSkill(key) {
            const ta = document.getElementById('rice-ai-chat-input');
            const slot = this.aiChat.skillSlash;
            if (ta && slot.tokenStart >= 0) {
                const value = ta.value || '';
                const pos = ta.selectionStart ?? value.length;
                const inserted = '/' + key + ' ';
                const next = value.slice(0, slot.tokenStart) + inserted + value.slice(pos);
                ta.value = next;
                this.aiChat.input = next;
                const newPos = slot.tokenStart + inserted.length;
                try { ta.setSelectionRange(newPos, newPos); ta.focus(); } catch (_) {}
            }
            this.aiChat.skillKey = key;
            this.aiChat.skillSlash.open = false;
            this._riceAiChatRenderSkillSlash();
            this._riceAiChatRenderInputHighlight();
            this.toast('スキル: ' + (this.aiSkills?.[key]?.name || key) + ' を選択. 続けて詳細指示を書けます.', 'info');
        },
        // コレクション選択時: '/...' トークンを '/<name>' に置換 (本文に残す).
        // バックエンド側で '/コレクション名' を検出してナレッジを展開する.
        pickAiChatCollection(name) {
            const ta = document.getElementById('rice-ai-chat-input');
            const slot = this.aiChat.skillSlash;
            if (ta && slot.tokenStart >= 0) {
                const value = ta.value || '';
                const pos = ta.selectionStart ?? value.length;
                const inserted = '/' + name + ' ';
                const next = value.slice(0, slot.tokenStart) + inserted + value.slice(pos);
                ta.value = next;
                this.aiChat.input = next;
                const newPos = slot.tokenStart + inserted.length;
                try { ta.setSelectionRange(newPos, newPos); ta.focus(); } catch (_) {}
            }
            this.aiChat.skillSlash.open = false;
            this._riceAiChatRenderSkillSlash();
            this._riceAiChatRenderInputHighlight();
            this.toast('ナレッジ: /' + name + ' を本文に挿入しました', 'info');
        },
        // モデルプルダウンの値 ("provider:model") を aiChat.provider / aiChat.model にバラす.
        updateAiChatModelFromPick() {
            const v = (this.aiChat.modelPick || '').trim();
            if (!v) { this.aiChat.provider = null; this.aiChat.model = null; return; }
            const i = v.indexOf(':');
            if (i < 0) { this.aiChat.provider = v; this.aiChat.model = null; return; }
            this.aiChat.provider = v.slice(0, i);
            this.aiChat.model    = v.slice(i + 1);
        },
        closeAiChat() {
            this.aiChat.open = false;
            this._setAiChatVisible(false);
            this._stopAiChatPoll();
        },
        async switchAiChatKind(kind) {
            if (this.aiChat.kind === kind) return;
            this._stopAiChatPoll();
            this.aiChat.kind = kind;
            this.aiChat.messages = [];
            this.aiChat.sessionId = null;
            await this.loadAiChat();
            this.$nextTick(() => this._scrollAiChatToBottom());
        },
        async loadAiChat() {
            if (!this.selectedThreadId) return;
            try {
                const url = '/threads/' + this.selectedThreadId + '/ai-chat?kind=' + encodeURIComponent(this.aiChat.kind);
                const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) { this.toast('AI チャットの読み込みに失敗しました', 'error'); return; }
                const d = await r.json();
                this.aiChat.sessionId = d.session?.id || null;
                this.aiChat.messages  = Array.isArray(d.messages) ? d.messages : [];
                // 未完了 assistant が残っているなら再開ポーリング.
                if (this.aiChat.messages.some(m => m.role === 'assistant' && m.status === 'pending')) {
                    this._startAiChatPoll();
                }
            } catch (e) {
                this.toast('通信エラー: ' + e.message, 'error');
            }
        },
        async sendAiChat() {
            // 入力テキスト: Alpine state にあっても無くても textarea の生 value を信頼する.
            const ta      = document.getElementById('rice-ai-chat-input');
            const rawText = ((ta && ta.value) || this.aiChat.input || '').trim();
            // 文中の '/スキル名' を検出 → スキルに反映 + 本文から除去
            const ext = this._riceAiChatExtractSkillFromText(rawText);
            const text = ext.text.trim();
            if (ext.skillKey && ext.skillKey !== this.aiChat.skillKey) {
                this.aiChat.skillKey = ext.skillKey;
                const skName = (this.aiSkills?.[ext.skillKey]?.name) || ext.skillKey;
                this.toast('スキル: ' + skName + ' を適用しました', 'info');
            }
            console.info('[ai-chat] sendAiChat text=', text.slice(0, 40), 'skill=', this.aiChat.skillKey, 'sending=', this.aiChat.sending, 'thread=', this.selectedThreadId);
            if (this.aiChat.sending) { console.warn('[ai-chat] already sending'); return; }
            if (text === '')        { console.warn('[ai-chat] empty input'); this.toast('入力が空です', 'info'); return; }
            if (!this.selectedThreadId) {
                this.toast('スレッドを選択してください', 'error');
                return;
            }
            this.aiChat.sending = true;
            // ボタン視覚: アイコンをスピナーに切替 (Alpine binding に依存しない)
            const sendIcon = document.getElementById('rice-ai-chat-send-icon');
            const sendBtn  = document.getElementById('rice-ai-chat-send-btn');
            if (sendIcon) sendIcon.className = 'fas fa-circle-notch fa-spin';
            if (sendBtn)  sendBtn.disabled   = true;
            try {
                const csrfEl = document.querySelector('meta[name="csrf-token"]');
                const csrf   = csrfEl ? csrfEl.content : '';
                let url, body;
                if (this.aiChat.sessionId) {
                    url  = '/ai-chat-sessions/' + this.aiChat.sessionId + '/messages';
                    const payload = { message: text };
                    if (this.aiChat.skillKey) payload.skill = this.aiChat.skillKey;
                    body = JSON.stringify(payload);
                } else {
                    url  = '/threads/' + this.selectedThreadId + '/ai-chat';
                    const payload = { kind: this.aiChat.kind, message: text };
                    if (this.aiChat.skillKey) payload.skill = this.aiChat.skillKey;
                    if (this.aiProvider) payload.provider = this.aiProvider;
                    if (this.aiModel)    payload.model    = this.aiModel;
                    body = JSON.stringify(payload);
                }
                console.info('[ai-chat] POST', url);
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': csrf },
                    body,
                });
                if (!r.ok) {
                    const j = await r.json().catch(() => ({}));
                    console.error('[ai-chat] send error', r.status, j);
                    this.toast(j.message || ('HTTP ' + r.status), 'error');
                    return;
                }
                const d = await r.json();
                if (d.session?.id) this.aiChat.sessionId = d.session.id;
                if (d.user)      this.aiChat.messages.push(d.user);
                if (d.assistant) this.aiChat.messages.push(d.assistant);
                this.aiChat.input = '';
                if (ta) ta.value = '';
                this._riceAiChatRenderInputHighlight();
                this.$nextTick(() => this._scrollAiChatToBottom());
                this._startAiChatPoll();
            } catch (e) {
                console.error('[ai-chat] send exception', e);
                this.toast('通信エラー: ' + e.message, 'error');
            } finally {
                this.aiChat.sending = false;
                if (sendIcon) sendIcon.className = 'fas fa-paper-plane';
                if (sendBtn)  sendBtn.disabled   = false;
            }
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
        copyAiChatMessage(m) {
            if (!m || !m.content) return;
            try {
                navigator.clipboard.writeText(m.content);
                this.toast('コピーしました', 'success');
            } catch (_) { this.toast('コピーに失敗しました', 'error'); }
        },
        // ポーリング: 未完了 assistant メッセージが完了するまで 2 秒間隔で再取得.
        _startAiChatPoll() {
            this._stopAiChatPoll();
            this.aiChat.pollTimer = setInterval(async () => {
                if (!this.aiChat.open || !this.aiChat.sessionId) {
                    this._stopAiChatPoll();
                    return;
                }
                try {
                    const url = '/threads/' + this.selectedThreadId + '/ai-chat?kind=' + encodeURIComponent(this.aiChat.kind);
                    const r = await fetch(url, { headers: { 'Accept':'application/json' } });
                    if (!r.ok) return;
                    const d = await r.json();
                    const next = Array.isArray(d.messages) ? d.messages : [];
                    // 既存と差し替え (status の遷移を反映)
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
            const el = document.getElementById('rice-ai-chat-messages');
            if (el) el.scrollTop = el.scrollHeight;
        },

        openThreadSummary() {
            if (!this.selectedThreadId) {
                this.toast('スレッドを選択してください', 'error');
                return;
            }
            // 件名をヘッダーに表示
            const subj = document.getElementById('riceAiSummarySubject');
            if (subj) subj.textContent = this.selectedThread?.subject || '';

            // 右側スライドインで表示
            if (window.riceOpenAiSummaryPanel) window.riceOpenAiSummaryPanel();

            this.threadSummaryOpen = true;
            this.threadSummaryCopied = false;
            // モデル一覧をロード (完了後にピッカーを描画)
            (async () => {
                await this.loadAiModels();
                this._renderAiPickers();
            })();
            // スキルピッカーは aiSkills が既に読み込まれていれば即描画
            this._renderAiSkillButtons();
            // 既に生成済みなら再利用、未生成なら生成
            if (this.threadSummary && this.threadSummary.thread_id === this.selectedThreadId) {
                this._setAiSummarySections({ result: this.threadSummary });
            } else {
                this.loadThreadSummary(false);
            }
        },

        // ===== AI パネル: プロバイダー / モデル / スキル ボタンを直接 DOM 生成 =====
        // Alpine x-for / x-model がモーダル内でうまく反応しなかったため、すべて
        // imperative に生成する. 状態は this.aiProvider / this.aiModel / this.summarySkill に
        // 反映する (ペイロード組立のため).
        _renderAiPickers() {
            // プロバイダー 3 ボタン
            const providerRow = document.getElementById('riceAiProviderRow');
            if (providerRow) {
                providerRow.innerHTML = '';
                const providers = [
                    { key: 'ollama', label: 'Ollama', hasKey: true },
                    { key: 'claude', label: 'Claude', hasKey: !!this.aiHasClaudeKey },
                    { key: 'gemini', label: 'Gemini', hasKey: !!this.aiHasGeminiKey },
                ];
                providers.forEach((p, i) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    const active = this.aiProvider === p.key;
                    b.className = 'px-3 py-1 transition-colors' + (i > 0 ? ' border-l border-gray-200' : '');
                    b.style.cssText = active
                        ? 'background-color:#1f2937;color:#ffffff;'
                        : 'background-color:#ffffff;color:#4b5563;';
                    b.textContent = p.label;
                    if (!p.hasKey && p.key !== 'ollama') b.title = 'APIキー未設定';
                    b.onclick = () => {
                        this.aiProvider = p.key;
                        // プロバイダー切替 → 該当 provider の先頭モデルへ
                        const list = this.aiCurrentModels;
                        this.aiModel = list.length > 0 ? (list[0].id || list[0]) : '';
                        this._renderAiPickers(); // ボタン色と select 再描画
                    };
                    providerRow.appendChild(b);
                });
            }
            // モデル select
            const sel = document.getElementById('riceAiModelSelect');
            if (sel) {
                const list = this.aiCurrentModels || [];
                sel.innerHTML = '';
                if (list.length === 0) {
                    const o = document.createElement('option');
                    o.value = '';
                    o.textContent = 'モデルなし';
                    sel.appendChild(o);
                } else {
                    list.forEach(m => {
                        const id = m.id || m;
                        const name = m.name || m;
                        const o = document.createElement('option');
                        o.value = id;
                        o.textContent = name;
                        if (id === this.aiModel) o.selected = true;
                        sel.appendChild(o);
                    });
                }
            }
            // 警告メッセージ
            const warn = document.getElementById('riceAiProviderWarning');
            if (warn) {
                if (this.aiProvider === 'claude' && !this.aiHasClaudeKey) {
                    warn.style.display = 'block';
                    warn.textContent = '⚠ Claude APIキー未設定。AI設定から登録してください。';
                } else if (this.aiProvider === 'gemini' && !this.aiHasGeminiKey) {
                    warn.style.display = 'block';
                    warn.textContent = '⚠ Gemini APIキー未設定。AI設定から登録してください。';
                } else {
                    warn.style.display = 'none';
                }
            }
            // ピッカー読み込み中表示
            const ld = document.getElementById('riceAiPickerLoading');
            if (ld) ld.style.display = this.aiPickerLoading ? 'inline' : 'none';
        },

        _renderAiSkillButtons() {
            const c = document.getElementById('riceAiSkillsContainer');
            if (!c) return;
            const skills = this.aiSkills || {};
            const keys = Object.keys(skills);
            c.innerHTML = '';
            keys.forEach(key => {
                const skill = skills[key];
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'w-full px-2.5 py-1 rounded-lg text-[11px] font-bold border transition-colors text-center truncate';
                const active = this.summarySkill === key;
                b.style.cssText = active
                    ? 'background-color:#4f46e5;color:#ffffff;border-color:#4f46e5;'
                    : 'background-color:#ffffff;color:#374151;border-color:#e5e7eb;';
                b.title = skill.description || '';
                b.innerHTML = `<i class="fas fa-magic text-[9px] mr-1"></i><span>${skill.name || key}</span>`;
                b.onclick = () => {
                    this.summarySkill = key;
                    this._renderAiSkillButtons();
                };
                c.appendChild(b);
            });
            // スキル説明
            const desc = document.getElementById('riceAiSkillDescription');
            if (desc) desc.textContent = skills[this.summarySkill]?.description || '';
        },
        async loadThreadSummary(force = false) {
            if (!this.selectedThreadId) return;
            if (this.threadSummaryLoading) return;
            if (!force && this.threadSummary && this.threadSummary.thread_id === this.selectedThreadId) {
                this._setAiSummarySections({ result: this.threadSummary });
                return;
            }
            this.threadSummaryLoading = true;
            this.threadSummaryError = '';
            this.threadSummary = null;
            // 期間内メール数を画面ヒントに出す
            const detail = document.getElementById('riceAiSummaryLoadingDetail');
            if (detail) detail.textContent = (this.threadEmails?.length || 0) + ' 通のメールを読み込み中';
            this._setAiSummarySections({ loading: true });
            const targetThreadId = this.selectedThreadId;
            try {
                const res = await fetch(`/threads/${targetThreadId}/ai-summary`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        provider: this.aiProvider || null,
                        model:    this.aiModel    || null,
                        skill:    this.summarySkill || 'summarize',
                        // プロンプトはパネルの textarea から読む (Alpine x-model を撤去したため)
                        prompt:   (document.getElementById('riceAiSummaryPromptArea')?.value || this.summaryUserPrompt || ''),
                    }),
                });
                localStorage.setItem('summarySkill', this.summarySkill || 'summarize');
                const initial = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.threadSummaryError = initial.message || `AI要約の開始に失敗しました (HTTP ${res.status})`;
                    this._setAiSummarySections({ error: this.threadSummaryError });
                    return;
                }
                const taskId = initial.task_id;
                const finalData = await this._pollAiTask(taskId);
                if (!finalData) {
                    this._setAiSummarySections({ error: this.threadSummaryError || 'タイムアウト' });
                    return;
                }
                if (finalData.status === 'error') {
                    const code = finalData.error_code;
                    let prefix = '';
                    if (code === 'insufficient_credits') prefix = '【クレジット不足】';
                    else if (code === 'invalid_api_key') prefix = '【APIキー無効】';
                    else if (code === 'rate_limited') prefix = '【レート制限】';
                    else if (code === 'model_not_found') prefix = '【モデル未存在】';
                    else if (code === 'rag_api_unreachable') prefix = '【RAG API 未起動】';
                    this.threadSummaryError = prefix + 'AI要約に失敗しました: ' + (finalData.error_message || '');
                    this._setAiSummarySections({ error: this.threadSummaryError });
                    this.toast(prefix + 'AI要約に失敗', 'error');
                    this._notifyDesktop('AI要約失敗', prefix + (finalData.error_message || ''));
                    return;
                }
                this.threadSummary = {
                    summary: finalData.answer,
                    sources: finalData.sources || [],
                    provider: finalData.provider,
                    model:    finalData.model,
                    skill_name: initial.skill_name,
                    email_count: initial.email_count,
                    subject:  initial.subject,
                    ticket:   initial.ticket,
                    thread_id: targetThreadId,
                };
                this._setAiSummarySections({ result: this.threadSummary });
                const subjLabel = initial.subject ? ('「' + initial.subject + '」') : '';
                this.toast('AI要約が完了しました' + (this.threadSummaryOpen ? '' : ' (モーダルを開いて確認)'), 'success');
                this._notifyDesktop('AI要約 完了', subjLabel || 'スレッドの要約が生成されました');
            } catch (e) {
                this.threadSummaryError = '通信エラー: ' + (e.message || '');
                this._setAiSummarySections({ error: this.threadSummaryError });
            } finally {
                this.threadSummaryLoading = false;
            }
        },

        // AiTask の完了をポーリング (最大 180s, 1.5s 間隔)。done/error なら最終データ、タイムアウトなら null
        async _pollAiTask(taskId, maxWaitMs = 180000, intervalMs = 1500) {
            const started = Date.now();
            while (Date.now() - started < maxWaitMs) {
                try {
                    const res = await fetch(`/ai-tasks/${taskId}`, { headers: { Accept: 'application/json' } });
                    if (res.ok) {
                        const data = await res.json();
                        if (data.status === 'done' || data.status === 'error') return data;
                    }
                } catch (_) { /* ネットワーク一時エラーは無視して継続 */ }
                await new Promise(r => setTimeout(r, intervalMs));
            }
            this.threadSummaryError = 'タイムアウト: AI 処理に時間がかかっています。しばらく後で再度お試しください。';
            return null;
        },
        async copyThreadSummary() {
            if (!this.threadSummary?.summary) return;
            try {
                await navigator.clipboard.writeText(this.threadSummary.summary);
                this.threadSummaryCopied = true;
                // 直接 DOM でアイコン / ラベルを差し替える (Alpine reactivity に依存しない).
                const icon  = document.getElementById('riceAiSummaryCopyIcon');
                const label = document.getElementById('riceAiSummaryCopyLabel');
                if (icon)  { icon.classList.remove('fa-copy'); icon.classList.add('fa-check'); }
                if (label) label.textContent = 'コピーしました';
                setTimeout(() => {
                    this.threadSummaryCopied = false;
                    if (icon)  { icon.classList.remove('fa-check'); icon.classList.add('fa-copy'); }
                    if (label) label.textContent = '要約をコピー';
                }, 1500);
            } catch (e) {
                this.toast('コピーに失敗しました', 'error');
            }
        },

        // チャットパネルの開閉 (スコープに関わらず、開いていれば一発で閉じる)
        toggleChatPanel() {
            if (!this.selectedThreadId) {
                this.toast('スレッドを選択してください', 'error');
                return;
            }
            if (this.chatOpen) {
                // 現在のスコープ (thread / email) を問わず、押下時は閉じる
                this.chatOpen = false;
                this.stopChatPolling();
                this._chatReadJustNow = false;
                return;
            }
            // チャット閉じている状態 → thread スコープで開く
            this.setChatScopeThread();
            this.chatOpen = true;
            this._chatReadJustNow = true;
            const row = (this.threads || []).find(t => t.id === this.selectedThreadId);
            if (row) row.unread_chat_count = 0;
            this.loadChatComments();
            this.startChatPolling();
        },

        // スレッド全体スコープに切替
        setChatScopeThread() {
            this.chatScope = { kind: 'thread', email_id: null, email_subject: '', email_from: '' };
            if (this.chatOpen) this.loadChatComments();
        },

        // 直近の email スコープに戻る (「メール」トグル / 「↩ メールに戻る」用)
        restoreEmailScope() {
            if (this.chatScope.kind === 'email') return; // 既に email スコープなら何もしない
            if (!this.lastEmailScope) return;            // 復元先がない
            this.chatScope = { ...this.lastEmailScope };
            if (this.chatOpen) this.loadChatComments();
        },

        // 特定メールにフォーカス (チャットコメントの📧チップから)
        focusEmailFromChat(emailId) {
            const email = (this.threadEmails || []).find(e => e.id === emailId);
            if (email) this.openEmailChat(email);
        },

        // メール件名を id から逆引き (チップ表示用)
        emailSubjectFor(emailId) {
            const e = (this.threadEmails || []).find(x => x.id === emailId);
            return e ? (e.subject || '') : '';
        },

        // スクロール検知 (一番下から80px以上離れたら「最新へ」ボタン表示)
        onChatScroll(e) {
            const el = e.target;
            if (!el) return;
            const distance = el.scrollHeight - (el.scrollTop + el.clientHeight);
            this.chatScrolledUp = distance > 80;
        },

        // 8秒ごとに自動更新 (他ユーザーの新規メッセージを取得)
        startChatPolling() {
            this.stopChatPolling();
            this.chatPollIntervalId = setInterval(() => {
                if (!this.chatOpen || !this.selectedThreadId) {
                    this.stopChatPolling();
                    return;
                }
                this.loadChatComments(true);
            }, 8000);
        },
        stopChatPolling() {
            if (this.chatPollIntervalId) {
                clearInterval(this.chatPollIntervalId);
                this.chatPollIntervalId = null;
            }
        },

        // チャット一覧の取得 (silent=true ならローディング表示なし＆自動スクロール抑制)
        async loadChatComments(silent = false) {
            if (!this.selectedThreadId) return;
            if (!silent) this.chatLoading = true;
            try {
                // スコープに応じて URL を切替 (email スコープなら email_id を付与)
                const params = new URLSearchParams();
                if (this.chatScope.kind === 'email' && this.chatScope.email_id) {
                    params.set('email_id', this.chatScope.email_id);
                }
                const url = `/threads/${this.selectedThreadId}/comments${params.toString() ? '?' + params.toString() : ''}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                const before = this.chatComments.length;
                this.chatComments = data.comments || [];
                // 新着があればスクロール (画面下部にいる時のみ自動スクロール)
                if (!silent || this.chatComments.length > before) {
                    this.$nextTick(() => this.scrollChatToBottom(silent));
                }
            } catch (e) {
                if (!silent) {
                    console.error('チャット読み込み失敗', e);
                    this.toast('チャットの読み込みに失敗しました', 'error');
                }
            } finally {
                if (!silent) this.chatLoading = false;
            }
        },

        // チャット送信
        async sendChatComment() {
            // 防御: chatInput が null だと .trim() で落ちて :disabled 含めボタン全体が反応しなく見える.
            const text = (this.chatInput || '').trim();
            const hasFiles = (this.chatPendingFiles?.length || 0) > 0;
            // 早期 return は理由を明示する (旧実装は silent return → ユーザが何故動かないか分からなかった).
            if (!text && !hasFiles) {
                this.toast?.('本文を入力するか、ファイルを添付してください', 'error');
                return;
            }
            if (!this.selectedThreadId) {
                this.toast?.('スレッドが選択されていません. スレッドを開いてから送信してください', 'error');
                return;
            }
            if (this.chatSending) return; // 重複クリック防止 (これは silent でよい)
            this.chatSending = true;
            try {
                const url = `/threads/${this.selectedThreadId}/comments`;
                // email スコープなら email_id を含めて送信 (per-email chat)
                const emailIdForSend = this.chatScope.kind === 'email' ? this.chatScope.email_id : null;
                let res;
                if (hasFiles) {
                    const fd = new FormData();
                    if (text) fd.append('content', text);
                    if (emailIdForSend) fd.append('email_id', emailIdForSend);
                    this.chatPendingFiles.forEach(f => fd.append('files[]', f));
                    res = await fetch(url, {
                        method:'POST',
                        headers:{'Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                        body: fd,
                    });
                } else {
                    const body = { content: text };
                    if (emailIdForSend) body.email_id = emailIdForSend;
                    res = await fetch(url, {
                        method:'POST',
                        headers: this.jsonHeaders(),
                        body: JSON.stringify(body),
                    });
                }
                const data = await res.json();
                if (!res.ok || data.status === 'error') {
                    this.toast(data.message || '送信に失敗しました', 'error');
                    return;
                }
                if (data.comment) this.chatComments.push(data.comment);
                this.chatInput = '';
                this.chatPendingFiles = [];
                this.closeMention();
                this.$nextTick(() => this.scrollChatToBottom());
            } catch (e) {
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            } finally {
                this.chatSending = false;
            }
        },

        // チャット用ファイル選択 / 除外 / バイト整形
        onChatFilesPicked(e) {
            const files = Array.from(e.target.files || []);
            const max = 10, maxBytes = 10 * 1024 * 1024;
            for (const f of files) {
                if (this.chatPendingFiles.length >= max) { this.toast('添付は最大10ファイル', 'error'); break; }
                if (f.size > maxBytes) { this.toast(`「${f.name}」は10MB超`, 'error'); continue; }
                this.chatPendingFiles.push(f);
            }
            e.target.value = '';
        },
        removeChatPendingFile(i) { this.chatPendingFiles.splice(i, 1); },
        formatFileBytes(n) {
            n = Number(n) || 0;
            if (n < 1024) return n + 'B';
            if (n < 1024 * 1024) return (n / 1024).toFixed(1) + 'KB';
            return (n / 1024 / 1024).toFixed(1) + 'MB';
        },

        // チャット削除
        async deleteChatComment(id) {
            if (!confirm('このメッセージを削除しますか？')) return;
            try {
                const res = await fetch(`/thread-comments/${id}`, {
                    method: 'DELETE',
                    headers: this.jsonHeaders(),
                });
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    this.toast(data.message || '削除に失敗しました', 'error');
                    return;
                }
                this.chatComments = this.chatComments.filter(c => c.id !== id);
            } catch (e) {
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            }
        },

        // チャット画面下部へスクロール (silent=true 時はユーザーが下部付近にいる場合のみ)
        scrollChatToBottom(silent = false) {
            const el = document.getElementById('chat-messages');
            if (!el) return;
            if (silent) {
                const distance = el.scrollHeight - (el.scrollTop + el.clientHeight);
                if (distance > 80) return; // ユーザーが過去メッセージを読んでいる時は触らない
            }
            el.scrollTop = el.scrollHeight;
        },

        // ============= メール毎チャット (per-email) - サイドバーで開く =============
        async openEmailChat(email) {
            if (!email?.id || !this.selectedThreadId) {
                this.toast('スレッドを選択してください', 'error');
                return;
            }
            // トグル動作: 同じメールのチャットが既に開いていれば閉じる.
            // (チャット表示中に同じボタンを再度押す = 閉じる、というユーザ要望.)
            if (this.chatOpen
                && this.chatScope?.kind === 'email'
                && this.chatScope?.email_id === email.id) {
                this.chatOpen = false;
                this.stopChatPolling();
                return;
            }
            // チャットスコープを email に切替してサイドバーを開く
            this.chatScope = {
                kind: 'email',
                email_id: email.id,
                email_subject: email.subject || '',
                email_from: email.from_label || email.from_address || '',
            };
            // 「メール」トグルで戻れるように記憶
            this.lastEmailScope = { ...this.chatScope };
            if (!this.chatOpen) {
                this.chatOpen = true;
                this.startChatPolling();
            }
            this._chatReadJustNow = true;
            this.chatComments = [];
            await this.loadChatComments();
        },
        // ============= @メンション =============

        // 入力中: カーソル前の "@xxx" パターンを検出
        onChatInput(e) {
            const value = e.target.value;
            const cursor = e.target.selectionStart || 0;
            const before = value.slice(0, cursor);

            // 直前の "@" を探す (空白や改行で区切られている)
            const match = before.match(/(?:^|[\s\n])@([^\s\n]*)$/) || before.match(/^@([^\s\n]*)$/);
            if (match) {
                this.mentionQuery = match[1] || '';
                this.mentionStart = cursor - this.mentionQuery.length - 1; // "@" の位置
                this.mentionOpen = true;
                this.mentionIndex = 0;
            } else {
                this.closeMention();
            }
        },

        // メンション候補のフィルタリング.
        // 自分自身は候補から除外する (自分宛にチャットを送るシーンが無いため候補に出さない).
        get mentionMatches() {
            if (!this.mentionOpen) return [];
            const q = (this.mentionQuery || '').toLowerCase();
            const myId = {{ auth()->id() ?? 'null' }};
            const list = (this.users || []).filter(u => {
                if (myId !== null && Number(u.id) === Number(myId)) return false;
                if (!q) return true;
                return (u.name || '').toLowerCase().includes(q)
                    || (u.email || '').toLowerCase().includes(q);
            });
            return list.slice(0, 8);
        },

        // 候補内の上下移動
        onMentionKeydown(e, dir) {
            if (!this.mentionOpen || this.mentionMatches.length === 0) return;
            e.preventDefault();
            if (dir === 'up') {
                this.mentionIndex = (this.mentionIndex - 1 + this.mentionMatches.length) % this.mentionMatches.length;
            } else {
                this.mentionIndex = (this.mentionIndex + 1) % this.mentionMatches.length;
            }
        },

        // Enter 時: メンション候補が開いていれば選択、それ以外は送信
        onChatEnter() {
            if (this.mentionOpen && this.mentionMatches.length > 0) {
                this.pickMention(this.mentionMatches[this.mentionIndex]);
            } else {
                this.sendChatComment();
            }
        },

        // チャット入力: Ctrl/Cmd + Enter で送信、Enter は改行 (デフォルト)
        onChatKeydown(e) {
            if (e.key !== 'Enter') return;
            if (this.mentionOpen && this.mentionMatches.length > 0) {
                // メンション選択中は Enter で選択
                e.preventDefault();
                this.pickMention(this.mentionMatches[this.mentionIndex]);
                return;
            }
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                this.sendChatComment();
            }
        },

        threadChatAutoresize(e) {
            const ta = e.target;
            ta.style.height = 'auto';
            ta.style.height = Math.min(ta.scrollHeight, 200) + 'px';
        },

        threadChatAvatarColor(userId) {
            const palette = ['#3b82f6','#10b981','#f59e0b','#ec4899','#8b5cf6','#06b6d4','#ef4444','#84cc16'];
            return palette[(userId || 0) % palette.length];
        },

        // 候補を選択 → 入力欄に "@名前 " を挿入
        pickMention(user) {
            if (!user || this.mentionStart < 0) {
                this.closeMention();
                return;
            }
            const value = this.chatInput;
            // mentionStart は "@" の位置。その前 + "@名前 " + その後ろ (現在のカーソル以降は @xxx の続きが消える前提)
            const before = value.slice(0, this.mentionStart);
            const ta = document.getElementById('chat-input-textarea');
            const cursor = ta?.selectionStart ?? value.length;
            const after = value.slice(cursor);
            const inserted = '@' + user.name + ' ';
            this.chatInput = before + inserted + after;
            this.closeMention();
            // 挿入後の位置にカーソル移動
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

        // 表示時のメンションハイライト (HTMLエスケープしてから @名前 を span でラップ)
        renderMentions(content, isAuthor) {
            const escape = (s) => String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            const escaped = escape(content);
            // @名前 の集合を作成
            const names = (this.users || []).map(u => u.name).filter(Boolean)
                .sort((a, b) => b.length - a.length);
            if (names.length === 0) return escaped;

            // 名前を正規表現でエスケープ
            const reEsc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const pattern = new RegExp('@(' + names.map(reEsc).join('|') + ')(?=[\\s\\n.,!?。、]|$)', 'g');
            const cls = isAuthor
                ? 'bg-white/25 text-white font-bold rounded px-1'
                : 'bg-amber-100 text-amber-700 font-bold rounded px-1';
            return escaped.replace(pattern, '<span class="' + cls + '">@$1</span>');
        },

        // 自分宛メンションかチェック
        isMentionedToMe(content) {
            const myName = @json(auth()->user()->name ?? '');
            if (!myName) return false;
            const reEsc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const re = new RegExp('@' + reEsc(myName) + '(?=[\\s\\n.,!?。、]|$)');
            return re.test(content || '');
        },

        // スレッド上部の所属ルームチップの「✕」を押した時の処理。
        // 表示中スレッドを指定ルームから外す (DELETE /api/chat-rooms/{room}/threads/{thread})。
        // 共有/個人どちらも同じ API。共有ルームから外すと他メンバーから見えなくなるので警告メッセージを出す。
        async detachThreadFromRoom(room) {
            if (!room || !room.id || !this.selectedThreadId) return;
            const tid = this.selectedThreadId;
            const isShared = !room.is_private;
            const subject = this.selectedThread?.subject || '(件名なし)';
            const msg = isShared
                ? `⚠ 共有ルーム「${room.name}」からこのスレッドを外します\n\n`
                  + `スレッド: ${subject}\n\n`
                  + `このルームに参加している他のメンバー全員から、このスレッドが見えなくなります。\n`
                  + '本当に外しますか?'
                : `個人ルーム「${room.name}」からこのスレッドを外します。\n\n`
                  + `スレッド: ${subject}\n\nよろしいですか?`;
            if (!confirm(msg)) return;

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch(`/api/chat-rooms/${room.id}/threads/${tid}`, {
                    method: 'DELETE',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                });
                if (!res.ok) {
                    let detail = `HTTP ${res.status}`;
                    try { detail = (await res.json()).error || detail; } catch (_) {}
                    this.toast('ルームから外せませんでした: ' + detail, 'error');
                    return;
                }

                // ローカル state からも該当ルームを除く (即時反映)。
                // bundled_rooms はサーバ側で is_private 別に分けて返している構造を踏襲。
                const filterOut = (arr) => (arr || []).filter(x => Number(x.id) !== Number(room.id));
                this.threadBundledRooms = {
                    shared:  filterOut(this.threadBundledRooms?.shared),
                    private: filterOut(this.threadBundledRooms?.private),
                };

                // ルーム一覧 (左サイドバー) の bundled_thread_ids もズレるので再取得。
                // 「ルーム未設定」フィルタにもすぐ反映される。
                this.loadEmailRooms();

                // 現在そのルームで絞り込んでいた場合は、絞り込み一覧からこのスレッドが消えるので再フェッチ
                if (String(this.emailRoomFilterId) === String(room.id)) {
                    this.loadEmailRoomBundledThreads();
                    this.loadThreads();
                }

                this.toast(`「${room.name}」から外しました`, 'success');
            } catch (e) {
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            }
        },

        // スレッド内検索: threadEmails をクエリで絞り込む。
        // 検索対象: 件名 / 差出人表示名 / 差出人アドレス / 宛先 / Cc / 本文 (plain_body)
        // 全て大文字小文字を無視。 検索クエリが空の時は全件返す。
        get filteredThreadEmails() {
            const q = (this.threadInnerSearchQuery || '').trim().toLowerCase();
            if (!q) return this.threadEmails;
            const arr = (this.threadEmails || []).filter(e => {
                const haystacks = [
                    e.subject || '',
                    e.from_label || '',
                    e.from_address || '',
                    e.from_name || '',
                    e.to_address || '',
                    e.cc || '',
                    e.plain_body || '',
                ];
                return haystacks.some(h => String(h).toLowerCase().includes(q));
            });
            return arr;
        },

        // ===== バンドル先ルーム一覧 (各メール上部の「振り分け先」表示用) =====
        // threadBundledRooms (= サーバから来たバンドル先ルーム一覧) を共有/個人をまとめて 1 配列に.
        // matched_rule_type が埋まっているもの = 自動振り分け (フィルタ条件付きで表示)
        // matched_rule_type が NULL のもの     = 手動で追加 / 監査列追加前から存在 (「手動」表記で表示)
        // ★ 「どのフィルタでルームに入ったか各メールに書いてほしい」要望に応えるため
        //   全部出す. 自動か手動かはアイコンとラベルで区別.
        get autoRoutedBundledRooms() {
            const out = [];
            (this.threadBundledRooms?.shared || []).forEach(r => {
                if (r) out.push(r);
            });
            (this.threadBundledRooms?.private || []).forEach(r => {
                if (r) out.push(r);
            });
            return out;
        },

        // ===== スレッド全体の添付ファイルサマリ (ヘッダ表示用) =====
        // スレッドに含まれるすべてのメールの添付を 1 個の配列にフラット化して返す。
        // 同じファイル ID が重複しないように Map で集約。
        // 各エントリは { id, filename, url, email_id, email_subject, sent_at } を持ち、
        // ヘッダのチップから「該当メールへスクロール」できるよう email_id も保持する。
        // (旧UI: 添付は各メールの最下部 → スレッドが長いと埋もれて見落としやすい問題があった)
        get threadAttachments() {
            const out = [];
            const seen = new Set();
            (this.threadEmails || []).forEach(e => {
                (e.attachments || []).forEach(a => {
                    if (!a || a.id == null) return;
                    if (seen.has(a.id)) return;
                    seen.add(a.id);
                    out.push({
                        id:            a.id,
                        filename:      a.filename || '(無題)',
                        url:           a.url,
                        email_id:      e.id,
                        email_subject: e.subject || '',
                        sent_at:       e.sent_at || e.created_at || null,
                    });
                });
            });
            return out;
        },

        // 添付チップから該当メールに飛ぶ. メールが折りたたまれていれば展開も行う.
        // _scrollToCurrentMatch と同じ pattern で青リングフラッシュも付ける.
        scrollToEmail(emailId) {
            if (!emailId) return;
            if (!this.expandedEmailIds.includes(emailId)) {
                this.expandedEmailIds.push(emailId);
            }
            this.$nextTick(() => {
                const el = document.querySelector('[data-email-id="' + emailId + '"]');
                if (el && typeof el.scrollIntoView === 'function') {
                    el.scrollIntoView({ block: 'start', behavior: 'smooth' });
                    try {
                        el.classList.add('search-flash');
                        setTimeout(() => el.classList.remove('search-flash'), 800);
                    } catch (_) {}
                }
            });
        },

        // ===== スレッド内検索: 次/前マッチへの移動 + ハイライト =====
        // threadInnerSearchIndex は filteredThreadEmails 配列内の現在位置 (0-based)。
        threadInnerSearchIndex: 0,
        // 検索クエリが変わった瞬間に index を 0 へリセットして「最初のマッチ」へ飛ばす.
        // ★ メソッド名に "$" プレフィックスを付けない (Alpine の magic property と
        //   衝突して emailApp() スコープ全体が壊れる事故あり)。 _ prefix を使う。
        _resetSearchIndex() { this.threadInnerSearchIndex = 0; },
        get hasThreadInnerSearch() {
            return (this.threadInnerSearchQuery || '').trim() !== '';
        },
        // 「次/前のマッチ」へ移動. delta = +1 / -1
        gotoNextMatch(delta) {
            const arr = this.filteredThreadEmails;
            if (!arr || arr.length === 0) return;
            // wrap-around (一周したら最初/最後へ)
            const n = arr.length;
            this.threadInnerSearchIndex = ((this.threadInnerSearchIndex + delta) % n + n) % n;
            this._scrollToCurrentMatch();
        },
        // 現在の検索対象 (= filteredThreadEmails[threadInnerSearchIndex]) を開いて
        // 画面に持ってくる. 一瞬リングフラッシュも入れて視覚的に分かりやすくする.
        _scrollToCurrentMatch() {
            const arr = this.filteredThreadEmails;
            if (!arr || arr.length === 0) return;
            const idx = Math.max(0, Math.min(arr.length - 1, this.threadInnerSearchIndex));
            const cur = arr[idx];
            if (!cur) return;
            // メール詳細を展開していなければ開く
            if (!this.expandedEmailIds.includes(cur.id)) {
                this.expandedEmailIds.push(cur.id);
            }
            // 次フレームで scroll (展開アニメ後)
            this.$nextTick(() => {
                const el = document.querySelector('[data-email-id="' + cur.id + '"]');
                if (el && typeof el.scrollIntoView === 'function') {
                    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    // 一瞬青リングフラッシュ
                    try {
                        el.classList.add('search-flash');
                        setTimeout(() => el.classList.remove('search-flash'), 800);
                    } catch (_) {}
                }
            });
        },
        // 検索文字列を含む文字列を、HTML エスケープしつつマッチ部分を <mark> で囲む.
        // 表示用ヘルパ。 q を含まない場合 / 空の場合はエスケープのみ。
        highlightMatch(text) {
            const s = String(text ?? '');
            if (s === '') return '';
            const q = (this.threadInnerSearchQuery || '').trim();
            // まず HTML エスケープ (XSS / レイアウト崩れ対策)
            const esc = s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            if (q === '') return esc;
            // 正規表現エスケープして大文字小文字無視で全マッチを置換
            const re = new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
            return esc.replace(re, (m) => '<mark class="thread-search-mark">' + m + '</mark>');
        },

        async loadThread(id) {
            // スレッドが変わったら chat スコープと「直近メール」記憶をリセット
            const switchingThread = (this.selectedThreadId !== id);
            if (switchingThread) {
                this.chatScope = { kind: 'thread', email_id: null, email_subject: '', email_from: '' };
                this.lastEmailScope = null;
                // 別スレッドへ移ったら検索クエリもリセット (スレッドをまたいで検索を引き継がない)
                this.threadInnerSearchQuery = '';
                // 個別メールフォーカスも切替で抜ける
                this._focusedEmailId = null;
            }
            this.selectedThreadId = id;
            // 横断ナビ用にカレントスレッドを保存
            try { localStorage.setItem('currentThreadId', String(id)); } catch (_) {}
            this.expandedEmailIds = [];
            // 別スレッドを選択したらバッジ抑制フラグをクリア (この新スレッドの未読を表示するため)
            this._chatReadJustNow = false;
            try {
                const res = await fetch(`/threads/${id}`, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                this.selectedThread = data.thread;
                this.threadEmails = (data.emails || []).slice().sort((a, b) => {
                    const ta = a.received_at ? Date.parse(a.received_at.replace(/\//g, '-')) : 0;
                    const tb = b.received_at ? Date.parse(b.received_at.replace(/\//g, '-')) : 0;
                    if (tb !== ta) return tb - ta;
                    return (b.id || 0) - (a.id || 0);
                });
                this.threadMerges = data.merges || [];
                this.pendingApprovals = data.pending_approvals || [];
                // バンドル先ルーム (共有/個人で分かれた構造で来る)。
                // 配列の正規化で undefined アクセスを防ぐ。
                this.threadBundledRooms = {
                    shared:  Array.isArray(data.bundled_rooms?.shared)  ? data.bundled_rooms.shared  : [],
                    private: Array.isArray(data.bundled_rooms?.private) ? data.bundled_rooms.private : [],
                };
                if (this.threadEmails.length > 0) this.expandedEmailIds.push(this.threadEmails[0].id);
                // スレッドを切替えた時はスクロールを先頭に戻す.
                // (前スレッドで下までスクロールしていると、別スレッドを開いた直後も
                //  同じ DOM の scrollTop が引き継がれて新スレッドの末尾が見える、という事象の対策)
                if (switchingThread) {
                    this.$nextTick(() => {
                        const pane = this.$refs.threadEmailsPane;
                        if (pane) {
                            pane.scrollTop = 0;
                        } else {
                            // x-ref が拾えない場合のフォールバック (DOM 構造変更時の保険)
                            const fallback = document.querySelector('main .flex-1.overflow-y-auto.custom-scrollbar')
                                          || document.querySelector('.custom-scrollbar[class*="overflow-y-auto"]');
                            if (fallback) fallback.scrollTop = 0;
                        }
                    });
                }
                // チャットパネルが開いていれば、新スレッドのチャットを取得 (既読化される)
                if (this.chatOpen) {
                    this._chatReadJustNow = true;
                    const row = (this.threads || []).find(t => t.id === id);
                    if (row) row.unread_chat_count = 0;
                    this.loadChatComments();
                }
                // チャットが閉じている間は裏での取得はしない (未読バッジは一覧由来の値のまま)
            } catch(e) {
                console.error('スレッド読み込み失敗', e);
                this.toast('スレッドの読み込みに失敗しました', 'error');
            }
        },

        toggleEmailExpand(id) { if (this.expandedEmailIds.includes(id)) this.expandedEmailIds = this.expandedEmailIds.filter(eid => eid !== id); else this.expandedEmailIds.push(id); },

        // メールが展開状態か判定。
        // 通常は expandedEmailIds に id が含まれているかどうかだが、
        // 「スレッド内検索」が有効な時はヒットしたメールを自動展開して内容を即座に確認できるようにする
        // (filteredThreadEmails には既に絞り込み済みのメールしか流れて来ないので、
        // 検索アクティブ時は受け取った email を全て展開扱いにすれば良い)。
        isEmailExpanded(email) {
            if (!email) return false;
            if ((this.threadInnerSearchQuery || '').trim() !== '') return true;
            return this.expandedEmailIds.includes(email.id);
        },

        // === メール本文 (HTML / テキスト) 表示モード ===
        emailViewMode(id) {
            // デフォルトは常に「テキスト」表示。
            // 理由: HTML 表示は sandbox iframe 内とはいえ外部画像 (img src=https://...) が
            //   読み込まれるため、開封トラッキング (web bug) や見えない通信を発生させる懸念がある。
            //   テキスト表示はそのリスクが無いので、明示的に HTML ボタンを押した時だけ HTML 描画する。
            // ユーザが (per-email で) HTML を選択した場合は、その選択を維持。
            if (this.emailViewModes[id]) return this.emailViewModes[id];
            return 'text';
        },
        setEmailViewMode(id, mode) {
            this.emailViewModes = { ...this.emailViewModes, [id]: mode };
        },
        // iframe に渡す srcdoc を構築する.
        //
        // 設計メモ:
        //   - 受信メールの body_html は <!DOCTYPE><html><head>...<style>...</style></head>
        //     <body>...</body></html> の完全形 / フラグメントの両方がある.
        //   - 完全形を直接 srcdoc にする実装も試したが、メーラ側がレイアウト用に作る
        //     XHTML 宣言 (`<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" ...>`)
        //     や、`<html xmlns:o="urn:schemas-microsoft-com:office:office" ...>` のような
        //     Office 名前空間の混入で、ブラウザによっては HTML5 parser モードに切り替えられず
        //     body の中身が表示されない事象が起きた (Chrome で空白になる).
        //   - そのため「常に自前の HTML5 スケルトンで包む」方式に統一する. 中に
        //     メール側 <html><head><style>...</style></head><body>...</body></html> を
        //     そのまま入れても、HTML5 パーサが内側の <html>/<head>/<body> を読み飛ばして
        //     <style> 等は外側の <head> へホイストしてくれるため、CSS は正しく適用される.
        iframeSrcDocFor(email) {
            const safe = email.safe_body_html || '';
            // 親と同じフォントで表示し、img/table がはみ出さないようにする最低限の補助 CSS.
            // メール側 <style> がある場合はそちらが優先されるが、未指定要素には効く.
            const baseCss = `
                html, body { margin:0; padding:12px; background:#ffffff; color:#1f2937;
                    font-family: -apple-system, BlinkMacSystemFont, 'Hiragino Kaku Gothic ProN',
                                 'Hiragino Sans', Meiryo, 'Noto Sans JP', sans-serif;
                    font-size: 14px; line-height: 1.7; word-wrap: break-word; }
                img { max-width: 100%; height: auto; }
                table { max-width: 100%; }
                pre { white-space: pre-wrap; word-wrap: break-word; }
                blockquote { border-left: 3px solid #e5e7eb; margin: 8px 0; padding: 4px 12px; color: #6b7280; }
                a { color: #2563eb; text-decoration: underline; }
                a:hover { color: #1d4ed8; }
            `;
            // CSP: script の inline 実行と外部 script 読み込みを完全遮断.
            //      style-src は 'unsafe-inline' (= メール内の <style> と style="" を許可).
            //      img-src は * (画像はトラッキングのリスクがあるが、メール本文を正しく見せるため許可).
            return `<!DOCTYPE html><html><head>
<meta http-equiv="Content-Security-Policy" content="default-src 'self' data:; img-src * data:; style-src 'unsafe-inline'; script-src 'none'; object-src 'none'; frame-src 'none';">
<meta http-equiv="X-Content-Type-Options" content="nosniff">
<base target="_blank">
<style>${baseCss}</style>
</head><body>${safe}</body></html>`;
        },
        // iframe の高さを内容に合わせて自動調整
        resizeEmailIframe(iframe) {
            try {
                const doc = iframe.contentDocument || iframe.contentWindow?.document;
                if (!doc) return;
                const h = Math.max(
                    doc.body?.scrollHeight || 0,
                    doc.documentElement?.scrollHeight || 0,
                    240
                );
                iframe.style.height = (h + 32) + 'px';
            } catch (_) { /* cross-origin 等は無視 */ }
        },
        // iframe の document に直接 HTML を書き込む.
        // srcdoc 経由だと一部の HTML メールで body が描画されない事象があったため、
        // document.open() + write() で確実に流し込む. sandbox に allow-same-origin が
        // 付与されている前提で動作する (script-src 'none' で JS は実行されない).
        writeEmailIframe(iframe, email) {
            try {
                const html = this.iframeSrcDocFor(email);
                const doc = iframe.contentDocument || iframe.contentWindow?.document;
                if (!doc) {
                    // contentDocument が触れない場合は srcdoc にフォールバック
                    iframe.setAttribute('srcdoc', html);
                    return;
                }
                doc.open();
                doc.write(html);
                doc.close();
                // 描画完了後にサイズ調整 (画像読み込み完了も拾うため少し待つ)
                requestAnimationFrame(() => this.resizeEmailIframe(iframe));
                setTimeout(() => this.resizeEmailIframe(iframe), 200);
            } catch (e) {
                // 失敗したら srcdoc にフォールバック
                try { iframe.setAttribute('srcdoc', this.iframeSrcDocFor(email)); } catch (_) {}
                console.warn('[writeEmailIframe] fallback to srcdoc:', e);
            }
        },

        async updateSingleEmailStatus(threadId, status) {
            try {
                const res = await fetch(`/threads/${threadId}/status`, { method: 'PUT', headers: this.jsonHeaders(), body: JSON.stringify({ status }) });
                if (res.ok) this.toast('マージ元の状態を更新しました', 'success');
                else this.toast('更新に失敗しました', 'error');
            } catch(e) { this.toast('更新に失敗しました', 'error'); }
        },

        async updateThreadStatus(thread, status) {
            try {
                const idx = this.threads.findIndex(t => t.id === thread.id);
                const nextThreadId = (idx !== -1 && idx < this.threads.length - 1) ? this.threads[idx + 1].id : null;

                // ★ Undo 用に旧 status を記録 (PUT 成功後に履歴へ積む)
                const oldStatus = thread.status;
                const threadId = thread.id;

                const res = await fetch(`/threads/${thread.id}/status`, { method: 'PUT', headers: this.jsonHeaders(), body: JSON.stringify({ status }) });
                if (!res.ok) { this.toast('ステータス更新に失敗しました', 'error'); return; }

                if (this.selectedThread) this.selectedThread.status = status;
                this.toast(`「${this.statusLabels[status] || status}」に変更しました`, 'success');

                // Undo: 同じ thread を oldStatus に戻す
                if (oldStatus && oldStatus !== status) {
                    this._pushUndoAction(
                        `ステータス変更 #${threadId} → ${this.statusLabels[status] || status}`,
                        async () => {
                            const r = await fetch(`/threads/${threadId}/status`, {
                                method: 'PUT',
                                headers: this.jsonHeaders(),
                                body: JSON.stringify({ status: oldStatus }),
                            });
                            if (!r.ok) throw new Error('ステータスを戻せませんでした');
                            await this.loadThreads();
                            this.loadEmailRooms();
                        }
                    );
                }

                if (!this.selectionMode && (status === 'hold' || status === 'completed')) {
                    await this.loadThreads();
                    if (nextThreadId) {
                        await this.loadThread(nextThreadId);
                    } else {
                        this.closeWorkspace();
                    }
                } else {
                    await this.loadThreads();
                }
            } catch(e) { this.toast('ステータス更新に失敗しました', 'error'); }
        },

        startResizeThreadList(e) {
            const startX = e.clientX, startW = this.threadWidth;
            const onMove = (me) => { this.threadWidth = Math.max(300, Math.min(700, startW + (me.clientX - startX))); };
            const onUp = () => { localStorage.setItem('threadWidth', this.threadWidth); document.removeEventListener('mousemove', onMove); document.removeEventListener('mouseup', onUp); };
            document.addEventListener('mousemove', onMove); document.addEventListener('mouseup', onUp);
        },

        // スレッド内チャットパネルの幅をドラッグでリサイズ (左端ハンドル: 左ドラッグで広く、右ドラッグで狭く)
        startResizeChatPanel(e) {
            const startX = e.clientX, startW = this.chatPanelWidth;
            const prevUserSelect = document.body.style.userSelect;
            document.body.style.userSelect = 'none';
            document.body.style.cursor = 'col-resize';
            const onMove = (me) => {
                this.chatPanelWidth = Math.max(280, Math.min(900, startW - (me.clientX - startX)));
            };
            const onUp = () => {
                localStorage.setItem('chatPanelWidth', this.chatPanelWidth);
                document.body.style.userSelect = prevUserSelect;
                document.body.style.cursor = '';
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        }
    }
}
</script>

<style>
[x-cloak] { display: none !important; }

/* ===== AI チャットパネル: メッセージふきだし =====
   Tailwind の flex utility 経由だと align-items:stretch で吹き出しが行高さまで
   引き伸ばされる事故が出るため, 全部素の CSS で content-sized に固定する. */
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
    align-self: flex-start;  /* 行高さに引き伸ばされない */
    width: auto;
    flex: 0 0 auto;          /* flex で伸縮しない */
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
.rice-ai-msg-pending {
    display: inline-flex; align-items: center; gap: 6px;
    color: #6b7280; font-size: 12px;
}
.rice-ai-msg-error {
    color: #b91c1c; font-size: 12px;
    display: inline-flex; align-items: center; gap: 6px;
}
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
.rice-ai-msg-action-btn:hover { background: #e0e7ff; color: #4338ca; }
.rice-ai-msg-elapsed { margin-left: auto; }

/* ===== /スキル と /コレクション の青チップ表示 (チャット履歴 + textarea オーバーレイ共用) ===== */
.rice-ai-tag {
    background-color: #dbeafe;   /* tailwind blue-100 */
    color: #1d4ed8;              /* tailwind blue-700 */
    border-radius: 4px;
    padding: 1px 6px;
    margin: 0 1px;
    font-weight: 700;
    font-size: 0.95em;
    box-shadow: inset 0 0 0 1px rgba(29, 78, 216, 0.18);
    white-space: nowrap;
}

/* textarea にチップを重ねるためのオーバーレイ. textarea のテキストを透明にして
   下のハイライト div だけ見せる. caret は textarea のものをそのまま表示. */
.rice-ai-input-wrap { position: relative; flex: 1; min-width: 0; }
.rice-ai-input-highlight,
.rice-ai-input-wrap textarea {
    font-family: inherit;
    font-size: 13px;
    line-height: 1.5;
    padding: 8px 12px;
    letter-spacing: normal;
    word-spacing: normal;
    tab-size: 4;
}
.rice-ai-input-highlight {
    position: absolute; inset: 0;
    border: 1px solid transparent; border-radius: 8px;
    pointer-events: none;
    white-space: pre-wrap; word-wrap: break-word; overflow: hidden;
    color: #111827; background: transparent;
    z-index: 1;
}
.rice-ai-input-wrap textarea {
    position: relative; z-index: 2;
    width: 100%;
    background: #f9fafb !important;
    color: transparent !important;
    -webkit-text-fill-color: transparent;
    caret-color: #111827;
    border: 1px solid #e5e7eb; border-radius: 8px;
    outline: none; resize: none;
}
.rice-ai-input-wrap textarea::selection { background-color: rgba(29, 78, 216, 0.18); color: transparent; }
.custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
.active { box-shadow: inset 0 0 0 1px rgba(37, 99, 235, 0.1); }

/* ===== スレッド内チャット (ライト + メッセージ行レイアウト) ===== */
.thread-chat-panel { background-color:#ffffff; color:#111827; border-left:1px solid #e5e7eb; }
.thread-chat-resize:hover, .thread-chat-resize:active { background-color:#3b82f6; }
.thread-chat-header { background-color:#f9fafb; border-bottom:1px solid #e5e7eb; }
.thread-chat-hash { color:#9ca3af; font-weight:700; font-size:18px; }
.thread-chat-close { color:#6b7280; padding:6px; border-radius:6px; transition:all 0.15s; }
.thread-chat-close:hover { background-color:#f3f4f6; color:#111827; }
.thread-chat-messages { background-color:#ffffff; padding:12px 0; }
.thread-chat-messages .msg-row {
    padding: 8px 12px 8px 56px; position:relative; min-height:36px;
    border-bottom: 1px solid #f1f5f9;
}
.thread-chat-messages .msg-row:last-child { border-bottom:none; }
.thread-chat-messages .msg-row:hover { background-color:#f3f4f6; }
.thread-chat-messages .msg-row .avatar {
    position:absolute; left:16px; top:4px;
    width:34px; height:34px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-weight:700; font-size:13px;
}
.thread-chat-messages .msg-row .ts-header { display:flex; align-items:baseline; gap:6px; margin-bottom:2px; }
.thread-chat-messages .msg-row .author { color:#111827; font-weight:600; font-size:15px; }
.thread-chat-messages .msg-row .ts { color:#9ca3af; font-size:12px; }
.thread-chat-messages .msg-row .body { color:#1f2937; font-size:15px; line-height:1.6; white-space:pre-wrap; word-wrap:break-word; }
.thread-chat-messages .msg-row .msg-actions {
    position:absolute; right:8px; top:4px;
    background:#ffffff; border:1px solid #e5e7eb; border-radius:4px;
    color:#6b7280; padding:3px 7px; font-size:11px;
}
.thread-chat-messages .msg-row .msg-actions:hover { color:#dc2626; border-color:#fca5a5; background:#fef2f2; }
.thread-chat-input-wrap { background-color:#f9fafb; padding:0 12px 12px; border-top:1px solid #e5e7eb; padding-top:10px; }
.thread-chat-input-box {
    background-color:#ffffff; border:1px solid #e5e7eb; border-radius:10px;
    display:flex; align-items:flex-end; gap:6px; padding:8px 12px;
    box-shadow:0 1px 2px rgba(0,0,0,0.04);
}
.thread-chat-input-box:focus-within { border-color:#93c5fd; box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
.thread-chat-input-box textarea {
    flex:1; background:transparent; border:none; outline:none; resize:none;
    color:#111827; font-size:15px; line-height:1.5; max-height:200px;
}
.thread-chat-input-box textarea::placeholder { color:#9ca3af; }
.thread-chat-send { color:#2563eb; background:transparent; border:none; padding:4px; }
.thread-chat-send:not(:disabled):hover { color:#1d4ed8; }
.thread-chat-kbd { background:#f3f4f6; border:1px solid #e5e7eb; padding:1px 4px; border-radius:3px; font-size:10px; color:#4b5563; }

/* ===== /コレクション をグレーチップで可視化 (AI 要約モーダル用) ===== */
.prompt-editor-container { position: relative; background-color: #ffffff; border-radius: 0.5rem; }
.prompt-editor-highlight,
.prompt-editor-input {
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Hiragino Kaku Gothic ProN", "Yu Gothic", sans-serif;
    font-size: 0.875rem;       /* text-sm */
    line-height: 1.5;
    padding: 0.5rem 0.75rem;   /* px-3 py-2 */
}
.prompt-editor-highlight {
    position: absolute; inset: 0;
    border: 1px solid transparent; border-radius: 0.5rem;
    pointer-events: none; white-space: pre-wrap; word-wrap: break-word;
    overflow-y: auto; color: #111827; background: transparent; z-index: 1;
}
.prompt-editor-input {
    position: relative; z-index: 2;
    background: transparent !important;
    color: transparent !important;
    -webkit-text-fill-color: transparent;
    caret-color: #111827;
}
.prompt-editor-input::selection { background-color: rgba(99, 102, 241, 0.25); color: transparent; }
.prompt-editor-highlight .col-tag {
    background-color: #e5e7eb; color: #374151;
    border-radius: 4px; padding: 1px 2px; margin: 0 -1px;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.04);
}

/* アイコンボタン共通 (背景・ボーダーは Tailwind ユーティリティに任せる) */
.icon-btn {
    width: 2.25rem;
    height: 2.25rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.625rem;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
    flex-shrink: 0;
    position: relative;
    cursor: pointer;
}
/* 新規作成ボタンは Tailwind 未ビルドでも色が出るように補強 */
.compose-btn:hover { background-color:#1d4ed8 !important; }

/* ツールチップは title 属性のブラウザ標準表示を使用 (カスタム CSS は撤去) */

/* キーボードショートカット ヘルプ用の <kbd> スタイル.
   システムフォントで小さく、キャップキーらしく見せる. */
.rice-kbd {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Roboto Mono", monospace;
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
    color: #374151;
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-bottom-width: 2px;
    border-radius: 4px;
    box-shadow: 0 1px 0 rgba(0,0,0,.04);
    text-transform: uppercase;
}
</style>
@endsection
