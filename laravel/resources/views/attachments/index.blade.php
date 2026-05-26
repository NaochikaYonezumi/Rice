@extends('layouts.app')
@section('title', '添付ファイル')

@section('css')
<style>
    body.attachments-page { overflow: hidden !important; }
    body.attachments-page .content-header { display: none !important; }
    body.attachments-page .main-footer { display: none !important; }
    body.attachments-page .content-wrapper { padding: 0 !important; overflow: hidden !important; }
    body.attachments-page .content,
    body.attachments-page .content > .container-fluid {
        padding: 0 !important;
        max-width: none !important;
        width: 100% !important;
        height: calc(100vh - 3.5rem) !important;
        min-height: 0 !important;
        overflow: hidden !important;
        background: #f9fafb;
    }
    body.attachments-page .content > .container-fluid { height: 100% !important; }

    .att-root {
        height: 100% !important;
        width: 100% !important;
        min-width: 0 !important;
        min-height: 0 !important;
        overflow: hidden !important;
        display: flex !important;
    }
    .att-root .clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        word-break: break-word;
    }
    .att-root input[type="date"] { color: #111827; }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
    [x-cloak] { display: none !important; }

    /* ===== 左サイドバー: ルーム一覧 (メール/チャット画面と同じデザイン) ===== */
    .att-rooms-sidebar {
        background:#ffffff; color:#374151; border-right:1px solid #e5e7eb;
        transition: width 0.2s ease;
        display:flex; flex-direction:column; position:relative; flex-shrink:0;
    }
    .att-rooms-sidebar.is-collapsed { overflow:hidden; }
    .att-rooms-sidebar.is-collapsed > *:not(.att-rooms-collapse-toggle) { display:none !important; }
    .att-rooms-head { padding:6px 34px 6px 10px; border-bottom:1px solid #e5e7eb; }
    .att-rooms-head h3 { color:#111827; font-size:12px; font-weight:700; margin:0; }
    .att-rooms-section { padding:8px 10px 2px; font-size:10px; font-weight:800; color:#9ca3af; text-transform:uppercase; letter-spacing:0.08em; }
    .att-room-item {
        color:#4b5563; padding:4px 8px; border-radius:6px; cursor:pointer;
        display:flex; align-items:center; gap:6px; margin:1px 6px;
        font-size:12px; min-height:26px; position:relative;
    }
    .att-room-item:hover { background:#f3f4f6; color:#111827; }
    .att-room-item.active { background:#eff6ff; color:#1d4ed8; font-weight:700; border-left:3px solid #2563eb; padding-left:5px; }
    .att-room-item .hash { color:#9ca3af; font-weight:700; }
    .att-room-item.active .hash { color:#1d4ed8; }
    .att-room-item .name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .att-room-item .att-room-del-btn {
        margin-left:auto; background:transparent; border:none; color:#9ca3af;
        padding:2px 4px; border-radius:4px; cursor:pointer; opacity:0;
        transition:opacity .15s, background-color .15s, color .15s;
        display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
    }
    .att-room-item:hover .att-room-del-btn { opacity:1; }
    .att-room-item .att-room-del-btn:hover { background:#fee2e2; color:#dc2626; }
    .att-rooms-collapse-toggle {
        position:absolute; top:6px; right:6px; z-index:20;
        width:22px; height:22px;
        background:#ffffff; border:1px solid #e5e7eb; border-radius:50%;
        color:#6b7280; font-size:10px; cursor:pointer;
        display:inline-flex; align-items:center; justify-content:center;
        box-shadow:0 1px 3px rgba(0,0,0,0.08); padding:0;
        transition: background .15s, color .15s;
    }
    .att-rooms-collapse-toggle:hover { background:#f3f4f6; color:#111827; }
    .att-rooms-resize {
        position:absolute; top:0; right:0; width:4px; height:100%;
        cursor:col-resize; z-index:5; background:transparent; transition:background-color .15s;
    }
    .att-thread-pin-btn {
        background:transparent; border:none; padding:2px 4px;
        border-radius:4px; cursor:pointer;
        display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
        transition:background-color .15s, color .15s, transform .1s;
    }
    .att-thread-pin-btn:hover { background:#fef3c7; transform:scale(1.15); }
    /* ルームに追加リンクボタン (ホバー時表示) */
    .att-room-item .att-room-link-btn {
        background:transparent; border:none; color:#9ca3af; padding:2px 4px;
        border-radius:4px; cursor:pointer; opacity:0;
        transition:opacity 0.15s, background-color 0.15s, color 0.15s;
        display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
    }
    .att-room-item:hover .att-room-link-btn { opacity:1; }
    .att-room-item .att-room-link-btn:hover { background:#eff6ff; color:#2563eb; }
    /* バンドル先スレッドの「メール件数」バッジ (メール画面と同じ色で統一: 受信=青/保留=琥珀/承認待ち=橙) */
    .att-room-item .badge-email-unread {
        background:#3b82f6; color:#fff; font-size:10px; font-weight:800;
        border-radius:8px; min-width:18px; height:18px; padding:0 5px;
        display:inline-flex; align-items:center; justify-content:center;
        gap:3px; line-height:1; flex-shrink:0;
    }
    .att-room-item .badge-email-unread i { font-size:8px; }
    .att-room-item.active .badge-email-unread { background:#1d4ed8; }
    .att-room-item .badge-email-hold {
        background:#f59e0b; color:#fff; font-size:10px; font-weight:800;
        border-radius:8px; min-width:18px; height:18px; padding:0 5px;
        display:inline-flex; align-items:center; justify-content:center;
        gap:3px; line-height:1; flex-shrink:0;
    }
    .att-room-item .badge-email-hold i { font-size:8px; }
    .att-room-item.active .badge-email-hold { background:#d97706; }
    .att-room-item .badge-email-pending {
        background:#f97316; color:#fff; font-size:10px; font-weight:800;
        border-radius:8px; min-width:18px; height:18px; padding:0 5px;
        display:inline-flex; align-items:center; justify-content:center;
        gap:3px; line-height:1; flex-shrink:0;
    }
    .att-room-item .badge-email-pending i { font-size:8px; }
    .att-room-item.active .badge-email-pending { background:#c2410c; }
    /* 自分が非表示にしている行 */
    .att-room-item.is-hidden { opacity:0.55; }
    .att-room-item.is-hidden .name { text-decoration:line-through; color:#6b7280; }
    .att-rooms-resize:hover, .att-rooms-resize.is-resizing { background:#3b82f6; }
    body.att-rooms-resizing { cursor:col-resize !important; user-select:none !important; }

    /* 添付ファイル行のアクションボタン (ダウンロード / 削除) */
    .att-action-btn {
        display:inline-flex; align-items:center; justify-content:center;
        width:36px; height:36px; border-radius:8px;
        border:1px solid transparent;
        transition: background-color .15s, color .15s, border-color .15s;
        cursor: pointer;
    }
    .att-action-download { background:#eff6ff; color:#2563eb; border-color:#dbeafe; }
    .att-action-download:hover { background:#2563eb; color:#ffffff; border-color:#2563eb; }
    /* 削除はライト/ダークとも一目で「赤」と分かる色を使う。
       これまで f3f4f6 + 6b7280 はダーク行ホバー時に同化して消えていた */
    .att-action-delete { background:#fee2e2; color:#b91c1c; border-color:#fecaca; }
    .att-action-delete:hover { background:#dc2626; color:#ffffff; border-color:#dc2626; }
    html.theme-dark .att-action-download {
        background: rgba(88,101,242,0.15) !important;
        color: #c7d0ff !important;
        border-color: rgba(88,101,242,0.4) !important;
    }
    html.theme-dark .att-action-download:hover {
        background: #5865f2 !important;
        color: #ffffff !important;
        border-color: #5865f2 !important;
    }
    html.theme-dark .att-action-delete {
        background: rgba(237,66,69,0.18) !important;
        color: #ff9a9c !important;
        border-color: rgba(237,66,69,0.45) !important;
    }
    html.theme-dark .att-action-delete:hover {
        background: #ed4245 !important;
        color: #ffffff !important;
        border-color: #ed4245 !important;
    }
</style>
@endsection

@section('content')
<script>
    document.body.classList.add('attachments-page');
    window.addEventListener('beforeunload', function() {
        document.body.classList.remove('attachments-page');
    });
</script>

{{--
  添付ファイル一覧 グローバルショートカット:
    J / K          : 次 / 前の添付ファイル (selectedAttachmentId をたどる)
    Enter          : 選択中の添付プレビューを開く
    Esc            : プレビューを閉じる / 選択解除
    /              : 検索ボックスにフォーカス
    ?              : ヘルプモーダルを開く
  入力欄フォーカス時は無効化.
--}}
<div class="att-root flex bg-gray-50" x-data="attachmentApp()" x-cloak
     @keydown.window="onGlobalKey($event)">

    {{-- ルーム作成モーダル — 中央配置 --}}
    <div x-show="attCreateRoomOpen" x-cloak>
        <div @click="attCreateRoomOpen = false" class="rice-modal-backdrop"></div>
        <div class="rice-modal" style="width:480px;max-width:94vw;">
            <div class="rice-modal-head">
                <div class="rice-modal-head-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-plus"></i></div>
                <div class="rice-modal-head-text">
                    <h3>新しいルームを作成</h3>
                    <p>関係者とまとめて共有する場や、自分用のメモ用ルームを作れます。</p>
                </div>
                <button @click="attCreateRoomOpen = false" class="rice-modal-close" title="閉じる"><i class="fas fa-times"></i></button>
            </div>
            <div class="rice-modal-body">
                <div class="rice-field">
                    <label>ルーム名</label>
                    <input type="text" x-model="attNewRoomName" @keydown.enter="submitAttCreateRoom()"
                           placeholder="例: 案件A 進行管理"
                           class="rice-input" autofocus>
                    {{-- 重複防止: 入力名と部分一致する既存ルームを最大 8 件提示。
                         入力したキーワードに部分一致する既存ルームをクリックでそのルームへ移動できる。 --}}
                    <template x-if="attSimilarRoomsForNewName.length > 0">
                        <div style="margin-top:8px;border:1px solid #fde68a;background:#fffbeb;border-radius:6px;padding:8px 10px;">
                            <p style="margin:0 0 4px;font-size:10px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.04em;">
                                <i class="fas fa-info-circle" style="margin-right:4px;"></i>似た名前のルームがあります (クリックで開く)
                            </p>
                            <div style="display:flex;flex-direction:column;gap:2px;max-height:140px;overflow-y:auto;">
                                <template x-for="r in attSimilarRoomsForNewName" :key="'att-sim-' + r.id">
                                    <button type="button" @click="selectExistingAttRoomFromCreate(r)"
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
                        <button type="button" @click="attNewRoomIsPrivate = false"
                                :class="!attNewRoomIsPrivate ? 'rice-radio-card is-selected' : 'rice-radio-card'">
                            <div class="rice-radio-card-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-globe"></i></div>
                            <div class="rice-radio-card-body">
                                <strong>全員共有</strong>
                                <span>他のメンバーにも表示されます</span>
                            </div>
                            <i class="fas fa-check-circle rice-radio-check" x-show="!attNewRoomIsPrivate"></i>
                        </button>
                        <button type="button" @click="attNewRoomIsPrivate = true"
                                :class="attNewRoomIsPrivate ? 'rice-radio-card is-selected' : 'rice-radio-card'">
                            <div class="rice-radio-card-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-lock"></i></div>
                            <div class="rice-radio-card-body">
                                <strong>個人用</strong>
                                <span>あなただけに表示されます</span>
                            </div>
                            <i class="fas fa-check-circle rice-radio-check" x-show="attNewRoomIsPrivate"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="rice-modal-foot">
                <button @click="attCreateRoomOpen = false" class="rice-btn-secondary">キャンセル</button>
                <button @click="submitAttCreateRoom()" :disabled="!attNewRoomName?.trim() || attCreatingRoom"
                        class="rice-btn-primary"
                        :style="(!attNewRoomName?.trim() || attCreatingRoom) ? 'opacity:0.5;cursor:not-allowed;' : ''">
                    <i class="fas" :class="attCreatingRoom ? 'fa-spinner fa-spin' : 'fa-plus'"></i>
                    ルームを作成
                </button>
            </div>
        </div>
    </div>

    {{-- ルーム編集モーダル — 名前と公開範囲を変更 --}}
    <div x-show="attEditRoomOpen" x-cloak>
        <div @click="attEditRoomOpen = false" class="rice-modal-backdrop"></div>
        <div class="rice-modal" style="width:480px;max-width:94vw;">
            <div class="rice-modal-head">
                <div class="rice-modal-head-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-pen"></i></div>
                <div class="rice-modal-head-text">
                    <h3>ルームを編集</h3>
                    <p>名前と公開範囲を変更できます。</p>
                </div>
                <button @click="attEditRoomOpen = false" class="rice-modal-close" title="閉じる"><i class="fas fa-times"></i></button>
            </div>
            <div class="rice-modal-body">
                <div class="rice-field">
                    <label>ルーム名</label>
                    <input type="text" x-model="attEditRoomName" @keydown.enter="submitAttEditRoom()"
                           placeholder="ルーム名" class="rice-input" autofocus>
                </div>
                <template x-if="attEditRoomIsCreator">
                    <div class="rice-field">
                        <label>公開範囲</label>
                        <div class="rice-radio-grid">
                            <button type="button" @click="attEditRoomIsPrivate = false"
                                    :class="!attEditRoomIsPrivate ? 'rice-radio-card is-selected' : 'rice-radio-card'">
                                <div class="rice-radio-card-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-globe"></i></div>
                                <div class="rice-radio-card-body"><strong>全員共有</strong><span>他のメンバーにも表示</span></div>
                                <i class="fas fa-check-circle rice-radio-check" x-show="!attEditRoomIsPrivate"></i>
                            </button>
                            <button type="button" @click="attEditRoomIsPrivate = true"
                                    :class="attEditRoomIsPrivate ? 'rice-radio-card is-selected' : 'rice-radio-card'">
                                <div class="rice-radio-card-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-lock"></i></div>
                                <div class="rice-radio-card-body"><strong>個人用</strong><span>あなただけに表示</span></div>
                                <i class="fas fa-check-circle rice-radio-check" x-show="attEditRoomIsPrivate"></i>
                            </button>
                        </div>
                    </div>
                </template>
                <template x-if="!attEditRoomIsCreator">
                    <p style="font-size:11px;color:#6b7280;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:6px;padding:8px 10px;">
                        <i class="fas fa-info-circle" style="margin-right:4px;color:#9ca3af;"></i>
                        公開範囲はルーム作成者のみ変更できます。
                    </p>
                </template>
            </div>
            <div class="rice-modal-foot">
                <button @click="attEditRoomOpen = false" class="rice-btn-secondary">キャンセル</button>
                <button @click="submitAttEditRoom()"
                        :disabled="!attEditRoomName?.trim() || attEditingRoom"
                        class="rice-btn-primary"
                        :style="(!attEditRoomName?.trim() || attEditingRoom) ? 'opacity:0.5;cursor:not-allowed;' : ''">
                    <i class="fas" :class="attEditingRoom ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                    保存
                </button>
            </div>
        </div>
    </div>

    {{-- スレッドをルームに追加モーダル — 中央配置 --}}
    <div x-show="attAddToRoomOpen" x-cloak>
        <div @click="attAddToRoomOpen = false" class="rice-modal-backdrop"></div>
        <div class="rice-modal" style="width:480px;max-width:94vw;max-height:80vh;">
            <div class="rice-modal-head">
                <div class="rice-modal-head-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-link"></i></div>
                <div class="rice-modal-head-text">
                    <h3>スレッドをルームに追加</h3>
                    <p>追加先のルームを選択してください。</p>
                </div>
                <button @click="attAddToRoomOpen = false" class="rice-modal-close" title="閉じる"><i class="fas fa-times"></i></button>
            </div>
            <div class="rice-modal-body" style="overflow-y:auto;">
                <div class="rice-room-list-section">
                    <p class="rice-room-list-head"><i class="fas fa-globe rice-room-list-head-icon"></i>共有ルーム</p>
                    <template x-for="r in attRoomsShared" :key="'aattr-sh-' + r.id">
                        <button type="button" @click="confirmAttAddToRoom(r)" class="rice-room-list-item">
                            <span class="rice-room-list-hash">#</span>
                            <span x-text="r.name"></span>
                        </button>
                    </template>
                    <template x-if="attRoomsShared.length === 0">
                        <p class="rice-room-list-empty">共有ルームはありません</p>
                    </template>
                </div>
                <div class="rice-room-list-section">
                    <p class="rice-room-list-head"><i class="fas fa-lock rice-room-list-head-icon" style="color:#a78bfa;"></i>個人ルーム</p>
                    <template x-for="r in attRoomsPersonal" :key="'aattr-pr-' + r.id">
                        <button type="button" @click="confirmAttAddToRoom(r)" class="rice-room-list-item">
                            <i class="fas fa-lock rice-room-list-lock"></i>
                            <span x-text="r.name"></span>
                        </button>
                    </template>
                    <template x-if="attRoomsPersonal.length === 0">
                        <p class="rice-room-list-empty">個人ルームはありません</p>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- 左サイドバー: ルーム一覧 --}}
    <aside class="att-rooms-sidebar"
           :class="{ 'is-collapsed': attRoomsCollapsed }"
           :style="'width:' + (attRoomsCollapsed ? 32 : attRoomsWidth) + 'px;'">
        <button @click="toggleAttRoomsSidebar()" class="att-rooms-collapse-toggle"
                :title="attRoomsCollapsed ? '展開' : '折りたたむ'">
            <i class="fas" :class="attRoomsCollapsed ? 'fa-angle-double-right' : 'fa-angle-double-left'"></i>
        </button>
        <div class="att-rooms-head"><h3>ルーム</h3></div>
        {{-- ルーム/スレッド 横断検索 --}}
        <div style="padding:6px 8px;border-bottom:1px solid #f3f4f6;">
            <div style="position:relative;">
                <i class="fas fa-search" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:10px;"></i>
                <input type="text" x-model="attSidebarSearchQuery"
                       placeholder="ルーム/スレッド検索"
                       style="width:100%;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:4px 8px 4px 24px;font-size:11px;outline:none;">
                <button x-show="attSidebarSearchQuery" @click="attSidebarSearchQuery = ''"
                        style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ca3af;font-size:10px;padding:2px;"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto py-1 custom-scrollbar" style="min-height:0;">
            <div @click="setAttRoomFilter('all')"
                 :class="(attRoomFilterId === 'all' && attThreadFilterId === 'all') ? 'att-room-item active' : 'att-room-item'"
                 title="ルーム/スレッドフィルターを外して全添付を表示">
                <i class="fas fa-inbox" style="font-size:11px;color:#3b82f6;"></i>
                <span class="name" style="font-weight:700;">すべて</span>
            </div>

            {{-- ルーム未設定: どのルームにも紐付いていないスレッドの添付ファイルだけ --}}
            <div @click="setAttRoomFilter('none')"
                 :class="attRoomFilterId === 'none' ? 'att-room-item active' : 'att-room-item'"
                 title="どのルームにも未登録のスレッドの添付ファイルだけ表示">
                <i class="fas fa-folder-minus" style="font-size:11px;color:#f59e0b;"></i>
                <span class="name" style="font-weight:700;">ルーム未設定</span>
            </div>

            {{-- 共有ルーム (折りたたみ可) --}}
            <div class="att-rooms-section" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;"
                 @click="toggleAttSharedRoomsCollapsed()">
                <span><i class="fas" :class="attSharedRoomsCollapsed ? 'fa-chevron-right' : 'fa-chevron-down'" style="font-size:8px;margin-right:4px;"></i>共有ルーム</span>
                <button @click.stop="openAttCreateRoom(false)" title="新規共有ルームを作成"
                        style="background:none;border:none;color:#6b7280;font-size:11px;padding:0;cursor:pointer;">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <template x-for="r in (attSharedRoomsCollapsed ? [] : filteredAttSharedRooms)" :key="'att-sh-' + r.id">
                <div @click="setAttRoomFilter(String(r.id))"
                     :class="(isAttRoomInSelection(r) ? 'att-room-item active' : 'att-room-item') + (isAttRoomHidden(r) ? ' is-hidden' : '')"
                     :style="'position:relative;padding-left:' + (8 + ((r._depth||0) * 12)) + 'px;'">
                    <template x-if="r._hasChildren">
                        <button type="button" @click.stop="toggleAttRoomBranch(r.id)"
                                style="background:none;border:none;color:#6b7280;font-size:8px;padding:0 4px 0 0;cursor:pointer;"
                                :title="attRoomBranchCollapsed[r.id] ? '子ルームを表示' : '子ルームを折りたたむ'">
                            <i class="fas" :class="attRoomBranchCollapsed[r.id] ? 'fa-chevron-right' : 'fa-chevron-down'"></i>
                        </button>
                    </template>
                    <template x-if="!r._hasChildren">
                        <span class="hash">#</span>
                    </template>
                    <span class="name" x-text="r.name"></span>
                    {{-- バンドル先スレッドの件数バッジ (status 別色分け). メール画面と同じデザイン --}}
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
                    <span x-show="isAttRoomHidden(r)"
                          style="display:inline-flex;align-items:center;gap:3px;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;font-size:9px;font-weight:700;padding:0 5px;border-radius:9999px;line-height:1.4;flex-shrink:0;"
                          title="自分で非表示にしているルームです">
                        <i class="fas fa-eye-slash" style="font-size:8px;"></i>非表示中
                    </span>
                    <button x-show="isAttRoomHidden(r)" @click.stop="unhideAttRoom(r.id)" class="att-room-link-btn"
                            style="opacity:1;color:#059669;"
                            title="再表示">
                        <i class="fas fa-undo" style="font-size:9px;"></i>
                    </button>
                    <button x-show="!isAttRoomHidden(r)" @click.stop="toggleAttHideRoom(r.id)" class="att-room-link-btn"
                            title="このルームを非表示にする">
                        <i class="fas fa-eye-slash" style="font-size:9px;"></i>
                    </button>
                    {{-- ルーム編集 (個人ルームは作成者のみ表示) --}}
                    <button x-show="canEditAttRoom(r)" @click.stop="openAttEditRoom(r)" class="att-room-link-btn"
                            title="このルームを編集">
                        <i class="fas fa-pen" style="font-size:9px;"></i>
                    </button>
                    <button @click.stop="deleteAttRoom(r, $event)" class="att-room-del-btn" title="このルームを削除">
                        <i class="fas fa-times" style="font-size:9px;"></i>
                    </button>
                </div>
            </template>
            <template x-if="!attSharedRoomsCollapsed && filteredAttSharedRooms.length === 0">
                <p class="text-center" style="color:#9ca3af;font-size:11px;padding:6px;" x-text="attSidebarSearchQuery ? '該当なし' : 'なし'"></p>
            </template>

            {{-- 個人ルーム (折りたたみ可) --}}
            <div class="att-rooms-section" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;"
                 @click="toggleAttPersonalRoomsCollapsed()">
                <span><i class="fas" :class="attPersonalRoomsCollapsed ? 'fa-chevron-right' : 'fa-chevron-down'" style="font-size:8px;margin-right:4px;"></i>個人ルーム</span>
                <button @click.stop="openAttCreateRoom(true)" title="新規個人ルームを作成"
                        style="background:none;border:none;color:#a78bfa;font-size:11px;padding:0;cursor:pointer;">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <template x-for="r in (attPersonalRoomsCollapsed ? [] : filteredAttPersonalRooms)" :key="'att-pr-' + r.id">
                <div @click="setAttRoomFilter(String(r.id))"
                     :class="(isAttRoomInSelection(r) ? 'att-room-item active' : 'att-room-item') + (isAttRoomHidden(r) ? ' is-hidden' : '')"
                     :style="'position:relative;padding-left:' + (8 + ((r._depth||0) * 12)) + 'px;'">
                    <template x-if="r._hasChildren">
                        <button type="button" @click.stop="toggleAttRoomBranch(r.id)"
                                style="background:none;border:none;color:#6b7280;font-size:8px;padding:0 4px 0 0;cursor:pointer;"
                                :title="attRoomBranchCollapsed[r.id] ? '子ルームを表示' : '子ルームを折りたたむ'">
                            <i class="fas" :class="attRoomBranchCollapsed[r.id] ? 'fa-chevron-right' : 'fa-chevron-down'"></i>
                        </button>
                    </template>
                    <template x-if="!r._hasChildren">
                        <i class="fas fa-lock" style="font-size:9px;color:#a78bfa;"></i>
                    </template>
                    <span class="name" x-text="r.name"></span>
                    {{-- バンドル先スレッドの件数バッジ (status 別色分け). 個人ルームも共通仕様で. --}}
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
                    <span x-show="isAttRoomHidden(r)"
                          style="display:inline-flex;align-items:center;gap:3px;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;font-size:9px;font-weight:700;padding:0 5px;border-radius:9999px;line-height:1.4;flex-shrink:0;"
                          title="自分で非表示にしているルームです">
                        <i class="fas fa-eye-slash" style="font-size:8px;"></i>非表示中
                    </span>
                    <button x-show="isAttRoomHidden(r)" @click.stop="unhideAttRoom(r.id)" class="att-room-link-btn"
                            style="opacity:1;color:#059669;"
                            title="再表示">
                        <i class="fas fa-undo" style="font-size:9px;"></i>
                    </button>
                    <button x-show="!isAttRoomHidden(r)" @click.stop="toggleAttHideRoom(r.id)" class="att-room-link-btn"
                            title="このルームを非表示にする">
                        <i class="fas fa-eye-slash" style="font-size:9px;"></i>
                    </button>
                    {{-- ルーム編集 (個人ルームは作成者のみ表示) --}}
                    <button x-show="canEditAttRoom(r)" @click.stop="openAttEditRoom(r)" class="att-room-link-btn"
                            title="このルームを編集">
                        <i class="fas fa-pen" style="font-size:9px;"></i>
                    </button>
                    <button @click.stop="deleteAttRoom(r, $event)" class="att-room-del-btn" title="このルームを削除">
                        <i class="fas fa-times" style="font-size:9px;"></i>
                    </button>
                </div>
            </template>
            <template x-if="!attPersonalRoomsCollapsed && filteredAttPersonalRooms.length === 0">
                <p class="text-center" style="color:#9ca3af;font-size:11px;padding:6px;" x-text="attSidebarSearchQuery ? '該当なし' : 'なし'"></p>
            </template>

            {{-- ===== スレッド一覧 (折りたたみ可・縦スクロール) ===== --}}
            <div class="att-rooms-section" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;"
                 @click="toggleAttThreadsCollapsed()">
                <span><i class="fas" :class="attThreadsCollapsed ? 'fa-chevron-right' : 'fa-chevron-down'" style="font-size:8px;margin-right:4px;"></i>スレッド</span>
                <span x-show="visibleAttThreads.length > 0" style="color:#9ca3af;font-size:9px;font-weight:600;"
                      x-text="visibleAttThreads.length + '件'"></span>
            </div>
            <template x-if="!attThreadsCollapsed">
                <div style="max-height:320px;overflow-y:auto;">
                    <template x-for="t in visibleAttThreads" :key="'att-th-' + t.id">
                        <div @click="setAttThreadFilter(String(t.id))"
                             :class="isAttSidebarThreadActive(t) ? 'att-room-item active' : 'att-room-item'"
                             :title="t.subject"
                             style="position:relative;">
                            <button @click.stop="toggleAttThreadPin(t)"
                                    class="att-thread-pin-btn"
                                    :title="t.is_pinned ? 'ピン留め解除' : 'ピン留め'"
                                    :style="t.is_pinned ? 'color:#f59e0b;' : 'color:#d1d5db;'">
                                <i class="fas fa-thumbtack" style="font-size:9px;"></i>
                            </button>
                            <span class="name" x-text="t.subject || '(件名なし)'"></span>
                            {{-- ルームに追加 (ホバー時表示) --}}
                            <button @click.stop="openAttAddToRoom(t.id)" class="att-room-link-btn"
                                    title="このスレッドをルームに追加">
                                <i class="fas fa-link" style="font-size:9px;"></i>
                            </button>
                            {{-- 非表示にする (サイドバーのみ非表示。添付ファイル一覧は影響なし) --}}
                            <button @click.stop="toggleAttHideThread(t.id)" class="att-room-del-btn"
                                    title="このスレッドをサイドバーで非表示にする (添付ファイル一覧には影響しません)">
                                <i class="fas fa-eye-slash" style="font-size:9px;"></i>
                            </button>
                        </div>
                    </template>
                    <template x-if="visibleAttThreads.length === 0 && attSidebarSearchQuery">
                        <p class="text-center" style="color:#9ca3af;font-size:11px;padding:6px;">該当なし</p>
                    </template>
                    <template x-if="visibleAttThreads.length === 0 && !attSidebarSearchQuery">
                        <p class="text-center" style="color:#9ca3af;font-size:11px;padding:6px;">なし</p>
                    </template>
                </div>
            </template>

            {{-- 非表示も表示 トグル + 非表示中スレッド一覧 --}}
            <template x-if="!attThreadsCollapsed">
                <div>
                    <button type="button" @click="toggleAttShowHidden()"
                            style="width:calc(100% - 12px);margin:4px 6px;background:#f3f4f6;border:1px solid #e5e7eb;color:#4b5563;font-size:10px;font-weight:700;padding:4px 6px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:space-between;">
                        <span>
                            <i class="fas" :class="attShowHidden ? 'fa-eye' : 'fa-eye-slash'" style="font-size:10px;margin-right:4px;"></i>
                            <span x-text="attShowHidden ? '非表示を隠す' : '非表示も表示'"></span>
                        </span>
                        <span x-show="attHiddenThreadIds.length > 0"
                              style="background:#fee2e2;color:#b91c1c;border-radius:9999px;padding:0 6px;font-size:9px;"
                              x-text="attHiddenThreadIds.length"></span>
                    </button>
                    <template x-if="attShowHidden">
                        <div style="max-height:200px;overflow-y:auto;">
                            <template x-for="t in hiddenVisibleAttThreads" :key="'att-th-hidden-' + t.id">
                                <div :class="String(attThreadFilterId) === String(t.id) ? 'att-room-item active' : 'att-room-item'"
                                     style="position:relative;opacity:0.65;"
                                     @click="setAttThreadFilter(String(t.id))"
                                     :title="t.subject">
                                    <i class="fas fa-eye-slash" style="font-size:9px;color:#dc2626;"></i>
                                    <span class="name" x-text="t.subject || '(件名なし)'" style="text-decoration:line-through;"></span>
                                    <button @click.stop="unhideAttThread(t.id)" class="att-room-del-btn"
                                            style="opacity:1;color:#059669;"
                                            title="再表示">
                                        <i class="fas fa-undo" style="font-size:9px;"></i>
                                    </button>
                                </div>
                            </template>
                            <template x-if="hiddenVisibleAttThreads.length === 0">
                                <p class="text-center" style="color:#9ca3af;font-size:11px;padding:6px;">
                                    <span x-show="!attSidebarSearchQuery">非表示中のスレッドはありません</span>
                                    <span x-show="attSidebarSearchQuery">該当なし</span>
                                </p>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
        <div x-show="!attRoomsCollapsed"
             @mousedown.prevent="startResizeAttRooms($event)"
             @dblclick="attRoomsWidth = 200; try { localStorage.setItem('attRoomsWidth', '200'); } catch(_) {}"
             class="att-rooms-resize"
             :class="{ 'is-resizing': attRoomsResizing }"
             title="ドラッグで幅変更"></div>
    </aside>

    {{-- ===== メイン (顧客リストは廃止し、ツールバーのドロップダウンで絞込む) ===== --}}
    <main class="flex-1 min-w-0"
          style="width:100%;height:100%;display:flex;flex-direction:column;min-height:0;overflow:hidden;">

        {{-- ヘッダー (1行コンパクト: タイトル + 件数 + フィルタバッジ + 更新) --}}
        <div class="px-5 py-2 bg-white border-b border-gray-200 flex items-center justify-between gap-3"
             style="flex-shrink:0;">
            <div class="min-w-0 flex-1 inline-flex items-center gap-2">
                <i class="fas fa-paperclip text-blue-500 text-xs shrink-0"></i>
                <h1 class="text-sm font-extrabold text-gray-900 truncate"
                    x-text="(() => {
                        if (attThreadFilterId !== 'all') {
                            const t = attThreads.find(t => String(t.id) === String(attThreadFilterId));
                            return t ? ('スレッド: ' + (t.subject || '(件名なし)')) : 'スレッドの添付ファイル';
                        }
                        if (attRoomFilterId === 'none') return 'ルーム未設定';
                        if (attRoomFilterId !== 'all') {
                            const r = [...attRoomsShared, ...attRoomsPersonal].find(r => String(r.id) === String(attRoomFilterId));
                            return r ? ('# ' + r.name) : 'ルームの添付ファイル';
                        }
                        return 'すべての添付ファイル';
                    })()"></h1>
                <span class="text-[11px] font-bold text-gray-500 shrink-0" x-text="'(' + total + ')'"></span>
                <template x-if="hasActiveFilter">
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold shrink-0"
                          style="background-color:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;">
                        <i class="fas fa-filter"></i> フィルタ適用中
                    </span>
                </template>
            </div>
            {{-- 横断ナビは画面右上 (プロフィール横) のグローバル navbar に集約済み --}}
            <button @click="load()"
                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold transition-colors shrink-0"
                style="background-color:#111827;color:#ffffff;"
                onmouseover="this.style.backgroundColor='#000000';"
                onmouseout="this.style.backgroundColor='#111827';"
                :class="{ 'opacity-50 cursor-wait': loading }">
                <i class="fas text-[10px]" :class="loading ? 'fa-circle-notch fa-spin' : 'fa-sync-alt'"></i>
                更新
            </button>
        </div>

        {{-- 束ねたスレッド (ルーム選択中のみ表示)。
             折りたたみ時はラベル＋件数のみ。展開時は全チップ表示。
             メール画面 / チャット画面と同じ仕様。 --}}
        <div x-show="attRoomFilterId !== 'all' && attRoomBundledThreads.length > 0"
             class="shrink-0 bundle-band"
             style="padding:6px 12px;background:#f9fafb;border-bottom:1px solid #e5e7eb;display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
            <span style="font-size:10px;color:#6b7280;font-weight:700;text-transform:uppercase;">
                束ねたスレッド
                <span style="color:#9ca3af;font-weight:600;text-transform:none;margin-left:2px;"
                      x-text="'(' + attRoomBundledThreads.length + ')'"></span>
                :
            </span>
            <template x-for="bt in visibleAttBundleChips" :key="'att-bundle-' + bt.id">
                <span class="bundle-chip"
                      style="display:inline-flex;align-items:center;gap:4px;background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;">
                    <i class="fas fa-envelope" style="font-size:9px;color:#9ca3af;"></i>
                    <span x-text="bt.subject"></span>
                    <button @click="detachAttRoomThread(bt.id)" title="紐付けを外す"
                            style="background:none;border:none;color:#9ca3af;padding:0;cursor:pointer;font-size:10px;"><i class="fas fa-times"></i></button>
                </span>
            </template>
            {{-- 展開 / たたむ トグル --}}
            <button type="button"
                    @click="toggleAttBundleBandExpanded()"
                    style="background:#ffffff;border:1px dashed #cbd5e1;color:#475569;font-size:10px;font-weight:700;padding:3px 10px;border-radius:999px;cursor:pointer;display:inline-flex;align-items:center;gap:4px;"
                    onmouseover="this.style.backgroundColor='#eff6ff';this.style.borderColor='#60a5fa';this.style.color='#1d4ed8';"
                    onmouseout="this.style.backgroundColor='#ffffff';this.style.borderColor='#cbd5e1';this.style.color='#475569';"
                    :title="attBundleBandExpanded ? '束ねたスレッド一覧を隠す' : ('束ねたスレッド ' + attRoomBundledThreads.length + ' 件を展開')">
                <i class="fas" :class="attBundleBandExpanded ? 'fa-chevron-up' : 'fa-chevron-down'" style="font-size:8px;"></i>
                <span x-show="!attBundleBandExpanded">展開</span>
                <span x-show="attBundleBandExpanded">たたむ</span>
            </button>
        </div>

        {{-- ツールバー: 検索 + 顧客 + 期間 + 並び順 + 受信/送信 + 種別 --}}
        <div class="px-6 py-3 bg-white border-b border-gray-100 space-y-2"
             style="flex-shrink:0;">
            <div class="flex items-center gap-2 flex-wrap">
                <div class="relative flex-1" style="min-width:240px;">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" x-model="searchQuery" @input="onSearchInput()"
                           placeholder="ファイル名・メール件名で検索..."
                           class="w-full pl-8 pr-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-300">
                </div>

                {{-- ルームフィルタ (ドロップダウン. 左サイドバーと同じ attRoomFilterId を共有).
                     'all' / 'none' / 数値ルームID を選択可能. 階層表示は indent で表現. --}}
                <div class="relative shrink-0" @click.outside="roomDropdownOpen = false">
                    <button type="button" @click="roomDropdownOpen = !roomDropdownOpen"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold transition-colors"
                        :style="(attRoomFilterId && attRoomFilterId !== 'all')
                            ? 'background-color:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;'
                            : 'background-color:#f9fafb;color:#4b5563;border:1px solid #e5e7eb;'">
                        <i class="fas fa-folder"></i>
                        <span class="max-w-[160px] truncate" x-text="currentRoomFilterLabel"></span>
                        <i class="fas fa-chevron-down text-[9px]"></i>
                    </button>
                    <div x-show="roomDropdownOpen" x-cloak x-transition.duration.150ms
                         class="absolute right-0 mt-1 w-72 rounded-xl shadow-xl z-30 overflow-hidden flex flex-col"
                         style="background-color:#ffffff;border:1px solid #e5e7eb;max-height:420px;">
                        <div class="shrink-0 p-2 border-b border-gray-100">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                <input type="text" x-model="roomSearchQuery" placeholder="ルームを絞り込み..."
                                       class="w-full pl-8 pr-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-300">
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto custom-scrollbar p-1 min-h-0">
                            <button type="button"
                                @click="setAttRoomFilter('all'); roomDropdownOpen = false;"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold transition-colors flex items-center justify-between"
                                :style="attRoomFilterId === 'all'
                                    ? 'background-color:#dbeafe;color:#1e40af;'
                                    : 'background-color:transparent;color:#4b5563;'">
                                <span class="inline-flex items-center gap-2"><i class="fas fa-globe-asia text-[10px]"></i> すべて</span>
                            </button>
                            <button type="button"
                                @click="setAttRoomFilter('none'); roomDropdownOpen = false;"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold transition-colors flex items-center justify-between"
                                :style="attRoomFilterId === 'none'
                                    ? 'background-color:#dbeafe;color:#1e40af;'
                                    : 'background-color:transparent;color:#4b5563;'">
                                <span class="inline-flex items-center gap-2"><i class="fas fa-circle-question text-[10px]"></i> ルーム未設定</span>
                            </button>
                            <template x-if="filteredFlatRooms.length === 0">
                                <p class="text-center text-[11px] text-gray-400 py-3">ルームがありません</p>
                            </template>
                            <template x-for="r in filteredFlatRooms" :key="'roomf-' + r.id">
                                <button type="button"
                                    @click="setAttRoomFilter(r.id); roomDropdownOpen = false;"
                                    class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold transition-colors flex items-center justify-between"
                                    :style="String(attRoomFilterId) === String(r.id)
                                        ? 'background-color:#dbeafe;color:#1e40af;'
                                        : 'background-color:transparent;color:#4b5563;'">
                                    <span class="truncate pr-2 inline-flex items-center gap-1.5"
                                          :style="'padding-left:' + (r.depth * 12) + 'px;'">
                                        <i class="fas text-[9px]"
                                           :class="r.is_private ? 'fa-lock text-amber-500' : 'fa-folder text-blue-400'"></i>
                                        <span x-text="r.name"></span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg shrink-0"
                     style="background-color:#f9fafb;border:1px solid #e5e7eb;">
                    <i class="fas fa-calendar text-gray-400 text-[10px]"></i>
                    <input type="date" x-model="dateFrom" @change="load()"
                           class="bg-transparent border-none text-[11px] p-0 focus:ring-0 font-semibold text-gray-700 w-[120px]">
                    <span class="text-gray-300 text-xs">〜</span>
                    <input type="date" x-model="dateTo" @change="load()"
                           class="bg-transparent border-none text-[11px] p-0 focus:ring-0 font-semibold text-gray-700 w-[120px]">
                </div>
                <button @click="sortOrder = (sortOrder === 'desc' ? 'asc' : 'desc'); load()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold transition-colors shrink-0"
                    style="background-color:#f9fafb;color:#4b5563;border:1px solid #e5e7eb;"
                    onmouseover="this.style.backgroundColor='#ffffff';"
                    onmouseout="this.style.backgroundColor='#f9fafb';">
                    <i class="fas" :class="sortOrder === 'desc' ? 'fa-arrow-down-wide-short' : 'fa-arrow-up-short-wide'"></i>
                    <span x-text="sortOrder === 'desc' ? '新しい順' : '古い順'"></span>
                </button>

                {{-- アップロード (新規メールスレッドを作成してファイル添付) --}}
                <input type="file" multiple x-ref="uploadInput" style="display:none;"
                       @change="onUploadFilesSelected($event)" />
                <button @click="$refs.uploadInput.click()" :disabled="uploading"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold transition-colors shrink-0 disabled:opacity-50"
                    style="background-color:#4f46e5;color:#ffffff;border:1px solid #4f46e5;"
                    onmouseover="if(!this.disabled)this.style.backgroundColor='#4338ca';"
                    onmouseout="if(!this.disabled)this.style.backgroundColor='#4f46e5';"
                    :title="uploading ? 'アップロード中…' : '新しい添付ファイルをアップロード'">
                    <i class="fas" :class="uploading ? 'fa-spinner fa-spin' : 'fa-upload'"></i>
                    <span x-text="uploading ? 'アップロード中…' : 'アップロード'"></span>
                </button>

                <button @click="resetFilters()" x-show="hasActiveFilter"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold transition-colors shrink-0"
                    style="background-color:#fef2f2;color:#b91c1c;border:1px solid #fecaca;"
                    onmouseover="this.style.backgroundColor='#fee2e2';"
                    onmouseout="this.style.backgroundColor='#fef2f2';">
                    <i class="fas fa-times-circle"></i> 条件リセット
                </button>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                {{-- 受信/送信 --}}
                <div class="inline-flex items-center gap-0.5 p-0.5 rounded-lg" style="background-color:#f3f4f6;">
                    <button @click="setDirection('')"
                        class="px-2.5 py-1 rounded-md text-[11px] font-bold transition-all inline-flex items-center gap-1"
                        :style="direction === ''
                            ? 'background-color:#ffffff;color:#111827;box-shadow:0 1px 2px rgba(0,0,0,0.06);'
                            : 'background-color:transparent;color:#6b7280;'">
                        <i class="fas fa-globe text-[10px]"></i> すべて
                    </button>
                    <button @click="setDirection('received')"
                        class="px-2.5 py-1 rounded-md text-[11px] font-bold transition-all inline-flex items-center gap-1"
                        :style="direction === 'received'
                            ? 'background-color:#ffffff;color:#047857;box-shadow:0 1px 2px rgba(0,0,0,0.06);'
                            : 'background-color:transparent;color:#6b7280;'">
                        <i class="fas fa-inbox text-[10px]"></i> 受信
                    </button>
                    <button @click="setDirection('sent')"
                        class="px-2.5 py-1 rounded-md text-[11px] font-bold transition-all inline-flex items-center gap-1"
                        :style="direction === 'sent'
                            ? 'background-color:#ffffff;color:#1d4ed8;box-shadow:0 1px 2px rgba(0,0,0,0.06);'
                            : 'background-color:transparent;color:#6b7280;'">
                        <i class="fas fa-paper-plane text-[10px]"></i> 送信
                    </button>
                </div>

                {{-- 種別 --}}
                <div class="inline-flex items-center gap-0.5 p-0.5 rounded-lg" style="background-color:#f3f4f6;">
                    <template x-for="t in typeTabs" :key="t.key">
                        <button @click="setTypeFilter(t.key)"
                            class="px-2.5 py-1 rounded-md text-[11px] font-bold transition-all inline-flex items-center gap-1"
                            :style="typeFilter === t.key
                                ? 'background-color:#ffffff;color:#1d4ed8;box-shadow:0 1px 2px rgba(0,0,0,0.06);'
                                : 'background-color:transparent;color:#6b7280;'">
                            <i class="fas text-[10px]" :class="t.icon"></i>
                            <span x-text="t.label"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        {{-- 結果エリア --}}
        <div class="custom-scrollbar"
             style="flex:1 1 0%;min-height:0;height:0;overflow-y:auto;overflow-x:hidden;padding:20px 24px;">
            {{-- ローディング --}}
            <template x-if="loading">
                <div class="flex flex-col items-center justify-center py-20 text-gray-400">
                    <i class="fas fa-circle-notch fa-spin fa-2x mb-3"></i>
                    <p class="text-xs font-bold">読み込み中...</p>
                </div>
            </template>

            {{-- 空 --}}
            <template x-if="!loading && attachments.length === 0">
                <div class="flex flex-col items-center justify-center py-20 px-6 text-center bg-white border border-gray-200 rounded-2xl">
                    <div class="w-14 h-14 rounded-full flex items-center justify-center mb-3"
                         style="background-color:#f3f4f6;color:#9ca3af;">
                        <i class="fas fa-paperclip fa-lg"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-700">添付ファイルが見つかりません</p>
                    <p class="text-[11px] text-gray-400 mt-1" x-text="hasActiveFilter ? '検索条件を見直してください' : 'メールの送受信時に添付されたファイルがここに表示されます'"></p>
                </div>
            </template>

            {{-- リスト表示 (グリッド表示は廃止) --}}
            <template x-if="!loading && attachments.length > 0">
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table class="w-full text-sm" style="table-layout:fixed;">
                        <thead style="background-color:#f9fafb;">
                            <tr class="border-b border-gray-100">
                                <th class="px-3 py-2.5" style="width:56px;"></th>
                                <th class="text-left px-3 py-2.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider" style="width:42%;">ファイル名</th>
                                <th class="text-left px-3 py-2.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider">件名 / 相手</th>
                                <th class="text-left px-3 py-2.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider" style="width:160px;">日時 / サイズ</th>
                                {{--
                                    アクション列の幅。以前は 64px だったが、ダウンロード (36px) +
                                    ギャップ (6px) + 削除 (36px) + 横パディング (24px) ≒ 102px
                                    必要で、削除ボタンが親の overflow-hidden に隠れて押せなかった。
                                    両ボタンが余裕で収まる 120px に拡張する。
                                --}}
                                <th class="px-3 py-2.5" style="width:120px;"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="att in attachments" :key="att.id">
                                <tr class="transition-colors"
                                    :data-att-row-id="att.id"
                                    :style="selectedAttachmentId === att.id ? 'background-color:#eff6ff;' : ''"
                                    onmouseover="if(!this.dataset.selected) this.style.backgroundColor='#f9fafb';"
                                    onmouseout="if(!this.dataset.selected) this.style.backgroundColor='';"
                                    :data-selected="selectedAttachmentId === att.id ? '1' : null"
                                    @click="selectedAttachmentId = att.id">
                                    <td class="px-3 py-2.5 text-center">
                                        <template x-if="att.is_image">
                                            <div class="w-10 h-10 rounded-lg overflow-hidden border border-gray-200 bg-gray-50 inline-block cursor-pointer"
                                                 @click="openPreview(att)">
                                                <img :src="att.url" :alt="att.filename" class="w-full h-full object-cover">
                                            </div>
                                        </template>
                                        <template x-if="!att.is_image">
                                            <span class="text-2xl" x-text="mimeIcon(att.mime_type)"></span>
                                        </template>
                                    </td>
                                    <td class="px-3 py-2.5" style="overflow:hidden;">
                                        <div class="flex items-center gap-2 mb-1" style="min-width:0;">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold shrink-0"
                                                  :style="att.direction === 'sent'
                                                    ? 'background-color:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;'
                                                    : 'background-color:#d1fae5;color:#047857;border:1px solid #a7f3d0;'">
                                                <i class="fas" :class="att.direction === 'sent' ? 'fa-paper-plane' : 'fa-inbox'"></i>
                                                <span x-text="att.direction === 'sent' ? '送信' : '受信'"></span>
                                            </span>
                                            <button @click="openPreview(att)"
                                                    class="text-xs font-bold text-gray-800 hover:text-blue-600 text-left transition-colors"
                                                    style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;"
                                                    x-text="att.filename" :title="att.filename"></button>
                                        </div>
                                        <span class="text-[10px] text-gray-400 font-semibold"
                                              style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                              x-text="mimeLabel(att.mime_type)" :title="mimeLabel(att.mime_type)"></span>
                                    </td>
                                    <td class="px-3 py-2.5 max-w-0">
                                        <template x-if="att.thread_id">
                                            <a :href="'/?thread=' + att.thread_id"
                                               class="text-[12px] text-blue-600 hover:underline font-bold block mb-0.5 leading-snug"
                                               style="word-break:break-word;overflow-wrap:anywhere;white-space:normal;"
                                               x-text="att.email_subject" :title="att.email_subject"></a>
                                        </template>
                                        {{--
                                            From / To / Cc はメール一覧と同じ「アドレス優先」表記。
                                            from_address に "@" が無い (= MAILER-DAEMON のような表示名のみ) の場合は
                                            from_label を出して「アドレスなし」と注記する。
                                            Cc は値が入っている時だけ次行に表示する。
                                        --}}
                                        <p class="text-[10px] text-gray-500 truncate">
                                            <template x-if="att.direction === 'sent'">
                                                <span :title="att.to_address">
                                                    <span class="text-gray-400">To:</span>
                                                    <span x-text="att.to_address || '—'"></span>
                                                </span>
                                            </template>
                                            <template x-if="att.direction !== 'sent'">
                                                <span :title="(att.from_label || '') + ' <' + (att.from_address || '') + '>'">
                                                    <span class="text-gray-400">From:</span>
                                                    <template x-if="att.from_address && att.from_address.includes('@')">
                                                        <span x-text="att.from_address"></span>
                                                    </template>
                                                    <template x-if="!att.from_address || !att.from_address.includes('@')">
                                                        <span>
                                                            <span x-text="att.from_label || att.from_address || '—'"></span>
                                                            <span class="ml-1 italic text-gray-400">(アドレスなし)</span>
                                                        </span>
                                                    </template>
                                                </span>
                                            </template>
                                        </p>
                                        <p class="text-[10px] text-gray-500 truncate" x-show="att.cc" :title="att.cc">
                                            <span class="text-gray-400">Cc:</span>
                                            <span x-text="att.cc"></span>
                                        </p>
                                    </td>
                                    <td class="px-3 py-2.5 whitespace-nowrap">
                                        <p class="text-[11px] text-gray-700 font-semibold" x-text="att.received_at"></p>
                                        <p class="text-[10px] text-gray-400 font-semibold" x-text="att.size"></p>
                                    </td>
                                    <td class="px-3 py-2.5 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <a :href="att.url" :download="att.filename"
                                                class="att-action-btn att-action-download"
                                                title="ダウンロード">
                                                <i class="fas fa-download text-xs"></i>
                                            </a>
                                            <button @click.stop="deleteAttachment(att)"
                                                class="att-action-btn att-action-delete"
                                                title="この添付ファイルを一覧から削除">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        {{-- ページャー (件数 + 前へ/次へ + ページ番号 + 1ページあたりの件数) --}}
        <div class="px-6 py-2 bg-white border-t border-gray-200 flex items-center justify-between gap-3 flex-wrap"
             style="flex-shrink:0;"
             x-show="!loading && total > 0">
            <div class="text-[11px] text-gray-500">
                <span class="font-bold text-gray-700"
                      x-text="((page - 1) * perPage + 1) + '〜' + Math.min(page * perPage, total)"></span>
                <span class="mx-1 text-gray-300">/</span>
                <span class="font-bold text-gray-700" x-text="total + ' 件'"></span>
            </div>

            <div class="flex items-center gap-2">
                {{-- 1ページあたり --}}
                <label class="text-[11px] text-gray-500 inline-flex items-center gap-1.5">
                    表示
                    <select @change="changePerPage($event.target.value)"
                            class="text-[11px] font-bold rounded-lg outline-none focus:ring-2 focus:ring-blue-100"
                            style="background-color:#f9fafb;color:#111827;border:1px solid #e5e7eb;padding:4px 8px;">
                        <option :value="20" :selected="perPage === 20">20</option>
                        <option :value="30" :selected="perPage === 30">30</option>
                        <option :value="50" :selected="perPage === 50">50</option>
                        <option :value="100" :selected="perPage === 100">100</option>
                    </select>
                    件
                </label>

                {{-- ページャー本体 --}}
                <div class="inline-flex items-center gap-0.5">
                    <button type="button" @click="goToPage(1)"
                            :disabled="page <= 1"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-[11px] font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            style="background-color:#f9fafb;color:#4b5563;border:1px solid #e5e7eb;"
                            onmouseover="if(!this.disabled)this.style.backgroundColor='#ffffff';"
                            onmouseout="if(!this.disabled)this.style.backgroundColor='#f9fafb';"
                            title="先頭へ">
                        <i class="fas fa-angle-double-left"></i>
                    </button>
                    <button type="button" @click="prevPage()"
                            :disabled="page <= 1"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-[11px] font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            style="background-color:#f9fafb;color:#4b5563;border:1px solid #e5e7eb;"
                            onmouseover="if(!this.disabled)this.style.backgroundColor='#ffffff';"
                            onmouseout="if(!this.disabled)this.style.backgroundColor='#f9fafb';"
                            title="前のページ">
                        <i class="fas fa-angle-left"></i>
                    </button>
                    <span class="px-3 py-1 text-[11px] font-bold rounded-lg"
                          style="background-color:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;">
                        <span x-text="page"></span>
                        <span class="text-blue-400 mx-0.5">/</span>
                        <span x-text="totalPages"></span>
                    </span>
                    <button type="button" @click="nextPage()"
                            :disabled="page >= totalPages"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-[11px] font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            style="background-color:#f9fafb;color:#4b5563;border:1px solid #e5e7eb;"
                            onmouseover="if(!this.disabled)this.style.backgroundColor='#ffffff';"
                            onmouseout="if(!this.disabled)this.style.backgroundColor='#f9fafb';"
                            title="次のページ">
                        <i class="fas fa-angle-right"></i>
                    </button>
                    <button type="button" @click="goToPage(totalPages)"
                            :disabled="page >= totalPages"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-[11px] font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            style="background-color:#f9fafb;color:#4b5563;border:1px solid #e5e7eb;"
                            onmouseover="if(!this.disabled)this.style.backgroundColor='#ffffff';"
                            onmouseout="if(!this.disabled)this.style.backgroundColor='#f9fafb';"
                            title="末尾へ">
                        <i class="fas fa-angle-double-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </main>

    {{-- ===== プレビューモーダル ===== --}}
    <div x-show="previewOpen" x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center p-4"
         style="background-color:rgba(15,23,42,0.7);"
         @click.self="previewOpen = false"
         @keydown.escape.window="previewOpen = false">
        <div class="w-full max-w-4xl rounded-2xl overflow-hidden flex flex-col"
             style="background-color:#ffffff;max-height:90vh;box-shadow:0 25px 50px -12px rgba(0,0,0,0.4);">
            {{-- ヘッダー --}}
            <div class="shrink-0 px-5 py-3 flex items-center justify-between gap-4 border-b border-gray-100"
                 style="background-color:#f9fafb;">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-extrabold text-gray-900 truncate" x-text="previewFile?.filename"></p>
                    <p class="text-[10px] text-gray-500 mt-0.5 truncate">
                        <span x-text="previewFile?.size"></span>
                        <span class="text-gray-300 mx-1">•</span>
                        <span x-text="mimeLabel(previewFile?.mime_type)"></span>
                        <span class="text-gray-300 mx-1">•</span>
                        <span x-text="previewFile?.received_at"></span>
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a :href="previewFile?.url" :download="previewFile?.filename"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-bold transition-colors"
                       style="background-color:#2563eb;color:#ffffff;"
                       onmouseover="this.style.backgroundColor='#1d4ed8';"
                       onmouseout="this.style.backgroundColor='#2563eb';">
                        <i class="fas fa-download"></i> ダウンロード
                    </a>
                    <button @click="previewOpen = false"
                            class="w-9 h-9 inline-flex items-center justify-center rounded-lg transition-colors"
                            style="color:#9ca3af;"
                            onmouseover="this.style.backgroundColor='#f3f4f6';this.style.color='#374151';"
                            onmouseout="this.style.backgroundColor='';this.style.color='#9ca3af';"
                            title="閉じる">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            {{-- 本文 --}}
            <div class="flex-1 overflow-auto p-6 flex items-center justify-center"
                 style="background-color:#ffffff;min-height:300px;">
                <template x-if="previewFile?.is_image">
                    <img :src="previewFile?.url" class="max-w-full max-h-[65vh] rounded-xl border border-gray-200">
                </template>
                <template x-if="previewFile && !previewFile.is_image">
                    <div class="text-center py-8">
                        <div class="text-7xl mb-5" x-text="mimeIcon(previewFile?.mime_type)"></div>
                        <p class="text-base font-bold text-gray-900 mb-1 truncate max-w-[400px] mx-auto" x-text="previewFile?.filename"></p>
                        <p class="text-[11px] text-gray-500 mb-5">
                            <span x-text="mimeLabel(previewFile?.mime_type)"></span>
                            <span class="text-gray-300 mx-1">•</span>
                            <span x-text="previewFile?.size"></span>
                        </p>
                        <a :href="previewFile?.url" :download="previewFile?.filename"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-bold transition-colors"
                            style="background-color:#111827;color:#ffffff;"
                            onmouseover="this.style.backgroundColor='#000000';"
                            onmouseout="this.style.backgroundColor='#111827';">
                            <i class="fas fa-download"></i> ダウンロード
                        </a>
                    </div>
                </template>
            </div>

            {{-- フッター: 関連メール --}}
            <template x-if="previewFile?.email_subject">
                <div class="shrink-0 px-5 py-3 border-t border-gray-100 text-[11px] text-gray-600 flex items-center gap-2 flex-wrap"
                     style="background-color:#f9fafb;">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold"
                          :style="previewFile?.direction === 'sent'
                            ? 'background-color:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;'
                            : 'background-color:#d1fae5;color:#047857;border:1px solid #a7f3d0;'">
                        <i class="fas" :class="previewFile?.direction === 'sent' ? 'fa-paper-plane' : 'fa-inbox'"></i>
                        <span x-text="previewFile?.direction === 'sent' ? '送信' : '受信'"></span>
                    </span>
                    <span class="text-gray-500">
                        <span class="text-gray-400" x-text="previewFile?.direction === 'sent' ? 'To:' : 'From:'"></span>
                        <span class="font-bold text-gray-800" x-text="previewFile?.direction === 'sent' ? previewFile?.to_address : previewFile?.from_label"></span>
                    </span>
                    <span class="text-gray-300">|</span>
                    <span class="text-gray-500">件名:</span>
                    <a :href="'/?thread=' + previewFile?.thread_id" target="_blank"
                       class="text-blue-600 hover:underline font-bold"
                       style="word-break:break-word;overflow-wrap:anywhere;white-space:normal;"
                       x-text="previewFile?.email_subject"></a>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function attachmentApp() {
    return {
        loading: false,
        uploading: false,
        attachments: [],
        total: 0,
        searchQuery: '',
        typeFilter: '',
        direction: '',
        dateFrom: '',
        dateTo: '',
        sortOrder: 'desc',
        searchDebounce: null,
        previewOpen: false,
        previewFile: null,
        // J/K ナビ用. 現在ハイライトされている添付の id. プレビューとは独立.
        selectedAttachmentId: null,
        // ルームフィルタドロップダウン (左サイドバーと state を共有: attRoomFilterId).
        // 顧客フィルタは削除済 (2026-05). 顧客単位の絞込はルーム単位に統一.
        roomDropdownOpen: false,
        roomSearchQuery: '',
        // ページング
        page: 1,
        perPage: 30,
        totalPages: 1,
        // ルーム (メール/チャットと同じ概念をここにも)
        attRoomFilterId: localStorage.getItem('attRoomFilterId') || 'all',
        attRoomsShared: [],
        attRoomsPersonal: [],
        attRoomsCollapsed: (() => { try { return JSON.parse(localStorage.getItem('attRoomsCollapsed') || 'false'); } catch(_) { return false; } })(),
        attRoomsWidth: parseInt(localStorage.getItem('attRoomsWidth') || '200', 10),
        attRoomsResizing: false,
        attCreateRoomOpen: false,
        attNewRoomName: '',
        attNewRoomIsPrivate: false,
        attCreatingRoom: false,
        // ルーム編集モーダル state
        attEditRoomOpen: false,
        attEditRoomId: null,
        attEditRoomName: '',
        attEditRoomIsPrivate: false,
        attEditRoomIsCreator: false,
        attEditingRoom: false,
        // 編集/削除の権限判定 (ログインユーザーが作成者か) に使う
        attMyUserId: @json(auth()->id() ?? null),
        // スレッド (チャットと同じ概念をここにも)
        attThreads: [],
        attThreadFilterId: localStorage.getItem('attThreadFilterId') || 'all',
        attSidebarSearchQuery: '',
        attThreadsCollapsed: (() => { try { return JSON.parse(localStorage.getItem('attThreadsCollapsed') || 'false'); } catch(_) { return false; } })(),
        attSharedRoomsCollapsed: (() => { try { return JSON.parse(localStorage.getItem('attSharedRoomsCollapsed') || 'false'); } catch(_) { return false; } })(),
        attPersonalRoomsCollapsed: (() => { try { return JSON.parse(localStorage.getItem('attPersonalRoomsCollapsed') || 'false'); } catch(_) { return false; } })(),
        // 階層化ルーム: 各ノードの折りたたみ状態
        attRoomBranchCollapsed: (() => { try { return JSON.parse(localStorage.getItem('attRoomBranchCollapsed') || '{}'); } catch(_) { return {}; } })(),
        attShowHidden: (() => { try { return JSON.parse(localStorage.getItem('attShowHidden') || 'false'); } catch(_) { return false; } })(),
        attHiddenThreadIds: [],
        // スレッドをルームに追加するモーダル
        attAddToRoomOpen: false,
        attAddToRoomThreadId: null,
        // 選択中ルームに紐付けされたスレッド (チップ表示用)
        attRoomBundledThreads: [],
        // 束ねたスレッド帯: 折りたたみ状態 (デフォルト = 折りたたみ済み)。
        // 折りたたみ時はチップを 1 件も出さず、ラベル＋件数だけ表示する。
        attBundleBandExpanded: (() => { try { return JSON.parse(localStorage.getItem('attBundleBandExpanded') || 'false'); } catch(_) { return false; } })(),
        // 自分が非表示にしているルーム ID 集合
        attHiddenRoomIds: [],

        typeTabs: [
            { key: '',         label: 'すべて',  icon: 'fa-layer-group' },
            { key: 'image',    label: '画像',    icon: 'fa-image' },
            { key: 'document', label: '文書',    icon: 'fa-file-lines' },
            { key: 'other',    label: 'その他',  icon: 'fa-box' },
        ],

        async init() {
            // クエリパラメータ `?room=<id>` / `?thread=<id>` が受け渡された場合は先に反映
            try {
                const url = new URL(window.location.href);
                const rawRoom   = url.searchParams.get('room');
                const rawThread = url.searchParams.get('thread');
                const roomId   = rawRoom   && /^\d+$/.test(rawRoom)   ? parseInt(rawRoom, 10)   : null;
                const threadId = rawThread && /^\d+$/.test(rawThread) ? parseInt(rawThread, 10) : null;
                if (roomId) {
                    this.attRoomFilterId = String(roomId);
                    try {
                        localStorage.setItem('attRoomFilterId', String(roomId));
                        localStorage.setItem('currentRoomId', String(roomId));
                    } catch (_) {}
                } else {
                    try {
                        const lf = localStorage.getItem('attRoomFilterId');
                        if (lf && lf !== 'all') localStorage.setItem('currentRoomId', String(lf));
                    } catch (_) {}
                }
                if (threadId) {
                    this.attThreadFilterId = String(threadId);
                    try {
                        localStorage.setItem('attThreadFilterId', String(threadId));
                        localStorage.setItem('currentThreadId', String(threadId));
                    } catch (_) {}
                } else {
                    // URL でスレッド指定なし → localStorage の値を共通キーに反映 (なければ all)
                    try {
                        const lf = localStorage.getItem('attThreadFilterId');
                        if (lf && lf !== 'all') localStorage.setItem('currentThreadId', String(lf));
                    } catch (_) {}
                }
            } catch (_) {}
            await Promise.all([this.load(), this.loadAttRooms(), this.loadAttThreads(), this.loadAttRoomBundledThreads()]);
        },

        get hasActiveFilter() {
            return !!(this.searchQuery || this.typeFilter || this.direction || this.dateFrom || this.dateTo
                || (this.attRoomFilterId && this.attRoomFilterId !== 'all')
                || (this.attThreadFilterId && this.attThreadFilterId !== 'all'));
        },

        // ===== ツールバー ルームフィルタ用ヘルパ =====
        // 現在の attRoomFilterId に対応する表示名を返す.
        get currentRoomFilterLabel() {
            const id = this.attRoomFilterId;
            if (!id || id === 'all') return 'ルーム: すべて';
            if (id === 'none') return 'ルーム未設定';
            const r = [...(this.attRoomsShared || []), ...(this.attRoomsPersonal || [])]
                .find(rr => String(rr.id) === String(id));
            return r ? r.name : '不明なルーム';
        },
        // ドロップダウン内に階層をフラット化して表示するための配列.
        // ルーム検索クエリでフィルタもかける. 共有 → 個人の順に並べ、それぞれ親→子で字下げ.
        get filteredFlatRooms() {
            const q = (this.roomSearchQuery || '').toLowerCase().trim();
            const shared   = this._walkAttRoomTree(this.attRoomsShared || []);
            const personal = this._walkAttRoomTree(this.attRoomsPersonal || []);
            const all = [...shared, ...personal];
            if (!q) return all;
            return all.filter(r => (r.name || '').toLowerCase().includes(q));
        },

        // ===== サイドバーの横断絞り込み =====
        get _attQuery() { return (this.attSidebarSearchQuery || '').toLowerCase().trim(); },
        // 階層化したルーム配列を DFS で並べ、親→子の順に展開して depth を付与する.
        // 配下のルームに親→子で字下げする視覚表現に使う.
        _walkAttRoomTree(rooms) {
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
                    if (hasChildren && !this.attRoomBranchCollapsed[r.id]) {
                        dfs(String(r.id), depth + 1);
                    }
                }
            };
            dfs('root', 0);
            return out;
        },
        toggleAttRoomBranch(id) {
            this.attRoomBranchCollapsed = { ...this.attRoomBranchCollapsed, [id]: !this.attRoomBranchCollapsed[id] };
            try { localStorage.setItem('attRoomBranchCollapsed', JSON.stringify(this.attRoomBranchCollapsed)); } catch(_) {}
        },
        // 選択中ルームの子孫 ID 集合 (子ルームも青ハイライト対象にする).
        get _selectedAttRoomDescendants() {
            const all = [...(this.attRoomsShared || []), ...(this.attRoomsPersonal || [])];
            const id = this.attRoomFilterId && this.attRoomFilterId !== 'all' && this.attRoomFilterId !== 'none'
                ? Number(this.attRoomFilterId) : null;
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
        isAttRoomInSelection(r) {
            if (!r) return false;
            return this._selectedAttRoomDescendants.has(Number(r.id));
        },
        get filteredAttSharedRooms() {
            const q = this._attQuery;
            const hidden = new Set(this.attHiddenRoomIds || []);
            let base = this.attRoomsShared || [];
            if (!this.attShowHidden) base = base.filter(r => !hidden.has(Number(r.id)));
            if (q) base = base.filter(r => (r.name || '').toLowerCase().includes(q));
            return this._walkAttRoomTree(base);
        },
        get filteredAttPersonalRooms() {
            const q = this._attQuery;
            const hidden = new Set(this.attHiddenRoomIds || []);
            let base = this.attRoomsPersonal || [];
            if (!this.attShowHidden) base = base.filter(r => !hidden.has(Number(r.id)));
            if (q) base = base.filter(r => (r.name || '').toLowerCase().includes(q));
            return this._walkAttRoomTree(base);
        },
        isAttRoomHidden(r) { return (this.attHiddenRoomIds || []).includes(Number(r.id)); },
        get _sortedAttThreads() {
            let arr = (this.attThreads || []).slice();
            // ルームフィルタの値で挙動を分岐:
            //   - 'all': フィルタなし
            //   - 'none': どのルームにも属していないスレッドだけ
            //   - 数値 ID: そのルームに束ねられたスレッドだけ
            if (this.attRoomFilterId === 'none') {
                const inAnyRoom = this._allAttBundledThreadIds;
                arr = arr.filter(t => !inAnyRoom.has(Number(t.id)));
            } else if (this.attRoomFilterId && this.attRoomFilterId !== 'all') {
                const bundleIds = new Set((this.attRoomBundledThreads || []).map(b => Number(b.id)));
                arr = arr.filter(t => bundleIds.has(Number(t.id)));
            }
            return arr.sort((a, b) => (b.is_pinned ? 1 : 0) - (a.is_pinned ? 1 : 0));
        },

        // 全ルームのバンドルスレッド ID 集合 (Set)。
        // 「ルーム未設定」フィルタで「どのルームにも入っていない」スレッドを
        // サイドバー側でも絞り込むために使う。
        get _allAttBundledThreadIds() {
            const set = new Set();
            const collect = (rooms) => {
                for (const r of (rooms || [])) {
                    for (const tid of (r.bundled_thread_ids || [])) {
                        set.add(Number(tid));
                    }
                }
            };
            collect(this.attRoomsShared);
            collect(this.attRoomsPersonal);
            return set;
        },
        get visibleAttThreads() {
            const q = this._attQuery;
            const hidden = new Set((this.attHiddenThreadIds || []).map(Number));
            return this._sortedAttThreads
                .filter(t => !hidden.has(Number(t.id)))
                .filter(t => !q || (t.subject || '').toLowerCase().includes(q));
        },
        get hiddenVisibleAttThreads() {
            const q = this._attQuery;
            const hidden = new Set((this.attHiddenThreadIds || []).map(Number));
            return this._sortedAttThreads
                .filter(t => hidden.has(Number(t.id)))
                .filter(t => !q || (t.subject || '').toLowerCase().includes(q));
        },
        // 添付ファイルサイドバー上、スレッド行が「選択中」に見えるかの判定
        isAttSidebarThreadActive(t) {
            if (String(this.attThreadFilterId) === String(t.id)) return true;
            if (this.attRoomFilterId && this.attRoomFilterId !== 'all') {
                return (this.attRoomBundledThreads || []).some(b => Number(b.id) === Number(t.id));
            }
            return false;
        },

        async load(opts = {}) {
            // フィルタ変更系の呼び出しは1ページ目に戻す
            if (opts.resetPage !== false) this.page = 1;
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.searchQuery) params.set('q', this.searchQuery);
                if (this.typeFilter)  params.set('type', this.typeFilter);
                if (this.direction)   params.set('direction', this.direction);
                if (this.dateFrom)    params.set('date_from', this.dateFrom);
                if (this.dateTo)      params.set('date_to', this.dateTo);
                // customer_id フィルタは廃止 (顧客フィルタを削除済). ルーム単位に統一.
                if (this.attRoomFilterId && this.attRoomFilterId !== 'all') params.set('chat_room_id', this.attRoomFilterId);
                if (this.attThreadFilterId && this.attThreadFilterId !== 'all') params.set('thread_id', this.attThreadFilterId);
                params.set('sort', this.sortOrder);
                params.set('page',     String(this.page));
                params.set('per_page', String(this.perPage));

                const res = await fetch('/attachments?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.attachments = data.attachments ?? [];
                this.total       = data.total ?? 0;
                this.page        = data.page ?? 1;
                this.totalPages  = data.total_pages ?? 1;
                this.perPage     = data.per_page ?? this.perPage;
            } finally {
                this.loading = false;
            }
        },

        goToPage(p) {
            const target = Math.max(1, Math.min(this.totalPages, p));
            if (target === this.page) return;
            this.page = target;
            this.load({ resetPage: false });
            // スクロール位置を先頭へ
            const el = document.querySelector('.att-root [style*="overflow-y:auto"]');
            if (el) el.scrollTop = 0;
        },
        nextPage() { this.goToPage(this.page + 1); },
        prevPage() { this.goToPage(this.page - 1); },
        changePerPage(n) {
            this.perPage = parseInt(n, 10) || 30;
            this.page = 1;
            this.load({ resetPage: false });
        },

        onSearchInput() {
            clearTimeout(this.searchDebounce);
            this.searchDebounce = setTimeout(() => this.load(), 300);
        },

        setTypeFilter(type) { this.typeFilter = type; this.load(); },
        setDirection(dir)   { this.direction  = dir;  this.load(); },

        resetFilters() {
            this.searchQuery      = '';
            this.typeFilter       = '';
            this.direction        = '';
            this.dateFrom         = '';
            this.dateTo           = '';
            this.attRoomFilterId  = 'all';
            this.attThreadFilterId = 'all';
            try {
                localStorage.setItem('attRoomFilterId', 'all');
                localStorage.setItem('attThreadFilterId', 'all');
                localStorage.removeItem('currentRoomId');
                localStorage.removeItem('currentThreadId');
            } catch(_) {}
            this.load();
        },

        // ===== 添付ファイルのアップロード / 削除 =====
        async onUploadFilesSelected(event) {
            const files = Array.from(event.target.files || []);
            if (files.length === 0) return;
            this.uploading = true;
            try {
                const fd = new FormData();
                files.forEach(f => fd.append('files[]', f));
                // ルームが選択されていればそのルームに紐付ける
                // (attRoomFilterId = 'all' / 数値ID / 'none' のいずれか)
                if (this.attRoomFilterId
                    && this.attRoomFilterId !== 'all'
                    && this.attRoomFilterId !== 'none'
                    && /^\d+$/.test(String(this.attRoomFilterId))) {
                    fd.append('chat_room_id', String(this.attRoomFilterId));
                }
                const res = await fetch('/attachments/upload', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: fd,
                });
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    throw new Error(data.message || `HTTP ${res.status}`);
                }
                const data = await res.json();
                // 成功: 一覧 + ルームのバンドルスレッド情報をリロード
                await this.load();
                if (typeof this.loadAttRoomBundledThreads === 'function') {
                    try { await this.loadAttRoomBundledThreads(); } catch (_) {}
                }
                // バックエンドがルーム名を返してくれるのでそれを使い、
                // どこに保存されたかを toast で明示する
                const where = data.room_name
                    ? `ルーム『${data.room_name}』`
                    : (data.chat_room_id ? '選択中のルーム' : 'マイ添付');
                const msg = `${data.count} 件のファイルを${where}にアップロードしました`;
                if (typeof this.toast === 'function') {
                    this.toast(msg, 'success');
                } else {
                    alert(msg);
                }
            } catch (e) {
                console.error('アップロード失敗', e);
                if (typeof this.toast === 'function') {
                    this.toast('アップロードに失敗しました: ' + e.message, 'error');
                } else {
                    alert('アップロードに失敗しました: ' + e.message);
                }
            } finally {
                this.uploading = false;
                event.target.value = ''; // 同じファイルを連続で選べるように
            }
        },

        async deleteAttachment(att) {
            // 「削除」と銘打っているが実体は「添付ファイル一覧からの非表示」。
            // 元メール本文のプレビューやダウンロードリンクは引き続き使えるため、
            // 文面でも誤解されないように説明する。
            if (!confirm(`「${att.filename}」を添付ファイル一覧から削除しますか?\n(元メールの添付ファイルは残ります)`)) return;
            try {
                const res = await fetch(`/attachments/${att.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                // 一覧から消す (リロードでも良いが体感速度のためローカル除去)
                this.attachments = this.attachments.filter(a => a.id !== att.id);
                this.total = Math.max(0, this.total - 1);
                if (typeof this.toast === 'function') {
                    this.toast('添付ファイル一覧から削除しました', 'success');
                }
            } catch (e) {
                console.error('削除失敗', e);
                if (typeof this.toast === 'function') {
                    this.toast('削除に失敗しました: ' + e.message, 'error');
                } else {
                    alert('削除に失敗しました: ' + e.message);
                }
            }
        },

        // ===== スレッド (チャットと同じ概念) =====
        async loadAttThreads() {
            try {
                // show_hidden=1 で非表示も含めて全件取得 (サイドバーで自前フィルタするため)
                const res = await fetch('/chats/threads?show_hidden=1', { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const d = await res.json();
                this.attThreads = (d.threads || []).map(t => ({
                    id: t.id,
                    subject: t.subject,
                    is_pinned: !!t.is_pinned,
                }));
                // サーバ側で「依然として非表示」と判定された ID リスト
                this.attHiddenThreadIds = (d.hidden_threads || []).map(Number);
                // 不可視/存在しないスレッドが選択されていればクリア
                const allIds = this.attThreads.map(t => String(t.id));
                if (this.attThreadFilterId !== 'all' && !allIds.includes(String(this.attThreadFilterId))) {
                    this.attThreadFilterId = 'all';
                    try { localStorage.setItem('attThreadFilterId', 'all'); } catch(_) {}
                }
            } catch (_) {}
        },
        async toggleAttThreadPin(t) {
            try {
                const res = await fetch(`/threads/${t.id}/pin`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
                if (!res.ok) return;
                const data = await res.json();
                t.is_pinned = !!data.is_pinned;
            } catch (_) {}
        },
        toggleAttThreadsCollapsed() {
            this.attThreadsCollapsed = !this.attThreadsCollapsed;
            try { localStorage.setItem('attThreadsCollapsed', JSON.stringify(this.attThreadsCollapsed)); } catch(_) {}
        },
        toggleAttSharedRoomsCollapsed() {
            this.attSharedRoomsCollapsed = !this.attSharedRoomsCollapsed;
            try { localStorage.setItem('attSharedRoomsCollapsed', JSON.stringify(this.attSharedRoomsCollapsed)); } catch(_) {}
        },
        toggleAttPersonalRoomsCollapsed() {
            this.attPersonalRoomsCollapsed = !this.attPersonalRoomsCollapsed;
            try { localStorage.setItem('attPersonalRoomsCollapsed', JSON.stringify(this.attPersonalRoomsCollapsed)); } catch(_) {}
        },
        toggleAttShowHidden() {
            this.attShowHidden = !this.attShowHidden;
            try { localStorage.setItem('attShowHidden', JSON.stringify(this.attShowHidden)); } catch(_) {}
        },
        // サイドバー専用: スレッドを「非表示」に (添付ファイル一覧自体には影響しない)
        async toggleAttHideThread(id) {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const r = await fetch('/api/chats/hide', {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf },
                    body: JSON.stringify({ type: 'thread', id }),
                });
                if (!r.ok) return;
                const nid = Number(id);
                if (!this.attHiddenThreadIds.includes(nid)) this.attHiddenThreadIds.push(nid);
            } catch (_) {}
        },
        async unhideAttThread(id) {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const r = await fetch('/api/chats/unhide', {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf },
                    body: JSON.stringify({ type: 'thread', id }),
                });
                if (!r.ok) return;
                this.attHiddenThreadIds = this.attHiddenThreadIds.filter(x => Number(x) !== Number(id));
            } catch (_) {}
        },
        // スレッドをルームに追加 (リンクボタン)
        openAttAddToRoom(threadId) {
            this.attAddToRoomThreadId = threadId;
            this.attAddToRoomOpen = true;
        },
        async confirmAttAddToRoom(room) {
            if (!this.attAddToRoomThreadId || !room?.id) return;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const r = await fetch(`/api/chat-rooms/${room.id}/threads`, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf },
                    body: JSON.stringify({ thread_id: this.attAddToRoomThreadId }),
                });
                if (!r.ok) {
                    const err = await r.json().catch(() => ({}));
                    alert('ルームに追加できませんでした: ' + (err.error || r.status));
                    return;
                }
                this.attAddToRoomOpen = false;
                this.attAddToRoomThreadId = null;
                // ルーム一覧 / 束ねたスレッドチップを再取得
                this.loadAttRooms();
                this.loadAttRoomBundledThreads();
            } catch (e) {
                alert('通信エラー: ' + (e.message || ''));
            }
        },
        setAttThreadFilter(id) {
            this.attThreadFilterId = id;
            try { localStorage.setItem('attThreadFilterId', String(id)); } catch(_) {}
            try {
                if (id && id !== 'all') {
                    localStorage.setItem('currentThreadId', String(id));
                    // スレッド絞り込み時はルーム選択は外す (チャット/メールと同じ排他挙動)
                    this.attRoomFilterId = 'all';
                    localStorage.setItem('attRoomFilterId', 'all');
                    localStorage.removeItem('currentRoomId');
                } else {
                    localStorage.removeItem('currentThreadId');
                }
            } catch (_) {}
            this.load();
        },

        // ===== ルーム (チャット/メールと同じ概念) =====
        async loadAttRooms() {
            try {
                const res = await fetch('/api/chat-rooms', { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const d = await res.json();
                const rooms = d.rooms || [];
                this.attRoomsShared = rooms.filter(r => !r.is_private);
                this.attRoomsPersonal = rooms.filter(r => r.is_private);
                this.attHiddenRoomIds = (d.hidden_rooms || []).map(Number);
                const allIds = rooms.map(r => String(r.id));
                // 'none' は特殊フィルタなので自動リセットの対象外
                if (this.attRoomFilterId !== 'all'
                    && this.attRoomFilterId !== 'none'
                    && !allIds.includes(String(this.attRoomFilterId))) {
                    this.attRoomFilterId = 'all';
                    try { localStorage.setItem('attRoomFilterId', 'all'); } catch(_) {}
                }
            } catch (_) {}
        },
        async toggleAttHideRoom(id) {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const r = await fetch('/api/chats/hide', {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf },
                    body: JSON.stringify({ type: 'room', id }),
                });
                if (!r.ok) return;
                const nid = Number(id);
                if (!this.attHiddenRoomIds.includes(nid)) this.attHiddenRoomIds.push(nid);
                if (String(this.attRoomFilterId) === String(id)) this.setAttRoomFilter('all');
            } catch (_) {}
        },
        async unhideAttRoom(id) {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const r = await fetch('/api/chats/unhide', {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf },
                    body: JSON.stringify({ type: 'room', id }),
                });
                if (!r.ok) return;
                this.attHiddenRoomIds = this.attHiddenRoomIds.filter(x => Number(x) !== Number(id));
            } catch (_) {}
        },
        setAttRoomFilter(id) {
            // 同じルームを再度クリックしたら "すべて" に切り替えるトグル動作
            if (id !== 'all' && String(this.attRoomFilterId) === String(id)) {
                id = 'all';
            }
            this.attRoomFilterId = id;
            // ルーム選択は常にスレッド選択を解除する (排他: 全体/ルーム/スレッド のいずれか)
            this.attThreadFilterId = 'all';
            try {
                localStorage.setItem('attRoomFilterId', String(id));
                localStorage.setItem('attThreadFilterId', 'all');
                localStorage.removeItem('currentThreadId');
                if (id && id !== 'all') localStorage.setItem('currentRoomId', String(id));
                else localStorage.removeItem('currentRoomId');
            } catch (_) {}
            this.load();
            this.loadAttRoomBundledThreads();
        },
        async loadAttRoomBundledThreads() {
            // 'all' / 'none' は対象ルームが無いので空に
            if (!this.attRoomFilterId
                || this.attRoomFilterId === 'all'
                || this.attRoomFilterId === 'none') {
                this.attRoomBundledThreads = [];
                return;
            }
            try {
                const r = await fetch(`/api/chat-rooms/${this.attRoomFilterId}/threads`, { headers: { Accept:'application/json' } });
                if (!r.ok) { this.attRoomBundledThreads = []; return; }
                const d = await r.json();
                this.attRoomBundledThreads = d.threads || [];
            } catch (_) { this.attRoomBundledThreads = []; }
        },
        // ===== 束ねたスレッド帯: 折りたたみ / 展開 =====
        // 折りたたみ時 (デフォルト) はチップ非表示、ラベル＋件数だけ。
        toggleAttBundleBandExpanded() {
            this.attBundleBandExpanded = !this.attBundleBandExpanded;
            try { localStorage.setItem('attBundleBandExpanded', JSON.stringify(this.attBundleBandExpanded)); } catch (_) {}
        },
        get visibleAttBundleChips() {
            return this.attBundleBandExpanded ? (this.attRoomBundledThreads || []) : [];
        },
        async detachAttRoomThread(threadId) {
            if (!this.attRoomFilterId || this.attRoomFilterId === 'all') return;
            const allRooms = [...(this.attRoomsShared || []), ...(this.attRoomsPersonal || [])];
            const room = allRooms.find(r => String(r.id) === String(this.attRoomFilterId));
            const isShared = room && !room.is_private;
            const bt = (this.attRoomBundledThreads || []).find(b => Number(b.id) === Number(threadId));
            const subject = bt?.subject || '(件名なし)';
            const msg = isShared
                ? '⚠ 共有ルームからスレッドを外します\n\n'
                + 'ルーム名: # ' + (room?.name || '') + '\n'
                + 'スレッド: ' + subject + '\n\n'
                + 'このルームに参加している他のメンバー全員からも、このスレッドが見えなくなります。\n'
                + '本当に外しますか?'
                : '個人ルームからスレッドを外します。\n\nスレッド: ' + subject + '\n\nよろしいですか?';
            if (!confirm(msg)) return;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const r = await fetch(`/api/chat-rooms/${this.attRoomFilterId}/threads/${threadId}`, {
                    method: 'DELETE',
                    headers: { 'Accept':'application/json', 'X-CSRF-TOKEN': csrf },
                });
                if (!r.ok) { alert('紐付け解除に失敗しました'); return; }
                this.attRoomBundledThreads = this.attRoomBundledThreads.filter(t => t.id !== threadId);
                this.load();
            } catch (_) { alert('通信エラー'); }
        },
        toggleAttRoomsSidebar() {
            this.attRoomsCollapsed = !this.attRoomsCollapsed;
            try { localStorage.setItem('attRoomsCollapsed', JSON.stringify(this.attRoomsCollapsed)); } catch(_) {}
        },
        startResizeAttRooms(e) {
            const startX = e.clientX, startW = this.attRoomsWidth;
            this.attRoomsResizing = true;
            document.body.classList.add('att-rooms-resizing');
            const onMove = (me) => {
                const delta = me.clientX - startX;
                this.attRoomsWidth = Math.max(120, Math.min(500, startW + delta));
            };
            const onUp = () => {
                this.attRoomsResizing = false;
                document.body.classList.remove('att-rooms-resizing');
                try { localStorage.setItem('attRoomsWidth', String(this.attRoomsWidth)); } catch(_) {}
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
        openAttCreateRoom(isPrivate = false) {
            this.attNewRoomName = '';
            this.attNewRoomIsPrivate = !!isPrivate;
            this.attCreateRoomOpen = true;
        },
        async submitAttCreateRoom() {
            const name = (this.attNewRoomName || '').trim();
            if (!name || this.attCreatingRoom) return;
            this.attCreatingRoom = true;
            try {
                const r = await fetch('/api/chat-rooms', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ name, is_private: this.attNewRoomIsPrivate }),
                });
                if (!r.ok) { alert('作成失敗'); return; }
                const data = await r.json();
                this.attCreateRoomOpen = false;
                await this.loadAttRooms();
                if (data?.room?.id) this.setAttRoomFilter(String(data.room.id));
            } catch (e) { alert('通信エラー: ' + e.message); }
            finally { this.attCreatingRoom = false; }
        },

        // ====== ルーム作成時の重複サジェスト ======
        // 入力名と部分一致する既存ルームを最大 8 件提示 (共有→個人の順)。
        // 2 文字未満では空 (1 文字だと全件マッチで意味が無い)。
        get attSimilarRoomsForNewName() {
            const q = (this.attNewRoomName || '').trim().toLowerCase();
            if (q.length < 2) return [];
            const allRooms = [...(this.attRoomsShared || []), ...(this.attRoomsPersonal || [])];
            const matches = allRooms.filter(r => (r.name || '').toLowerCase().includes(q));
            return matches.sort((a, b) => {
                const ap = a.is_private ? 1 : 0;
                const bp = b.is_private ? 1 : 0;
                if (ap !== bp) return ap - bp;
                return (a.name || '').localeCompare(b.name || '');
            }).slice(0, 8);
        },
        selectExistingAttRoomFromCreate(room) {
            if (!room) return;
            this.attCreateRoomOpen = false;
            this.attNewRoomName = '';
            this.setAttRoomFilter(String(room.id));
        },

        // ====== ルーム編集 ======
        // 編集ボタン表示の権限判定:
        //   - 共有ルーム → 閲覧者なら誰でも編集 OK
        //   - 個人ルーム → 作成者のみ
        canEditAttRoom(room) {
            if (!room) return false;
            if (!room.is_private) return true;
            return room.created_by_user_id != null
                && Number(room.created_by_user_id) === Number(this.attMyUserId);
        },
        openAttEditRoom(room) {
            if (!room) return;
            this.attEditRoomId        = room.id;
            this.attEditRoomName      = room.name || '';
            this.attEditRoomIsPrivate = !!room.is_private;
            this.attEditRoomIsCreator = (room.created_by_user_id != null
                && Number(room.created_by_user_id) === Number(this.attMyUserId));
            this.attEditRoomOpen      = true;
        },
        async submitAttEditRoom() {
            const name = (this.attEditRoomName || '').trim();
            if (!name || this.attEditingRoom || !this.attEditRoomId) return;
            this.attEditingRoom = true;
            try {
                // 公開範囲は作成者のみ送信 (サーバ側でも 403 で再防御)
                const body = { name };
                if (this.attEditRoomIsCreator) body.is_private = !!this.attEditRoomIsPrivate;
                const r = await fetch(`/api/chat-rooms/${this.attEditRoomId}`, {
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
                this.attEditRoomOpen = false;
                await this.loadAttRooms();
            } catch (e) { alert('通信エラー: ' + e.message); }
            finally { this.attEditingRoom = false; }
        },
        async deleteAttRoom(room, $event) {
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
                if (String(this.attRoomFilterId) === String(room.id)) {
                    this.setAttRoomFilter('all');
                }
                await this.loadAttRooms();
            } catch (e) { alert('通信エラー: ' + e.message); }
        },

        openPreview(att) {
            this.previewFile = att;
            this.previewOpen = true;
            this.selectedAttachmentId = att?.id || null;
        },

        // 添付ファイル一覧のキーボードショートカット.
        // メール画面と同じ感覚で J/K で次・前へ移動し、Enter でプレビューを開く.
        onGlobalKey(e) {
            const tag = (e.target?.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
            if (e.target?.isContentEditable) return;
            // プレビュー / ルーム作成モーダルなどが開いている時は Esc 以外を奪わない
            if (this.previewOpen) {
                if (e.key === 'Escape') { e.preventDefault(); this.previewOpen = false; }
                return;
            }
            if (this.attCreateRoomOpen || this.editRoomOpen) return;

            const ctrlOrCmd = e.ctrlKey || e.metaKey;
            if (ctrlOrCmd && (e.key === 'z' || e.key === 'Z') && !e.shiftKey) {
                // 添付ファイル画面では戻すべき mutate アクションが今のところ無い
                e.preventDefault();
                return;
            }
            if (ctrlOrCmd || e.altKey) return;

            switch (e.key) {
                case 'j': case 'J': e.preventDefault(); this._navAttachment(+1); break;
                case 'k': case 'K': e.preventDefault(); this._navAttachment(-1); break;
                case 'Enter': {
                    if (!this.selectedAttachmentId) return;
                    const att = (this.attachments || []).find(a => a.id === this.selectedAttachmentId);
                    if (att) { e.preventDefault(); this.openPreview(att); }
                    break;
                }
                case 'Escape':
                    if (this.selectedAttachmentId) { e.preventDefault(); this.selectedAttachmentId = null; }
                    break;
                case '/':
                    // 検索ボックスにフォーカス
                    e.preventDefault();
                    const el = document.querySelector('input[placeholder*="ファイル名"]');
                    if (el) el.focus();
                    break;
                case '?':
                    e.preventDefault();
                    if (typeof window.riceShowKeyboardShortcuts === 'function') window.riceShowKeyboardShortcuts();
                    break;
            }
        },
        _navAttachment(dir) {
            const list = this.attachments || [];
            if (list.length === 0) return;
            let idx = list.findIndex(a => a.id === this.selectedAttachmentId);
            if (idx === -1) {
                idx = dir > 0 ? 0 : list.length - 1;
            } else {
                idx = Math.max(0, Math.min(list.length - 1, idx + dir));
            }
            this.selectedAttachmentId = list[idx].id;
            this.$nextTick(() => {
                const el = document.querySelector('[data-att-row-id="' + this.selectedAttachmentId + '"]');
                if (el?.scrollIntoView) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            });
        },

        mimeIcon(mime) {
            if (!mime) return '📎';
            if (mime.startsWith('image/')) return '🖼';
            if (mime === 'application/pdf') return '📕';
            if (mime.includes('word')) return '📝';
            if (mime.includes('excel') || mime.includes('spreadsheet')) return '📊';
            if (mime.includes('zip') || mime.includes('archive')) return '📦';
            if (mime.startsWith('text/')) return '📄';
            return '📎';
        },

        mimeLabel(mime) {
            if (!mime) return '不明';
            const map = {
                'application/pdf': 'PDF',
                'application/msword': 'Word',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word',
                'application/vnd.ms-excel': 'Excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel',
                'text/plain': 'テキスト',
            };
            return map[mime] || (mime.split('/').pop() || '').toUpperCase();
        },
    };
}
</script>
@endsection
