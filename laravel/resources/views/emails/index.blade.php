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
</style>
@endsection

@section('content')
<div class="flex bg-white overflow-hidden text-gray-800 font-sans" style="height:calc(100vh - 3.5rem)" x-data="emailApp()" x-init="init()" x-cloak>

    {{-- メインコンテンツエリア --}}
    <div class="flex flex-1 min-w-0 overflow-hidden">

        {{-- スレッド一覧 --}}
        <div class="flex flex-col flex-shrink-0 overflow-hidden bg-white border-r border-gray-200 relative z-20 shadow-sm"
             :style="'width:' + threadWidth + 'px'">

            {{-- 操作ヘッダー (2段構成) --}}
            <div class="shrink-0 px-4 py-3 border-b border-gray-200 bg-white flex flex-col gap-2 relative">

                {{-- 1段目: 担当者フィルター --}}
                <div class="flex items-center gap-2 px-1 min-w-0">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest shrink-0">担当者:</label>
                    <select @change="setAssigneeFilter($event.target.value)"
                            class="flex-1 bg-gray-50 border-0 rounded-lg px-3 py-1.5 text-[10px] font-bold text-gray-700 focus:ring-2 focus:ring-blue-100 outline-none shadow-inner cursor-pointer min-w-0">
                        <option value="all">全員を表示</option>
                        <option value="none">未設定</option>
                        <template x-for="user in users" :key="user.id">
                            <option :value="user.id" :selected="assigneeFilterId == user.id" x-text="user.name"></option>
                        </template>
                    </select>
                </div>

                {{-- 2段目: 左=同期+全表示トグル / 右=ピン+新規作成 --}}
                <div class="flex items-center justify-between gap-2">

                    {{-- 左寄せ: 同期 + 全表示トグル --}}
                    <div class="flex items-center gap-2">
                        {{-- 同期ボタン --}}
                        <button @click="fetchEmails()"
                                class="h-9 w-9 inline-flex items-center justify-center rounded-lg text-gray-400 hover:text-blue-600 hover:bg-gray-50 transition-all"
                                title="一覧を更新">
                            <i class="fas fa-sync-alt text-sm" :class="fetching ? 'animate-spin text-blue-600' : ''"></i>
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

                    {{-- 右寄せ: ピン + 新規作成 --}}
                    <div class="flex items-center gap-2">
                        {{-- ピン留めボタン --}}
                        <button @click="togglePinnedOnly()"
                                :class="pinnedOnlyMode ? 'bg-amber-100 text-amber-600 border-amber-200' : 'bg-white text-gray-400 border-gray-200 hover:bg-gray-50 hover:text-amber-600'"
                                class="h-9 w-9 inline-flex items-center justify-center rounded-lg border transition-all"
                                title="ピン留めのみ表示">
                            <i class="fas fa-thumbtack text-sm"></i>
                        </button>

                        {{-- 新規作成ボタン --}}
                        <button @click="openCompose()"
                                class="compose-btn h-9 w-9 inline-flex items-center justify-center rounded-lg transition-all"
                                style="background-color:#2563eb;color:#ffffff;border:1px solid #2563eb;box-shadow:0 1px 2px rgba(0,0,0,.06);"
                                title="新規作成 (新しいウィンドウ)">
                            <i class="fas fa-edit text-sm" style="color:#ffffff"></i>
                        </button>
                    </div>
                </div>
            </div>

            {{-- 複数選択アクションバー --}}
            <template x-if="selectionMode">
                <div class="absolute inset-x-0 top-0 bg-blue-600 text-white flex justify-between items-center shadow-lg animate-in slide-in-from-top duration-300 px-4 z-[100] h-[128px]">
                    <span class="text-[10px] font-black uppercase tracking-widest" x-text="selectedThreadIds.length + ' 件選択中'"></span>
                    <div class="flex items-center gap-1.5 flex-wrap justify-end">
                        <button @click="updateSelectedStatus('completed')" class="bg-white/20 hover:bg-white/30 text-white px-2 py-1.5 rounded-lg text-[9px] font-black border border-white/30 uppercase tracking-widest transition-all">完了</button>
                        <button @click="updateSelectedStatus('no_action')" class="bg-white/20 hover:bg-white/30 text-white px-2 py-1.5 rounded-lg text-[9px] font-black border border-white/30 uppercase tracking-widest transition-all">対応不要</button>
                        <button @click="updateSelectedStatus('hold')" class="bg-white/20 hover:bg-white/30 text-white px-2 py-1.5 rounded-lg text-[9px] font-black border border-white/30 uppercase tracking-widest transition-all">保留</button>
                        <button @click="updateSelectedStatus('inbox')" class="bg-white/20 hover:bg-white/30 text-white px-2 py-1.5 rounded-lg text-[9px] font-black border border-white/30 uppercase tracking-widest transition-all">未対応</button>
                        <button @click="batchPinSelected(true)" class="bg-white/20 hover:bg-white/30 text-white px-2 py-1.5 rounded-lg text-[9px] font-black border border-white/30 uppercase tracking-widest transition-all"><i class="fas fa-thumbtack"></i> ピン留</button>
                        <button @click="batchPinSelected(false)" class="bg-white/20 hover:bg-white/30 text-white px-2 py-1.5 rounded-lg text-[9px] font-black border border-white/30 uppercase tracking-widest transition-all"><i class="fas fa-unlink"></i> ピン外</button>
                        <button @click="batchDeleteSelected()" class="bg-red-500/80 hover:bg-red-600 text-white px-2 py-1.5 rounded-lg text-[9px] font-black border border-red-400/50 uppercase tracking-widest transition-all"><i class="fas fa-trash"></i></button>
                        <button @click="mergeSelected()" x-show="selectedThreadIds.length > 1" class="bg-amber-500/80 hover:bg-amber-600 text-white px-2 py-1.5 rounded-lg text-[9px] font-black border border-amber-400/50 uppercase tracking-widest transition-all"><i class="fas fa-object-group"></i> マージ</button>
                        <div class="w-px h-4 bg-white/20 mx-1"></div>
                        <button @click="cancelSelection()" class="text-white/70 hover:text-white text-[10px] font-black px-2 transition-all">キャンセル</button>
                    </div>
                </div>
            </template>

            {{-- ステータスタブ --}}
            <div class="shrink-0 px-3 py-2 border-b border-gray-200 bg-gray-50 flex items-center gap-2">
                <div class="flex items-center gap-1 bg-gray-200/50 p-1 rounded-xl shadow-inner flex-1 overflow-hidden">
                    <template x-for="tab in ['inbox', 'hold', 'completed', 'no_action', 'pending']">
                        <button @click="setLeftTab(tab)"
                                :class="leftTab === tab ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-800'"
                                class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all truncate"
                                x-text="statusLabels[tab]"></button>
                    </template>
                </div>
                <button @click="toggleSort()" class="p-2 text-gray-400 hover:text-blue-600">
                    <i class="fas" :class="sortOrder === 'desc' ? 'fa-sort-amount-down' : 'fa-sort-amount-up'"></i>
                </button>
            </div>

            {{-- 仮想スクロールリスト --}}
            <div class="flex-1 min-h-0 overflow-y-auto bg-white custom-scrollbar relative" id="email-list-container" @scroll.passive="handleScroll()">
                <div :style="'height: ' + totalListHeight + 'px; position: relative;'">
                    <div :style="'transform: translateY(' + listPaddingTop + 'px)'">
                        <template x-for="thread in visibleThreads" :key="thread.id">
                            <div @mousedown="startLongPress(thread, $event)"
                                 @mouseup="cancelLongPress()"
                                 @mouseleave="cancelLongPress()"
                                 @click="if(!isLongPressing){ selectionMode ? toggleSelection(thread) : loadThread(thread.id) }"
                                 class="email-item group/row w-full cursor-pointer border-b border-gray-100 hover:bg-blue-50 transition-all duration-200 thread-list-row relative"
                                 :style="'height: ' + virtualScroll.rowHeight + 'px'"
                                 :class="selectedThreadId === thread.id ? 'bg-blue-50 ring-1 ring-inset ring-blue-200' : (selectedThreadIds.includes(thread.id) ? 'bg-blue-50/50' : '')">

                                {{-- ホバー時に表示する削除ボタン (個別削除) --}}
                                <button @click.stop="deleteThreadById(thread.id, thread.subject)"
                                        x-show="!selectionMode"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 z-10 w-8 h-8 inline-flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:text-red-500 hover:border-red-200 hover:bg-red-50 shadow-sm opacity-0 group-hover/row:opacity-100 transition-all"
                                        title="このスレッドを削除">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>

                                <div class="px-5 py-2 flex flex-col justify-center h-full gap-1">
                                    {{-- 1段目: 送信者 (日付は別行に分離) --}}
                                    <div class="flex items-center gap-2 min-w-0">
                                        <template x-if="selectionMode">
                                            <input type="checkbox" class="w-3.5 h-3.5 text-blue-600 border-gray-300 rounded focus:ring-blue-500 shrink-0"
                                                   :checked="selectedThreadIds.includes(thread.id)" @click.stop="toggleSelection(thread)">
                                        </template>
                                        <i x-show="thread.is_pinned" class="fas fa-thumbtack text-amber-500 text-[10px] shrink-0"></i>
                                        <i x-show="thread.thread_merges_count > 0" class="fas fa-object-group text-blue-500 text-[10px] shrink-0" title="マージ済み"></i>
                                        <span class="text-[12px] font-bold text-gray-900 truncate" x-text="thread.latest_email?.from_label || '不明な送信者'"></span>
                                    </div>

                                    {{-- 2段目: 件名 (フル幅・最大2行) --}}
                                    <div class="text-[11px] text-gray-700 font-medium leading-snug break-words"
                                         style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"
                                         x-text="thread.subject"></div>

                                    {{-- 3段目: 日付 + メタデータ (ステータス / 担当者 / タグ) --}}
                                    <div class="flex items-center gap-1.5 flex-wrap min-h-[18px]">
                                        <span class="text-[10px] text-gray-400 font-medium shrink-0 inline-flex items-center gap-1">
                                            <i class="fas fa-clock text-[8px]"></i>
                                            <span x-text="thread.last_email_at"></span>
                                        </span>

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

                                        {{-- タグ (複数) --}}
                                        <template x-for="tag in (thread.tags || [])" :key="tag">
                                            <span class="bg-purple-50 text-purple-700 border border-purple-200 px-2 py-0.5 rounded text-[9px] 2xl:text-[10px] font-black inline-flex items-center gap-1">
                                                <i class="fas fa-tag text-purple-400 text-[8px]"></i>
                                                <span x-text="tag"></span>
                                            </span>
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
                                <button @click="updateThreadStatus(selectedThread, 'completed')" title="完了にする"
                                    class="icon-btn bg-green-50 text-green-600 hover:bg-green-600 hover:text-white">
                                    <i class="fas fa-check-double text-xs"></i>
                                </button>
                                <button @click="updateThreadStatus(selectedThread, 'no_action')" title="対応不要にする"
                                    class="icon-btn bg-gray-50 text-gray-600 hover:bg-gray-500 hover:text-white">
                                    <i class="fas fa-ban text-xs"></i>
                                </button>
                                <button @click="if(threadEmails.length > 0) openReplyForEmail(threadEmails[0])" title="返信 (新しいウィンドウ)"
                                    class="icon-btn bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white">
                                    <i class="fas fa-reply text-xs"></i>
                                </button>
                                <button @click="if(threadEmails.length > 0) openReplyForEmail(threadEmails[0], true)" title="全員に返信 (新しいウィンドウ)"
                                    class="icon-btn text-blue-400 border border-blue-100 hover:bg-blue-50 hover:text-blue-600">
                                    <i class="fas fa-reply-all text-xs"></i>
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
                                        <div class="border-t border-gray-100 my-1"></div>
                                        <button @click="deleteSelectedThread(); open = false"
                                                class="w-full text-left px-4 py-2.5 text-[11px] font-black text-red-600 hover:bg-red-50 flex items-center gap-3 transition-colors uppercase tracking-widest">
                                            <i class="fas fa-trash text-red-500"></i> 削除
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
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
                                <h2 class="text-sm font-extrabold text-gray-800 min-w-0"
                                    style="word-break:break-word;overflow-wrap:anywhere;line-height:1.4;"
                                    x-text="selectedThread?.subject"></h2>
                                {{-- AI要約ボタン (件名の直後) --}}
                                <button type="button" @click="openThreadSummary()"
                                        :disabled="!threadEmails.length"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold transition-colors shrink-0 disabled:opacity-40 disabled:cursor-not-allowed"
                                        style="background-color:#4f46e5;color:#ffffff;box-shadow:0 1px 3px rgba(79,70,229,0.25);"
                                        onmouseover="if(!this.disabled)this.style.backgroundColor='#4338ca';"
                                        onmouseout="if(!this.disabled)this.style.backgroundColor='#4f46e5';"
                                        title="このスレッドの全メールを AI で要約">
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
                        </div>
                    </div>
                </div>

                <div class="flex-1 flex min-h-0 relative bg-gray-50/30">
                    <div class="flex-1 min-w-0 overflow-y-auto p-10 custom-scrollbar">

                        {{-- スレッド表示 --}}
                        <template x-if="selectedThread">
                            <div class="max-w-4xl 2xl:max-w-6xl mx-auto space-y-6">

                                {{-- マージ情報表示 --}}
                                <template x-for="merge in threadMerges" :key="merge.id">
                                    <div class="bg-amber-50 border border-amber-100 p-4 rounded-2xl flex items-center justify-between animate-in slide-in-from-top duration-300">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-amber-100 rounded-xl flex items-center justify-center text-amber-600 shadow-inner"><i class="fas fa-object-group fa-xs"></i></div>
                                            <div>
                                                <p class="text-[10px] font-black text-amber-900 uppercase tracking-widest">マージ済みスレッド</p>
                                                <p class="text-sm font-bold text-amber-800" x-text="merge.source_subject"></p>
                                            </div>
                                        </div>
                                        <button @click="unmergeThread(merge.id)" class="text-[10px] font-black bg-white text-amber-600 border border-amber-200 px-4 py-2 rounded-xl hover:bg-amber-600 hover:text-white transition-all shadow-sm uppercase tracking-widest">
                                            解除
                                        </button>
                                    </div>
                                </template>

                                {{-- 各メール表示: 件名→宛先→日付の縦積み (アイコン無し) --}}
                                <template x-for="email in threadEmails" :key="email.id">
                                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow transition-shadow group">
                                        <div class="px-4 py-3 cursor-pointer hover:bg-gray-50/50 transition-colors" @click="toggleEmailExpand(email.id)">

                                            {{-- 1段目: 件名 + 返信/全員/開閉 --}}
                                            <div class="flex items-start gap-2 min-w-0">
                                                <h3 class="text-sm font-bold text-gray-900 flex-1 min-w-0"
                                                    style="word-break:break-word;overflow-wrap:anywhere;line-height:1.4;"
                                                    x-text="email.subject || selectedThread?.subject || '(件名なし)'"></h3>
                                                <button @click.stop="openReplyForEmail(email)"
                                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold transition-colors shrink-0"
                                                        style="background-color:#eff6ff;color:#2563eb;"
                                                        onmouseover="this.style.backgroundColor='#2563eb';this.style.color='#ffffff';"
                                                        onmouseout="this.style.backgroundColor='#eff6ff';this.style.color='#2563eb';"
                                                        title="返信">
                                                    <i class="fas fa-reply"></i> 返信
                                                </button>
                                                <button @click.stop="openReplyForEmail(email, true)"
                                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold transition-colors shrink-0"
                                                        style="background-color:#ffffff;color:#2563eb;border:1px solid #dbeafe;"
                                                        onmouseover="this.style.backgroundColor='#eff6ff';"
                                                        onmouseout="this.style.backgroundColor='#ffffff';"
                                                        title="全員に返信">
                                                    <i class="fas fa-reply-all"></i> 全員
                                                </button>
                                                {{-- このメールをナレッジに登録 --}}
                                                <button @click.stop="openKnowledgeRegister(email)"
                                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold transition-colors shrink-0"
                                                        style="background-color:#ffffff;color:#475569;border:1px solid #e2e8f0;"
                                                        onmouseover="this.style.backgroundColor='#f1f5f9';this.style.color='#0f172a';"
                                                        onmouseout="this.style.backgroundColor='#ffffff';this.style.color='#475569';"
                                                        title="このメールをナレッジに登録">
                                                    <i class="fas fa-book"></i> ナレッジ
                                                </button>
                                                {{-- このメールに紐付くチャット (per-email) --}}
                                                <button @click.stop="openEmailChat(email)"
                                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold transition-colors shrink-0"
                                                        style="background-color:#ffffff;color:#7c3aed;border:1px solid #ddd6fe;"
                                                        onmouseover="this.style.backgroundColor='#7c3aed';this.style.color='#ffffff';"
                                                        onmouseout="this.style.backgroundColor='#ffffff';this.style.color='#7c3aed';"
                                                        title="このメールに関するチャット">
                                                    <i class="fas fa-comment-dots"></i> チャット
                                                </button>
                                                <i class="fas fa-chevron-down text-gray-300 group-hover:text-blue-500 transition-all shrink-0 text-[10px] mt-1"
                                                   :class="expandedEmailIds.includes(email.id) ? 'rotate-180' : ''"></i>
                                            </div>

                                            {{-- 2段目: From / To / Cc (件名の下にメールアドレス) --}}
                                            <div class="mt-1.5 space-y-0.5 text-[11px] text-gray-600">
                                                <div class="truncate" :title="(email.from_label || '') + ' <' + (email.from_address || '') + '>'">
                                                    <span class="text-gray-400 mr-1 inline-block w-7">From:</span>
                                                    <span class="font-semibold text-gray-800" x-text="email.from_label || email.from_address || '不明'"></span>
                                                    <span class="text-gray-400 ml-1" x-show="email.from_label && email.from_address" x-text="'<' + email.from_address + '>'"></span>
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
                                            <div class="mt-1.5 text-[10px] text-gray-400 font-medium" x-text="email.received_at"></div>
                                        </div>

                                        <div x-show="expandedEmailIds.includes(email.id)" x-collapse>
                                            <div class="px-6 pb-6 pt-2 border-t border-gray-50">
                                                <div class="bg-white p-6 rounded-2xl text-gray-700 leading-relaxed font-medium whitespace-pre-wrap text-sm 2xl:text-base" x-text="email.plain_body"></div>
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
                                                        <template x-for="at in email.attachments" :key="at.id">
                                                            <a :href="at.url" class="flex items-center gap-2 bg-gray-50 border border-gray-100 px-3 py-2 rounded-xl text-[10px] font-black text-blue-600 hover:bg-blue-600 hover:text-white transition-all shadow-sm">
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
                                <button @click="chatScope.kind === 'email' ? null : null"
                                        :disabled="chatScope.kind !== 'email'"
                                        :style="chatScope.kind === 'email'
                                            ? 'background:#7c3aed;color:#fff;'
                                            : 'background:#ffffff;color:#d1d5db;cursor:not-allowed;'"
                                        class="px-2 py-1 transition-colors"
                                        :title="chatScope.kind === 'email' ? '現在このメール固有のチャットを表示中' : 'メールの💬チャットボタンから開いてください'">メール</button>
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
                                <button @click="sendChatComment()"
                                        :disabled="(!chatInput.trim() && chatPendingFiles.length === 0) || chatSending"
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

    {{-- AI要約モーダル --}}
    <div x-show="threadSummaryOpen" x-cloak
         class="fixed inset-0 z-[2000] flex items-center justify-center p-4"
         style="background-color:rgba(15,23,42,0.55);"
         @click.self="threadSummaryOpen = false"
         @keydown.escape.window="threadSummaryOpen = false">
        <div class="w-full max-w-2xl rounded-2xl overflow-hidden flex flex-col"
             style="background-color:#ffffff;max-height:85vh;box-shadow:0 25px 50px -12px rgba(0,0,0,0.4);">
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
                        <p class="text-[10px] truncate" style="color:#6366f1;"
                           x-text="selectedThread?.subject || ''"></p>
                    </div>
                </div>
                <button @click="threadSummaryOpen = false"
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
                {{-- モデル選択ピッカー --}}
                <div class="mb-4 p-3 rounded-xl border" style="background-color:#fafafa;border-color:#e5e7eb;">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest" style="color:#6b7280;">
                            <i class="fas fa-cog text-[9px] mr-1"></i>AIモデル
                        </span>
                        <span x-show="aiPickerLoading" class="text-[10px]" style="color:#9ca3af;">
                            <i class="fas fa-circle-notch fa-spin"></i> 読み込み中
                        </span>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- プロバイダー切り替え --}}
                        <div class="flex rounded-lg border border-gray-200 overflow-hidden text-[11px]">
                            <button type="button" @click="setAiProvider('ollama')"
                                    :class="aiProvider === 'ollama' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                    class="px-3 py-1 transition-colors">Ollama</button>
                            <button type="button" @click="setAiProvider('claude')"
                                    :class="aiProvider === 'claude' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                    :title="!aiHasClaudeKey ? 'APIキー未設定' : ''"
                                    class="px-3 py-1 transition-colors border-l border-gray-200">Claude</button>
                            <button type="button" @click="setAiProvider('gemini')"
                                    :class="aiProvider === 'gemini' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                    :title="!aiHasGeminiKey ? 'APIキー未設定' : ''"
                                    class="px-3 py-1 transition-colors border-l border-gray-200">Gemini</button>
                        </div>
                        {{-- モデル選択 --}}
                        <select x-model="aiModel"
                                class="border border-gray-200 rounded-lg px-2 py-1 text-[11px] text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white min-w-[180px]">
                            <template x-if="aiCurrentModels.length === 0">
                                <option value="">モデルなし</option>
                            </template>
                            <template x-for="m in aiCurrentModels" :key="m.id || m">
                                <option :value="m.id || m" x-text="m.name || m"></option>
                            </template>
                        </select>
                        {{-- 再生成ボタン (モデル変更後の便利ボタン) --}}
                        <button type="button" @click="loadThreadSummary(true)"
                                :disabled="threadSummaryLoading || !aiModel"
                                class="ml-auto inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-bold transition-colors disabled:opacity-40"
                                style="background-color:#4f46e5;color:#fff;">
                            <i class="fas fa-bolt"></i> このモデルで生成
                        </button>
                    </div>
                    <template x-if="aiProvider === 'claude' && !aiHasClaudeKey">
                        <p class="mt-1 text-[10px]" style="color:#d97706;">⚠ Claude APIキー未設定。AI設定から登録してください。</p>
                    </template>
                    <template x-if="aiProvider === 'gemini' && !aiHasGeminiKey">
                        <p class="mt-1 text-[10px]" style="color:#d97706;">⚠ Gemini APIキー未設定。AI設定から登録してください。</p>
                    </template>
                </div>

                {{-- スキル選択 + プロンプト編集 --}}
                <div class="mb-4 p-3 rounded-xl border" style="background-color:#fafafa;border-color:#e5e7eb;">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest" style="color:#6b7280;">
                            <i class="fas fa-lightbulb text-[9px] mr-1"></i>スキル / 指示
                        </span>
                        <button type="button" @click="summaryShowPrompt = !summaryShowPrompt"
                                class="text-[10px] font-bold underline" style="color:#4f46e5;">
                            <span x-show="!summaryShowPrompt">追加指示を入力</span>
                            <span x-show="summaryShowPrompt">追加指示を閉じる</span>
                        </button>
                    </div>
                    {{-- スキルピッカー --}}
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="(skill, key) in aiSkills" :key="key">
                            <button type="button" @click="summarySkill = key"
                                    :title="skill.description"
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-bold border transition-colors"
                                    :class="summarySkill === key ? '' : 'hover:bg-white'"
                                    :style="summarySkill === key
                                        ? 'background-color:#4f46e5;color:#ffffff;border-color:#4f46e5;'
                                        : 'background-color:#ffffff;color:#374151;border-color:#e5e7eb;'">
                                <i class="fas fa-magic text-[9px] mr-1"></i>
                                <span x-text="skill.name"></span>
                            </button>
                        </template>
                    </div>
                    <p class="mt-1.5 text-[10px] leading-snug" style="color:#9ca3af;"
                       x-text="aiSkills[summarySkill]?.description || ''"></p>

                    {{-- 追加指示 (プロンプト) --}}
                    <div x-show="summaryShowPrompt" x-cloak class="mt-3">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[10px] font-extrabold uppercase tracking-widest" style="color:#6b7280;">追加指示 (任意)</label>
                            <span class="text-[10px]" style="color:#6366f1;"><kbd class="px-1 bg-white border border-gray-200 rounded text-[9px]">/</kbd> でコレクション挿入</span>
                        </div>
                        <div class="relative prompt-editor-container">
                            <div x-ref="summaryPromptHighlight" class="prompt-editor-highlight" aria-hidden="true"
                                 x-html="renderSummaryHighlight(summaryUserPrompt)"></div>
                            <textarea x-ref="summaryPromptArea"
                                      x-model="summaryUserPrompt"
                                      @input="onSummaryPromptInput($event); syncSummaryHighlightScroll()"
                                      @scroll="syncSummaryHighlightScroll()"
                                      @keydown="onSummaryPromptKeyDown($event)"
                                      @blur="setTimeout(() => summarySlash.open = false, 150)"
                                      rows="3"
                                      placeholder="例: /(コレクション名) を参照、丁寧な敬語で、技術用語は平易に。"
                                      class="w-full text-sm border rounded-lg resize-y prompt-editor-input"
                                      style="border-color:#e5e7eb;"></textarea>

                            {{-- スラッシュコマンド候補 --}}
                            <div x-show="summarySlash.open" x-cloak
                                 class="absolute left-0 right-0 z-30 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl max-h-60 overflow-y-auto"
                                 style="top:100%;">
                                <div class="sticky top-0 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                                    <span><i class="fas fa-folder text-indigo-400 mr-1"></i>ナレッジコレクション</span>
                                    <span class="text-[9px] text-gray-300" x-show="summarySlash.loading">読み込み中...</span>
                                </div>
                                <template x-for="(c, idx) in filteredSummaryCollections" :key="c.name + idx">
                                    <button type="button"
                                            @mousedown.prevent="insertSummaryCollection(c.name)"
                                            @mouseenter="summarySlash.activeIdx = idx"
                                            :class="idx === summarySlash.activeIdx ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'"
                                            class="w-full text-left px-3 py-2 text-xs flex items-center gap-2">
                                        <i class="fas fa-folder text-indigo-400 text-[10px]"></i>
                                        <span class="flex-1 font-mono" x-text="c.name"></span>
                                    </button>
                                </template>
                                <template x-if="!summarySlash.loading && filteredSummaryCollections.length === 0">
                                    <p class="px-3 py-3 text-xs text-gray-400 text-center">該当するコレクションがありません</p>
                                </template>
                            </div>
                        </div>
                        <p class="mt-1 text-[10px]" style="color:#9ca3af;">
                            スキルの基本指示に加えて、この依頼に固有の指示を追加できます。空でも OK。
                        </p>
                    </div>
                </div>

                {{-- ローディング --}}
                <div x-show="threadSummaryLoading" class="flex flex-col items-center justify-center py-10">
                    <i class="fas fa-circle-notch fa-spin fa-2x mb-3" style="color:#818cf8;"></i>
                    <p class="text-sm font-bold" style="color:#4f46e5;">スレッドを分析中...</p>
                    <p class="text-[11px] mt-1" style="color:#9ca3af;"
                       x-text="threadEmails.length + ' 通のメールを読み込み中'"></p>
                </div>

                {{-- エラー --}}
                <div x-show="!threadSummaryLoading && threadSummaryError"
                     class="rounded-lg p-4 text-sm whitespace-pre-wrap break-words"
                     style="background-color:#fef2f2;color:#b91c1c;border:1px solid #fecaca;max-height:24rem;overflow-y:auto;"
                     x-text="threadSummaryError"></div>

                {{-- 結果 --}}
                <div x-show="!threadSummaryLoading && threadSummary && !threadSummaryError" class="space-y-3">
                    <div class="flex items-center gap-2 text-[10px] font-bold flex-wrap">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full"
                              style="background-color:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;">
                            <i class="fas fa-envelope"></i>
                            <span x-text="(threadSummary?.email_count || 0) + ' 通'"></span>
                        </span>
                        <template x-if="threadSummary?.skill_name">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full"
                                  style="background-color:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;">
                                <i class="fas fa-magic"></i>
                                <span x-text="threadSummary.skill_name"></span>
                            </span>
                        </template>
                        <template x-if="threadSummary?.ticket">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full"
                                  style="background-color:#fef3c7;color:#92400e;border:1px solid #fde68a;">
                                <i class="fas fa-hashtag"></i>
                                <span x-text="threadSummary.ticket"></span>
                            </span>
                        </template>
                    </div>
                    <div class="rounded-xl p-4 text-sm leading-relaxed whitespace-pre-wrap"
                         style="background-color:#f9fafb;color:#111827;border:1px solid #e5e7eb;word-break:break-word;"
                         x-text="threadSummary?.summary || ''"></div>
                </div>
            </div>

            {{-- フッター --}}
            <div class="shrink-0 px-5 py-3 flex items-center justify-between gap-2 border-t border-gray-100"
                 style="background-color:#f9fafb;">
                <button type="button" @click="copyThreadSummary()"
                        x-show="!threadSummaryLoading && threadSummary && !threadSummaryError"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
                        style="background-color:#ffffff;color:#374151;border:1px solid #d1d5db;"
                        onmouseover="this.style.backgroundColor='#f3f4f6';"
                        onmouseout="this.style.backgroundColor='#ffffff';">
                    <i class="fas" :class="threadSummaryCopied ? 'fa-check' : 'fa-copy'"></i>
                    <span x-text="threadSummaryCopied ? 'コピーしました' : '要約をコピー'"></span>
                </button>
                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" @click="loadThreadSummary(true)"
                            :disabled="threadSummaryLoading"
                            x-show="threadSummary && !threadSummaryError"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors disabled:opacity-50"
                            style="background-color:#ffffff;color:#374151;border:1px solid #d1d5db;"
                            onmouseover="if(!this.disabled)this.style.backgroundColor='#f3f4f6';"
                            onmouseout="if(!this.disabled)this.style.backgroundColor='#ffffff';">
                        <i class="fas fa-redo text-[10px]"></i> 再生成
                    </button>
                    <button type="button" @click="threadSummaryOpen = false"
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

    {{-- ナレッジ登録モーダル (メール本文を編集 → 登録) --}}
    <div x-show="knowledgeRegisterOpen" x-cloak
         class="fixed inset-0 z-[2000] flex items-center justify-center p-4"
         style="background-color:rgba(15,23,42,0.55);"
         @click.self="knowledgeRegisterOpen = false"
         @keydown.escape.window="knowledgeRegisterOpen = false">
        <div class="w-full max-w-3xl rounded-2xl overflow-hidden flex flex-col"
             style="background-color:#ffffff;max-height:90vh;box-shadow:0 25px 50px -12px rgba(0,0,0,0.4);">
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
                <button @click="knowledgeRegisterOpen = false" class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-white" title="閉じる">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-5 space-y-4">
                <div x-show="knowledgeLoading" class="flex items-center justify-center py-8 text-emerald-300">
                    <i class="fas fa-circle-notch fa-spin"></i>
                    <span class="ml-2 text-sm">読み込み中...</span>
                </div>

                <template x-if="knowledgeError">
                    <div class="rounded-lg p-3 text-sm bg-red-50 text-red-700 border border-red-200" x-text="knowledgeError"></div>
                </template>

                <div x-show="!knowledgeLoading && knowledgeForm">
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800 mb-3">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <span x-text="knowledgeForm.suggested_pii_warning || ''"></span>
                        <p class="mt-1.5 text-[11px]">よく使うマスク例:
                            <code class="bg-white px-1 rounded">[氏名]</code>
                            <code class="bg-white px-1 rounded">[メール]</code>
                            <code class="bg-white px-1 rounded">[電話]</code>
                            <code class="bg-white px-1 rounded">[住所]</code>
                            <button type="button" @click="applyMaskHeuristics()" class="ml-2 underline font-bold">自動マスク</button>
                        </p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-extrabold uppercase tracking-widest text-gray-500 mb-1">タイトル</label>
                        <input type="text" x-model="knowledgeForm.title" maxlength="255"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-200">
                    </div>

                    <div class="mt-3">
                        <label class="block text-[10px] font-extrabold uppercase tracking-widest text-gray-500 mb-1">コレクション</label>
                        <input type="text" x-model="knowledgeForm.collection" maxlength="64" placeholder="default"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-200">
                    </div>

                    <div class="mt-3">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[10px] font-extrabold uppercase tracking-widest text-gray-500">登録する本文 (編集可)</label>
                            <span class="text-[10px] text-gray-400" x-text="(knowledgeForm.content?.length || 0) + ' 字'"></span>
                        </div>
                        <textarea x-model="knowledgeForm.content" rows="14"
                                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs font-mono leading-relaxed focus:outline-none focus:ring-2 focus:ring-emerald-200 resize-y"></textarea>
                    </div>
                </div>
            </div>

            <div class="shrink-0 px-5 py-3 border-t border-gray-100 bg-gray-50 flex items-center justify-end gap-2">
                <button type="button" @click="knowledgeRegisterOpen = false"
                        class="px-3 py-1.5 rounded-lg text-sm text-gray-700 bg-white border border-gray-200 hover:bg-gray-100">キャンセル</button>
                <button type="button" @click="submitKnowledgeRegister()"
                        :disabled="knowledgeSaving || !knowledgeForm?.content"
                        class="px-4 py-1.5 rounded-lg text-sm font-bold text-white disabled:opacity-50 inline-flex items-center gap-1.5"
                        style="background-color:#059669;">
                    <i x-show="knowledgeSaving" class="fas fa-circle-notch fa-spin text-xs"></i>
                    <span x-text="knowledgeSaving ? '登録中…' : 'ナレッジに登録'"></span>
                </button>
            </div>
        </div>
    </div>

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
                    <p class="rounded-lg p-3 text-[12px] text-gray-800 leading-relaxed break-all"
                       style="background-color:#f9fafb;border:1px solid #e5e7eb;"
                       x-text="syncError.detail"></p>
                    <div x-data="{ expanded: false }" class="text-left">
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

    {{-- マージベース選択モーダル --}}
    <template x-if="mergeModalOpen">
        <div class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-md p-4">
            <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-xl overflow-hidden animate-in zoom-in duration-200">
                <div class="bg-blue-50 px-8 py-6 border-b border-blue-100">
                    <h3 class="text-lg font-black text-blue-900 uppercase tracking-tighter">ベースとなるスレッドを選択</h3>
                    <p class="text-xs text-blue-600 mt-1 font-bold">選択したスレッドの件名がマージ後のスレッド名になります。</p>
                </div>
                <div class="max-h-[400px] overflow-y-auto p-6 space-y-3 custom-scrollbar">
                    <template x-for="threadId in selectedThreadIds" :key="threadId">
                        <div @click="mergeTargetId = threadId"
                             :class="mergeTargetId === threadId ? 'bg-blue-50 border-blue-200 ring-2 ring-blue-500' : 'bg-gray-50 border-gray-100 hover:bg-white'"
                             class="p-4 rounded-2xl border-2 cursor-pointer transition-all flex items-center justify-between">
                            <div class="min-w-0 pr-4">
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest" x-text="'ID: ' + threadId"></p>
                                <p class="text-sm font-bold text-gray-800 truncate" x-text="threads.find(t => t.id === threadId)?.subject"></p>
                            </div>
                            <div class="shrink-0 w-6 h-6 rounded-full border-2 flex items-center justify-center"
                                 :class="mergeTargetId === threadId ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300'">
                                <i x-show="mergeTargetId === threadId" class="fas fa-check fa-xs"></i>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="px-8 py-6 bg-gray-50 border-t border-gray-100 flex gap-3">
                    <button @click="mergeModalOpen = false" class="flex-1 py-4 text-xs font-black text-gray-400 hover:bg-white rounded-2xl border border-transparent uppercase">キャンセル</button>
                    <button @click="executeMerge()" class="flex-[2] bg-blue-600 text-white py-4 rounded-2xl font-black text-xs shadow-xl uppercase">マージを実行</button>
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
function emailApp() {
    return {
        threadWidth: parseInt(localStorage.getItem('threadWidth')) || (window.innerWidth >= 1920 ? 450 : 380),
        fetching: false,
        selectedThreadId: null, selectedThread: null,
        leftTab: 'inbox', searchQuery: '',
        allStatusMode: (() => { try { return JSON.parse(localStorage.getItem('allStatusMode')) === true; } catch(_) { return false; } })(),
        pinnedOnlyMode: {{ isset($isPinnedView) && $isPinnedView ? 'true' : 'false' }},
        assigneeFilterId: localStorage.getItem('assigneeFilterId') || 'all',
        sortOrder: 'desc',
        statusLabels: { inbox: '受信', hold: '保留', completed: '完了', no_action: '対応不要', pending: '承認待ち' },
        threadEmails: [], threadMerges: [], expandedEmailIds: [],
        // AI要約 (スレッド全体)
        threadSummaryOpen: false, threadSummaryLoading: false, threadSummary: null,
        threadSummaryError: '', threadSummaryCopied: false,
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
        // 最新へスクロールボタン用 (ユーザーがスクロールアップしている時に表示)
        chatScrolledUp: false,
        // スレッド全体チャット添付
        chatPendingFiles: [],
        // @メンション機能
        mentionOpen: false, mentionQuery: '', mentionStart: -1, mentionIndex: 0,
        selectionMode: false, selectedThreadIds: [], longPressTimer: null, isLongPressing: false,
        mergeModalOpen: false, mergeTargetId: null,
        threads: [], threadsLoading: false, syncError: null,
        users: [],
        pendingApprovals: [],
        toasts: [],
        virtualScroll: { startIndex: 0, endIndex: 30, rowHeight: 120, viewportHeight: 600, buffer: 10 },
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
            await Promise.all([
                this.loadThreads(),
                this.loadUsers()
            ]);
            window.addEventListener('resize', () => this.updateVirtualViewport());
            this.$nextTick(() => this.updateVirtualViewport());

            // 別ウィンドウ (compose-window) で走った AI タスクの完了をバックグラウンドポーリング
            this.startAiBackgroundPoll();

            // クエリパラメータ `?thread=<id>` で指定されたスレッドを自動表示
            // (チャット画面の「元メールを開く」や添付ファイル画面の件名リンク等から
            //  ?thread=N で飛んできた際に、該当スレッドを自動でロードしてワークスペースに表示)
            try {
                const url = new URL(window.location.href);
                const raw = url.searchParams.get('thread');
                const threadId = raw ? parseInt(raw, 10) : null;
                if (threadId && !Number.isNaN(threadId)) {
                    console.log('[emails] auto-open thread from URL param:', threadId);
                    await this.loadThread(threadId);
                }
            } catch (e) {
                console.error('[emails] ?thread= 自動オープン失敗:', e);
            }

            // 作成専用ウィンドウからの送信完了 / 下書き保存通知を購読
            window.addEventListener('message', (event) => {
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
            });

            this.setupPolling();
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
                sort_order: this.sortOrder
            });
            if (this.assigneeFilterId !== 'all') params.append('assigned_user_id', this.assigneeFilterId);

            try {
                const res = await fetch('/emails/search?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                if (res.status === 401) { window.location.href = '/login'; return; }
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const json = await res.json();
                this.threads = Array.isArray(json) ? json : [];
                this.handleScroll();
            } catch(e) {
                console.error('スレッド一覧の取得に失敗', e);
                if (!isBackground) this.toast('一覧の取得に失敗しました', 'error');
            } finally {
                if (!isBackground) this.threadsLoading = false;
            }
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
        toggleSort() { this.sortOrder = (this.sortOrder === 'desc' ? 'asc' : 'desc'); this.loadThreads(); },

        async fetchEmails(isBackground = false) {
            if (this.fetching) return;
            if (!isBackground) {
                this.fetching = true;
                this.syncError = null;
            }
            try {
                const res = await fetch('/emails/fetch', { method: 'POST', headers: this.jsonHeaders() });
                let data = {};
                try { data = await res.json(); } catch(_) {}
                if (!res.ok) throw new Error(data.error || data.message || `HTTP ${res.status}`);

                await this.loadThreads(isBackground);

                this.pollFailCount = 0;
                this.currentPollDelay = this.basePollDelay;
            } catch (e) {
                if (!isBackground) {
                    this.syncError = { message: 'メールサーバーとの同期に失敗しました', detail: e.message || '原因不明のエラー', stack: e.stack || '' };
                }
                this.pollFailCount = Math.min(this.pollFailCount + 1, 8);
                const delay = this.basePollDelay * Math.pow(2, this.pollFailCount);
                this.currentPollDelay = Math.min(delay, this.maxPollDelay);
            } finally {
                if (!isBackground) this.fetching = false;
            }
        },

        startLongPress(thread, e) {
            if (e.target.closest('button') || e.target.tagName.toLowerCase() === 'button') return;
            this.isLongPressing = false;
            this.longPressTimer = setTimeout(() => {
                this.isLongPressing = true;
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

        async updateSelectedStatus(status) {
            try {
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
                this.cancelSelection(); await this.loadThreads();
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
            } catch(e) {
                this.toast('削除に失敗しました', 'error');
                await this.loadThreads();
            }
        },

        mergeSelected() {
            if (this.selectedThreadIds.length < 2) return;
            this.mergeTargetId = this.selectedThreadIds[0];
            this.mergeModalOpen = true;
        },

        async executeMerge() {
            const targetId = this.mergeTargetId;
            if (!targetId) { this.toast('ベースとなるスレッドを選択してください', 'error'); return; }
            const sourceIds = this.selectedThreadIds.filter(id => id !== targetId);
            try {
                let hasError = false;
                for (let id of sourceIds) {
                    const res = await fetch(`/threads/${targetId}/merge`, {
                        method: 'POST',
                        headers: this.jsonHeaders(),
                        body: JSON.stringify({ merge_thread_id: id })
                    });
                    if (!res.ok) hasError = true;
                }
                this.mergeModalOpen = false;
                this.cancelSelection();
                await this.loadThreads();
                await this.loadThread(targetId);
                if (hasError) this.toast('一部のマージに失敗しました', 'error');
                else this.toast('マージしました', 'success');
            } catch(e) { this.toast('マージに失敗しました', 'error'); }
        },

        async unmergeThread(mergeId) {
            if (!confirm('マージを解除しますか？')) return;
            try {
                const res = await fetch(`/thread-merges/${mergeId}`, { method: 'DELETE', headers: this.jsonHeaders() });
                if (res.ok) {
                    this.toast('マージを解除しました', 'success');
                    await this.loadThread(this.selectedThreadId);
                    await this.loadThreads();
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

        // 任意のスレッドを ID で削除 (リスト行のホバー削除ボタン用)
        async deleteThreadById(id, subject) {
            if (!id) return;
            const label = subject || '(無題)';
            if (!confirm(`「${label}」を削除します。よろしいですか？\n\n※スレッド内のメールも一緒に削除されます。`)) return;

            const wasSelected = this.selectedThreadId === id;
            const idx = this.threads.findIndex(t => t.id === id);
            const nextThreadId = wasSelected && idx !== -1 && idx < this.threads.length - 1
                ? this.threads[idx + 1].id
                : null;

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

                // 削除したのが現在表示中のスレッドならワークスペースを閉じる/次へ
                if (wasSelected) {
                    this.closeWorkspace();
                }
                // 選択モードなら選択リストからも除外
                if (this.selectedThreadIds.includes(id)) {
                    this.selectedThreadIds = this.selectedThreadIds.filter(x => x !== id);
                    this.selectionMode = this.selectedThreadIds.length > 0;
                }
                await this.loadThreads();
                if (wasSelected && nextThreadId && this.threads.find(t => t.id === nextThreadId)) {
                    this.loadThread(nextThreadId);
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
                this.toast(data.is_pinned ? 'ピン留めしました' : 'ピン留めを解除しました', 'success');
                await this.loadThreads();
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

        setLeftTab(tab) { this.leftTab = tab; this.loadThreads(); },
        goToPrevThread() { const idx = this.threads.findIndex(t => t.id === this.selectedThreadId); if (idx > 0) this.loadThread(this.threads[idx - 1].id); },
        goToNextThread() {
            const idx = this.threads.findIndex(t => t.id === this.selectedThreadId);
            if (idx !== -1 && idx < this.threads.length - 1) {
                this.loadThread(this.threads[idx + 1].id);
            } else {
                this.closeWorkspace();
            }
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

        // ワークスペースを閉じる (スレッド閲覧の終了のみ)
        closeWorkspace() {
            this.selectedThread = null;
            this.selectedThreadId = null;
            this.threadEmails = [];
            this.threadMerges = [];
            this.expandedEmailIds = [];
            this.pendingApprovals = [];
            this.chatOpen = false;
            this.chatComments = [];
            this.chatInput = '';
            this.chatPendingFiles = [];
            this.chatScope = { kind: 'thread', email_id: null, email_subject: '', email_from: '' };
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
        async openKnowledgeRegister(emailArg = null) {
            let target = emailArg;
            if (!target?.id) {
                const emails = this.threadEmails || [];
                if (emails.length === 0) { this.toast('スレッドにメールがありません', 'error'); return; }
                target = emails.find(e => e.id) || emails[0];
            }
            if (!target?.id) { this.toast('登録対象のメールが見つかりません', 'error'); return; }

            this.knowledgeRegisterOpen = true;
            this.knowledgeLoading = true;
            this.knowledgeError = '';
            this.knowledgeForm = null;
            try {
                const res = await fetch(`/knowledge/from-email/${target.id}`, { headers: { Accept: 'application/json' } });
                if (!res.ok) {
                    this.knowledgeError = 'メール内容を取得できませんでした (HTTP ' + res.status + ')';
                    return;
                }
                const data = await res.json();
                this.knowledgeForm = {
                    email_id: data.email_id,
                    title: data.default_title || '',
                    content: data.editable_content || '',
                    collection: 'default',
                    suggested_pii_warning: data.suggested_pii_warning || '',
                };
            } catch (e) {
                this.knowledgeError = '通信エラー: ' + (e.message || '');
            } finally {
                this.knowledgeLoading = false;
            }
        },

        // 簡易な PII 自動マスク (メール / 電話 / 郵便番号 を置換)
        applyMaskHeuristics() {
            if (!this.knowledgeForm) return;
            let text = this.knowledgeForm.content;
            text = text.replace(/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/g, '[メール]');
            text = text.replace(/(\d{2,4}-\d{2,4}-\d{4})/g, '[電話]');
            text = text.replace(/(\d{3}-\d{4})(?!\d)/g, '[郵便番号]');
            // 全角数字の電話 (例: 090-1234-5678 系)
            text = text.replace(/([０-９]{2,4}[\-－][０-９]{2,4}[\-－][０-９]{4})/g, '[電話]');
            this.knowledgeForm.content = text;
            this.toast('連絡先パターンを自動マスクしました', 'info');
        },

        async submitKnowledgeRegister() {
            if (!this.knowledgeForm) return;
            if (!this.knowledgeForm.content?.trim()) { this.knowledgeError = '本文が空です'; return; }
            if (!this.knowledgeForm.title?.trim()) { this.knowledgeError = 'タイトルを入力してください'; return; }
            this.knowledgeSaving = true;
            this.knowledgeError = '';
            try {
                const res = await fetch(`/knowledge/from-email/${this.knowledgeForm.email_id}`, {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({
                        title: this.knowledgeForm.title,
                        content: this.knowledgeForm.content,
                        collection: this.knowledgeForm.collection || 'default',
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) { this.knowledgeError = data.message || ('登録に失敗しました (HTTP ' + res.status + ')'); return; }
                this.toast(data.message || 'ナレッジに登録しました', 'success');
                this.knowledgeRegisterOpen = false;
            } catch (e) {
                this.knowledgeError = '通信エラー: ' + (e.message || '');
            } finally {
                this.knowledgeSaving = false;
            }
        },

        // ============= AI要約 (スレッド全体) =============
        openThreadSummary() {
            if (!this.selectedThreadId) {
                this.toast('スレッドを選択してください', 'error');
                return;
            }
            this.threadSummaryOpen = true;
            this.threadSummaryCopied = false;
            // モデル一覧をロード (初回のみ)
            this.loadAiModels();
            // 既に生成済みなら再利用、未生成なら生成
            if (!this.threadSummary || this.threadSummary?.thread_id !== this.selectedThreadId) {
                this.loadThreadSummary(false);
            }
        },
        async loadThreadSummary(force = false) {
            if (!this.selectedThreadId) return;
            if (this.threadSummaryLoading) return;
            if (!force && this.threadSummary && this.threadSummary.thread_id === this.selectedThreadId) return;
            this.threadSummaryLoading = true;
            this.threadSummaryError = '';
            this.threadSummary = null;
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
                        prompt:   this.summaryUserPrompt || '',
                    }),
                });
                localStorage.setItem('summarySkill', this.summarySkill || 'summarize');
                const initial = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.threadSummaryError = initial.message || `AI要約の開始に失敗しました (HTTP ${res.status})`;
                    return;
                }
                // task_id を受け取ったので完了までポーリング
                const taskId = initial.task_id;
                const finalData = await this._pollAiTask(taskId);
                if (!finalData) return; // タイムアウトはエラー設定済み
                if (finalData.status === 'error') {
                    const code = finalData.error_code;
                    let prefix = '';
                    if (code === 'insufficient_credits') prefix = '【クレジット不足】';
                    else if (code === 'invalid_api_key') prefix = '【APIキー無効】';
                    else if (code === 'rate_limited') prefix = '【レート制限】';
                    else if (code === 'model_not_found') prefix = '【モデル未存在】';
                    else if (code === 'rag_api_unreachable') prefix = '【RAG API 未起動】';
                    this.threadSummaryError = prefix + 'AI要約に失敗しました: ' + (finalData.error_message || '');
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
                // 完了通知 (モーダルが閉じていてもユーザーに知らせる)
                const subjLabel = initial.subject ? ('「' + initial.subject + '」') : '';
                this.toast('AI要約が完了しました' + (this.threadSummaryOpen ? '' : ' (モーダルを開いて確認)'), 'success');
                this._notifyDesktop('AI要約 完了', subjLabel || 'スレッドの要約が生成されました');
            } catch (e) {
                this.threadSummaryError = '通信エラー: ' + (e.message || '');
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
                setTimeout(() => { this.threadSummaryCopied = false; }, 1500);
            } catch (e) {
                this.toast('コピーに失敗しました', 'error');
            }
        },

        // チャットパネルの開閉 (スレッド全体スコープで開く)
        toggleChatPanel() {
            if (!this.selectedThreadId) {
                this.toast('スレッドを選択してください', 'error');
                return;
            }
            if (this.chatOpen && this.chatScope.kind === 'thread') {
                // 既に thread スコープで開いている → 閉じる
                this.chatOpen = false;
                this.stopChatPolling();
                this._chatReadJustNow = false;
                return;
            }
            // thread スコープに切替えて開く
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
            const text = this.chatInput.trim();
            const hasFiles = this.chatPendingFiles.length > 0;
            if ((!text && !hasFiles) || !this.selectedThreadId || this.chatSending) return;
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
            // チャットスコープを email に切替してサイドバーを開く
            this.chatScope = {
                kind: 'email',
                email_id: email.id,
                email_subject: email.subject || '',
                email_from: email.from_label || email.from_address || '',
            };
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

        // メンション候補のフィルタリング
        get mentionMatches() {
            if (!this.mentionOpen) return [];
            const q = (this.mentionQuery || '').toLowerCase();
            const list = (this.users || []).filter(u =>
                !q || (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q)
            );
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

        // Discord 風: Ctrl/Cmd + Enter で送信、Enter は改行 (デフォルト)
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

        async loadThread(id) {
            this.selectedThreadId = id;
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
                if (this.threadEmails.length > 0) this.expandedEmailIds.push(this.threadEmails[0].id);
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

                const res = await fetch(`/threads/${thread.id}/status`, { method: 'PUT', headers: this.jsonHeaders(), body: JSON.stringify({ status }) });
                if (!res.ok) { this.toast('ステータス更新に失敗しました', 'error'); return; }

                if (this.selectedThread) this.selectedThread.status = status;
                this.toast(`「${this.statusLabels[status] || status}」に変更しました`, 'success');

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
.thread-chat-messages .msg-row .author { color:#111827; font-weight:600; font-size:13px; }
.thread-chat-messages .msg-row .ts { color:#9ca3af; font-size:11px; }
.thread-chat-messages .msg-row .body { color:#1f2937; font-size:13px; line-height:1.5; white-space:pre-wrap; word-wrap:break-word; }
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
    color:#111827; font-size:13px; line-height:1.4; max-height:200px;
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
</style>
@endsection
