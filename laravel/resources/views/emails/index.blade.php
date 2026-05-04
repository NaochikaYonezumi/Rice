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
                    <template x-for="tab in ['inbox', 'hold', 'completed', 'pending']">
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
                                    {{-- 1段目: 送信者 + 日付 --}}
                                    <div class="flex justify-between items-center gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <template x-if="selectionMode">
                                                <input type="checkbox" class="w-3.5 h-3.5 text-blue-600 border-gray-300 rounded focus:ring-blue-500 shrink-0"
                                                       :checked="selectedThreadIds.includes(thread.id)" @click.stop="toggleSelection(thread)">
                                            </template>
                                            <i x-show="thread.is_pinned" class="fas fa-thumbtack text-amber-500 text-[10px] shrink-0"></i>
                                            <i x-show="thread.thread_merges_count > 0" class="fas fa-object-group text-blue-500 text-[10px] shrink-0" title="マージ済み"></i>
                                            <span class="text-[13px] 2xl:text-base font-black text-gray-900 truncate" x-text="thread.latest_email?.from_label || '不明な送信者'"></span>
                                        </div>
                                        <span class="text-[10px] 2xl:text-xs text-gray-400 font-medium shrink-0" x-text="thread.last_email_at"></span>
                                    </div>

                                    {{-- 2段目: 件名 (フル幅・最大2行) --}}
                                    <div class="text-[12px] 2xl:text-sm text-gray-700 font-medium leading-snug break-words"
                                         style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"
                                         x-text="thread.subject"></div>

                                    {{-- 3段目: メタデータ (ステータス / 担当者 / タグ) --}}
                                    <div class="flex items-center gap-1.5 flex-wrap min-h-[18px]"
                                         x-show="allStatusMode || thread.assignee || (thread.tags && thread.tags.length > 0)">

                                        {{-- ステータスバッジ (全表示モード時) --}}
                                        <template x-if="allStatusMode">
                                            <span class="px-2 py-0.5 rounded text-[8px] 2xl:text-[10px] font-black uppercase shadow-sm border inline-flex items-center"
                                                :class="{
                                                    'bg-blue-100 text-blue-700 border-blue-200': thread.status === 'inbox' || !thread.status,
                                                    'bg-amber-100 text-amber-800 border-amber-200': thread.status === 'hold',
                                                    'bg-green-100 text-green-800 border-green-200': thread.status === 'completed',
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
                                <button @click="if(threadEmails.length > 0) openReplyForEmail(threadEmails[0])" title="返信 (新しいウィンドウ)"
                                    class="icon-btn bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white">
                                    <i class="fas fa-reply text-xs"></i>
                                </button>
                                <button @click="if(threadEmails.length > 0) openReplyForEmail(threadEmails[0], true)" title="全員に返信 (新しいウィンドウ)"
                                    class="icon-btn text-blue-400 border border-blue-100 hover:bg-blue-50 hover:text-blue-600">
                                    <i class="fas fa-reply-all text-xs"></i>
                                </button>

                                {{-- チャット切替ボタン (このスレッド専用のチャット) --}}
                                <button @click="toggleChatPanel()" title="このスレッド専用のチャット"
                                    :class="chatOpen ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'"
                                    class="h-9 inline-flex items-center gap-1.5 px-3 rounded-lg border text-xs font-bold transition-all relative">
                                    <i class="fas fa-comments"></i>
                                    <span>チャット</span>
                                    <span x-show="chatComments.length > 0"
                                          class="ml-0.5 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-black"
                                          :class="chatOpen ? 'bg-white text-emerald-700' : 'bg-emerald-600 text-white'"
                                          x-text="chatComments.length"></span>
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
                    {{-- 2行目: 件名 + 承認状態バッジ --}}
                    <div class="px-6 py-2.5 flex items-center gap-2.5 min-w-0">
                        <div class="w-7 h-7 bg-blue-600 rounded-lg flex items-center justify-center text-white shrink-0">
                            <i class="fas fa-envelope text-[11px]"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-sm font-extrabold text-gray-800"
                                style="word-break:break-word;overflow-wrap:anywhere;line-height:1.35;"
                                x-text="selectedThread?.subject"></h2>
                            <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                {{-- 担当者は上部のトグルボタンに表示 (重複削除) --}}
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

                                {{-- 各メール表示 (ヘッダを複数段に分割) --}}
                                <template x-for="email in threadEmails" :key="email.id">
                                    <div class="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm hover:shadow-md transition-shadow group">
                                        <div class="px-6 py-4 cursor-pointer hover:bg-gray-50/50 transition-colors flex flex-col gap-3" @click="toggleEmailExpand(email.id)">

                                            {{-- 1段目: アバター + 送信者情報 + 開閉アイコン --}}
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="flex items-center gap-4 min-w-0 flex-1">
                                                    <div class="w-10 h-10 bg-gray-100 rounded-2xl flex items-center justify-center text-gray-500 font-black text-lg shadow-inner shrink-0" x-text="(email.from_label || '?')[0]"></div>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm font-black text-gray-900 break-words" x-text="email.from_label"></p>
                                                        <p class="text-[11px] text-gray-400 font-medium break-all" x-text="email.from_address"></p>
                                                    </div>
                                                </div>
                                                <i class="fas fa-chevron-down text-gray-300 group-hover:text-blue-500 transition-all mt-2 shrink-0" :class="expandedEmailIds.includes(email.id) ? 'rotate-180' : ''"></i>
                                            </div>

                                            {{-- 2段目: 宛先 (To) --}}
                                            <div class="text-[11px] text-gray-500 font-medium break-all">
                                                <span class="text-gray-400 mr-1">To:</span><span x-text="email.to_address"></span>
                                            </div>

                                            {{-- 3段目: 受信日時 --}}
                                            <div class="text-[10px] text-gray-400 font-bold uppercase tracking-widest" x-text="email.received_at"></div>

                                            {{-- 4段目: アクションボタン (返信 / 全員に返信) --}}
                                            <div class="flex flex-wrap items-center gap-2">
                                                <button @click.stop="openReplyForEmail(email)"
                                                        class="bg-blue-50 text-blue-600 px-4 py-2 rounded-xl font-black text-[11px] 2xl:text-xs shadow-sm hover:bg-blue-600 hover:text-white transition-all inline-flex items-center gap-2">
                                                    <i class="fas fa-reply"></i> 返信
                                                </button>
                                                <button @click.stop="openReplyForEmail(email, true)"
                                                        class="bg-white text-blue-600 border border-blue-100 px-4 py-2 rounded-xl font-black text-[11px] 2xl:text-xs shadow-sm hover:bg-blue-50 transition-all inline-flex items-center gap-2">
                                                    <i class="fas fa-reply-all"></i> 全員に返信
                                                </button>
                                            </div>
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
                                                    <div class="flex flex-wrap gap-2">
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
                           class="w-[360px] shrink-0 border-l border-emerald-100 bg-emerald-50/20 flex flex-col overflow-hidden">
                        {{-- ヘッダ --}}
                        <div class="shrink-0 px-4 py-3 bg-white border-b border-emerald-100 flex items-center justify-between">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-8 h-8 rounded-lg bg-emerald-500 flex items-center justify-center text-white shrink-0">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-bold text-gray-800">スレッド内チャット</h3>
                                    <p class="text-[10px] text-gray-400 truncate" x-text="selectedThread?.subject || ''"></p>
                                </div>
                            </div>
                            <button @click="toggleChatPanel()" class="text-gray-400 hover:text-emerald-600 p-1" title="閉じる">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </div>

                        {{-- メッセージリスト --}}
                        <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar" id="chat-messages">
                            <template x-if="chatLoading">
                                <div class="flex items-center justify-center py-8 text-emerald-300">
                                    <i class="fas fa-circle-notch fa-spin"></i>
                                </div>
                            </template>
                            <template x-if="!chatLoading && chatComments.length === 0">
                                <div class="text-center py-12 text-gray-400">
                                    <i class="fas fa-comment-slash fa-2x text-gray-200 mb-3"></i>
                                    <p class="text-xs font-semibold">まだメッセージがありません</p>
                                    <p class="text-[10px] text-gray-400 mt-1">下から最初のメッセージを送ってみましょう</p>
                                </div>
                            </template>
                            <template x-for="c in chatComments" :key="c.id">
                                <div class="flex" :class="c.is_author ? 'justify-end' : 'justify-start'">
                                    <div class="max-w-[80%] group">
                                        <div class="flex items-center gap-2 mb-0.5"
                                             :class="c.is_author ? 'justify-end' : 'justify-start'">
                                            <span class="text-[10px] font-bold text-gray-500" x-text="c.author"></span>
                                            <span class="text-[10px] text-gray-400" x-text="c.created_at"></span>
                                            <template x-if="isMentionedToMe(c.content)">
                                                <span class="text-[9px] font-black px-1 py-0.5 rounded bg-amber-100 text-amber-700 border border-amber-200">@あなた宛</span>
                                            </template>
                                        </div>
                                        <div class="rounded-2xl px-3 py-2 text-sm whitespace-pre-wrap break-words leading-relaxed shadow-sm"
                                             :style="c.is_author
                                                ? 'background-color:#10b981;color:#ffffff;'
                                                : 'background-color:#ffffff;color:#1f2937;border:1px solid #e5e7eb;'"
                                             x-html="renderMentions(c.content, c.is_author)"></div>
                                        <div class="text-right mt-1" x-show="c.is_author">
                                            <button @click="deleteChatComment(c.id)"
                                                    class="text-[10px] text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity"
                                                    title="削除">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- 入力エリア --}}
                        <div class="shrink-0 border-t border-emerald-100 bg-white p-3 relative">

                            {{-- メンション候補ドロップダウン --}}
                            <template x-if="mentionOpen && mentionMatches.length > 0">
                                <div class="absolute left-3 right-3 bottom-full mb-2 bg-white border border-gray-200 rounded-xl shadow-2xl overflow-hidden max-h-56 overflow-y-auto custom-scrollbar z-50">
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
                            </template>

                            <div class="flex items-end gap-2">
                                <textarea id="chat-input-textarea"
                                          x-model="chatInput"
                                          rows="2"
                                          @input="onChatInput($event)"
                                          @keydown.arrow-up="onMentionKeydown($event, 'up')"
                                          @keydown.arrow-down="onMentionKeydown($event, 'down')"
                                          @keydown.escape="closeMention()"
                                          @keydown.enter.exact.prevent="onChatEnter()"
                                          @keydown.enter.shift="" @keydown.enter.meta="" @keydown.enter.ctrl=""
                                          placeholder="メッセージを入力 (@で担当者をメンション / Enterで送信 / Shift+Enterで改行)"
                                          class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-300 resize-none"></textarea>
                                <button @click="sendChatComment()"
                                        :disabled="!chatInput.trim() || chatSending"
                                        class="h-10 w-10 inline-flex items-center justify-center rounded-xl text-white shadow-md disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
                                        style="background-color:#10b981;">
                                    <i class="fas" :class="chatSending ? 'fa-spinner animate-spin' : 'fa-paper-plane'"></i>
                                </button>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1.5">
                                <i class="fas fa-at mr-1"></i>
                                <span class="font-bold">@名前</span> で担当者にメンションできます
                            </p>
                        </div>
                    </aside>
                </div>
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
                 class="px-5 py-3 rounded-xl shadow-2xl text-sm font-semibold flex items-center gap-3 max-w-md pointer-events-auto animate-in slide-in-from-bottom duration-200">
                <i class="fas" :class="t.type === 'success' ? 'fa-check-circle' : (t.type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle')"></i>
                <span x-text="t.message" class="whitespace-pre-line"></span>
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
        statusLabels: { inbox: '受信', hold: '保留', completed: '完了', pending: '承認待ち' },
        threadEmails: [], threadMerges: [], expandedEmailIds: [],
        // チャット関連 (スレッド毎)
        chatOpen: false, chatComments: [], chatLoading: false, chatInput: '', chatSending: false,
        chatPollIntervalId: null,
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
        jsonHeaders() {
            return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' };
        },
        toast(message, type = 'info') {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, type });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 3500);
        },

        async init() {
            await Promise.all([
                this.loadThreads(),
                this.loadUsers()
            ]);
            window.addEventListener('resize', () => this.updateVirtualViewport());
            this.$nextTick(() => this.updateVirtualViewport());

            // 作成専用ウィンドウからの送信完了通知を購読
            window.addEventListener('message', (event) => {
                if (event.origin !== window.location.origin) return;
                if (!event.data || event.data.type !== 'rice-mail-sent') return;
                this.fetchEmails(true);
                this.toast('メールを送信しました', 'success');
                if (this.selectedThreadId) {
                    this.loadThread(this.selectedThreadId);
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
            this.stopChatPolling();
            this.closeMention();
        },

        // チャットパネルの開閉
        toggleChatPanel() {
            if (!this.selectedThreadId) {
                this.toast('スレッドを選択してください', 'error');
                return;
            }
            this.chatOpen = !this.chatOpen;
            if (this.chatOpen) {
                this.loadChatComments();
                this.startChatPolling();
            } else {
                this.stopChatPolling();
            }
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
                const res = await fetch(`/threads/${this.selectedThreadId}/comments`, {
                    headers: { 'Accept': 'application/json' }
                });
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
                    this.toast(data.message || '送信に失敗しました', 'error');
                    return;
                }
                if (data.comment) this.chatComments.push(data.comment);
                this.chatInput = '';
                this.closeMention();
                this.$nextTick(() => this.scrollChatToBottom());
            } catch (e) {
                this.toast('通信エラー: ' + (e.message || ''), 'error');
            } finally {
                this.chatSending = false;
            }
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
                // チャットパネルが開いていれば、新スレッドのチャットを取得
                if (this.chatOpen) {
                    this.loadChatComments();
                } else {
                    // 件数バッジ用に裏で件数のみ取得
                    this.loadChatComments().catch(() => {});
                }
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
