@extends('layouts.app')
@section('title', 'メール管理')

@section('content')
<div class="flex h-full bg-gray-50 overflow-hidden text-gray-900 font-sans" x-data="emailApp()" x-init="init()" x-cloak>

    {{-- 1カラム目: ミニサイドバー (リサイズ可能) --}}
    {{-- "Shift" behavior: Hide when replyAiPanelOpen is true. --}}
    <template x-if="!replyAiPanelOpen && !fullThreadMode && !isListMaximizing">
        <div x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             :style="'width:' + sidebarWidth + 'px'" 
             class="shrink-0 border-r border-gray-200 bg-white flex flex-col items-center py-6 gap-6 z-50 shadow-sm relative transition-all duration-300 overflow-hidden">
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
    </template>

    {{-- メインワークスペースコンテナ --}}
    <div class="flex flex-1 min-h-0 overflow-hidden">
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
                        {{-- ID added for Sortable --}}
                        <div id="customer-list-none" data-group-id="none" class="px-2 space-y-0.5 mt-1 min-h-[10px]">
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
                                {{-- ID and data-customer-id added for Sortable --}}
                                <div x-show="isGroupOpen(group.id)" x-collapse :id="'customer-list-' + group.id" :data-group-id="group.id" class="ml-9 border-l border-gray-200/50 mt-1 space-y-1">
                                    <template x-for="c in (group.customers || [])" :key="c.id">
                                        <div :data-customer-id="c.id" class="group/c flex items-center gap-1 drop-target-customer" :data-cid="c.id">
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

        {{-- 3カラム目: メール一覧 (received threads) --}}
    <div x-show="!fullThreadMode" 
        class="flex flex-col shrink-0 overflow-hidden bg-white border-r border-gray-200 relative z-20 thread-list-column shadow-sm"
        :style="'width:' + threadWidth + 'px'" style="min-width: 250px">
        
        {{-- Row 1: 新規作成 --}}
        <div class="shrink-0 px-4 py-3 border-b border-gray-100 bg-white flex justify-end items-center">
            <button @click="openCompose()" 
                class="bg-blue-600 text-white text-[11px] px-6 py-2 rounded-xl font-black shadow-lg hover:bg-blue-700 transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> 新規作成
            </button>
        </div>

        {{-- Row 2: ステータスフィルター --}}
        <div class="shrink-0 px-3 py-2 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
            <div class="flex items-center gap-1 bg-gray-200/50 p-1 rounded-xl shadow-inner flex-1 overflow-hidden">
                <button @click="setLeftTab('inbox')" :class="leftTab==='inbox'?'bg-white shadow text-blue-600':'text-gray-500'" class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all truncate">受信</button>
                <button @click="setLeftTab('hold')" :class="leftTab==='hold'?'bg-white shadow text-gray-800':'text-gray-500'" class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all truncate">保留</button>
                <button @click="setLeftTab('completed')" :class="leftTab==='completed'?'bg-white shadow text-green-600':'text-gray-500'" class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all truncate">完了</button>
                <button @click="setLeftTab('pending')" :class="leftTab==='pending'?'bg-white shadow text-amber-600':'text-gray-500'" class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all flex items-center justify-center gap-1 truncate">
                    承認待ち <span x-show="pendingCount > 0" class="bg-amber-500 text-white px-1.5 rounded-full text-[8px]" x-text="pendingCount"></span>
                </button>
            </div>
        </div>

        {{-- Row 3: 列ヘッダー --}}
        <div class="shrink-0 px-3 py-1.5 border-b border-gray-100 bg-white flex items-center text-gray-400 font-black uppercase tracking-tighter text-[9px]">
            <div class="shrink-0 w-[32px] flex justify-center">
                <input type="checkbox" class="rounded-sm border-gray-300 text-blue-600 focus:ring-blue-500" 
                    @click="selectionMode = !selectionMode; if(selectionMode) selectedThreadIds = threads.map(t => t.id); else selectedThreadIds = []">
            </div>
            <div class="shrink-0 w-[24px]"></div>
            <div class="flex-1 min-w-0 pl-1 flex items-center gap-1 cursor-pointer hover:text-blue-600 transition-colors"
                @click="toggleSort('last_email_at')">
                差出人 / 日時 
                <i class="fas" :class="sortKey === 'last_email_at' ? (sortOrder === 'asc' ? 'fa-sort-up text-blue-600' : 'fa-sort-down text-blue-600') : 'fa-sort opacity-20'"></i>
            </div>
            <div class="w-20 text-right flex items-center justify-end gap-1 cursor-pointer hover:text-blue-600 transition-colors"
                @click="toggleSort('subject')">
                件名 
                <i class="fas" :class="sortKey === 'subject' ? (sortOrder === 'asc' ? 'fa-sort-up text-blue-600' : 'fa-sort-down text-blue-600') : 'fa-sort opacity-20'"></i>
            </div>
        </div>

        {{-- Row 4: 検索 & タグフィルター --}}
        <div class="shrink-0 px-4 py-3 border-b border-gray-100 bg-white space-y-3">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[10px]"></i>
                <input type="text" x-model="searchQuery" @input="onSearchInput()" placeholder="検索..." 
                    class="w-full pl-9 pr-4 py-2 bg-gray-50 border-none rounded-2xl text-[11px] focus:ring-2 focus:ring-blue-400 outline-none font-medium shadow-inner">
            </div>
            <div class="flex flex-wrap items-center gap-1.5 max-h-32 overflow-y-auto no-scrollbar">
                <button @click="activeTags = []" 
                    :class="activeTags.length === 0 ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'" 
                    class="shrink-0 text-[8px] font-black px-3 py-1 rounded-full transition-all">すべて</button>
                <template x-for="tag in filteredMasterTags" :key="tag.id">
                    <button @click="toggleTagFilter(tag.name)" 
                        :class="activeTags.includes(tag.name)?'bg-indigo-600 text-white shadow-md':'bg-indigo-50 text-indigo-500 hover:bg-indigo-100'" 
                        class="shrink-0 text-[8px] font-black px-3 py-1 rounded-full transition-all border border-indigo-100" 
                        x-text="'#' + tag.name"></button>
                </template>
            </div>
        </div>

        {{-- 長押し一括操作アクションバー --}}
        <template x-if="selectionMode">
            <div class="px-4 py-2.5 bg-blue-600 text-white flex items-center justify-between sticky top-0 z-30 shadow-lg animate-in slide-in-from-top duration-300">
                <div class="flex items-center gap-2"><span class="text-[11px] font-black text-white/90" x-text="selectedThreadIds.length + ' 件選択'"></span><div class="flex items-center gap-1">
                        <button @click="bulkMoveToInbox()" class="text-[9px] font-black bg-white/20 hover:bg-white/30 px-2 py-1 rounded-lg transition-all text-blue-200">受信</button>
                        <button @click="bulkMoveToHold()" class="text-[9px] font-black bg-white/20 hover:bg-white/30 px-2 py-1 rounded-lg transition-all">保留</button>
                        <button @click="bulkMoveToComplete()" class="text-[9px] font-black bg-white/20 hover:bg-white/30 px-2 py-1 rounded-lg text-green-300">完了</button>
                        <button @click="bulkMerge()" class="text-[9px] font-black bg-white/20 hover:bg-white/30 px-2 py-1 rounded-lg transition-all">マージ</button>
                        <button @click="bulkDelete()" class="text-[9px] font-black bg-red-500 hover:bg-red-600 px-2 py-1 rounded-lg transition-all shadow-sm">削除</button>
                    </div>
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

    {{-- ======================================================= --}}
    {{-- Column 3: ワークスペース (Unified 3-Pane Side-by-Side)    --}}
    {{-- ======================================================= --}}
    <div x-show="selectedThread || composeMode" 
         class="flex-1 flex flex-col relative overflow-hidden bg-white z-40 transition-all duration-300">
        
        {{-- Unified Header --}}
        <div class="shrink-0 border-b border-gray-200 px-6 py-2.5 flex items-center justify-between bg-white shadow-sm z-50">
            <div class="flex items-center gap-4 min-w-0">
                <div x-show="!composeMode" class="flex items-center bg-gray-50 border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <button @click="loadPrevThread()" :disabled="!hasPrevThread" class="p-1.5 hover:bg-white disabled:opacity-20 border-r border-gray-100 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 15l7-7 7 7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <button @click="loadNextThread()" :disabled="!hasNextThread" class="p-1.5 hover:bg-white disabled:opacity-20 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
                <div class="flex items-center gap-2 min-w-0">
                    <template x-if="composeMode">
                        <span class="bg-blue-600 text-white text-[10px] font-black px-2 py-0.5 rounded uppercase tracking-widest shrink-0">Reply Mode</span>
                    </template>
                    <h2 class="text-sm font-bold text-gray-900 truncate" x-text="selectedThread?.subject || '新規メッセージ'"></h2>
                </div>
            </div>
            <div class="flex items-center gap-3">
                {{-- AI Toggle (Visible when composing) --}}
                <button x-show="composeMode" @click="replyAiPanelOpen = !replyAiPanelOpen; if(replyAiPanelOpen && !aiAnalysis) aiUserPrompt = defaultAiPrompt;"
                    :class="replyAiPanelOpen ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-indigo-600 border-indigo-200 hover:bg-indigo-50'"
                    class="flex items-center gap-2 text-[10px] font-black border px-4 py-2 rounded-xl transition-all shadow-sm uppercase tracking-widest">
                    <i class="fas fa-magic"></i> AIアシスタント
                </button>
                {{-- Fullscreen Toggle --}}
                <button @click="fullThreadMode = !fullThreadMode" class="text-gray-400 hover:text-blue-600 p-2 bg-white border border-gray-200 rounded-xl transition-all shadow-sm">
                    <svg x-show="!fullThreadMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" stroke-width="2.5"/></svg>
                    <svg x-show="fullThreadMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="3"/></svg>
                </button>
                {{-- Close button --}}
                <button x-show="composeMode" @click="closeReplyOverlay()" class="text-gray-400 hover:text-red-500 p-2 bg-white border border-gray-200 rounded-xl transition-all shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
                </button>
            </div>
        </div>

        {{-- Flex Workspace Container: Pane 1, 2, 3 Side-by-Side --}}
        <div x-ref="workspaceContainer" class="flex-1 flex overflow-hidden">
            
            {{-- Pane 1: Thread History / Detail View (Dynamic Width) --}}
            <div x-show="selectedThread" 
                 :style="composeMode ? 'width: ' + overlayHistoryWidth + '%; min-width: 200px;' : 'flex: 1'"
                 class="flex flex-col overflow-hidden relative border-r border-gray-200 bg-gray-50 transition-all duration-75">
                
                {{-- If NOT composing: Full Detail View --}}
                <div x-show="!composeMode" class="flex-1 flex flex-col overflow-hidden">
                    <div class="h-full flex relative">
                        {{-- Left Sub-Pane: Thread Content (Flexible) --}}
                        <div id="thread-content-pane" class="flex-1 flex flex-col overflow-hidden border-r border-gray-200 transition-all duration-300">
                            {{-- アクションバー (Tabs removed, Quick Actions only) --}}
                            <div class="shrink-0 border-b border-gray-100 bg-gray-50/50 px-6 py-2 flex items-center justify-end">
                                <div class="flex items-center gap-1.5">
                                    <button @click="updateThreadStatus(selectedThread, 'inbox')" 
                                        :class="selectedThread.status === 'inbox' || !selectedThread.status ? 'bg-blue-600 text-white ring-2 ring-blue-400' : 'bg-white text-gray-700'"
                                        class="text-[10px] font-black border border-gray-200 px-3 py-1 rounded-lg hover:bg-gray-50 shadow-sm transition-all">受信</button>
                                    <button @click="updateThreadStatus(selectedThread, 'hold')" 
                                        :class="selectedThread.status === 'hold' ? 'bg-gray-800 text-white ring-2 ring-gray-400' : 'bg-white text-gray-700'"
                                        class="text-[10px] font-black border border-gray-200 px-3 py-1 rounded-lg hover:bg-gray-50 shadow-sm transition-all">保留</button>
                                    <button @click="updateThreadStatus(selectedThread, 'completed')" 
                                        :class="selectedThread.status === 'completed' ? 'bg-green-600 text-white ring-2 ring-green-400' : 'bg-white text-gray-700'"
                                        class="text-[10px] font-black border border-gray-200 px-3 py-1 rounded-lg hover:bg-gray-50 shadow-sm transition-all">完了</button>
                                    <button @click="deleteThread(selectedThread)" class="text-[10px] font-black bg-red-50 text-red-600 border border-red-200 px-3 py-1 rounded-lg hover:bg-red-500 hover:text-white shadow-sm transition-all ml-1">削除</button>
                                </div>
                            </div>

                            {{-- 詳細帯: タグ・顧客・件名・返信ボタン --}}
                            <div class="shrink-0 bg-white border-b border-gray-100 px-6 py-4 space-y-3 shadow-sm z-10">
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
                                <div class="flex items-center gap-3 flex-wrap">
                                    <div class="relative">
                                        <button @click="assignDropdownOpen = !assignDropdownOpen; quickCustomerFormOpen = false"
                                            class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-xl px-3 py-1.5 text-xs font-semibold text-gray-700 hover:border-blue-300 transition-all">
                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-width="2"/></svg>
                                            <span x-text="selectedThread?.customer?.name || '顧客を選択'"></span>
                                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="2"/></svg>
                                        </button>
                                        <div x-show="assignDropdownOpen" @click.away="assignDropdownOpen = false"
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
                                        </div>
                                    </div>
                                    <template x-for="tag in (selectedThread?.tags || [])" :key="tag">
                                        <button @click="toggleTagFilter(tag)" class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-600 text-[10px] font-bold px-3 py-1 rounded-full border border-indigo-100 hover:bg-indigo-600 hover:text-white transition-all group">
                                            <span x-text="'#' + tag"></span>
                                            <span @click.stop="removeTagFromThread(selectedThread, tag)" class="opacity-40 hover:opacity-100 group-hover:text-white">✕</span>
                                        </button>
                                    </template>
                                    </div>

                                    {{-- マージ済みスレッドリスト --}}
                                    <template x-if="threadMerges.length > 0">
                                    <div class="px-6 py-2 bg-blue-50/50 border-b border-blue-100 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[10px] font-black text-blue-600 uppercase tracking-widest">マージ済みスレッド: <span x-text="threadMerges.length"></span>件</span>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="m in threadMerges" :key="m.id">
                                                <div class="flex items-center gap-2 bg-white border border-blue-200 px-3 py-1.5 rounded-xl shadow-sm">
                                                    <span class="text-[10px] font-bold text-gray-700 truncate max-w-[200px]" x-text="m.source_thread_subject"></span>
                                                    <button @click="unmergeThread(m.id)" class="text-[9px] font-black text-blue-500 hover:text-red-500 transition-colors">解除</button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    </template>
                                    </div>
                            {{-- メインエリア: スレッド履歴 --}}
                            <div class="flex-1 overflow-y-auto bg-gray-50 custom-scrollbar" id="thread-main-area">
                                <div class="p-8 space-y-6 max-w-4xl mx-auto">
                                    <div class="space-y-4">

                                        <template x-for="(email, idx) in threadEmails" :key="email.id">
                                            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow group/email">
                                                <div @click="toggleEmail(email.id)" class="px-6 py-4 cursor-pointer flex items-center justify-between hover:bg-gray-50 transition-colors">
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
                                                <div x-show="expandedEmailIds.includes(email.id)" class="px-6 py-5 border-t border-gray-100 email-body-text bg-white">
                                                    <iframe x-show="!!email.body_html" class="w-full border-0 min-h-[100px]" :srcdoc="email.body_html" sandbox="allow-same-origin allow-popups allow-scripts" @load="$el.style.height = ($el.contentWindow.document.documentElement.scrollHeight + 30) + 'px'"></iframe>
                                                    <div x-show="!email.body_html" class="whitespace-pre-wrap leading-relaxed" x-text="email.plain_body"></div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Right Sidebar (jQuery) --}}
                        <div id="right-sidebar" class="bg-white border-l shadow transition-all duration-300 overflow-hidden" style="width: 0;">
                            <div style="width: 380px; height: 100%; display: flex; flex-direction: column;">
                                <div class="sidebar-header d-flex align-items-center justify-content-end p-2 bg-light border-bottom flex-shrink-0">
                                    <button type="button" class="btn btn-sm btn-light close-sidebar"><i class="fas fa-times"></i></button>
                                </div>
                                <div class="sidebar-body flex-grow-1 overflow-y-auto">
                                    <div id="sidebar-tab-thread" class="sidebar-tab-content active">
                                        <div class="sidebar-section-title px-3 py-2 bg-secondary text-white small font-weight-bold d-flex justify-content-between align-items-center">メモ<button class="btn btn-xs btn-light btn-sm text-secondary p-0 px-2 py-1" id="add-memo-toggle">＋追加</button></div>
                                        <div id="sidebar-memo-list" class="px-0"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="sidebar-toggle-collapsed" class="absolute right-0 top-1/2 -translate-y-1/2 bg-white border border-r-0 border-gray-200 py-4 px-1 rounded-l-xl cursor-pointer shadow-sm z-30 text-[10px] font-bold text-gray-400 hover:text-blue-600 transition-all flex flex-col items-center gap-2">
                             <i class="fas fa-chevron-left"></i><span>メモ</span>
                        </div>
                    </div>
                </div>

                {{-- If composing: Simplified History Pane (Active when composing) --}}
                <div x-show="composeMode" class="flex-1 flex flex-col overflow-hidden">
                    <div class="px-6 py-3 border-b border-gray-200 bg-white shrink-0">
                         <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest">Thread History</h3>
                    </div>
                    <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                        <template x-for="email in threadEmails" :key="'compose-history-' + email.id">
                            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                                <div @click="toggleEmail(email.id)" class="px-4 py-2.5 cursor-pointer flex items-center justify-between hover:bg-gray-50 transition-colors">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-[10px] shrink-0" x-text="(email.from_label||'?').charAt(0).toUpperCase()"></div>
                                        <div class="min-w-0 text-left">
                                            <p class="text-[11px] font-bold text-gray-900 truncate" x-text="email.from_label"></p>
                                            <p class="text-[9px] text-gray-400" x-text="email.received_at"></p>
                                        </div>
                                    </div>
                                    <svg class="w-3 h-3 text-gray-400 transition-transform" :class="expandedEmailIds.includes(email.id) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="2"/></svg>
                                </div>
                                <div x-show="expandedEmailIds.includes(email.id)" class="px-4 py-3 border-t border-gray-100 bg-white">
                                     <div class="text-[11px] leading-relaxed text-gray-700 whitespace-pre-wrap" x-text="email.plain_body"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Handle 1 (History Resize) --}}
            <div x-show="composeMode" 
                 class="w-1 cursor-col-resize hover:bg-indigo-400 z-50 transition-colors"
                 :class="resizingType === 'history' ? 'bg-indigo-400' : 'bg-gray-100'"
                 @mousedown="startResizeOverlay('history', $event)"></div>

            {{-- Pane 2: Reply Draft Form (Anchor - Always visible in Compose Mode) --}}
            <div x-show="composeMode" 
                 style="min-width: 200px;"
                 class="flex-1 flex flex-col overflow-hidden bg-white">
                 <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6">
                    <div class="space-y-3">
                        <div class="relative">
                            <label class="text-[10px] font-bold text-gray-400 uppercase absolute left-3 top-1.5 z-10">宛先 (To)</label>
                            <input type="text" x-model="replyToAddress" class="w-full pt-6 pb-2 px-3 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-200">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="relative"><label class="text-[10px] font-bold text-gray-400 uppercase absolute left-3 top-1.5 z-10">Cc</label><input type="text" x-model="replyCc" class="w-full pt-6 pb-2 px-3 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-200"></div>
                            <div class="relative"><label class="text-[10px] font-bold text-gray-400 uppercase absolute left-3 top-1.5 z-10">Bcc</label><input type="text" x-model="replyBcc" class="w-full pt-6 pb-2 px-3 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-200"></div>
                        </div>
                    </div>
                    <div class="flex-1 flex flex-col min-h-[500px]">
                        <label class="text-[10px] font-bold text-gray-400 uppercase mb-2 block">メッセージ本文</label>
                        <textarea x-model="replyBody" class="flex-1 w-full text-sm border border-gray-200 bg-gray-50 rounded-2xl p-3 text-left focus:ring-2 focus:ring-blue-200 outline-none leading-relaxed resize-none email-body-text" style="vertical-align: top;" placeholder="返信内容を入力してください..."></textarea>
                    </div>
                    <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                         <div class="flex items-center gap-3">
                             <input type="file" multiple @change="handleFileSelect($event)" class="hidden" id="reply-file-input">
                             <label for="reply-file-input" class="cursor-pointer bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-2 rounded-xl text-[11px] font-bold transition-all flex items-center gap-2">
                                 <i class="fas fa-paperclip"></i> 添付
                             </label>
                             <div class="flex gap-1">
                                 <template x-for="(f, i) in selectedFiles" :key="i">
                                     <span class="bg-blue-50 text-blue-600 px-2 py-1 rounded text-[10px] font-bold flex items-center gap-1"><span x-text="f.name"></span><button @click="removeSelectedFile(i)">✕</button></span>
                                 </template>
                             </div>
                         </div>
                         <button @click="submitReply()" :disabled="!replyBody || sendingReply"
                            class="bg-blue-600 text-white px-8 py-3 rounded-2xl font-bold text-sm shadow-lg hover:bg-blue-700 transition-all flex items-center gap-3 disabled:opacity-50">
                            <span x-text="sendingReply ? '送信中...' : '承認依頼'"></span>
                            <i class="fas fa-paper-plane"></i>
                         </button>
                    </div>
                 </div>
            </div>

            {{-- Handle 2 (AI Resize) --}}
            <div x-show="composeMode && replyAiPanelOpen" 
                 class="w-1 cursor-col-resize hover:bg-indigo-400 z-50 transition-colors"
                 :class="resizingType === 'ai' ? 'bg-indigo-400' : 'bg-gray-100'"
                 @mousedown="startResizeOverlay('ai', $event)"></div>

            {{-- Pane 3: AI Assistant (Visible when AI Open) --}}
            <aside x-show="composeMode && replyAiPanelOpen" 
                   :style="'width: ' + overlayAiWidth + 'px; min-width: 200px;'"
                   class="shrink-0 flex flex-col bg-white overflow-hidden shadow-2xl border-l border-indigo-50">
                 <div class="px-6 py-4 border-b border-indigo-50 bg-indigo-50/20 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-indigo-700 flex items-center gap-2">
                        <i class="fas fa-magic"></i> AIアシスタント
                    </h3>
                    <button @click="replyAiPanelOpen = false" class="text-gray-400 hover:text-indigo-600 p-1.5"><i class="fas fa-times"></i></button>
                </div>
                <div class="flex-1 overflow-y-auto p-5 space-y-6 custom-scrollbar">
                    <div class="bg-indigo-50/40 rounded-2xl p-4 border border-indigo-100 space-y-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest block">AIへの指示</label>
                            <textarea x-model="aiUserPrompt" rows="3" class="w-full text-[11px] border border-indigo-100 bg-white rounded-xl p-3 focus:ring-2 focus:ring-indigo-200 outline-none leading-relaxed resize-none" placeholder="指示を入力..."></textarea>
                        </div>
                        <button @click="askAiForReply()" :disabled="aiLoading" class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-bold text-[11px] shadow-lg hover:bg-indigo-700 transition-all flex items-center justify-center gap-2 disabled:opacity-50">
                            <i class="fas fa-bolt" :class="aiLoading ? 'animate-spin' : ''"></i>
                            <span>生成する</span>
                        </button>
                    </div>
                    
                    <div x-show="aiLoading" class="flex flex-col items-center justify-center py-12 space-y-4">
                        <div class="w-8 h-8 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
                        <p class="text-[10px] font-bold text-indigo-400 animate-pulse">解析中...</p>
                    </div>

                    <div x-show="aiAnalysis && !aiLoading" class="space-y-6">
                         <div class="space-y-2">
                             <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest">状況分析</h4>
                             <div class="text-[11px] text-gray-600 bg-gray-50 p-4 rounded-xl border border-gray-100 leading-relaxed" x-text="aiAnalysis.columns?.left?.content"></div>
                         </div>
                         <div class="space-y-2">
                             <div class="flex items-center justify-between">
                                 <h4 class="text-[10px] font-black text-indigo-500 uppercase tracking-widest">返信案</h4>
                                 <button @click="applyAiDraft()" class="text-indigo-600 bg-indigo-50 px-2 py-1 rounded text-[10px] font-bold hover:bg-indigo-600 hover:text-white transition-all">反映</button>
                             </div>
                             <div class="text-[11px] text-gray-800 bg-indigo-50/30 p-4 rounded-xl border border-indigo-100 leading-relaxed font-medium" x-text="aiAnalysis.columns?.center?.body"></div>
                         </div>
                         <div class="space-y-2">
                             <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest">アドバイス</h4>
                             <div class="text-[11px] text-gray-600 bg-gray-50 p-4 rounded-xl border border-gray-100 leading-relaxed" x-text="aiAnalysis.columns?.right?.content"></div>
                         </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    {{-- 各種モーダル等 --}}
    <template x-if="defaultPromptModalOpen">
        <div class="fixed inset-0 z-[1200] flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="defaultPromptModalOpen = false">
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg p-8 animate-in zoom-in duration-200">
                <h3 class="text-lg font-bold mb-2 text-gray-900">デフォルト返信プロンプト設定</h3>
                <textarea x-model="editingDefaultPrompt" rows="6" class="w-full text-sm border border-gray-200 bg-gray-50 rounded-xl p-4 focus:ring-2 focus:ring-indigo-200 outline-none resize-y"></textarea>
                <div class="flex gap-3 mt-5">
                    <button @click="defaultPromptModalOpen = false" class="flex-1 py-3 text-sm font-semibold text-gray-500 hover:bg-gray-50 rounded-xl border border-gray-100 transition-all">キャンセル</button>
                    <button @click="saveDefaultPrompt()" class="flex-[2] bg-indigo-600 text-white py-3 rounded-xl font-bold text-sm shadow-lg hover:bg-indigo-700 transition-all">保存する</button>
                </div>
            </div>
        </div>
    </template>

    <template x-if="customerGroupModalOpen"><div class="fixed inset-0 z-[110] flex items-center justify-center bg-black/50 backdrop-blur-md p-4" @click.self="customerGroupModalOpen = false"><div class="bg-white rounded-[3rem] shadow-2xl w-full max-w-md p-12 text-center animate-in zoom-in duration-300"><h3 class="text-2xl font-black mb-8 tracking-tighter uppercase text-gray-900">新しいフォルダ</h3><input type="text" x-model="newGroupName" placeholder="フォルダ名を入力..." class="w-full text-lg font-black border-gray-200 bg-gray-50 border-2 rounded-2xl px-8 py-6 outline-none mb-10 text-center focus:ring-4 focus:ring-blue-400 shadow-inner"><div class="flex gap-4"><button @click="customerGroupModalOpen = false" class="flex-1 py-5 text-sm font-black text-gray-400 hover:bg-gray-50 rounded-2xl transition-all">キャンセル</button><button @click="addCustomerGroup()" class="flex-[2] bg-blue-600 text-white py-5 rounded-2xl font-black text-base shadow-xl hover:bg-blue-700 transition-all">フォルダを作成</button></div></div></div></template>
    <template x-if="customerModalOpen"><div class="fixed inset-0 z-[110] flex items-center justify-center bg-black/50 backdrop-blur-md p-4" @click.self="customerModalOpen = false"><div class="bg-white rounded-[3rem] shadow-2xl w-full max-w-md p-12 animate-in zoom-in duration-300 shadow-indigo-100"><h3 class="text-2xl font-black mb-8 text-center tracking-tighter uppercase text-gray-900">顧客を追加</h3><div class="space-y-5 mb-10"><input type="text" x-model="newCustomerName" placeholder="氏名 / 会社名" class="w-full text-base font-black border-gray-200 border-2 bg-gray-50 rounded-2xl px-8 py-5 focus:bg-white focus:ring-4 focus:ring-blue-400 outline-none shadow-inner"><input type="email" x-model="newCustomerEmail" placeholder="メールアドレス (任意)" class="w-full text-base font-black border-gray-200 border-2 bg-gray-50 rounded-2xl px-8 py-5 focus:bg-white focus:ring-4 focus:ring-blue-400 outline-none shadow-inner"></div><div class="flex gap-4"><button @click="customerModalOpen = false" class="flex-1 py-5 text-sm font-black text-gray-400 hover:bg-gray-50 rounded-2xl transition-all">キャンセル</button><button @click="addCustomer()" class="flex-[2] bg-blue-600 text-white py-5 rounded-2xl font-black text-base shadow-xl hover:bg-blue-700 transition-all shadow-blue-100">登録する</button></div></div></div></template>

    {{-- マージ先選択モーダル --}}
    <template x-if="mergeDialogOpen">
        <div class="fixed inset-0 z-[1200] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4" @click.self="mergeDialogOpen = false">
            <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-2xl p-8 animate-in zoom-in duration-200">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-black uppercase tracking-tighter text-gray-900">マージのベースを選択</h3>
                    <button @click="mergeDialogOpen = false" class="text-gray-400 hover:text-red-500 transition-all"><i class="fas fa-times fa-lg"></i></button>
                </div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-6">ベースとなるスレッド（件名や顧客情報が維持されるもの）を1つ選んでください。</p>
                
                <div class="max-h-96 overflow-y-auto border border-gray-100 rounded-2xl mb-8 custom-scrollbar bg-gray-50/30">
                    <template x-for="t in mergeCandidates" :key="t.id">
                        <label class="flex items-center gap-4 p-5 border-b border-gray-100 last:border-0 hover:bg-white cursor-pointer transition-all group">
                            <input type="radio" :value="t.id" x-model="mergeBaseId" class="w-5 h-5 text-blue-600 focus:ring-blue-500 border-gray-300">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-3 mb-1">
                                    <span class="text-[11px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded uppercase" x-text="t.latest_email?.from_label"></span>
                                    <span class="text-[10px] font-bold text-gray-400" x-text="t.last_email_at"></span>
                                </div>
                                <div class="text-sm font-bold text-gray-700 truncate group-hover:text-gray-900" x-text="t.subject"></div>
                            </div>
                        </label>
                    </template>
                </div>

                <div class="flex gap-4">
                    <button @click="mergeDialogOpen = false" class="flex-1 py-4 text-sm font-black text-gray-400 hover:bg-gray-50 rounded-2xl transition-all">キャンセル</button>
                    <button @click="confirmMerge()" class="flex-[2] bg-blue-600 text-white py-4 rounded-2xl font-black text-sm shadow-xl hover:bg-blue-700 transition-all shadow-blue-100">このメールをベースにしてマージ実行</button>
                </div>
            </div>
        </div>
    </template>

</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
function newSearchParams(obj) { return new URLSearchParams(obj); }

function emailApp() {
    return {
        // UI Layout States
        sidebarVisible: true, isComposing: false,
        navPanelOpen: false, tagPanelOpen: false, customerModalOpen: false, customerGroupModalOpen: false, fetching: false, 
        tagEditorOpen: false, assignDropdownOpen: false, loadingThread: false, 
        fullThreadMode: false, isListMaximizing: false, openGroupIds: [], expandedMemoMode: false,
        threadMemos: [], newMemoContent: '', threadComments: [], newCommentContent: '',
        threadsLoading: true,

        // Resizing State
        sidebarWidth: parseInt(localStorage.getItem('sidebarWidth')) || 64,
        navPanelWidth: parseInt(localStorage.getItem('navPanelWidth')) || 280,
        threadWidth: parseInt(localStorage.getItem('threadWidth')) || 350,
        overlayHistoryWidth: parseInt(localStorage.getItem('overlayHistoryWidth')) || 30, // %
        overlayAiWidth: parseInt(localStorage.getItem('overlayAiWidth')) || 400, // px
        resizingType: null,

        // Selection & Long Press
        selectionMode: false, selectedThreadIds: [], longPressTimer: null, isLongPressing: false,

        // AI & Reply
        replyOverlayOpen: false, replyAiPanelOpen: false, composeMode: false, aiDrawerOpen: false,
        mergeDialogOpen: false, mergeBaseId: null, mergeCandidates: [],
        replyBody: '', replyToEmailId: null, replyToAddress: '', replyCc: '', replyBcc: '',
        aiUserPrompt: '', aiAnalysis: null, aiLoading: false, sendingReply: false, selectedFiles: [],
        aiScrapeUrl: '', defaultAiPrompt: '', editingDefaultPrompt: '', defaultPromptModalOpen: false,
        quickCustomerFormOpen: false, quickCustomerName: '', quickCustomerEmailVal: '',

        // Core Data & Filter
        leftTab: 'inbox', searchQuery: '', customerSearchQuery: '', activeCustomerId: null, activeGroupId: null, activeTags: [], 
        sortKey: 'last_email_at', sortOrder: 'desc',
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
                const tid = newSearchParams(window.location.search).get('thread');
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

        async openAiPanel(thread, email = null) {
            this.sidebarVisible = false;
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
            this.aiUserPrompt = '';
            this.aiAnalysis = null;
            this.aiScrapeUrl = '';
            this.aiDrawerOpen = true;
            this.replyAiPanelOpen = true;
            this.composeMode = true;
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

            // Customer Ordering
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

            // Email dropping onto customers
            document.querySelectorAll('.drop-target-customer').forEach(el => {
                const cid = el.dataset.cid;
                this.sortableInstances[`drop-cid-${cid}`] = new Sortable(el, {
                    group: { name: 'assignTarget', put: ['emailAssignment'] },
                    onAdd: async (evt) => {
                        const tid = evt.item.dataset.threadId;
                        await this.assignCustomer(cid, tid);
                        evt.item.remove();
                    }
                });
            });
        },

        // Thread Navigation
        get currentNavList() { return this.threads; },
        get currentNavIndex() { return this.currentNavList.findIndex(item => item.id === this.selectedThreadId); },
        get hasPrevThread() { return this.currentNavIndex > 0; },
        get hasNextThread() { return this.currentNavIndex >= 0 && this.currentNavIndex < this.currentNavList.length - 1; },
        async loadPrevThread() { if (this.hasPrevThread) await this.loadThread(this.currentNavList[this.currentNavIndex - 1].id); },
        async loadNextThread() { if (this.hasNextThread) await this.loadThread(this.currentNavList[this.currentNavIndex + 1].id); },

        // Selection / Long Press
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

        // Bulk Actions
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
        async bulkMoveToHold() { for (const id of this.selectedThreadIds) await this.updateThreadStatus({id}, 'hold'); this.cancelSelection(); },
        async bulkMoveToComplete() { for (const id of this.selectedThreadIds) await this.updateThreadStatus({id}, 'completed'); this.cancelSelection(); },
        async bulkMoveToInbox() { for (const id of this.selectedThreadIds) await this.updateThreadStatus({id}, 'inbox'); this.cancelSelection(); },
        async bulkDelete() { if(confirm('本当に削除しますか？')) { for (const id of this.selectedThreadIds) await fetch(`/threads/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } }); await this.loadThreads(); this.cancelSelection(); } },

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
        startResizeOverlay(type, e) {
            this.resizingType = type;
            const startX = e.clientX;
            const containerWidth = this.$refs.workspaceContainer.offsetWidth;
            const startHistoryPercent = this.overlayHistoryWidth;
            const startAiWidth = this.overlayAiWidth;
            const onMove = (me) => {
                if (this.resizingType === 'history') {
                    const deltaX = me.clientX - startX;
                    const deltaPercent = (deltaX / containerWidth) * 100;
                    this.overlayHistoryWidth = Math.max(15, Math.min(50, startHistoryPercent + deltaPercent));
                } else if (this.resizingType === 'ai') {
                    const deltaX = startX - me.clientX;
                    this.overlayAiWidth = Math.max(200, Math.min(800, startAiWidth + deltaX));
                }
            };
            const onUp = () => {
                this.resizingType = null;
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                localStorage.setItem('overlayHistoryWidth', this.overlayHistoryWidth);
                localStorage.setItem('overlayAiWidth', this.overlayAiWidth);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        // API Loading
        async loadThreads() {
            this.threadsLoading = true;
            const params = newSearchParams({ sort_key: this.sortKey, sort_order: this.sortOrder });
            if (this.searchQuery) params.set('q', this.searchQuery);
            if (this.activeCustomerId) params.set('customer_id', this.activeCustomerId);
            if (this.activeGroupId) params.set('group_id', this.activeGroupId);
            this.activeTags.forEach(t => params.append('tags[]', t));
            const statusMap = { inbox: 'inbox', hold: 'hold', completed: 'completed', pending: 'pending' };
            if (statusMap[this.leftTab]) params.set('status', statusMap[this.leftTab]);
            try { 
                const res = await fetch('/emails/search?' + params.toString()); 
                this.threads = await res.json(); 
            } catch(e) { this.threads = []; }
            this.threadsLoading = false;
        },

        toggleSort(key) {
            if (this.sortKey === key) {
                this.sortOrder = (this.sortOrder === 'desc' ? 'asc' : 'desc');
            } else {
                this.sortKey = key;
                this.sortOrder = 'asc';
            }
            this.loadThreads();
        },

        async bulkMerge() {
            if (this.selectedThreadIds.length < 2) {
                alert('マージするには2つ以上のスレッドを選択してください');
                return;
            }
            // マージ候補の詳細を取得（件名、差出人など）
            this.mergeCandidates = this.threads.filter(t => this.selectedThreadIds.includes(t.id));
            this.mergeBaseId = this.selectedThreadIds[0];
            this.mergeDialogOpen = true;
        },

        async confirmMerge() {
            if (!this.mergeBaseId) return;
            const targetId = this.mergeBaseId;
            const sources = this.selectedThreadIds.filter(id => id !== targetId);
            
            if (!confirm(`選択した ${sources.length} 件のスレッドをベーススレッドにマージしますか？`)) return;

            try {
                for (const sourceId of sources) {
                    const res = await fetch(`/threads/${targetId}/merge`, {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content 
                        },
                        body: JSON.stringify({ merge_thread_id: sourceId })
                    });
                    if (!res.ok) throw new Error(`スレッド ${sourceId} のマージに失敗しました`);
                }
                alert('マージが完了しました');
                this.mergeDialogOpen = false;
                this.cancelSelection();
                await this.loadThreads();
            } catch (e) {
                alert(e.message);
            }
        },

        async unmergeThread(mergeId) {
            if (!confirm('このマージを解除しますか？')) return;
            try {
                const res = await fetch(`/thread-merges/${mergeId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                if (res.ok) {
                    alert('マージを解除しました');
                    await this.loadThread(this.selectedThreadId);
                } else {
                    throw new Error('解除に失敗しました');
                }
            } catch (e) {
                alert(e.message);
            }
        },

        async loadThread(id) {
            this.selectedThreadId = id; this.loadingThread = true; this.composeMode = false; this.replyAiPanelOpen = false;
            try {
                const res = await fetch(`/threads/${id}`); const data = await res.json();
                this.selectedThread = data.thread;
                this.threadEmails = data.emails.sort((a,b) => b.id - a.id);
                this.expandedEmailIds = [this.threadEmails[0]?.id].filter(Boolean);
                this.threadMerges = data.merges || [];
            } catch(e) {}
            this.loadingThread = false;
        },

        // AI & Reply logic
        openReplyOverlay(email) {
            if (email) {
                this.replyToEmailId = email.id;
                this.replyToAddress = email.from_address;
            }
            this.replyBody = ''; this.replyCc = ''; this.replyBcc = '';
            this.aiAnalysis = null; this.selectedFiles = [];
            this.aiUserPrompt = this.defaultAiPrompt;
            this.replyAiPanelOpen = false;
            this.composeMode = true;
            this.$nextTick(() => { document.querySelector('textarea[x-model="replyBody"]')?.focus(); });
        },
        closeReplyOverlay() { this.composeMode = false; this.replyAiPanelOpen = false; },
        async askAiForReply() {
            if (!this.replyToEmailId) return;
            this.aiLoading = true; this.aiAnalysis = null;
            try {
                const res = await fetch(`/emails/${this.replyToEmailId}/ai`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ prompt: this.aiUserPrompt })
                });
                const data = await res.json();
                if (res.ok) this.aiAnalysis = data; else alert(data.error || 'AI解析に失敗');
            } catch(e) { alert('AI生成に失敗'); }
            this.aiLoading = false;
        },
        applyAiDraft() {
            if (this.aiAnalysis?.columns?.center?.body) {
                this.replyBody = this.aiAnalysis.columns.center.body;
                this.replyAiPanelOpen = false;
            }
        },
        async submitReply() {
            if (!this.replyBody) return;
            this.sendingReply = true;
            const fd = new FormData();
            fd.append('body', this.replyBody);
            fd.append('to', this.replyToAddress);
            fd.append('cc', this.replyCc);
            fd.append('bcc', this.replyBcc);
            fd.append('created_by', '米住 直親'); // fallback if auth not active in session
            this.selectedFiles.forEach(f => fd.append('attachments[]', f));
            try {
                // if composeMode is true and replyToEmailId is null, use a different endpoint or handle accordingly
                const url = (this.composeMode && !this.replyToEmailId) ? '/emails/compose' : `/emails/${this.replyToEmailId}/reply`;
                const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: fd });
                if (res.ok) {
                    this.closeReplyOverlay();
                    alert('承認依頼を送信しました');
                    await this.loadPending();
                } else {
                    alert('送信に失敗しました');
                }
            } catch(e) { alert('通信エラーが発生しました'); }
            this.sendingReply = false;
        },

        // Helpers
        async setLeftTab(tab) { this.leftTab = tab; await this.loadThreads(); },
        openCompose() { this.composeMode = true; this.selectedThreadId = null; this.selectedThread = null; this.replyBody = ''; },
        async updateThreadStatus(t, k) {
            await fetch(`/threads/${t.id}/status`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ status: k }) });
            await this.loadThreads(); this.selectedThread = null;
        },
        async moveToHold(t) { await this.updateThreadStatus(t, 'hold'); },
        async markThreadComplete(t) { await this.updateThreadStatus(t, 'completed'); },
        async markThreadIgnored(t) { await this.updateThreadStatus(t, 'ignored'); },
        async deleteThread(t) {
            if (!confirm('このスレッドを削除しますか？')) return;
            try {
                const res = await fetch(`/threads/${t.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                if (res.ok) {
                    alert('削除しました');
                    this.selectedThreadId = null;
                    this.selectedThread = null;
                    await this.loadThreads();
                } else {
                    throw new Error('削除に失敗しました');
                }
            } catch (e) {
                alert(e.message);
            }
        },
        async assignCustomer(cid, tid = null) { 
            const threadId = tid || this.selectedThreadId; if (!threadId) return;
            await fetch(`/threads/${threadId}/assign-customer`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ customer_id: cid }) }); 
            if (this.selectedThreadId === threadId) await this.loadThread(threadId);
            await this.loadThreads();
        },
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
        get filteredUnassignedCustomers() { const q = (this.customerSearchQuery||'').toLowerCase(); return (this.customerData || []).filter(c => !this.isCustomerInAnyGroup(c.id) && c.name.toLowerCase().includes(q)); },
        isCustomerInAnyGroup(cid) { const walk = (groups) => { for (let g of groups) { if ((g.customers||[]).some(c => c.id === cid)) return true; if (g.children && walk(g.children)) return true; } return false; }; if(!this.customerGroups) return false; return walk(this.customerGroups); },
        get filteredMasterTags() { return (this.masterTags || []).filter(mt => !this.reservedWords.includes(mt.name)); },
        get filteredCustomers() { const q = (this.customerSearchQuery||'').toLowerCase(); return (this.customerData || []).filter(c => c.id !== 'none' && c.name.toLowerCase().includes(q)); },
        onSearchInput() { clearTimeout(this.searchDebounce); this.searchDebounce = setTimeout(() => this.loadThreads(), 300); },
        toggleGroupFilter(gid) { this.activeGroupId = (this.activeGroupId === gid ? null : gid); this.loadThreads(); },
        toggleCustomerFilter(id, name) { this.activeCustomerId = (this.activeCustomerId === id ? null : id); this.loadThreads(); },
        toggleTagFilter(tag) { const idx = this.activeTags.indexOf(tag); if (idx === -1) this.activeTags.push(tag); else this.activeTags.splice(idx, 1); this.loadThreads(); },
        toggleEmail(id) { const idx = this.expandedEmailIds.indexOf(id); if (idx === -1) this.expandedEmailIds.push(id); else this.expandedEmailIds.splice(idx, 1); },
        openCreateGroup(p) { this.newGroupName = ''; this.customerGroupModalOpen = true; },
        async addCustomerGroup() { await fetch('/customer-groups', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ name: this.newGroupName }) }); this.customerGroupModalOpen = false; await this.loadCustomerGroups(); },
        async addCustomer() { await fetch('/customers', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ name: this.newCustomerName, email: this.newCustomerEmail }) }); this.customerModalOpen = false; await this.loadCustomerData(); },
        handleFileSelect(e) { this.selectedFiles = Array.from(e.target.files); },
        removeSelectedFile(i) { this.selectedFiles.splice(i, 1); }
    };
}
</script>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #E5E7EB; border-radius: 10px; }
[x-cloak] { display: none !important; }
.subject-clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.email-body-text { font-size: 14px; line-height: 1.6; color: #374151; }
.sortable-ghost { opacity: 0.4; background: #EEF2FF !important; border: 1px dashed #3B82F6; }
.c-drag-handle { cursor: grab; }
</style>
@endsection
