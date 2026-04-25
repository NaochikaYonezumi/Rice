@extends('layouts.app')
@section('title', 'メール管理')

@section('content')
<div class="flex h-full bg-gray-50 overflow-hidden text-gray-900 font-sans" x-data="emailApp()" x-init="init()" x-cloak>

    {{-- 1カラム目: ミニサイドバー (リサイズ可能) --}}
    <div x-show="!fullThreadMode && !isListMaximizing" :style="'width:' + sidebarWidth + 'px'" class="shrink-0 border-r border-gray-200 bg-white flex flex-col items-center py-6 gap-6 z-50 shadow-sm relative transition-all duration-150">
        <button @click="navPanelOpen = !navPanelOpen; tagPanelOpen = false" :class="navPanelOpen ? 'bg-blue-100 text-blue-600 shadow-inner' : 'text-gray-400'" class="p-2.5 rounded-2xl transition-all hover:bg-blue-50 group flex items-center gap-3 overflow-hidden">
            <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-width="2"/></svg>
            <span x-show="sidebarWidth > 100" class="text-[11px] font-black truncate uppercase tracking-widest">顧客フォルダ</span>
        </button>
        <button @click="tagPanelOpen = !tagPanelOpen; navPanelOpen = false" :class="tagPanelOpen ? 'bg-indigo-100 text-indigo-600 shadow-inner' : 'text-gray-400'" class="p-2.5 rounded-2xl transition-all hover:bg-indigo-50 group flex items-center gap-3 overflow-hidden">
            <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" stroke-width="2"/></svg>
            <span x-show="sidebarWidth > 100" class="text-[11px] font-black truncate uppercase tracking-widest">タグマスター</span>
        </button>
        <div class="mt-auto flex flex-col items-center gap-4">
            <button @click="fetchEmails()" class="p-2 text-gray-300 hover:text-blue-500" :class="fetching ? 'animate-spin' : ''"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.001 0 01-15.357-2m15.357 2H15" stroke-width="2"/></svg></button>
            <button @click="navPanelOpen = false; tagPanelOpen = false" class="text-gray-300 hover:text-gray-600 p-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M11 19l-7-7 7-7" stroke-width="2"/></svg></button>
        </div>
        <div class="absolute top-0 right-0 w-1 h-full cursor-col-resize hover:bg-blue-400 z-50 transition-colors" @mousedown.prevent="startResizeSidebar($event)"></div>
    </div>

    {{-- 2カラム目: ナビゲーション (一括紐付け対応) --}}
    <div x-show="!fullThreadMode && !isListMaximizing && (navPanelOpen || tagPanelOpen)" :style="'width:' + navPanelWidth + 'px'" class="shrink-0 border-r border-gray-200 bg-white flex flex-col overflow-hidden shadow-sm z-30 relative transition-all duration-75">
        <div x-show="navPanelOpen" class="flex flex-col h-full">
            <div class="px-5 py-5 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-[11px] font-black uppercase tracking-widest">顧客フォルダ</h2>
                <div class="flex items-center gap-1">
                    <button @click="openCreateGroup(null)" class="text-blue-600 p-1.5 hover:bg-blue-50 rounded-full transition-all" title="ルートフォルダ作成"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke-width="2.5"/></svg></button>
                    <button @click="customerModalOpen = true" class="text-blue-500 p-1.5 hover:bg-blue-50 rounded-full transition-all" title="顧客追加"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2.5"/></svg></button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto py-2 custom-scrollbar bg-gray-50/20" id="customer-group-container">
                <div class="mb-4">
                    <button @click="selectionMode ? assignCustomerToSelected('none') : toggleGroupFilter('none')" :class="activeGroupId==='none'?'bg-blue-50 text-blue-700 shadow-inner ring-1 ring-blue-200':'text-gray-400 hover:bg-white'" class="w-full text-left px-4 py-2 text-[10px] font-black uppercase tracking-tighter transition-all">未分類 / 紐付け解除</button>
                    <div class="px-2 space-y-0.5 mt-1 min-h-[10px]">
                        <template x-for="c in filteredUnassignedCustomers" :key="c.id">
                            <div :data-customer-id="c.id" class="group/c flex items-center gap-1 drop-target-customer" :data-cid="c.id">
                                <div class="c-drag-handle p-2 text-gray-300 cursor-grab opacity-0 group-hover/c:opacity-100 font-black text-[10px] transition-all">⠿</div>
                                <button @click="selectionMode ? assignCustomerToSelected(c.id) : toggleCustomerFilter(c.id, c.name)" :class="activeCustomerId===c.id?'bg-blue-600 text-white shadow-md':'text-gray-600 hover:bg-white'" class="flex-1 text-left px-4 py-2 rounded-xl text-xs font-bold truncate transition-all shadow-sm" x-text="c.name"></button>
                            </div>
                        </template>
                    </div>
                </div>
                <div id="nested-groups">
                    <template x-for="group in customerGroups" :key="group.id">
                        <div class="mb-2">
                            <div class="mx-2 flex items-center gap-0.5 group">
                                <button @click="toggleGroup(group.id)" class="p-1.5 text-gray-400 hover:text-blue-600 transition-transform duration-200" :class="isGroupOpen(group.id)?'rotate-90' : ''"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-width="3"/></svg></button>
                                <div @click="toggleGroupFilter(group.id)" :class="activeGroupId===group.id?'bg-blue-600 text-white shadow-lg':'text-blue-900 bg-blue-50/50 hover:bg-white'" class="flex-1 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-tighter flex items-center justify-between group-drag-handle cursor-pointer border border-transparent shadow-sm transition-all">
                                    <span class="truncate" x-text="group.name"></span>
                                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-all">
                                        <button @click.stop="openCreateGroup(group.id)" class="text-gray-400 hover:text-blue-600 p-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2.5"/></svg></button>
                                        <button @click.stop="renameCustomerGroup(group)" class="text-gray-400 hover:text-blue-600 p-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-width="2"/></svg></button>
                                    </div>
                                </div>
                            </div>
                            <div x-show="isGroupOpen(group.id)" x-collapse class="ml-9 border-l border-gray-200/50 mt-1 space-y-1">
                                <template x-for="c in (group.customers || [])" :key="c.id">
                                    <div class="group/c flex items-center gap-1 drop-target-customer" :data-cid="c.id">
                                        <div class="c-drag-handle p-2 text-gray-300 cursor-grab opacity-0 group-hover/c:opacity-100 transition-all font-black text-[10px]">⠿</div>
                                        <button @click="selectionMode ? assignCustomerToSelected(c.id) : toggleCustomerFilter(c.id, c.name)" :class="activeCustomerId===c.id?'bg-blue-600 text-white shadow-md':'text-gray-600 hover:bg-white'" class="flex-1 text-left px-4 py-2 rounded-xl text-xs font-bold truncate transition-all shadow-sm" x-text="c.name"></button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div class="px-4 py-4 border-t border-gray-100 bg-white"><input type="text" x-model="customerSearchQuery" placeholder="顧客を検索..." class="w-full px-3 py-2 bg-gray-50 border-none rounded-xl text-[10px] focus:ring-2 focus:ring-blue-400 font-bold outline-none shadow-inner"></div>
            <div class="absolute top-0 right-0 w-1.5 h-full cursor-col-resize hover:bg-blue-400 z-50 transition-colors" @mousedown.prevent="startResizeNav($event)"></div>
        </div>
        <div x-show="tagPanelOpen" class="flex flex-col h-full bg-white">
            <div class="px-5 py-5 border-b border-gray-100 flex items-center justify-between"><h2 class="text-[11px] font-black text-indigo-900 uppercase tracking-widest">タグマスター</h2><button @click="openMasterTagAdd()" class="text-indigo-600 p-1.5 hover:bg-indigo-50 rounded-full transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2.5"/></svg></button></div>
            <div class="flex-1 overflow-y-auto py-2 custom-scrollbar bg-indigo-50/10" id="master-tag-list">
                <template x-for="mt in filteredMasterTags" :key="mt.id">
                    <div class="group flex items-center gap-1 px-2" :data-id="mt.id">
                        <div class="drag-handle p-2 text-gray-300 cursor-grab opacity-0 group-hover:opacity-100 transition-all font-black text-[10px]">⠿</div>
                        <button @click="toggleTagFilter(mt.name)" :class="activeTags.includes(mt.name)?'bg-indigo-600 text-white shadow-md':'text-gray-600 hover:bg-white hover:text-indigo-700'" class="flex-1 text-left px-4 py-2 rounded-xl text-xs font-bold flex items-center justify-between shadow-sm truncate transition-all"><span x-text="mt.name"></span><span class="text-[10px] opacity-40 group-hover:opacity-100" x-text="(tagMap[mt.name]||[]).length"></span></button>
                    </div>
                </template>
            </div>
            <div class="absolute top-0 right-0 w-1.5 h-full cursor-col-resize hover:bg-blue-400 z-50 transition-colors" @mousedown.prevent="startResizeNav($event)"></div>
        </div>
    </div>

    {{-- 3カラム目: メール一覧 (固定幅) --}}
    <div x-show="!fullThreadMode" class="flex flex-col overflow-hidden bg-white border-r border-gray-200 relative z-20 thread-list-column">
        <div class="bg-white border-b border-gray-100 px-6 pt-4 shrink-0 shadow-sm z-10">
            <div class="flex items-center justify-between mb-4">
                <div x-show="isListMaximizing" class="mr-2"><button @click="isListMaximizing = false" class="text-blue-600 p-2 hover:bg-blue-50 rounded-xl font-black text-xs flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M11 19l-7-7 7-7M21 19l-7-7 7-7" stroke-width="2.5"/></svg> 戻る</button></div>
                <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-xl shadow-inner border border-gray-50 flex-1 mr-2 overflow-hidden">
                    <button @click="setLeftTab('inbox')" :class="leftTab==='inbox'?'bg-white shadow text-blue-600':'text-gray-500'" class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all">受信</button>
                    <button @click="setLeftTab('hold')" :class="leftTab==='hold'?'bg-white shadow text-gray-800':'text-gray-500'" class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all">保留</button>
                    <button @click="setLeftTab('completed')" :class="leftTab==='completed'?'bg-white shadow text-green-600':'text-gray-500'" class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all">完了</button>
                    <button @click="setLeftTab('pending')" :class="leftTab==='pending'?'bg-white shadow text-amber-600':'text-gray-500'" class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all flex items-center justify-center gap-1">承認待ち <span x-show="pendingCount > 0" class="bg-amber-500 text-white px-1.5 rounded-full text-[8px]" x-text="pendingCount"></span></button>
                </div>
                <button @click="openCompose()" class="bg-blue-600 text-white text-[10px] px-5 py-2 rounded-xl font-black shadow-lg hover:bg-blue-700 transition-all shrink-0">+ 新規作成</button>
            </div>
            <div class="relative mb-4"><svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><input type="text" x-model="searchQuery" @input="onSearchInput()" placeholder="メッセージ、件名、差出人を検索..." class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border-none rounded-2xl text-xs focus:ring-2 focus:ring-blue-400 outline-none font-medium shadow-inner"></div>
            
            {{-- トップタグ --}}
            <div class="flex items-center gap-2 pb-4 overflow-x-auto no-scrollbar border-t border-gray-50 pt-3">
                <template x-for="tag in filteredMasterTags" :key="tag.id">
                    <button @click="toggleTagFilter(tag.name)" :class="activeTags.includes(tag.name)?'bg-indigo-600 text-white shadow-md':'bg-indigo-50 text-indigo-500 hover:bg-indigo-100 shadow-sm'" class="shrink-0 text-[9px] font-black px-3.5 py-1 rounded-full transition-all border border-indigo-100" x-text="'#' + tag.name"></button>
                </template>
            </div>
        </div>

        {{-- 長押し一括操作アクションバー --}}
        <template x-if="selectionMode">
            <div class="px-4 py-2.5 bg-blue-600 text-white flex items-center justify-between sticky top-0 z-30 shadow-lg animate-in slide-in-from-top duration-300">
                <div class="flex items-center gap-2"><span class="text-[11px] font-black text-white/90" x-text="selectedThreadIds.length + ' 件選択'"></span><div class="flex items-center gap-1"><button @click="bulkMoveToHold()" class="text-[9px] font-black bg-white/20 hover:bg-white/30 px-2 py-1 rounded-lg transition-all">保留</button><button @click="bulkMoveToComplete()" class="text-[9px] font-black bg-white/20 hover:bg-white/30 px-2 py-1 rounded-lg text-green-300 transition-all">完了</button><button @click="bulkMerge()" class="text-[9px] font-black bg-white/20 hover:bg-white/30 px-2 py-1 rounded-lg transition-all">マージ</button><button @click="bulkDelete()" class="text-[9px] font-black bg-red-500 hover:bg-red-600 px-2 py-1 rounded-lg transition-all shadow-sm">削除</button>
                <div class="relative" x-data="{ classifyOpen: false }">
                    <button @click="classifyOpen = !classifyOpen" class="text-[9px] font-black bg-white/20 hover:bg-white/30 px-2 py-1 rounded-lg transition-all">顧客を分類 ▼</button>
                    <div x-show="classifyOpen" @click.away="classifyOpen = false" class="absolute top-full left-0 mt-2 w-64 bg-white border border-gray-200 shadow-2xl rounded-xl z-50 overflow-hidden text-gray-800">
                        <div class="p-2 border-b border-gray-100"><input type="text" x-model="customerSearchQuery" placeholder="顧客を検索..." class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-300 text-gray-800"></div>
                        <div class="max-h-48 overflow-y-auto custom-scrollbar">
                            <template x-for="c in filteredCustomers" :key="c.id"><button @click="bulkAssignCustomerSubmit(c.id); classifyOpen = false" class="w-full text-left px-4 py-2 text-xs font-semibold hover:bg-blue-50 border-b border-gray-50 last:border-0" x-text="c.name"></button></template>
                        </div>
                        <div class="border-t border-gray-100 p-2"><button @click="quickCustomerFormOpen = !quickCustomerFormOpen" class="w-full flex items-center gap-2 text-[10px] font-bold text-blue-600 hover:text-blue-800 py-1 px-2 transition-all">＋ Add New Customer</button><div x-show="quickCustomerFormOpen" class="mt-2 space-y-2 px-2 pb-2"><input x-model="quickCustomerName" placeholder="氏名 / 会社名 *" class="w-full px-2 py-1.5 border border-gray-200 rounded text-[10px]"><input x-model="quickCustomerEmailVal" placeholder="メールアドレス" type="email" class="w-full px-2 py-1.5 border border-gray-200 rounded text-[10px]"><button @click="bulkQuickCreateAndAssign(); classifyOpen = false" class="w-full bg-blue-600 text-white text-[10px] font-bold py-1.5 rounded hover:bg-blue-700">作成して紐付け</button></div></div>
                    </div>
                </div></div></div>
                <button @click="cancelSelection()" class="hover:bg-white/20 p-1 rounded-full transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
            </div>
        </template>

        <div class="flex-1 overflow-y-auto bg-white custom-scrollbar" id="email-list-container">
            <template x-for="thread in threads" :key="thread.id">
                <div :data-thread-id="thread.id"
                    @mousedown="startLongPress(thread, $event)" @mouseup="cancelLongPress()" @mouseleave="cancelLongPress()"
                    @click="if(!isLongPressing){ selectionMode ? toggleSelection(thread) : loadThread(thread.id) }"
                    class="email-item w-full cursor-pointer transition-all duration-200 thread-list-row"
                    :class="selectedThreadId === thread.id ? 'active' : (selectedThreadIds.includes(thread.id) ? 'selected' : '')">
                    
                    <div class="flex w-full">
                        {{-- Checkbox Column (Fixed Width ~32px) --}}
                        <div class="shrink-0 w-[32px] flex flex-col items-center pt-3">
                            <input type="checkbox" class="thread-checkbox w-4 h-4 cursor-pointer text-blue-600 rounded" 
                                :checked="selectedThreadIds.includes(thread.id)"
                                @click.stop="selectionMode = true; toggleSelection(thread)">
                        </div>

                        {{-- Icons Column (Fixed Width ~24px) --}}
                        <div class="shrink-0 w-[24px] pt-3 flex flex-col items-center gap-1.5">
                            <template x-if="thread.status === 'inbox' || !thread.status"><i class="fas fa-envelope text-primary fa-sm"></i></template>
                            <template x-if="thread.status !== 'inbox' && thread.status"><i class="fas fa-envelope-open text-muted fa-sm"></i></template>
                            <template x-if="thread.has_attachments"><i class="fas fa-paperclip text-muted fa-xs"></i></template>
                            <template x-if="thread.customer_id"><i class="fas fa-user-check text-success fa-xs"></i></template>
                        </div>

                        {{-- Main Content --}}
                        <div class="flex-1 min-w-0 pr-3 pb-2 pt-2.5">
                            <div class="flex items-center justify-between mb-1 gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-[13px] font-bold text-gray-800 truncate" x-text="thread.latest_email?.from_label ?? '—'"></span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-[10px] text-gray-500 font-medium" x-text="thread.last_email_at"></span>
                                </div>
                            </div>
                            <h4 class="subject-clamp text-[13px] font-medium text-gray-700 mb-1.5" x-text="thread.subject"></h4>
                            
                            {{-- Tags wrapper --}}
                            <div class="thread-tags-wrapper">
                                <template x-for="(tag, index) in (thread.tags || []).filter(t => !reservedWords.includes(t))">
                                    <template x-if="index < 3">
                                        <span class="thread-tag-badge bg-indigo-50 text-indigo-500 border border-indigo-100" x-text="'#' + tag"></span>
                                    </template>
                                </template>
                                <template x-if="(thread.tags || []).filter(t => !reservedWords.includes(t)).length > 3">
                                    <span class="thread-tag-badge bg-gray-100 text-gray-500 border border-gray-200" x-text="'＋' + ((thread.tags || []).filter(t => !reservedWords.includes(t)).length - 3)"></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- 4カラム目: スレッド詳細 --}}
    <div x-show="selectedThread || composeMode" class="flex-1 bg-white flex flex-col relative shadow-2xl z-40 overflow-hidden min-w-0 transition-all duration-300">
        <div class="h-full flex relative">
            {{-- Left Sub-Pane: Thread Content (Flexible) --}}
            <div id="thread-content-pane" class="flex-1 flex flex-col overflow-hidden border-r border-gray-200 transition-all duration-300">
            {{-- アクションバー --}}
            <div class="shrink-0 border-b border-gray-100 bg-gray-50/50 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-4">

                    {{-- ↑↓ スレッド間移動 --}}
                    <div class="flex items-center bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden ml-1">
                        <button @click="loadPrevThread()" :disabled="!hasPrevThread" class="p-2 hover:bg-gray-50 disabled:opacity-20 border-r border-gray-100 transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 15l7-7 7 7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                        <button @click="loadNextThread()" :disabled="!hasNextThread" class="p-2 hover:bg-gray-50 disabled:opacity-20 transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                    </div>
                    <div class="flex bg-gray-200 p-0.5 rounded-lg ml-2">
                        <button @click="detailTab = 'thread'" :class="detailTab === 'thread' ? 'bg-white shadow text-blue-600' : 'text-gray-500'" class="px-4 py-1 text-[10px] font-black rounded-md transition-all">スレッド</button>
                        <button @click="detailTab = 'wiki'; if(selectedThread?.customer) selectCategory(selectedThread.customer.name)" :class="detailTab === 'wiki' ? 'bg-white shadow text-blue-600' : 'text-gray-500'" class="px-4 py-1 text-[10px] font-black rounded-md transition-all">Wiki</button>
                        <button @click="detailTab = 'files'" :class="detailTab === 'files' ? 'bg-white shadow text-blue-600' : 'text-gray-500'" class="px-4 py-1 text-[10px] font-black rounded-md transition-all">添付</button>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <template x-if="!composeMode">
                        <div class="flex items-center gap-1.5 mr-2 pr-4 border-r border-gray-200">
                            <button @click="moveToHold(selectedThread)" class="text-[10px] font-black bg-white border border-gray-200 px-4 py-1.5 rounded-xl hover:bg-gray-50 shadow-sm transition-all">保留</button>
                            <button @click="markThreadIgnored(selectedThread)" class="text-[10px] font-black bg-white border border-gray-200 px-4 py-1.5 rounded-xl text-gray-400 hover:bg-gray-50 shadow-sm transition-all">不要</button>
                            <button @click="markThreadComplete(selectedThread)" class="text-[10px] font-black bg-green-600 text-white px-5 py-1.5 rounded-xl shadow-lg hover:bg-green-700 transition-all">完了</button>
                            {{-- 追加: スレッド上部の削除ボタン --}}
                            <button @click="deleteThread(selectedThread)" class="text-[10px] font-black bg-red-50 text-red-600 border border-red-200 px-4 py-1.5 rounded-xl hover:bg-red-500 hover:text-white shadow-sm transition-all ml-1">削除</button>
                        </div>
                    </template>
                    {{-- 全ウィンドウ表示ボタン --}}
                    <button @click="fullThreadMode = !fullThreadMode" class="text-gray-400 hover:text-blue-600 p-2 bg-white border border-gray-200 rounded-xl transition-all shadow-sm">
                        <svg x-show="!fullThreadMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" stroke-width="2.5"/></svg>
                        <svg x-show="fullThreadMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="3"/></svg>
                    </button>
                </div>
            </div>

            {{-- 全画面モード時のみ表示される戻るバー --}}
            <template x-if="fullThreadMode">
                 <div class="px-8 py-3 bg-blue-600 text-white flex items-center shrink-0 shadow-lg relative z-50">
                    <button @click="fullThreadMode = false" class="text-[11px] font-black flex items-center gap-3 hover:bg-white/20 px-5 py-2 rounded-2xl transition-all uppercase tracking-widest"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M11 19l-7-7 7-7M21 19l-7-7 7-7" stroke-width="3.5"/></svg> マルチウィンドウに戻る</button>
                 </div>
            </template>

            {{-- 詳細帯: タグ・顧客・件名・返信ボタン --}}
            <div class="shrink-0 bg-white border-b border-gray-100 px-6 py-4 space-y-3 shadow-sm z-10">
                {{-- 件名 (2行クランプ) --}}
                <div class="flex items-start justify-between gap-4">
                    <h2 class="subject-clamp text-lg font-bold text-gray-900 flex-1 leading-snug" x-text="selectedThread?.subject"></h2>
                    <div class="flex gap-2">

                        <button @click="openReplyOverlay(threadEmails[0])"
                            class="shrink-0 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-5 py-2 rounded-xl shadow transition-all flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            返信
                        </button>
                    </div>
                </div>

                {{-- 顧客選択 + タグ --}}
                <div class="flex items-center gap-3 flex-wrap">
                    {{-- 顧客ドロップダウン (Add New 対応) --}}
                    <div class="relative">
                        <button @click="assignDropdownOpen = !assignDropdownOpen; quickCustomerFormOpen = false"
                            class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-xl px-3 py-1.5 text-xs font-semibold text-gray-700 hover:border-blue-300 transition-all">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-width="2"/></svg>
                            <span x-text="selectedThread?.customer?.name || '顧客を選択'"></span>
                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="2"/></svg>
                        </button>
                        <div x-show="assignDropdownOpen" @click.away="assignDropdownOpen = false; quickCustomerFormOpen = false"
                            class="absolute top-full left-0 mt-1 w-72 bg-white border border-gray-200 shadow-2xl rounded-2xl z-[100] overflow-hidden">
                            <div class="p-2 border-b border-gray-100">
                                <input type="text" x-model="customerSearchQuery" placeholder="顧客を検索..."
                                    class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-300">
                            </div>
                            <div class="max-h-56 overflow-y-auto custom-scrollbar">
                                <template x-for="c in filteredCustomers" :key="c.id">
                                    <button @click="assignCustomer(c.id); assignDropdownOpen = false"
                                        class="w-full text-left px-4 py-2.5 text-xs font-semibold text-gray-700 hover:bg-blue-50 transition-colors border-b border-gray-50 last:border-0"
                                        x-text="c.name"></button>
                                </template>
                            </div>
                            {{-- ＋ 新規顧客を作成 --}}
                            <div class="border-t border-gray-100 p-3">
                                <button @click="quickCustomerFormOpen = !quickCustomerFormOpen; if(quickCustomerFormOpen && threadEmails[0]) quickCustomerEmailVal = threadEmails[0].from_address"
                                    class="w-full flex items-center gap-2 text-xs font-bold text-blue-600 hover:text-blue-800 py-1 transition-all">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2.5"/></svg>
                                    新規顧客を作成して紐付け
                                </button>
                                <div x-show="quickCustomerFormOpen" class="mt-3 space-y-2">
                                    <input x-model="quickCustomerName" placeholder="氏名 / 会社名 *" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-300">
                                    <input x-model="quickCustomerEmailVal" placeholder="メールアドレス (任意)" type="email" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-300">
                                    <button @click="quickCreateAndAssign()"
                                        class="w-full bg-blue-600 text-white text-xs font-bold py-2 rounded-lg hover:bg-blue-700 transition-all">
                                        作成して紐付け
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- タグ --}}
                    <template x-for="tag in (selectedThread?.tags || [])" :key="tag">
                        <button @click="toggleTagFilter(tag)" class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-600 text-[10px] font-bold px-3 py-1 rounded-full border border-indigo-100 hover:bg-indigo-600 hover:text-white transition-all group">
                            <span x-text="'#' + tag"></span>
                            <span @click.stop="removeTagFromThread(selectedThread, tag)" class="opacity-40 hover:opacity-100 group-hover:text-white">✕</span>
                        </button>
                    </template>
                    <button @click="tagEditorOpen = !tagEditorOpen" class="text-[10px] font-bold text-indigo-400 bg-white border border-dashed border-indigo-200 px-3 py-1 rounded-full hover:bg-indigo-50 transition-all">+ タグ追加</button>
                </div>
                <div x-show="tagEditorOpen" class="flex gap-2">
                    <input type="text" x-model="newTagName" @keydown.enter="addTagToSelected()" placeholder="タグ名を入力..." class="flex-1 text-xs px-4 py-2 bg-indigo-50/50 border border-indigo-100 rounded-xl focus:ring-2 focus:ring-indigo-300 outline-none">
                    <button @click="addTagToSelected()" class="bg-indigo-600 text-white text-xs px-5 py-2 rounded-xl font-bold hover:bg-indigo-700 transition-all">追加</button>
                </div>
            </div>

            {{-- メインエリア: スレッド --}}
            <div class="flex-1 overflow-y-auto bg-gray-50 custom-scrollbar" id="thread-main-area">
                <div class="p-8 space-y-6 max-w-4xl mx-auto">

                    {{-- マージ履歴 --}}
                    <template x-if="detailTab === 'thread' && threadMerges && threadMerges.length > 0">
                        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 shadow-sm">
                            <h4 class="text-[10px] font-black text-amber-700 uppercase tracking-widest mb-3">マージされたスレッド履歴</h4>
                            <template x-for="m in threadMerges" :key="m.id">
                                <div class="flex items-center justify-between bg-white px-5 py-3 rounded-xl border border-amber-100 mb-2 shadow-sm">
                                    <div><p class="text-xs font-semibold text-gray-700" x-text="m.source_subject"></p><p class="text-[10px] text-gray-400 mt-0.5" x-text="m.created_at"></p></div>
                                    <button @click="unmerge(m.id)" class="text-[10px] font-bold text-amber-600 bg-amber-100 hover:bg-amber-200 px-4 py-1.5 rounded-lg transition-all">マージ解除</button>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- スレッド履歴 --}}
                    <div x-show="detailTab === 'thread'" class="space-y-4">
                        <template x-for="(email, idx) in threadEmails" :key="email.id">
                            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow group/email">
                                {{-- Email header --}}
                                <div @click="toggleEmail(email.id)"
                                    class="px-6 py-4 cursor-pointer flex items-center justify-between hover:bg-gray-50 transition-colors">
                                    <div class="flex items-center gap-4 min-w-0">
                                        <div class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm shrink-0" x-text="(email.from_label || '?').charAt(0).toUpperCase()"></div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 truncate" x-text="email.from_label"></p>
                                            <p class="text-[11px] text-gray-400" x-text="email.received_at"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button @click.stop="openAiPanel(null, email)" class="text-gray-300 hover:text-indigo-500 p-1.5 rounded-full transition-all" title="AIアシスタント">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1 1 .03 2.798-1.415 2.798H4.213c-1.445 0-2.414-1.798-1.414-2.798L4 15.298"/></svg>
                                        </button>
                                        <button @click.stop="openReplyOverlay(email)" class="text-[11px] bg-blue-50 text-blue-600 px-4 py-1.5 rounded-full font-semibold hover:bg-blue-600 hover:text-white transition-all">返信</button>
                                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="expandedEmailIds.includes(email.id) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </div>
                                </div>
                                {{-- Email body --}}
                                <div x-show="expandedEmailIds.includes(email.id)" class="px-6 py-5 border-t border-gray-100 email-body-text bg-white">
                                    <iframe x-show="!!email.body_html" class="w-full border-0 min-h-[100px]" :srcdoc="email.body_html" sandbox="allow-same-origin allow-popups allow-scripts" @load="$el.style.height = ($el.contentWindow.document.documentElement.scrollHeight + 30) + 'px'"></iframe>
                                    <div x-show="!email.body_html" class="whitespace-pre-wrap leading-relaxed" x-text="email.plain_body"></div>
                                </div>
                            </div>
                        </template>
                        {{-- Reply prompt at bottom --}}
                        <div x-show="threadEmails.length > 0" class="text-center py-4">
                            <button @click="openReplyOverlay(threadEmails[0])"
                                class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold px-8 py-3 rounded-2xl shadow-lg transition-all flex items-center gap-2 mx-auto">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                返信する
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Sidebar --}}
            <div id="right-sidebar" class="bg-white border-left shadow flex flex-col transition-all duration-300" style="width: 0; overflow: hidden;">
                <div style="width: 380px; height: 100%; display: flex; flex-direction: column;">
                    <!-- Header: Tabs + Close -->
                    <div class="sidebar-header d-flex align-items-center justify-content-between p-2 bg-light border-bottom flex-shrink-0">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary active" data-sidebar-tab="thread">スレッド</button>
                            <button type="button" class="btn btn-outline-primary" data-sidebar-tab="wiki">Wiki</button>
                            <button type="button" class="btn btn-outline-primary" data-sidebar-tab="files">添付</button>
                        </div>
                        <button type="button" class="btn btn-sm btn-light close-sidebar">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Tab Contents -->
                    <div class="sidebar-body flex-grow-1" style="overflow-y: auto;">
                        <!-- Thread Tab: Memo & Comments -->
                        <div id="sidebar-tab-thread" class="sidebar-tab-content active">
                            <!-- Memos Section -->
                            <div class="sidebar-section-title px-3 py-2 bg-secondary text-white small font-weight-bold d-flex justify-content-between align-items-center">
                                スレッドメモ
                                <button class="btn btn-xs btn-light btn-sm text-secondary p-0 px-2 py-1" id="add-memo-toggle">＋追加</button>
                            </div>
                            <div id="memo-form-container" class="p-3 d-none border-bottom bg-light">
                                <textarea id="new-memo-content" class="form-control form-control-sm mb-2" rows="3" placeholder="メモを入力..."></textarea>
                                <button id="save-memo-btn" class="btn btn-sm btn-primary btn-block">保存</button>
                            </div>
                            <div id="sidebar-memo-list" class="px-0">
                                <!-- Memos will be rendered here by jQuery -->
                            </div>

                            <!-- Comments Section -->
                            <div class="sidebar-section-title px-3 py-2 bg-success text-white small font-weight-bold d-flex justify-content-between align-items-center mt-3">
                                コメント
                                <button class="btn btn-xs btn-light btn-sm text-success p-0 px-2 py-1" id="add-comment-toggle">＋追加</button>
                            </div>
                            <div id="comment-form-container" class="p-3 d-none border-bottom bg-light">
                                <textarea id="new-comment-content" class="form-control form-control-sm mb-2" rows="3" placeholder="コメントを入力..."></textarea>
                                <button id="post-comment-btn" class="btn btn-sm btn-success btn-block">投稿</button>
                            </div>
                            <div id="sidebar-comment-list" class="px-0">
                                <!-- Comments will be rendered here by jQuery -->
                            </div>
                        </div>

                        <!-- Wiki Tab: University/Customer notes -->
                        <div id="sidebar-tab-wiki" class="sidebar-tab-content">
                            <div class="sidebar-section-title px-3 py-2 bg-info text-white small font-weight-bold">
                                Wiki (大学メモ)
                            </div>
                            <div class="p-3">
                                <div id="wiki-customer-name" class="font-weight-bold mb-2 text-primary small"></div>
                                <div id="wiki-content-container">
                                    <!-- Wiki items will be rendered here -->
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-outline-info btn-block" id="add-wiki-item-btn">+ 項目追加</button>
                                    <button class="btn btn-sm btn-primary btn-block mt-2 d-none" id="save-wiki-btn">Wikiを保存</button>
                                </div>
                            </div>
                        </div>

                        <!-- Files Tab: Attachments -->
                        <div id="sidebar-tab-files" class="sidebar-tab-content">
                            <div class="sidebar-section-title px-3 py-2 bg-warning text-dark small font-weight-bold">
                                添付ファイル
                            </div>
                            <div id="sidebar-file-list" class="list-group list-group-flush">
                                <!-- Files will be rendered here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toggle button (visible when sidebar is closed) -->
            <div id="sidebar-toggle-collapsed">
                <i class="fas fa-chevron-left mr-1"></i>メモ・Wiki・添付
            </div>
        </div>
    </div>
</div>
        </div>
    </div>

    {{-- ======================================================= --}}
    {{-- 全画面返信オーバーレイ (Reply / Compose full-window)    --}}
    {{-- ======================================================= --}}
    <div x-show="replyOverlayOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:1050;background:#fff;"
        class="flex flex-col">

        {{-- オーバーレイヘッダー --}}
        <div class="shrink-0 border-b border-gray-200 px-6 py-3.5 flex items-center justify-between bg-white shadow-sm">
            <div class="flex items-center gap-3 min-w-0">
                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="text-xs text-gray-400 font-semibold">返信:</span>
                <span class="text-sm font-bold text-gray-800 truncate" x-text="composeMode ? '新規メッセージ' : (selectedThread?.subject || '')"></span>
            </div>
            <button @click="closeReplyOverlay()" class="flex items-center gap-2 text-xs font-semibold text-gray-500 hover:text-red-500 bg-gray-50 hover:bg-red-50 border border-gray-200 hover:border-red-200 px-4 py-2 rounded-xl transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
                閉じる
            </button>
        </div>

        {{-- オーバーレイ本体 --}}
        <div class="flex-1 flex overflow-hidden">

            {{-- 左ペイン (58%): スレッド履歴 (読み取り専用) --}}
            <div x-show="!composeMode" style="width:58%;min-width:0;" class="flex flex-col overflow-hidden border-r border-gray-200 bg-gray-50">
                <div class="px-8 py-4 border-b border-gray-200 bg-white shrink-0">
                    <h3 class="subject-clamp text-base font-bold text-gray-900 leading-snug" x-text="selectedThread?.subject"></h3>
                    <p class="text-xs text-gray-400 mt-1" x-text="selectedThread?.customer?.name || '顧客未設定'"></p>
                </div>
                <div class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar">
                    <template x-for="email in threadEmails" :key="email.id">
                        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                            <div @click="toggleEmail(email.id)" class="px-5 py-3.5 cursor-pointer flex items-center justify-between hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs shrink-0" x-text="(email.from_label||'?').charAt(0).toUpperCase()"></div>
                                    <div><p class="text-xs font-semibold text-gray-900" x-text="email.from_label"></p><p class="text-[10px] text-gray-400" x-text="email.received_at"></p></div>
                                </div>
                                <svg class="w-3.5 h-3.5 text-gray-400 transition-transform shrink-0" :class="expandedEmailIds.includes(email.id) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                            <div x-show="expandedEmailIds.includes(email.id)" class="px-5 py-4 border-t border-gray-100 email-body-text" :class="email.is_agent ? 'bg-blue-50/30' : 'bg-white'">
                                <iframe x-show="!!email.body_html" class="w-full border-0 min-h-[80px]" :srcdoc="email.body_html" sandbox="allow-same-origin allow-popups allow-scripts" @load="$el.style.height = ($el.contentWindow.document.documentElement.scrollHeight + 20) + 'px'"></iframe>
                                <div x-show="!email.body_html" class="whitespace-pre-wrap leading-relaxed" x-text="email.plain_body"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- 左ペイン: 新規作成時はプレースホルダー --}}
            <div x-show="composeMode" style="width:58%;" class="border-r border-gray-200 bg-gray-50 flex items-center justify-center">
                <div class="text-center text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke-width="1.5"/></svg>
                    <p class="text-sm font-semibold">新規メッセージ作成</p>
                </div>
            </div>

            {{-- 右ペイン (42%): 返信フォーム + AIパネル --}}
            <div style="width:42%;min-width:0;" class="flex flex-col overflow-hidden bg-white">

                {{-- AIパネル (明示的なAIアイコンクリックでのみ表示) --}}
                <div x-show="replyAiPanelOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="shrink-0 border-b border-indigo-100 bg-indigo-50/20 overflow-y-auto custom-scrollbar" style="max-height:55vh;">
                    <div class="p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-indigo-700 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1 1 .03 2.798-1.415 2.798H4.213c-1.445 0-2.414-1.798-1.414-2.798L4 15.298"/></svg>
                                AIアシスタント
                            </h4>
                            <div class="flex items-center gap-2">
                                <button @click="defaultPromptModalOpen = true" class="text-[10px] font-semibold text-indigo-400 hover:text-indigo-700 bg-white border border-indigo-100 px-2.5 py-1 rounded-lg transition-all">デフォルト設定</button>
                                <button @click="replyAiPanelOpen = false" class="text-gray-400 hover:text-red-500 p-1 transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg></button>
                            </div>
                        </div>
                        <textarea x-model="aiUserPrompt" rows="4" class="w-full text-sm border border-indigo-100 bg-white rounded-xl p-3 focus:ring-2 focus:ring-indigo-200 outline-none leading-relaxed resize-none" placeholder="指示を入力 (空欄でデフォルト使用)..."></textarea>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[9px] font-semibold text-gray-400 uppercase block mb-1">参考URL (オプション)</label>
                                <input type="url" x-model="aiScrapeUrl" placeholder="https://..." class="w-full text-xs border border-gray-200 bg-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
                            </div>
                            <div class="flex items-end">
                                <button @click="askAiForReply()" :disabled="aiLoading" class="w-full bg-indigo-600 text-white py-2 rounded-lg font-bold text-xs shadow hover:bg-indigo-700 transition-all flex items-center justify-center gap-2 disabled:opacity-50">
                                    <svg class="w-3.5 h-3.5" :class="aiLoading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z" stroke-width="2.5"/></svg>
                                    <span x-text="aiLoading ? '生成中...' : '返信案を生成'"></span>
                                </button>
                            </div>
                        </div>
                        <div x-show="aiDraftBody && !aiLoading" class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-[9px] font-bold text-indigo-400 uppercase tracking-widest">生成された返信案</label>
                                <button @click="replyBody = aiDraftBody; replyAiPanelOpen = false" class="text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-lg text-[10px] font-bold hover:bg-indigo-100 transition-all">返信欄へ反映</button>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-3 text-xs text-gray-700 leading-relaxed border border-gray-100 max-h-36 overflow-y-auto custom-scrollbar" x-text="aiDraftBody"></div>
                        </div>
                    </div>
                </div>

                {{-- 返信フォーム --}}
                <div class="flex-1 flex flex-col overflow-y-auto custom-scrollbar">
                    {{-- フォームツールバー --}}
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between shrink-0 bg-gray-50/50">
                        <span class="text-xs font-semibold text-gray-500" x-text="composeMode ? '新規メッセージ' : '返信フォーム'"></span>
                        {{-- AI アイコンボタン (明示的クリックのみ) --}}
                        <button @click="replyAiPanelOpen = !replyAiPanelOpen; if(replyAiPanelOpen){ aiUserPrompt = defaultAiPrompt; editingDefaultPrompt = defaultAiPrompt; aiDraftBody = ''; }"
                            :class="replyAiPanelOpen ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-indigo-600 border-indigo-200 hover:bg-indigo-50'"
                            class="flex items-center gap-2 text-xs font-bold border px-4 py-2 rounded-xl transition-all shadow-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1 1 .03 2.798-1.415 2.798H4.213c-1.445 0-2.414-1.798-1.414-2.798L4 15.298"/></svg>
                            AIアシスタント
                        </button>
                    </div>

                    <div class="flex-1 p-5 space-y-4">
                        {{-- To, CC, BCC --}}
                        <div class="space-y-2.5">
                            <div class="relative">
                                <label class="text-[10px] font-semibold text-gray-400 uppercase absolute left-3 top-1.5 z-10">宛先 (To)</label>
                                <input type="text" x-model="replyToAddress" class="w-full pt-6 pb-2 px-3 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-200">
                            </div>
                            <div class="grid grid-cols-2 gap-2.5">
                                <div class="relative"><label class="text-[10px] font-semibold text-gray-400 uppercase absolute left-3 top-1.5 z-10">Cc</label><input type="text" x-model="replyCc" class="w-full pt-6 pb-2 px-3 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-200"></div>
                                <div class="relative"><label class="text-[10px] font-semibold text-gray-400 uppercase absolute left-3 top-1.5 z-10">Bcc</label><input type="text" x-model="replyBcc" class="w-full pt-6 pb-2 px-3 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-200"></div>
                            </div>
                        </div>
                        {{-- Body --}}
                        <div>
                            <label class="text-[10px] font-semibold text-gray-400 uppercase mb-1.5 block">メッセージ本文</label>
                            <textarea x-model="replyBody" rows="12" class="w-full text-sm border border-gray-200 bg-gray-50 rounded-2xl p-4 focus:ring-2 focus:ring-blue-200 outline-none leading-relaxed resize-none email-body-text" placeholder="返信内容を入力してください..."></textarea>
                        </div>
                        {{-- Attachments --}}
                        <div>
                            <label class="text-[10px] font-semibold text-gray-400 uppercase mb-1.5 block">添付ファイル</label>
                            <input type="file" multiple @change="handleFileSelect($event)" class="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-4 file:rounded-full file:border-0 file:bg-blue-600 file:text-white file:font-semibold cursor-pointer">
                            <div class="flex flex-wrap gap-2 mt-2">
                                <template x-for="(f, i) in selectedFiles" :key="i">
                                    <span class="bg-gray-100 px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-2"><span x-text="f.name"></span><button @click="removeSelectedFile(i)" class="text-red-400 hover:text-red-600">✕</button></span>
                                </template>
                            </div>
                        </div>
                        {{-- Send button --}}
                        <div class="pt-2">
                            <button @click="submitReply()" :disabled="!replyBody || sendingReply"
                                class="w-full bg-blue-600 text-white py-3.5 rounded-2xl font-bold text-sm shadow-lg hover:bg-blue-700 transition-all flex items-center justify-center gap-3 disabled:opacity-50">
                                <span x-text="sendingReply ? '送信中...' : '返信を予約 (承認待ちへ保存)'"></span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ====================================================== --}}
    {{-- スタンドアロン AI ドロワー (メール一覧から開くとき)      --}}
    {{-- ====================================================== --}}
    <div x-show="aiDrawerOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-x-full opacity-0"
        x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0 opacity-100"
        x-transition:leave-end="translate-x-full opacity-0"
        style="position:fixed;top:0;right:0;height:100vh;width:420px;z-index:1040;"
        class="bg-white border-l border-indigo-100 shadow-2xl flex flex-col">
        <div class="px-6 py-5 border-b border-indigo-50 bg-indigo-50/20 flex items-center justify-between shrink-0">
            <h3 class="text-sm font-bold text-indigo-700 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1 1 .03 2.798-1.415 2.798H4.213c-1.445 0-2.414-1.798-1.414-2.798L4 15.298"/></svg>
                AIアシスタント
            </h3>
            <div class="flex items-center gap-2">
                <button @click="defaultPromptModalOpen = true" class="text-[10px] font-semibold text-indigo-400 hover:text-indigo-700 bg-white border border-indigo-100 px-3 py-1.5 rounded-lg transition-all">デフォルト設定</button>
                <button @click="aiDrawerOpen = false" class="text-gray-400 hover:text-indigo-600 p-1.5 bg-white rounded-full border border-gray-100 shadow-sm transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg></button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-5 space-y-5 custom-scrollbar">
            <div class="bg-indigo-50/40 rounded-2xl p-5 border border-indigo-100 space-y-3">
                <label class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest block">AIへの指示プロンプト</label>
                <textarea x-model="aiUserPrompt" rows="6" class="w-full text-sm border border-indigo-100 bg-white rounded-xl p-3 focus:ring-2 focus:ring-indigo-200 outline-none leading-relaxed resize-none" placeholder="指示をここに書いてください... (空欄ならデフォルト指示を使用)"></textarea>
                <div>
                    <label class="text-[10px] font-semibold text-gray-400 uppercase mb-1 block">参考URL (オプション)</label>
                    <input type="url" x-model="aiScrapeUrl" placeholder="https://..." class="w-full text-sm border border-gray-200 bg-white rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-200 outline-none">
                </div>
                <button @click="askAiForReply()" :disabled="aiLoading" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold text-sm shadow hover:bg-indigo-700 transition-all flex items-center justify-center gap-2 disabled:opacity-50">
                    <svg class="w-4 h-4" :class="aiLoading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z" stroke-width="2.5"/></svg>
                    返信案を生成
                </button>
            </div>
            <div x-show="aiLoading" class="text-center text-indigo-400 animate-pulse text-xs font-semibold uppercase tracking-wider">AI is thinking...</div>
            <div x-show="aiDraftBody && !aiLoading" class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest">生成された返信案</label>
                    <button @click="replyBody = aiDraftBody; openReplyOverlay(null)" class="text-indigo-600 bg-indigo-50 px-3 py-1.5 rounded-lg text-[10px] font-bold hover:bg-indigo-100 transition-all">返信フォームへ</button>
                </div>
                <div class="bg-gray-50 rounded-2xl p-4 text-sm text-gray-700 leading-relaxed border border-gray-100" x-text="aiDraftBody"></div>
            </div>
        </div>
    </div>

    {{-- デフォルトプロンプト設定モーダル --}}
    <template x-if="defaultPromptModalOpen">
        <div class="fixed inset-0 z-[1200] flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="defaultPromptModalOpen = false">
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg p-8 animate-in zoom-in duration-200">
                <h3 class="text-lg font-bold mb-2 text-gray-900">デフォルト返信プロンプト設定</h3>
                <p class="text-xs text-gray-400 mb-5">AIアシスタントを開いたとき、プロンプト欄に自動で表示される指示文です。</p>
                <textarea x-model="editingDefaultPrompt" rows="6" class="w-full text-sm border border-gray-200 bg-gray-50 rounded-xl p-4 focus:ring-2 focus:ring-indigo-200 outline-none resize-y" placeholder="例: このスレッドの内容を把握した上で、丁寧で的確な返信を日本語で作成してください。"></textarea>
                <div class="flex gap-3 mt-5">
                    <button @click="defaultPromptModalOpen = false" class="flex-1 py-3 text-sm font-semibold text-gray-500 hover:bg-gray-50 rounded-xl border border-gray-100 transition-all">キャンセル</button>
                    <button @click="saveDefaultPrompt()" class="flex-[2] bg-indigo-600 text-white py-3 rounded-xl font-bold text-sm shadow-lg hover:bg-indigo-700 transition-all">保存する</button>
                </div>
            </div>
        </div>
    </template>

    {{-- 拡大表示/メモ モード (Full screen overlay for Memos) --}}
    <template x-if="expandedMemoMode">
        <div class="fixed inset-0 z-[100] bg-white flex flex-col flex-1 overflow-hidden">
            {{-- Header --}}
            <div class="px-6 py-4 bg-gray-900 text-white flex items-center justify-between shrink-0 shadow-md relative z-10">
                <div class="flex items-center gap-4">
                    <span class="text-sm font-bold truncate">拡大表示・メモ</span>
                </div>
                <button @click="expandedMemoMode = false" class="flex items-center gap-2 text-xs font-semibold hover:text-red-400 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
                    閉じる
                </button>
            </div>
            
            <div class="flex-1 flex overflow-hidden">
                {{-- Left: Thread History (58%) --}}
                <div style="width:58%;min-width:0;" class="flex flex-col overflow-hidden border-r border-gray-200 bg-gray-50">
                    <div class="px-8 py-4 border-b border-gray-200 bg-white shrink-0">
                        <h3 class="subject-clamp text-base font-bold text-gray-900 leading-snug" x-text="selectedThread?.subject"></h3>
                        <p class="text-xs text-gray-400 mt-1" x-text="selectedThread?.customer?.name || '顧客未設定'"></p>
                    </div>
                    <div class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar">
                        <template x-for="email in threadEmails" :key="'memo-'+email.id">
                            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                                <div @click="toggleEmail(email.id)" class="px-5 py-3.5 cursor-pointer flex items-center justify-between hover:bg-gray-50 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-xl bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs shrink-0" x-text="(email.from_label||'?').charAt(0).toUpperCase()"></div>
                                        <div><p class="text-xs font-semibold text-gray-900" x-text="email.from_label"></p><p class="text-[10px] text-gray-400" x-text="email.received_at"></p></div>
                                    </div>
                                    <svg class="w-3.5 h-3.5 text-gray-400 transition-transform shrink-0" :class="expandedEmailIds.includes(email.id) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div x-show="expandedEmailIds.includes(email.id)" class="px-5 py-4 border-t border-gray-100 email-body-text" :class="email.is_agent ? 'bg-blue-50/30' : 'bg-white'">
                                    <iframe x-show="!!email.body_html" class="w-full border-0 min-h-[80px]" :srcdoc="email.body_html" sandbox="allow-same-origin allow-popups allow-scripts" @load="$el.style.height = ($el.contentWindow.document.documentElement.scrollHeight + 20) + 'px'"></iframe>
                                    <div x-show="!email.body_html" class="whitespace-pre-wrap leading-relaxed" x-text="email.plain_body"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Right: Memo Area (42%) --}}
                <div style="width:42%;min-width:0;" class="flex flex-col overflow-hidden bg-white">
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between shrink-0 bg-gray-50/50">
                        <span class="text-xs font-semibold text-gray-500">スレッドメモ</span>
                    </div>
                    <div class="flex-1 flex flex-col p-5 overflow-hidden">
                        {{-- Memo Input Form --}}
                        <div class="shrink-0 mb-6">
                            <label class="text-[10px] font-semibold text-gray-400 uppercase mb-1.5 block">メモを登録</label>
                            <textarea x-model="newMemoContent" rows="6" class="w-full text-sm border border-gray-200 bg-gray-50 rounded-2xl p-4 focus:ring-2 focus:ring-blue-200 outline-none leading-relaxed resize-none mb-2" placeholder="スレッドに関するメモ..."></textarea>
                            <div class="text-right">
                                <button @click="saveMemo()" class="bg-blue-600 text-white font-bold py-2 px-6 rounded-xl hover:bg-blue-700 transition-all text-xs shadow-sm">保存</button>
                            </div>
                        </div>

                        {{-- Saved Memos List --}}
                        <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-4">
                            <label class="text-[10px] font-semibold text-gray-400 uppercase mb-1.5 block">過去のメモ</label>
                            <template x-for="memo in threadMemos" :key="memo.id">
                                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 shadow-sm">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-xs font-bold text-gray-700" x-text="memo.author"></span>
                                        <span class="text-[10px] text-gray-500" x-text="memo.created_at"></span>
                                    </div>
                                    <div class="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed" x-text="memo.content"></div>
                                </div>
                            </template>
                            <template x-if="threadMemos.length === 0">
                                <div class="text-center text-gray-400 text-xs py-4">メモはまだありません</div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- 各種モーダル --}}
    <template x-if="customerGroupModalOpen"><div class="fixed inset-0 z-[110] flex items-center justify-center bg-black/50 backdrop-blur-md p-4" @click.self="customerGroupModalOpen = false"><div class="bg-white rounded-[3rem] shadow-2xl w-full max-w-md p-12 text-center animate-in zoom-in duration-300"><h3 class="text-2xl font-black mb-8 tracking-tighter uppercase text-gray-900">新しいフォルダ</h3><input type="text" x-model="newGroupName" placeholder="フォルダ名を入力..." class="w-full text-lg font-black border-gray-200 bg-gray-50 border-2 rounded-2xl px-8 py-6 outline-none mb-10 text-center focus:ring-4 focus:ring-blue-400 shadow-inner"><div class="flex gap-4"><button @click="customerGroupModalOpen = false" class="flex-1 py-5 text-sm font-black text-gray-400 hover:bg-gray-50 rounded-2xl transition-all">キャンセル</button><button @click="addCustomerGroup()" class="flex-[2] bg-blue-600 text-white py-5 rounded-2xl font-black text-base shadow-xl hover:bg-blue-700 transition-all">フォルダを作成</button></div></div></div></template>
    <template x-if="customerModalOpen"><div class="fixed inset-0 z-[110] flex items-center justify-center bg-black/50 backdrop-blur-md p-4" @click.self="customerModalOpen = false"><div class="bg-white rounded-[3rem] shadow-2xl w-full max-w-md p-12 animate-in zoom-in duration-300 shadow-indigo-100"><h3 class="text-2xl font-black mb-8 text-center tracking-tighter uppercase text-gray-900">顧客を追加</h3><div class="space-y-5 mb-10"><input type="text" x-model="newCustomerName" placeholder="氏名 / 会社名" class="w-full text-base font-black border-gray-200 border-2 bg-gray-50 rounded-2xl px-8 py-5 focus:bg-white focus:ring-4 focus:ring-blue-400 outline-none shadow-inner"><input type="email" x-model="newCustomerEmail" placeholder="メールアドレス (任意)" class="w-full text-base font-black border-gray-200 border-2 bg-gray-50 rounded-2xl px-8 py-5 focus:bg-white focus:ring-4 focus:ring-blue-400 outline-none shadow-inner"></div><div class="flex gap-4"><button @click="customerModalOpen = false" class="flex-1 py-5 text-sm font-black text-gray-400 hover:bg-gray-50 rounded-2xl transition-all">キャンセル</button><button @click="addCustomer()" class="flex-[2] bg-blue-600 text-white py-5 rounded-2xl font-black text-base shadow-xl hover:bg-blue-700 transition-all shadow-blue-100">登録する</button></div></div></div></template>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
function newSearchParams(obj) { return new URLSearchParams(obj); }

function emailApp() {
    return {
        // UI Layout States
        navPanelOpen: false, tagPanelOpen: false, customerModalOpen: false, customerGroupModalOpen: false, fetching: false, 
        detailTab: 'thread', tagEditorOpen: false, assignDropdownOpen: false, loadingThread: false, 
        fullThreadMode: false, isListMaximizing: false, openGroupIds: [], expandedMemoMode: false,
        threadMemos: [], newMemoContent: '', threadComments: [], newCommentContent: '',
        threadsLoading: true,

        // Resizing State
        sidebarWidth: parseInt(localStorage.getItem('sidebarWidth')) || 64,
        navPanelWidth: parseInt(localStorage.getItem('navPanelWidth')) || 280,

        // Selection & Long Press
        selectionMode: false, selectedThreadIds: [], longPressTimer: null, isLongPressing: false,

        // AI & Reply
        replyOverlayOpen: false, replyAiPanelOpen: false, composeMode: false, aiDrawerOpen: false,
        replyBody: '', replyToEmailId: null, replyToAddress: '', replyCc: '', replyBcc: '',
        aiUserPrompt: '', aiDraftBody: '', aiLoading: false, sendingReply: false, selectedFiles: [],
        aiScrapeUrl: '', defaultAiPrompt: '', editingDefaultPrompt: '', defaultPromptModalOpen: false,
        quickCustomerFormOpen: false, quickCustomerName: '', quickCustomerEmailVal: '',

        // Core Data & Filter
        leftTab: 'inbox', searchQuery: '', customerSearchQuery: '', activeCustomerId: null, activeGroupId: null, activeTags: [], sortOrder: 'desc',
        reservedWords: ['完了', '保留', '受信', '対応不要', '不要', 'inbox', 'hold', 'completed', 'ignored', 'test'],
        threads: [], masterTags: [], customerGroups: [], customerData: [], tagMap: {}, pendingCount: 0,
        selectedThreadId: null, selectedThread: null, threadEmails: [], threadMerges: [], expandedEmailIds: [], allAttachments: [],
        newCustomerName: '', newCustomerEmail: '', newGroupName: '', newTagName: '', newGroupParentId: null,
        sortableInstances: {},

        async init() {
            try {
                await Promise.all([
                    this.loadThreads(), this.loadPending(), this.loadCustomers(),
                    this.loadCustomerData(), this.loadTagMapData(), this.loadMasterTags(),
                    this.loadCustomerGroups(), this.loadDefaultPrompt()
                ]);
                const tid = new URLSearchParams(window.location.search).get('thread');
                if (tid) await this.loadThread(parseInt(tid));
                this.$nextTick(() => this.initSorting());
            } catch (e) {}
        },

        async loadDefaultPrompt() {
            try {
                const res = await fetch('/settings/ai/default-prompt');
                const d = await res.json();
                this.defaultAiPrompt = d.prompt || '';
            } catch(e) {}
        },

        async saveDefaultPrompt() {
            try {
                await fetch('/settings/ai/default-prompt', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ prompt: this.editingDefaultPrompt })
                });
                this.defaultAiPrompt = this.editingDefaultPrompt;
                this.defaultPromptModalOpen = false;
            } catch(e) { alert('保存に失敗しました'); }
        },

        // AI panel opener — can be called from email list (pass thread only) or thread card (pass email)
        async openAiPanel(thread, email = null) {
            if (thread && thread.id !== this.selectedThreadId) {
                await this.loadThread(thread.id);
            }
            if (email) {
                this.replyToEmailId = email.id;
                this.replyToAddress = email.from_address;
            } else if (this.threadEmails && this.threadEmails.length > 0) {
                const latest = this.threadEmails[0];
                this.replyToEmailId = latest.id;
                this.replyToAddress = latest.from_address;
            }
            this.aiUserPrompt = this.defaultAiPrompt;
            this.editingDefaultPrompt = this.defaultAiPrompt;
            this.aiDraftBody = '';
            this.aiScrapeUrl = '';
            this.aiDrawerOpen = true;
        },

        async loadMemos() {
            if (!this.selectedThreadId) return;
            try {
                const res = await fetch(`/threads/${this.selectedThreadId}/memos`);
                if (res.ok) {
                    const data = await res.json();
                    this.threadMemos = data.memos;
                }
            } catch (e) { console.error('Failed to load memos', e); }
        },

        async saveMemo() {
            if (!this.newMemoContent.trim() || !this.selectedThreadId) return;
            try {
                const res = await fetch(`/threads/${this.selectedThreadId}/memos`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ content: this.newMemoContent })
                });
                if (res.ok) {
                    const data = await res.json();
                    this.threadMemos.unshift(data.memo);
                    this.newMemoContent = '';
                } else {
                    alert('メモの保存に失敗しました');
                }
            } catch (e) { alert('エラーが発生しました'); }
        },

        async loadComments() {
            if (!this.selectedThreadId) return;
            try {
                const res = await fetch(`/threads/${this.selectedThreadId}/comments`);
                if (res.ok) {
                    const data = await res.json();
                    this.threadComments = data.comments;
                }
            } catch (e) { console.error('Failed to load comments', e); }
        },

        async postComment() {
            if (!this.newCommentContent.trim() || !this.selectedThreadId) return;
            try {
                const res = await fetch(`/threads/${this.selectedThreadId}/comments`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ content: this.newCommentContent })
                });
                if (res.ok) {
                    const data = await res.json();
                    this.threadComments.push(data.comment);
                    this.newCommentContent = '';
                } else {
                    alert('コメントの投稿に失敗しました');
                }
            } catch (e) { alert('エラーが発生しました'); }
        },

        async deleteComment(id) {
            if(!confirm('コメントを削除しますか？')) return;
            try {
                const res = await fetch(`/thread-comments/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                if (res.ok) {
                    this.threadComments = this.threadComments.filter(c => c.id !== id);
                } else {
                    alert('削除に失敗しました');
                }
            } catch (e) { alert('エラーが発生しました'); }
        },

        initSorting() {
            Object.values(this.sortableInstances).forEach(i => i.destroy());
            this.sortableInstances = {};
            const emailList = document.getElementById('email-list-container');
            if (emailList) {
                this.sortableInstances.emailList = new Sortable(emailList, {
                    group: { name: 'emailAssignment', pull: 'clone', put: false },
                    sort: false, animation: 150, draggable: '.email-item'
                });
            }
            ['none', ...this.getAllGroupIds()].forEach(gid => {
                const listEl = document.getElementById(`customer-list-${gid}`);
                if (listEl) {
                    this.sortableInstances[`customers-${gid}`] = new Sortable(listEl, { 
                        group: 'customers', animation: 150, handle: '.c-drag-handle',
                        onEnd: async (evt) => {
                            const cid = evt.item.dataset.customerId; const targetGid = evt.to.dataset.groupId;
                            if (evt.from !== evt.to && cid) await this.moveCustomerToGroup(cid, targetGid);
                            const ids = Array.from(evt.to.querySelectorAll('[data-customer-id]')).map(el => el.dataset.customerId);
                            if (ids.length > 0) this.saveCustomerOrder(targetGid, ids);
                        }
                    });
                }
            });
            document.querySelectorAll('.drop-target-customer').forEach(el => {
                this.sortableInstances[`drop-cid-${el.dataset.cid}`] = new Sortable(el, {
                    group: { name: 'assignTarget', put: ['emailAssignment'] },
                    onAdd: async (evt) => {
                        const tid = evt.item.dataset.threadId; const cid = el.dataset.cid;
                        await this.assignCustomer(cid, tid); evt.item.remove();
                    }
                });
            });
        },

        // Thread Navigation (↑↓矢印)
        get currentNavList() { return this.threads; },
        get currentNavIndex() { return this.currentNavList.findIndex(item => item.id === this.selectedThreadId); },
        get hasPrevThread() { return this.currentNavIndex > 0; },
        get hasNextThread() { return this.currentNavIndex >= 0 && this.currentNavIndex < this.currentNavList.length - 1; },
        async loadPrevThread() { if (this.hasPrevThread) await this.loadThread(this.currentNavList[this.currentNavIndex - 1].id); },
        async loadNextThread() { if (this.hasNextThread) await this.loadThread(this.currentNavList[this.currentNavIndex + 1].id); },

        // Selection / Long Press (0.6s)
        startLongPress(thread, e) {
            this.isLongPressing = false; clearTimeout(this.longPressTimer);
            this.longPressTimer = setTimeout(() => { this.isLongPressing = true; this.selectionMode = true; this.toggleSelection(thread); }, 600);
        },
        cancelLongPress() { clearTimeout(this.longPressTimer); setTimeout(() => { this.isLongPressing = false; }, 10); },
        toggleSelection(thread) {
            const idx = this.selectedThreadIds.indexOf(thread.id);
            if (idx === -1) this.selectedThreadIds.push(thread.id); else this.selectedThreadIds.splice(idx, 1);
            if (this.selectedThreadIds.length === 0) this.selectionMode = false;
        },
        cancelSelection() { this.selectionMode = false; this.selectedThreadIds = []; },
        async assignCustomerToSelected(cid) { if (!this.selectionMode) return; for (const tid of this.selectedThreadIds) await this.assignCustomer(cid, tid); this.cancelSelection(); },

        // Bulk Actions & Thread Deletion
        async bulkAssignCustomerSubmit(cid) {
            if (!this.selectionMode || this.selectedThreadIds.length === 0) return;
            try {
                const res = await fetch('/emails/bulk-assign-customer', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ thread_ids: this.selectedThreadIds, customer_id: cid })
                });
                if (!res.ok) throw new Error('一括紐付けに失敗しました');
                await this.loadThreads();
                this.cancelSelection();
            } catch (e) { alert(e.message); }
        },
        async bulkQuickCreateAndAssign() {
            if (!this.quickCustomerName.trim() || this.selectedThreadIds.length === 0) return;
            try {
                const res = await fetch('/customers', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ name: this.quickCustomerName, email: this.quickCustomerEmailVal })
                });
                const customer = await res.json();
                await this.bulkAssignCustomerSubmit(customer.id);
                await Promise.all([this.loadCustomerData(), this.loadCustomerGroups()]);
                this.quickCustomerName = ''; this.quickCustomerEmailVal = ''; this.quickCustomerFormOpen = false;
            } catch (e) { alert('顧客作成と紐付けに失敗しました'); }
        },
        async bulkMoveToHold() { for (const id of this.selectedThreadIds) await this.updateThreadStatus({id}, 'hold'); this.cancelSelection(); },
        async bulkMoveToComplete() { for (const id of this.selectedThreadIds) await this.updateThreadStatus({id}, 'completed'); this.cancelSelection(); },
        async bulkDelete() { if(confirm('本当に削除しますか？')) { for (const id of this.selectedThreadIds) await fetch(`/threads/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } }); await this.loadThreads(); this.cancelSelection(); } },
        async bulkMerge() { 
            if(this.selectedThreadIds.length !== 2) { alert('マージするにはスレッドを2つ選択してください'); return; }
            if(confirm('選択した2つのスレッドをマージしますか？')) { 
                const targetId = this.selectedThreadIds[0]; const sourceId = this.selectedThreadIds[1];
                try {
                    const res = await fetch(`/threads/${targetId}/merge`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ merge_thread_id: sourceId }) });
                    if (!res.ok) throw new Error('マージに失敗しました');
                    await this.loadThreads(); this.cancelSelection(); alert('マージ完了');
                } catch(e) { alert(e.message); }
            } 
        },
        async deleteThread(t) {
            if(!confirm('本当にこのスレッドを削除しますか？')) return;
            try {
                await fetch(`/threads/${t.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                await this.loadThreads(); this.selectedThread = null; this.fullThreadMode = false;
            } catch(e) { alert('削除に失敗しました'); }
        },
        async unmerge(mergeId) {
            if(!confirm('マージを解除して元のスレッドに戻しますか？')) return;
            try {
                const res = await fetch(`/thread-merges/${mergeId}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                if (!res.ok) throw new Error('解除失敗');
                await this.loadThreads(); if (this.selectedThreadId) await this.loadThread(this.selectedThreadId); alert('マージを解除しました');
            } catch(e) { alert(e.message); }
        },

        // Resize Logic
        startResizeSidebar(e) {
            const startX = e.clientX, startW = this.sidebarWidth;
            const onMove = (me) => { this.sidebarWidth = Math.max(56, Math.min(250, startW + (me.clientX - startX))); };
            const onUp = () => { document.removeEventListener('mousemove', onMove); localStorage.setItem('sidebarWidth', this.sidebarWidth); };
            document.addEventListener('mousemove', onMove); document.addEventListener('mouseup', onUp);
        },
        startResizeNav(e) {
            const startX = e.clientX, startW = this.navPanelWidth;
            const onMove = (me) => { this.navPanelWidth = Math.max(150, Math.min(600, startW + (me.clientX - startX))); };
            const onUp = () => { document.removeEventListener('mousemove', onMove); localStorage.setItem('navPanelWidth', this.navPanelWidth); };
            document.addEventListener('mousemove', onMove); document.addEventListener('mouseup', onUp);
        },
        // API Loading
        async loadThreads() {
            this.threadsLoading = true;
            const params = newSearchParams({ sort: this.sortOrder });
            if (this.searchQuery) params.set('q', this.searchQuery);
            if (this.activeCustomerId) params.set('customer_id', this.activeCustomerId);
            if (this.activeGroupId) params.set('group_id', this.activeGroupId);
            this.activeTags.forEach(t => params.append('tags[]', t));
            const statusMap = { inbox: 'inbox', hold: 'hold', completed: 'completed', ignored: 'ignored', pending: 'pending' };
            if (statusMap[this.leftTab]) params.set('status', statusMap[this.leftTab]);
            try { const res = await fetch('/emails/search?' + params.toString()); this.threads = await res.json(); } catch(e) { this.threads = []; }
            this.threadsLoading = false;
        },

    async loadThread(id) {
            this.selectedThreadId = id; this.detailTab = 'thread'; this.loadingThread = true; this.composeMode = false; this.replyOverlayOpen = false; this.isListMaximizing = false; this.aiDrawerOpen = false;
            try {
                const res = await fetch(`/threads/${id}`); const data = await res.json();
                this.selectedThread = data.thread;
                this.threadEmails = data.emails.sort((a,b) => b.id - a.id);
                this.expandedEmailIds = [this.threadEmails[0]?.id].filter(Boolean);
                this.threadMerges = data.merges || [];
                const allFiles = [];
                data.emails.forEach(e => { (e.attachments||[]).forEach(a => { allFiles.push({...a, received_at: e.received_at}); }); });
                this.allAttachments = allFiles.sort((a,b) => b.id - a.id);
                if (data.thread.customer) await this.loadWikis(data.thread.customer.name);
                
                await Promise.all([this.loadMemos(), this.loadComments()]);
            } catch(e) {}
            this.loadingThread = false;
        },

        // Reply / AI Logic
        openReplyOverlay(email) {
            if (email) {
                this.replyToEmailId = email.id;
                this.replyToAddress = email.from_address;
            }
            this.replyCc = ''; this.replyBcc = ''; this.replyBody = '';
            this.aiDraftBody = ''; this.selectedFiles = [];
            this.aiUserPrompt = this.defaultAiPrompt;
            this.editingDefaultPrompt = this.defaultAiPrompt;
            this.aiScrapeUrl = '';
            this.replyAiPanelOpen = false;
            this.replyOverlayOpen = true;
        },
        closeReplyOverlay() {
            this.replyOverlayOpen = false;
            this.replyAiPanelOpen = false;
            this.composeMode = false;
            this.selectedFiles = [];
        },
        async quickCreateAndAssign() {
            if (!this.quickCustomerName.trim()) return;
            try {
                const res = await fetch('/customers', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ name: this.quickCustomerName, email: this.quickCustomerEmailVal })
                });
                const customer = await res.json();
                await this.assignCustomer(customer.id);
                await Promise.all([this.loadCustomerData(), this.loadCustomerGroups()]);
                this.quickCustomerFormOpen = false;
                this.quickCustomerName = '';
                this.quickCustomerEmailVal = '';
                this.assignDropdownOpen = false;
            } catch(e) { alert('顧客の作成に失敗しました'); }
        },
        async askAiForReply() {
            if (!this.replyToEmailId && !this.composeMode) return;
            this.aiLoading = true;
            try {
                const payload = { prompt: this.aiUserPrompt };
                if (this.aiScrapeUrl) payload.url = this.aiScrapeUrl;
                const res = await fetch(`/emails/${this.replyToEmailId || 0}/ai`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                this.aiDraftBody = typeof data.answer === 'string' ? data.answer : JSON.stringify(data.answer);
            } catch(e) { alert('AI案の生成に失敗しました。'); }
            this.aiLoading = false;
        },
        handleFileSelect(e) { this.selectedFiles = Array.from(e.target.files); },
        removeSelectedFile(i) { this.selectedFiles.splice(i, 1); },
        async submitReply() {
            if (!this.replyBody) return;
            this.sendingReply = true;
            const fd = new FormData();
            fd.append('body', this.replyBody); fd.append('to', this.replyToAddress); fd.append('cc', this.replyCc); fd.append('bcc', this.replyBcc);
            this.selectedFiles.forEach(f => fd.append('attachments[]', f));
            try {
                const url = this.composeMode ? '/emails/send' : `/emails/${this.replyToEmailId}/reply`;
                await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: fd });
                this.closeReplyOverlay(); this.aiDrawerOpen = false;
                alert('返信を承認待ちに保存しました。'); await this.loadPending();
            } catch(e) { alert('処理に失敗しました。'); }
            this.sendingReply = false;
        },

        // Other Actions
        async setLeftTab(tab) { this.leftTab = tab; await this.loadThreads(); },
        openCompose() { this.composeMode = true; this.selectedThreadId = null; this.selectedThread = { subject: '(新規メッセージ)', tags: [], customer: null }; this.threadEmails = []; this.threadMerges = []; this.replyBody = ''; this.replyOverlayOpen = true; this.replyAiPanelOpen = false; this.aiUserPrompt = this.defaultAiPrompt; this.editingDefaultPrompt = this.defaultAiPrompt; this.aiDraftBody = ''; this.aiScrapeUrl = ''; this.detailTab = 'thread'; this.isListMaximizing = false; },
        async updateThreadStatus(t, k) {
            await fetch(`/threads/${t.id}/status`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ status: k }) });
            await this.loadThreads(); this.selectedThread = null; this.fullThreadMode = false;
        },
        async moveToHold(t) { await this.updateThreadStatus(t, 'hold'); },
        async markThreadIgnored(t) { await this.updateThreadStatus(t, 'ignored'); },
        async markThreadComplete(t) { await this.updateThreadStatus(t, 'completed'); },

        async addTagToSelected() {
            if (!this.newTagName.trim() || !this.selectedThreadId) return;
            const tags = [...(this.selectedThread.tags || []), ...this.newTagName.split(/[,、]/).map(s=>s.trim()).filter(s=>s!='')];
            await fetch(`/threads/${this.selectedThreadId}/tags`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ tags: [...new Set(tags)] }) });
            this.newTagName = ''; this.tagEditorOpen = false; await this.loadThread(this.selectedThreadId); await this.loadThreads(); await this.loadTagMapData();
        },
        async removeTagFromThread(t, tag) {
            const tags = (t.tags || []).filter(v => v !== tag);
            await fetch(`/threads/${t.id}/tags`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ tags }) });
            await this.loadThread(t.id); await this.loadThreads();
        },
        async assignCustomer(cid, tid = null) { 
            const threadId = tid || this.selectedThreadId; if (!threadId) return;
            await fetch(`/threads/${threadId}/assign-customer`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ customer_id: cid }) }); 
            if (this.selectedThreadId === threadId) await this.loadThread(threadId);
            await Promise.all([this.loadThreads(), this.loadCustomerData(), this.loadCustomerGroups()]); 
        },

        // API Helpers
        async loadMasterTags() { try { const res = await fetch('/master/tags'); this.masterTags = await res.json(); } catch(e) {} },
        async loadCustomerData() { try { const res = await fetch('/customers/data'); this.customerData = await res.json(); } catch(e) {} },
        async loadTagMapData() { try { const res = await fetch('/tags/data'); this.tagMap = await res.json(); } catch(e) {} },
        async loadCustomers() { try { const res = await fetch('/customers'); this.customers = await res.json(); } catch(e) {} },
        async loadCustomerGroups() { try { const res = await fetch('/customer-groups'); this.customerGroups = await res.json(); } catch(e) {} },
        async loadPending() { try { const res = await fetch('/pending-emails?status=pending'); const d = await res.json(); this.pendingCount = d.length; } catch(e) {} },
        async fetchEmails() { this.fetching = true; await fetch('/emails/fetch', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } }); await this.loadThreads(); this.fetching = false; },
        getAllGroupIds() { let ids = []; const walk = (groups) => { groups.forEach(g => { ids.push(g.id); if (g.children) walk(g.children); }); }; if(this.customerGroups) walk(this.customerGroups); return ids; },
        toggleGroup(id) { const idx = this.openGroupIds.indexOf(id); if (idx === -1) this.openGroupIds.push(id); else this.openGroupIds.splice(idx, 1); },
        isGroupOpen(id) { return this.openGroupIds.includes(id); },

        // Utils
        get filteredUnassignedCustomers() { const q = (this.customerSearchQuery||'').toLowerCase(); return (this.customerData || []).filter(c => !this.isCustomerInAnyGroup(c.id) && c.name.toLowerCase().includes(q)); },
        isCustomerInAnyGroup(cid) { const walk = (groups) => { for (let g of groups) { if ((g.customers||[]).some(c => c.id === cid)) return true; if (g.children && walk(g.children)) return true; } return false; }; if(!this.customerGroups) return false; return walk(this.customerGroups); },
        get filteredMasterTags() { return (this.masterTags || []).filter(mt => !this.reservedWords.includes(mt.name)); },
        get filteredCustomers() { const q = (this.customerSearchQuery||'').toLowerCase(); return (this.customerData || []).filter(c => c.id !== 'none' && c.name.toLowerCase().includes(q)); },
        onSearchInput() { clearTimeout(this.searchDebounce); this.searchDebounce = setTimeout(() => this.loadThreads(), 300); },
        toggleGroupFilter(gid) { this.activeGroupId = (this.activeGroupId === gid ? null : gid); this.loadThreads(); },
        toggleCustomerFilter(id, name) { this.activeCustomerId = (this.activeCustomerId === id ? null : id); this.loadThreads(); },
        toggleTagFilter(tag) { const idx = this.activeTags.indexOf(tag); if (idx === -1) this.activeTags.push(tag); else this.activeTags.splice(idx, 1); this.loadThreads(); },
        toggleEmail(id) { const idx = this.expandedEmailIds.indexOf(id); if (idx === -1) this.expandedEmailIds.push(id); else this.expandedEmailIds.splice(idx, 1); },
        renderMarkdown(t) { return typeof marked !== 'undefined' ? marked.parse(t || '') : t; },
        async loadWikis(name) { try { const res = await fetch(`/tag-notes/${encodeURIComponent(name)}`); const d = await res.json(); this.wikis = (d.content || []).map(w => ({ ...w, mode: 'preview' })); } catch(e) { this.wikis = []; } },
        async saveWikis() { const name = this.selectedThread?.customer?.name; if (!name) return; this.noteSaving = true; await fetch(`/tag-notes/${encodeURIComponent(name)}`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ content: this.wikis.map(w => ({ title: w.title, body: w.body })) }) }); this.noteSaving = false; },
        addWiki() { this.wikis.push({ title: '新規項目', body: '', mode: 'edit' }); },
        removeWiki(i) { if (confirm('削除しますか？')) this.wikis.splice(i, 1); },
        selectCategory(name) { this.loadWikis(name); },
        openCreateGroup(p) { this.newGroupName = ''; this.customerGroupModalOpen = true; },
        async addCustomerGroup() { await fetch('/customer-groups', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ name: this.newGroupName }) }); this.customerGroupModalOpen = false; await this.loadCustomerGroups(); },
        async renameCustomerGroup(g) { const n = prompt('名前変更:', g.name); if(n) { await fetch(`/customer-groups/${g.id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ name: n }) }); await this.loadCustomerGroups(); } },
        async renameCustomer(c) { const n = prompt('名前変更:', c.name); if(n) { await fetch(`/customers/${c.id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ name: n, email: c.email }) }); await Promise.all([this.loadCustomerGroups(), this.loadCustomerData(), this.loadThreads()]); } },
        async moveCustomerToGroup(cid, gid) { await fetch(`/customers/${cid}/move`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ group_id: gid === 'none' ? null : gid }) }); await this.loadCustomerGroups(); await this.loadCustomerData(); },
        async addCustomer() { await fetch('/customers', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ name: this.newCustomerName, email: this.newCustomerEmail }) }); this.customerModalOpen = false; await this.loadCustomerData(); },
        async openMasterTagAdd() { const input = prompt('新しいタグ名:'); if (!input) return; for (const name of input.split(/[,、]/).map(s=>s.trim()).filter(s=>s!='')) { try { await fetch('/master/tags', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ name }) }); } catch(e){} } await Promise.all([this.loadMasterTags(), this.loadTagMapData()]); },
        async saveTagOrder(ids) { await fetch('/master/tags/reorder', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ ids }) }); },
        async saveGroupOrder(ids, p) { await fetch('/customer-groups/reorder', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ ids, parent_id: p }) }); },
        async saveCustomerOrder(gid, ids) { if(ids.length===0)return; await fetch('/customers/reorder', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ ids }) }); }
    };
}
function newSearchParams(obj) { return new URLSearchParams(obj); }

// jQuery Sidebar Logic
$(function() {
    let currentThreadId = null;
    let currentCustomerName = null;
    let wikis = [];

    // Sidebar Toggle
    function toggleSidebar(open) {
        if (open) {
            $('#right-sidebar').addClass('open').css('width', '380px');
            $('#sidebar-toggle-collapsed').addClass('d-none');
        } else {
            $('#right-sidebar').removeClass('open').css('width', '0');
            $('#sidebar-toggle-collapsed').removeClass('d-none');
        }
    }

    $(document).on('click', '#sidebar-toggle-collapsed, .close-sidebar', function() {
        toggleSidebar(!$('#right-sidebar').hasClass('open'));
    });

    // Sidebar Tabs
    $(document).on('click', '[data-sidebar-tab]', function() {
        const tab = $(this).data('sidebar-tab');
        $('[data-sidebar-tab]').removeClass('active');
        $(this).addClass('active');
        $('.sidebar-tab-content').removeClass('active');
        $('#sidebar-tab-' + tab).addClass('active');
    });

    // Hook into thread selection (we can observe selectedThreadId or just wait for calls)
    // For this implementation, we'll use a polling check or just listen for clicks on thread list
    $(document).on('click', '.thread-list-row', function() {
        const threadId = $(this).data('thread-id');
        if (threadId && threadId !== currentThreadId) {
            currentThreadId = threadId;
            loadSidebarData(threadId);
            toggleSidebar(true); // Open sidebar on thread selection
        }
    });

    function loadSidebarData(threadId) {
        // Fetch Thread Data (to get customer name for Wiki)
        $.get(`/threads/${threadId}`, function(data) {
            currentCustomerName = data.thread.customer ? data.thread.customer.name : null;
            renderWiki();
            renderFiles(data.emails);
        });

        // Fetch Memos
        loadMemos(threadId);

        // Fetch Comments
        loadComments(threadId);
    }

    function loadMemos(threadId) {
        $.get(`/threads/${threadId}/memos`, function(data) {
            let html = '';
            data.memos.forEach(m => {
                html += `
                    <div class="memo-item bg-warning-light p-2 mb-2 rounded shadow-sm border" style="background-color: #fff9db;">
                        <div class="d-flex justify-content-between x-small font-weight-bold text-muted mb-1">
                            <span>${m.author}</span>
                            <span>${m.created_at}</span>
                        </div>
                        <div class="small text-dark text-break">${m.content}</div>
                    </div>
                `;
            });
            $('#sidebar-memo-list').html(html || '<div class="text-center text-muted py-3 small">メモはありません</div>');
        });
    }

    function loadComments(threadId) {
        $.get(`/threads/${threadId}/comments`, function(data) {
            let html = '';
            data.comments.forEach(c => {
                html += `
                    <div class="comment-item bg-light p-2 mb-2 rounded shadow-sm border">
                        <div class="d-flex justify-content-between x-small font-weight-bold text-muted mb-1">
                            <span>${c.author}</span>
                            <span>${c.created_at}</span>
                        </div>
                        <div class="small text-dark text-break">${c.content}</div>
                    </div>
                `;
            });
            $('#sidebar-comment-list').html(html || '<div class="text-center text-muted py-3 small">コメントはありません</div>');
        });
    }

    // Memo Store
    $('#add-memo-toggle').on('click', function() {
        $('#memo-form-container').toggleClass('d-none');
    });

    $('#save-memo-btn').on('click', function() {
        const content = $('#new-memo-content').val().trim();
        if (!content || !currentThreadId) return;

        $.post(`/threads/${currentThreadId}/memos`, {
            _token: $('meta[name="csrf-token"]').attr('content'),
            content: content
        }, function(data) {
            $('#new-memo-content').val('');
            $('#memo-form-container').addClass('d-none');
            loadMemos(currentThreadId);
        });
    });

    // Comment Store
    $('#add-comment-toggle').on('click', function() {
        $('#comment-form-container').toggleClass('d-none');
    });

    $('#post-comment-btn').on('click', function() {
        const content = $('#new-comment-content').val().trim();
        if (!content || !currentThreadId) return;

        $.post(`/threads/${currentThreadId}/comments`, {
            _token: $('meta[name="csrf-token"]').attr('content'),
            content: content
        }, function(data) {
            $('#new-comment-content').val('');
            $('#comment-form-container').addClass('d-none');
            loadComments(currentThreadId);
        });
    });

    // Wiki Rendering & Store
    function renderWiki() {
        if (!currentCustomerName) {
            $('#wiki-customer-name').text('顧客未設定');
            $('#wiki-content-container').html('<div class="text-center text-muted py-4 small">顧客が設定されていません</div>');
            $('#save-wiki-btn').addClass('d-none');
            return;
        }

        $('#wiki-customer-name').text(currentCustomerName);
        $.get(`/tag-notes/${encodeURIComponent(currentCustomerName)}`, function(data) {
            wikis = data.content || [];
            let html = '';
            wikis.forEach((w, i) => {
                html += `
                    <div class="wiki-item border rounded p-2 mb-2 bg-white shadow-sm">
                        <div class="form-group mb-1">
                            <input type="text" class="form-control form-control-sm font-weight-bold wiki-title-input" data-index="${i}" value="${w.title}">
                        </div>
                        <textarea class="form-control form-control-sm wiki-body-input" data-index="${i}" rows="3">${w.body}</textarea>
                        <div class="text-right mt-1">
                            <button class="btn btn-xs btn-link text-danger remove-wiki-item" data-index="${i}">削除</button>
                        </div>
                    </div>
                `;
            });
            $('#wiki-content-container').html(html || '<div class="text-center text-muted py-3 small">Wiki項目がありません</div>');
            $('#save-wiki-btn').removeClass('d-none');
        });
    }

    $('#add-wiki-item-btn').on('click', function() {
        if (!currentCustomerName) return;
        wikis.push({ title: '新規項目', body: '' });
        refreshWikiUI();
    });

    function refreshWikiUI() {
        let html = '';
        wikis.forEach((w, i) => {
            html += `
                <div class="wiki-item border rounded p-2 mb-2 bg-white shadow-sm">
                    <div class="form-group mb-1">
                        <input type="text" class="form-control form-control-sm font-weight-bold wiki-title-input" data-index="${i}" value="${w.title}">
                    </div>
                    <textarea class="form-control form-control-sm wiki-body-input" data-index="${i}" rows="3">${w.body}</textarea>
                    <div class="text-right mt-1">
                        <button class="btn btn-xs btn-link text-danger remove-wiki-item" data-index="${i}">削除</button>
                    </div>
                </div>
            `;
        });
        $('#wiki-content-container').html(html);
        $('#save-wiki-btn').removeClass('d-none');
    }

    $(document).on('change', '.wiki-title-input', function() { wikis[$(this).data('index')].title = $(this).val(); });
    $(document).on('change', '.wiki-body-input', function() { wikis[$(this).data('index')].body = $(this).val(); });
    $(document).on('click', '.remove-wiki-item', function() {
        wikis.splice($(this).data('index'), 1);
        refreshWikiUI();
    });

    $('#save-wiki-btn').on('click', function() {
        if (!currentCustomerName) return;
        $.ajax({
            url: `/tag-notes/${encodeURIComponent(currentCustomerName)}`,
            method: 'PUT',
            data: JSON.stringify({ content: wikis }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function() {
                alert('Wikiを保存しました');
            }
        });
    });

    // Files Rendering
    function renderFiles(emails) {
        const allFiles = [];
        emails.forEach(e => {
            (e.attachments || []).forEach(a => {
                allFiles.push({ ...a, received_at: e.received_at });
            });
        });

        let html = '';
        allFiles.sort((a,b) => b.id - a.id).forEach(f => {
            html += `
                <a href="${f.url}" target="_blank" class="list-group-item list-group-item-action p-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="text-truncate mr-2 small font-weight-bold">
                            <i class="fas fa-file-alt mr-1 text-muted"></i> ${f.filename}
                        </div>
                        <span class="x-small text-muted">${f.received_at}</span>
                    </div>
                </a>
            `;
        });
        $('#sidebar-file-list').html(html || '<div class="text-center text-muted py-4 small">添付ファイルはありません</div>');
    }
});
</script>

<style>
/* Scrollbar */
.custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #E5E7EB; border-radius: 10px; }
.no-scrollbar::-webkit-scrollbar { display: none; }
[x-cloak] { display: none !important; }

/* Thread list subject: wrap to 2 lines max with ellipsis */
.subject-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    white-space: normal;
    word-break: break-word;
}

/* Clean readable font for thread/email body text */
.email-body-text, #email-list-container {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 14px;
    line-height: 1.6;
    color: #374151;
}

/* Fixed email list column */
.mail-list-fixed { min-width: 340px; max-width: 340px; width: 340px; flex-shrink: 0; }

/* Drag handles */
.drag-handle, .c-drag-handle, .group-drag-handle { cursor: grab; }
.drag-handle:active { cursor: grabbing; }
.sortable-ghost { opacity: 0.4; background: #EEF2FF !important; border: 1px dashed #3B82F6; }
</style>
@endsection