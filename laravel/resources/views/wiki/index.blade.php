@extends('layouts.app')
@section('title', 'Wiki')

@section('content')
<div class="flex h-full" x-data="wikiPageApp(@js($rooms), @js($myId))" x-cloak>

    {{-- 左: ルーム選択サイドバー (検索 + 新規 + ホバーで編集/削除) --}}
    <aside class="shrink-0 flex flex-col border-r border-gray-200 bg-white"
           style="width:260px;min-width:200px;">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2 shrink-0" style="background:#f0f9ff;">
            <i class="fas fa-book" style="color:#0ea5e9;"></i>
            <h2 class="font-bold text-sm flex-1" style="color:#0c4a6e;">ルーム Wiki</h2>
            {{-- 新規ルーム作成ボタン (モーダルを開く) --}}
            <button type="button" @click="openCreateRoomModal()"
                    class="inline-flex items-center justify-center w-6 h-6 rounded text-white"
                    style="background:#0ea5e9;"
                    title="新規ルームを作成">
                <i class="fas fa-plus text-[10px]"></i>
            </button>
        </div>
        <div class="px-3 py-2 border-b border-gray-100 shrink-0">
            <div style="position:relative;">
                <i class="fas fa-search" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:10px;"></i>
                <input type="text" x-model="filterQuery"
                       placeholder="ルーム検索..."
                       style="padding-left:24px;"
                       class="w-full text-xs border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300">
                <button x-show="filterQuery" @click="filterQuery = ''" type="button"
                        style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ca3af;padding:0;font-size:10px;"
                        title="検索クリア">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto">
            <template x-if="filteredRooms().length === 0">
                <p class="text-center text-xs text-gray-400 py-6"
                   x-text="filterQuery ? '検索結果なし' : 'ルームがありません'"></p>
            </template>
            <template x-for="room in filteredRooms()" :key="room.id">
                {{-- 行はグループ。 row 自体は select、右端のアイコンはホバー時表示。
                     親子関係: 階層深さに応じて字下げ. 親選択時は子孫も青ハイライト. --}}
                <div class="group relative flex items-center"
                     :class="isRoomInWikiSelection(room)
                        ? 'bg-sky-50 border-l-2 border-l-sky-500'
                        : 'hover:bg-gray-50 border-l-2 border-l-transparent'">
                    <button type="button" @click="selectRoom(room)"
                            :class="isRoomInWikiSelection(room) ? 'text-sky-900' : 'text-gray-700'"
                            :style="'padding-left:' + (16 + ((room._depth||0) * 12)) + 'px;'"
                            class="flex-1 min-w-0 text-left py-2 pr-4 text-xs font-bold flex items-center gap-2 transition-colors">
                        <i x-show="room.is_private" class="fas fa-lock text-[9px] shrink-0" style="color:#a78bfa;" title="個人ルーム"></i>
                        <i x-show="!room.is_private && !room._hasChildren" class="fas fa-hashtag text-[10px] text-gray-400 shrink-0"></i>
                        <i x-show="!room.is_private && room._hasChildren" class="fas fa-folder-open text-[10px] shrink-0" style="color:#0284c7;" title="子ルームを含むフォルダ"></i>
                        <span class="truncate" x-text="room.name"></span>
                    </button>
                    {{-- ホバー時のアクションボタン群。 個人ルームの編集/削除は作成者のみ表示 --}}
                    <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button type="button"
                                x-show="canEditRoom(room)"
                                @click.stop="openEditRoomModal(room)"
                                class="inline-flex items-center justify-center w-6 h-6 rounded text-gray-400 hover:text-blue-600 hover:bg-blue-50"
                                title="ルームを編集">
                            <i class="fas fa-pen text-[10px]"></i>
                        </button>
                        <button type="button"
                                x-show="canDeleteRoom(room)"
                                @click.stop="deleteRoomConfirm(room)"
                                class="inline-flex items-center justify-center w-6 h-6 rounded text-gray-400 hover:text-red-600 hover:bg-red-50"
                                title="ルームを削除">
                            <i class="fas fa-trash text-[10px]"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </aside>

    {{-- 右: 選択中ルームの Wiki カード一覧 (画面全体) --}}
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden" style="background:#f0f9ff;">
        {{-- ルーム未選択 --}}
        <template x-if="!selectedRoomId">
            <div class="flex-1 flex items-center justify-center text-gray-400 flex-col gap-3">
                <i class="fas fa-book fa-3x" style="opacity:0.25;"></i>
                <p class="text-sm">左のリストから Wiki を見るルームを選択してください</p>
            </div>
        </template>

        {{-- ルーム選択中 --}}
        <template x-if="selectedRoomId">
            <div class="flex-1 flex flex-col min-h-0">
                {{-- ヘッダ --}}
                <div class="shrink-0 bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-3">
                    <h1 class="font-bold text-xs flex-1 min-w-0 truncate" style="color:#0c4a6e;"
                        x-text="selectedRoomName"></h1>
                    <span class="text-xs text-gray-400" x-text="cards.length + ' 枚'"></span>
                    {{--
                        重要: Alpine の :style 文字列バインディングは静的 style 属性を上書きする。
                        通常時 (adding=false) に :style="''" を返すと背景色が消えて
                        白背景＋白文字でボタンが見えなくなるため、背景色も :style 側にまとめる。
                    --}}
                    <button @click="addCard()" :disabled="adding"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors text-white shrink-0"
                            :style="'background:#0ea5e9;' + (adding ? 'opacity:0.5;cursor:not-allowed;' : '')">
                        <i class="fas" :class="adding ? 'fa-spinner fa-spin' : 'fa-plus'"></i>
                        <span>新規カード</span>
                    </button>
                </div>

                {{-- カードグリッド (画面全体に展開) --}}
                <div class="flex-1 overflow-y-auto p-6 wiki-card-grid">
                    <template x-if="loading">
                        <p class="text-center py-10 text-gray-400 text-sm col-span-full">
                            <i class="fas fa-circle-notch fa-spin mr-1"></i>読み込み中...
                        </p>
                    </template>
                    <template x-if="!loading && cards.length === 0">
                        <div class="col-span-full flex flex-col items-center text-gray-400 py-12 gap-2">
                            <i class="fas fa-sticky-note fa-2x" style="opacity:0.3;"></i>
                            <p class="text-sm">まだ Wiki がありません</p>
                            <p class="text-xs">右上の「新規カード」から書き始めましょう</p>
                        </div>
                    </template>
                    <template x-for="card in cards" :key="card.id">
                        <div class="wiki-card"
                             :style="(card.is_own === false)
                                ? 'background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:12px 14px;box-shadow:0 2px 4px rgba(0,0,0,0.05);display:flex;flex-direction:column;gap:6px;min-height:180px;'
                                : 'background:#fefce8;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;box-shadow:0 2px 4px rgba(0,0,0,0.05);display:flex;flex-direction:column;gap:6px;min-height:180px;'">
                            {{-- 子ルーム由来の Wiki カードに「出元バッジ」を出す.
                                 編集も自由にできる (該当ルームの ID で PUT する) が、視覚的に
                                 「ここはこのルームのカードではない」と分かるよう色味も変える. --}}
                            <div x-show="card.is_own === false"
                                 style="display:inline-flex;align-items:center;gap:4px;background:#0284c7;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:9999px;width:fit-content;">
                                <i class="fas fa-folder-tree" style="font-size:9px;"></i>
                                <span>子ルーム:</span>
                                <span x-text="card.room_name || ''"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="text" x-model="card.title"
                                       @input.debounce.700ms="autoSave(card)"
                                       @blur="autoSave(card, true)"
                                       placeholder="タイトル (任意)"
                                       class="flex-1 bg-transparent border-none outline-none text-sm font-extrabold"
                                       :style="(card.is_own === false) ? 'color:#075985;padding:2px 4px;' : 'color:#713f12;padding:2px 4px;'">
                                <span x-show="card._saving" class="text-[10px] text-gray-400"><i class="fas fa-spinner fa-spin"></i></span>
                                <span x-show="!card._saving && card._saved" class="text-[10px]" style="color:#059669;"><i class="fas fa-check"></i></span>
                                <button @click="enterFullscreen(card.id)" title="全画面で開く"
                                        class="text-[12px] hover:opacity-70"
                                        :style="(card.is_own === false) ? 'color:#0369a1;background:none;border:none;padding:2px 4px;cursor:pointer;' : 'color:#a16207;background:none;border:none;padding:2px 4px;cursor:pointer;'">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button @click="removeCard(card)" title="削除"
                                        class="text-[12px] hover:opacity-70" style="color:#dc2626;background:none;border:none;padding:2px 4px;cursor:pointer;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <textarea x-model="card.content"
                                      @input.debounce.700ms="autoSave(card)"
                                      @blur="autoSave(card, true)"
                                      :ref="'ta-' + card.id"
                                      placeholder="メモを入力..."
                                      class="flex-1 w-full bg-transparent border-none outline-none resize-none"
                                      style="font-family:inherit;font-size:13px;line-height:1.6;color:#1f2937;padding:2px 4px;min-height:120px;"></textarea>
                            <span class="text-[10px] text-gray-400 mt-1"
                                  x-text="card.updated_at ? '最終更新: ' + card.updated_at : ''"></span>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </main>

    {{-- 新規ルーム作成モーダル (名前・公開範囲・部分一致サジェスト付き) --}}
    <div x-show="createRoomOpen" x-cloak
         style="position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;padding:24px;">
        <div @click="createRoomOpen = false"
             style="position:absolute;inset:0;background:rgba(0,0,0,0.45);"></div>
        <div style="position:relative;background:#fff;border-radius:12px;width:100%;max-width:440px;box-shadow:0 24px 60px rgba(0,0,0,0.3);overflow:hidden;">
            <div style="background:#0c4a6e;color:#fff;padding:12px 16px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-plus-circle"></i>
                <strong style="flex:1;font-size:13px;">新規ルームを作成</strong>
                <button @click="createRoomOpen = false"
                        style="background:none;border:none;color:#fff;padding:4px;font-size:14px;"
                        title="閉じる"><i class="fas fa-times"></i></button>
            </div>
            <div style="padding:14px 18px;">
                <label style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;">ルーム名</label>
                <input type="text" x-model="newRoomName" @keydown.enter="submitCreateRoom()"
                       placeholder="例: 案件A 進行管理"
                       style="margin-top:4px;width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:7px 10px;font-size:13px;outline:none;">

                {{-- 部分一致候補 (重複作成防止)。 chats/index.blade.php と同じ方針。
                     2 文字以上の入力で、名前に substring 一致するルームを最大 8 件提示。 --}}
                <template x-if="similarRoomsForNewName.length > 0">
                    <div style="margin-top:10px;border:1px solid #fde68a;background:#fffbeb;border-radius:6px;padding:8px 10px;">
                        <p style="margin:0 0 4px;font-size:10px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.04em;">
                            <i class="fas fa-info-circle" style="margin-right:4px;"></i>
                            似た名前のルームがあります (クリックで開く)
                        </p>
                        <div style="display:flex;flex-direction:column;gap:2px;max-height:140px;overflow-y:auto;">
                            <template x-for="r in similarRoomsForNewName" :key="'sim-' + r.id">
                                <button type="button"
                                        @click="selectExistingRoomFromCreate(r)"
                                        style="display:flex;align-items:center;gap:6px;width:100%;text-align:left;background:#fff;border:1px solid #fde68a;border-radius:4px;padding:5px 8px;cursor:pointer;font-size:12px;color:#1f2937;transition:background .12s;"
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

                <label style="display:block;margin-top:14px;font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;">公開範囲</label>
                <div style="display:flex;gap:8px;margin-top:6px;">
                    <label style="flex:1;display:flex;align-items:flex-start;gap:8px;padding:8px 10px;border:1px solid #e5e7eb;border-radius:6px;cursor:pointer;"
                           :style="!newRoomIsPrivate ? 'background:#eff6ff;border-color:#bfdbfe;' : ''">
                        <input type="radio" :checked="!newRoomIsPrivate" @change="newRoomIsPrivate = false" style="margin-top:2px;">
                        <div>
                            <p style="margin:0;font-weight:700;font-size:12px;color:#1d4ed8;"><i class="fas fa-globe" style="margin-right:4px;"></i>全員共有</p>
                            <p style="margin:2px 0 0;font-size:10px;color:#6b7280;">他のユーザーにも表示</p>
                        </div>
                    </label>
                    <label style="flex:1;display:flex;align-items:flex-start;gap:8px;padding:8px 10px;border:1px solid #e5e7eb;border-radius:6px;cursor:pointer;"
                           :style="newRoomIsPrivate ? 'background:#ede9fe;border-color:#ddd6fe;' : ''">
                        <input type="radio" :checked="newRoomIsPrivate" @change="newRoomIsPrivate = true" style="margin-top:2px;">
                        <div>
                            <p style="margin:0;font-weight:700;font-size:12px;color:#6d28d9;"><i class="fas fa-lock" style="margin-right:4px;"></i>個人用</p>
                            <p style="margin:2px 0 0;font-size:10px;color:#6b7280;">あなただけに表示</p>
                        </div>
                    </label>
                </div>
            </div>
            <div style="padding:10px 14px;border-top:1px solid #f3f4f6;display:flex;justify-content:flex-end;gap:8px;">
                <button @click="createRoomOpen = false" type="button"
                        style="background:#fff;border:1px solid #e5e7eb;color:#4b5563;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;">キャンセル</button>
                <button @click="submitCreateRoom()" type="button"
                        :disabled="!newRoomName?.trim() || creatingRoom"
                        :style="'background:#0ea5e9;color:#fff;border:none;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:700;'
                             + ((!newRoomName?.trim() || creatingRoom) ? 'opacity:0.5;cursor:not-allowed;' : '')">
                    <i class="fas" :class="creatingRoom ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                    作成
                </button>
            </div>
        </div>
    </div>

    {{-- ルーム編集モーダル (名前 + 公開範囲)。chats/index と同じ規約:
         個人ルームでも作成者のみが触れる仕組みは canEditRoom で UI 段階で守り、
         サーバ側でも ChatRoomController::update が再判定する。 --}}
    <div x-show="editRoomOpen" x-cloak
         style="position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;padding:24px;">
        <div @click="editRoomOpen = false"
             style="position:absolute;inset:0;background:rgba(0,0,0,0.45);"></div>
        <div style="position:relative;background:#fff;border-radius:12px;width:100%;max-width:440px;box-shadow:0 24px 60px rgba(0,0,0,0.3);overflow:hidden;">
            <div style="background:#0c4a6e;color:#fff;padding:12px 16px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-pen"></i>
                <strong style="flex:1;font-size:13px;">ルームを編集</strong>
                <button @click="editRoomOpen = false"
                        style="background:none;border:none;color:#fff;padding:4px;font-size:14px;"
                        title="閉じる"><i class="fas fa-times"></i></button>
            </div>
            <div style="padding:14px 18px;">
                <label style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;">ルーム名</label>
                <input type="text" x-model="editRoomName" @keydown.enter="submitEditRoom()"
                       placeholder="ルーム名"
                       style="margin-top:4px;width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:7px 10px;font-size:13px;outline:none;">

                {{-- 公開範囲は「自分が作成者」の時だけ変更可能 (chats/index と同様)。
                     非作成者には「共有ルームの名前変更のみ可」を案内する。 --}}
                <template x-if="editRoomIsCreator">
                    <div>
                        <label style="display:block;margin-top:14px;font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;">公開範囲</label>
                        <div style="display:flex;gap:8px;margin-top:6px;">
                            <label style="flex:1;display:flex;align-items:flex-start;gap:8px;padding:8px 10px;border:1px solid #e5e7eb;border-radius:6px;cursor:pointer;"
                                   :style="!editRoomIsPrivate ? 'background:#eff6ff;border-color:#bfdbfe;' : ''">
                                <input type="radio" :checked="!editRoomIsPrivate" @change="editRoomIsPrivate = false" style="margin-top:2px;">
                                <div>
                                    <p style="margin:0;font-weight:700;font-size:12px;color:#1d4ed8;"><i class="fas fa-globe" style="margin-right:4px;"></i>全員共有</p>
                                </div>
                            </label>
                            <label style="flex:1;display:flex;align-items:flex-start;gap:8px;padding:8px 10px;border:1px solid #e5e7eb;border-radius:6px;cursor:pointer;"
                                   :style="editRoomIsPrivate ? 'background:#ede9fe;border-color:#ddd6fe;' : ''">
                                <input type="radio" :checked="editRoomIsPrivate" @change="editRoomIsPrivate = true" style="margin-top:2px;">
                                <div>
                                    <p style="margin:0;font-weight:700;font-size:12px;color:#6d28d9;"><i class="fas fa-lock" style="margin-right:4px;"></i>個人用</p>
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
                <button @click="editRoomOpen = false" type="button"
                        style="background:#fff;border:1px solid #e5e7eb;color:#4b5563;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;">キャンセル</button>
                <button @click="submitEditRoom()" type="button"
                        :disabled="!editRoomName?.trim() || editingRoom"
                        :style="'background:#0ea5e9;color:#fff;border:none;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:700;'
                             + ((!editRoomName?.trim() || editingRoom) ? 'opacity:0.5;cursor:not-allowed;' : '')">
                    <i class="fas" :class="editingRoom ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                    保存
                </button>
            </div>
        </div>
    </div>

    {{-- 全画面モーダル --}}
    <div x-show="fullscreenId" x-cloak
         style="position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;padding:24px;">
        <template x-for="card in cards" :key="'fs-' + card.id">
            <div x-show="fullscreenId === card.id"
                 style="background:#ffffff;border-radius:12px;width:100%;max-width:1400px;height:100%;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,0.5);overflow:hidden;">
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
                </div>
                <textarea x-model="card.content"
                          @input.debounce.700ms="autoSave(card)"
                          @blur="autoSave(card, true)"
                          placeholder="メモを入力..."
                          style="flex:1;width:100%;background:#ffffff;border:none;padding:20px 28px;font-size:15px;line-height:1.85;color:#1f2937;outline:none;resize:none;font-family:inherit;"></textarea>
            </div>
        </template>
    </div>
</div>

<style>
.wiki-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px;
    align-content: start;
}
</style>

<script>
function wikiPageApp(initialRooms, currentUserId) {
    return {
        rooms: initialRooms || [],
        // ログインユーザーID。「自分が作成者か」を判定して個人ルームの編集/削除を制御する。
        myId: currentUserId ?? null,
        filterQuery: '',
        selectedRoomId: null,
        selectedRoomName: '',
        loading: false,
        adding: false,
        cards: [],
        fullscreenId: null,
        // ====== ルーム CRUD モーダル state ======
        // 新規作成モーダル
        createRoomOpen: false,
        newRoomName: '',
        newRoomIsPrivate: false,
        creatingRoom: false,
        // 編集モーダル
        editRoomOpen: false,
        editRoomId: null,
        editRoomName: '',
        editRoomIsPrivate: false,
        editRoomIsCreator: false,
        editingRoom: false,

        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        init() {
            // URL ?room= から自動選択。なければ localStorage.currentRoomId、それも無ければ未選択。
            let roomId = null;
            try {
                const p = new URL(window.location.href).searchParams.get('room');
                if (p && /^\d+$/.test(p)) roomId = parseInt(p, 10);
                if (!roomId) {
                    const lf = localStorage.getItem('currentRoomId');
                    if (lf && /^\d+$/.test(lf)) roomId = parseInt(lf, 10);
                }
            } catch (_) {}
            if (roomId) {
                const r = this.rooms.find(x => Number(x.id) === Number(roomId));
                if (r) this.selectRoom(r);
            }
        },

        filteredRooms() {
            const q = this.filterQuery.trim().toLowerCase();
            const base = q
                ? this.rooms.filter(r => (r.name || '').toLowerCase().includes(q))
                : this.rooms;
            return this._walkRoomTree(base);
        },
        // 親子関係を考慮したリスト展開. 親 → 子 → 孫の順に並べ depth を付与する.
        // 子も検索結果に居ない場合は親と一緒に root 直下扱いになる (孤立を避ける).
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
        // 選択中ルームの子孫 ID 集合. 親選択時に子も active 表示するため.
        get _selectedRoomDescendants() {
            const id = this.selectedRoomId ? Number(this.selectedRoomId) : null;
            if (!id) return new Set();
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
        isRoomInWikiSelection(r) {
            if (!r) return false;
            return this._selectedRoomDescendants.has(Number(r.id));
        },

        // ====== ルーム CRUD ======

        // 一覧の再読み込み。 /api/chat-rooms は created_by_user_id 含む payload を返す。
        async reloadRooms() {
            try {
                const r = await fetch('/api/chat-rooms', { headers: { Accept: 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                this.rooms = (d.rooms || []).map(x => ({
                    id: x.id,
                    name: x.name,
                    is_private: !!x.is_private,
                    created_by_user_id: x.created_by_user_id ?? null,
                }));
            } catch (_) {}
        },

        // 操作権限の判定:
        //  - 編集ボタン: 共有ルームは全員に表示 / 個人ルームは作成者のみに表示
        //  - 削除ボタン: 同じく。 削除は元々「個人は作成者のみ」とサーバ側で再判定する。
        canEditRoom(room) {
            if (!room) return false;
            if (!room.is_private) return true;
            return room.created_by_user_id != null && Number(room.created_by_user_id) === Number(this.myId);
        },
        canDeleteRoom(room) {
            // 編集と同じ条件で出す (サーバ側でも再判定される)。
            return this.canEditRoom(room);
        },

        // ----- 新規作成 -----
        openCreateRoomModal() {
            this.newRoomName = '';
            this.newRoomIsPrivate = false;
            this.createRoomOpen = true;
        },

        // 名前の部分一致サジェスト (重複作成防止)。
        // 2 文字以上の入力でだけ動作。共有→個人の順で並べて 8 件まで。
        get similarRoomsForNewName() {
            const q = (this.newRoomName || '').trim().toLowerCase();
            if (q.length < 2) return [];
            const matches = (this.rooms || []).filter(r =>
                (r.name || '').toLowerCase().includes(q)
            );
            return matches.sort((a, b) => {
                const ap = a.is_private ? 1 : 0;
                const bp = b.is_private ? 1 : 0;
                if (ap !== bp) return ap - bp;
                return (a.name || '').localeCompare(b.name || '');
            }).slice(0, 8);
        },

        // サジェストクリック: モーダルを閉じてそのルームを開く
        selectExistingRoomFromCreate(room) {
            if (!room) return;
            this.createRoomOpen = false;
            this.newRoomName = '';
            this.selectRoom(room);
        },

        async submitCreateRoom() {
            const name = (this.newRoomName || '').trim();
            if (!name || this.creatingRoom) return;
            this.creatingRoom = true;
            try {
                const r = await fetch('/api/chat-rooms', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ name, is_private: !!this.newRoomIsPrivate }),
                });
                if (!r.ok) {
                    let msg = 'ルームの作成に失敗しました';
                    try { msg = (await r.json()).error || msg; } catch (_) {}
                    alert(msg);
                    return;
                }
                const data = await r.json();
                this.createRoomOpen = false;
                await this.reloadRooms();
                // 作成したルームを自動選択して Wiki カード一覧をロード
                const created = this.rooms.find(x => Number(x.id) === Number(data.room?.id));
                if (created) this.selectRoom(created);
            } catch (e) {
                alert('通信エラー: ' + (e.message || ''));
            } finally {
                this.creatingRoom = false;
            }
        },

        // ----- 編集 -----
        openEditRoomModal(room) {
            if (!room) return;
            this.editRoomId        = room.id;
            this.editRoomName      = room.name || '';
            this.editRoomIsPrivate = !!room.is_private;
            this.editRoomIsCreator = (room.created_by_user_id != null && Number(room.created_by_user_id) === Number(this.myId));
            this.editRoomOpen      = true;
        },

        async submitEditRoom() {
            const name = (this.editRoomName || '').trim();
            if (!name || this.editingRoom || !this.editRoomId) return;
            this.editingRoom = true;
            try {
                // 公開範囲は作成者だけが送る (サーバ側でも 403 で再防御される)
                const body = { name };
                if (this.editRoomIsCreator) body.is_private = !!this.editRoomIsPrivate;
                const r = await fetch(`/api/chat-rooms/${this.editRoomId}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
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
                // ローカルの rooms と選択中ルーム名も即時更新 (再フェッチで最終整合)
                const updated = data.room || {};
                const idx = this.rooms.findIndex(x => Number(x.id) === Number(this.editRoomId));
                if (idx >= 0) {
                    this.rooms[idx] = { ...this.rooms[idx], ...updated };
                }
                if (Number(this.selectedRoomId) === Number(this.editRoomId) && updated.name) {
                    this.selectedRoomName = updated.name;
                }
                await this.reloadRooms();
            } catch (e) {
                alert('通信エラー: ' + (e.message || ''));
            } finally {
                this.editingRoom = false;
            }
        },

        // ----- 削除 -----
        async deleteRoomConfirm(room) {
            if (!room) return;
            if (!confirm(`「${room.name}」を削除します。\nこのルームの Wiki / チャット / 紐付けがすべて失われます。\n本当によろしいですか?`)) return;
            try {
                const r = await fetch(`/api/chat-rooms/${room.id}`, {
                    method: 'DELETE',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                });
                if (!r.ok) {
                    let msg = 'ルームの削除に失敗しました';
                    try { msg = (await r.json()).error || msg; } catch (_) {}
                    alert(msg);
                    return;
                }
                // 削除されたルームを開いていたら選択解除
                if (Number(this.selectedRoomId) === Number(room.id)) {
                    this.selectedRoomId = null;
                    this.selectedRoomName = '';
                    this.cards = [];
                    try {
                        localStorage.removeItem('currentRoomId');
                        const u = new URL(window.location.href);
                        u.searchParams.delete('room');
                        window.history.replaceState(null, '', u.toString());
                    } catch (_) {}
                }
                await this.reloadRooms();
            } catch (e) {
                alert('通信エラー: ' + (e.message || ''));
            }
        },

        async selectRoom(room) {
            if (!room) return;
            this.selectedRoomId = room.id;
            this.selectedRoomName = room.name;
            try { localStorage.setItem('currentRoomId', String(room.id)); } catch (_) {}
            // URL を ?room=ID に置き換える (戻る/共有用)
            try {
                const u = new URL(window.location.href);
                u.searchParams.set('room', String(room.id));
                window.history.replaceState(null, '', u.toString());
            } catch (_) {}
            await this.loadCards();
        },

        async loadCards() {
            if (!this.selectedRoomId) return;
            this.loading = true;
            this.cards = [];
            try {
                const r = await fetch(`/api/chat-rooms/${this.selectedRoomId}/wikis`, { headers: { Accept: 'application/json' } });
                if (r.ok) {
                    const d = await r.json();
                    this.cards = (d.wikis || []).map(w => this._wrapCard(w));
                }
            } catch (_) {}
            this.loading = false;
        },

        _wrapCard(w) {
            return {
                id: w.id,
                title: w.title || '',
                content: w.content || '',
                sort_order: w.sort_order ?? 0,
                updated_at: w.updated_at || '',
                _saving: false,
                _saved: false,
                _lastSavedTitle: w.title || '',
                _lastSavedContent: w.content || '',
            };
        },

        async addCard() {
            if (!this.selectedRoomId || this.adding) return;
            this.adding = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const r = await fetch(`/api/chat-rooms/${this.selectedRoomId}/wikis`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ title: '', content: '' }),
                });
                if (!r.ok) { console.warn('Wiki カード追加失敗 status=' + r.status); return; }
                const d = await r.json();
                if (d.wiki) {
                    const wrapped = this._wrapCard(d.wiki);
                    this.cards.push(wrapped);
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

        async autoSave(card, force = false) {
            if (!card || !this.selectedRoomId) return;
            const prev = JSON.stringify({ t: card._lastSavedTitle || '', c: card._lastSavedContent || '' });
            const curr = JSON.stringify({ t: card.title || '', c: card.content || '' });
            if (prev === curr) return;
            if (card._saving) return;
            card._saving = true;
            card._saved = false;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                // 階層対応: 子ルーム由来のカードは「そのルーム ID」で PUT する.
                // (= 表示中のルーム ID では弾かれるため)
                const ownerRoomId = card.room_id || this.selectedRoomId;
                const r = await fetch(`/api/chat-rooms/${ownerRoomId}/wikis/${card.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ title: card.title || '', content: card.content || '' }),
                });
                if (!r.ok) return;
                const d = await r.json();
                if (d.wiki) {
                    card.updated_at = d.wiki.updated_at || '';
                    card._lastSavedTitle = card.title || '';
                    card._lastSavedContent = card.content || '';
                    card._saved = true;
                    setTimeout(() => { card._saved = false; }, 1500);
                }
            } catch (_) {} finally { card._saving = false; }
        },

        async removeCard(card) {
            if (!this.selectedRoomId || !card) return;
            const label = card.title ? `「${card.title}」` : 'このカード';
            // 子ルームから読み込んだカードを削除しようとしている場合は注意喚起.
            const extraNote = (card.is_own === false && card.room_name)
                ? `\n\n注意: このカードは子ルーム「${card.room_name}」のものです.\n削除するとそのルームでも消えます.`
                : '';
            if (!confirm(`${label} を削除しますか？\n(復元できません)` + extraNote)) return;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                // 階層対応: 子ルーム由来のカードはその親 ID で DELETE する.
                const ownerRoomId = card.room_id || this.selectedRoomId;
                const r = await fetch(`/api/chat-rooms/${ownerRoomId}/wikis/${card.id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                });
                if (!r.ok) { console.warn('Wiki カード削除失敗 status=' + r.status); return; }
                this.cards = this.cards.filter(c => c.id !== card.id);
                if (this.fullscreenId === card.id) this.fullscreenId = null;
            } catch (e) { console.warn('Wiki カード削除通信エラー: ' + (e?.message || '')); }
        },

        enterFullscreen(id) { this.fullscreenId = id; },
        exitFullscreen()    { this.fullscreenId = null; },
    };
}
</script>
@endsection
