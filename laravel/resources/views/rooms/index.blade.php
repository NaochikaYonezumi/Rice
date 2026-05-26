@extends('layouts.app')
@section('title', 'ルーム管理')
@section('header', 'ルーム管理')

@section('css')
<style>
    /* ===== ダークモード上書き (rooms/index 専用) =====
       本ページは inline style (style="background:#fafafa;" 等) を多用しているため、
       属性セレクタ + !important で上書きする。
       全 inline 指定がここに含まれているとは限らないので、必要に応じて追加する。 */
    html.theme-dark [style*="background:#f9fafb"],
    html.theme-dark [style*="background:#fafafa"],
    html.theme-dark [style*="background: #f9fafb"],
    html.theme-dark [style*="background: #fafafa"] {
        background: var(--rd-bg-hover) !important;
        color: var(--rd-text) !important;
    }
    html.theme-dark [style*="color:#1f2937"],
    html.theme-dark [style*="color: #1f2937"] { color: var(--rd-text) !important; }
    html.theme-dark [style*="color:#374151"],
    html.theme-dark [style*="color: #374151"] { color: var(--rd-text) !important; }
    html.theme-dark [style*="color:#9ca3af"],
    html.theme-dark [style*="color: #9ca3af"] { color: var(--rd-text-dim) !important; }
    html.theme-dark [style*="color:#2563eb"],
    html.theme-dark [style*="color: #2563eb"] { color: #93c5fd !important; }
    html.theme-dark [style*="background:#dbeafe"],
    html.theme-dark [style*="background: #dbeafe"] {
        background: rgba(88,101,242,0.25) !important;
        color: #c7d2fe !important;
    }
    html.theme-dark [style*="background:#3b82f6"],
    html.theme-dark [style*="background: #3b82f6"] {
        background: var(--rd-brand) !important;
    }
    /* コード表示 (パターン) */
    html.theme-dark code {
        background: transparent !important;
        color: var(--rd-text) !important;
    }
    /* テーブル (Bootstrap) */
    html.theme-dark table.table,
    html.theme-dark table.table-sm {
        color: var(--rd-text) !important;
        background-color: transparent !important;
    }
    html.theme-dark table.table thead th {
        background-color: var(--rd-bg-3) !important;
        color: var(--rd-text-mute) !important;
        border-bottom-color: var(--rd-border) !important;
    }
    html.theme-dark table.table tbody tr {
        background-color: var(--rd-bg-2) !important;
    }
    html.theme-dark table.table tbody tr:hover {
        background-color: var(--rd-bg-hover) !important;
    }
    html.theme-dark table.table td,
    html.theme-dark table.table th { border-color: var(--rd-border) !important; }
    /* バッジ (Bootstrap secondary/light) */
    html.theme-dark .badge-secondary {
        background: var(--rd-bg-active) !important;
        color: var(--rd-text) !important;
    }
    html.theme-dark .badge-light {
        background: var(--rd-bg-hover) !important;
        color: var(--rd-text) !important;
    }
    /* アラート (msg トースト) */
    html.theme-dark .alert {
        border-color: var(--rd-border) !important;
        background-color: var(--rd-bg-2) !important;
        color: var(--rd-text) !important;
    }
    html.theme-dark .alert-success { background-color: rgba(87,242,135,0.15) !important; color: #6ee7b7 !important; }
    html.theme-dark .alert-danger  { background-color: rgba(237,66,69,0.15) !important; color: #fca5a5 !important; }

    /* form-control (Bootstrap input) は app.blade.php で覆われるが念のため */
    html.theme-dark .form-control,
    html.theme-dark .form-control-sm {
        background-color: var(--rd-bg-3) !important;
        color: var(--rd-text) !important;
        border-color: var(--rd-border) !important;
    }
</style>
@endsection

@section('content')
{{--
    ルーム管理画面
    ---------------------------------------------------------
    迷惑メール設定 (settings.spam) を参考にしたレイアウト。
    - 左: ルーム一覧 (検索 + 共有/個人タブ + 件数バッジ)
    - 右: 選択中ルームの詳細
        * 編集 (名前 / 公開範囲)
        * 振り分けルール CRUD (タイプ + パターン + 有効/無効 + マッチ数 + 削除)
    全て /api/chat-rooms と /api/chat-rooms/{room}/routing-rules を直叩き.
--}}
<div class="p-4" x-data="roomsAdminApp()" x-init="load()" x-cloak>

    <div class="mb-3 flex items-center gap-3">
        <i class="fas fa-th-large text-2xl" style="color:#2563eb;"></i>
        <div>
            <h2 class="text-lg font-bold" style="color:#1f2937;">ルーム管理</h2>
            <p class="text-xs text-gray-500">ルームごとの振り分けルール (差出人 / ドメイン / 件名 / 宛先) と、名前 / 公開範囲を編集できます。</p>
        </div>
    </div>

    <div class="row" style="gap:0;">
        {{-- 左ペイン: ルーム一覧 --}}
        <div class="col-12 col-lg-4" style="padding-right:8px;">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm" style="overflow:hidden;">
                <div class="px-3 py-2 border-b border-gray-200 d-flex align-items-center gap-2" style="background:#f9fafb;">
                    <i class="fas fa-search text-gray-400 small"></i>
                    <input type="text" x-model="query" placeholder="ルーム名で検索..."
                           class="form-control form-control-sm border-0 bg-transparent"
                           style="box-shadow:none;font-size:13px;">
                    <button type="button" class="btn btn-sm btn-link p-0" @click="load()" title="ルーム一覧を再読込 (※振り分けはこちら → を押してください)">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    {{-- 全ルームでルール再適用 (ぐるぐる) ボタン. ルーム一覧の再読込とは別の役割. --}}
                    <button type="button" class="btn btn-sm btn-link p-0"
                            :disabled="reapplyingAll"
                            @click="reapplyAllRoomsRules()"
                            style="color:#2563eb;"
                            title="全ルームの振り分けルールを既存メールに再適用">
                        <i class="fas" :class="reapplyingAll ? 'fa-spinner fa-spin' : 'fa-magic'"></i>
                    </button>
                </div>
                {{-- タブ: 共有 / 個人 --}}
                <div class="px-3 py-2 border-b border-gray-200 d-flex gap-2" style="background:#fafafa;">
                    <button type="button" class="btn btn-sm" @click="tab = 'shared'"
                            :class="tab === 'shared' ? 'btn-primary' : 'btn-outline-secondary'">
                        <i class="fas fa-globe mr-1"></i>共有 <span class="badge badge-light ml-1" x-text="sharedRooms.length"></span>
                    </button>
                    <button type="button" class="btn btn-sm" @click="tab = 'private'"
                            :class="tab === 'private' ? 'btn-primary' : 'btn-outline-secondary'">
                        <i class="fas fa-lock mr-1"></i>個人 <span class="badge badge-light ml-1" x-text="privateRooms.length"></span>
                    </button>
                </div>
                <div x-show="loading" class="p-4 text-center text-gray-400 small">
                    <i class="fas fa-circle-notch fa-spin mr-2"></i>読込中...
                </div>
                <div x-show="!loading && filteredRooms.length === 0" class="p-4 text-center text-gray-400 small">
                    該当ルームがありません。
                </div>
                <ul class="list-unstyled mb-0" style="max-height:calc(100vh - 280px);overflow-y:auto;">
                    <template x-for="r in filteredRooms" :key="r.id">
                        <li @click="select(r)"
                            :style="(isRoomInSelection(r))
                                ? ('background:#eff6ff;border-left:3px solid #2563eb;padding-left:' + (12 + ((r._depth||0) * 12)) + 'px;')
                                : ('border-left:3px solid transparent;padding-left:' + (12 + ((r._depth||0) * 12)) + 'px;')"
                            style="padding:8px 12px;border-bottom:1px solid #f3f4f6;cursor:pointer;display:flex;align-items:center;gap:8px;">
                            {{-- 子ルームを持っていればフォルダアイコン. それ以外は従来の # / 🔒. --}}
                            <i x-show="r._hasChildren" class="fas fa-folder-open"
                               style="font-size:10px;width:12px;text-align:center;color:#0284c7;"
                               title="子ルームを含むフォルダ"></i>
                            <i x-show="!r._hasChildren && r.is_private" class="fas fa-lock text-purple"
                               style="font-size:10px;width:12px;text-align:center;"></i>
                            <i x-show="!r._hasChildren && !r.is_private" class="fas fa-hashtag text-gray-400"
                               style="font-size:10px;width:12px;text-align:center;"></i>
                            <span class="flex-1 small font-weight-bold" style="color:#1f2937;" x-text="r.name"></span>
                            <span x-show="r.rule_count > 0"
                                  class="badge"
                                  style="background:#dbeafe;color:#1e40af;font-size:9px;font-weight:700;"
                                  :title="r.rule_count + ' 件のルール'"
                                  x-text="r.rule_count"></span>
                            <span x-show="r.received_email_count > 0"
                                  class="badge"
                                  style="background:#3b82f6;color:#fff;font-size:9px;font-weight:700;"
                                  :title="r.received_email_count + ' 件の未対応スレッド (子孫含む)'"
                                  x-text="r.received_email_count"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        {{-- 右ペイン: 選択ルームの詳細 --}}
        <div class="col-12 col-lg-8" style="padding-left:8px;">
            <template x-if="!selected">
                <div class="rounded-xl border border-gray-200 bg-white p-5 text-center" style="color:#9ca3af;">
                    <i class="fas fa-arrow-left text-2xl mb-2 d-block"></i>
                    <p class="small mb-0">左からルームを選択してください。</p>
                </div>
            </template>

            <template x-if="selected">
                <div>
                    {{-- ルーム情報カード --}}
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm mb-3">
                        <div class="px-4 py-3 border-b border-gray-200 d-flex align-items-center gap-2 flex-wrap">
                            <i class="fas" :class="selected.is_private ? 'fa-lock' : 'fa-hashtag'"
                               :style="selected.is_private ? 'color:#7c3aed;' : 'color:#9ca3af;'"></i>
                            <h3 class="mb-0 font-weight-bold" style="color:#1f2937;font-size:16px;" x-text="selected.name"></h3>
                            {{-- 親ルームがあれば「親: 名前」バッジで知らせる. クリックで親へジャンプ. --}}
                            <template x-if="selected.parent_room_id">
                                <button type="button"
                                        @click="(() => { const p = rooms.find(x => Number(x.id) === Number(selected.parent_room_id)); if (p) select(p); })()"
                                        class="badge"
                                        style="background:#f0f9ff;color:#075985;border:1px solid #bae6fd;font-size:10px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;"
                                        :title="'親ルーム: ' + parentRoomNameFor(selected.id) + ' (クリックで切替)'">
                                    <i class="fas fa-folder-tree" style="font-size:9px;"></i>
                                    親: <span x-text="parentRoomNameFor(selected.id)"></span>
                                </button>
                            </template>
                            <span class="badge ml-auto"
                                  :style="selected.is_private ? 'background:#ede9fe;color:#6d28d9;' : 'background:#dbeafe;color:#1d4ed8;'"
                                  x-text="selected.is_private ? '個人ルーム' : '共有ルーム'"></span>
                        </div>
                        <div class="px-4 py-3" style="background:#fafafa;">
                            <div class="row">
                                <div class="col-12 col-md-7">
                                    <label class="text-[11px] font-weight-bold text-gray-500 mb-1 d-block">ルーム名</label>
                                    <input type="text" class="form-control form-control-sm" x-model="editName" maxlength="255">
                                </div>
                                <div class="col-12 col-md-5">
                                    <label class="text-[11px] font-weight-bold text-gray-500 mb-1 d-block">公開範囲</label>
                                    {{-- 仕様: 共有ルームは全員が公開範囲を変更可. 個人ルームは作成者本人のみ.
                                         (= editPublicityAllowed が true の時だけ select を有効化) --}}
                                    {{-- 公開範囲を切り替えた瞬間、現在選択中の親ルームが反対側
                                         (= 共有 ⇔ 個人) なら整合性を失うのでクリアする. --}}
                                    <select class="form-control form-control-sm" x-model.boolean="editIsPrivate"
                                            :disabled="!editPublicityAllowed"
                                            @change="clearParentIfVisibilityMismatch()">
                                        <option :value="false">共有 (全員)</option>
                                        <option :value="true">個人 (自分のみ)</option>
                                    </select>
                                    <p x-show="!editPublicityAllowed" class="text-[10px] text-gray-400 mt-1 mb-0">
                                        ※ 個人ルームの公開範囲は作成者のみ変更可
                                    </p>
                                </div>
                            </div>
                            {{-- 親ルーム (フォルダ構成).
                                 検索付きドロップダウン. 自分自身と子孫は候補から除外.
                                 「保存」ボタンを押した時に編集中の他項目と一緒に POST/PUT される. --}}
                            <div class="row mt-3">
                                <div class="col-12">
                                    <label class="text-[11px] font-weight-bold text-gray-500 mb-1 d-block">
                                        <i class="fas fa-folder-tree mr-1" style="color:#7c3aed;"></i>
                                        親ルーム (任意, 最大 5 階層)
                                    </label>
                                    <div style="position:relative;" x-data="{ open: false }" @click.outside="open = false">
                                        <input type="text" class="form-control form-control-sm"
                                               :value="editParentLabel"
                                               @focus="open = true"
                                               @input="editParentLabel = $event.target.value; open = true"
                                               placeholder="(なし = ルート) ルーム名で検索…"
                                               style="padding-right:32px;">
                                        <button type="button" x-show="editParentId"
                                                @click.stop="editParentId = null; editParentLabel = ''"
                                                style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ca3af;cursor:pointer;font-size:12px;"
                                                title="親を解除 (ルートにする)">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                        <div x-show="open && availableParentRoomsSearched.length > 0" x-cloak
                                             style="position:absolute;left:0;right:0;top:100%;margin-top:4px;max-height:240px;overflow-y:auto;background:#ffffff;border:1px solid #d1d5db;border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,0.12);z-index:50;">
                                            <template x-for="r in availableParentRoomsSearched" :key="'rp-parent-' + r.id">
                                                <button type="button"
                                                        @click.stop="editParentId = r.id; editParentLabel = r.name; open = false"
                                                        style="display:flex;align-items:center;gap:8px;width:100%;text-align:left;padding:6px 10px;background:#fff;border:0;border-bottom:1px solid #f3f4f6;cursor:pointer;font-size:12px;color:#1f2937;"
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
                                    <p class="text-[10px] text-gray-400 mt-1 mb-0">
                                        ここに指定したルームの中 (フォルダ配下) にこのルームが入ります. 空ならルート.
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3 d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-primary"
                                        :disabled="!editName.trim() || saving"
                                        @click="saveRoom()">
                                    <i class="fas fa-save mr-1"></i>
                                    <span x-text="saving ? '保存中...' : '保存'"></span>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger ml-auto"
                                        :disabled="!canDelete"
                                        :title="canDelete ? 'このルームを削除' : '個人ルームは作成者のみ削除できます'"
                                        @click="deleteRoom()">
                                    <i class="fas fa-trash mr-1"></i>削除
                                </button>
                            </div>

                            {{-- ============================================================
                                 他のルームに統合 (マージ).
                                 - 統合元の {スレッド / チャット / 子ルーム / 振り分けルール / Wiki}
                                   が統合先に引き継がれる. 統合元は削除される.
                                 - canDelete と同等の権限 (個人ルームは作成者のみ).
                                 - 公開範囲の整合性 (共有 ⇔ 共有 / 個人 ⇔ 個人) を UI 側でも担保.
                                 ============================================================ --}}
                            <div class="mt-4 pt-3" style="border-top:1px solid #e5e7eb;">
                                <label class="d-flex align-items-center gap-2 mb-1"
                                       style="font-size:12px;font-weight:800;color:#b91c1c;">
                                    <i class="fas fa-compress-alt"></i>
                                    他のルームに統合 (マージ)
                                </label>
                                <p style="font-size:10px;color:#6b7280;line-height:1.55;margin-bottom:8px;">
                                    このルームの <strong>スレッド・チャット・子ルーム・振り分けルール・Wiki</strong> を選択した先に
                                    移し、このルーム自体は削除します。<br>
                                    <span style="color:#dc2626;">⚠ 取り消せません。</span>
                                </p>
                                <div class="d-flex gap-2 align-items-start">
                                    {{-- マージ先サーチドロップダウン. 自身・子孫・公開範囲不一致は候補から外す. --}}
                                    <div style="flex:1;position:relative;"
                                         x-data="{ open: false }" @click.outside="open = false">
                                        <input type="text"
                                               :value="mergeTargetLabel"
                                               @focus="open = true"
                                               @input="mergeTargetSearch = $event.target.value; mergeTargetLabel = $event.target.value; open = true"
                                               placeholder="マージ先のルーム名で検索…"
                                               class="form-control form-control-sm"
                                               :disabled="!canDelete">
                                        <button type="button" x-show="mergeTargetId"
                                                @click.stop="mergeTargetId = null; mergeTargetLabel = ''; mergeTargetSearch = ''"
                                                title="選択解除"
                                                style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:0;color:#9ca3af;cursor:pointer;font-size:11px;">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                        <div x-show="open && availableMergeTargetsSearched.length > 0" x-cloak
                                             style="position:absolute;left:0;right:0;top:100%;margin-top:4px;max-height:220px;overflow-y:auto;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 8px 24px rgba(15,23,42,0.12);z-index:50;">
                                            <template x-for="r in availableMergeTargetsSearched" :key="'merge-target-' + r.id">
                                                <button type="button"
                                                        @click.stop="mergeTargetId = Number(r.id); mergeTargetLabel = r.name; mergeTargetSearch = ''; open = false"
                                                        style="display:flex;align-items:center;gap:8px;width:100%;text-align:left;padding:6px 10px;background:#fff;border:0;border-bottom:1px solid #f3f4f6;cursor:pointer;font-size:11px;color:#1f2937;"
                                                        onmouseover="this.style.backgroundColor='#fef2f2';"
                                                        onmouseout="this.style.backgroundColor='#fff';">
                                                    <i :class="r.is_private ? 'fas fa-lock' : 'fas fa-hashtag'"
                                                       style="font-size:9px;"
                                                       :style="r.is_private ? 'color:#a78bfa;' : 'color:#6b7280;'"></i>
                                                    <span style="flex:1;" x-text="r.name"></span>
                                                    <span style="font-size:9px;color:#9ca3af;font-weight:700;text-transform:uppercase;"
                                                          x-text="r.is_private ? '個人' : '共有'"></span>
                                                </button>
                                            </template>
                                        </div>
                                        <div x-show="open && availableMergeTargetsSearched.length === 0"
                                             style="position:absolute;left:0;right:0;top:100%;margin-top:4px;padding:6px 10px;background:#fff;border:1px solid #d1d5db;border-radius:6px;font-size:10px;color:#6b7280;z-index:50;">
                                            候補がありません (公開範囲が同じルームのみ選択できます)
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-danger"
                                            :disabled="!canDelete || !mergeTargetId || merging"
                                            @click="submitMerge()">
                                        <i class="fas fa-compress-alt mr-1"></i>
                                        <span x-text="merging ? '統合中...' : 'マージ実行'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        {{-- 子ルーム一覧. このルーム配下にある子供を一覧する.
                             クリックで該当ルームの詳細にジャンプ. --}}
                        <template x-if="childRoomsOfSelected.length > 0">
                            <div class="px-4 py-3 border-t border-gray-100" style="background:#f0f9ff;">
                                <label class="text-[11px] font-weight-bold mb-2 d-block" style="color:#075985;">
                                    <i class="fas fa-folder-tree mr-1"></i>
                                    子ルーム (<span x-text="childRoomsOfSelected.length"></span>)
                                </label>
                                <div class="d-flex flex-wrap" style="gap:6px;">
                                    <template x-for="c in childRoomsOfSelected" :key="'child-' + c.id">
                                        <button type="button" @click="select(c)"
                                                class="btn btn-sm"
                                                style="background:#ffffff;border:1px solid #bae6fd;color:#075985;font-size:11px;padding:4px 10px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;"
                                                onmouseover="this.style.background='#e0f2fe';"
                                                onmouseout="this.style.background='#ffffff';">
                                            <i class="fas fa-arrow-right" style="font-size:9px;"></i>
                                            <span x-text="c.name"></span>
                                            <span x-show="c.rule_count > 0"
                                                  style="background:#dbeafe;color:#1e40af;font-size:9px;font-weight:700;border-radius:9999px;padding:1px 6px;margin-left:4px;"
                                                  x-text="c.rule_count + ' 件'"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- 振り分けルール --}}
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="px-4 py-3 border-b border-gray-200 d-flex align-items-center gap-2">
                            <i class="fas fa-filter" style="color:#2563eb;"></i>
                            <h3 class="mb-0 font-weight-bold" style="color:#1f2937;font-size:14px;">振り分けルール</h3>
                            <span class="badge badge-light ml-1" x-text="rules.length"></span>
                            <button type="button" class="btn btn-sm btn-outline-primary ml-auto"
                                    :disabled="rules.length === 0 || reapplyingRules"
                                    @click="reapplyRules()"
                                    title="このルームの全ルールを過去メールに再適用する">
                                <i class="fas" :class="reapplyingRules ? 'fa-spinner fa-spin' : 'fa-history'"></i>
                                <span x-text="reapplyingRules ? '適用中...' : '過去メールに再適用'"></span>
                            </button>
                            <button type="button" class="btn btn-sm btn-link p-0" @click="loadRules()" title="再読込">
                                <i class="fas fa-sync-alt small"></i>
                            </button>
                        </div>

                        {{-- 追加フォーム --}}
                        <div class="px-4 py-3 border-b border-gray-100" style="background:#fafafa;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <p class="small font-weight-bold mb-0" style="color:#374151;">新しいルールを追加</p>
                                <button type="button" class="btn btn-sm btn-link p-0 ml-auto"
                                        @click="builderMode = !builderMode"
                                        :title="builderMode ? '単一条件に戻す' : 'AND/OR 複合条件を作る'">
                                    <i class="fas" :class="builderMode ? 'fa-chevron-up' : 'fa-sitemap'"></i>
                                    <span style="font-size:11px;" x-text="builderMode ? '単一条件に戻す' : 'AND/OR 複合条件'"></span>
                                </button>
                            </div>

                            {{-- (A) 単一条件モード (従来通り) --}}
                            <div x-show="!builderMode" class="d-flex flex-wrap gap-2 align-items-end">
                                <div style="min-width:170px;">
                                    <label class="text-[11px] font-weight-bold text-gray-500 d-block mb-1">タイプ</label>
                                    <select class="form-control form-control-sm" x-model="ruleForm.type">
                                        <option value="any_address">メールアドレス (From/To/Cc 全部)</option>
                                        <option value="any_domain">ドメイン (From/To/Cc 全部)</option>
                                        <option value="from_address">差出人 (From のみ・完全一致)</option>
                                        <option value="from_domain">差出人ドメイン (From のみ)</option>
                                        <option value="subject_contains">件名に含む</option>
                                        <option value="to_contains">宛先 (To/Cc/Bcc) に含む</option>
                                    </select>
                                </div>
                                <div style="flex:1 1 280px;">
                                    <label class="text-[11px] font-weight-bold text-gray-500 d-block mb-1">パターン</label>
                                    <input type="text" class="form-control form-control-sm" x-model="ruleForm.pattern"
                                           :placeholder="rulePlaceholder" maxlength="500"
                                           @keydown.enter.prevent="addRule()">
                                </div>
                                <button type="button" class="btn btn-sm btn-primary"
                                        :disabled="!ruleForm.pattern.trim() || ruleSubmitting"
                                        @click="addRule()">
                                    <i class="fas fa-plus mr-1"></i>
                                    <span x-text="ruleSubmitting ? '追加中...' : '追加'"></span>
                                </button>
                            </div>

                            {{--
                                (B) AND/OR 複合条件モード.
                                ルートグループ (logic=and/or) + 子要素 (条件 or サブグループ) で構築.
                                深さは 2 段まで (ルートグループ → サブグループ → リーフ).
                            --}}
                            <div x-show="builderMode" x-cloak class="border rounded p-2" style="background:#fff;">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="text-[11px] font-weight-bold text-gray-500">全体は</span>
                                    <select class="form-control form-control-sm" style="width:auto;" x-model="builderTree.logic">
                                        <option value="and">AND (すべて一致)</option>
                                        <option value="or">OR (いずれか一致)</option>
                                    </select>
                                    <span class="text-[11px] text-gray-500">で評価</span>
                                </div>

                                <template x-for="(item, idx) in builderTree.items" :key="'b-' + idx">
                                    <div class="border-left pl-2 ml-1 mb-2" style="border-color:#cbd5e1 !important;">
                                        {{-- リーフノード --}}
                                        <div x-show="!item.logic" class="d-flex flex-wrap gap-2 align-items-center">
                                            <select class="form-control form-control-sm" style="width:auto;min-width:160px;" x-model="item.type">
                                                <option value="any_address">アドレス (全部)</option>
                                                <option value="any_domain">ドメイン (全部)</option>
                                                <option value="from_address">差出人 (From)</option>
                                                <option value="from_domain">差出人ドメイン</option>
                                                <option value="subject_contains">件名に含む</option>
                                                <option value="to_contains">宛先に含む</option>
                                            </select>
                                            <input type="text" class="form-control form-control-sm" style="flex:1;min-width:200px;"
                                                   x-model="item.pattern" placeholder="パターン" maxlength="500">
                                            <button type="button" class="btn btn-sm btn-link text-danger" @click="builderRemoveItem(idx)" title="この条件を削除">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>

                                        {{-- サブグループノード (1 段ネストのみ. items はリーフだけ) --}}
                                        <div x-show="item.logic" class="border rounded p-2" style="background:#f9fafb;">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <span class="text-[10px] font-weight-bold text-gray-500">サブグループ:</span>
                                                <select class="form-control form-control-sm" style="width:auto;" x-model="item.logic">
                                                    <option value="and">AND</option>
                                                    <option value="or">OR</option>
                                                </select>
                                                <button type="button" class="btn btn-sm btn-link text-danger ml-auto"
                                                        @click="builderRemoveItem(idx)" title="このサブグループを削除">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <template x-for="(sub, subIdx) in item.items" :key="'b-' + idx + '-' + subIdx">
                                                <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                                    <select class="form-control form-control-sm" style="width:auto;min-width:140px;" x-model="sub.type">
                                                        <option value="any_address">アドレス</option>
                                                        <option value="any_domain">ドメイン</option>
                                                        <option value="from_address">From</option>
                                                        <option value="from_domain">From ドメイン</option>
                                                        <option value="subject_contains">件名に含む</option>
                                                        <option value="to_contains">宛先に含む</option>
                                                    </select>
                                                    <input type="text" class="form-control form-control-sm" style="flex:1;min-width:180px;"
                                                           x-model="sub.pattern" placeholder="パターン" maxlength="500">
                                                    <button type="button" class="btn btn-sm btn-link text-danger"
                                                            @click="builderRemoveSubItem(idx, subIdx)" title="削除">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </template>
                                            <button type="button" class="btn btn-sm btn-outline-secondary mt-1"
                                                    @click="builderAddSubItem(idx)">
                                                <i class="fas fa-plus"></i> サブグループに条件追加
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <div class="d-flex gap-2 mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" @click="builderAddItem()">
                                        <i class="fas fa-plus"></i> 条件追加
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="builderAddGroup()">
                                        <i class="fas fa-folder-plus"></i> サブグループ追加
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary ml-auto"
                                            :disabled="ruleSubmitting || !builderValid"
                                            @click="addRuleFromBuilder()">
                                        <i class="fas fa-check mr-1"></i>
                                        <span x-text="ruleSubmitting ? '追加中...' : 'ルールを追加'"></span>
                                    </button>
                                </div>
                            </div>

                            <p class="text-[10px] text-gray-400 mt-2 mb-0">
                                追加すると、過去メールにもさかのぼって振り分けが適用されます (取り込み件数はトーストで表示)。
                            </p>
                        </div>

                        {{-- 一覧 --}}
                        <div x-show="rulesLoading" class="p-4 text-center text-gray-400 small">
                            <i class="fas fa-circle-notch fa-spin mr-2"></i>読込中...
                        </div>
                        <div x-show="!rulesLoading && rules.length === 0" class="p-4 text-center text-gray-400 small">
                            まだルールがありません。上のフォームから追加してください。
                        </div>
                        <table x-show="!rulesLoading && rules.length > 0" class="table table-sm mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width:5%;">有効</th>
                                    <th style="width:18%;">タイプ</th>
                                    <th>パターン</th>
                                    <th style="width:11%;" class="text-right">マッチ回数</th>
                                    <th style="width:14%;">最終マッチ</th>
                                    <th style="width:8%;" class="text-right">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="r in rules" :key="r.id">
                                    <tr>
                                        <td>
                                            <label style="cursor:pointer;margin:0;">
                                                <input type="checkbox" :checked="r.enabled" @change="toggleRule(r)">
                                            </label>
                                        </td>
                                        <td>
                                            {{-- 単一条件ルール: type_label.
                                                 ネスト条件ルール: AND/OR 結合表示.
                                                 1 リーフのみの conditions は単一条件と等価なので type_label を出す. --}}
                                            <span x-show="!isCompoundRule(r)" class="badge badge-secondary" x-text="r.type_label"></span>
                                            <span x-show="isCompoundRule(r)"
                                                  class="badge"
                                                  :class="(r.logic === 'and') ? 'badge-warning' : 'badge-info'"
                                                  x-text="(r.logic === 'and') ? 'AND 複合' : 'OR 複合'"></span>
                                        </td>
                                        <td>
                                            <code x-show="!isCompoundRule(r)"
                                                  style="font-size:12px;color:#1f2937;background:transparent;word-break:break-all;"
                                                  x-text="r.pattern"></code>
                                            <code x-show="isCompoundRule(r)"
                                                  style="font-size:12px;color:#1f2937;background:transparent;word-break:break-all;"
                                                  x-text="describeConditions(r.conditions)"></code>
                                        </td>
                                        <td class="text-right small" x-text="r.match_count.toLocaleString()"></td>
                                        <td><small x-text="r.last_matched_at || '-'"></small></td>
                                        <td class="text-right">
                                            <button type="button" class="btn btn-sm btn-outline-danger" @click="deleteRule(r)"
                                                    title="削除">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- 通知トースト --}}
    <div x-show="msg" x-cloak x-transition.duration.300ms
         class="position-fixed" style="bottom:24px;right:24px;z-index:9999;">
        <div class="alert" :class="msgError ? 'alert-danger' : 'alert-success'" x-text="msg" style="margin:0;"></div>
    </div>
</div>

<script>
function roomsAdminApp() {
    return {
        loading: false,
        saving: false,
        rules: [],
        rulesLoading: false,
        ruleSubmitting: false,
        reapplyingRules: false,
        reapplyingAll: false,
        rooms: [],          // 全ルーム (rule_count を加味した結果)
        query: '',
        tab: 'shared',
        selected: null,
        editName: '',
        editIsPrivate: false,
        // 親ルーム選択 (検索ドロップダウン用).
        // editParentId: 確定済みの ID, editParentLabel: 入力欄に表示している文字列.
        editParentId: null,
        editParentLabel: '',

        // ===== ルームマージ =====
        // 「他のルームに統合」UI の状態. mergeTargetId は確定済みの ID, Label は表示テキスト.
        // 候補は availableMergeTargetsSearched で動的に絞る (公開範囲一致 + 自身/子孫除外).
        mergeTargetId: null,
        mergeTargetLabel: '',
        mergeTargetSearch: '',
        merging: false,
        ruleForm: { type: 'any_address', pattern: '' },
        // AND/OR 複合条件ビルダーの状態.
        //   builderMode: true でビルダー UI を表示.
        //   builderTree: ルートグループ. items はリーフ ({type, pattern}) または
        //                サブグループ ({logic, items: [リーフ, ...]}) を持つ.
        //   保存時に conditions ツリーとして送信する.
        builderMode: false,
        builderTree: { logic: 'or', items: [ { type: 'from_domain', pattern: '' } ] },
        msg: '', msgError: false,
        csrf: document.querySelector('meta[name="csrf-token"]')?.content,
        myUserId: @json(auth()->id() ?? null),

        // ビルダー操作: 条件 / サブグループの追加・削除.
        builderAddItem() {
            this.builderTree.items.push({ type: 'from_domain', pattern: '' });
        },
        builderAddGroup() {
            // サブグループの既定 logic は親と反対 (典型的な (A AND B) OR C パターンに合わせる).
            const subLogic = this.builderTree.logic === 'and' ? 'or' : 'and';
            this.builderTree.items.push({
                logic: subLogic,
                items: [
                    { type: 'from_domain', pattern: '' },
                    { type: 'from_domain', pattern: '' },
                ],
            });
        },
        builderRemoveItem(idx) {
            this.builderTree.items.splice(idx, 1);
            if (this.builderTree.items.length === 0) {
                this.builderTree.items.push({ type: 'from_domain', pattern: '' });
            }
        },
        builderAddSubItem(idx) {
            const grp = this.builderTree.items[idx];
            if (grp && Array.isArray(grp.items)) {
                grp.items.push({ type: 'from_domain', pattern: '' });
            }
        },
        builderRemoveSubItem(idx, subIdx) {
            const grp = this.builderTree.items[idx];
            if (grp && Array.isArray(grp.items)) {
                grp.items.splice(subIdx, 1);
                if (grp.items.length === 0) {
                    // サブグループが空になったらグループごと削除する.
                    this.builderRemoveItem(idx);
                }
            }
        },
        builderResetTree() {
            this.builderTree = { logic: 'or', items: [ { type: 'from_domain', pattern: '' } ] };
        },
        // ビルダーの内容が「全てのリーフに pattern がある」状態か (送信可否判定).
        get builderValid() {
            if (!this.builderTree?.items || this.builderTree.items.length === 0) return false;
            for (const it of this.builderTree.items) {
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

        // ビルダーから API へ送信. conditions ツリーを正規化して POST.
        async addRuleFromBuilder() {
            if (!this.selected || this.ruleSubmitting || !this.builderValid) return;
            this.ruleSubmitting = true;
            try {
                // pattern を trim して送る (バックエンドの normalizePattern が小文字化等を行う).
                const cleanTree = JSON.parse(JSON.stringify(this.builderTree));
                const cleanItem = (n) => {
                    if (n.logic) {
                        n.items = n.items.map(cleanItem);
                    } else {
                        n.pattern = String(n.pattern || '').trim();
                    }
                    return n;
                };
                cleanTree.items = cleanTree.items.map(cleanItem);
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/routing-rules`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify({ conditions: cleanTree, enabled: true }),
                });
                if (!r.ok) {
                    const e = await r.json().catch(() => ({}));
                    throw new Error(e.error || ('HTTP ' + r.status));
                }
                const d = await r.json();
                this.builderResetTree();
                this.builderMode = false;
                await this.loadRules();
                const n = d.backfilled || 0;
                this.notify(n > 0 ? `複合ルールを追加し、過去メール ${n} 件を取り込みました` : '複合ルールを追加しました');
            } catch (e) { this.notify('追加失敗: ' + e.message, true); }
            finally { this.ruleSubmitting = false; }
        },

        // ルールが「複合条件」(= リーフ 1 つの単純ルールではない) か判定.
        // 単一条件ルールは内部的に { logic:'or', items:[{type,pattern}] } として保存されるので
        // items が 1 個でかつそれがリーフのときは「複合ではない」扱いにする.
        isCompoundRule(rule) {
            if (!rule || !rule.conditions) return false;
            const c = rule.conditions;
            if (!Array.isArray(c.items)) return false;
            if (c.items.length === 1 && !c.items[0]?.logic) return false;
            return true;
        },

        // ネスト条件ツリーを人間可読な文字列に整形 (一覧表のパターン列で使う).
        //   { type:'from_domain', pattern:'foo.com' } → "差出人ドメイン=foo.com"
        //   { logic:'and', items:[a, b] } → "( A AND B )"
        describeConditions(node) {
            if (!node) return '';
            if (node.type) {
                const label = (this.typeShortLabels && this.typeShortLabels[node.type]) || node.type;
                return `${label}=${node.pattern}`;
            }
            const items = (node.items || []).map(n => this.describeConditions(n));
            const sep = ` ${(node.logic || 'or').toUpperCase()} `;
            return items.length > 1 ? `( ${items.join(sep)} )` : (items[0] || '');
        },
        // describeConditions で使う型 → 短いラベル.
        typeShortLabels: {
            any_address: 'アドレス',
            any_domain: 'ドメイン',
            from_address: 'From',
            from_domain: 'Fromドメイン',
            subject_contains: '件名',
            to_contains: '宛先',
        },

        get rulePlaceholder() {
            switch (this.ruleForm.type) {
                case 'any_address':      return '例: info1@example.com (From/To/Cc どこでも)';
                case 'any_domain':       return '例: example.com (From/To/Cc どこでも)';
                case 'from_address':     return '例: suzuki@univ-x.ac.jp (From のみ)';
                case 'from_domain':      return '例: univ-x.ac.jp (From のみ)';
                case 'subject_contains': return '例: 【○○大学様】';
                case 'to_contains':      return '例: support@';
            }
            return '';
        },
        get sharedRooms()  { return this.rooms.filter(r => !r.is_private); },
        get privateRooms() { return this.rooms.filter(r => r.is_private); },
        get filteredRooms() {
            const base = this.tab === 'private' ? this.privateRooms : this.sharedRooms;
            const q = (this.query || '').toLowerCase().trim();
            const filtered = q
                ? base.filter(r => (r.name || '').toLowerCase().includes(q))
                : base;
            // 親子関係: DFS で展開して depth と hasChildren を付与する.
            return this._walkRoomTree(filtered);
        },
        // 階層展開ヘルパ. 親→子→孫の順に並べる. 親が表示集合に無い時は root 直下扱い.
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
                    out.push({ ...r, _depth: depth, _hasChildren: byParent.has(String(r.id)) });
                    if (byParent.has(String(r.id))) dfs(String(r.id), depth + 1);
                }
            };
            dfs('root', 0);
            return out;
        },
        // 選択中ルームの子孫 ID 集合. 親選択時に子も青ハイライト.
        get _selectedRoomDescendants() {
            if (!this.selected) return new Set();
            const id = Number(this.selected.id);
            const byParent = new Map();
            for (const r of (this.rooms || [])) {
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
        isRoomInSelection(r) {
            if (!r) return false;
            return this._selectedRoomDescendants.has(Number(r.id));
        },
        // 親ルーム名を解決 (詳細ペインの「親: ...」表示用)
        parentRoomNameFor(roomId) {
            const r = (this.rooms || []).find(x => Number(x.id) === Number(roomId));
            if (!r || !r.parent_room_id) return '';
            const p = (this.rooms || []).find(x => Number(x.id) === Number(r.parent_room_id));
            return p ? p.name : '';
        },
        // 選択中ルームの 直近の子ルーム一覧 (詳細ペインの「子ルーム」セクション用)
        get childRoomsOfSelected() {
            if (!this.selected) return [];
            const id = Number(this.selected.id);
            return (this.rooms || [])
                .filter(r => Number(r.parent_room_id || 0) === id)
                .sort((a, b) => (a.name || '').localeCompare(b.name || '', 'ja'));
        },
        get isCreator() {
            return this.selected && this.selected.created_by_user_id != null
                && Number(this.selected.created_by_user_id) === Number(this.myUserId);
        },
        // 公開範囲の変更権限:
        //   - 個人ルーム (現在の selected.is_private が true) → 作成者本人のみ
        //   - 共有ルーム → 全員可
        // (= 「他人の個人ルームを勝手に共有化できない」+「共有ルームは誰でも切替できる」)
        get editPublicityAllowed() {
            if (!this.selected) return false;
            if (this.selected.is_private) return this.isCreator;
            return true;
        },
        get canDelete() {
            // 共有ルームは全員削除可。 個人ルームは作成者のみ。
            if (!this.selected) return false;
            if (!this.selected.is_private) return true;
            return this.isCreator;
        },

        notify(text, isErr = false) {
            this.msg = text; this.msgError = isErr;
            setTimeout(() => { this.msg = ''; }, 2500);
        },

        async load() {
            this.loading = true;
            try {
                const r = await fetch('/api/chat-rooms', { headers: { Accept: 'application/json' } });
                if (!r.ok) throw new Error('読込失敗 ' + r.status);
                const d = await r.json();
                const rooms = (d.rooms || []).map(x => ({ ...x, rule_count: 0 }));
                // ルームごとのルール件数を並列でロード (重そうに見えるが軽い API)
                this.rooms = rooms;
                this._loadRuleCounts();  // 非同期 (await しない)
                // 選択を維持: 同じ ID があれば再選択、無ければクリア
                if (this.selected) {
                    const stillThere = rooms.find(x => x.id === this.selected.id);
                    if (stillThere) this.select(stillThere); else this.selected = null;
                }
            } catch (e) {
                this.notify('読込失敗: ' + e.message, true);
            } finally { this.loading = false; }
        },
        // 各ルームの rule_count を並列取得 (バックグラウンド)。
        // 件数だけなのでサイドバーの数字をだんだん埋めるイメージ。失敗してもサイレント。
        async _loadRuleCounts() {
            await Promise.all(this.rooms.map(async (room) => {
                try {
                    const r = await fetch(`/api/chat-rooms/${room.id}/routing-rules`, { headers: { Accept: 'application/json' } });
                    if (!r.ok) return;
                    const d = await r.json();
                    room.rule_count = (d.rules || []).length;
                } catch (_) {}
            }));
        },

        select(room) {
            this.selected = room;
            this.editName = room.name || '';
            this.editIsPrivate = !!room.is_private;
            // 親ルーム選択ドロップダウンの初期値.
            this.editParentId = room.parent_room_id ? Number(room.parent_room_id) : null;
            this.editParentLabel = this.editParentId ? this.parentRoomNameFor(room.id) : '';
            // マージ用ドロップダウンも切り替え時に毎回リセット (前のルームの選択が残らないように).
            this.mergeTargetId = null;
            this.mergeTargetLabel = '';
            this.mergeTargetSearch = '';
            this.loadRules();
        },

        // 親ルーム候補 (自分自身と子孫を除外, 共有/個人の整合性を考慮, 5 階層制限).
        get _availableParentRoomsBase() {
            if (!this.selected) return [];
            const all = this.rooms || [];
            const meId = Number(this.selected.id);
            const meIsPrivate = !!this.editIsPrivate;
            // 子孫 ID 集合
            const childrenMap = new Map();
            for (const r of all) {
                const pid = r.parent_room_id ? Number(r.parent_room_id) : null;
                if (pid !== null) {
                    if (!childrenMap.has(pid)) childrenMap.set(pid, []);
                    childrenMap.get(pid).push(Number(r.id));
                }
            }
            const bad = new Set([meId]);
            const q = [meId];
            while (q.length) {
                const cur = q.shift();
                for (const c of (childrenMap.get(cur) || [])) {
                    if (!bad.has(c)) { bad.add(c); q.push(c); }
                }
            }
            // 自身の subtree_max_depth を算出 (移動後の最大階層チェック用)
            const subtreeDepth = (id) => {
                const cs = childrenMap.get(Number(id)) || [];
                if (cs.length === 0) return 1;
                return 1 + Math.max(...cs.map(c => subtreeDepth(c)));
            };
            const meSubDepth = subtreeDepth(meId);
            // ルームの depth を求めるヘルパ
            const depthOf = (id) => {
                let d = 1;
                let cur = all.find(x => Number(x.id) === Number(id));
                const seen = new Set([Number(id)]);
                while (cur && cur.parent_room_id) {
                    const p = Number(cur.parent_room_id);
                    if (seen.has(p)) break;
                    seen.add(p);
                    d++;
                    if (d > 100) break;
                    cur = all.find(x => Number(x.id) === p);
                }
                return d;
            };
            const MAX_DEPTH = 5;
            return all
                .filter(r => !bad.has(Number(r.id)))
                // 公開範囲の一致を要求 (要望: 共有ルームは共有ルームのみ / 個人ルームは個人ルームのみ親選択可).
                //   meIsPrivate を権威にする (= 編集中ルームの is_private が現在の状態).
                //   親 (r) の is_private と一致しないものは候補から外す.
                .filter(r => !!r.is_private === meIsPrivate)
                // 階層制限: 自身の subtree が新親の下に入った時に MAX_DEPTH を越えないこと.
                .filter(r => (depthOf(r.id) + meSubDepth) <= MAX_DEPTH);
        },
        get availableParentRoomsSearched() {
            const base = this._availableParentRoomsBase;
            const q = (this.editParentLabel || '').toLowerCase().trim();
            if (!q) return base.slice(0, 30);
            return base.filter(r => (r.name || '').toLowerCase().includes(q)).slice(0, 30);
        },

        // ===== マージ先候補 =====
        // - 編集中ルーム (selected) と公開範囲が一致するもののみ
        // - 自身と子孫は除外 (ループ防止)
        // - 検索文字列 (mergeTargetSearch) で名前部分一致絞り込み
        // - 上限 30 件
        get availableMergeTargetsSearched() {
            if (!this.selected) return [];
            const all = this.rooms || [];
            const meId = Number(this.selected.id);
            const meIsPrivate = !!this.selected.is_private;
            // 子孫 ID 集合 (自身も含めて除外)
            const childrenMap = new Map();
            for (const r of all) {
                const pid = r.parent_room_id ? Number(r.parent_room_id) : null;
                if (pid !== null) {
                    if (!childrenMap.has(pid)) childrenMap.set(pid, []);
                    childrenMap.get(pid).push(Number(r.id));
                }
            }
            const bad = new Set([meId]);
            const q = [meId];
            while (q.length) {
                const cur = q.shift();
                for (const c of (childrenMap.get(cur) || [])) {
                    if (!bad.has(c)) { bad.add(c); q.push(c); }
                }
            }
            const ql = (this.mergeTargetSearch || '').toLowerCase().trim();
            return all
                .filter(r => !bad.has(Number(r.id)))
                // 公開範囲一致 (要望 #25 で親選択を制限したのと同じ整合性ルールに合わせる).
                .filter(r => !!r.is_private === meIsPrivate)
                .filter(r => !ql || (r.name || '').toLowerCase().includes(ql))
                .slice(0, 30);
        },

        // マージ実行. POST /api/chat-rooms/{room}/merge.
        // サーバ側 (ChatRoomController::mergeRoom) が同じくループ防止 / 公開範囲整合 / 階層上限を再検査する.
        async submitMerge() {
            if (!this.selected || !this.mergeTargetId || this.merging) return;
            if (!this.canDelete) { this.notify('このルームは権限が無いためマージできません', true); return; }
            const targetId = Number(this.mergeTargetId);
            const target = (this.rooms || []).find(r => Number(r.id) === targetId);
            const targetName = target?.name || '(不明)';
            if (!confirm(
                `「${this.selected.name}」を「${targetName}」に統合します。\n\n` +
                '中身 (スレッド・チャット・子ルーム・振り分けルール・Wiki) は引き継がれ、\n' +
                'このルーム自体は削除されます。取り消せません。\n\nよろしいですか?'
            )) return;
            this.merging = true;
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/merge`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify({ target_room_id: targetId }),
                });
                if (!r.ok) {
                    const e = await r.json().catch(() => ({}));
                    throw new Error(e.error || ('HTTP ' + r.status));
                }
                this.notify(`「${targetName}」に統合しました`);
                // ローカル一覧から消し、選択もマージ先へ切り替える.
                const sourceId = this.selected.id;
                this.rooms = this.rooms.filter(x => Number(x.id) !== Number(sourceId));
                this.mergeTargetId = null;
                this.mergeTargetLabel = '';
                this.mergeTargetSearch = '';
                // マージ先の rule_count などは server 側で増えているので再ロードする.
                await this.load();
                const newSel = (this.rooms || []).find(x => Number(x.id) === targetId);
                if (newSel) this.select(newSel);
                else this.selected = null;
            } catch (e) {
                this.notify('マージ失敗: ' + e.message, true);
            } finally {
                this.merging = false;
            }
        },

        // 公開範囲を編集中に切り替えた時、選択済み親ルームが不整合 (共有⇔個人) なら自動クリア.
        // ユーザに「親候補が消えるけど親が残ったまま」という宙ぶらりん状態を見せないため.
        clearParentIfVisibilityMismatch() {
            if (!this.editParentId) return;
            const p = (this.rooms || []).find(x => Number(x.id) === Number(this.editParentId));
            if (!p) {
                // 候補にいないなら一律クリア (ロード待ちのレースに備えた防御).
                this.editParentId = null;
                this.editParentLabel = '';
                return;
            }
            if (!!p.is_private !== !!this.editIsPrivate) {
                this.editParentId = null;
                this.editParentLabel = '';
                try {
                    this.notify(this.editIsPrivate
                        ? '個人ルームの親は個人ルームのみ選べるため、親選択をクリアしました'
                        : '共有ルームの親は共有ルームのみ選べるため、親選択をクリアしました');
                } catch (_) {}
            }
        },

        async loadRules() {
            if (!this.selected) return;
            this.rulesLoading = true;
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/routing-rules`, { headers: { Accept: 'application/json' } });
                if (!r.ok) throw new Error('読込失敗 ' + r.status);
                const d = await r.json();
                this.rules = d.rules || [];
                // サイドバーの件数も更新
                const ri = this.rooms.findIndex(x => x.id === this.selected.id);
                if (ri >= 0) this.rooms[ri].rule_count = this.rules.length;
            } catch (e) {
                this.notify('ルール読込失敗: ' + e.message, true);
            } finally { this.rulesLoading = false; }
        },

        async saveRoom() {
            if (!this.selected || !this.editName.trim()) return;
            this.saving = true;
            try {
                const body = { name: this.editName.trim() };
                // 共有ルームは誰でも公開範囲変更可. 個人ルームは作成者のみ.
                if (this.editPublicityAllowed) body.is_private = !!this.editIsPrivate;
                // 1) 名前 / 公開範囲を保存
                const r = await fetch(`/api/chat-rooms/${this.selected.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify(body),
                });
                if (!r.ok) {
                    const e = await r.json().catch(() => ({}));
                    throw new Error(e.error || ('HTTP ' + r.status));
                }

                // 2) 親ルーム変更があれば別エンドポイントで保存.
                //    PUT の payload にも parent_room_id を入れたいところだが、
                //    ChatRoomController::update は parent を見ないので /move を叩く.
                const newParentId = this.editParentId ? Number(this.editParentId) : null;
                const oldParentId = this.selected.parent_room_id ? Number(this.selected.parent_room_id) : null;
                if (newParentId !== oldParentId) {
                    const mvRes = await fetch(`/api/chat-rooms/${this.selected.id}/move`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
                        body: JSON.stringify({ parent_room_id: newParentId }),
                    });
                    if (!mvRes.ok) {
                        const e = await mvRes.json().catch(() => ({}));
                        throw new Error(e.error || ('親ルーム変更失敗 HTTP ' + mvRes.status));
                    }
                }

                this.notify('保存しました');
                // 反映: ローカルのルーム名/is_private/parent_room_id を更新
                Object.assign(this.selected, {
                    name: body.name,
                    is_private: !!this.editIsPrivate,
                    parent_room_id: newParentId,
                });
                const ri = this.rooms.findIndex(x => x.id === this.selected.id);
                if (ri >= 0) this.rooms[ri] = { ...this.rooms[ri], ...this.selected };
            } catch (e) {
                this.notify('保存失敗: ' + e.message, true);
            } finally { this.saving = false; }
        },
        async deleteRoom() {
            if (!this.selected || !this.canDelete) return;
            if (!confirm(`「${this.selected.name}」を削除しますか?\n紐付け済みスレッドはルーム解除されますが、メール自体は残ります。`)) return;
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}`, {
                    method: 'DELETE',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
                });
                if (!r.ok) {
                    const e = await r.json().catch(() => ({}));
                    throw new Error(e.error || ('HTTP ' + r.status));
                }
                this.notify('削除しました');
                this.rooms = this.rooms.filter(x => x.id !== this.selected.id);
                this.selected = null;
            } catch (e) { this.notify('削除失敗: ' + e.message, true); }
        },

        async addRule() {
            const pattern = (this.ruleForm.pattern || '').trim();
            if (!this.selected || !pattern || this.ruleSubmitting) return;
            this.ruleSubmitting = true;
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/routing-rules`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify({ type: this.ruleForm.type, pattern, enabled: true }),
                });
                if (!r.ok) {
                    const e = await r.json().catch(() => ({}));
                    throw new Error(e.error || ('HTTP ' + r.status));
                }
                const d = await r.json();
                this.ruleForm.pattern = '';
                await this.loadRules();
                const n = d.backfilled || 0;
                this.notify(n > 0 ? `ルールを追加し、過去メール ${n} 件を取り込みました` : 'ルールを追加しました');
            } catch (e) { this.notify('追加失敗: ' + e.message, true); }
            finally { this.ruleSubmitting = false; }
        },
        async toggleRule(rule) {
            const next = !rule.enabled;
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/routing-rules/${rule.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify({ enabled: next }),
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                rule.enabled = next;
                this.notify(next ? '有効化しました' : '無効化しました');
            } catch (e) { this.notify('切替失敗: ' + e.message, true); }
        },
        async deleteRule(rule) {
            if (!confirm(`ルール「${rule.type_label}: ${rule.pattern}」を削除しますか?\n(過去に振り分け済みのメールはそのまま残ります)`)) return;
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/routing-rules/${rule.id}`, {
                    method: 'DELETE',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                this.rules = this.rules.filter(x => x.id !== rule.id);
                this.notify('削除しました');
                const ri = this.rooms.findIndex(x => x.id === this.selected.id);
                if (ri >= 0) this.rooms[ri].rule_count = this.rules.length;
            } catch (e) { this.notify('削除失敗: ' + e.message, true); }
        },
        // 全ルームでルールを既存メールに再適用 (左ペイン上部の魔法ボタンから).
        async reapplyAllRoomsRules() {
            if (this.reapplyingAll) return;
            this.reapplyingAll = true;
            try {
                const r = await fetch('/api/chat-rooms/_/reapply-all-rules', {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const d = await r.json();
                const rooms = d.processed_rooms || 0;
                const total = d.total_newly_added || 0;
                this.notify(total > 0
                    ? `全ルーム再適用: ${rooms} ルームを処理し、${total} スレッドを新規取り込み`
                    : `全ルーム再適用完了: ${rooms} ルームを処理しました (新規取り込みなし)`);
                // ルーム件数バッジ / ルール統計を更新
                await this.load();
                if (this.selected) await this.loadRules();
            } catch (e) {
                this.notify('再適用に失敗: ' + e.message, true);
            } finally {
                this.reapplyingAll = false;
            }
        },

        // ルームの全ルールを過去メールへ手動で再適用 (= マッチするメールをまとめて bundle).
        // ルール追加直後は自動で 1 回適用されるが、TO_CONTAINS の挙動を Cc/Bcc にも広げた
        // 直後など、過去ぶんを取り直したい場面のためのエスケープハッチ.
        async reapplyRules() {
            if (!this.selected || this.reapplyingRules || this.rules.length === 0) return;
            this.reapplyingRules = true;
            try {
                const r = await fetch(`/api/chat-rooms/${this.selected.id}/routing-rules/_/reapply`, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const d = await r.json();
                const n = d.newly_added || 0;
                this.notify(n > 0
                    ? `過去メールへ再適用: ${n} スレッドを新規取り込み`
                    : '過去メールへ再適用しました (新規取り込みなし)');
                // ルールの統計 (マッチ件数) は変わるので再読込
                await this.loadRules();
            } catch (e) {
                this.notify('再適用に失敗: ' + e.message, true);
            } finally {
                this.reapplyingRules = false;
            }
        },
    };
}
</script>
@endsection
