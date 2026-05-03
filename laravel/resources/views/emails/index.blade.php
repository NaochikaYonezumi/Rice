@extends('layouts.fullpage')
@section('title', 'Rice Mail - 受信トレイ')

@section('content')
<div class="flex h-screen bg-white overflow-hidden text-gray-800 font-sans" x-data="emailApp()" x-init="init()" x-cloak>

    {{-- 1カラム目: サイドバー --}}
    <div :style="'width:' + sidebarWidth + 'px'"
         class="flex-shrink-0 border-r border-gray-800 bg-gray-900 flex flex-col items-center py-6 gap-3 z-50 relative transition-all duration-300">
        <button @click="navPanelOpen = !navPanelOpen"
                :class="navPanelOpen ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white'"
                class="p-3 rounded-xl transition-all hover:bg-gray-800 flex items-center justify-center shadow-lg"
                title="ステータスショートカット">
            <i class="fas fa-folder fa-lg"></i>
        </button>

        <div class="w-8 h-px bg-gray-800 my-1"></div>

        <a href="{{ route('emails.index') }}" class="p-3 rounded-xl text-blue-400 bg-gray-800/60 flex items-center justify-center shadow-lg" title="メール">
            <i class="fas fa-envelope fa-lg"></i>
        </a>
        <a href="{{ route('attachments.index') }}" class="p-3 rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 flex items-center justify-center transition-all" title="添付ファイル">
            <i class="fas fa-paperclip fa-lg"></i>
        </a>
        <a href="{{ route('approvals.index') }}" class="p-3 rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 flex items-center justify-center transition-all" title="承認">
            <i class="fas fa-check-circle fa-lg"></i>
        </a>
        <a href="{{ route('chat.index') }}" class="p-3 rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 flex items-center justify-center transition-all" title="Rice Chat">
            <i class="fas fa-comments fa-lg"></i>
        </a>
        <a href="{{ route('knowledge.index') }}" class="p-3 rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 flex items-center justify-center transition-all" title="ナレッジベース">
            <i class="fas fa-book fa-lg"></i>
        </a>
        <a href="{{ route('reports.index') }}" class="p-3 rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 flex items-center justify-center transition-all" title="レポート">
            <i class="fas fa-chart-bar fa-lg"></i>
        </a>

        <div class="mt-auto flex flex-col items-center gap-3 mb-2">
            <button @click="fetchEmails()" :disabled="fetching"
                    class="p-3 rounded-xl text-gray-400 hover:text-blue-400 hover:bg-gray-800 transition-all"
                    title="メールを同期">
                <i class="fas fa-sync-alt fa-lg" :class="fetching ? 'animate-spin text-blue-400' : ''"></i>
            </button>
            @auth
            @if(auth()->user()->isAdmin())
            <a href="{{ route('settings.mail') }}" class="p-3 rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 flex items-center justify-center transition-all" title="設定">
                <i class="fas fa-cog fa-lg"></i>
            </a>
            @endif
            <form action="{{ route('logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="p-3 rounded-xl text-gray-400 hover:text-red-400 hover:bg-gray-800 flex items-center justify-center transition-all" title="ログアウト ({{ auth()->user()->name }})">
                    <i class="fas fa-sign-out-alt fa-lg"></i>
                </button>
            </form>
            @endauth
        </div>
        {{-- リサイズハンドル --}}
        <div class="absolute top-0 right-0 w-1 h-full cursor-col-resize hover:bg-blue-500 z-50" @mousedown.prevent="startResizeSidebar($event)"></div>
    </div>

    {{-- メインコンテンツエリア --}}
    <div class="flex flex-1 min-w-0 overflow-hidden">
        
        {{-- 2カラム目: ショートカットパネル --}}
        <div x-show="navPanelOpen"
             :style="'width:' + navPanelWidth + 'px'"
             class="flex-shrink-0 border-r border-gray-200 bg-gray-50 flex flex-col overflow-hidden z-30 relative">
            <div class="px-6 py-5 border-b border-gray-200 bg-white">
                <h2 class="text-sm font-bold text-gray-700">ショートカット</h2>
                <p class="text-xs text-gray-400 mt-0.5">クリックで絞り込み</p>
            </div>
            <div class="flex-1 overflow-y-auto py-3 custom-scrollbar">
                <nav class="px-3 space-y-1">
                    <button type="button" @click="goToShortcut('inbox')"
                            :class="!pinnedOnlyMode && !allStatusMode && leftTab === 'inbox' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100'"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors">
                        <span class="flex items-center gap-3"><i class="fas fa-inbox text-blue-500 w-4"></i> 受信トレイ</span>
                    </button>
                    <button type="button" @click="goToShortcut('hold')"
                            :class="!pinnedOnlyMode && !allStatusMode && leftTab === 'hold' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100'"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors">
                        <span class="flex items-center gap-3"><i class="fas fa-pause-circle text-amber-500 w-4"></i> 保留</span>
                    </button>
                    <button type="button" @click="goToShortcut('completed')"
                            :class="!pinnedOnlyMode && !allStatusMode && leftTab === 'completed' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100'"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors">
                        <span class="flex items-center gap-3"><i class="fas fa-check-circle text-green-500 w-4"></i> 完了</span>
                    </button>
                    <button type="button" @click="goToShortcut('pending')"
                            :class="!pinnedOnlyMode && !allStatusMode && leftTab === 'pending' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100'"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors">
                        <span class="flex items-center gap-3"><i class="fas fa-hourglass-half text-orange-500 w-4"></i> 承認待ち</span>
                    </button>
                    <div class="border-t border-gray-200 my-2"></div>
                    <button type="button" @click="goToShortcut('pinned')"
                            :class="pinnedOnlyMode ? 'bg-amber-50 text-amber-700' : 'text-gray-700 hover:bg-gray-100'"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors">
                        <span class="flex items-center gap-3"><i class="fas fa-thumbtack text-amber-500 w-4"></i> ピン留め</span>
                    </button>
                    <button type="button" @click="goToShortcut('all')"
                            :class="allStatusMode ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100'"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-semibold transition-colors">
                        <span class="flex items-center gap-3"><i class="fas fa-layer-group text-gray-500 w-4"></i> すべて表示</span>
                    </button>
                </nav>
            </div>
            <div class="absolute top-0 right-0 w-1 h-full cursor-col-resize hover:bg-blue-500 z-50" @mousedown.prevent="startResizeNav($event)"></div>
        </div>

        {{-- 3カラム目: スレッド一覧 --}}
        <div class="flex flex-col flex-shrink-0 overflow-hidden bg-white border-r border-gray-200 relative z-20 shadow-sm"
             :style="'width:' + threadWidth + 'px'">
            
            {{-- 操作ヘッダー --}}
            <div class="shrink-0 px-4 py-3 border-b border-gray-200 bg-white flex flex-col gap-3 relative">
                <div class="flex justify-between items-center gap-2">
                    <div class="flex items-center gap-2">
                        <button @click="fetchEmails()" class="text-gray-400 hover:text-blue-600 p-2" title="一覧を更新"><i class="fas fa-sync-alt" :class="fetching ? 'animate-spin text-blue-600' : ''"></i></button>
                        <label class="relative inline-flex items-center cursor-pointer ml-1" title="ステータスタブの絞り込みを無効にして全件を表示">
                            <input type="checkbox" id="all-status-toggle" :checked="allStatusMode" @change="toggleAllStatus()" class="sr-only peer">
                            <div class="w-10 h-5 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                            <span class="ml-2 text-xs font-semibold text-gray-600">すべて</span>
                        </label>
                        <button @click="togglePinnedOnly()"
                                :class="pinnedOnlyMode ? 'bg-amber-100 text-amber-700 border-amber-300' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50'"
                                class="ml-1 px-3 py-1.5 rounded-lg border text-xs font-semibold transition-all flex items-center gap-1.5 shadow-sm">
                            <i class="fas fa-thumbtack"></i> ピン留め
                        </button>
                    </div>
                    <button @click="openCompose()"
                            class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg font-semibold shadow-md hover:bg-blue-700 transition-all flex items-center gap-2">
                        <i class="fas fa-plus"></i> 新規作成
                    </button>
                </div>
                {{-- 担当者フィルター --}}
                <div class="flex items-center gap-2 px-1">
                    <label class="text-xs font-semibold text-gray-500 shrink-0">担当者</label>
                    <select @change="setAssigneeFilter($event.target.value)"
                            class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-xs font-medium text-gray-700 focus:ring-2 focus:ring-blue-100 focus:border-blue-300 outline-none cursor-pointer">
                        <option value="all">全員を表示</option>
                        <option value="none">未設定</option>
                        <template x-for="user in users" :key="user.id">
                            <option :value="user.id" :selected="assigneeFilterId == user.id" x-text="user.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            {{-- 複数選択アクションバー --}}
            <template x-if="selectionMode">
                <div class="absolute inset-x-0 top-0 bg-blue-600 text-white flex flex-col gap-2 shadow-lg animate-in slide-in-from-top duration-300 px-4 py-3 z-[100]">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-bold" x-text="selectedThreadIds.length + ' 件選択中'"></span>
                        <button @click="cancelSelection()" class="text-white/80 hover:text-white text-xs font-semibold px-2 py-1 rounded hover:bg-white/10">キャンセル</button>
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button @click="updateSelectedStatus('completed')" class="bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg text-xs font-semibold border border-white/30 transition-all">完了</button>
                        <button @click="updateSelectedStatus('hold')" class="bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg text-xs font-semibold border border-white/30 transition-all">保留</button>
                        <button @click="updateSelectedStatus('inbox')" class="bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg text-xs font-semibold border border-white/30 transition-all">未対応</button>
                        <button @click="batchPinSelected(true)" class="bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg text-xs font-semibold border border-white/30 transition-all"><i class="fas fa-thumbtack mr-1"></i>ピン留</button>
                        <button @click="batchPinSelected(false)" class="bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg text-xs font-semibold border border-white/30 transition-all"><i class="fas fa-unlink mr-1"></i>ピン外</button>
                        <button @click="batchDeleteSelected()" class="bg-red-500/80 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold border border-red-400/50 transition-all" title="削除"><i class="fas fa-trash"></i></button>
                        <button @click="mergeSelected()" x-show="selectedThreadIds.length > 1" class="bg-amber-500/80 hover:bg-amber-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold border border-amber-400/50 transition-all"><i class="fas fa-object-group mr-1"></i>マージ</button>
                    </div>
                </div>
            </template>

            {{-- ステータスタブ --}}
            <div class="shrink-0 px-3 py-2 border-b border-gray-200 bg-gray-50 flex items-center gap-2">
                <div class="flex items-center gap-1 bg-gray-200/60 p-1 rounded-lg shadow-inner flex-1 overflow-hidden">
                    <template x-for="tab in ['inbox', 'hold', 'completed', 'pending']">
                        <button @click="setLeftTab(tab)"
                                :class="leftTab === tab ? 'bg-white shadow-sm text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                                class="flex-1 py-1.5 rounded-md text-xs font-semibold transition-all truncate"
                                x-text="statusLabels[tab]"></button>
                    </template>
                </div>
                <button @click="toggleSort()" class="p-2 text-gray-400 hover:text-blue-600" :title="sortOrder === 'desc' ? '新しい順' : '古い順'">
                    <i class="fas" :class="sortOrder === 'desc' ? 'fa-sort-amount-down' : 'fa-sort-amount-up'"></i>
                </button>
            </div>

            {{-- 仮想スクロールリスト --}}
            <div class="flex-1 min-h-0 overflow-y-auto bg-white custom-scrollbar relative" id="email-list-container" @scroll.passive="handleScroll()">
                <template x-if="!threadsLoading && threads.length === 0">
                    <div class="flex flex-col items-center justify-center h-full px-6 py-10 text-center">
                        <div class="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center text-gray-400 mb-3">
                            <i class="fas fa-inbox fa-lg"></i>
                        </div>
                        <p class="text-sm font-semibold text-gray-600">表示するメールはありません</p>
                        <p class="text-xs text-gray-400 mt-1">フィルタを変更するか、左下の同期ボタンで取得してください。</p>
                    </div>
                </template>
                <div :style="'height: ' + totalListHeight + 'px; position: relative;'" x-show="threads.length > 0">
                    <div :style="'transform: translateY(' + listPaddingTop + 'px)'">
                        <template x-for="thread in visibleThreads" :key="thread.id">
                            <div @mousedown="startLongPress(thread, $event)" 
                                 @mouseup="cancelLongPress()" 
                                 @mouseleave="cancelLongPress()" 
                                 @click="if(!isLongPressing){ selectionMode ? toggleSelection(thread) : loadThread(thread.id) }"
                                 class="email-item w-full cursor-pointer border-b border-gray-100 hover:bg-blue-50 transition-all duration-200 thread-list-row relative"
                                 :style="'height: ' + virtualScroll.rowHeight + 'px'"
                                 :class="selectedThreadId === thread.id ? 'bg-blue-50 ring-1 ring-inset ring-blue-200' : (selectedThreadIds.includes(thread.id) ? 'bg-blue-50/50' : '')">
                                
                                <div class="px-5 flex flex-col justify-center h-full">
                                    <div class="flex justify-between items-center mb-1">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <template x-if="selectionMode">
                                                <input type="checkbox" class="w-3.5 h-3.5 text-blue-600 border-gray-300 rounded focus:ring-blue-500 shrink-0"
                                                       :checked="selectedThreadIds.includes(thread.id)" @click.stop="toggleSelection(thread)">
                                            </template>
                                            <i x-show="thread.is_pinned" class="fas fa-thumbtack text-amber-500 text-[10px] shrink-0"></i>
                                            <i x-show="thread.thread_merges_count > 0" class="fas fa-object-group text-blue-500 text-[10px] shrink-0" title="マージ済み"></i>
                                            <span class="text-[13px] 2xl:text-base font-black text-gray-900 truncate" x-text="thread.latest_email?.from_label || '不明な送信者'"></span>                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <template x-if="allStatusMode">
                                                <div class="px-2 py-0.5 rounded text-[8px] 2xl:text-[10px] font-black uppercase shadow-sm border"
                                                    :class="{
                                                        'bg-blue-100 text-blue-700 border-blue-200': thread.status === 'inbox' || !thread.status,
                                                        'bg-amber-100 text-amber-800 border-amber-200': thread.status === 'hold',
                                                        'bg-green-100 text-green-800 border-green-200': thread.status === 'completed',
                                                        'bg-orange-100 text-orange-800 border-orange-200': thread.status === 'pending'
                                                    }">
                                                    <span x-text="statusLabels[thread.status] || '受信'"></span>
                                                </div>
                                            </template>
                                            <span class="text-[10px] 2xl:text-xs text-gray-400 font-medium" x-text="thread.last_email_at"></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="text-[12px] 2xl:text-sm text-gray-500 truncate font-medium flex-1" x-text="thread.subject"></div>
                                        <div x-show="thread.assignee" class="shrink-0 flex items-center gap-1 bg-gray-100 px-2 py-0.5 rounded text-[9px] 2xl:text-[11px] font-black text-gray-600 border border-gray-200 uppercase tracking-tighter shadow-sm">
                                            <i class="fas fa-user-circle text-gray-400"></i>
                                            <span x-text="thread.assignee?.name"></span>
                                        </div>
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

        {{-- 4カラム目: ワークスペース --}}
        <div class="flex-1 flex flex-col min-w-0 bg-white z-10 relative">
            
            <div x-show="!selectedThread" class="flex-1 flex flex-col items-center justify-center bg-gray-50 px-6">
                <div class="w-20 h-20 bg-white rounded-3xl shadow-xl flex items-center justify-center text-gray-300 mb-6">
                    <i class="fas fa-envelope-open-text fa-2x"></i>
                </div>
                <p class="text-base font-semibold text-gray-700">メールを選択してください</p>
                <p class="text-xs text-gray-400 mt-2 max-w-xs text-center leading-relaxed">左の一覧から選ぶと、ここに本文が表示されます。新しく書き始めるには右上の「新規作成」ボタンを押してください。</p>
                <div class="mt-6 flex items-center gap-2 text-[11px] text-gray-400">
                    <span class="px-2 py-1 bg-white border border-gray-200 rounded font-mono">↑ ↓</span>
                    <span>でスレッドを移動 ・</span>
                    <span class="px-2 py-1 bg-white border border-gray-200 rounded font-mono">長押し</span>
                    <span>で複数選択</span>
                </div>
            </div>

            <div x-show="selectedThread" class="flex-1 flex flex-col h-full overflow-hidden animate-in fade-in duration-300">
                {{-- ヘッダー --}}
                <div class="shrink-0 border-b border-gray-200 bg-white z-20 flex flex-col">
                    {{-- 1行目: アクションボタン --}}
                    <div class="px-8 py-3 flex items-center justify-between border-b border-gray-50 bg-gray-50/30">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-1 mr-2 border-r border-gray-100 pr-4" x-show="selectedThread">
                                <button @click="goToPrevThread()" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-white rounded-lg transition-all" title="前のスレッド"><i class="fas fa-chevron-up"></i></button>
                                <button @click="goToNextThread()" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-white rounded-lg transition-all" title="次のスレッド"><i class="fas fa-chevron-down"></i></button>
                            </div>
                            <div class="flex items-center gap-2" x-show="selectedThread">
                                <button @click="updateThreadStatus(selectedThread, 'completed')"
                                    class="bg-green-600 text-white text-xs font-bold px-4 py-2 rounded-lg shadow-md hover:bg-green-700 transition-all flex items-center gap-2">
                                    <i class="fas fa-check-double"></i> 完了
                                </button>
                                <button @click="if(threadEmails.length > 0) openReplyForEmail(threadEmails[0])"
                                    class="bg-blue-600 text-white text-xs font-bold px-4 py-2 rounded-lg shadow-md hover:bg-blue-700 transition-all flex items-center gap-2">
                                    <i class="fas fa-reply"></i> 返信
                                </button>
                                <button @click="if(threadEmails.length > 0) openReplyForEmail(threadEmails[0], true)"
                                    class="bg-white text-blue-600 border border-blue-100 text-xs font-bold px-4 py-2 rounded-lg shadow-sm hover:bg-blue-50 transition-all flex items-center gap-2">
                                    <i class="fas fa-reply-all"></i> 全員に返信
                                </button>
                                {{-- 三点リーダーメニュー --}}
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" @click.away="open = false" 
                                        class="w-9 h-9 flex items-center justify-center bg-white border border-gray-200 rounded-xl text-gray-400 hover:text-blue-600 hover:border-blue-200 transition-all shadow-sm">
                                        <i class="fas fa-ellipsis-h"></i>
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
                                        <div class="px-4 py-2 text-[9px] font-black text-gray-400 uppercase tracking-widest">担当者を設定</div>
                                        <div class="max-h-48 overflow-y-auto custom-scrollbar">
                                            <button @click="updateAssignee(null); open = false" class="w-full text-left px-4 py-2 text-[10px] font-bold text-gray-500 hover:bg-gray-50 italic">未設定</button>
                                            <template x-for="user in users" :key="user.id">
                                                <button @click="updateAssignee(user.id); open = false" class="w-full text-left px-4 py-2 text-[10px] font-bold text-gray-700 hover:bg-blue-50 flex items-center justify-between">
                                                    <span x-text="user.name"></span>
                                                    <i x-show="selectedThread?.assigned_user_id == user.id" class="fas fa-check text-blue-500"></i>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button @click="closeWorkspace()" class="text-gray-300 hover:text-red-500 transition-colors p-2"><i class="fas fa-times fa-lg"></i></button>
                        </div>
                    </div>
                    {{-- 2行目: 件名 --}}
                    <div class="px-8 py-5 flex items-center gap-4 min-w-0">
                        <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg shrink-0">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-lg font-black tracking-tight truncate text-gray-800" x-text="selectedThread?.subject"></h2>
                            <div x-show="selectedThread?.assignee" class="mt-0.5 flex items-center gap-2">
                                <span class="text-xs font-semibold text-gray-500">担当:</span>
                                <span class="bg-blue-50 text-blue-600 text-xs font-semibold px-2 py-0.5 rounded-lg border border-blue-100" x-text="selectedThread.assignee?.name"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex-1 flex min-h-0 relative bg-gray-50/30">
                    <div class="flex-1 overflow-y-auto p-10 custom-scrollbar">
                        
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

                                {{-- 各メール表示 --}}
                                <template x-for="email in threadEmails" :key="email.id">
                                    <div class="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm hover:shadow-md transition-shadow group">
                                        <div class="px-6 py-4 flex items-center justify-between cursor-pointer hover:bg-gray-50/50 transition-colors" @click="toggleEmailExpand(email.id)">
                                            <div class="flex items-center gap-4 min-w-0">
                                                <div class="w-10 h-10 bg-gray-100 rounded-2xl flex items-center justify-center text-gray-500 font-black text-lg shadow-inner" x-text="(email.from_label || '?')[0]"></div>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-black text-gray-900" x-text="email.from_label"></p>
                                                    <p class="text-[11px] text-gray-400 font-medium truncate" x-text="email.from_address + ' から ' + email.to_address"></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-4 shrink-0">
                                                <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest" x-text="email.received_at"></span>
                                                <div class="flex items-center gap-2">
                                                    <button @click.stop="openReplyForEmail(email)" class="bg-blue-50 text-blue-600 px-4 py-1.5 rounded-full font-black text-[11px] 2xl:text-xs shadow-sm hover:bg-blue-600 hover:text-white transition-all flex items-center gap-2 uppercase tracking-widest"><i class="fas fa-reply"></i> 返信</button>
                                                    <button @click.stop="openReplyForEmail(email, true)" class="bg-white text-blue-600 border border-blue-100 px-4 py-1.5 rounded-full font-black text-[11px] 2xl:text-xs shadow-sm hover:bg-blue-50 transition-all flex items-center gap-2 uppercase tracking-widest"><i class="fas fa-reply-all"></i> 全員</button>
                                                </div>
                                                <i class="fas fa-chevron-down text-gray-300 group-hover:text-blue-500 transition-all" :class="expandedEmailIds.includes(email.id) ? 'rotate-180' : ''"></i>
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
                                                    <div class="flex wrap gap-2">
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
                </div>
            </div>
        </div>
    </div>

    {{-- 同期エラーモーダル --}}
    <template x-if="syncError">
        <div class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-md p-4" @click.self="syncError = null">
            <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg overflow-hidden animate-in zoom-in duration-200 text-center">
                <div class="bg-red-50 px-8 py-6 flex flex-col items-center gap-4 border-b border-red-100">
                    <div class="w-12 h-12 bg-red-100 rounded-2xl flex items-center justify-center text-red-600 shadow-inner"><i class="fas fa-exclamation-triangle fa-lg"></i></div>
                    <h3 class="text-lg font-black text-red-900 uppercase tracking-tighter" x-text="syncError.message"></h3>
                </div>
                <div class="px-8 py-6 space-y-4">
                    <p class="bg-gray-50 rounded-xl p-4 border border-gray-100 text-left text-gray-800 leading-relaxed text-sm" x-text="syncError.detail"></p>
                    <div x-data="{ expanded: false }" class="text-left"><button @click="expanded = !expanded" class="text-[10px] font-black text-blue-500 uppercase tracking-widest hover:text-blue-700 transition-colors flex items-center gap-1"><i class="fas" :class="expanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i> スタックトレースを表示</button><div x-show="expanded" x-collapse class="mt-2 text-left"><pre class="bg-gray-900 text-gray-300 p-4 rounded-xl text-[9px] overflow-auto max-h-40 custom-scrollbar font-mono leading-relaxed" x-text="syncError.stack"></pre></div></div>
                </div>
                <div class="px-8 py-6 bg-gray-50 flex gap-3 border-t border-red-50"><button @click="syncError = null" class="flex-1 py-4 text-xs font-black text-gray-400 hover:bg-white rounded-2xl border border-transparent uppercase">閉じる</button><button @click="fetchEmails()" class="flex-[2] bg-red-600 text-white py-4 rounded-2xl font-black text-xs shadow-xl uppercase"><i class="fas fa-sync-alt"></i> リトライ</button></div>
            </div>
        </div>
    </template>

    {{-- トースト通知 --}}
    <div class="fixed bottom-6 right-6 z-[3000] flex flex-col gap-2 pointer-events-none">
        <template x-for="t in toasts" :key="t.id">
            <div :class="{
                    'bg-green-600 text-white': t.type === 'success',
                    'bg-red-600 text-white': t.type === 'error',
                    'bg-gray-900 text-white': t.type === 'info' || (t.type !== 'success' && t.type !== 'error')
                 }"
                 class="px-5 py-3 rounded-xl shadow-2xl text-sm font-semibold flex items-center gap-3 max-w-md pointer-events-auto animate-in slide-in-from-bottom duration-200">
                <i class="fas" :class="t.type === 'success' ? 'fa-check-circle' : (t.type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle')"></i>
                <span x-text="t.message" class="whitespace-pre-line"></span>
            </div>
        </template>
    </div>

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

</div>

<script>
function emailApp() {
    return {
        sidebarWidth: parseInt(localStorage.getItem('sidebarWidth')) || 64,
        navPanelWidth: parseInt(localStorage.getItem('navPanelWidth')) || (window.innerWidth >= 1920 ? 320 : 280),
        threadWidth: parseInt(localStorage.getItem('threadWidth')) || (window.innerWidth >= 1920 ? 450 : 380),
        navPanelOpen: false, fetching: false,
        selectedThreadId: null, selectedThread: null,
        leftTab: 'inbox', searchQuery: '',
        allStatusMode: (() => { try { return JSON.parse(localStorage.getItem('allStatusMode')) === true; } catch(_) { return false; } })(),
        pinnedOnlyMode: {{ isset($isPinnedView) && $isPinnedView ? 'true' : 'false' }},
        assigneeFilterId: localStorage.getItem('assigneeFilterId') || 'all',
        sortOrder: 'desc',
        statusLabels: { inbox: '受信', hold: '保留', completed: '完了', pending: '承認待ち' },
        threadEmails: [], threadMerges: [], expandedEmailIds: [],
        selectionMode: false, selectedThreadIds: [], longPressTimer: null, isLongPressing: false,
        mergeModalOpen: false, mergeTargetId: null,
        threads: [], threadsLoading: false, syncError: null,
        users: [], // 招待管理から一元化されたユーザーリスト
        toasts: [],
        virtualScroll: { startIndex: 0, endIndex: 30, rowHeight: 95, viewportHeight: 600, buffer: 10 },
        pollIntervalId: null, pollFailCount: 0, basePollDelay: 60000, maxPollDelay: 300000, currentPollDelay: 60000,
        messageListener: null,

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
            this.messageListener = (event) => {
                if (event.origin !== window.location.origin) return;
                if (!event.data || event.data.type !== 'rice-mail-sent') return;
                this.fetchEmails(true);
                this.toast('メールを送信しました', 'success');
            };
            window.addEventListener('message', this.messageListener);

            this.setupPolling();
        },

        setupPolling() {
            this.startPolling();
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.fetchEmails(true); // 即時受信
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
            if (!isBackground) {
                this.threadsLoading = true;
            }
            const params = new URLSearchParams({
                all_status: this.allStatusMode ? '1' : '0',
                is_pinned: this.pinnedOnlyMode ? '1' : '0',
                status: this.leftTab,
                sort_order: this.sortOrder
            });
            if (this.assigneeFilterId !== 'all') {
                params.append('assigned_user_id', this.assigneeFilterId);
            }

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
                if (!isBackground) {
                    this.threadsLoading = false;
                }
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
        goToShortcut(key) {
            if (key === 'pinned') {
                this.pinnedOnlyMode = true;
                this.allStatusMode = false;
            } else if (key === 'all') {
                this.allStatusMode = true;
                this.pinnedOnlyMode = false;
            } else {
                this.pinnedOnlyMode = false;
                this.allStatusMode = false;
                this.leftTab = key;
            }
            try { localStorage.setItem('allStatusMode', JSON.stringify(this.allStatusMode)); } catch(_) {}
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

                // Exponential backoff (capped at max delay)
                this.pollFailCount = Math.min(this.pollFailCount + 1, 8);
                const delay = this.basePollDelay * Math.pow(2, this.pollFailCount);
                this.currentPollDelay = Math.min(delay, this.maxPollDelay);
            } finally {
                if (!isBackground) {
                    this.fetching = false;
                }
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
        },

        async loadThread(id) {
            this.selectedThreadId = id; this.expandedEmailIds = [];
            try {
                const res = await fetch(`/threads/${id}`, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                this.selectedThread = data.thread;

                // 新しいメールが一番上（received_at の降順、IDはタイブレーカー）
                this.threadEmails = (data.emails || []).slice().sort((a, b) => {
                    const ta = a.received_at ? Date.parse(a.received_at.replace(/\//g, '-')) : 0;
                    const tb = b.received_at ? Date.parse(b.received_at.replace(/\//g, '-')) : 0;
                    if (tb !== ta) return tb - ta;
                    return (b.id || 0) - (a.id || 0);
                });
                this.threadMerges = data.merges || [];

                // 読み込み時に「一番上(最新)」のメールを自動展開
                if (this.threadEmails.length > 0) this.expandedEmailIds.push(this.threadEmails[0].id);
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

        startResizeSidebar(e) {
            const startX = e.clientX, startW = this.sidebarWidth;
            const onMove = (me) => { this.sidebarWidth = Math.max(64, Math.min(200, startW + (me.clientX - startX))); };
            const onUp = () => { localStorage.setItem('sidebarWidth', this.sidebarWidth); document.removeEventListener('mousemove', onMove); document.removeEventListener('mouseup', onUp); };
            document.addEventListener('mousemove', onMove); document.addEventListener('mouseup', onUp);
        },
        startResizeNav(e) {
            const startX = e.clientX, startW = this.navPanelWidth;
            const onMove = (me) => { this.navPanelWidth = Math.max(200, Math.min(500, startW + (me.clientX - startX))); };
            const onUp = () => { localStorage.setItem('navPanelWidth', this.navPanelWidth); document.removeEventListener('mousemove', onMove); document.removeEventListener('mouseup', onUp); };
            document.addEventListener('mousemove', onMove); document.addEventListener('mouseup', onUp);
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
</style>
@endsection
