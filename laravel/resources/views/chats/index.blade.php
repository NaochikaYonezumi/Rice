@extends('layouts.app')
@section('title', 'チャット一覧 - Rice')

@section('css')
<style>
    body.chats-page { overflow: hidden !important; }
    body.chats-page .content-header { display: none !important; }
    body.chats-page .main-footer { display: none !important; }
    body.chats-page .content-wrapper { padding: 0 !important; overflow: hidden !important; }
    body.chats-page .content,
    body.chats-page .content > .container-fluid {
        padding: 0 !important; max-width: none !important; width: 100% !important;
        height: calc(100vh - 3.5rem) !important; min-height: 0 !important; overflow: hidden !important; background:#f9fafb;
    }
    body.chats-page .content > .container-fluid { height: 100% !important; }

    .chats-root { height:100%; width:100%; min-width:0; min-height:0; overflow:hidden; display:flex; }
    [x-cloak] { display:none !important; }

    /* ===== ライト系統 (アプリの既存スタイル) ===== */
    .chat-sidebar      { background:#ffffff; color:#374151; border-right:1px solid #e5e7eb; }
    .chat-sidebar-head { background:#ffffff; border-bottom:1px solid #e5e7eb; }
    .chat-sidebar-section {
        padding: 12px 12px 4px; font-size:10px; font-weight:800;
        color:#9ca3af; text-transform:uppercase; letter-spacing:0.08em;
        display:flex; align-items:center; justify-content:space-between;
    }
    .chat-channel {
        color:#4b5563; padding:6px 10px; border-radius:6px; cursor:pointer;
        display:flex; align-items:center; gap:6px; margin:1px 8px;
        font-size:13px; min-height:32px;
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

    .chat-main         { background:#f9fafb; flex:1; display:flex; flex-direction:column; min-width:0; min-height:0; }
    .chat-header       { background:#ffffff; border-bottom:1px solid #e5e7eb; color:#111827; padding:12px 16px; display:flex; align-items:center; gap:8px; min-height:48px; }
    .chat-header .hash { color:#9ca3af; font-weight:700; font-size:18px; }
    .chat-header h2    { color:#111827; font-weight:700; font-size:16px; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin:0; }
    .chat-header .meta { color:#9ca3af; font-size:11px; }
    /* 入力欄上部の余白を作らないよう padding-bottom を 0 に */
    .chat-messages     { background:#f9fafb; flex:1; overflow-y:auto; padding:16px 0 0; min-height:0; }
    .chat-messages::-webkit-scrollbar { width:6px; }
    .chat-messages::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:4px; }
    .chat-messages::-webkit-scrollbar-thumb:hover { background:#9ca3af; }

    /* メッセージ行 (ライト) */
    .msg-row {
        padding: 8px 16px 8px 64px; position:relative; min-height:36px;
        border-bottom: 1px solid #f1f5f9;
    }
    /* コンパクト (同分内連投) は上ボーダーなしで前のメッセージと結合表示 */
    .msg-row.compact { border-top:none; }
    .msg-row:last-child { border-bottom:none; }
    .msg-row:hover { background:#f3f4f6; }
    .msg-row .avatar {
        position:absolute; left:16px; top:4px; width:36px; height:36px;
        border-radius:50%; display:flex; align-items:center; justify-content:center;
        color:#fff; font-weight:700;
    }
    .msg-row .author { color:#111827; font-weight:600; font-size:14px; }
    .msg-row .ts     { color:#9ca3af; font-size:11px; margin-left:6px; }
    .msg-row .body   { color:#1f2937; font-size:14px; line-height:1.5; white-space:pre-wrap; word-wrap:break-word; }
    .msg-row.compact { padding-top:0; padding-bottom:0; min-height:22px; }
    .msg-row.compact .author, .msg-row.compact .avatar, .msg-row.compact .ts-header { display:none; }
    .msg-row .floating-ts { display:none; position:absolute; left:16px; top:50%; transform:translateY(-50%); width:38px; text-align:center; color:#9ca3af; font-size:10px; }
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
        padding-left:61px;
    }
    .msg-row.is-mentioned-me:hover { background:#ffedd5; }
    .msg-row.is-mentioned-me.compact { padding-left:61px; }

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
        font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:0.05em;
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
        color:#111827; font-size:14px; line-height:1.5; max-height:200px;
        padding:4px 0;
    }
    .chat-input-box textarea::placeholder { color:#9ca3af; font-size:14px; }
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

    /* リサイズハンドル */
    .chat-resize {
        position:absolute; top:0; right:0; width:3px; height:100%;
        cursor:col-resize; z-index:5; background:transparent;
    }
    .chat-resize:hover, .chat-resize:active { background:#3b82f6; }
</style>
@endsection

@section('content')
<script>
    document.body.classList.add('chats-page');
    window.addEventListener('beforeunload', function() { document.body.classList.remove('chats-page'); });
</script>

<div class="chats-root" x-data="chatHubApp()" x-init="init()" x-cloak>

    {{-- ===== 左サイドバー ===== --}}
    <aside class="chat-sidebar flex flex-col shrink-0 relative" :style="'width:' + panelWidth + 'px;'">
        <div class="chat-sidebar-head px-3 py-3">
            <div class="d-flex align-items-center mb-2" style="gap:6px;">
                <h3 class="flex-1 mb-0" style="color:#111827;font-size:13px;font-weight:700;">チャット</h3>
                <button @click="load()" :class="loading ? 'fa-spin' : ''"
                        style="color:#9ca3af;background:none;border:none;font-size:11px;"
                        title="更新"><i class="fas fa-sync-alt"></i></button>
            </div>
            <div class="chat-search">
                <input type="text" x-model="searchQuery" @input.debounce.300ms="load()" placeholder="検索...">
            </div>
            <div class="d-flex" style="gap:4px;margin-top:6px;">
                <button @click="filter = 'all'; load()"
                        :style="filter === 'all' ? 'background:#2563eb;color:#fff;' : 'background:#f3f4f6;color:#4b5563;'"
                        style="border:none;border-radius:4px;padding:3px 8px;font-size:11px;font-weight:600;flex:1;">すべて</button>
                <button @click="filter = 'mentioned'; load()"
                        :style="filter === 'mentioned' ? 'background:#2563eb;color:#fff;' : 'background:#f3f4f6;color:#4b5563;'"
                        style="border:none;border-radius:4px;padding:3px 8px;font-size:11px;font-weight:600;flex:1;">自分宛</button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto py-1" style="min-height:0;">
            {{-- ルーム (スタンドアロン) --}}
            <div class="chat-sidebar-section">
                <span>ルーム</span>
                <button @click="createRoom()" style="background:none;border:none;color:#6b7280;font-size:11px;" title="新規ルーム"><i class="fas fa-plus"></i></button>
            </div>
            <template x-for="r in rooms" :key="'room-' + r.id">
                <div @click="selectRoom(r)"
                     :class="selected?.kind === 'room' && selected?.id === r.id ? 'chat-channel active' : 'chat-channel'">
                    <i class="fas fa-thumbtack" style="font-size:9px;color:#f59e0b;" x-show="r.is_pinned_chat" title="ピン留め中"></i>
                    <span class="hash" x-show="!r.is_pinned_chat">#</span>
                    <span class="name" x-text="r.name"></span>
                    <span class="badge-mention" x-show="r.mention_count > 0" x-text="r.mention_count"></span>
                </div>
            </template>
            <template x-if="!loading && rooms.length === 0">
                <p class="text-center py-2" style="color:#9ca3af;font-size:11px;">ルームなし</p>
            </template>

            {{-- スレッド --}}
            <div class="chat-sidebar-section">
                <span>スレッド</span>
                <span style="color:#9ca3af;font-size:10px;font-weight:600;" x-text="threads.length"></span>
            </div>
            <template x-if="loading">
                <div class="text-center py-3" style="color:#3b82f6;"><i class="fas fa-circle-notch fa-spin"></i></div>
            </template>
            <template x-for="t in threads" :key="'thread-' + t.id">
                <div @click="selectThread(t)"
                     :class="selected?.kind === 'thread' && selected?.id === t.id ? 'chat-channel active' : 'chat-channel'">
                    <i class="fas fa-thumbtack" style="font-size:9px;color:#f59e0b;" x-show="t.is_pinned_chat" title="ピン留め中"></i>
                    <i class="fas fa-envelope" style="font-size:10px;opacity:0.7;" x-show="!t.is_pinned_chat"></i>
                    <span class="name" x-text="t.subject"></span>
                    {{-- 未読 + @自分宛: 黄色アイコン (mention_count > 0 のみ) --}}
                    <span class="badge-mention" x-show="t.mention_count > 0" x-text="t.mention_count" title="未読メンション"></span>
                    {{-- 未読 (メンションなし): グレーバッジ --}}
                    <span class="badge-count" x-show="t.mention_count === 0 && t.unread_count > 0" x-text="t.unread_count" title="未読"></span>
                </div>
            </template>
            <template x-if="!loading && threads.length === 0">
                <p class="text-center py-2" style="color:#9ca3af;font-size:11px;">スレッドなし</p>
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
                    <span class="hash" x-show="selected.kind === 'room'">#</span>
                    <i class="fas fa-envelope" x-show="selected.kind === 'thread'" style="color:#9ca3af;"></i>
                    <h2 x-text="selected.kind === 'room' ? selected.name : selected.subject"></h2>
                    {{-- ピン留めトグル --}}
                    <button @click="togglePinSelected()"
                            style="background:none;border:none;padding:4px 8px;border-radius:4px;font-size:13px;"
                            :style="selected.is_pinned_chat ? 'color:#f59e0b;' : 'color:#9ca3af;'"
                            onmouseover="this.style.backgroundColor='#f3f4f6'"
                            onmouseout="this.style.backgroundColor='transparent'"
                            :title="selected.is_pinned_chat ? 'ピン留めを解除' : 'ピン留め'">
                        <i class="fas fa-thumbtack"></i>
                    </button>
                    {{-- スレッド: 元メールサイドパネルトグル --}}
                    <button x-show="selected.kind === 'thread'" @click="origEmailOpen = !origEmailOpen; if(origEmailOpen) loadOrigEmail()"
                            style="background:#2563eb;color:#fff;border:none;font-size:11px;padding:4px 10px;border-radius:6px;font-weight:600;"
                            title="元メールをサイドパネルで表示">
                        <i class="fas fa-envelope-open-text"></i> 元メール
                    </button>
                    <button x-show="selected.kind === 'room'" @click="deleteRoom()" title="ルーム削除"
                            style="color:#9ca3af;background:none;border:none;padding:4px 8px;border-radius:4px;font-size:11px;"
                            onmouseover="this.style.color='#dc2626';this.style.backgroundColor='#fef2f2'"
                            onmouseout="this.style.color='#9ca3af';this.style.backgroundColor='transparent'">
                        <i class="fas fa-trash"></i>
                    </button>
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
                        <div>
                            <template x-if="shouldShowDate(idx)">
                                <div class="date-divider"><span x-text="dateLabel(m.created_at)"></span></div>
                            </template>
                            <div :class="(isCompact(idx) ? 'msg-row compact' : 'msg-row') + (isMentionedToMe(m.content) ? ' is-mentioned-me' : '')" :id="'comment-' + m.id">
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
                                <div class="body" x-html="renderMentions(m.content)" x-show="m.content"></div>
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
                        </div>
                    </template>
                </div>

                {{-- 入力欄 --}}
                <div class="chat-input-wrap relative">
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

    {{-- 元メール プレビューパネル (右側スライドイン) — 左端をドラッグでリサイズ可能 --}}
    <aside x-show="origEmailOpen" x-cloak
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="translate-x-8 opacity-0"
           x-transition:enter-end="translate-x-0 opacity-100"
           :style="'width:' + origEmailPanelWidth + 'px;'"
           style="border-left:1px solid #e5e7eb;background:#fafafa;display:flex;flex-direction:column;position:relative;flex-shrink:0;">
        {{-- リサイズハンドル (左端) --}}
        <div @mousedown.prevent="startResizeOrigEmail($event)"
             title="ドラッグして幅を変更"
             style="position:absolute;top:0;left:-3px;width:6px;height:100%;cursor:col-resize;z-index:60;"
             onmouseover="this.style.background='rgba(59,130,246,0.25)'"
             onmouseout="this.style.background='transparent'"></div>
        <div style="background:#ffffff;border-bottom:1px solid #e5e7eb;padding:10px 14px;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-envelope-open-text" style="color:#3b82f6;"></i>
            <strong style="flex:1;font-size:13px;">元メール</strong>
            <a :href="`/?thread=${selected?.id}`"
               class="btn btn-sm btn-primary"
               style="font-size:11px;padding:4px 10px;"
               title="メール画面で開く">
                <i class="fas fa-external-link-alt"></i> メール画面へ
            </a>
            <button @click="origEmailOpen = false" style="background:none;border:none;color:#6b7280;padding:4px;" title="閉じる"><i class="fas fa-times"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto p-3" style="font-size:12px;color:#374151;">
            <template x-if="origEmailLoading">
                <p class="text-center py-4" style="color:#9ca3af;"><i class="fas fa-circle-notch fa-spin mr-1"></i>読み込み中...</p>
            </template>
            <template x-if="!origEmailLoading && origEmail">
                <div>
                    <p style="font-weight:700;color:#111827;font-size:13px;margin:0 0 6px;" x-text="origEmail.subject || '(件名なし)'"></p>
                    <p class="text-muted" style="font-size:11px;margin:2px 0;">From: <span x-text="origEmail.from_label || origEmail.from_address || ''"></span></p>
                    <p class="text-muted" style="font-size:11px;margin:2px 0;" x-show="origEmail.to_address">To: <span x-text="origEmail.to_address"></span></p>
                    <p class="text-muted" style="font-size:11px;margin:2px 0 8px;" x-show="origEmail.received_at">日時: <span x-text="origEmail.received_at"></span></p>
                    <pre style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:10px;font-family:inherit;font-size:12px;line-height:1.5;white-space:pre-wrap;word-break:break-word;color:#1f2937;max-height:60vh;overflow-y:auto;" x-text="origEmail.plain_body || '(本文なし)'"></pre>
                    <a :href="`/?thread=${selected?.id}`"
                       style="display:inline-block;margin-top:10px;color:#2563eb;text-decoration:none;font-size:12px;font-weight:600;">
                        <i class="fas fa-arrow-right"></i> このメールを開く
                    </a>
                </div>
            </template>
            <template x-if="!origEmailLoading && !origEmail">
                <p class="text-center py-4" style="color:#9ca3af;">元メールを取得できませんでした</p>
            </template>
        </div>
    </aside>

    {{-- リアクションピッカー --}}
    <div x-show="reactionPickerOpen" x-cloak
         @click.outside="reactionPickerOpen = false"
         :style="'position:fixed;left:' + reactionPickerX + 'px;top:' + reactionPickerY + 'px;z-index:200;'"
         style="background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;padding:8px;width:280px;box-shadow:0 10px 25px rgba(0,0,0,0.15);">
        <div style="font-size:10px;color:#6b7280;font-weight:700;text-transform:uppercase;padding:2px 4px 6px;">リアクション</div>
        <div style="display:grid;grid-template-columns:repeat(8,1fr);gap:2px;max-height:240px;overflow-y:auto;">
            <template x-for="e in reactionEmojis" :key="e">
                <button type="button" @click="pickReaction(e)"
                        style="background:none;border:none;padding:6px;cursor:pointer;font-size:18px;border-radius:6px;line-height:1;"
                        onmouseover="this.style.backgroundColor='#f3f4f6'"
                        onmouseout="this.style.backgroundColor='transparent'"
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
        // 選択 (kind = 'thread' | 'room')
        selected: null,
        // メッセージ
        comments: [], chatLoading: false, sending: false, input: '',
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
        // 元メール プレビュー
        origEmailOpen: false,
        origEmail: null,
        origEmailLoading: false,
        origEmailPanelWidth: parseInt(localStorage.getItem('chatOrigEmailPanelWidth') || '420', 10),
        myId: {{ auth()->id() ?? 'null' }}, myName: @js(auth()->user()->name ?? ''),
        csrfToken: document.querySelector('meta[name="csrf-token"]').content,
        // 左パネル幅
        panelWidth: parseInt(localStorage.getItem('chatHubPanelWidth') || '280', 10),

        async init() {
            await Promise.all([this.load(), this.loadUsers()]);
            // 通知リンク (`/chats#thread-123&comment=456` または `?thread=123&comment=456` ) から自動選択
            await this.applyHashSelection();
            // hash 変化 (通知の再クリック) にも対応
            window.addEventListener('hashchange', () => this.applyHashSelection());
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
                const t = this.threads.find(x => x.id === threadId);
                if (t) { await this.selectThread(t); }
            } else if (roomId) {
                const r = this.rooms.find(x => x.id === roomId);
                if (r) { await this.selectRoom(r); }
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
                const params = new URLSearchParams();
                if (this.searchQuery) params.set('q', this.searchQuery);
                if (this.filter === 'mentioned') params.set('mentioned', '1');
                const r = await fetch('/chats/threads?' + params.toString(), { headers:{Accept:'application/json'} });
                if (r.ok) {
                    const d = await r.json();
                    this.threads = (d.threads || []).map(t => ({ kind:'thread', ...t }));
                    this.rooms   = (d.rooms   || []).map(r => ({ kind:'room',   ...r }));
                }
            } catch (_) {}
            this.loading = false;
        },

        async selectRoom(r) {
            this.selected = r;
            await this.loadComments();
        },
        async selectThread(t) {
            this.selected = t;
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
        replyTo(m) {
            this.replyingTo = m;
            // 入力欄に @author を先頭挿入 (まだ無ければ)
            const mentionPrefix = '@' + (m.author || '') + ' ';
            if (!this.input.startsWith(mentionPrefix)) {
                this.input = mentionPrefix + this.input;
            }
            this.$nextTick(() => { try { this.$refs.ta.focus(); } catch (_) {} });
        },
        cancelReply() { this.replyingTo = null; },
        quoteMessage(m) {
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

        async createRoom() {
            const name = prompt('ルーム名を入力してください');
            if (!name?.trim()) return;
            const r = await fetch('/api/chat-rooms', {
                method:'POST',
                headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfToken},
                body: JSON.stringify({ name }),
            });
            if (!r.ok) { alert('作成失敗'); return; }
            await this.load();
            const data = await r.json();
            const room = this.rooms.find(x => x.id === data.room.id);
            if (room) await this.selectRoom(room);
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
            // @name パターンを <span class="mention-tag">@name</span> に
            return esc(text).replace(/@([^\s@.,!?。、]+)/g, '<span class="mention-tag">@$1</span>');
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

        // ===== 元メール プレビュー =====
        async loadOrigEmail() {
            if (!this.selected || this.selected.kind !== 'thread') return;
            this.origEmailLoading = true;
            this.origEmail = null;
            try {
                const r = await fetch(`/threads/${this.selected.id}`, { headers:{Accept:'application/json'} });
                if (r.ok) {
                    const d = await r.json();
                    const emails = d.emails || d.thread?.emails || [];
                    // 元メール = スレッドの最初のメール (received_at 昇順の先頭)
                    if (emails.length) {
                        this.origEmail = [...emails].sort((a,b) => (a.received_at||'').localeCompare(b.received_at||''))[0];
                    }
                }
            } catch (_) {}
            this.origEmailLoading = false;
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
            this.reactionPickerOpen = false;
            await this.toggleReaction(target, emoji);
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

        // ===== 元メールパネルのリサイズ =====
        startResizeOrigEmail(e) {
            const startX = e.clientX, startW = this.origEmailPanelWidth;
            const prevUS = document.body.style.userSelect;
            document.body.style.userSelect = 'none'; document.body.style.cursor = 'col-resize';
            const onMove = (me) => {
                // 左へドラッグ = パネル拡大 / 右へドラッグ = パネル縮小
                const delta = startX - me.clientX;
                this.origEmailPanelWidth = Math.max(280, Math.min(900, startW + delta));
            };
            const onUp = () => {
                localStorage.setItem('chatOrigEmailPanelWidth', String(this.origEmailPanelWidth));
                document.body.style.userSelect = prevUS; document.body.style.cursor = '';
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
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
