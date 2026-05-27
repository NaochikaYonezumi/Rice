@extends('layouts.app')
@section('title', 'Rice Mail - 受信トレイ')

@section('content')
<div class="flex h-screen bg-white overflow-hidden text-gray-800 font-sans" x-data="emailApp()" x-init="init()" x-cloak>

    {{-- 1カラム目: サイドバー --}}
    <div :style="'width:' + sidebarWidth + 'px'" 
         class="flex-shrink-0 border-r border-gray-200 bg-gray-900 flex flex-col items-center py-6 gap-6 z-50 relative transition-all duration-300">
        <button @click="navPanelOpen = !navPanelOpen" 
                :class="navPanelOpen ? 'bg-blue-600 text-white' : 'text-gray-500'" 
                class="p-3 rounded-xl transition-all hover:bg-gray-800 flex items-center justify-center shadow-lg">
            <i class="fas fa-folder fa-lg"></i>
        </button>
        
        <div class="mt-auto flex flex-col items-center gap-4 mb-4">
            <button @click="fetchEmails()" :disabled="fetching" 
                    class="p-3 text-gray-500 hover:text-blue-400 transition-all" 
                    :class="fetching ? 'animate-spin text-blue-500' : ''" title="同期">
                <i class="fas fa-sync-alt fa-lg"></i>
            </button>
        </div>
        {{-- リサイズハンドル --}}
        <div class="absolute top-0 right-0 w-1 h-full cursor-col-resize hover:bg-blue-500 z-50" @mousedown.prevent="startResizeSidebar($event)"></div>
    </div>

    {{-- メインコンテンツエリア --}}
    <div class="flex flex-1 min-w-0 overflow-hidden">
        
        {{-- 2カラム目: フォルダパネル --}}
        <div x-show="navPanelOpen" 
             :style="'width:' + navPanelWidth + 'px'" 
             class="flex-shrink-0 border-r border-gray-200 bg-gray-50 flex flex-col overflow-hidden z-30 relative">
            <div class="px-6 py-6 border-b border-gray-200 bg-white">
                <h2 class="text-xs 2xl:text-sm font-black uppercase tracking-widest text-gray-500">フォルダ</h2>
            </div>
            <div class="flex-1 overflow-y-auto py-4 custom-scrollbar">
                <div class="px-4 space-y-1">
                    <div class="text-sm text-gray-500 p-2 italic">フォルダを読み込み中...</div>
                </div>
            </div>
            <div class="absolute top-0 right-0 w-1 h-full cursor-col-resize hover:bg-blue-500 z-50" @mousedown.prevent="startResizeNav($event)"></div>
        </div>

        {{-- 3カラム目: スレッド一覧 --}}
        <div class="flex flex-col flex-shrink-0 overflow-hidden bg-white border-r border-gray-200 relative z-20 shadow-sm"
             :style="'width:' + threadWidth + 'px'">
            
            {{-- 操作ヘッダー --}}
            <div class="shrink-0 px-4 py-4 border-b border-gray-200 bg-white flex flex-col gap-3 relative">
                <div class="flex justify-between items-center gap-2">
                    <div class="flex items-center gap-2">
                        <button @click="fetchEmails()" class="text-gray-400 hover:text-blue-600 p-2" title="一覧を更新"><i class="fas fa-sync-alt" :class="fetching ? 'animate-spin text-blue-600' : ''"></i></button>
                        <label class="relative inline-flex items-center cursor-pointer ml-1">
                            <input type="checkbox" id="all-status-toggle" :checked="allStatusMode" @change="toggleAllStatus()" class="sr-only peer">
                            <div class="w-10 h-5 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                            <span class="ml-2 text-[10px] font-black text-gray-500 uppercase tracking-tighter">全表示</span>
                        </label>
                        <button @click="togglePinnedOnly()" 
                                :class="pinnedOnlyMode ? 'bg-amber-100 text-amber-600 border-amber-200' : 'bg-gray-100 text-gray-400 border-gray-200'"
                                class="ml-2 px-3 py-1 rounded-full border text-[10px] font-black transition-all flex items-center gap-1 uppercase tracking-tighter shadow-sm">
                            <i class="fas fa-thumbtack"></i> ピン留め
                        </button>
                    </div>
                    <button @click="openCompose()" 
                            class="bg-blue-600 text-white text-[11px] px-5 py-2.5 rounded-xl font-black shadow-md hover:bg-blue-700 transition-all flex items-center gap-2">
                        <i class="fas fa-plus"></i> 新規作成
                    </button>
                </div>
                {{-- 担当者フィルター --}}
                <div class="flex items-center gap-2 px-1">
                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest shrink-0">担当者:</label>
                    <select @change="setAssigneeFilter($event.target.value)" 
                            class="flex-1 bg-gray-50 border-0 rounded-lg px-3 py-1.5 text-[10px] font-bold text-gray-700 focus:ring-2 focus:ring-blue-100 outline-none shadow-inner cursor-pointer">
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
            <div class="flex-1 overflow-y-auto bg-white custom-scrollbar relative" id="email-list-container" style="height: 600px;" @scroll.passive="handleScroll()">
                <div :style="'height: ' + totalListHeight + 'px; position: relative;'">
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
            
            <div x-show="!selectedThread && !composeMode" class="flex-1 flex flex-col items-center justify-center bg-gray-50">
                <div class="w-20 h-20 bg-white rounded-3xl shadow-xl flex items-center justify-center text-gray-200 mb-6">
                    <i class="fas fa-paper-plane fa-2x"></i>
                </div>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">閲覧するメールを選択してください</p>
            </div>

            <div x-show="selectedThread || composeMode" class="flex-1 flex flex-col h-full overflow-hidden animate-in fade-in duration-300">
                {{-- ヘッダー --}}
                <div class="shrink-0 border-b border-gray-200 bg-white z-20 flex flex-col">
                    {{-- 1行目: アクションボタン --}}
                    <div class="px-8 py-3 flex items-center justify-between border-b border-gray-50 bg-gray-50/30">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-1 mr-2 border-r border-gray-100 pr-4" x-show="selectedThread && !composeMode">
                                <button @click="goToPrevThread()" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-white rounded-lg transition-all" title="前のスレッド"><i class="fas fa-chevron-up"></i></button>
                                <button @click="goToNextThread()" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-white rounded-lg transition-all" title="次のスレッド"><i class="fas fa-chevron-down"></i></button>
                            </div>
                            <div class="flex items-center gap-2" x-show="selectedThread && !composeMode">
                                <button @click="updateThreadStatus(selectedThread, 'completed')" 
                                    class="bg-green-600 text-white text-[10px] 2xl:text-xs font-black px-4 py-2 rounded-xl shadow-md hover:bg-green-700 transition-all uppercase tracking-widest flex items-center gap-2">
                                    <i class="fas fa-check-double"></i> 完了
                                </button>
                                <button @click="if(threadEmails.length > 0) openReplyForEmail(threadEmails[0])" 
                                    class="bg-blue-600 text-white text-[10px] 2xl:text-xs font-black px-4 py-2 rounded-xl shadow-md hover:bg-blue-700 transition-all uppercase tracking-widest flex items-center gap-2">
                                    <i class="fas fa-reply"></i> 返信
                                </button>
                                <button @click="if(threadEmails.length > 0) openReplyForEmail(threadEmails[0], true)" 
                                    class="bg-white text-blue-600 border border-blue-100 text-[10px] 2xl:text-xs font-black px-4 py-2 rounded-xl shadow-sm hover:bg-blue-50 transition-all uppercase tracking-widest flex items-center gap-2">
                                    <i class="fas fa-reply-all"></i> 全員に返信
                                </button>
                                <button @click="replyAiPanelOpen = !replyAiPanelOpen" 
                                    :class="replyAiPanelOpen ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-600 border border-indigo-200'"
                                    class="text-[10px] font-black px-4 py-2 rounded-xl shadow-sm transition-all uppercase tracking-widest flex items-center gap-2">
                                    <i class="fas fa-magic"></i> AIアシスタント
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
                            <i class="fas" :class="composeMode ? 'fa-pen-fancy' : 'fa-envelope'"></i>
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-lg font-black tracking-tight truncate text-gray-800" x-text="selectedThread?.subject || '新規メッセージ作成'"></h2>
                            <div x-show="selectedThread?.assignee" class="mt-0.5 flex items-center gap-2">
                                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">担当:</span>
                                <span class="bg-blue-50 text-blue-600 text-[10px] font-black px-2 py-0.5 rounded-lg border border-blue-100" x-text="selectedThread.assignee?.name"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex-1 flex min-h-0 relative bg-gray-50/30">
                    <div class="flex-1 overflow-y-auto p-10 custom-scrollbar">
                        
                        {{-- スレッド表示 --}}
                        <template x-if="selectedThread && !composeMode">
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

                        {{-- 新規作成用フォーム --}}
                        <div x-show="composeMode" class="max-w-4xl 2xl:max-w-6xl mx-auto space-y-6 h-full flex flex-col">
                            <div class="space-y-4 shrink-0">
                                <template x-if="sendableAccounts.length > 1">
                                    <div class="relative group">
                                        <label class="text-[9px] font-black text-gray-400 uppercase absolute left-4 top-2 tracking-widest">送信アカウント</label>
                                        <select x-on:change="pickSendableAccount($event.target.value)"
                                                class="w-full pt-7 pb-3 px-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:ring-4 focus:ring-blue-50 transition-all font-bold appearance-none">
                                            <template x-for="acc in sendableAccounts" :key="acc.id ?? 'system'">
                                                <option :value="acc.id ?? ''" :selected="(acc.id ?? null) === replyAccountId" x-text="acc.label + ' <' + acc.from_address + '>'"></option>
                                            </template>
                                        </select>
                                    </div>
                                </template>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="relative group">
                                        <label data-test-id="compose-from-label" class="text-[9px] font-black text-gray-400 uppercase absolute left-4 top-2 tracking-widest">差出人 (From)</label>
                                        <input type="text" x-model="replyFromAddress" class="w-full pt-7 pb-3 px-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:ring-4 focus:ring-blue-50 transition-all font-bold">
                                    </div>
                                    <div class="relative group">
                                        <label data-test-id="compose-to-label" class="text-[9px] font-black text-gray-400 uppercase absolute left-4 top-2 tracking-widest">宛先 (To)</label>
                                        <input type="text" x-model="replyToAddress" class="w-full pt-7 pb-3 px-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:ring-4 focus:ring-blue-50 transition-all font-bold">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="relative group"><label data-test-id="compose-cc-label" class="text-[9px] font-black text-gray-400 uppercase absolute left-4 top-2 tracking-widest">Cc</label><input type="text" x-model="replyCc" class="w-full pt-7 pb-3 px-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:ring-4 focus:ring-blue-50 transition-all font-medium"></div>
                                    <div class="relative group"><label data-test-id="compose-bcc-label" class="text-[9px] font-black text-gray-400 uppercase absolute left-4 top-2 tracking-widest">Bcc</label><input type="text" x-model="replyBcc" class="w-full pt-7 pb-3 px-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:ring-4 focus:ring-blue-50 transition-all font-medium"></div>
                                </div>
                                <div class="relative group">
                                    <label data-test-id="compose-subject-label" class="text-[9px] font-black text-gray-400 uppercase absolute left-4 top-2 tracking-widest">件名</label>
                                    <input type="text" x-model="replySubject" class="w-full pt-7 pb-3 px-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:ring-4 focus:ring-blue-50 transition-all font-black">
                                </div>
                            </div>
                            <div class="flex-1 flex flex-col min-h-[300px]">
                                <textarea x-model="replyBody" class="flex-1 w-full text-base border-0 bg-transparent py-4 outline-none leading-relaxed resize-none font-medium text-gray-700 placeholder-gray-300" placeholder="メッセージを入力してください..."></textarea>
                            </div>
                            
                            <div class="pt-6 border-t border-gray-100 flex items-center justify-between sticky bottom-0 bg-white py-6 z-10">
                                <div class="flex items-center gap-4">
                                    <input type="file" multiple @change="handleFileSelect($event)" class="hidden" id="compose-file-input">
                                    <label for="compose-file-input" class="cursor-pointer bg-gray-50 hover:bg-gray-100 text-gray-500 px-5 py-3 rounded-2xl text-[11px] font-black border border-gray-100 transition-all flex items-center gap-2">
                                        <i class="fas fa-paperclip"></i> 添付
                                    </label>
                                    <div class="flex wrap gap-2">
                                        <template x-for="(f, i) in selectedFiles" :key="i">
                                            <div class="bg-blue-50 text-blue-600 px-3 py-2 rounded-xl text-[10px] font-black flex items-center gap-2 border border-blue-100 animate-in zoom-in duration-200">
                                                <span x-text="f.name"></span>
                                                <button @click="removeSelectedFile(i)" class="hover:text-red-500"><i class="fas fa-times-circle"></i></button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="replyAiPanelOpen = !replyAiPanelOpen" :class="replyAiPanelOpen ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-600 border border-indigo-200 hover:bg-indigo-50'" class="px-6 py-3 rounded-[2rem] font-black text-xs transition-all shadow-md flex items-center gap-2">
                                        <i class="fas fa-magic"></i> AIアシスタント
                                    </button>
                                    <button @click="submitReply()" :disabled="!replyBody || sendingReply"
                                        class="bg-blue-600 text-white px-10 py-4 rounded-[2rem] font-black text-xs shadow-2xl hover:bg-blue-700 transition-all flex items-center gap-4 disabled:opacity-50">
                                        <span x-show="!sendingReply">承認を依頼する</span>
                                        <span x-show="sendingReply">送信中...</span>
                                        <i class="fas fa-paper-plane" x-show="!sendingReply"></i>
                                        <i class="fas fa-spinner animate-spin" x-show="sendingReply"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 返信サイドパネル --}}
                    <aside x-show="replyingToEmailId" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                           class="w-[500px] shrink-0 flex flex-col bg-white overflow-hidden shadow-2xl border-l border-blue-100 z-40">
                        <div class="px-8 py-6 border-b border-blue-50 bg-blue-50/10 flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-black text-blue-700 uppercase tracking-widest">返信ドラフト</h3>
                                <p class="text-[9px] text-blue-400 font-bold mt-0.5 truncate max-w-[300px]" x-text="replySubject"></p>
                            </div>
                            <button @click="replyingToEmailId = null" class="text-gray-300 hover:text-blue-600 transition-colors p-2"><i class="fas fa-times fa-lg"></i></button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-8 space-y-6 custom-scrollbar">
                            <div class="space-y-4">
                                <template x-if="sendableAccounts.length > 1">
                                    <div class="relative mb-3">
                                        <label class="text-[9px] font-black text-blue-500 uppercase absolute left-3 top-1.5 z-10">送信アカウント</label>
                                        <select x-on:change="pickSendableAccount($event.target.value)"
                                                class="w-full pt-6 pb-2 px-3 bg-white border border-blue-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-300 font-bold appearance-none">
                                            <template x-for="acc in sendableAccounts" :key="acc.id ?? 'system'">
                                                <option :value="acc.id ?? ''" :selected="(acc.id ?? null) === replyAccountId" x-text="acc.label + ' <' + acc.from_address + '>'"></option>
                                            </template>
                                        </select>
                                    </div>
                                </template>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="relative">
                                        <label data-test-id="reply-from-label" class="text-[9px] font-black text-blue-500 uppercase absolute left-3 top-1.5 z-10">差出人 (From)</label>
                                        <input type="text" x-model="replyFromAddress" class="w-full pt-6 pb-2 px-3 bg-white border border-blue-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-300 font-bold">
                                    </div>
                                    <div class="relative">
                                        <label data-test-id="reply-to-label" class="text-[9px] font-black text-blue-500 uppercase absolute left-3 top-1.5 z-10">宛先 (To)</label>
                                        <input type="text" x-model="replyToAddress" class="w-full pt-6 pb-2 px-3 bg-white border border-blue-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-300 font-bold">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="relative"><label data-test-id="reply-cc-label" class="text-[9px] font-black text-blue-500 uppercase absolute left-3 top-1.5 z-10">Cc</label><input type="text" x-model="replyCc" class="w-full pt-6 pb-2 px-3 bg-white border border-blue-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-300 font-medium"></div>
                                    <div class="relative"><label data-test-id="reply-bcc-label" class="text-[9px] font-black text-blue-500 uppercase absolute left-3 top-1.5 z-10">Bcc</label><input type="text" x-model="replyBcc" class="w-full pt-6 pb-2 px-3 bg-white border border-blue-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-300 font-medium"></div>
                                </div>
                                <div class="relative">
                                    <label data-test-id="reply-subject-label" class="text-[9px] font-black text-blue-500 uppercase absolute left-3 top-1.5 z-10">件名</label>
                                    <input type="text" x-model="replySubject" class="w-full pt-6 pb-2 px-3 bg-white border border-blue-100 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-300 font-black">
                                </div>
                            </div>
                            <div class="flex flex-col min-h-[300px]">
                                <textarea x-model="replyBody" class="flex-1 w-full text-sm border border-blue-100 bg-white rounded-2xl p-4 focus:ring-2 focus:ring-blue-300 outline-none leading-relaxed resize-none text-gray-700" placeholder="返信内容を入力してください..."></textarea>
                            </div>
                            <div class="flex wrap gap-2">
                                <template x-for="(f, i) in selectedFiles" :key="i">
                                    <span class="bg-blue-50 text-blue-600 px-3 py-2 rounded-xl text-[10px] font-black flex items-center gap-2 border border-blue-100">
                                        <span x-text="f.name"></span>
                                        <button @click="removeSelectedFile(i)" class="hover:text-red-500"><i class="fas fa-times"></i></button>
                                    </span>
                                </template>
                            </div>
                        </div>
                        <div class="p-8 border-t border-gray-100 bg-gray-50/50 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <input type="file" multiple @change="handleFileSelect($event)" class="hidden" id="reply-file-aside">
                                <label for="reply-file-aside" class="cursor-pointer bg-white hover:bg-blue-100 text-blue-600 px-4 py-2 rounded-xl text-[11px] font-black border border-blue-200 transition-all flex items-center gap-2 shadow-sm">
                                    <i class="fas fa-paperclip"></i> 添付
                                </label>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="replyAiPanelOpen = !replyAiPanelOpen" :class="replyAiPanelOpen ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-600 border border-indigo-200 hover:bg-indigo-50'" class="px-5 py-2.5 rounded-xl font-black text-xs transition-all shadow-sm flex items-center gap-2">
                                    <i class="fas fa-magic"></i> AIアシスタント
                                </button>
                                <button @click="submitReply()" :disabled="!replyBody || sendingReply"
                                    class="bg-blue-600 text-white px-8 py-2.5 rounded-xl font-black text-xs shadow-lg hover:bg-blue-700 transition-all flex items-center gap-2 disabled:opacity-50">
                                    <span x-show="!sendingReply">承認を依頼する</span>
                                    <span x-show="sendingReply">送信中...</span>
                                    <i class="fas fa-paper-plane" x-show="!sendingReply"></i>
                                    <i class="fas fa-spinner animate-spin" x-show="sendingReply"></i>
                                </button>
                            </div>
                        </div>
                    </aside>

                    {{-- AIアシスタントサイドパネル --}}
                    <aside x-show="replyAiPanelOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                           class="w-[450px] shrink-0 flex flex-col bg-white overflow-hidden shadow-2xl border-l border-indigo-100 z-50">
                         <div class="px-8 py-6 border-b border-indigo-50 bg-indigo-50/10 flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-black text-indigo-700 uppercase tracking-widest">AIアシスタント</h3>
                                <p class="text-[9px] text-indigo-400 font-bold mt-0.5">自動コンテキスト分析エンジン</p>
                            </div>
                            <button @click="replyAiPanelOpen = false" class="text-gray-300 hover:text-indigo-600 transition-colors p-2"><i class="fas fa-times fa-lg"></i></button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-8 space-y-8 custom-scrollbar">
                            <div class="space-y-6">
                                <div class="space-y-3">
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">スキルを選択</label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <template x-for="(skill, key) in aiSkills" :key="key">
                                            <button @click="aiSkill = key" 
                                                :class="aiSkill === key ? 'bg-indigo-600 text-white border-indigo-600 shadow-xl' : 'bg-gray-50 text-gray-600 border-gray-100 hover:border-indigo-200'"
                                                class="p-4 rounded-2xl border text-left transition-all">
                                                <p class="text-[11px] font-black" x-text="skill.name"></p>
                                                <p class="text-[8px] mt-1 opacity-70 leading-tight font-bold" x-text="skill.description"></p>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">追加の指示 (任意)</label>
                                    <textarea x-model="aiUserPrompt" rows="4" class="w-full text-sm border-gray-100 bg-gray-50 rounded-2xl p-4 outline-none focus:ring-4 focus:ring-indigo-50 transition-all resize-none font-medium placeholder-gray-300" placeholder="例: もっと簡潔に、箇条書きで..."></textarea>
                                </div>
                                <div class="flex items-center gap-3 bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100/50">
                                    <input type="checkbox" x-model="maskPii" id="mask-pii-aside" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                                    <label for="mask-pii-aside" class="text-[10px] font-black text-indigo-700 uppercase tracking-tighter cursor-pointer">個人情報をマスキングする</label>
                                </div>
                                <button @click="askAiForReply()" :disabled="aiLoading" class="w-full bg-indigo-600 text-white py-5 rounded-[2rem] font-black text-xs shadow-xl hover:bg-indigo-700 transition-all flex items-center justify-center gap-3 disabled:opacity-50">
                                    <i class="fas fa-bolt" :class="aiLoading ? 'animate-spin' : ''"></i>
                                    <span x-text="aiLoading ? '分析中...' : 'AI回答を生成する'"></span>
                                </button>
                            </div>

                            <div x-show="aiAnalysis || aiLoading" class="space-y-6 pb-10">
                                 <div class="bg-gray-900 rounded-[2.5rem] p-8 shadow-2xl relative overflow-hidden">
                                     <h4 class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mb-4">生成結果</h4>
                                     <div class="text-[13px] text-gray-200 leading-relaxed font-medium min-h-[150px] whitespace-pre-wrap" x-text="aiAnalysis?.answer"></div>
                                     <div class="mt-8 pt-6 border-t border-gray-800 flex flex-col gap-4">
                                         <div class="flex gap-2">
                                             <template x-if="aiAnalysis?.sources?.kb"><span class="px-2 py-1 bg-green-900/30 text-green-400 text-[8px] font-black rounded border border-green-800 uppercase tracking-tighter">ナレッジ参照</span></template>
                                             <template x-if="aiAnalysis?.sources?.reports"><span class="px-2 py-1 bg-blue-900/30 text-blue-400 text-[8px] font-black rounded border border-blue-800 uppercase tracking-tighter">レポート参照</span></template>
                                         </div>
                                         <button @click="applyAiDraft()" class="w-full bg-white text-gray-900 py-3 rounded-xl text-[10px] font-black hover:bg-indigo-50 transition-all shadow-lg uppercase tracking-widest">本文に反映する</button>
                                     </div>
                                 </div>
                            </div>
                        </div>
                    </aside>
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

    {{-- 添付エラーモーダル --}}
    <template x-if="attachmentError">
        <div class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-md p-4" @click.self="attachmentError = null">
            <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in duration-200 text-center">
                <div class="bg-amber-50 px-8 py-6 flex flex-col items-center gap-4 border-b border-amber-100 text-amber-600"><div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center text-amber-500 shadow-xl"><i class="fas fa-paperclip fa-lg"></i></div><h3 class="text-xl font-black text-amber-900 uppercase tracking-tighter">添付ファイルエラー</h3></div>
                <div class="px-8 py-6 space-y-4"><p class="text-sm font-bold text-gray-700 leading-relaxed whitespace-pre-wrap text-left" x-text="attachmentError.message"></p><div class="bg-gray-50 rounded-xl p-6 text-center border border-gray-100"><p class="text-[10px] font-black text-gray-400 uppercase mb-1">現在の合計サイズ</p><p class="text-3xl font-black text-gray-800" x-text="attachmentError.totalSize"></p></div></div>
                <div class="px-8 py-6 bg-gray-50 border-t border-gray-100"><button @click="attachmentError = null" class="w-full bg-gray-900 text-white py-4 rounded-2xl font-black text-xs shadow-xl uppercase hover:bg-black transition-all">了解しました</button></div>
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

</div>

<script>
function emailApp() {
    return {
        sidebarWidth: parseInt(localStorage.getItem('sidebarWidth')) || 64,
        navPanelWidth: parseInt(localStorage.getItem('navPanelWidth')) || (window.innerWidth >= 1920 ? 320 : 280),
        threadWidth: parseInt(localStorage.getItem('threadWidth')) || (window.innerWidth >= 1920 ? 450 : 380),
        navPanelOpen: false, fetching: false, 
        selectedThreadId: null, selectedThread: null, composeMode: false,
        leftTab: 'inbox', searchQuery: '', 
        allStatusMode: JSON.parse(localStorage.getItem('allStatusMode')) || false,
        pinnedOnlyMode: {{ isset($isPinnedView) && $isPinnedView ? 'true' : 'false' }},
        assigneeFilterId: localStorage.getItem('assigneeFilterId') || 'all',
        sortOrder: 'desc',
        statusLabels: { inbox: '受信', hold: '保留', completed: '完了', pending: '承認待ち' },
        threadEmails: [], threadMerges: [], expandedEmailIds: [], 
        selectionMode: false, selectedThreadIds: [], longPressTimer: null, isLongPressing: false,
        mergeModalOpen: false, mergeTargetId: null,
        threads: [], threadsLoading: false, syncError: null, attachmentError: null,
        users: [], // 招待管理から一元化されたユーザーリスト
        selectedFiles: [],
        replyToAddress: '', replyCc: '', replyBcc: '', replySubject: '', replyBody: '', replyFromAddress: '',
        replyAccountId: null,
        sendableAccounts: @json($sendableAccounts ?? []),
        pickSendableAccount(idOrEmpty) {
            // 値はアカウントID (数値文字列) または '' (システム既定)
            const id = idOrEmpty === '' ? null : Number(idOrEmpty);
            const acc = this.sendableAccounts.find(a => (a.id ?? null) === id);
            this.replyAccountId = id;
            if (acc) this.replyFromAddress = acc.from_address;
        },
        replyAiPanelOpen: false, aiSkill: 'reply', 
        aiSkills: @json(config('ai_skills.skills', [])),
        aiUserPrompt: '', aiAnalysis: null, aiLoading: false, sendingReply: false, maskPii: true,
        replyingToEmailId: null,
        virtualScroll: { startIndex: 0, endIndex: 30, rowHeight: 95, viewportHeight: 600, buffer: 10 },
        pollIntervalId: null, pollFailCount: 0, basePollDelay: 60000, maxPollDelay: 300000, currentPollDelay: 60000,

        async init() {
            await Promise.all([
                this.loadThreads(),
                this.loadUsers()
            ]);
            window.addEventListener('resize', () => this.updateVirtualViewport());
            this.$nextTick(() => this.updateVirtualViewport());
            setInterval(() => { if (this.composeMode) this.autoSaveDraft(); }, 30000);

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
                const res = await fetch('/users');
                this.users = await res.json();
            } catch(e) { console.error('ユーザーリストの取得に失敗'); }
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
                const res = await fetch('/emails/search?' + params.toString());
                this.threads = await res.json();
                this.handleScroll();
            } finally { 
                if (!isBackground) {
                    this.threadsLoading = false; 
                }
            }
        },

        toggleAllStatus() { this.allStatusMode = !this.allStatusMode; localStorage.setItem('allStatusMode', JSON.stringify(this.allStatusMode)); this.loadThreads(); },
        togglePinnedOnly() { 
            if ({{ isset($isPinnedView) && $isPinnedView ? 'true' : 'false' }}) {
                window.location.href = '/';
            } else {
                window.location.href = '/emails/pinned';
            }
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
                const res = await fetch('/emails/fetch', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Connection Timeout');
                
                // If there are new emails or we just want to update the list seamlessly
                await this.loadThreads(isBackground);
                
                // Reset exponential backoff on success
                this.pollFailCount = 0;
                this.currentPollDelay = this.basePollDelay;
            } catch (e) { 
                if (!isBackground) {
                    this.syncError = { message: 'メールサーバーとの同期に失敗しました', detail: e.message, stack: e.stack || '' }; 
                }
                
                // Exponential backoff
                this.pollFailCount++;
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
                // Optimistic UI updates
                const originalStatuses = {};
                this.selectedThreadIds.forEach(id => {
                    const thread = this.threads.find(t => t.id === id);
                    if (thread) {
                        originalStatuses[id] = thread.status;
                        thread.status = status;
                    }
                });

                let hasError = false;
                for (let id of this.selectedThreadIds) {
                    const res = await fetch(`/threads/${id}/status`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ status }) });
                    if (!res.ok) hasError = true;
                }
                
                if (hasError) throw new Error('Some updates failed');
                this.cancelSelection(); await this.loadThreads();
            } catch(e) { 
                alert('更新に失敗しました'); 
                await this.loadThreads(); // Rollback
            }
        },

        async batchPinSelected(pinStatus) {
            try {
                // Optimistic UI updates
                this.selectedThreadIds.forEach(id => {
                    const thread = this.threads.find(t => t.id === id);
                    if (thread) thread.is_pinned = pinStatus;
                });

                let hasError = false;
                for (let id of this.selectedThreadIds) {
                    const res = await fetch(`/threads/${id}/pin`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ is_pinned: pinStatus }) });
                    if (!res.ok) hasError = true;
                }

                if (hasError) throw new Error('Some updates failed');
                this.cancelSelection(); await this.loadThreads();
            } catch(e) { 
                alert('更新に失敗しました'); 
                await this.loadThreads(); // Rollback
            }
        },

        async batchDeleteSelected() {
            if (!confirm('選択したメールを削除しますか？')) return;
            try {
                let hasError = false;
                for (let id of this.selectedThreadIds) {
                    const res = await fetch(`/threads/${id}`, { method: 'DELETE', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                    if (!res.ok) hasError = true;
                }
                if (hasError) throw new Error('Some deletes failed');
                this.cancelSelection(); await this.loadThreads();
            } catch(e) { 
                alert('削除に失敗しました'); 
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
            const sourceIds = this.selectedThreadIds.filter(id => id !== targetId);
            
            try {
                for (let id of sourceIds) {
                    await fetch(`/threads/${targetId}/merge`, { 
                        method: 'POST', 
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, 
                        body: JSON.stringify({ merge_thread_id: id }) 
                    });
                }
                this.mergeModalOpen = false;
                this.cancelSelection(); 
                await this.loadThreads(); 
                await this.loadThread(targetId);
            } catch(e) { alert('マージに失敗しました'); }
        },

        async unmergeThread(mergeId) {
            if (!confirm('マージを解除しますか？')) return;
            try {
                const res = await fetch(`/thread-merges/${mergeId}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                if (res.ok) { await this.loadThread(this.selectedThreadId); await this.loadThreads(); }
            } catch(e) { alert('解除に失敗しました'); }
        },

        async togglePin(threadId = null) {
            const id = threadId || this.selectedThreadId;
            if (!id) return;
            try {
                const res = await fetch(`/threads/${id}/pin`, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                const data = await res.json();
                if (this.selectedThreadId === id) this.selectedThread.is_pinned = data.is_pinned;
                await this.loadThreads();
            } catch(e) {}
        },

        async updateAssignee(userId, threadId = null) {
            const id = threadId || this.selectedThreadId;
            if (!id) return;
            try {
                await fetch(`/threads/${id}/assignee`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ assigned_user_id: userId })
                });
                if (this.selectedThreadId === id) {
                    const user = this.users.find(u => u.id == userId);
                    this.selectedThread.assigned_user_id = userId;
                    this.selectedThread.assignee = user ? { id: user.id, name: user.name } : null;
                }
                await this.loadThreads();
            } catch(e) {}
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

        openCompose() { this.composeMode = true; this.selectedThread = null; this.selectedThreadId = null; this.replySubject = ''; this.replyBody = ''; this.replyToAddress = ''; this.replyCc = ''; this.replyBcc = ''; this.replyFromAddress = ''; this.replyingToEmailId = null; this.loadDraft(); },
        openReplyForEmail(email, all = false) {
            if (!email) return;
            this.replyingToEmailId = email.id;
            this.replyToAddress = email.from_address || '';
            this.replySubject = ((email.subject || '').startsWith('Re:') ? '' : 'Re: ') + (email.subject || '');
            this.replyBody = '';
            
            const toAddresses = email.to_address ? email.to_address.split(',').map(a => a.trim()).filter(a => a) : [];
            this.replyFromAddress = toAddresses.length > 0 ? toAddresses[0] : '';
            
            if (all) {
                const ccAddresses = email.cc ? email.cc.split(',').map(a => a.trim()).filter(a => a) : [];
                this.replyCc = ccAddresses.filter(c => !toAddresses.includes(c)).join(', ');
            } else {
                this.replyCc = '';
            }
            
            this.replyBcc = '';
        },

        closeWorkspace() { this.selectedThread = null; this.selectedThreadId = null; this.composeMode = false; this.replyAiPanelOpen = false; this.replyingToEmailId = null; },

        async askAiForReply() {
            this.aiLoading = true; this.aiAnalysis = null;
            try {
                const emailId = this.replyingToEmailId || this.threadEmails[0]?.id || 1;
                const res = await fetch(`/emails/${emailId}/ai`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ prompt: this.aiUserPrompt, skill: this.aiSkill, mask_pii: this.maskPii }) });
                if (!res.ok) throw new Error('AI Server Error');
                const data = await res.json(); this.simulateStreaming(data);
            } catch(e) { alert('AI生成失敗'); this.aiLoading = false; }
        },

        simulateStreaming(data) {
            let fullText = data.answer || '', i = 0;
            this.aiAnalysis = { ...data, answer: '' };
            const interval = setInterval(() => { if (i < fullText.length) { this.aiAnalysis.answer += fullText[i++]; } else { clearInterval(interval); this.aiLoading = false; } }, 5);
        },

        applyAiDraft() { if (this.aiAnalysis?.answer) this.replyBody = (this.replyBody ? this.replyBody + "\n\n" : "") + this.aiAnalysis.answer; },
        copyAiDraft() { navigator.clipboard.writeText(this.aiAnalysis?.answer); alert('コピーしました'); },

        handleFileSelect(e) {
            const files = Array.from(e.target.files), MAX = 20 * 1024 * 1024;
            let total = this.selectedFiles.reduce((acc, f) => acc + f.size, 0), errors = [];
            files.forEach(f => {
                if (f.size > MAX) errors.push(`${f.name} が上限(20MB)を超えています。`);
                else if (total + f.size > MAX) errors.push(`${f.name} を含めると 20MB を超えます。`);
                else { this.selectedFiles.push(f); total += f.size; }
            });
            if (errors.length > 0) this.attachmentError = { title: '添付不可', message: errors.join('\n'), totalSize: (total/(1024*1024)).toFixed(2) + 'MB' };
            e.target.value = '';
        },
        removeSelectedFile(i) { this.selectedFiles.splice(i, 1); },

        autoSaveDraft() { if(this.replyBody) localStorage.setItem('mail_draft', JSON.stringify({ to: this.replyToAddress, sub: this.replySubject, body: this.replyBody })); },
        loadDraft() { const d = JSON.parse(localStorage.getItem('mail_draft')); if (d && !this.replyBody) { this.replyToAddress = d.to; this.replySubject = d.sub; this.replyBody = d.body; } },

        async loadThread(id) {
            this.selectedThreadId = id; this.composeMode = false; this.expandedEmailIds = [];
            try {
                const res = await fetch(`/threads/${id}`);
                const data = await res.json();
                this.selectedThread = data.thread;
                
                // 新しいメールが一番上（降順）
                this.threadEmails = data.emails.sort((a, b) => b.id - a.id);
                this.threadMerges = data.merges || [];
                
                // 読み込み時に「一番上(最新)」のメールを自動展開
                if (this.threadEmails.length > 0) this.expandedEmailIds.push(this.threadEmails[0].id);
            } catch(e) { console.error('スレッド読み込み失敗'); }
        },

        toggleEmailExpand(id) { if (this.expandedEmailIds.includes(id)) this.expandedEmailIds = this.expandedEmailIds.filter(eid => eid !== id); else this.expandedEmailIds.push(id); },

        async updateSingleEmailStatus(threadId, status) {
            try {
                const res = await fetch(`/threads/${threadId}/status`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ status }) });
                if(res.ok) {
                    alert('マージ元の状態を更新しました');
                }
            } catch(e) { alert('更新に失敗しました'); }
        },

        async updateThreadStatus(thread, status) {
            try {
                const idx = this.threads.findIndex(t => t.id === thread.id);
                const nextThreadId = (idx !== -1 && idx < this.threads.length - 1) ? this.threads[idx + 1].id : null;

                const res = await fetch(`/threads/${thread.id}/status`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ status }) });
                if(res.ok) { 
                    this.selectedThread.status = status; 
                    
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
                }
            } catch(e) {}
        },

        async submitReply() {
            if (!this.replyBody) return;
            this.sendingReply = true;
            const formData = new FormData();
            formData.append('body', this.replyBody); 
            formData.append('to', this.replyToAddress); 
            formData.append('from_address', this.replyFromAddress);
            if (this.replyAccountId !== null && this.replyAccountId !== undefined) {
                formData.append('mail_account_id', this.replyAccountId);
            }
            formData.append('cc', this.replyCc || ''); 
            formData.append('bcc', this.replyBcc || ''); 
            formData.append('subject', this.replySubject);
            this.selectedFiles.forEach(f => formData.append('attachments[]', f));
            
            try {
                let url;
                let emailToReply = null;
                if (this.composeMode && !this.replyingToEmailId) {
                    url = '/emails/compose';
                } else {
                    const emailId = this.replyingToEmailId || (this.threadEmails.length > 0 ? this.threadEmails[0].id : null);
                    if (!emailId) throw new Error('返信対象が見つかりません');
                    url = `/emails/${emailId}/reply`;
                    emailToReply = this.threadEmails.find(e => e.id == emailId);
                }
                
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: formData
                });
                
                let data = {};
                try {
                    data = await res.json();
                } catch(je) {
                    console.error('JSON parsing failed', je);
                }

                if (res.ok) {
                    alert('送信しました (承認待ち)');
                    localStorage.removeItem('mail_draft');
                    
                    const currentThreadId = this.selectedThreadId;
                    const idx = this.threads.findIndex(t => t.id === currentThreadId);
                    const nextThreadId = (idx !== -1 && idx < this.threads.length - 1) ? this.threads[idx + 1].id : null;

                    // 返信したメールが属するスレッドを完了にする
                    const threadToComplete = emailToReply ? emailToReply.thread_id : currentThreadId;
                    if (threadToComplete) {
                        try {
                            await fetch(`/threads/${threadToComplete}/status`, { 
                                method: 'PUT', 
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, 
                                body: JSON.stringify({ status: 'completed' }) 
                            });
                        } catch(se) { console.error('Auto-complete failed', se); }
                    }

                    this.closeWorkspace();
                    await this.loadThreads();
                    
                    if (!this.selectionMode && nextThreadId) {
                        this.loadThread(nextThreadId);
                    }
                } else {
                    alert('送信失敗: ' + (data.message || data.error || 'サーバーエラーが発生しました'));
                }
            } catch(e) { 
                console.error(e);
                alert('通信エラー: ' + e.message); 
            } finally { 
                this.sendingReply = false; 
            }
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
