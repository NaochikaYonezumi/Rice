<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Rice')</title>
    {{-- テーマ早期適用 (描画前にローカルストレージから読み込んで <html> にクラス付与) --}}
    <script>
        (function() {
            try {
                var t = localStorage.getItem('riceTheme');
                if (t === 'dark') document.documentElement.classList.add('theme-dark');
            } catch (e) {}
            // ===== ルーム/スレッド コンテキスト変更時のイベントブロードキャスト =====
            // localStorage.setItem / removeItem を監視し、'currentRoomId' / 'currentThreadId'
            // が変わったら 'rice-room-context-changed' を window にディスパッチ。
            // 同一タブ内の Wiki ボタン (navbar) や他コンポーネントが即座に反応できるようにする。
            try {
                var _watchKeys = ['currentRoomId', 'currentThreadId'];
                var origSet = Storage.prototype.setItem;
                var origDel = Storage.prototype.removeItem;
                Storage.prototype.setItem = function(k, v) {
                    var prev = this.getItem(k);
                    var ret = origSet.apply(this, arguments);
                    if (this === window.localStorage && _watchKeys.indexOf(k) !== -1 && prev !== String(v)) {
                        try { window.dispatchEvent(new CustomEvent('rice-room-context-changed')); } catch (_) {}
                    }
                    return ret;
                };
                Storage.prototype.removeItem = function(k) {
                    var had = this.getItem(k) !== null;
                    var ret = origDel.apply(this, arguments);
                    if (this === window.localStorage && _watchKeys.indexOf(k) !== -1 && had) {
                        try { window.dispatchEvent(new CustomEvent('rice-room-context-changed')); } catch (_) {}
                    }
                    return ret;
                };
            } catch (e) {}
            // ===== Ctrl + Shift + L でダーク / ライトを切替 =====
            try {
                document.addEventListener('keydown', function (e) {
                    if (!e.ctrlKey || !e.shiftKey) return;
                    // 'KeyL' (キー位置で判定) または lower/upper-case L
                    if (e.code !== 'KeyL' && e.key !== 'L' && e.key !== 'l') return;
                    e.preventDefault();
                    var cur = localStorage.getItem('riceTheme') === 'dark' ? 'dark' : 'light';
                    var next = cur === 'dark' ? 'light' : 'dark';
                    try { localStorage.setItem('riceTheme', next); } catch (_) {}
                    if (next === 'dark') document.documentElement.classList.add('theme-dark');
                    else document.documentElement.classList.remove('theme-dark');
                });
            } catch (e) {}

            // ===== グローバル ナビ ショートカット =====
            //   Alt+M → メール (/emails)
            //   Alt+C → チャット (/chats)
            //   Alt+A → 添付 (/attachments)
            //   Alt+W → Wiki (/wiki)
            //   Alt+R → ルーム管理 (/rooms)
            // Alt 修飾を使うのは、各画面の単一キー (J/K/E/R/D 等) と
            // ブラウザ標準のショートカット (Ctrl+T 等) を避けるため。
            // input/textarea にフォーカス中とモーダル表示中はスキップ.
            try {
                var NAV_TARGETS = {
                    'm': '{{ route('emails.index') }}',
                    'c': '{{ route('chats.index') }}',
                    'a': '{{ route('attachments.index') }}',
                    'w': '{{ route('wiki.index') }}',
                    'r': '{{ route('rooms.index') }}'
                };
                document.addEventListener('keydown', function (e) {
                    if (!e.altKey) return;
                    if (e.ctrlKey || e.metaKey || e.shiftKey) return;
                    var key = (e.key || '').toLowerCase();
                    var dest = NAV_TARGETS[key];
                    if (!dest) return;
                    // 入力欄フォーカス中はスキップ
                    var t = e.target;
                    var tag = (t && t.tagName || '').toLowerCase();
                    if (tag === 'input' || tag === 'textarea' || tag === 'select' || (t && t.isContentEditable)) return;
                    // 開いてるモーダル / ドロップダウンを大雑把に検知してスキップ
                    if (document.querySelector('.modal.show, .rice-modal-backdrop, [class*="z-["][class*="000"]')) {
                        // Alpine モーダルだと検出漏れがあるが、最低限の安全弁.
                    }
                    e.preventDefault();
                    if (window.location.pathname === new URL(dest, window.location.origin).pathname) return; // 同じページなら何もしない
                    window.location.href = dest;
                });
            } catch (e) {}
        })();
    </script>
    
    <!-- AdminLTE & Bootstrap CSS (FreeScout Style) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* 左右の余白は基本ゼロにする (フル幅レイアウト) */
        .content,
        .content > .container-fluid,
        .content-wrapper > .content,
        section.content,
        section.content > .container-fluid {
            padding-left: 0 !important;
            padding-right: 0 !important;
            max-width: 100% !important;
        }
        /* Bootstrap container も 100% 幅 */
        .container-fluid { padding-left: 0 !important; padding-right: 0 !important; max-width: 100% !important; }

        /* =========================================================
           サイドバー: 普段は折り畳み (アイコンのみ)
           マウスホバーで展開 (テキスト表示)
           ========================================================= */
        :root {
            --sidebar-collapsed: 60px;
            --sidebar-expanded: 220px;
        }
        .main-sidebar {
            width: var(--sidebar-collapsed) !important;
            transition: width 0.2s ease-in-out;
            z-index: 1050;        /* コンテンツより前面で展開 */
            overflow: hidden;
        }
        .main-sidebar:hover {
            width: var(--sidebar-expanded) !important;
            box-shadow: 4px 0 12px rgba(0, 0, 0, 0.08);
        }
        /* コンテンツ・ヘッダー・フッターの margin は折り畳み幅で固定 (展開時はサイドバーが浮き上がる) */
        .main-header.navbar,
        .content-wrapper,
        .main-footer {
            margin-left: var(--sidebar-collapsed) !important;
            transition: margin-left 0.2s ease-in-out;
        }

        /* ラベル文字は通常非表示・ホバーでフェードイン */
        .main-sidebar .nav-link p,
        .main-sidebar .brand-text,
        .main-sidebar .nav-header,
        .main-sidebar .badge {
            opacity: 0;
            white-space: nowrap;
            transition: opacity 0.15s ease-in-out;
        }
        .main-sidebar:hover .nav-link p,
        .main-sidebar:hover .brand-text,
        .main-sidebar:hover .nav-header,
        .main-sidebar:hover .badge {
            opacity: 1;
        }

        /* 折り畳み時はアイコン中央寄せ */
        .main-sidebar .nav-icon {
            margin-right: 0 !important;
            text-align: center;
            width: 1.6rem;
        }
        .main-sidebar:hover .nav-icon {
            margin-right: 0.5rem !important;
        }
        /* ブランドリンク */
        .main-sidebar .brand-link {
            display: flex !important;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1.15rem 0.5rem !important;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            transition: all 0.15s;
            background: linear-gradient(180deg, rgba(59,130,246,0.08), rgba(59,130,246,0));
        }
        .main-sidebar:hover .brand-link {
            justify-content: flex-start;
            padding-left: 1rem !important;
        }
        .main-sidebar .brand-link:hover { background-color: rgba(255,255,255,0.06); }
        .main-sidebar .brand-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: #ffffff;
            border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 20px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(59,130,246,0.5);
            letter-spacing: -0.02em;
        }
        .main-sidebar .brand-text {
            color: #ffffff !important;
            font-weight: 800 !important;
            font-size: 22px;
            letter-spacing: 0.03em;
            white-space: nowrap;
            opacity: 0; transition: opacity 0.15s;
            text-shadow: 0 1px 2px rgba(0,0,0,0.25);
        }
        .main-sidebar:hover .brand-text { opacity: 1; }

        /* モバイル: ホバーが効かないので常時折り畳み (タップで展開する場合は body.sidebar-open など別実装が必要) */
        @media (max-width: 991px) {
            .main-header.navbar,
            .content-wrapper,
            .main-footer { margin-left: 0 !important; }
            .main-sidebar { display: none; }
        }

        /* content-header の余白調整 (使うページのみ) */
        .content-header { padding: .75rem 1rem; }
        .content-header h1 { font-size: 1.4rem; }

        /* ===== 統一モーダル (ルーム作成 / ルームに追加 等で共通利用) ===== */
        .rice-modal-backdrop {
            position: fixed; inset: 0;
            background: rgba(15,23,42,0.55); backdrop-filter: blur(2px);
            z-index: 9998;
        }
        .rice-modal {
            position: fixed; top: 50%; left: 50%;
            transform: translate(-50%,-50%);
            background: #ffffff; border-radius: 14px;
            display: flex; flex-direction: column;
            box-shadow: 0 24px 60px rgba(0,0,0,0.32);
            z-index: 9999; overflow: hidden;
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
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 9px 12px; font-size: 13px; color: #0f172a;
            outline: none; transition: border-color .15s, box-shadow .15s, background-color .15s;
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

        /* ===== グローバル UI 密度圧縮 (要望:「UI が大きすぎる」) =====
           主に settings 系ページで大きすぎた見出しや padding を抑える。
           emails / chats など既に密度の高いページには影響しない (それらは独自の小さい
           Tailwind ユーティリティ + 専用 class を使っているため). */
        .content-wrapper h1.text-3xl,
        .content-wrapper h1.text-4xl { font-size: 20px !important; line-height: 1.3 !important; }
        .content-wrapper h2.text-2xl { font-size: 17px !important; line-height: 1.3 !important; }
        .content-wrapper h3.text-xl,
        .content-wrapper h3.text-lg { font-size: 14px !important; line-height: 1.4 !important; }
        /* settings ページの大きすぎる外余白 (py-8 / py-10 / p-10) を 1rem 系に */
        .content-wrapper .px-10 { padding-left: 1.25rem !important; padding-right: 1.25rem !important; }
        .content-wrapper .py-10 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
        .content-wrapper .py-8  { padding-top: 0.75rem !important; padding-bottom: 0.75rem !important; }
        .content-wrapper .p-10  { padding: 1rem !important; }
        .content-wrapper .p-8   { padding: 1rem !important; }
        .content-wrapper .space-y-10 > * + * { margin-top: 1.25rem !important; }
        .content-wrapper .space-y-8 > * + *  { margin-top: 1rem !important; }
        .content-wrapper .rounded-3xl { border-radius: 12px !important; }
        .content-wrapper .rounded-2xl { border-radius: 10px !important; }
    </style>

    {{-- ===== ダークモード (Discord 配色ベース) ===== --}}
    <style>
        /* Discord パレット */
        html.theme-dark {
            --rd-bg:        #36393f; /* メインエリア */
            --rd-bg-2:      #2f3136; /* サイドバー */
            --rd-bg-3:      #202225; /* 最外サイド / ヘッダ濃 */
            --rd-bg-hover:  #34363c;
            --rd-bg-active: #393c43;
            --rd-text:      #dcddde;
            --rd-text-mute: #b9bbbe;
            --rd-text-dim:  #8e9297;
            --rd-border:    #42454a;
            --rd-border-2:  #2b2d31;
            --rd-brand:     #5865f2;
            --rd-brand-h:   #4752c4;
            --rd-accent:    #00b0f4;
            --rd-success:   #57f287;
            --rd-warn:      #faa61a;
            --rd-danger:    #ed4245;
        }
        /* ベース */
        html.theme-dark body,
        html.theme-dark .content-wrapper,
        html.theme-dark .content,
        html.theme-dark .content > .container-fluid {
            background-color: var(--rd-bg) !important;
            color: var(--rd-text);
        }
        html.theme-dark { color-scheme: dark; }
        html.theme-dark body { color: var(--rd-text); }

        /* AdminLTE ナビバー (上部) */
        html.theme-dark .main-header.navbar,
        html.theme-dark .main-header.navbar {
            background-color: var(--rd-bg-3) !important;
            border-bottom: 1px solid var(--rd-border-2) !important;
            color: var(--rd-text) !important;
        }
        html.theme-dark .main-header.navbar .nav-link,
        html.theme-dark .main-header.navbar a { color: var(--rd-text) !important; }
        html.theme-dark .main-header.navbar .nav-link:hover { color: #fff !important; }

        /* AdminLTE 左サイドバー */
        html.theme-dark .main-sidebar { background-color: var(--rd-bg-3) !important; }
        html.theme-dark .main-sidebar .brand-link { background-color: var(--rd-bg-3) !important; color: var(--rd-text) !important; border-bottom: 1px solid var(--rd-border-2) !important; }
        html.theme-dark .nav-sidebar .nav-link { color: var(--rd-text-mute) !important; }
        html.theme-dark .nav-sidebar .nav-link:hover { background-color: var(--rd-bg-hover) !important; color: #fff !important; }
        html.theme-dark .nav-sidebar .nav-link.active { background-color: var(--rd-brand) !important; color: #fff !important; }

        /* スクロールバー */
        html.theme-dark *::-webkit-scrollbar-thumb { background:#4f5258 !important; }
        html.theme-dark *::-webkit-scrollbar-thumb:hover { background:#5e6166 !important; }
        html.theme-dark *::-webkit-scrollbar-track { background: transparent; }

        /* 汎用: Tailwind のホワイトベース系を上書き — Discord 流に控えめに */
        html.theme-dark .bg-white { background-color: var(--rd-bg-2) !important; color: var(--rd-text); }
        html.theme-dark .bg-gray-50 { background-color: var(--rd-bg) !important; color: var(--rd-text); }
        html.theme-dark .bg-gray-100 { background-color: var(--rd-bg-hover) !important; color: var(--rd-text); }
        html.theme-dark .bg-gray-200 { background-color: var(--rd-bg-active) !important; color: var(--rd-text); }
        /* 色ベース背景 (bg-blue-50 等) は派手な色付け禁止。すべてニュートラルなダークに */
        html.theme-dark .bg-blue-50,
        html.theme-dark .bg-indigo-50,
        html.theme-dark .bg-amber-50,
        html.theme-dark .bg-emerald-50,
        html.theme-dark .bg-red-50,
        html.theme-dark .bg-purple-50,
        html.theme-dark .bg-pink-50,
        html.theme-dark .bg-yellow-50,
        html.theme-dark .bg-green-50,
        html.theme-dark .bg-orange-50,
        html.theme-dark .bg-rose-50,
        html.theme-dark .bg-sky-50,
        html.theme-dark .bg-teal-50,
        html.theme-dark .bg-cyan-50,
        html.theme-dark .bg-slate-50,
        html.theme-dark .bg-slate-100,
        html.theme-dark .bg-zinc-50,
        html.theme-dark .bg-zinc-100,
        html.theme-dark .bg-neutral-50,
        html.theme-dark .bg-neutral-100 { background-color: var(--rd-bg-hover) !important; color: var(--rd-text); }
        /* 同様に -100 系もニュートラル化 */
        html.theme-dark .bg-blue-100,
        html.theme-dark .bg-indigo-100,
        html.theme-dark .bg-amber-100,
        html.theme-dark .bg-emerald-100,
        html.theme-dark .bg-red-100,
        html.theme-dark .bg-purple-100,
        html.theme-dark .bg-pink-100,
        html.theme-dark .bg-yellow-100,
        html.theme-dark .bg-green-100,
        html.theme-dark .bg-orange-100 { background-color: var(--rd-bg-active) !important; color: var(--rd-text); }

        /* テキスト色 */
        html.theme-dark .text-gray-900 { color: var(--rd-text) !important; }
        html.theme-dark .text-gray-800 { color: var(--rd-text) !important; }
        html.theme-dark .text-gray-700 { color: var(--rd-text) !important; }
        html.theme-dark .text-gray-600 { color: var(--rd-text-mute) !important; }
        html.theme-dark .text-gray-500 { color: var(--rd-text-mute) !important; }
        html.theme-dark .text-gray-400 { color: var(--rd-text-dim) !important; }
        html.theme-dark .text-gray-300 { color: var(--rd-text-dim) !important; }
        html.theme-dark .text-black { color: var(--rd-text) !important; }
        html.theme-dark .text-white { color: #fff !important; }

        /* ボーダー色 */
        html.theme-dark .border,
        html.theme-dark .border-gray-100,
        html.theme-dark .border-gray-200,
        html.theme-dark .border-gray-300,
        html.theme-dark [class*="border-"] { border-color: var(--rd-border) !important; }
        html.theme-dark .border-blue-100,
        html.theme-dark .border-blue-200 { border-color: rgba(88,101,242,0.5) !important; }

        /* フォーム要素 */
        html.theme-dark input,
        html.theme-dark textarea,
        html.theme-dark select {
            background-color: var(--rd-bg-3) !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }
        html.theme-dark input::placeholder,
        html.theme-dark textarea::placeholder { color: var(--rd-text-dim) !important; }
        html.theme-dark input:focus,
        html.theme-dark textarea:focus,
        html.theme-dark select:focus {
            outline: none !important;
            border-color: var(--rd-brand) !important;
            box-shadow: 0 0 0 2px rgba(88,101,242,0.3) !important;
        }
        html.theme-dark .form-control { background-color: var(--rd-bg-3) !important; color: var(--rd-text) !important; border-color: var(--rd-border) !important; }
        html.theme-dark input[type="checkbox"], html.theme-dark input[type="radio"] { background:transparent !important; }

        /* ドロップダウン / メニュー */
        html.theme-dark .dropdown-menu {
            background-color: var(--rd-bg-2) !important;
            border-color: var(--rd-border) !important;
            color: var(--rd-text);
        }
        html.theme-dark .dropdown-item { color: var(--rd-text); }
        html.theme-dark .dropdown-item:hover { background-color: var(--rd-bg-hover); color: #fff; }
        html.theme-dark .dropdown-header { color: var(--rd-text-mute); }

        /* ボタン群: Tailwind の青ボタン以外も色味を落ち着かせる */
        html.theme-dark .btn,
        html.theme-dark button.btn-default,
        html.theme-dark button.btn-secondary { background-color: var(--rd-bg-hover); color: var(--rd-text); border-color: var(--rd-border); }
        html.theme-dark .btn-primary { background-color: var(--rd-brand) !important; border-color: var(--rd-brand) !important; }
        html.theme-dark .btn-primary:hover { background-color: var(--rd-brand-h) !important; }
        html.theme-dark .btn-danger { background-color: var(--rd-danger) !important; border-color: var(--rd-danger) !important; }

        /* ホバー系 (Tailwind hover:bg-gray-50/100 等) */
        html.theme-dark *[class*="hover:bg-gray-50"]:hover,
        html.theme-dark *[class*="hover:bg-gray-100"]:hover { background-color: var(--rd-bg-hover) !important; }
        html.theme-dark *[class*="hover:bg-blue-50"]:hover { background-color: rgba(88,101,242,0.2) !important; }

        /* リング / シャドウ系 */
        html.theme-dark .shadow,
        html.theme-dark .shadow-sm,
        html.theme-dark .shadow-md,
        html.theme-dark .shadow-lg,
        html.theme-dark .shadow-xl,
        html.theme-dark .shadow-2xl { box-shadow: 0 4px 12px rgba(0,0,0,0.4) !important; }

        /* チャット画面 (chats-root 内) の固有スタイル上書き */
        html.theme-dark .chat-sidebar { background:var(--rd-bg-2) !important; border-right-color:var(--rd-border-2) !important; }
        html.theme-dark .chat-sidebar-head { background:var(--rd-bg-2) !important; color:var(--rd-text); border-bottom-color:var(--rd-border-2) !important; }
        html.theme-dark .chat-sidebar-head h3 { color: var(--rd-text) !important; }
        html.theme-dark .chat-sidebar-section { color: var(--rd-text-dim) !important; }
        html.theme-dark .chat-channel { color: var(--rd-text-mute); }
        html.theme-dark .chat-channel:hover { background: var(--rd-bg-hover); color: #fff; }
        /* 選択中: 派手な青塗りはやめ Discord 風のニュートラル濃灰 + 白文字に */
        html.theme-dark .chat-channel.active { background: var(--rd-bg-active) !important; color: #fff !important; }
        html.theme-dark .chat-channel .name { color: inherit; }
        html.theme-dark .chat-channel .hash { color: var(--rd-text-dim); }
        html.theme-dark .chat-channel.active .hash { color: rgba(255,255,255,0.85); }
        html.theme-dark .chat-main { background: var(--rd-bg) !important; }
        html.theme-dark .chat-header { background: var(--rd-bg) !important; color: var(--rd-text) !important; border-bottom-color: var(--rd-border-2) !important; }
        html.theme-dark .chat-header h2 { color: var(--rd-text) !important; }
        html.theme-dark .chat-messages { background: var(--rd-bg) !important; }
        html.theme-dark .chat-input-wrap { background: var(--rd-bg) !important; }
        html.theme-dark .chat-input-box { background: var(--rd-bg-2) !important; border-color: var(--rd-border) !important; }
        html.theme-dark .chat-input-box textarea { background: transparent !important; color: var(--rd-text) !important; }
        html.theme-dark .orig-thread-panel { background: var(--rd-bg-2) !important; border-left-color: var(--rd-border-2) !important; color: var(--rd-text); }
        html.theme-dark .msg-row,
        html.theme-dark .msg-row.compact { color: var(--rd-text); }
        html.theme-dark .msg-row .body { color: var(--rd-text) !important; }
        html.theme-dark .msg-row .author { color: var(--rd-text) !important; }
        html.theme-dark .msg-row .ts,
        html.theme-dark .msg-row .floating-ts { color: var(--rd-text-dim) !important; }
        html.theme-dark .msg-row .msg-actions { background: var(--rd-bg-2); border-color: var(--rd-border); }

        /* メール一覧 / 添付ファイル サイドバー固有 */
        html.theme-dark .mail-rooms-sidebar { background:var(--rd-bg-2) !important; border-right-color:var(--rd-border-2) !important; color: var(--rd-text); }
        html.theme-dark .mail-rooms-head h3 { color: var(--rd-text) !important; }
        html.theme-dark .mail-room-item { color: var(--rd-text-mute); }
        html.theme-dark .mail-room-item:hover { background: var(--rd-bg-hover); color: #fff; }
        html.theme-dark .mail-room-item.active { background: var(--rd-bg-active) !important; color:#fff !important; }
        html.theme-dark .att-rooms-sidebar { background:var(--rd-bg-2) !important; border-right-color:var(--rd-border-2) !important; color: var(--rd-text); }
        html.theme-dark .att-rooms-head h3 { color: var(--rd-text) !important; }
        html.theme-dark .att-room-item { color: var(--rd-text-mute); }
        html.theme-dark .att-room-item:hover { background: var(--rd-bg-hover); color: #fff; }
        html.theme-dark .att-room-item.active { background: var(--rd-bg-active) !important; color:#fff !important; }

        /* メール一覧本体 */
        html.theme-dark .email-item { background-color: var(--rd-bg-2) !important; color: var(--rd-text); border-color: var(--rd-border-2) !important; }
        html.theme-dark .email-item:hover { background-color: var(--rd-bg-hover) !important; }
        html.theme-dark .thread-list-row { color: var(--rd-text); }

        /* グローバル Wiki ドロワー */
        html.theme-dark aside[x-show="open"] { background: var(--rd-bg-2) !important; color: var(--rd-text); }
        /* ドロワー内カードはグリッド表示 */
        .global-wiki-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            align-content: start;
        }
        .global-wiki-full { grid-column: 1 / -1; }

        /* モーダル系 (固定背景) */
        html.theme-dark .modal-content,
        html.theme-dark .bundle-modal { background: var(--rd-bg-2) !important; color: var(--rd-text); border-color: var(--rd-border) !important; }
        html.theme-dark .bundle-modal-head { background: var(--rd-bg-3) !important; color: var(--rd-text); border-bottom-color: var(--rd-border) !important; }

        /* Tailwind の hover:bg-* 上書き対応 */
        html.theme-dark .hover\:bg-gray-50:hover,
        html.theme-dark .hover\:bg-gray-100:hover { background-color: var(--rd-bg-hover) !important; }

        /* gradient 背景 (プロフィールメニューヘッダー等は明るすぎるので暗く落ち着いた色に) */
        html.theme-dark [style*="linear-gradient"][style*="2563eb"] { background: linear-gradient(135deg, var(--rd-bg-3), var(--rd-bg-2)) !important; }
        /* AdminLTE 左サイドバーの active ナビは派手な青塗りを抑える */
        html.theme-dark .nav-sidebar .nav-link.active { background-color: var(--rd-bg-active) !important; color: #fff !important; }

        /* ===== Discord 風の落ち着いた配色補正 (派手なハイライト抑制) ===== */
        /* メンション行: 明るいオレンジを抑える */
        html.theme-dark .msg-row.is-mentioned-me {
            background: rgba(250,166,26,0.08) !important;
            border-left: 3px solid var(--rd-warn) !important;
        }
        html.theme-dark .msg-row.is-mentioned-me:hover { background: rgba(250,166,26,0.12) !important; }
        html.theme-dark .mention-tag { background: rgba(88,101,242,0.25) !important; color:#c7d0ff !important; }
        html.theme-dark .mention-self { background: rgba(250,166,26,0.25) !important; color:#ffd58a !important; border:none !important; }

        /* インライン青ボタン (#2563eb / #3b82f6) を選択中表示として控えめに */
        html.theme-dark [style*="background:#2563eb"],
        html.theme-dark [style*="background-color:#2563eb"],
        html.theme-dark [style*="background:#1d4ed8"],
        html.theme-dark [style*="background:#3b82f6"],
        html.theme-dark [style*="background-color:#3b82f6"] {
            background: var(--rd-bg-active) !important;
            background-color: var(--rd-bg-active) !important;
            color: #fff !important;
        }
        /* 紫 (#7c3aed / #6d28d9) も控えめに */
        html.theme-dark [style*="background:#7c3aed"],
        html.theme-dark [style*="background-color:#7c3aed"] { background-color: var(--rd-bg-active) !important; color:#fff !important; }
        /* 緑 (#10b981) / 黄 (#f59e0b / #fbbf24) / 赤 (#dc2626 / #ef4444) なども下のレイヤーに合わせ強度を下げる */
        html.theme-dark [style*="background:#10b981"],
        html.theme-dark [style*="background-color:#10b981"] { background-color:#2a4a3e !important; color:#aef5c5 !important; }
        html.theme-dark [style*="background:#f59e0b"],
        html.theme-dark [style*="background-color:#f59e0b"] { background-color:#4a3a1f !important; color:#ffd58a !important; }
        html.theme-dark [style*="background:#0ea5e9"],
        html.theme-dark [style*="background-color:#0ea5e9"] { background-color:#1f3a4a !important; color:#a7d8f0 !important; }

        /* navbar の Wiki / メール / チャット / 添付 ピル: 落ち着いた配色 */
        html.theme-dark .navbar [style*="background:#fffbeb"],
        html.theme-dark .navbar [style*="background:#eff6ff"],
        html.theme-dark .navbar [style*="background:#ecfdf5"],
        html.theme-dark .navbar [style*="background:#f0f9ff"] {
            background-color: var(--rd-bg-hover) !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }
        /* navbar disabled (グレー) ピル */
        html.theme-dark .navbar [style*="background:#f3f4f6"] {
            background-color: var(--rd-bg-hover) !important;
            color: var(--rd-text-dim) !important;
            border-color: var(--rd-border) !important;
        }
        /* 「すべて」「自分宛」のチャットサイドバー上部フィルタ: 青系を控えめに */
        html.theme-dark .chat-sidebar-head button[style*="#2563eb"] {
            background: var(--rd-brand) !important;
            color: #fff !important;
        }

        /* バッジ・ピル類のソフト化 */
        html.theme-dark .chat-hidden-badge {
            background: rgba(237,66,69,0.18) !important;
            color: #ff9a9c !important;
            border-color: rgba(237,66,69,0.35) !important;
        }
        html.theme-dark .badge-mention,
        html.theme-dark .badge-count {
            background: var(--rd-bg-active) !important;
            color: var(--rd-text) !important;
        }

        /* チャットメッセージ行の背景交互色を抑制 */
        html.theme-dark .msg-row.bg-alt-a,
        html.theme-dark .msg-row.bg-alt-b,
        html.theme-dark .msg-row.msg-bg-a,
        html.theme-dark .msg-row.msg-bg-b { background-color: var(--rd-bg) !important; }
        html.theme-dark .msg-row { color: var(--rd-text); }
        html.theme-dark .msg-row:hover { background-color: var(--rd-bg-hover) !important; }

        /* リンクの規定色 (青) はテキスト用のソフトな水色に */
        html.theme-dark a { color: var(--rd-accent); }
        html.theme-dark a:hover { color: #8ed7ff; }

        /* グレーの薄ベース・ボーダー */
        html.theme-dark hr,
        html.theme-dark .border-t,
        html.theme-dark .border-b { border-color: var(--rd-border) !important; }

        /* 「ピン留め中」のオレンジハイライト等が浮かないように */
        html.theme-dark [style*="color:#f59e0b"] { color: #f0b34c !important; }
        html.theme-dark [style*="color:#2563eb"],
        html.theme-dark [style*="color:#1d4ed8"] { color: #97a8ff !important; }

        /* Alpine の :style バインディングは rgb() 形式でレンダリングされるため別途上書き */
        html.theme-dark [style*="background-color: rgb(37, 99, 235)"],
        html.theme-dark [style*="background-color: rgb(59, 130, 246)"],
        html.theme-dark [style*="background-color:rgb(37, 99, 235)"],
        html.theme-dark [style*="background-color:rgb(59, 130, 246)"] {
            background-color: var(--rd-bg-active) !important;
        }
        html.theme-dark [style*="background-color: rgb(229, 231, 235)"],
        html.theme-dark [style*="background-color:rgb(229, 231, 235)"] {
            background-color: var(--rd-bg-3) !important;
        }
        /* トグルスイッチ内のホワイトハンドル */
        html.theme-dark [style*="background:#ffffff;border:1px solid #d1d5db"],
        html.theme-dark [style*="background: #ffffff;border:1px solid #d1d5db"] {
            background-color: #d1d5db !important;
            border-color: var(--rd-border) !important;
        }

        /* ===== 明るすぎる inline 背景 (Tailwind 系の薄色を直接 #hex で書いたもの) を一括ダーク化 =====
           Tailwind クラス (bg-blue-50 等) には別途上書きがあるが、:style や style="..." で直接
           色値を書くと適用されないため、attribute selector で個別にキャッチする.
           パターン: "background:..", "background-color:..", "background-color: rgb(..)" の 3 系統.

           光線レベル (lightness 90%超) の色 → bg-hover (= 中濃ダーク) に統一. 文字色は色側ハイライトを少し残す.
           注意: #fff 単体マッチは多くの場所で必要なので別ルールで処理 (もっと幅広い対象). */
        html.theme-dark [style*="background-color:#fff"],
        html.theme-dark [style*="background-color: #fff"],
        html.theme-dark [style*="background:#fff"],
        html.theme-dark [style*="background: #fff"],
        html.theme-dark [style*="background-color:#ffffff"],
        html.theme-dark [style*="background-color: #ffffff"],
        html.theme-dark [style*="background:#ffffff"],
        html.theme-dark [style*="background: #ffffff"] {
            background-color: var(--rd-bg-2) !important;
            color: var(--rd-text);
        }
        /* 上記より狭いセレクタで一部例外 (前述のトグルハンドル) を後発上書き. */
        html.theme-dark [style*="background:#ffffff;border:1px solid #d1d5db"],
        html.theme-dark [style*="background: #ffffff;border:1px solid #d1d5db"] {
            background-color: #d1d5db !important;
        }

        /* slate / gray 系 50-100 を inline で書いた場合 (例: #f1f5f9 (slate-100), #f9fafb (gray-50)) */
        html.theme-dark [style*="background:#f1f5f9"],
        html.theme-dark [style*="background-color:#f1f5f9"],
        html.theme-dark [style*="background:#f8fafc"],
        html.theme-dark [style*="background-color:#f8fafc"],
        html.theme-dark [style*="background:#f9fafb"],
        html.theme-dark [style*="background-color:#f9fafb"],
        html.theme-dark [style*="background:#f3f4f6"],
        html.theme-dark [style*="background-color:#f3f4f6"],
        html.theme-dark [style*="background:#e5e7eb"],
        html.theme-dark [style*="background-color:#e5e7eb"] {
            background-color: var(--rd-bg-hover) !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }

        /* 色付き薄背景 (blue/indigo/amber/emerald/red/purple/pink/yellow/green/orange/rose/sky/teal/cyan の -50/-100) */
        html.theme-dark [style*="background:#eff6ff"],   /* blue-50 */
        html.theme-dark [style*="background-color:#eff6ff"],
        html.theme-dark [style*="background:#dbeafe"],   /* blue-100 */
        html.theme-dark [style*="background-color:#dbeafe"],
        html.theme-dark [style*="background:#eef2ff"],   /* indigo-50 */
        html.theme-dark [style*="background-color:#eef2ff"],
        html.theme-dark [style*="background:#e0e7ff"],   /* indigo-100 */
        html.theme-dark [style*="background-color:#e0e7ff"],
        html.theme-dark [style*="background:#fef3c7"],   /* amber-100 */
        html.theme-dark [style*="background-color:#fef3c7"],
        html.theme-dark [style*="background:#fffbeb"],   /* amber-50 */
        html.theme-dark [style*="background-color:#fffbeb"],
        html.theme-dark [style*="background:#ecfdf5"],   /* emerald-50 */
        html.theme-dark [style*="background-color:#ecfdf5"],
        html.theme-dark [style*="background:#d1fae5"],   /* emerald-100 */
        html.theme-dark [style*="background-color:#d1fae5"],
        html.theme-dark [style*="background:#fef2f2"],   /* red-50 */
        html.theme-dark [style*="background-color:#fef2f2"],
        html.theme-dark [style*="background:#fee2e2"],   /* red-100 */
        html.theme-dark [style*="background-color:#fee2e2"],
        html.theme-dark [style*="background:#f3e8ff"],   /* purple-50/100 */
        html.theme-dark [style*="background-color:#f3e8ff"],
        html.theme-dark [style*="background:#ede9fe"],   /* violet-100 */
        html.theme-dark [style*="background-color:#ede9fe"],
        html.theme-dark [style*="background:#fdf2f8"],   /* pink-50 */
        html.theme-dark [style*="background-color:#fdf2f8"],
        html.theme-dark [style*="background:#fce7f3"],   /* pink-100 */
        html.theme-dark [style*="background-color:#fce7f3"],
        html.theme-dark [style*="background:#fef9c3"],   /* yellow-100 */
        html.theme-dark [style*="background-color:#fef9c3"],
        html.theme-dark [style*="background:#dcfce7"],   /* green-100 */
        html.theme-dark [style*="background-color:#dcfce7"],
        html.theme-dark [style*="background:#fff7ed"],   /* orange-50 */
        html.theme-dark [style*="background-color:#fff7ed"],
        html.theme-dark [style*="background:#ffedd5"],   /* orange-100 */
        html.theme-dark [style*="background-color:#ffedd5"],
        html.theme-dark [style*="background:#f0f9ff"],   /* sky-50 */
        html.theme-dark [style*="background-color:#f0f9ff"],
        html.theme-dark [style*="background:#e0f2fe"],   /* sky-100 */
        html.theme-dark [style*="background-color:#e0f2fe"],
        html.theme-dark [style*="background:#fef0f0"],
        html.theme-dark [style*="background-color:#fef0f0"] {
            background-color: var(--rd-bg-hover) !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }
        /* それらの上のテキスト色 (濃色文字) は色相を残しつつ明度を上げて視認性確保 */
        html.theme-dark [style*="color:#1e40af"],   /* blue-800 */
        html.theme-dark [style*="color:#1d4ed8"],   /* blue-700 */
        html.theme-dark [style*="color: #1e40af"],
        html.theme-dark [style*="color: #1d4ed8"]   { color: #93c5fd !important; }
        html.theme-dark [style*="color:#3730a3"],   /* indigo-800 */
        html.theme-dark [style*="color:#4338ca"],   /* indigo-700 */
        html.theme-dark [style*="color:#4f46e5"],   /* indigo-600 */
        html.theme-dark [style*="color: #3730a3"],
        html.theme-dark [style*="color: #4338ca"],
        html.theme-dark [style*="color: #4f46e5"] { color: #a5b4fc !important; }
        html.theme-dark [style*="color:#92400e"],   /* amber-800 */
        html.theme-dark [style*="color:#b45309"],   /* amber-700 */
        html.theme-dark [style*="color: #92400e"],
        html.theme-dark [style*="color: #b45309"]   { color: #fcd34d !important; }
        html.theme-dark [style*="color:#047857"],   /* emerald-700 */
        html.theme-dark [style*="color:#065f46"],   /* emerald-800 */
        html.theme-dark [style*="color: #047857"],
        html.theme-dark [style*="color: #065f46"]   { color: #6ee7b7 !important; }
        html.theme-dark [style*="color:#b91c1c"],   /* red-700 */
        html.theme-dark [style*="color:#991b1b"],   /* red-800 */
        html.theme-dark [style*="color: #b91c1c"],
        html.theme-dark [style*="color: #991b1b"]   { color: #fca5a5 !important; }
        html.theme-dark [style*="color:#6d28d9"],   /* violet-700 */
        html.theme-dark [style*="color:#7c3aed"],   /* violet-600 */
        html.theme-dark [style*="color: #6d28d9"],
        html.theme-dark [style*="color: #7c3aed"]   { color: #c4b5fd !important; }
        html.theme-dark [style*="color:#075985"],   /* sky-800 */
        html.theme-dark [style*="color:#0c4a6e"],   /* sky-900 */
        html.theme-dark [style*="color: #075985"],
        html.theme-dark [style*="color: #0c4a6e"]   { color: #7dd3fc !important; }

        /* 明るいボーダー指定 (#bfdbfe, #c7d2fe, #fde68a 等 — Tailwind の -200 系) も中和 */
        html.theme-dark [style*="border:1px solid #bfdbfe"],
        html.theme-dark [style*="border-color:#bfdbfe"],
        html.theme-dark [style*="border:1px solid #c7d2fe"],
        html.theme-dark [style*="border-color:#c7d2fe"],
        html.theme-dark [style*="border:1px solid #cbd5e1"],
        html.theme-dark [style*="border-color:#cbd5e1"],
        html.theme-dark [style*="border:1px solid #fde68a"],
        html.theme-dark [style*="border-color:#fde68a"],
        html.theme-dark [style*="border:1px solid #a7f3d0"],
        html.theme-dark [style*="border-color:#a7f3d0"],
        html.theme-dark [style*="border:1px solid #fecaca"],
        html.theme-dark [style*="border-color:#fecaca"],
        html.theme-dark [style*="border:1px solid #ddd6fe"],
        html.theme-dark [style*="border-color:#ddd6fe"],
        html.theme-dark [style*="border:1px solid #bae6fd"],
        html.theme-dark [style*="border-color:#bae6fd"],
        html.theme-dark [style*="border:1px solid #e5e7eb"],
        html.theme-dark [style*="border-color:#e5e7eb"] {
            border-color: var(--rd-border) !important;
        }

        /* 半透明白背景 (rgba(255,255,255,..)) — モーダルのオーバーレイ的に使われる場合がある.
           完全には消さず、ホバー風の薄い灰色に置換. */
        html.theme-dark [style*="background:rgba(255,255,255"],
        html.theme-dark [style*="background-color:rgba(255,255,255"] {
            background-color: var(--rd-bg-hover) !important;
        }

        /* ===== タブセグメント (承認待ち/送信済/却下済、受信/保留/完了 等) ダーク向け調整 ===== */
        /* Material Design / Android Studio 風: ボタン周りの色付きは廃止し、テキスト + アンダーラインで状態表現 */
        html.theme-dark .bg-gray-200\/50 {
            background-color: transparent !important;
            box-shadow: inset 0 -1px 0 var(--rd-border) !important;  /* 下端にだけ細い区切り線 */
            padding: 0 !important;
            border-radius: 0 !important;
        }
        /* 未選択タブ: 背景なし、ミュート文字 */
        html.theme-dark .bg-gray-200\/50 > button {
            background-color: transparent !important;
            color: var(--rd-text-mute) !important;
            border-radius: 0 !important;
            position: relative;
            padding-top: 8px !important;
            padding-bottom: 8px !important;
            box-shadow: none !important;
            transition: color 0.15s, background-color 0.15s;
        }
        /* ホバー: わずかな半透明背景 + 文字を白寄り (Material Design ripple 風) */
        html.theme-dark .bg-gray-200\/50 > button:hover {
            background-color: rgba(255,255,255,0.04) !important;
            color: var(--rd-text) !important;
        }
        /* 選択中タブ: ブランド色文字 + 下線インジケータ (Material タブ風) */
        html.theme-dark .bg-gray-200\/50 > button.shadow {
            background-color: transparent !important;
            color: var(--rd-accent) !important;
            box-shadow: inset 0 -2px 0 var(--rd-accent) !important;
        }
        html.theme-dark .bg-gray-200\/50 > button.shadow.text-blue-600  { color: #61a8ff !important; box-shadow: inset 0 -2px 0 #61a8ff !important; }
        html.theme-dark .bg-gray-200\/50 > button.shadow.text-green-600 { color: #6ee7a7 !important; box-shadow: inset 0 -2px 0 #6ee7a7 !important; }
        html.theme-dark .bg-gray-200\/50 > button.shadow.text-red-600   { color: #ff9a9c !important; box-shadow: inset 0 -2px 0 #ff9a9c !important; }

        /* 束ねたスレッドの帯 + チップを十分暗く (Discord 風 #36393F)
           x-show が display プロパティを書き換えるため [style*="display:flex"] では当たらない。
           .bundle-band クラスをマーカとして利用する。 */
        html.theme-dark .bundle-band {
            background-color: #36393F !important;
            border-bottom-color: var(--rd-border) !important;
        }

        /* ステータスタブの帯 (受信/保留/完了/対応不要/承認待ち) を最濃グレーへ
           汎用 .bg-gray-50 ルールよりあとに置いて優先させる */
        html.theme-dark .status-tab-band {
            background-color: #202225 !important;
            border-bottom-color: var(--rd-border) !important;
        }
        html.theme-dark .bundle-chip {
            background: var(--rd-bg-active) !important;
            color: var(--rd-text-mute) !important;
            border: 1px solid var(--rd-border) !important;
        }
        html.theme-dark .bundle-chip * { color: var(--rd-text-mute) !important; }
        html.theme-dark .bundle-chip i { color: var(--rd-text-dim) !important; }
        html.theme-dark .bundle-chip button { color: var(--rd-text-dim) !important; }

        /* ===== チャット領域: ボタン / 入力欄 / リアクション / ホバーアクション ===== */
        /* メッセージ ホバー時のアクションツールバー */
        html.theme-dark .msg-row .msg-actions {
            background: var(--rd-bg-3) !important;
            border-color: var(--rd-border) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }
        html.theme-dark .msg-row .msg-action-btn { color: var(--rd-text-mute) !important; }
        html.theme-dark .msg-row .msg-action-btn:hover { background: var(--rd-bg-hover) !important; color: #fff !important; }
        html.theme-dark .msg-row .msg-action-btn.msg-action-del:hover { background: rgba(237,66,69,0.25) !important; color: #ff9a9c !important; }

        /* 返信中バナー */
        html.theme-dark .reply-banner {
            background: var(--rd-bg-hover) !important;
            border-color: var(--rd-border) !important;
            color: var(--rd-text);
        }
        html.theme-dark .reply-banner * { color: inherit !important; }

        /* 絵文字ピッカー */
        html.theme-dark .emoji-pop {
            background: var(--rd-bg-2) !important;
            border-color: var(--rd-border) !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        html.theme-dark .emoji-pop-head { color: var(--rd-text-dim); }
        html.theme-dark .emoji-btn:hover { background: var(--rd-bg-hover); }

        /* リアクションピル */
        html.theme-dark .reaction-pill {
            background: var(--rd-bg-3) !important;
            border-color: var(--rd-border) !important;
            color: var(--rd-text);
        }
        html.theme-dark .reaction-pill:hover {
            background: var(--rd-bg-hover) !important;
            border-color: var(--rd-border) !important;
        }
        html.theme-dark .reaction-pill.reaction-mine {
            background: rgba(88,101,242,0.2) !important;
            border-color: rgba(88,101,242,0.5) !important;
            color: #c7d0ff !important;
        }

        /* @自分宛 / @メンション タグ (.msg-row 内のオレンジバッジを抑える) */
        html.theme-dark .msg-row .mention-self {
            background: rgba(250,166,26,0.18) !important;
            color: #ffd58a !important;
            border-color: rgba(250,166,26,0.35) !important;
        }
        html.theme-dark .msg-row .mention-tag {
            background: rgba(88,101,242,0.25) !important;
            color: #c7d0ff !important;
        }

        /* 添付ファイル (チャット内) */
        html.theme-dark .chat-att-image { border-color: var(--rd-border) !important; }
        html.theme-dark .chat-att-file {
            background: var(--rd-bg-3) !important;
            border-color: var(--rd-border) !important;
            color: var(--rd-text) !important;
        }
        html.theme-dark .chat-att-file:hover { background: var(--rd-bg-hover) !important; }
        html.theme-dark .chat-att-file .size { color: var(--rd-text-dim) !important; }

        /* 入力欄: 選択中ファイルプレビュー */
        html.theme-dark .chat-pending-file {
            background: var(--rd-bg-3) !important;
            border-color: var(--rd-border) !important;
            color: var(--rd-text) !important;
        }
        html.theme-dark .chat-pending-file button { color: var(--rd-text-mute) !important; }

        /* 入力欄のアイコンボタン (絵文字 / 添付 / 送信) */
        html.theme-dark .chat-input-box button {
            background: transparent !important;
            color: var(--rd-text-mute) !important;
        }
        html.theme-dark .chat-input-box button:hover {
            background: var(--rd-bg-hover) !important;
            color: #fff !important;
        }
        html.theme-dark .chat-input-box button[type="submit"],
        html.theme-dark .chat-input-box button.send {
            color: var(--rd-brand) !important;
        }

        /* 日付区切り */
        html.theme-dark .date-divider { color: var(--rd-text-dim) !important; }
        html.theme-dark .date-divider::before,
        html.theme-dark .date-divider::after { background: var(--rd-border) !important; }
        html.theme-dark .date-divider span { background: var(--rd-bg) !important; color: var(--rd-text-dim) !important; }

        /* 全体ビューのコンテキストバッジ */
        html.theme-dark .msg-context-badge { background: var(--rd-bg-3) !important; color: var(--rd-text-mute) !important; border-color: var(--rd-border) !important; }
        html.theme-dark .msg-context-badge.thread { background: rgba(168,85,247,0.18) !important; color: #d8b4fe !important; border-color: rgba(168,85,247,0.4) !important; }

        /* バンドルチップ (束ねたスレッド表示) */
        html.theme-dark .bundle-chip {
            background: var(--rd-bg-3) !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }
        html.theme-dark .bundle-chip button { color: var(--rd-text-mute) !important; }

        /* メンション候補ポップ */
        html.theme-dark .mention-pop {
            background: var(--rd-bg-2) !important;
            border-color: var(--rd-border) !important;
            color: var(--rd-text);
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
        }
        html.theme-dark .mention-pop .head { color: var(--rd-text-dim) !important; background: var(--rd-bg-3) !important; border-bottom-color: var(--rd-border) !important; }
        html.theme-dark .mention-pop .item { color: var(--rd-text); }
        html.theme-dark .mention-pop .item:hover,
        html.theme-dark .mention-pop .item.active { background: var(--rd-bg-hover) !important; color: #fff; }
        html.theme-dark .mention-pop .item .email { color: var(--rd-text-dim) !important; }

        /* ===== グローバル: button タグ全般のソフト化 ===== */
        /* インライン背景がない素のボタンはダークサーフェスに合わせる */
        html.theme-dark button:not([style*="background"]):not(.btn-primary):not(.btn-danger):not(.btn-success):not(.btn-warning):not(.btn-info):not([class*="bg-"]) {
            color: var(--rd-text);
        }

        /* 鮮やかな白背景ボタン (#ffffff inline) は dark surface に (3桁略記の #fff; / #fff  も含む) */
        html.theme-dark [style*="background:#ffffff"],
        html.theme-dark [style*="background-color:#ffffff"],
        html.theme-dark [style*="background:#fff;"],
        html.theme-dark [style*="background:#fff "],
        html.theme-dark [style*="background-color:#fff;"],
        html.theme-dark [style*="background-color:#fff "] {
            background-color: var(--rd-bg-2) !important;
            color: var(--rd-text) !important;
        }
        /* 同様に #f9fafb / #f3f4f6 / #f5f3ff 等の薄グレー/紫 */
        html.theme-dark [style*="background:#f9fafb"],
        html.theme-dark [style*="background-color:#f9fafb"],
        html.theme-dark [style*="background:#f3f4f6"],
        html.theme-dark [style*="background-color:#f3f4f6"],
        html.theme-dark [style*="background:#f5f3ff"],
        html.theme-dark [style*="background-color:#f5f3ff"],
        html.theme-dark [style*="background:#fafafa"],
        html.theme-dark [style*="background-color:#fafafa"] {
            background-color: var(--rd-bg-hover) !important;
            color: var(--rd-text) !important;
        }

        /* テキスト色のソフト化 (チャットの author 名等) */
        html.theme-dark [style*="color:#111827"],
        html.theme-dark [style*="color:#1f2937"],
        html.theme-dark [style*="color:#374151"],
        html.theme-dark [style*="color:#475569"] { color: var(--rd-text) !important; }
        html.theme-dark [style*="color:#6b7280"],
        html.theme-dark [style*="color:#64748b"] { color: var(--rd-text-mute) !important; }
        html.theme-dark [style*="color:#9ca3af"],
        html.theme-dark [style*="color:#94a3b8"] { color: var(--rd-text-dim) !important; }

        /* ===== rice-modal (統一モーダル) ダークモード ===== */
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

        /* ボーダー色: 明色の境界線も落ち着いた灰色に */
        html.theme-dark [style*="border:1px solid #e5e7eb"],
        html.theme-dark [style*="border:1px solid #f3f4f6"],
        html.theme-dark [style*="border-color:#e5e7eb"],
        html.theme-dark [style*="border-color:#f3f4f6"],
        html.theme-dark [style*="border-bottom:1px solid #e5e7eb"],
        html.theme-dark [style*="border-bottom:1px solid #f3f4f6"],
        html.theme-dark [style*="border-top:1px solid #f3f4f6"],
        html.theme-dark [style*="border-top:1px solid #e5e7eb"] { border-color: var(--rd-border) !important; }

        /* ===== 鮮やか系 Tailwind 色 (-400 〜 -700) は半透明化してダーク映えするように ===== */
        /* 青系 (バルクアクションバー bg-blue-600 / インラインボタンの背景) */
        html.theme-dark .bg-blue-500,
        html.theme-dark .bg-blue-600,
        html.theme-dark .bg-blue-700 { background-color: rgba(88,101,242,0.35) !important; color:#fff !important; }
        html.theme-dark .hover\:bg-blue-600:hover,
        html.theme-dark .hover\:bg-blue-700:hover { background-color: rgba(88,101,242,0.55) !important; }
        /* オレンジ/アンバー (マージ / ピン留め active 等) */
        html.theme-dark .bg-amber-400,
        html.theme-dark .bg-amber-500,
        html.theme-dark .bg-amber-600 { background-color: rgba(250,166,26,0.3) !important; color:#fff !important; }
        html.theme-dark .hover\:bg-amber-500:hover,
        html.theme-dark .hover\:bg-amber-600:hover { background-color: rgba(250,166,26,0.45) !important; }
        /* 緑 (完了系) */
        html.theme-dark .bg-green-500,
        html.theme-dark .bg-green-600,
        html.theme-dark .bg-emerald-500,
        html.theme-dark .bg-emerald-600 { background-color: rgba(87,242,135,0.25) !important; color:#fff !important; }
        html.theme-dark .hover\:bg-green-600:hover,
        html.theme-dark .hover\:bg-emerald-600:hover { background-color: rgba(87,242,135,0.4) !important; }
        /* 赤 (削除系) */
        html.theme-dark .bg-red-500,
        html.theme-dark .bg-red-600 { background-color: rgba(237,66,69,0.35) !important; color:#fff !important; }
        html.theme-dark .hover\:bg-red-600:hover { background-color: rgba(237,66,69,0.55) !important; }
        /* 紫 / 黄 / シアン / 灰 */
        html.theme-dark .bg-purple-500,
        html.theme-dark .bg-purple-600 { background-color: rgba(168,85,247,0.3) !important; color:#fff !important; }
        html.theme-dark .bg-yellow-400,
        html.theme-dark .bg-yellow-500 { background-color: rgba(250,204,21,0.25) !important; color:#fff !important; }
        html.theme-dark .bg-gray-700,
        html.theme-dark .bg-gray-800,
        html.theme-dark .bg-gray-900 { background-color: var(--rd-bg-3) !important; color: var(--rd-text) !important; }

        /* Tailwind /XX 透過 (bg-white/20 等) は親に依存するので親要素背景を下げる */
        html.theme-dark .bg-white\/10,
        html.theme-dark .bg-white\/20,
        html.theme-dark .bg-white\/30 { background-color: rgba(255,255,255,0.08) !important; }
        html.theme-dark .hover\:bg-white\/20:hover,
        html.theme-dark .hover\:bg-white\/30:hover { background-color: rgba(255,255,255,0.14) !important; }
        html.theme-dark .bg-amber-500\/80,
        html.theme-dark .bg-red-500\/80,
        html.theme-dark .bg-blue-500\/80 { background-color: rgba(88,101,242,0.3) !important; }

        /* テキスト色: 鮮やかすぎる -600〜-800 を抑える */
        html.theme-dark .text-blue-500,
        html.theme-dark .text-blue-600,
        html.theme-dark .text-blue-700,
        html.theme-dark .text-blue-800 { color: #97a8ff !important; }
        html.theme-dark .text-indigo-500,
        html.theme-dark .text-indigo-600,
        html.theme-dark .text-indigo-700,
        html.theme-dark .text-indigo-800 { color: #a3aaff !important; }
        html.theme-dark .text-amber-600,
        html.theme-dark .text-amber-700,
        html.theme-dark .text-amber-800 { color: #f0b34c !important; }
        html.theme-dark .text-green-600,
        html.theme-dark .text-green-700,
        html.theme-dark .text-green-800,
        html.theme-dark .text-emerald-600,
        html.theme-dark .text-emerald-700,
        html.theme-dark .text-emerald-800 { color: #8de0a4 !important; }
        html.theme-dark .text-red-500,
        html.theme-dark .text-red-600,
        html.theme-dark .text-red-700,
        html.theme-dark .text-red-800 { color: #ff9a9c !important; }
        html.theme-dark .text-purple-600,
        html.theme-dark .text-purple-700,
        html.theme-dark .text-purple-800 { color: #d8b4fe !important; }
        html.theme-dark .text-orange-600,
        html.theme-dark .text-orange-700 { color: #ffae72 !important; }

        /* ===== レポート (Workflow / dashboard) 専用 ===== */
        html.theme-dark .stat-card {
            background-color: var(--rd-bg-2) !important;
            border-color: var(--rd-border) !important;
            color: var(--rd-text);
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        html.theme-dark .stat-card .stat-value { color: var(--rd-text) !important; }
        html.theme-dark .stat-card .stat-label { color: var(--rd-text-dim) !important; }
        html.theme-dark .panel {
            background-color: var(--rd-bg-2) !important;
            border-color: var(--rd-border) !important;
            color: var(--rd-text);
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        html.theme-dark .panel-header {
            background: var(--rd-bg-3) !important;
            border-bottom-color: var(--rd-border) !important;
            color: var(--rd-text);
        }
        html.theme-dark .panel-title { color: var(--rd-text) !important; }
        html.theme-dark .panel-sub { color: var(--rd-text-dim) !important; }
        html.theme-dark .data-table th {
            background: var(--rd-bg-3) !important;
            color: var(--rd-text-dim) !important;
            border-bottom-color: var(--rd-border) !important;
        }
        html.theme-dark .data-table td { border-bottom-color: var(--rd-border) !important; color: var(--rd-text); }
        html.theme-dark .data-table tr:hover { background: var(--rd-bg-hover) !important; }
        html.theme-dark .data-table .num { color: var(--rd-text) !important; }
        html.theme-dark .seg-bar { background: var(--rd-bg-3) !important; }
        html.theme-dark .day-label { color: var(--rd-text-mute) !important; }
        html.theme-dark .day-bar-track { background: var(--rd-bg-3) !important; }
        html.theme-dark .day-num { color: var(--rd-text) !important; }
        html.theme-dark .empty { color: var(--rd-text-dim) !important; }

        /* レポートのステータスピル (.pill-* クラス) を低彩度に */
        html.theme-dark .pill-inbox {
            color: #c7d0ff !important;
            background: rgba(88,101,242,0.18) !important;
            border-color: rgba(88,101,242,0.4) !important;
        }
        html.theme-dark .pill-hold {
            color: #f0b34c !important;
            background: rgba(250,166,26,0.18) !important;
            border-color: rgba(250,166,26,0.4) !important;
        }
        html.theme-dark .pill-completed {
            color: #8de0a4 !important;
            background: rgba(87,242,135,0.15) !important;
            border-color: rgba(87,242,135,0.35) !important;
        }
        html.theme-dark .pill-pending {
            color: #ffae72 !important;
            background: rgba(249,115,22,0.18) !important;
            border-color: rgba(249,115,22,0.4) !important;
        }
        html.theme-dark .seg-inbox     { background: #5865f2 !important; }
        html.theme-dark .seg-hold      { background: #faa61a !important; }
        html.theme-dark .seg-completed { background: #57f287 !important; }
        html.theme-dark .seg-pending   { background: #f97316 !important; }

        /* レポート: 顧客フィルタ・期間フィルタの input/select */
        html.theme-dark input[type="date"] {
            background-color: var(--rd-bg-3) !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }

        /* ===== 束ねたスレッドチップ・ステータスバッジを低彩度に ===== */
        /* チャットの bundle-chip は既に dark 化済み。インラインで色付けされている版もカバー */
        html.theme-dark .bundle-chip * { color: inherit !important; }

        /* ステータス badge (例: bg-amber-100 text-amber-800 等) は親で色変換済みだが、
           text-amber-800 だと黒っぽくなるので read-able な落ち着いた色味に差し替え (上で済) */

        /* ============================================================
           ダーク補完: 白系の残り (Tailwind 不透明度バリアント / インライン style /
           <table> / <button>) を一括でグレーに寄せる
           ============================================================ */

        /* A. Tailwind の bg-white / bg-gray-*の不透明度バリアント (CSS は \/ で / をエスケープ) */
        html.theme-dark .bg-white\/95,
        html.theme-dark .bg-white\/90,
        html.theme-dark .bg-white\/80,
        html.theme-dark .bg-white\/70,
        html.theme-dark .bg-white\/60,
        html.theme-dark .bg-white\/50,
        html.theme-dark .bg-white\/40,
        html.theme-dark .bg-white\/30,
        html.theme-dark .bg-white\/20,
        html.theme-dark .bg-white\/10 {
            background-color: var(--rd-bg-2) !important;
            color: var(--rd-text);
        }
        html.theme-dark .bg-gray-50\/60,
        html.theme-dark .bg-gray-50\/50,
        html.theme-dark .bg-gray-50\/40,
        html.theme-dark .bg-gray-50\/30,
        html.theme-dark .bg-gray-50\/20 {
            background-color: var(--rd-bg) !important;
            color: var(--rd-text);
        }
        html.theme-dark .bg-gray-100\/60,
        html.theme-dark .bg-gray-100\/50 {
            background-color: var(--rd-bg-hover) !important;
            color: var(--rd-text);
        }
        /* ホバーの opacity 変種 */
        html.theme-dark *[class*="hover:bg-gray-50\/"]:hover,
        html.theme-dark *[class*="hover:bg-white\/"]:hover {
            background-color: var(--rd-bg-hover) !important;
        }

        /* B. インライン style="background:#f9fafb" / #f3f4f6 / #ffffff / #fff / white / #fafafa
           Blade で直書きされている個別要素を一括でグレーに揃える。
           .bundle-band / .status-tab-band は別ルールで先に色を決めるので順番で勝つ */
        html.theme-dark [style*="background:#ffffff"],
        html.theme-dark [style*="background: #ffffff"],
        html.theme-dark [style*="background:#fff;"],
        html.theme-dark [style*="background: #fff;"],
        html.theme-dark [style*="background:white"],
        html.theme-dark [style*="background: white"],
        html.theme-dark [style*="background-color:#ffffff"],
        html.theme-dark [style*="background-color: #ffffff"],
        html.theme-dark [style*="background-color:#fff;"],
        html.theme-dark [style*="background-color: #fff;"],
        html.theme-dark [style*="background-color:white"],
        html.theme-dark [style*="background-color: white"] {
            background-color: var(--rd-bg-2) !important;
            color: var(--rd-text) !important;
        }
        html.theme-dark [style*="background:#f9fafb"],
        html.theme-dark [style*="background: #f9fafb"],
        html.theme-dark [style*="background-color:#f9fafb"],
        html.theme-dark [style*="background-color: #f9fafb"] {
            background-color: var(--rd-bg) !important;
            color: var(--rd-text) !important;
        }
        html.theme-dark [style*="background:#f3f4f6"],
        html.theme-dark [style*="background: #f3f4f6"],
        html.theme-dark [style*="background-color:#f3f4f6"],
        html.theme-dark [style*="background-color: #f3f4f6"] {
            background-color: var(--rd-bg-hover) !important;
            color: var(--rd-text) !important;
        }
        html.theme-dark [style*="background:#fafafa"],
        html.theme-dark [style*="background: #fafafa"] {
            background-color: var(--rd-bg-2) !important;
            color: var(--rd-text) !important;
        }
        /* sky-50 #f0f9ff: ルーム Wiki サイドパネルなどで多用される薄い水色 */
        html.theme-dark [style*="background:#f0f9ff"],
        html.theme-dark [style*="background: #f0f9ff"],
        html.theme-dark [style*="background-color:#f0f9ff"],
        html.theme-dark [style*="background-color: #f0f9ff"] {
            background-color: var(--rd-bg) !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }

        /* インライン color: #xxx の dark-text を確実に上書き
           (textarea / pre / 内部 div で色指定されているケース) */
        html.theme-dark [style*="color:#111827"],
        html.theme-dark [style*="color: #111827"],
        html.theme-dark [style*="color:#1f2937"],
        html.theme-dark [style*="color: #1f2937"],
        html.theme-dark [style*="color:#0f172a"],
        html.theme-dark [style*="color: #0f172a"],
        html.theme-dark [style*="color:#1e293b"],
        html.theme-dark [style*="color: #1e293b"],
        html.theme-dark [style*="color: rgb(17, 24, 39)"],
        html.theme-dark [style*="color:rgb(17,24,39)"],
        html.theme-dark [style*="color: rgb(31, 41, 55)"],
        html.theme-dark [style*="color:rgb(31,41,55)"] {
            color: var(--rd-text) !important;
        }

        /* C. <table> 一般化 — テーブル全体を Discord グレーへ */
        html.theme-dark table {
            background-color: var(--rd-bg-2) !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }
        html.theme-dark thead,
        html.theme-dark thead tr,
        html.theme-dark thead th {
            background-color: var(--rd-bg-3) !important;
            color: var(--rd-text-mute) !important;
            border-color: var(--rd-border) !important;
        }
        html.theme-dark tbody,
        html.theme-dark tbody tr,
        html.theme-dark tbody td {
            background-color: transparent !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }
        html.theme-dark tbody tr:hover,
        html.theme-dark tbody tr:hover td {
            background-color: var(--rd-bg-hover) !important;
        }
        html.theme-dark table .text-gray-500,
        html.theme-dark table .text-gray-600,
        html.theme-dark table .text-gray-700,
        html.theme-dark table .text-gray-900 { color: var(--rd-text) !important; }

        /* D. <button> のうち白系を強制グレー化
           - Tailwind の .bg-white は既に上書きされているが、インライン style や
             AdminLTE / Bootstrap の白系ボタンを救う */
        html.theme-dark button[style*="background:#ffffff"],
        html.theme-dark button[style*="background:#fff;"],
        html.theme-dark button[style*="background:white"],
        html.theme-dark button[style*="background: #ffffff"],
        html.theme-dark button[style*="background: #fff;"],
        html.theme-dark button[style*="background: white"],
        html.theme-dark a[role="button"][style*="background:#fff"] {
            background-color: var(--rd-bg-hover) !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }
        html.theme-dark .navbar-white,
        html.theme-dark .navbar-light { background-color: var(--rd-bg-3) !important; }
        html.theme-dark .btn-white,
        html.theme-dark .btn-light {
            background-color: var(--rd-bg-hover) !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }
        html.theme-dark .btn-outline-secondary,
        html.theme-dark .btn-outline-light {
            background-color: transparent !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }
        html.theme-dark .btn-outline-secondary:hover,
        html.theme-dark .btn-outline-light:hover {
            background-color: var(--rd-bg-hover) !important;
        }

        /* ============================================================
           メールスレッド画面のアクションボタン (返信 / 全員 / ナレッジ / チャット)
           インライン onmouseover を CSS :hover に置き換える。
           ライト・ダーク両モードで色味を切り替える。
           ============================================================ */
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            transition: background-color .15s, color .15s, border-color .15s;
            flex-shrink: 0;
            cursor: pointer;
            border: 1px solid transparent;
            line-height: 1.2;
        }
        /* --- ライトモード --- */
        .btn-action-reply       { background-color:#eff6ff; color:#2563eb; }
        .btn-action-reply:hover { background-color:#2563eb; color:#ffffff; }

        .btn-action-replyall       { background-color:#ffffff; color:#2563eb; border-color:#dbeafe; }
        .btn-action-replyall:hover { background-color:#eff6ff; }

        .btn-action-knowledge       { background-color:#ffffff; color:#475569; border-color:#e2e8f0; }
        .btn-action-knowledge:hover { background-color:#f1f5f9; color:#0f172a; }

        .btn-action-chat       { background-color:#ffffff; color:#7c3aed; border-color:#ddd6fe; }
        .btn-action-chat:hover { background-color:#7c3aed; color:#ffffff; }

        /* スレッド内の個別メール削除ボタン. 破壊的操作なので赤系で警告色. */
        .btn-action-delete       { background-color:#ffffff; color:#dc2626; border-color:#fecaca; }
        .btn-action-delete:hover { background-color:#dc2626; color:#ffffff; border-color:#dc2626; }

        /* スレッドからメールを分離するボタン. 削除ではなく「移動」なので琥珀系で警告控えめ. */
        .btn-action-detach       { background-color:#ffffff; color:#b45309; border-color:#fde68a; }
        .btn-action-detach:hover { background-color:#b45309; color:#ffffff; border-color:#b45309; }

        /* 転送 (Fwd:) ボタン. ユーザ要望で「黒で囲う」配色.
           白背景 + 黒文字 + 黒枠 / hover で黒背景 + 白文字. 他のボタンとの差別化のため濃いめにする. */
        .btn-action-forward       { background-color:#ffffff; color:#111827; border-color:#111827; }
        .btn-action-forward:hover { background-color:#111827; color:#ffffff; border-color:#111827; }

        /* --- ダークモード: ダーク基調の整合した配色 --- */
        html.theme-dark .btn-action-reply {
            background-color: rgba(88,101,242,0.18) !important;
            color: #c7d0ff !important;
            border-color: rgba(88,101,242,0.35) !important;
        }
        html.theme-dark .btn-action-reply:hover {
            background-color: #5865f2 !important;
            color: #ffffff !important;
            border-color: #5865f2 !important;
        }

        html.theme-dark .btn-action-replyall {
            background-color: transparent !important;
            color: #c7d0ff !important;
            border-color: rgba(88,101,242,0.35) !important;
        }
        html.theme-dark .btn-action-replyall:hover {
            background-color: rgba(88,101,242,0.18) !important;
            color: #ffffff !important;
        }

        html.theme-dark .btn-action-knowledge {
            background-color: transparent !important;
            color: var(--rd-text-mute) !important;
            border-color: var(--rd-border) !important;
        }
        html.theme-dark .btn-action-knowledge:hover {
            background-color: var(--rd-bg-hover) !important;
            color: #ffffff !important;
            border-color: var(--rd-border-2) !important;
        }

        html.theme-dark .btn-action-chat {
            background-color: transparent !important;
            color: #d8b4fe !important;
            border-color: rgba(168,85,247,0.35) !important;
        }
        html.theme-dark .btn-action-chat:hover {
            background-color: rgba(168,85,247,0.25) !important;
            color: #ffffff !important;
            border-color: rgba(168,85,247,0.5) !important;
        }

        html.theme-dark .btn-action-delete {
            background-color: transparent !important;
            color: #fca5a5 !important;
            border-color: rgba(239,68,68,0.35) !important;
        }
        html.theme-dark .btn-action-delete:hover {
            background-color: #dc2626 !important;
            color: #ffffff !important;
            border-color: #dc2626 !important;
        }

        html.theme-dark .btn-action-detach {
            background-color: transparent !important;
            color: #fcd34d !important;
            border-color: rgba(180,83,9,0.45) !important;
        }
        html.theme-dark .btn-action-detach:hover {
            background-color: #b45309 !important;
            color: #ffffff !important;
            border-color: #b45309 !important;
        }

        /* 転送 (Fwd:) ボタン. ダーク基調でも「黒で囲う」見た目を維持するため、
           枠は明るめ (= 暗背景上での "黒い枠" = コントラスト確保) で表現. hover で反転. */
        html.theme-dark .btn-action-forward {
            background-color: transparent !important;
            color: #f3f4f6 !important;
            border-color: rgba(255,255,255,0.45) !important;
        }
        html.theme-dark .btn-action-forward:hover {
            background-color: #f3f4f6 !important;
            color: #111827 !important;
            border-color: #f3f4f6 !important;
        }

        /* ============================================================
           スレッドチャットパネル (.thread-chat-*) を Discord 系ダークへ
           emails/index.blade.php のスコープ css がライト固定なので、
           dark テーマでは ! important で全部上書き
           ============================================================ */
        html.theme-dark .thread-chat-panel {
            background-color: var(--rd-bg) !important;
            color: var(--rd-text) !important;
            border-left-color: var(--rd-border) !important;
        }
        html.theme-dark .thread-chat-resize:hover,
        html.theme-dark .thread-chat-resize:active {
            background-color: var(--rd-brand) !important;
        }
        html.theme-dark .thread-chat-header {
            background-color: var(--rd-bg-2) !important;
            border-bottom-color: var(--rd-border) !important;
            color: var(--rd-text) !important;
        }
        html.theme-dark .thread-chat-header h3,
        html.theme-dark .thread-chat-header [style*="color:#111827"],
        html.theme-dark .thread-chat-header [style*="color: #111827"] {
            color: var(--rd-text) !important;
        }
        html.theme-dark .thread-chat-header p,
        html.theme-dark .thread-chat-header [style*="color:#6b7280"],
        html.theme-dark .thread-chat-header [style*="color: #6b7280"] {
            color: var(--rd-text-mute) !important;
        }
        html.theme-dark .thread-chat-hash {
            color: var(--rd-text-dim) !important;
        }
        html.theme-dark .thread-chat-close {
            color: var(--rd-text-mute) !important;
        }
        html.theme-dark .thread-chat-close:hover {
            background-color: var(--rd-bg-hover) !important;
            color: #ffffff !important;
        }
        html.theme-dark .thread-chat-messages {
            background-color: var(--rd-bg) !important;
        }
        html.theme-dark .thread-chat-messages .msg-row {
            color: var(--rd-text) !important;
            border-bottom-color: var(--rd-border) !important;
        }
        html.theme-dark .thread-chat-messages .msg-row:hover {
            background-color: var(--rd-bg-hover) !important;
        }
        html.theme-dark .thread-chat-messages .msg-row .author {
            color: var(--rd-text) !important;
        }
        html.theme-dark .thread-chat-messages .msg-row .ts {
            color: var(--rd-text-dim) !important;
        }
        html.theme-dark .thread-chat-messages .msg-row .body {
            color: var(--rd-text) !important;
        }
        html.theme-dark .thread-chat-messages .msg-row .msg-actions {
            background-color: var(--rd-bg-2) !important;
            border-color: var(--rd-border) !important;
            color: var(--rd-text-mute) !important;
        }
        html.theme-dark .thread-chat-messages .msg-row .msg-actions:hover {
            background-color: rgba(237,66,69,0.18) !important;
            border-color: rgba(237,66,69,0.35) !important;
            color: #ff9a9c !important;
        }
        html.theme-dark .thread-chat-input-wrap {
            background-color: var(--rd-bg-2) !important;
            border-top-color: var(--rd-border) !important;
        }
        html.theme-dark .thread-chat-input-box {
            background-color: var(--rd-bg-3) !important;
            border-color: var(--rd-border) !important;
            box-shadow: none !important;
        }
        html.theme-dark .thread-chat-input-box:focus-within {
            border-color: var(--rd-brand) !important;
            box-shadow: 0 0 0 3px rgba(88,101,242,0.25) !important;
        }
        html.theme-dark .thread-chat-input-box textarea {
            background: transparent !important;
            color: var(--rd-text) !important;
        }
        html.theme-dark .thread-chat-input-box textarea::placeholder {
            color: var(--rd-text-dim) !important;
        }
        html.theme-dark .thread-chat-send {
            color: var(--rd-text-mute) !important;
        }
        html.theme-dark .thread-chat-send:not(:disabled):hover {
            color: #ffffff !important;
        }
        html.theme-dark .thread-chat-kbd {
            background-color: var(--rd-bg-3) !important;
            border-color: var(--rd-border) !important;
            color: var(--rd-text-mute) !important;
        }

        /* AI要約ボタン (件名横の indigo CTA) — class 化して inline JS を排除。
           ライト: indigo-600 → indigo-700。ダーク: muted indigo → blurple */
        .btn-ai-summary {
            background-color: #4f46e5;
            color: #ffffff;
            box-shadow: 0 1px 3px rgba(79,70,229,0.25);
        }
        .btn-ai-summary:hover:not(:disabled) {
            background-color: #4338ca;
        }
        html.theme-dark .btn-ai-summary {
            background-color: rgba(88,101,242,0.30) !important;
            color: #ffffff !important;
            box-shadow: none !important;
        }
        html.theme-dark .btn-ai-summary:hover:not(:disabled) {
            background-color: #5865f2 !important;
        }

        /* ============================================================
           汎用救済: onmouseover で this.style.backgroundColor を書き換える
           ボタン/リンク/div が 91 箇所ある。class 化していない箇所の
           初期状態 (background:#xxx / background-color:#xxx) と
           ホバー後の色を一律でダーク基調のグレーへ寄せる。
           ============================================================ */
        /* Tailwind の bg-*-50/100 相当の薄いパステル + ライト系 hex を
           一括上書き。 background, background-color, 空白あり/なし、
           複数の hex を網羅。 */
        html.theme-dark [style*="background:#eff6ff"],
        html.theme-dark [style*="background: #eff6ff"],
        html.theme-dark [style*="background-color:#eff6ff"],
        html.theme-dark [style*="background-color: #eff6ff"],
        html.theme-dark [style*="background:#dbeafe"],
        html.theme-dark [style*="background: #dbeafe"],
        html.theme-dark [style*="background-color:#dbeafe"],
        html.theme-dark [style*="background-color: #dbeafe"],
        html.theme-dark [style*="background:#ede9fe"],
        html.theme-dark [style*="background: #ede9fe"],
        html.theme-dark [style*="background-color:#ede9fe"],
        html.theme-dark [style*="background-color: #ede9fe"],
        html.theme-dark [style*="background:#e0f2fe"],
        html.theme-dark [style*="background: #e0f2fe"],
        html.theme-dark [style*="background-color:#e0f2fe"],
        html.theme-dark [style*="background-color: #e0f2fe"],
        html.theme-dark [style*="background:#fef3c7"],
        html.theme-dark [style*="background: #fef3c7"],
        html.theme-dark [style*="background-color:#fef3c7"],
        html.theme-dark [style*="background-color: #fef3c7"],
        html.theme-dark [style*="background:#fffbeb"],
        html.theme-dark [style*="background: #fffbeb"],
        html.theme-dark [style*="background-color:#fffbeb"],
        html.theme-dark [style*="background-color: #fffbeb"],
        html.theme-dark [style*="background:#fff7ed"],
        html.theme-dark [style*="background: #fff7ed"],
        html.theme-dark [style*="background-color:#fff7ed"],
        html.theme-dark [style*="background-color: #fff7ed"],
        html.theme-dark [style*="background:#fef9c3"],
        html.theme-dark [style*="background: #fef9c3"],
        html.theme-dark [style*="background:#dcfce7"],
        html.theme-dark [style*="background: #dcfce7"],
        html.theme-dark [style*="background-color:#dcfce7"],
        html.theme-dark [style*="background-color: #dcfce7"],
        html.theme-dark [style*="background:#fee2e2"],
        html.theme-dark [style*="background: #fee2e2"],
        html.theme-dark [style*="background-color:#fee2e2"],
        html.theme-dark [style*="background-color: #fee2e2"],
        html.theme-dark [style*="background:#fef2f2"],
        html.theme-dark [style*="background: #fef2f2"],
        html.theme-dark [style*="background-color:#fef2f2"],
        html.theme-dark [style*="background-color: #fef2f2"],
        html.theme-dark [style*="background:#f1f5f9"],
        html.theme-dark [style*="background: #f1f5f9"],
        html.theme-dark [style*="background-color:#f1f5f9"],
        html.theme-dark [style*="background-color: #f1f5f9"] {
            background-color: var(--rd-bg-2) !important;
            color: var(--rd-text) !important;
        }
        /* ホバー後にビビッドな単色 (#2563eb / #5b21b6 / #7c3aed / #075985 等)
           になる onmouseover も、ダークでは控えめなアクセントに置き換える */
        html.theme-dark [style*="background-color:#2563eb"],
        html.theme-dark [style*="background-color: #2563eb"],
        html.theme-dark [style*="background-color:#1d4ed8"],
        html.theme-dark [style*="background-color: #1d4ed8"],
        html.theme-dark [style*="background-color:#3b82f6"],
        html.theme-dark [style*="background-color: #3b82f6"] {
            background-color: #5865f2 !important;   /* Discord blurple */
            color: #ffffff !important;
        }
        html.theme-dark [style*="background-color:#7c3aed"],
        html.theme-dark [style*="background-color: #7c3aed"],
        html.theme-dark [style*="background-color:#5b21b6"],
        html.theme-dark [style*="background-color: #5b21b6"] {
            background-color: rgba(168,85,247,0.4) !important;
            color: #ffffff !important;
        }
        html.theme-dark [style*="background-color:#075985"],
        html.theme-dark [style*="background-color: #075985"] {
            background-color: rgba(56,189,248,0.35) !important;
            color: #ffffff !important;
        }
        html.theme-dark [style*="background-color:#dc2626"],
        html.theme-dark [style*="background-color: #dc2626"] {
            background-color: rgba(237,66,69,0.4) !important;
            color: #ffffff !important;
        }
        /* indigo-600 #4f46e5 (vivid 各種 CTA) → blurple */
        html.theme-dark [style*="background:#4f46e5"],
        html.theme-dark [style*="background: #4f46e5"],
        html.theme-dark [style*="background-color:#4f46e5"],
        html.theme-dark [style*="background-color: #4f46e5"] {
            background-color: #5865f2 !important;
            color: #ffffff !important;
        }
        /* indigo-100 #e0e7ff (薄パステル indigo) → 控えめ blurple チント */
        html.theme-dark [style*="background:#e0e7ff"],
        html.theme-dark [style*="background: #e0e7ff"],
        html.theme-dark [style*="background-color:#e0e7ff"],
        html.theme-dark [style*="background-color: #e0e7ff"] {
            background-color: rgba(88,101,242,0.18) !important;
            color: #c7d0ff !important;
        }
        /* violet-50 #f5f3ff (薄パステル violet) → 控えめ紫チント */
        html.theme-dark [style*="background:#f5f3ff"],
        html.theme-dark [style*="background: #f5f3ff"],
        html.theme-dark [style*="background-color:#f5f3ff"],
        html.theme-dark [style*="background-color: #f5f3ff"] {
            background-color: rgba(168,85,247,0.15) !important;
            color: #d8b4fe !important;
        }
        /* Discord red #f23f42 (削除 / 危険系 ホバー) → 控えめ赤 */
        html.theme-dark [style*="background:#f23f42"],
        html.theme-dark [style*="background: #f23f42"],
        html.theme-dark [style*="background-color:#f23f42"],
        html.theme-dark [style*="background-color: #f23f42"] {
            background-color: rgba(237,66,69,0.4) !important;
            color: #ffffff !important;
        }
        /* 既に暗めの hex (gray-900 #111827 / gray-700 #374151 / 中間グレー / 暗赤) を
           背景に使うパターンも、Discord ダーク基調に揃える */
        html.theme-dark [style*="background:#111827"],
        html.theme-dark [style*="background: #111827"],
        html.theme-dark [style*="background-color:#111827"],
        html.theme-dark [style*="background-color: #111827"] {
            background-color: var(--rd-bg-3) !important;
            color: var(--rd-text) !important;
        }
        html.theme-dark [style*="background:#374151"],
        html.theme-dark [style*="background: #374151"],
        html.theme-dark [style*="background-color:#374151"],
        html.theme-dark [style*="background-color: #374151"] {
            background-color: var(--rd-bg-active) !important;
            color: var(--rd-text) !important;
        }
        html.theme-dark [style*="background:#3f1e22"],
        html.theme-dark [style*="background: #3f1e22"],
        html.theme-dark [style*="background-color:#3f1e22"],
        html.theme-dark [style*="background-color: #3f1e22"] {
            background-color: rgba(237,66,69,0.18) !important;
            color: #ffb3b3 !important;
        }
        /* rgb 形 (browser serialization) も対応 */
        html.theme-dark [style*="background-color: rgb(79, 70, 229)"],
        html.theme-dark [style*="background-color:rgb(79,70,229)"] {
            background-color: #5865f2 !important;
            color: #ffffff !important;
        }
        html.theme-dark [style*="background-color: rgb(224, 231, 255)"],
        html.theme-dark [style*="background-color:rgb(224,231,255)"] {
            background-color: rgba(88,101,242,0.18) !important;
            color: #c7d0ff !important;
        }
        html.theme-dark [style*="background-color: rgb(245, 243, 255)"],
        html.theme-dark [style*="background-color:rgb(245,243,255)"] {
            background-color: rgba(168,85,247,0.15) !important;
            color: #d8b4fe !important;
        }
        html.theme-dark [style*="background-color: rgb(242, 63, 66)"],
        html.theme-dark [style*="background-color:rgb(242,63,66)"] {
            background-color: rgba(237,66,69,0.4) !important;
            color: #ffffff !important;
        }
        html.theme-dark [style*="background-color: rgb(55, 65, 81)"],
        html.theme-dark [style*="background-color:rgb(55,65,81)"] {
            background-color: var(--rd-bg-active) !important;
            color: var(--rd-text) !important;
        }
        html.theme-dark [style*="background-color: rgb(63, 30, 34)"],
        html.theme-dark [style*="background-color:rgb(63,30,34)"] {
            background-color: rgba(237,66,69,0.18) !important;
            color: #ffb3b3 !important;
        }

        /* ============================================================
           rgb(...) シリアライズ形式の catch-all
           ブラウザは this.style.backgroundColor='#xxx' を rgb() に正規化する。
           [style*="#hex"] では取りこぼすので、rgb() 形式も網羅する。
           注意: "rgb(255,255,255)" は color にも background にも現れるので、
           "background:" / "background-color:" の前置詞を必ず含めて誤爆を防ぐ。
           ============================================================ */

        /* --- 白 / 極薄グレー / gray-50 / gray-100 を背景に持つ要素 → サーフェス系 --- */
        html.theme-dark [style*="background-color: rgb(255, 255, 255)"],
        html.theme-dark [style*="background-color:rgb(255,255,255)"],
        html.theme-dark [style*="background: rgb(255, 255, 255)"],
        html.theme-dark [style*="background:rgb(255,255,255)"],
        html.theme-dark [style*="background-color: rgb(250, 250, 250)"],
        html.theme-dark [style*="background-color:rgb(250,250,250)"],
        html.theme-dark [style*="background: rgb(250, 250, 250)"],
        html.theme-dark [style*="background:rgb(250,250,250)"] {
            background-color: var(--rd-bg-2) !important;
            color: var(--rd-text) !important;
        }
        html.theme-dark [style*="background-color: rgb(249, 250, 251)"],
        html.theme-dark [style*="background-color:rgb(249,250,251)"],
        html.theme-dark [style*="background: rgb(249, 250, 251)"],
        html.theme-dark [style*="background:rgb(249,250,251)"] {
            background-color: var(--rd-bg) !important;
            color: var(--rd-text) !important;
        }
        html.theme-dark [style*="background-color: rgb(243, 244, 246)"],
        html.theme-dark [style*="background-color:rgb(243,244,246)"],
        html.theme-dark [style*="background: rgb(243, 244, 246)"],
        html.theme-dark [style*="background:rgb(243,244,246)"],
        html.theme-dark [style*="background-color: rgb(241, 245, 249)"],
        html.theme-dark [style*="background-color:rgb(241,245,249)"],
        html.theme-dark [style*="background: rgb(241, 245, 249)"],
        html.theme-dark [style*="background:rgb(241,245,249)"] {
            background-color: var(--rd-bg-hover) !important;
            color: var(--rd-text) !important;
        }
        /* --- gray-200 #e5e7eb (border 色や薄背景に多用) → ホバーサーフェスへ --- */
        html.theme-dark [style*="background-color: rgb(229, 231, 235)"],
        html.theme-dark [style*="background-color:rgb(229,231,235)"],
        html.theme-dark [style*="background: rgb(229, 231, 235)"],
        html.theme-dark [style*="background:rgb(229,231,235)"] {
            background-color: var(--rd-bg-active) !important;
            color: var(--rd-text) !important;
        }

        /* --- ライト系パステル (blue-50 / blue-100 / indigo-50) → 青チント --- */
        html.theme-dark [style*="background-color: rgb(239, 246, 255)"],
        html.theme-dark [style*="background-color:rgb(239,246,255)"],
        html.theme-dark [style*="background: rgb(239, 246, 255)"],
        html.theme-dark [style*="background:rgb(239,246,255)"],
        html.theme-dark [style*="background-color: rgb(219, 234, 254)"],
        html.theme-dark [style*="background-color:rgb(219,234,254)"],
        html.theme-dark [style*="background: rgb(219, 234, 254)"],
        html.theme-dark [style*="background:rgb(219,234,254)"],
        html.theme-dark [style*="background-color: rgb(238, 242, 255)"],
        html.theme-dark [style*="background-color:rgb(238,242,255)"] {
            background-color: rgba(88,101,242,0.15) !important;
            color: #c7d0ff !important;
        }
        /* violet-100 #ede9fe */
        html.theme-dark [style*="background-color: rgb(237, 233, 254)"],
        html.theme-dark [style*="background-color:rgb(237,233,254)"],
        html.theme-dark [style*="background: rgb(237, 233, 254)"],
        html.theme-dark [style*="background:rgb(237,233,254)"] {
            background-color: rgba(168,85,247,0.18) !important;
            color: #d8b4fe !important;
        }
        /* sky-100 #e0f2fe */
        html.theme-dark [style*="background-color: rgb(224, 242, 254)"],
        html.theme-dark [style*="background-color:rgb(224,242,254)"],
        html.theme-dark [style*="background: rgb(224, 242, 254)"],
        html.theme-dark [style*="background:rgb(224,242,254)"] {
            background-color: rgba(56,189,248,0.18) !important;
            color: #7dd3fc !important;
        }
        /* sky-50 #f0f9ff (rgb 形) */
        html.theme-dark [style*="background-color: rgb(240, 249, 255)"],
        html.theme-dark [style*="background-color:rgb(240,249,255)"],
        html.theme-dark [style*="background: rgb(240, 249, 255)"],
        html.theme-dark [style*="background:rgb(240,249,255)"] {
            background-color: var(--rd-bg) !important;
            color: var(--rd-text) !important;
        }
        /* sky-500 #0ea5e9 (Wiki "保存" ボタンなど) */
        html.theme-dark [style*="background:#0ea5e9"],
        html.theme-dark [style*="background: #0ea5e9"],
        html.theme-dark [style*="background-color:#0ea5e9"],
        html.theme-dark [style*="background-color: #0ea5e9"],
        html.theme-dark [style*="background-color: rgb(14, 165, 233)"],
        html.theme-dark [style*="background-color:rgb(14,165,233)"],
        html.theme-dark [style*="background: rgb(14, 165, 233)"],
        html.theme-dark [style*="background:rgb(14,165,233)"] {
            background-color: rgba(56,189,248,0.55) !important;
            color: #ffffff !important;
        }
        /* amber 系 (amber-100 #fef3c7 / amber-50 #fffbeb / orange-50 #fff7ed) */
        html.theme-dark [style*="background-color: rgb(254, 243, 199)"],
        html.theme-dark [style*="background-color:rgb(254,243,199)"],
        html.theme-dark [style*="background: rgb(254, 243, 199)"],
        html.theme-dark [style*="background:rgb(254,243,199)"],
        html.theme-dark [style*="background-color: rgb(255, 251, 235)"],
        html.theme-dark [style*="background-color:rgb(255,251,235)"],
        html.theme-dark [style*="background: rgb(255, 251, 235)"],
        html.theme-dark [style*="background:rgb(255,251,235)"],
        html.theme-dark [style*="background-color: rgb(255, 247, 237)"],
        html.theme-dark [style*="background-color:rgb(255,247,237)"] {
            background-color: rgba(250,166,26,0.15) !important;
            color: #ffd58a !important;
        }
        /* green-100 #dcfce7 */
        html.theme-dark [style*="background-color: rgb(220, 252, 231)"],
        html.theme-dark [style*="background-color:rgb(220,252,231)"],
        html.theme-dark [style*="background: rgb(220, 252, 231)"],
        html.theme-dark [style*="background:rgb(220,252,231)"] {
            background-color: rgba(87,242,135,0.15) !important;
            color: #86efac !important;
        }
        /* red-100 #fee2e2 / red-50 #fef2f2 */
        html.theme-dark [style*="background-color: rgb(254, 226, 226)"],
        html.theme-dark [style*="background-color:rgb(254,226,226)"],
        html.theme-dark [style*="background: rgb(254, 226, 226)"],
        html.theme-dark [style*="background:rgb(254,226,226)"],
        html.theme-dark [style*="background-color: rgb(254, 242, 242)"],
        html.theme-dark [style*="background-color:rgb(254,242,242)"],
        html.theme-dark [style*="background: rgb(254, 242, 242)"],
        html.theme-dark [style*="background:rgb(254,242,242)"] {
            background-color: rgba(237,66,69,0.18) !important;
            color: #ffb3b3 !important;
        }

        /* --- ビビッドな単色 (Tailwind 600/700番台) → ダークアクセント --- */
        /* blue-600 / blue-700 / blue-500 / indigo-700 → blurple */
        html.theme-dark [style*="background-color: rgb(37, 99, 235)"],
        html.theme-dark [style*="background-color:rgb(37,99,235)"],
        html.theme-dark [style*="background: rgb(37, 99, 235)"],
        html.theme-dark [style*="background:rgb(37,99,235)"],
        html.theme-dark [style*="background-color: rgb(29, 78, 216)"],
        html.theme-dark [style*="background-color:rgb(29,78,216)"],
        html.theme-dark [style*="background: rgb(29, 78, 216)"],
        html.theme-dark [style*="background:rgb(29,78,216)"],
        html.theme-dark [style*="background-color: rgb(59, 130, 246)"],
        html.theme-dark [style*="background-color:rgb(59,130,246)"],
        html.theme-dark [style*="background-color: rgb(67, 56, 202)"],
        html.theme-dark [style*="background-color:rgb(67,56,202)"] {
            background-color: #5865f2 !important;
            color: #ffffff !important;
        }
        /* violet-600 #7c3aed / violet-700 #5b21b6 */
        html.theme-dark [style*="background-color: rgb(124, 58, 237)"],
        html.theme-dark [style*="background-color:rgb(124,58,237)"],
        html.theme-dark [style*="background: rgb(124, 58, 237)"],
        html.theme-dark [style*="background:rgb(124,58,237)"],
        html.theme-dark [style*="background-color: rgb(91, 33, 182)"],
        html.theme-dark [style*="background-color:rgb(91,33,182)"] {
            background-color: rgba(168,85,247,0.45) !important;
            color: #ffffff !important;
        }
        /* sky-800 #075985 */
        html.theme-dark [style*="background-color: rgb(7, 89, 133)"],
        html.theme-dark [style*="background-color:rgb(7,89,133)"] {
            background-color: rgba(56,189,248,0.4) !important;
            color: #ffffff !important;
        }
        /* red-600 #dc2626 / red-700 #b91c1c */
        html.theme-dark [style*="background-color: rgb(220, 38, 38)"],
        html.theme-dark [style*="background-color:rgb(220,38,38)"],
        html.theme-dark [style*="background-color: rgb(185, 28, 28)"],
        html.theme-dark [style*="background-color:rgb(185,28,28)"] {
            background-color: rgba(237,66,69,0.45) !important;
            color: #ffffff !important;
        }

        /* --- 黒系 (rgb(0,0,0) / gray-900 #111827) を BG に持つ要素 → サーフェス --- */
        html.theme-dark [style*="background-color: rgb(0, 0, 0)"],
        html.theme-dark [style*="background-color:rgb(0,0,0)"],
        html.theme-dark [style*="background: rgb(0, 0, 0)"],
        html.theme-dark [style*="background:rgb(0,0,0)"],
        html.theme-dark [style*="background-color: rgb(17, 24, 39)"],
        html.theme-dark [style*="background-color:rgb(17,24,39)"] {
            background-color: var(--rd-bg-3) !important;
            color: var(--rd-text) !important;
        }

        /* hover:bg-* (Tailwind) の opacity バリアントや動的 :class の救済 */
        html.theme-dark .hover\:bg-white:hover,
        html.theme-dark *[class*="hover:bg-white"]:hover {
            background-color: var(--rd-bg-hover) !important;
            color: var(--rd-text) !important;
        }

        /* Tailwind の bg-gray-200/50 / bg-gray-200/70 などピル背景 */
        html.theme-dark .bg-gray-200\/50,
        html.theme-dark .bg-gray-200\/70 {
            background-color: var(--rd-bg-3) !important;
        }

        /* AdminLTE/Bootstrap の card / card-body / list-group も白なのでサーフェスへ */
        html.theme-dark .card,
        html.theme-dark .card-body,
        html.theme-dark .list-group-item,
        html.theme-dark .info-box,
        html.theme-dark .info-box-content {
            background-color: var(--rd-bg-2) !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }
        html.theme-dark .card-header,
        html.theme-dark .card-footer {
            background-color: var(--rd-bg-3) !important;
            color: var(--rd-text-mute) !important;
            border-color: var(--rd-border) !important;
        }

        /* Bootstrap 4 の custom-file (ファイル選択 input) — 通常 / Browse ボタン両方 */
        html.theme-dark .custom-file-label {
            background-color: var(--rd-bg-2) !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }
        html.theme-dark .custom-file-label::after {
            background-color: var(--rd-bg-3) !important;
            color: var(--rd-text-mute) !important;
            border-left-color: var(--rd-border) !important;
        }
        html.theme-dark .custom-file-input:focus ~ .custom-file-label {
            border-color: var(--rd-brand) !important;
            box-shadow: 0 0 0 0.2rem rgba(88,101,242,0.25) !important;
        }
        /* 同系の Bootstrap form-control / input-group 周辺も白いので合わせる */
        html.theme-dark .input-group-text,
        html.theme-dark .input-group-prepend .input-group-text,
        html.theme-dark .input-group-append .input-group-text {
            background-color: var(--rd-bg-3) !important;
            color: var(--rd-text-mute) !important;
            border-color: var(--rd-border) !important;
        }
        html.theme-dark .form-control-sm,
        html.theme-dark .form-control-lg {
            background-color: var(--rd-bg-3) !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }
        html.theme-dark .form-control:focus,
        html.theme-dark .form-control-sm:focus,
        html.theme-dark .form-control-lg:focus {
            background-color: var(--rd-bg-3) !important;
            border-color: var(--rd-brand) !important;
            box-shadow: 0 0 0 0.2rem rgba(88,101,242,0.25) !important;
            color: var(--rd-text) !important;
        }

        /* ============================================================
           最終一掃: コードベースに残るすべての明るい hex 背景をダーク化。
           既存の `[style*="background:#xxxxxx"]` 上書きで漏れた色を網羅する。
           「background:」 / 「background-color:」 / 空白あり/なし のいずれも対象。
           表記は :is() で短縮することはせず、style-attribute 文字列マッチ
           の都合上 1 色につき 4 セレクタを並べる方針を踏襲。
           ============================================================ */

        /* === [A] Wiki スティッキー / Notice / Warning 系の薄い暖色 === */
        /* fefce8 (yellow-50 ふせん紙), fde68a (yellow-300 枠), fef9c3 (yellow-100),
           fef3c7 (amber-100), fffbeb (amber-50), fff7ed (orange-50) */
        html.theme-dark [style*="background:#fefce8"],
        html.theme-dark [style*="background: #fefce8"],
        html.theme-dark [style*="background-color:#fefce8"],
        html.theme-dark [style*="background-color: #fefce8"],
        html.theme-dark [style*="background:#fde68a"],
        html.theme-dark [style*="background: #fde68a"],
        html.theme-dark [style*="background-color:#fde68a"],
        html.theme-dark [style*="background-color: #fde68a"],
        html.theme-dark [style*="background:#fef9c3"],
        html.theme-dark [style*="background: #fef9c3"],
        html.theme-dark [style*="background-color:#fef9c3"],
        html.theme-dark [style*="background-color: #fef9c3"] {
            background-color: rgba(250,204,21,0.10) !important;
            color: var(--rd-text) !important;
            border-color: rgba(250,204,21,0.30) !important;
        }

        /* === [B] 残るパステル赤/ピンク/オレンジ系 ===
           fef2f2 (red-50), fee2e2 (red-100), fecaca (red-200), fda4af (rose-300),
           ffe4e6 (rose-100), fce7f3 (pink-100), fdf2f8 (pink-50) */
        html.theme-dark [style*="background:#fecaca"],
        html.theme-dark [style*="background: #fecaca"],
        html.theme-dark [style*="background-color:#fecaca"],
        html.theme-dark [style*="background-color: #fecaca"],
        html.theme-dark [style*="background:#ffe4e6"],
        html.theme-dark [style*="background: #ffe4e6"],
        html.theme-dark [style*="background-color:#ffe4e6"],
        html.theme-dark [style*="background-color: #ffe4e6"],
        html.theme-dark [style*="background:#fce7f3"],
        html.theme-dark [style*="background: #fce7f3"],
        html.theme-dark [style*="background-color:#fce7f3"],
        html.theme-dark [style*="background-color: #fce7f3"],
        html.theme-dark [style*="background:#fdf2f8"],
        html.theme-dark [style*="background: #fdf2f8"],
        html.theme-dark [style*="background-color:#fdf2f8"],
        html.theme-dark [style*="background-color: #fdf2f8"] {
            background-color: rgba(237,66,69,0.12) !important;
            color: #ff9a9c !important;
            border-color: rgba(237,66,69,0.30) !important;
        }

        /* === [C] パステル緑/シアン/エメラルド/ティール系 ===
           ecfdf5 (emerald-50), d1fae5 (emerald-100), a7f3d0 (emerald-200),
           dcfce7 (green-100), bbf7d0 (green-200),
           ecfeff (cyan-50), cffafe (cyan-100), ccfbf1 (teal-100) */
        html.theme-dark [style*="background:#ecfdf5"],
        html.theme-dark [style*="background: #ecfdf5"],
        html.theme-dark [style*="background-color:#ecfdf5"],
        html.theme-dark [style*="background-color: #ecfdf5"],
        html.theme-dark [style*="background:#d1fae5"],
        html.theme-dark [style*="background: #d1fae5"],
        html.theme-dark [style*="background-color:#d1fae5"],
        html.theme-dark [style*="background-color: #d1fae5"],
        html.theme-dark [style*="background:#a7f3d0"],
        html.theme-dark [style*="background: #a7f3d0"],
        html.theme-dark [style*="background-color:#a7f3d0"],
        html.theme-dark [style*="background-color: #a7f3d0"],
        html.theme-dark [style*="background:#bbf7d0"],
        html.theme-dark [style*="background: #bbf7d0"],
        html.theme-dark [style*="background-color:#bbf7d0"],
        html.theme-dark [style*="background-color: #bbf7d0"],
        html.theme-dark [style*="background:#ecfeff"],
        html.theme-dark [style*="background: #ecfeff"],
        html.theme-dark [style*="background-color:#ecfeff"],
        html.theme-dark [style*="background-color: #ecfeff"],
        html.theme-dark [style*="background:#cffafe"],
        html.theme-dark [style*="background: #cffafe"],
        html.theme-dark [style*="background-color:#cffafe"],
        html.theme-dark [style*="background-color: #cffafe"],
        html.theme-dark [style*="background:#ccfbf1"],
        html.theme-dark [style*="background: #ccfbf1"],
        html.theme-dark [style*="background-color:#ccfbf1"],
        html.theme-dark [style*="background-color: #ccfbf1"] {
            background-color: rgba(87,242,135,0.10) !important;
            color: #8de0a4 !important;
            border-color: rgba(87,242,135,0.28) !important;
        }

        /* === [D] パステル青/インディゴ/紫系の残り ===
           bfdbfe (blue-200), 93c5fd (blue-300), eef2ff (indigo-50),
           c7d2fe (indigo-200), ddd6fe (violet-200), e9d5ff (purple-200),
           faf5ff (purple-50) */
        html.theme-dark [style*="background:#bfdbfe"],
        html.theme-dark [style*="background: #bfdbfe"],
        html.theme-dark [style*="background-color:#bfdbfe"],
        html.theme-dark [style*="background-color: #bfdbfe"],
        html.theme-dark [style*="background:#93c5fd"],
        html.theme-dark [style*="background: #93c5fd"],
        html.theme-dark [style*="background-color:#93c5fd"],
        html.theme-dark [style*="background-color: #93c5fd"],
        html.theme-dark [style*="background:#eef2ff"],
        html.theme-dark [style*="background: #eef2ff"],
        html.theme-dark [style*="background-color:#eef2ff"],
        html.theme-dark [style*="background-color: #eef2ff"],
        html.theme-dark [style*="background:#c7d2fe"],
        html.theme-dark [style*="background: #c7d2fe"],
        html.theme-dark [style*="background-color:#c7d2fe"],
        html.theme-dark [style*="background-color: #c7d2fe"],
        html.theme-dark [style*="background:#ddd6fe"],
        html.theme-dark [style*="background: #ddd6fe"],
        html.theme-dark [style*="background-color:#ddd6fe"],
        html.theme-dark [style*="background-color: #ddd6fe"],
        html.theme-dark [style*="background:#e9d5ff"],
        html.theme-dark [style*="background: #e9d5ff"],
        html.theme-dark [style*="background-color:#e9d5ff"],
        html.theme-dark [style*="background-color: #e9d5ff"],
        html.theme-dark [style*="background:#faf5ff"],
        html.theme-dark [style*="background: #faf5ff"],
        html.theme-dark [style*="background-color:#faf5ff"],
        html.theme-dark [style*="background-color: #faf5ff"] {
            background-color: rgba(88,101,242,0.15) !important;
            color: #c7d0ff !important;
            border-color: rgba(88,101,242,0.32) !important;
        }

        /* === [E] スレート/Zinc/Stone 系の中明度グレー (残り) ===
           e5e7eb (gray-200), e2e8f0 (slate-200), cbd5e1 (slate-300),
           f8fafc (slate-50), f5f5f4 (stone-100), f4f4f5 (zinc-100),
           e7e5e4 (stone-200), d4d4d8 (zinc-300) */
        html.theme-dark [style*="background:#e5e7eb"],
        html.theme-dark [style*="background: #e5e7eb"],
        html.theme-dark [style*="background-color:#e5e7eb"],
        html.theme-dark [style*="background-color: #e5e7eb"],
        html.theme-dark [style*="background:#e2e8f0"],
        html.theme-dark [style*="background: #e2e8f0"],
        html.theme-dark [style*="background-color:#e2e8f0"],
        html.theme-dark [style*="background-color: #e2e8f0"],
        html.theme-dark [style*="background:#cbd5e1"],
        html.theme-dark [style*="background: #cbd5e1"],
        html.theme-dark [style*="background-color:#cbd5e1"],
        html.theme-dark [style*="background-color: #cbd5e1"],
        html.theme-dark [style*="background:#f8fafc"],
        html.theme-dark [style*="background: #f8fafc"],
        html.theme-dark [style*="background-color:#f8fafc"],
        html.theme-dark [style*="background-color: #f8fafc"],
        html.theme-dark [style*="background:#f5f5f4"],
        html.theme-dark [style*="background: #f5f5f4"],
        html.theme-dark [style*="background-color:#f5f5f4"],
        html.theme-dark [style*="background-color: #f5f5f4"],
        html.theme-dark [style*="background:#f4f4f5"],
        html.theme-dark [style*="background: #f4f4f5"],
        html.theme-dark [style*="background-color:#f4f4f5"],
        html.theme-dark [style*="background-color: #f4f4f5"],
        html.theme-dark [style*="background:#e7e5e4"],
        html.theme-dark [style*="background: #e7e5e4"],
        html.theme-dark [style*="background-color:#e7e5e4"],
        html.theme-dark [style*="background-color: #e7e5e4"],
        html.theme-dark [style*="background:#d4d4d8"],
        html.theme-dark [style*="background: #d4d4d8"],
        html.theme-dark [style*="background-color:#d4d4d8"],
        html.theme-dark [style*="background-color: #d4d4d8"] {
            background-color: var(--rd-bg-hover) !important;
            color: var(--rd-text) !important;
        }

        /* === [F] linear-gradient で明るい色を背景に使う箇所 (プロフィールヘッダ等)
           gradient 表記内に明るい hex が現れる要素を狙う。
           例: background:linear-gradient(135deg,#2563eb 0%,#4f46e5 100%) === */
        html.theme-dark [style*="linear-gradient"][style*="#2563eb"],
        html.theme-dark [style*="linear-gradient"][style*="#4f46e5"],
        html.theme-dark [style*="linear-gradient"][style*="#3b82f6"] {
            background: linear-gradient(135deg, var(--rd-bg-3), var(--rd-bg-2)) !important;
            color: var(--rd-text) !important;
        }

        /* === [G] 同系インラインの border-color (明るい灰色境界線) もダーク化 ===
           e2e8f0 / cbd5e1 / d4d4d8 / e7e5e4 を border-color にしている要素を救う */
        html.theme-dark [style*="border-color:#e2e8f0"],
        html.theme-dark [style*="border-color: #e2e8f0"],
        html.theme-dark [style*="border-color:#cbd5e1"],
        html.theme-dark [style*="border-color: #cbd5e1"],
        html.theme-dark [style*="border:1px solid #e2e8f0"],
        html.theme-dark [style*="border: 1px solid #e2e8f0"],
        html.theme-dark [style*="border:1px solid #cbd5e1"],
        html.theme-dark [style*="border: 1px solid #cbd5e1"],
        html.theme-dark [style*="border:1px solid #fde68a"],
        html.theme-dark [style*="border: 1px solid #fde68a"],
        html.theme-dark [style*="border:1px solid #fecaca"],
        html.theme-dark [style*="border: 1px solid #fecaca"],
        html.theme-dark [style*="border:1px solid #fed7aa"],
        html.theme-dark [style*="border: 1px solid #fed7aa"],
        html.theme-dark [style*="border:1px solid #a7f3d0"],
        html.theme-dark [style*="border: 1px solid #a7f3d0"],
        html.theme-dark [style*="border:1px solid #bfdbfe"],
        html.theme-dark [style*="border: 1px solid #bfdbfe"],
        html.theme-dark [style*="border:1px solid #c7d2fe"],
        html.theme-dark [style*="border: 1px solid #c7d2fe"],
        html.theme-dark [style*="border:1px solid #ddd6fe"],
        html.theme-dark [style*="border: 1px solid #ddd6fe"] {
            border-color: var(--rd-border) !important;
        }

        /* === [H] チャットの通知/ピン留めバナーで使われる薄オレンジ ===
           #fff7ed は他で扱い済みだが個別に notice/pinned 系の wrapper を確実に
           暗くする。class 名 .notice-bar や .pinned-bar 等のヒントがあれば
           対応する。Wiki 黄色も Tailwind ベースに統一 */
        html.theme-dark .pinned-bar,
        html.theme-dark .notice-bar {
            background-color: var(--rd-bg-2) !important;
            color: var(--rd-text) !important;
            border-color: var(--rd-border) !important;
        }

        /* === [I] 暗いブラウンや暗黄色 (Wiki スティッキー title 等) のテキストを
           ダーク化されたスティッキー背景でも読める色に置換 === */
        html.theme-dark [style*="color:#713f12"],
        html.theme-dark [style*="color: #713f12"],
        html.theme-dark [style*="color:#92400e"],
        html.theme-dark [style*="color: #92400e"] {
            color: #ffd58a !important;
        }
        html.theme-dark [style*="color:#a16207"],
        html.theme-dark [style*="color: #a16207"] {
            color: #f0b34c !important;
        }
        html.theme-dark [style*="color:#0c4a6e"],
        html.theme-dark [style*="color: #0c4a6e"],
        html.theme-dark [style*="color:#1e40af"],
        html.theme-dark [style*="color: #1e40af"],
        html.theme-dark [style*="color:#1e3a8a"],
        html.theme-dark [style*="color: #1e3a8a"] {
            color: #c7d0ff !important;
        }
        html.theme-dark [style*="color:#047857"],
        html.theme-dark [style*="color: #047857"],
        html.theme-dark [style*="color:#065f46"],
        html.theme-dark [style*="color: #065f46"] {
            color: #8de0a4 !important;
        }
        html.theme-dark [style*="color:#9a3412"],
        html.theme-dark [style*="color: #9a3412"],
        html.theme-dark [style*="color:#7c2d12"],
        html.theme-dark [style*="color: #7c2d12"] {
            color: #ffae72 !important;
        }
        html.theme-dark [style*="color:#581c87"],
        html.theme-dark [style*="color: #581c87"],
        html.theme-dark [style*="color:#6b21a8"],
        html.theme-dark [style*="color: #6b21a8"] {
            color: #d8b4fe !important;
        }
        html.theme-dark [style*="color:#991b1b"],
        html.theme-dark [style*="color: #991b1b"],
        html.theme-dark [style*="color:#b91c1c"],
        html.theme-dark [style*="color: #b91c1c"],
        html.theme-dark [style*="color:#7f1d1d"],
        html.theme-dark [style*="color: #7f1d1d"] {
            color: #ff9a9c !important;
        }
    </style>
    @yield('css')
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            @auth
            {{-- メール / チャット / 添付 への横断ナビ (同じルーム/スレッドを維持) --}}
            {{-- href はデフォルトで素の URL を出しておき、クリック直前に最新の localStorage 値で再構築する --}}
            <li class="nav-item d-inline-flex align-items-center" style="gap:6px;margin-right:8px;"
                x-data="roomNavBar()"
                @rice-room-context-changed.window="_syncFromStorage()">
                <a href="/" data-room-aware="mail"
                   class="d-inline-flex align-items-center"
                   :style="pillStyle('mail')"
                   title="メールへ">
                    <i class="fas fa-envelope" style="font-size:11px;margin-right:4px;"></i><span>メール</span>
                </a>
                <a href="/chats" data-room-aware="chat"
                   class="d-inline-flex align-items-center"
                   :style="pillStyle('chat')"
                   title="チャットへ">
                    <i class="fas fa-comments" style="font-size:11px;margin-right:4px;"></i><span>チャット</span>
                </a>
                <a href="/attachments" data-room-aware="att"
                   class="d-inline-flex align-items-center"
                   :style="pillStyle('att')"
                   title="添付ファイルへ">
                    <i class="fas fa-paperclip" style="font-size:11px;margin-right:4px;"></i><span>添付</span>
                </a>
                {{-- Wiki: ルーム選択時のみクリック可。スレッド選択中などはグレーで無効化 --}}
                <button type="button"
                        :disabled="!roomId"
                        @click="_syncFromStorage(); if (roomId) $dispatch('open-room-wiki', { roomId: roomId })"
                        class="d-inline-flex align-items-center"
                        :style="roomId
                            ? 'text-decoration:none;background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;font-size:11px;font-weight:700;padding:4px 10px;border-radius:9999px;line-height:1.2;cursor:pointer;'
                            : 'text-decoration:none;background:#f3f4f6;color:#9ca3af;border:1px solid #e5e7eb;font-size:11px;font-weight:700;padding:4px 10px;border-radius:9999px;line-height:1.2;cursor:not-allowed;opacity:0.7;'"
                        :title="roomId ? 'このルームの Wiki を開く' : 'ルームを選択すると Wiki を利用できます'">
                    <i class="fas fa-book" style="font-size:11px;margin-right:4px;"></i><span>Wiki</span>
                </button>
            </li>
            {{-- 全ルームに振り分けルールを再適用 (=過去メールを一括振り分け). どの画面からでも 1 クリックで実行できるグローバルボタン. --}}
            <li class="nav-item d-inline-flex align-items-center" style="margin-right:8px;"
                x-data="rerouteAllBtn()">
                <button type="button"
                        :disabled="busy"
                        @click="run()"
                        class="d-inline-flex align-items-center"
                        style="text-decoration:none;background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;font-size:11px;font-weight:700;padding:4px 10px;border-radius:9999px;line-height:1.2;cursor:pointer;"
                        title="全ルームの振り分けルールを過去メールに再適用 (Alt+Shift+M)">
                    <i class="fas" :class="busy ? 'fa-spinner fa-spin' : 'fa-magic'"
                       style="font-size:11px;margin-right:4px;"></i>
                    <span x-text="busy ? '適用中...' : '再振り分け'"></span>
                </button>
            </li>
            {{-- 通知ベル --}}
            <li class="nav-item dropdown" x-data="notifApp()" x-init="poll()" @click.away="open = false">
                <a class="nav-link position-relative" href="#" @click.prevent="toggle()">
                    <i class="fas fa-bell"></i>
                    <span x-show="unread > 0"
                        class="badge badge-danger navbar-badge"
                        x-text="unread > 9 ? '9+' : unread"></span>
                </a>
                <div x-show="open" x-transition
                    class="dropdown-menu dropdown-menu-right shadow"
                    style="width:360px;max-height:440px;overflow-y:auto;display:block;">
                    <div class="dropdown-header d-flex justify-content-between align-items-center px-3 py-2">
                        <strong>通知</strong>
                        <button x-show="unread > 0" @click="readAll()" class="btn btn-xs btn-link text-muted p-0">すべて既読</button>
                    </div>
                    <template x-if="items.length === 0">
                        <div class="dropdown-item text-muted text-center py-3">通知はありません</div>
                    </template>
                    <template x-for="n in items" :key="n.id">
                        <a :href="bellLinkFor(n)" @click="markRead(n.id)"
                            class="dropdown-item border-bottom py-2"
                            :class="n.read_at ? 'text-muted' : 'font-weight-bold'">
                            <div class="d-flex align-items-start" style="gap:8px;">
                                <i :class="bellIcon(n)" class="mt-1 mr-2" style="min-width:18px;text-align:center;"></i>
                                <div style="min-width:0;flex:1;">
                                    <div class="text-truncate" style="max-width:280px" x-text="bellTitle(n)"></div>
                                    <small class="text-muted text-truncate d-block" style="max-width:280px;" x-text="bellSubtitle(n)"></small>
                                </div>
                            </div>
                        </a>
                    </template>
                    <template x-if="items.length > 0">
                        <div class="d-flex" style="gap:0;">
                            <a href="{{ route('approvals.index') }}" class="dropdown-item text-center text-primary py-2 flex-fill border-right" style="font-size:12px;">
                                <i class="fas fa-check-double mr-1"></i>承認
                            </a>
                            <a href="{{ route('chats.index') }}" class="dropdown-item text-center text-success py-2 flex-fill" style="font-size:12px;">
                                <i class="fas fa-comments mr-1"></i>チャット一覧
                            </a>
                        </div>
                    </template>
                </div>
            </li>
            {{--
                キーボードショートカット ヘルプボタン (メール画面のみ表示)。
                以前は他の navbar 要素 (ユーザーメニューの :hover などで広がる領域) に
                覆われてクリックできなかったため、 position:relative + z-index で
                確実にクリック可能にする. pointer-events:auto も明示.
                onclick で console.log を吐いてクリック検知できるようにしてある.
            --}}
            @if(request()->routeIs('emails.*'))
            <li class="nav-item" style="position:relative;z-index:1500;">
                <button type="button"
                        onclick="console.log('[kbd icon] clicked'); window.riceShowKeyboardShortcuts && window.riceShowKeyboardShortcuts(); window.dispatchEvent(new CustomEvent('open-shortcuts-help'));"
                        style="background:transparent;border:0;cursor:pointer;color:#6b7280;padding:10px 14px;font-size:16px;line-height:1;display:inline-flex;align-items:center;justify-content:center;position:relative;z-index:1501;pointer-events:auto;"
                        onmouseover="this.style.color='#2563eb';this.style.background='#f3f4f6';"
                        onmouseout="this.style.color='#6b7280';this.style.background='transparent';"
                        title="キーボードショートカット (? キーでも開けます)">
                    <i class="fas fa-keyboard"></i>
                </button>
            </li>
            <script>
            /**
             * グローバル関数: キーボードショートカット ヘルプモーダルをバニラ JS で表示.
             * Alpine x-data / x-if / x-show / Tailwind ビルド状態に一切依存せず、
             * document.body 直下に DOM を直接 append する保険経路.
             * 上の onclick から window.riceShowKeyboardShortcuts() で呼ばれる.
             * Alpine 経由でも開ける (window event listener が別途登録されている) ので、
             * どちらか一方でも動けば確実に表示される.
             */
            window.riceShowKeyboardShortcuts = function () {
                const EXISTING_ID = 'rice-shortcuts-help-vanilla';
                const old = document.getElementById(EXISTING_ID);
                if (old) { old.remove(); return; }   // 開いていたら閉じる (トグル)

                const overlay = document.createElement('div');
                overlay.id = EXISTING_ID;
                overlay.setAttribute('style',
                    'position:fixed;top:0;left:0;right:0;bottom:0;z-index:999999;' +
                    'display:flex;align-items:center;justify-content:center;padding:16px;' +
                    'background:rgba(0,0,0,0.55);'
                );
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) overlay.remove();
                });

                const modal = document.createElement('div');
                modal.setAttribute('style',
                    'background:#ffffff;width:768px;max-width:96vw;max-height:88vh;' +
                    'border-radius:14px;box-shadow:0 24px 60px rgba(0,0,0,0.32);' +
                    'display:flex;flex-direction:column;overflow:hidden;'
                );

                // キーボード kbd 風スタイル. インラインで完全に閉じる.
                const kbd = (key) => '<span style="display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 6px;font-family:ui-monospace,SFMono-Regular,monospace;font-size:10px;font-weight:700;line-height:1;color:#374151;background:#fff;border:1px solid #d1d5db;border-bottom-width:2px;border-radius:4px;box-shadow:0 1px 0 rgba(0,0,0,.04);text-transform:uppercase;">' + key + '</span>';

                const liStyle = 'display:flex;align-items:center;justify-content:space-between;';
                const ulStyle = 'margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;font-size:12px;color:#374151;';
                const h3Style = 'margin:0 0 8px;font-size:10px;font-weight:900;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;';
                const sectionStyle = '';

                modal.innerHTML = `
                    <div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:12px;background:linear-gradient(90deg,#eff6ff,#eef2ff);">
                        <div style="width:40px;height:40px;border-radius:10px;background:#2563eb;color:#fff;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-keyboard"></i>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <h2 style="margin:0;font-size:15px;font-weight:800;color:#1e3a8a;">キーボードショートカット</h2>
                            <p style="margin:2px 0 0;font-size:11px;color:#60a5fa;">マウスオーバー中の行 → 開いているスレッド の順で対象が決まります。入力中・モーダル中は無効。</p>
                        </div>
                        <button type="button" id="rice-shortcuts-close"
                                style="background:transparent;border:0;color:#94a3b8;cursor:pointer;padding:6px 8px;font-size:14px;border-radius:6px;"
                                title="閉じる (Esc)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div style="padding:20px;overflow-y:auto;flex:1;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px 32px;">
                        <div style="${sectionStyle}">
                            <h3 style="${h3Style}">ナビゲーション</h3>
                            <ul style="${ulStyle}">
                                <li style="${liStyle}"><span>次のスレッド / 行</span>${kbd('J')}</li>
                                <li style="${liStyle}"><span>前のスレッド / 行</span>${kbd('K')}</li>
                                <li style="${liStyle}"><span>スレッド内 次のメール</span>${kbd(']')}</li>
                                <li style="${liStyle}"><span>スレッド内 前のメール</span>${kbd('[')}</li>
                                <li style="${liStyle}"><span>1 つ前に戻す (undo)</span><span style="display:inline-flex;gap:2px;">${kbd('Ctrl')}+${kbd('Z')}</span></li>
                                <li style="${liStyle}"><span>検索クリア / 選択解除 / 閉じる</span>${kbd('Esc')}</li>
                                <li style="${liStyle}"><span>メイン検索にフォーカス</span>${kbd('/')}</li>
                                <li style="${liStyle}"><span>このヘルプを開閉</span>${kbd('?')}</li>
                            </ul>
                            <p style="margin-top:8px;font-size:10px;color:#9ca3af;">
                                <i class="fas fa-info-circle" style="margin-right:3px;"></i>
                                J/K と Esc, / , ? は メール / 承認・送信 / 添付ファイル / チャット の各画面で共通.
                            </p>
                        </div>
                        <div style="${sectionStyle}">
                            <h3 style="${h3Style}">画面切替 (どの画面でも有効)</h3>
                            <ul style="${ulStyle}">
                                <li style="${liStyle}"><span>メール</span><span style="display:inline-flex;gap:2px;">${kbd('Alt')}+${kbd('M')}</span></li>
                                <li style="${liStyle}"><span>チャット</span><span style="display:inline-flex;gap:2px;">${kbd('Alt')}+${kbd('C')}</span></li>
                                <li style="${liStyle}"><span>添付ファイル</span><span style="display:inline-flex;gap:2px;">${kbd('Alt')}+${kbd('A')}</span></li>
                                <li style="${liStyle}"><span>Wiki</span><span style="display:inline-flex;gap:2px;">${kbd('Alt')}+${kbd('W')}</span></li>
                                <li style="${liStyle}"><span>ルーム管理</span><span style="display:inline-flex;gap:2px;">${kbd('Alt')}+${kbd('R')}</span></li>
                                <li style="${liStyle}"><span>ダーク / ライト切替</span><span style="display:inline-flex;gap:2px;">${kbd('Ctrl')}+${kbd('Shift')}+${kbd('L')}</span></li>
                                <li style="${liStyle}"><span>全ルームで振り分けルール再適用</span><span style="display:inline-flex;gap:2px;">${kbd('Alt')}+${kbd('Shift')}+${kbd('M')}</span></li>
                            </ul>
                        </div>
                        <div style="${sectionStyle}">
                            <h3 style="${h3Style}">行アクション (ホバー / 表示中)</h3>
                            <ul style="${ulStyle}">
                                <li style="${liStyle}"><span>返信 (要: 表示中スレッド)</span>${kbd('R')}</li>
                                <li style="${liStyle}"><span>全員に返信 (要: 表示中)</span><span style="display:inline-flex;gap:2px;">${kbd('Shift')}+${kbd('R')}</span></li>
                                <li style="${liStyle}"><span>完了</span>${kbd('E')}</li>
                                <li style="${liStyle}"><span>保留</span>${kbd('H')}</li>
                                <li style="${liStyle}"><span>受信箱に戻す</span>${kbd('I')}</li>
                                <li style="${liStyle}"><span>ピン留めトグル</span>${kbd('P')}</li>
                                <li style="${liStyle}"><span>迷惑メール</span>${kbd('S')}</li>
                                <li style="${liStyle}"><span>ルームに追加</span>${kbd('L')}</li>
                                <li style="${liStyle};color:#dc2626;">
                                    <span>削除 (確認あり / 選択モード中は一括)</span>
                                    <span style="display:inline-flex;gap:2px;align-items:center;">${kbd('D')}<span style="color:#d1d5db;font-size:10px;">/</span>${kbd('Del')}<span style="color:#d1d5db;font-size:10px;">/</span>${kbd('Ctrl')}+${kbd('Del')}</span>
                                </li>
                            </ul>
                        </div>
                        <div style="${sectionStyle}">
                            <h3 style="${h3Style}">作成 / 同期</h3>
                            <ul style="${ulStyle}">
                                <li style="${liStyle}"><span>新規メール作成</span>${kbd('C')}</li>
                                <li style="${liStyle}"><span>メール取得 (sync)</span>${kbd('G')}</li>
                            </ul>
                        </div>
                        <div style="${sectionStyle}">
                            <h3 style="${h3Style}">ステータスタブ切替</h3>
                            <ul style="${ulStyle}">
                                <li style="${liStyle}"><span>受信</span>${kbd('1')}</li>
                                <li style="${liStyle}"><span>保留</span>${kbd('2')}</li>
                                <li style="${liStyle}"><span>完了</span>${kbd('3')}</li>
                                <li style="${liStyle}"><span>対応不要</span>${kbd('4')}</li>
                                <li style="${liStyle}"><span>承認待ち</span>${kbd('5')}</li>
                                <li style="${liStyle}"><span>迷惑メール</span>${kbd('6')}</li>
                            </ul>
                        </div>
                        <div style="grid-column:span 2;">
                            <h3 style="${h3Style}">複数選択モード</h3>
                            <ul style="${ulStyle}">
                                <li style="${liStyle}"><span>選択モード on/off</span>${kbd('V')}</li>
                                <li style="${liStyle}"><span>表示中スレッドを全選択 / 全解除</span><span style="display:inline-flex;gap:2px;">${kbd('Ctrl')}+${kbd('A')}</span></li>
                                <li style="font-size:11px;color:#6b7280;">選択モード中: 行クリックで選択 / 解除、${kbd('L')} でまとめてルームに追加、${kbd('D')} でまとめて削除</li>
                            </ul>
                        </div>
                    </div>
                    <div style="padding:12px 20px;border-top:1px solid #e5e7eb;background:#f9fafb;display:flex;align-items:center;justify-content:space-between;font-size:11px;color:#6b7280;">
                        <span><i class="fas fa-info-circle" style="margin-right:4px;color:#9ca3af;"></i>入力欄やモーダル表示中はショートカットを受け付けません。</span>
                        <span>閉じる: ${kbd('Esc')}</span>
                    </div>
                `;

                overlay.appendChild(modal);
                document.body.appendChild(overlay);

                // 閉じるボタン
                const closeBtn = modal.querySelector('#rice-shortcuts-close');
                if (closeBtn) closeBtn.addEventListener('click', () => overlay.remove());

                // Esc で閉じる
                const escHandler = (e) => {
                    if (e.key === 'Escape') {
                        overlay.remove();
                        document.removeEventListener('keydown', escHandler);
                    }
                };
                document.addEventListener('keydown', escHandler);
            };
            </script>
            @endif
            {{-- ユーザーメニュー (アバター + 名前をクリックでドロップダウン) --}}
            <li class="nav-item" x-data="userMenu()" @click.away="open = false"
                style="position:relative;">
                <button type="button"
                        @click="toggle()"
                        class="nav-link d-inline-flex align-items-center"
                        style="background:transparent;border:0;padding:6px 14px 6px 8px;cursor:pointer;gap:8px;position:relative;z-index:1100;">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle font-weight-bold"
                          style="width:32px;height:32px;background:#2563eb;color:#fff;font-size:13px;">
                        {{ mb_substr(auth()->user()->name ?? '?', 0, 1) }}
                    </span>
                    <span class="d-none d-sm-inline" style="font-weight:600;color:#111827;">{{ auth()->user()->name }}</span>
                    <i class="fas fa-chevron-down" style="font-size:10px;color:#9ca3af;"></i>
                </button>

                <div x-show="open" x-cloak x-transition
                     class="shadow-lg"
                     style="position:absolute;top:calc(100% + 6px);right:0;width:340px;max-height:520px;overflow-y:auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;z-index:1200;">

                    {{-- プロフィールヘッダー --}}
                    <div style="padding:16px 18px;background:linear-gradient(135deg,#2563eb 0%,#4f46e5 100%);color:#fff;">
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle font-weight-bold flex-shrink-0"
                                  style="width:48px;height:48px;background:rgba(255,255,255,.25);font-size:20px;">
                                {{ mb_substr(auth()->user()->name ?? '?', 0, 1) }}
                            </span>
                            <div style="min-width:0;flex:1;">
                                <div style="font-weight:700;font-size:14px;" class="text-truncate">{{ auth()->user()->name }}</div>
                                <div style="font-size:11px;opacity:.85;" class="text-truncate">{{ auth()->user()->email }}</div>
                                <div style="font-size:9px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-top:2px;opacity:.85;">
                                    @if(auth()->user()->isAdmin())
                                        <i class="fas fa-shield-alt mr-1"></i>管理者
                                    @else
                                        <i class="fas fa-user mr-1"></i>メンバー
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 通知セクション --}}
                    <div style="padding:10px 14px 6px 14px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">
                            <i class="fas fa-bell mr-1"></i>通知 (<span x-text="userMenuItems.length"></span>)
                        </div>
                        <button x-show="userMenuItems.some(n => !n.read_at)" @click="userMenuReadAll()" class="btn btn-link btn-sm p-0" style="font-size:11px;">すべて既読</button>
                    </div>
                    <div style="max-height:200px;overflow-y:auto;">
                        <template x-if="userMenuItems.length === 0">
                            <div class="text-muted text-center" style="padding:24px;font-size:12px;">通知はありません</div>
                        </template>
                        <template x-for="n in userMenuItems" :key="n.id">
                            <a :href="userMenuLinkFor(n)" @click="userMenuMarkRead(n.id)"
                               class="d-block border-bottom"
                               :style="n.read_at ? 'padding:10px 14px;color:#6b7280;text-decoration:none;background:#fff;' : 'padding:10px 14px;color:#111827;text-decoration:none;background:#eff6ff;'">
                                <div class="d-flex align-items-start gap-2">
                                    <i :class="userMenuIcon(n)" style="margin-top:3px;"></i>
                                    <div style="min-width:0;flex:1;">
                                        <div class="text-truncate" style="font-size:12px;font-weight:600;" x-text="userMenuTitle(n)"></div>
                                        <div class="text-truncate text-muted" style="font-size:10px;" x-text="userMenuSubtitle(n)"></div>
                                    </div>
                                </div>
                            </a>
                        </template>
                    </div>

                    {{-- アクション --}}
                    <div style="padding:8px;border-top:1px solid #f3f4f6;background:#fafafa;">
                        <a href="{{ route('profile.edit') }}" class="dropdown-item" style="border-radius:8px;font-size:13px;font-weight:600;">
                            <i class="fas fa-user-cog mr-2 text-primary"></i>プロフィール設定
                        </a>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('settings.mail') }}" class="dropdown-item" style="border-radius:8px;font-size:13px;font-weight:600;">
                                <i class="fas fa-envelope mr-2 text-primary"></i>メール設定
                            </a>
                            <a href="{{ route('admin.invitations.index') }}" class="dropdown-item" style="border-radius:8px;font-size:13px;font-weight:600;">
                                <i class="fas fa-user-plus mr-2 text-primary"></i>招待管理
                            </a>
                        @endif
                        <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger" style="border-radius:8px;font-size:13px;font-weight:600;">
                                <i class="fas fa-sign-out-alt mr-2"></i>ログアウト
                            </button>
                        </form>
                    </div>
                </div>
            </li>
            @endauth
        </ul>
    </nav>

    @auth
    {{-- ===== グローバル ルーム Wiki ドロワー (複数カード + 全画面モード対応) ===== --}}
    <div x-data="globalRoomWiki()"
         @open-room-wiki.window="openFor($event.detail?.roomId)">
        {{-- 背景 (オーバーレイ) --}}
        <div x-show="open" x-cloak
             @click="if (!fullscreenId) open = false"
             style="position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9998;"></div>

        {{-- メインドロワー (カード一覧) --}}
        <aside x-show="open && !fullscreenId" x-cloak
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="translate-x-full opacity-0"
               x-transition:enter-end="translate-x-0 opacity-100"
               x-transition:leave="transition ease-in duration-150"
               x-transition:leave-start="translate-x-0 opacity-100"
               x-transition:leave-end="translate-x-full opacity-0"
               style="position:fixed;top:0;right:0;bottom:0;width:560px;max-width:96vw;background:#ffffff;z-index:9999;box-shadow:-12px 0 32px rgba(0,0,0,0.18);display:flex;flex-direction:column;">
            {{-- ヘッダ --}}
            <div style="background:#ffffff;border-bottom:1px solid #e5e7eb;padding:12px 16px;display:flex;align-items:center;gap:8px;flex-shrink:0;">
                <i class="fas fa-book" style="color:#0ea5e9;"></i>
                <div style="flex:1;min-width:0;">
                    <p style="margin:0;font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;">ルーム Wiki</p>
                    <p style="margin:0;font-size:13px;font-weight:700;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                       x-text="roomName || '(ルーム名取得中…)'"></p>
                </div>
                {{--
                    重要: Alpine の :style 文字列バインディングは静的 style 属性を上書きするので、
                    背景色など見た目に必要なスタイルも :style 側にまとめる。
                    (旧: style="background:#0ea5e9;..." + :style="''" → 通常時に背景が消えてボタンが透明化していた)
                --}}
                <button @click="addCard()" :disabled="adding"
                        :style="'display:inline-flex;align-items:center;gap:4px;background:#0ea5e9;color:#fff;border:none;padding:6px 10px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;' + (adding ? 'opacity:0.5;cursor:not-allowed;' : '')"
                        title="新規カード追加">
                    <i class="fas" :class="adding ? 'fa-spinner fa-spin' : 'fa-plus'"></i>
                    <span>カード追加</span>
                </button>
                <button @click="open = false" style="background:none;border:none;color:#6b7280;padding:4px;" title="閉じる"><i class="fas fa-times"></i></button>
            </div>
            {{-- カード一覧 (2 列グリッド + ローディング/空状態は全幅) --}}
            <div class="global-wiki-grid" style="flex:1;padding:14px;background:#f0f9ff;min-height:0;overflow-y:auto;">
                <template x-if="loading">
                    <p class="global-wiki-full text-center" style="color:#9ca3af;font-size:12px;padding:16px 0;"><i class="fas fa-circle-notch fa-spin mr-1"></i>読み込み中...</p>
                </template>
                <template x-if="!loading && cards.length === 0">
                    <div class="global-wiki-full" style="text-align:center;padding:40px 16px;color:#9ca3af;font-size:12px;">
                        <i class="fas fa-sticky-note fa-2x" style="opacity:0.3;margin-bottom:10px;"></i>
                        <p style="margin:0;">まだ Wiki がありません</p>
                        <p style="margin:6px 0 0;">右上の「カード追加」から始めましょう</p>
                    </div>
                </template>
                <template x-for="(card, idx) in cards" :key="card.id">
                    <div class="global-wiki-card" style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:8px 10px;display:flex;flex-direction:column;gap:4px;box-shadow:0 1px 2px rgba(0,0,0,0.04);min-height:120px;">
                        {{-- タイトル + アイコンボタン (タイトルは任意。空でも保存可能) --}}
                        <div style="display:flex;align-items:center;gap:6px;">
                            <input type="text" x-model="card.title"
                                   @input.debounce.700ms="autoSave(card)"
                                   @blur="autoSave(card, true)"
                                   placeholder="タイトル (任意)"
                                   style="flex:1;background:transparent;border:none;padding:2px 4px;font-size:13px;font-weight:700;color:#713f12;outline:none;">
                            {{-- 保存ステータス --}}
                            <span x-show="card._saving" style="font-size:9px;color:#9ca3af;"><i class="fas fa-spinner fa-spin"></i></span>
                            <span x-show="!card._saving && card._saved" style="font-size:9px;color:#059669;"><i class="fas fa-check"></i></span>
                            <button @click="enterFullscreen(card.id)" title="全画面で開く"
                                    style="background:none;border:none;color:#a16207;padding:2px 4px;cursor:pointer;font-size:11px;">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button @click="removeCard(card)" title="カード削除"
                                    style="background:none;border:none;color:#dc2626;padding:2px 4px;cursor:pointer;font-size:11px;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        {{-- 本文。debounce 700ms + blur で自動保存。"保存" ボタンは不要 --}}
                        <textarea x-model="card.content"
                                  @input.debounce.700ms="autoSave(card)"
                                  @blur="autoSave(card, true)"
                                  :data-card-id="card.id"
                                  placeholder="メモを入力..."
                                  style="width:100%;min-height:60px;max-height:240px;background:transparent;border:none;padding:2px 4px;font-size:13px;line-height:1.6;color:#1f2937;outline:none;resize:vertical;font-family:inherit;"></textarea>
                    </div>
                </template>
            </div>
        </aside>

        {{-- 全画面モーダル --}}
        <div x-show="fullscreenId" x-cloak
             style="position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;padding:24px;">
            <template x-for="card in cards" :key="'fs-' + card.id">
                <div x-show="fullscreenId === card.id"
                     style="background:#ffffff;border-radius:12px;width:100%;max-width:1200px;height:100%;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,0.5);overflow:hidden;">
                    <div style="background:#0c4a6e;color:#fff;padding:14px 18px;display:flex;align-items:center;gap:10px;">
                        <i class="fas fa-book"></i>
                        <input type="text" x-model="card.title"
                               @input.debounce.700ms="autoSave(card)"
                               @blur="autoSave(card, true)"
                               placeholder="タイトル (任意)"
                               style="flex:1;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.3);border-radius:6px;padding:6px 10px;font-size:14px;font-weight:700;color:#fff;outline:none;">
                        <span x-show="card._saving" style="font-size:11px;color:rgba(255,255,255,0.7);"><i class="fas fa-spinner fa-spin"></i> 保存中...</span>
                        <span x-show="!card._saving && card._saved" style="font-size:11px;color:#86efac;"><i class="fas fa-check-circle"></i> 保存しました</span>
                        <button @click="exitFullscreen()" title="全画面解除"
                                style="background:none;border:none;color:#fff;padding:6px;cursor:pointer;">
                            <i class="fas fa-compress"></i>
                        </button>
                        <button @click="fullscreenId = null; open = false" title="閉じる"
                                style="background:none;border:none;color:#fff;padding:6px;cursor:pointer;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <textarea x-model="card.content"
                              @input.debounce.700ms="autoSave(card)"
                              @blur="autoSave(card, true)"
                              placeholder="このカードのメモ..."
                              style="flex:1;width:100%;background:#ffffff;border:none;padding:20px 24px;font-size:15px;line-height:1.8;color:#1f2937;outline:none;resize:none;font-family:inherit;"></textarea>
                </div>
            </template>
        </div>
    </div>
    @endauth

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="/" class="brand-link" title="Rice ホーム">
            <span class="brand-icon">R</span>
            <span class="brand-text">Rice</span>
        </a>

        @php
            // 自分宛の承認待ち件数 (バッジ表示用)
            $myPendingApprovalCount = auth()->check()
                ? \App\Models\PendingEmail::where('status', \App\Models\PendingEmail::STATUS_PENDING)
                    ->where('target_approver_user_id', auth()->id())
                    ->count()
                : 0;
        @endphp
        <div class="sidebar">
            <nav class="mt-2">
                {{-- 主要4項目。href は素のままで、ページロード後に
                     <script id="sidebar-room-bridge"> 内のバニラ JS が currentRoomId を読み取り
                     ?room=Y / ?thread=X を付加する。Alpine も AdminLTE jQuery も介さないので
                     クリックの取りこぼしがない。 --}}
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    {{-- 主要4項目 --}}
                    <li class="nav-item">
                        <a href="{{ route('emails.index') }}"
                           data-room-aware="mail"
                           class="nav-link {{ request()->routeIs('emails.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-envelope"></i>
                            <p>メール一覧</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('drafts.index') }}" class="nav-link {{ request()->routeIs('drafts.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>下書き</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('approvals.index') }}" class="nav-link {{ request()->routeIs('approvals.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-check-double"></i>
                            <p>
                                承認・送信
                                @if($myPendingApprovalCount > 0)
                                    <span class="badge badge-warning right">{{ $myPendingApprovalCount > 99 ? '99+' : $myPendingApprovalCount }}</span>
                                @endif
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('attachments.index') }}"
                           data-room-aware="att"
                           class="nav-link {{ request()->routeIs('attachments.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-paperclip"></i>
                            <p>添付ファイル</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('chats.index') }}"
                           data-room-aware="chat"
                           class="nav-link {{ request()->routeIs('chats.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-comments"></i>
                            <p>チャット一覧</p>
                        </a>
                    </li>
                    {{-- ルーム管理: 振り分けルール / 編集 / 削除 を 1 画面で扱う --}}
                    <li class="nav-item">
                        <a href="{{ route('rooms.index') }}"
                           class="nav-link {{ request()->routeIs('rooms.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-th-large"></i>
                            <p>ルーム</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('wiki.index') }}"
                           data-room-aware="wiki"
                           class="nav-link {{ request()->routeIs('wiki.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-book"></i>
                            <p>Wiki</p>
                        </a>
                    </li>

                    {{-- 補助メニュー --}}
                    <li class="nav-item">
                        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <p>レポート</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('knowledge.index') }}" class="nav-link {{ request()->routeIs('knowledge.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-book"></i>
                            <p>ナレッジベース</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('settings.ai_skills.index') }}" class="nav-link {{ request()->routeIs('settings.ai_skills.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-magic"></i>
                            <p>AIスキル</p>
                        </a>
                    </li>
                    {{-- 迷惑メール設定: 管理者でなくても自分が登録したルールは編集可能 --}}
                    <li class="nav-item">
                        <a href="{{ route('settings.spam') }}" class="nav-link {{ request()->routeIs('settings.spam*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-shield-alt"></i>
                            <p>迷惑メール設定</p>
                        </a>
                    </li>
                    @if(auth()->user() && auth()->user()->isAdmin())
                    <li class="nav-header">ADMIN</li>
                    <li class="nav-item">
                        <a href="{{ route('admin.invitations.index') }}" class="nav-link {{ request()->routeIs('admin.invitations.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-user-plus"></i>
                            <p>招待管理</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('settings.mail') }}" class="nav-link {{ request()->routeIs('settings.mail*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-envelope-config"></i>
                            <p>メール設定</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('settings.ai') }}" class="nav-link {{ request()->routeIs('settings.ai*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-robot"></i>
                            <p>AI設定</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('settings.sso') }}" class="nav-link {{ request()->routeIs('settings.sso*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-key"></i>
                            <p>SSO設定</p>
                        </a>
                    </li>
                    @endif
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>@yield('header', '')</h1>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer text-sm" style="display:none"></footer>
</div>

<!--
    AdminLTE & Bootstrap JS.
    `defer` を付与してパーサーをブロックしないようにする (= ページ遷移時の真っ白対策).
    旧仕様だと jQuery → Bootstrap → AdminLTE の 3 本が直列ダウンロード & 評価で、
    遅い接続だと数秒間 first paint が遅れていた. defer で並行ダウンロード + DOMContentLoaded
    の直前に実行されるので Alpine の初期化を阻害しない.
    順序は保持される (defer 同士は記述順) ので $ → bootstrap → AdminLTE の依存関係も維持.
-->
<script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js" defer></script>

{{--
    ============================================================
    真っ白問題 (blank page) のセーフティネット
    ============================================================
    背景: メール画面など Alpine x-cloak を多用するページで、
          リダイレクト直後にごく稀に画面が真っ白になる事象あり.
          (Vite の chunk 取りこぼし / Alpine.start 前の throw 等で
           x-cloak 属性が外れず、CSS の display:none が残り続ける)
    対策: ページ表示 3.5 秒経過時点で window.Alpine が無いか、
          ルート x-data に x-cloak が残っているなら強制的に外し、
          ユーザに「データの取得に失敗しました。再読込してください」
          バナーを出す. これで完全な「真っ白」状態は防げる.
    ※ Alpine が正常に起動した場合は x-cloak が既に消えているので何もしない.

    あわせて bfcache 復帰時 (戻る/進む) に古い JS state で表示崩れする
    ケースを救うため、event.persisted=true の pageshow を全画面で
    検知し、表示が空に見える時だけ自動 reload する.
--}}
<script id="rice-blank-safety-net">
(function () {
    var STARTED_AT = Date.now();
    var TIMEOUT_MS = 3500;

    function alpineLooksBooted() {
        return typeof window.Alpine !== 'undefined'
            && window.Alpine
            && typeof window.Alpine.version !== 'undefined';
    }

    function stripCloakFallback() {
        try {
            var nodes = document.querySelectorAll('[x-cloak]');
            if (nodes.length === 0) return;
            console.warn('[rice-safety] Alpine appears not to have booted; force-stripping x-cloak from', nodes.length, 'elements');
            nodes.forEach(function (n) { try { n.removeAttribute('x-cloak'); } catch (_) {} });

            // ユーザに状況を知らせる小さなバナー (再読込ボタン付き).
            // 既に出していたら何もしない.
            if (document.getElementById('rice-blank-banner')) return;
            var div = document.createElement('div');
            div.id = 'rice-blank-banner';
            div.style.cssText = 'position:fixed;top:8px;left:50%;transform:translateX(-50%);z-index:99999;'
                + 'background:#fef3c7;color:#92400e;border:1px solid #fde68a;'
                + 'padding:8px 14px;border-radius:8px;font-size:12px;font-weight:700;'
                + 'box-shadow:0 8px 24px rgba(0,0,0,0.2);display:flex;align-items:center;gap:10px;';
            div.innerHTML = '<span>ページの初期化に失敗した可能性があります。</span>';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = '再読み込み';
            btn.style.cssText = 'background:#b45309;color:#fff;border:none;border-radius:6px;'
                + 'padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;';
            btn.onclick = function () { try { window.location.reload(); } catch (_) {} };
            div.appendChild(btn);
            (document.body || document.documentElement).appendChild(div);
        } catch (e) {
            // 最終手段: console に出すだけ
            try { console.error('[rice-safety] stripCloakFallback failed', e); } catch (_) {}
        }
    }

    // x-data ルートが起動 (Alpine が __x プロパティを付ける) しているかを確認する.
    // Alpine 3 では Alpine.initTree 後に __x が付くため、これで起動判定ができる.
    function rootDataInitialized() {
        var roots = document.querySelectorAll('[x-data]');
        if (roots.length === 0) return true; // x-data なしページは判定不要
        for (var i = 0; i < roots.length; i++) {
            // Alpine 3 系は _x_dataStack を node に付与する
            if (roots[i]._x_dataStack || roots[i].__x) return true;
        }
        return false;
    }

    function check() {
        // Alpine が起動して x-data も初期化されていれば何もしない
        if (alpineLooksBooted() && rootDataInitialized()) return;
        stripCloakFallback();
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(check, TIMEOUT_MS);
    } else {
        window.addEventListener('DOMContentLoaded', function () { setTimeout(check, TIMEOUT_MS); });
    }

    // bfcache (戻る/進む) 復帰時、ページ DOM は残っているが JS state がリセットされていて
    // x-data 側が古い状態のまま表示されることがある.
    // event.persisted === true の場合のみ、現在 x-cloak が残っているか判定し、
    // 残っているなら即時 reload (これで Alpine が再起動する).
    window.addEventListener('pageshow', function (ev) {
        if (!ev || !ev.persisted) return;
        try {
            var hasCloak = document.querySelector('[x-cloak]') !== null;
            if (hasCloak && !rootDataInitialized()) {
                // Alpine が走っていないので強制リロード
                console.warn('[rice-safety] bfcache restore + Alpine not booted -> reload');
                window.location.reload();
            }
        } catch (_) {}
    });
})();
</script>

{{-- サイドバーの主要 4 項目に room/thread コンテキストを引き継ぐためのバニラ JS。
     [data-room-aware="mail|chat|att"] が付いた <a> の href を、localStorage の
     currentRoomId / currentThreadId に応じて書き換える。
     Alpine / AdminLTE jQuery と干渉しないよう純粋な DOM 操作のみ。 --}}
<script id="sidebar-room-bridge">
(function () {
    function pickInt(key) {
        try {
            var raw = localStorage.getItem(key);
            if (!raw) return null;
            if (!/^\d+$/.test(raw)) return null;
            var n = parseInt(raw, 10);
            return (n > 0) ? n : null;
        } catch (_) { return null; }
    }
    function buildHref(kind, basePath) {
        var roomId = pickInt('currentRoomId');
        var threadId = pickInt('currentThreadId');
        // 'mail': room + thread 両方を渡す (スレッド詳細を開ける)
        // 'chat' / 'att' / 'wiki': room があれば room のみ。なければ thread を渡す
        var params = [];
        if (kind === 'mail') {
            if (threadId) params.push('thread=' + threadId);
            if (roomId)   params.push('room='   + roomId);
        } else {
            if (roomId)        params.push('room='   + roomId);
            else if (threadId) params.push('thread=' + threadId);
        }
        return basePath + (params.length ? ('?' + params.join('&')) : '');
    }
    var basePaths = { mail: '/', chat: '/chats', att: '/attachments', wiki: '/wiki' };
    function refresh() {
        var links = document.querySelectorAll('a[data-room-aware]');
        links.forEach(function (a) {
            var kind = a.getAttribute('data-room-aware');
            if (!basePaths[kind]) return;
            a.href = buildHref(kind, basePaths[kind]);
        });
    }
    // 初期化 + ルームコンテキスト変更時に href を更新
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refresh);
    } else {
        refresh();
    }
    window.addEventListener('rice-room-context-changed', refresh);
    // 別タブで localStorage が変わったケースにも追従
    window.addEventListener('storage', function (e) {
        if (e.key === 'currentRoomId' || e.key === 'currentThreadId') refresh();
    });
})();
</script>

<script>
function notifApp() {
    return {
        open: false,
        items: [],
        unread: 0,
        _timer: null,

        async fetch() {
            try {
                const res = await fetch('/notifications', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                this.items = await res.json();
                this.unread = this.items.filter(n => !n.read_at).length;
            } catch (e) {}
        },

        poll() {
            this.fetch();
            this._timer = setInterval(() => this.fetch(), 60000);
        },

        toggle() {
            this.open = !this.open;
        },

        async markRead(id) {
            await fetch(`/notifications/${id}/read`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            this.fetch();
        },

        async readAll() {
            await fetch('/notifications/read-all', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            this.fetch();
        },

        // 通知種別ごとに表示を切り替える (チャットメンション / 承認依頼 / 却下)
        bellIcon(n) {
            const k = n.data?.kind;
            if (k === 'chat_mention') return 'fas fa-at text-success';
            if (k === 'rejected')     return 'fas fa-times-circle text-danger';
            return 'fas fa-envelope-open-text text-primary';
        },
        bellTitle(n) {
            const k = n.data?.kind;
            if (k === 'chat_mention') return '@' + (n.data?.mentioner || '誰か') + ' があなたをメンション';
            if (k === 'rejected')     return '却下: ' + (n.data?.subject || '(無題)');
            return '承認依頼: ' + (n.data?.subject || '(無題)');
        },
        bellSubtitle(n) {
            const k = n.data?.kind;
            if (k === 'chat_mention') {
                const subj = n.data?.thread_subject || '';
                const prev = n.data?.preview || '';
                return subj ? (subj + ' — ' + prev) : prev;
            }
            if (k === 'rejected') return '却下理由: ' + (n.data?.rejection_reason || '(なし)');
            return '依頼者: ' + (n.data?.created_by || '不明');
        },
        bellLinkFor(n) {
            const k = n.data?.kind;
            if (k === 'chat_mention' && n.data?.thread_id) {
                // チャット一覧の該当スレッドを開き、コメント ID があれば該当行までスクロールさせる
                const cid = n.data?.comment_id;
                return '/chats#thread-' + n.data.thread_id + (cid ? ('&comment=' + cid) : '');
            }
            if (k === 'rejected') return '/drafts';
            return '{{ route('approvals.index') }}';
        }
    };
}

// 全ルームでルール再適用 (グローバルナビバーボタン用)
function rerouteAllBtn() {
    return {
        busy: false,
        init() {
            // Alt+Shift+M: グローバルショートカットで「再振り分け」起動
            window.addEventListener('keydown', (e) => {
                if (!e.altKey || !e.shiftKey) return;
                if (e.ctrlKey || e.metaKey) return;
                if ((e.key || '').toLowerCase() !== 'm') return;
                const t = e.target;
                const tag = (t && t.tagName || '').toLowerCase();
                if (tag === 'input' || tag === 'textarea' || tag === 'select' || (t && t.isContentEditable)) return;
                e.preventDefault();
                this.run();
            });
        },
        async run() {
            if (this.busy) return;
            this.busy = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                const r = await fetch('/api/chat-rooms/_/reapply-all-rules', {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const d = await r.json();
                const rooms = d.processed_rooms || 0;
                const total = d.total_newly_added || 0;
                // total には「新規追加」だけでなく「既存ペアの監査列を埋めた件数」も含まれる.
                // (= 過去手動追加だがルールにも一致するペアの認識整理)
                this._toast(total > 0
                    ? `再振り分け完了: ${rooms} ルーム / 既存スレッド ${total} 件を更新`
                    : `再振り分け完了: ${rooms} ルームを処理 (変更なし)`);
                // ページ側に「ルームリストや一覧を更新せよ」を broadcast (各画面の Alpine が拾える時だけ拾う)
                try { window.dispatchEvent(new CustomEvent('rice-rules-reapplied', { detail: d })); } catch (_) {}
            } catch (e) {
                this._toast('再振り分けに失敗: ' + (e.message || ''), true);
            } finally {
                this.busy = false;
            }
        },
        // 小さなフローティングトースト (どの画面でも単独で動かす必要があるため自前実装)
        _toast(text, isErr) {
            try {
                const el = document.createElement('div');
                el.textContent = text;
                el.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:99999;'
                    + 'background:' + (isErr ? '#dc2626' : '#1f2937') + ';color:#fff;'
                    + 'padding:10px 16px;border-radius:8px;font-size:12px;font-weight:700;'
                    + 'box-shadow:0 8px 24px rgba(0,0,0,0.25);max-width:380px;';
                document.body.appendChild(el);
                setTimeout(() => { try { el.remove(); } catch (_) {} }, 4000);
            } catch (_) {}
        },
    };
}

// ルーム横断ナビゲーション (メール / チャット / 添付)
// 全ビュー共通で localStorage.currentRoomId を読み、リンクに ?room=<id> を付与する。
function roomNavBar() {
    const _pick = (key) => {
        try {
            const raw = localStorage.getItem(key);
            const n = raw && /^\d+$/.test(raw) ? parseInt(raw, 10) : NaN;
            return (!Number.isNaN(n) && n > 0) ? String(n) : null;
        } catch (_) { return null; }
    };
    return {
        roomId: null,
        threadId: null,
        path: '',
        init() {
            this._syncFromStorage();
            this.path = (window.location.pathname || '/').replace(/\/+$/,'') || '/';
        },
        // クリック直前に localStorage を再読込し、最新スコープで遷移する
        _syncFromStorage() {
            this.roomId = _pick('currentRoomId');
            this.threadId = _pick('currentThreadId');
        },
        urlFor(base) {
            // 遷移先によって渡すスコープを切り替える:
            //   - メール (/): room と thread の両方を持っていく。
            //     (mail はスレッド詳細表示も兼ねるので thread 指定が活きる)
            //   - チャット (/chats) / 添付 (/attachments):
            //     room が選択されていれば room のみ。
            //     room 未選択時に限り thread を持っていく。
            //     (用途: 「ルームを選択してチャット → そのルームの一覧」)
            const params = [];
            const isRoomCentric = base.startsWith('/chats') || base.startsWith('/attachments');
            if (isRoomCentric) {
                if (this.roomId)       params.push('room=' + this.roomId);
                else if (this.threadId) params.push('thread=' + this.threadId);
            } else {
                if (this.threadId) params.push('thread=' + this.threadId);
                if (this.roomId)   params.push('room=' + this.roomId);
            }
            return base + (params.length ? ('?' + params.join('&')) : '');
        },
        navTo(base) {
            // 同一ページ内で他コンポーネントが localStorage を更新している可能性があるため再読込
            this._syncFromStorage();
            window.location.href = this.urlFor(base);
        },
        _isActive(kind) {
            if (kind === 'mail') return this.path === '/' || this.path.startsWith('/threads');
            if (kind === 'chat') return this.path.startsWith('/chats');
            if (kind === 'att')  return this.path.startsWith('/attachments');
            return false;
        },
        pillStyle(kind) {
            const active = this._isActive(kind);
            const isDark = document.documentElement.classList.contains('theme-dark');

            // ライト用パレット
            const lightPalette = {
                mail: { bg:'#fffbeb', fg:'#b45309', bd:'#fde68a' },
                chat: { bg:'#eff6ff', fg:'#1d4ed8', bd:'#bfdbfe' },
                att:  { bg:'#ecfdf5', fg:'#047857', bd:'#a7f3d0' },
            };
            // ダーク用パレット : 単色ではなく低彩度のチントで「クリックしても眩しくない」状態に
            const darkPalette = {
                mail: { bg:'rgba(250,166,26,0.10)', fg:'#ffd58a', bd:'rgba(250,166,26,0.30)',
                        activeBg:'rgba(250,166,26,0.22)', activeFg:'#ffd58a', activeBd:'rgba(250,166,26,0.55)' },
                chat: { bg:'rgba(88,101,242,0.10)',  fg:'#c7d0ff', bd:'rgba(88,101,242,0.30)',
                        activeBg:'rgba(88,101,242,0.22)', activeFg:'#ffffff', activeBd:'rgba(88,101,242,0.65)' },
                att:  { bg:'rgba(87,242,135,0.10)',  fg:'#86efac', bd:'rgba(87,242,135,0.30)',
                        activeBg:'rgba(87,242,135,0.22)', activeFg:'#86efac', activeBd:'rgba(87,242,135,0.55)' },
            };
            const common = 'text-decoration:none;font-size:11px;font-weight:700;padding:4px 10px;border-radius:9999px;line-height:1.2;';

            if (isDark) {
                const c = darkPalette[kind];
                if (active) return `${common}background:${c.activeBg};color:${c.activeFg};border:1px solid ${c.activeBd};`;
                return         `${common}background:${c.bg};color:${c.fg};border:1px solid ${c.bd};`;
            }
            const c = lightPalette[kind];
            if (active) return `${common}background:${c.fg};color:#fff;border:1px solid ${c.fg};`;
            return         `${common}background:${c.bg};color:${c.fg};border:1px solid ${c.bd};`;
        },
    };
}

// グローバル ルーム Wiki ドロワー (チャット/メール/添付 すべてから利用)。
// 1 ルーム N 枚のカードを扱う。各カードは独立して保存できる + 全画面表示モードあり。
function globalRoomWiki() {
    return {
        open: false,
        loading: false,
        adding: false,
        roomId: null,
        roomName: '',
        cards: [],            // [{id, title, content, sort_order, updated_at, _dirty, _saving, _saved}]
        fullscreenId: null,   // 全画面表示中のカード ID (null = 非表示)

        async openFor(roomId) {
            if (!roomId) return;
            this.roomId = roomId;
            this.open = true;
            this.loading = true;
            this.cards = [];
            this.fullscreenId = null;
            this.roomName = '';
            // ルーム名 (一覧 API を流用)
            try {
                const rRoom = await fetch('/api/chat-rooms', { headers:{Accept:'application/json'} });
                if (rRoom.ok) {
                    const d = await rRoom.json();
                    const found = (d.rooms || []).find(r => String(r.id) === String(roomId));
                    if (found) this.roomName = found.name || '';
                }
            } catch (_) {}
            // カード一覧。失敗してもエラーウィンドウは出さず、空のまま表示する
            try {
                const r = await fetch(`/api/chat-rooms/${roomId}/wikis`, { headers:{Accept:'application/json'} });
                if (r.ok) {
                    const d = await r.json();
                    this.cards = (d.wikis || []).map(w => this._wrapCard(w));
                } else {
                    console.warn('Wiki 取得失敗 status=' + r.status);
                }
            } catch (e) {
                console.warn('Wiki 取得通信エラー: ' + (e?.message || ''));
            }
            this.loading = false;
        },

        // サーバから返ってきたカードに UI 用フラグを足す
        _wrapCard(w) {
            return {
                id:                 w.id,
                title:              w.title || '',
                content:            w.content || '',
                sort_order:         w.sort_order ?? 0,
                updated_at:         w.updated_at || '',
                _saving:            false,
                _saved:             false,
                // autoSave で「実際に変更があった時だけ保存」する差分判定用
                _lastSavedTitle:    w.title || '',
                _lastSavedContent:  w.content || '',
            };
        },

        markDirty(card) {
            card._dirty = true;
            card._saved = false;
        },

        // 自動保存。debounce で短時間に連続編集してもまとめて 1 回だけ保存。
        // force=true (blur 時) は dirty なら必ず即保存。
        async autoSave(card, force = false) {
            if (!card || !this.roomId) return;
            // 入力イベントから来た時点で「変更あり」とみなす
            const prev = JSON.stringify({ t: card._lastSavedTitle ?? '', c: card._lastSavedContent ?? '' });
            const curr = JSON.stringify({ t: card.title || '', c: card.content || '' });
            if (prev === curr && !force) return;
            if (prev === curr && force) return; // 変更なしなら blur でも保存しない
            if (card._saving) return;
            card._saving = true;
            card._saved = false;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const r = await fetch(`/api/chat-rooms/${this.roomId}/wikis/${card.id}`, {
                    method:'PUT',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
                    body: JSON.stringify({ title: card.title || '', content: card.content || '' }),
                });
                if (!r.ok) {
                    // 保存失敗時は次回 input でリトライさせるため _lastSaved* を更新しない
                    return;
                }
                const d = await r.json();
                if (d.wiki) {
                    card.updated_at = d.wiki.updated_at || '';
                    card._lastSavedTitle = card.title || '';
                    card._lastSavedContent = card.content || '';
                    card._saved = true;
                    setTimeout(() => { card._saved = false; }, 1500);
                }
            } catch (_) { /* 通信エラーはサイレント、次回入力でリトライ */ }
            finally { card._saving = false; }
        },

        async addCard() {
            if (!this.roomId || this.adding) return;
            this.adding = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const r = await fetch(`/api/chat-rooms/${this.roomId}/wikis`, {
                    method:'POST',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
                    body: JSON.stringify({ title: '', content: '' }),
                });
                if (!r.ok) { console.warn('Wiki カード追加失敗 status=' + r.status); return; }
                const d = await r.json();
                if (d.wiki) {
                    const wrapped = this._wrapCard(d.wiki);
                    this.cards.push(wrapped);
                    // 直後に textarea にフォーカス (メモ風に即書ける体験)
                    // x-ref は静的キーしか取れないので data-card-id 属性で DOM 取得
                    this.$nextTick(() => {
                        try {
                            const el = document.querySelector(`textarea[data-card-id="${wrapped.id}"]`);
                            if (el && typeof el.focus === 'function') el.focus();
                        } catch (_) {}
                    });
                }
            } catch (e) { console.warn('Wiki カード追加通信エラー: ' + (e?.message || '')); }
            finally { this.adding = false; }
        },

        async removeCard(card) {
            if (!this.roomId || !card) return;
            const label = card.title ? `「${card.title}」` : 'このカード';
            if (!confirm(`${label} を削除しますか？\n(復元できません)`)) return;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const r = await fetch(`/api/chat-rooms/${this.roomId}/wikis/${card.id}`, {
                    method:'DELETE',
                    headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf},
                });
                if (!r.ok) { console.warn('Wiki カード削除失敗 status=' + r.status); return; }
                this.cards = this.cards.filter(c => c.id !== card.id);
                if (this.fullscreenId === card.id) this.fullscreenId = null;
            } catch (e) { console.warn('Wiki カード削除通信エラー: ' + (e?.message || '')); }
        },

        enterFullscreen(cardId) { this.fullscreenId = cardId; },
        exitFullscreen()        { this.fullscreenId = null; },
    };
}

// ユーザーメニュー (アバター/名前クリック → プロフィール+通知ドロップダウン)
function userMenu() {
    return {
        open: false,
        userMenuItems: [],
        _userMenuTimer: null,

        async userMenuFetch() {
            try {
                const res = await fetch('/notifications', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                this.userMenuItems = await res.json();
            } catch (e) {}
        },
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.userMenuFetch();
                if (!this._userMenuTimer) {
                    this._userMenuTimer = setInterval(() => this.userMenuFetch(), 60000);
                }
            }
        },
        async userMenuMarkRead(id) {
            await fetch(`/notifications/${id}/read`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            this.userMenuFetch();
        },
        async userMenuReadAll() {
            await fetch('/notifications/read-all', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            this.userMenuFetch();
        },
        // 通知の種別ごとに表示を切り替え
        userMenuIcon(n) {
            const k = n.data?.kind;
            if (k === 'rejected')      return 'fas fa-times-circle text-danger';
            if (k === 'chat_mention')  return 'fas fa-at text-success';
            return 'fas fa-envelope-open-text text-primary';
        },
        userMenuTitle(n) {
            const k = n.data?.kind;
            if (k === 'rejected')     return '却下: ' + (n.data?.subject || '(無題)');
            if (k === 'chat_mention') return '@' + (n.data?.mentioner || '誰か') + ' があなたをメンション';
            return '承認依頼: ' + (n.data?.subject || '(無題)');
        },
        userMenuSubtitle(n) {
            const k = n.data?.kind;
            if (k === 'rejected')     return '却下理由: ' + (n.data?.rejection_reason || '(なし)');
            if (k === 'chat_mention') return (n.data?.thread_subject || '') + ' — ' + (n.data?.preview || '');
            return '依頼者: ' + (n.data?.created_by || '不明');
        },
        userMenuLinkFor(n) {
            const k = n.data?.kind;
            if (k === 'rejected')     return '/drafts';
            if (k === 'chat_mention' && n.data?.thread_id) {
                // チャット一覧の該当スレッドを開き、コメント ID があればその行までスクロール
                const cid = n.data?.comment_id;
                return '/chats#thread-' + n.data.thread_id + (cid ? ('&comment=' + cid) : '');
            }
            return '{{ route('approvals.index') }}';
        },
    };
}
</script>
@yield('js')
</body>
</html>
