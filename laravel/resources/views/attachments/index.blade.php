@extends('layouts.app')
@section('title', '添付ファイル')

@section('content')
<div class="flex h-full bg-gray-50" x-data="attachmentApp()" x-init="init()" x-cloak>

    {{-- 左パネル: 顧客一覧 --}}
    <div class="w-64 shrink-0 border-r border-gray-200 bg-white flex flex-col overflow-hidden shadow-sm z-10">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-white">
            <h2 class="text-xs font-black text-gray-900 uppercase tracking-widest">顧客リスト</h2>
        </div>

        <div class="flex-1 overflow-y-auto py-2">
            <div class="space-y-0.5 px-2">
                {{-- すべてのファイル --}}
                <button @click="activeCustomerId = null; load()"
                    :class="activeCustomerId === null ? 'bg-blue-600 text-white shadow-md' : 'text-gray-600 hover:bg-blue-50'"
                    class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-between mb-4 group">
                    <span>すべてのファイル</span>
                    <span class="text-[10px] opacity-60 group-hover:opacity-100">ALL</span>
                </button>

                <div class="flex items-center justify-between px-2 mb-2">
                    <span class="text-[9px] font-bold text-gray-300 uppercase tracking-widest">顧客別</span>
                </div>

                <template x-if="customersLoading">
                    <div class="p-4 text-center text-xs text-gray-400 animate-pulse">読み込み中...</div>
                </template>

                <template x-if="!customersLoading">
                    <div class="space-y-0.5">
                        <template x-for="c in filteredCustomers" :key="c.id">
                            <button @click="activeCustomerId = c.id; load()"
                                :class="activeCustomerId === c.id ? 'bg-blue-600 text-white shadow-md' : 'text-gray-600 hover:bg-blue-50 hover:text-blue-700'"
                                class="w-full text-left px-3 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-between group">
                                <span class="truncate pr-2" x-text="c.name"></span>
                                <span class="text-[10px] opacity-60" x-text="c.emails?.length || 0"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- 顧客検索 --}}
        <div class="px-3 py-3 border-t border-gray-100 bg-white">
            <div class="relative">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="customerSearchQuery" placeholder="顧客を絞り込み..." 
                    class="w-full pl-8 pr-3 py-1.5 bg-gray-50 border-none rounded-lg text-[10px] focus:ring-2 focus:ring-blue-400 outline-none transition-all">
            </div>
        </div>
    </div>

    {{-- メインエリア --}}
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        {{-- ヘッダー --}}
        <div class="bg-white border-b border-gray-200 px-8 py-4 shrink-0 shadow-sm z-10">
            <div class="flex items-center justify-between mb-4">
                <div class="min-w-0">
                    <h1 class="text-xl font-black text-gray-900 truncate">
                        <span x-text="activeCustomerId ? (customerData.find(c=>c.id===activeCustomerId)?.name || '未設定') : 'すべての添付ファイル'"></span>
                    </h1>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">
                        Total: <span class="text-gray-900" x-text="total"></span> files
                        <template x-if="searchQuery || typeFilter || dateFrom || dateTo">
                            <span class="ml-2 text-blue-600">Filtering active</span>
                        </template>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center bg-gray-100 p-0.5 rounded-xl overflow-hidden shadow-inner">
                        <button @click="viewMode = 'list'"
                            :class="viewMode === 'list' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                            class="px-3 py-1.5 rounded-lg transition-all" title="リスト表示">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        </button>
                        <button @click="viewMode = 'grid'"
                            :class="viewMode === 'grid' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                            class="px-3 py-1.5 rounded-lg transition-all" title="グリッド表示">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        </button>
                    </div>
                    <button @click="load()"
                        class="text-xs bg-gray-900 hover:bg-black text-white px-5 py-2 rounded-xl transition-all font-black shadow-lg shadow-gray-200"
                        :class="{ 'opacity-50 cursor-wait': loading }">
                        更新
                    </button>
                </div>
            </div>

            {{-- 検索 + フィルター --}}
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-4 flex-wrap">
                    <div class="relative flex-1 min-w-md">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" x-model="searchQuery" @input="onSearchInput()"
                            placeholder="ファイル名、またはメールの件名で検索..."
                            class="w-full text-xs bg-gray-50 border-none rounded-xl pl-10 pr-4 py-2 focus:ring-2 focus:ring-blue-400 transition-all font-medium">
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <div class="flex items-center bg-gray-50 border border-gray-100 rounded-xl px-3 py-1 gap-2">
                            <span class="text-[9px] font-black text-gray-400 uppercase tracking-tighter">期間</span>
                            <input type="date" x-model="dateFrom" @change="load()" class="bg-transparent border-none text-[10px] p-0 focus:ring-0 font-bold text-gray-700">
                            <span class="text-gray-300">~</span>
                            <input type="date" x-model="dateTo" @change="load()" class="bg-transparent border-none text-[10px] p-0 focus:ring-0 font-bold text-gray-700">
                        </div>
                        <button @click="sortOrder = (sortOrder === 'desc' ? 'asc' : 'desc'); load()" 
                            class="bg-gray-50 border border-gray-100 px-3 py-1.5 rounded-xl text-[10px] font-black text-gray-600 hover:bg-white transition-all flex items-center gap-1.5 shadow-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                            <span x-text="sortOrder === 'desc' ? '新しい順' : '古い順'"></span>
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    {{-- 受信/送信タブ --}}
                    <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-xl shadow-inner">
                        <button @click="setDirection('')"
                            :class="direction === '' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700'"
                            class="text-xs px-3 py-1.5 rounded-lg transition-all font-bold inline-flex items-center gap-1.5">
                            <i class="fas fa-globe"></i> すべて
                        </button>
                        <button @click="setDirection('received')"
                            :class="direction === 'received' ? 'bg-white shadow text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                            class="text-xs px-3 py-1.5 rounded-lg transition-all font-bold inline-flex items-center gap-1.5">
                            <i class="fas fa-inbox"></i> 受信
                        </button>
                        <button @click="setDirection('sent')"
                            :class="direction === 'sent' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                            class="text-xs px-3 py-1.5 rounded-lg transition-all font-bold inline-flex items-center gap-1.5">
                            <i class="fas fa-paper-plane"></i> 送信
                        </button>
                    </div>
                    {{-- 種別フィルタ --}}
                    <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-xl shadow-inner">
                        <button @click="setTypeFilter('')"
                            :class="typeFilter === '' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                            class="text-[10px] px-3 py-1.5 rounded-lg transition-all font-bold">種別: すべて</button>
                        <button @click="setTypeFilter('image')"
                            :class="typeFilter === 'image' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                            class="text-[10px] px-3 py-1.5 rounded-lg transition-all font-bold">🖼 画像</button>
                        <button @click="setTypeFilter('document')"
                            :class="typeFilter === 'document' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                            class="text-[10px] px-3 py-1.5 rounded-lg transition-all font-bold">📄 文書</button>
                        <button @click="setTypeFilter('other')"
                            :class="typeFilter === 'other' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                            class="text-[10px] px-3 py-1.5 rounded-lg transition-all font-bold">📦 その他</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- コンテンツ --}}
        <div class="flex-1 overflow-y-auto px-8 py-8">
            {{-- ローディング --}}
            <template x-if="loading">
                <div class="flex items-center justify-center py-40 text-gray-400 animate-pulse font-black text-sm uppercase tracking-widest">
                    Loading Data...
                </div>
            </template>

            {{-- 空 --}}
            <template x-if="!loading && attachments.length === 0">
                <div class="flex flex-col items-center justify-center py-40 text-gray-400 bg-white rounded-[2.5rem] border border-gray-100 shadow-sm">
                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mb-6 text-gray-200">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    </div>
                    <p class="text-xl font-black text-gray-400">No attachments found</p>
                    <p class="text-xs mt-2 font-bold opacity-60">
                        <span x-text="searchQuery || typeFilter || dateFrom || dateTo ? 'Try adjusting your filters' : 'Files from emails will appear here'"></span>
                    </p>
                </div>
            </template>

            {{-- グリッド表示 --}}
            <template x-if="!loading && attachments.length > 0 && viewMode === 'grid'">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-8">
                    <template x-for="att in attachments" :key="att.id">
                        <div class="group bg-white rounded-[2rem] border border-gray-100 overflow-hidden shadow-sm hover:shadow-2xl hover:border-blue-200 transition-all cursor-pointer"
                            @click="openPreview(att)">
                            <div class="aspect-square bg-gray-50/50 flex items-center justify-center overflow-hidden relative">
                                <template x-if="att.is_image">
                                    <img :src="att.url" :alt="att.filename" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                                </template>
                                <template x-if="!att.is_image">
                                    <div class="text-6xl filter drop-shadow-md" x-text="mimeIcon(att.mime_type)"></div>
                                </template>
                                <a :href="att.url" :download="att.filename" @click.stop
                                    class="absolute top-3 right-3 bg-white/95 hover:bg-blue-600 hover:text-white text-gray-800 rounded-full p-2.5 opacity-0 group-hover:opacity-100 transition-all shadow-xl">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                            </div>
                            <div class="p-5">
                                <div class="flex items-center gap-1 mb-1.5">
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold border"
                                          :class="att.direction === 'sent' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'">
                                        <i class="fas" :class="att.direction === 'sent' ? 'fa-paper-plane' : 'fa-inbox'"></i>
                                        <span x-text="att.direction === 'sent' ? '送信' : '受信'"></span>
                                    </span>
                                </div>
                                <p class="text-xs font-black text-gray-900 truncate mb-1" x-text="att.filename" :title="att.filename"></p>
                                <div class="flex items-center justify-between">
                                    <span class="text-[9px] text-gray-400 font-black uppercase tracking-tighter" x-text="att.size"></span>
                                    <span class="text-[10px] text-blue-600 font-black truncate max-w-[80px]"
                                          x-text="att.direction === 'sent' ? att.to_address : att.from_label"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- リスト表示 --}}
            <template x-if="!loading && attachments.length > 0 && viewMode === 'list'">
                <div class="bg-white rounded-[2.5rem] border border-gray-100 overflow-hidden shadow-sm">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-50 bg-gray-50/50">
                                <th class="px-6 py-5 w-16"></th>
                                <th class="text-left px-4 py-5 text-[10px] font-black text-gray-400 uppercase tracking-widest">Filename</th>
                                <th class="text-left px-4 py-5 text-[10px] font-black text-gray-400 uppercase tracking-widest">Subject / From</th>
                                <th class="text-left px-4 py-5 text-[10px] font-black text-gray-400 uppercase tracking-widest">Date / Size</th>
                                <th class="px-6 py-5 w-20"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="att in attachments" :key="att.id">
                                <tr class="hover:bg-blue-50/20 transition-colors group">
                                    <td class="px-6 py-5 text-center">
                                        <template x-if="att.is_image">
                                            <div class="w-12 h-12 rounded-2xl overflow-hidden border border-gray-100 bg-gray-50 inline-block shadow-sm">
                                                <img :src="att.url" :alt="att.filename" class="w-full h-full object-cover cursor-pointer" @click="openPreview(att)">
                                            </div>
                                        </template>
                                        <template x-if="!att.is_image">
                                            <span class="text-3xl" x-text="mimeIcon(att.mime_type)"></span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-5">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold border shrink-0"
                                                  :class="att.direction === 'sent' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'">
                                                <i class="fas" :class="att.direction === 'sent' ? 'fa-paper-plane' : 'fa-inbox'"></i>
                                                <span x-text="att.direction === 'sent' ? '送信' : '受信'"></span>
                                            </span>
                                            <button @click="openPreview(att)" class="text-sm font-black text-gray-800 hover:text-blue-600 truncate text-left transition-colors" x-text="att.filename"></button>
                                        </div>
                                        <span class="text-[9px] text-gray-400 font-black uppercase tracking-tighter" x-text="mimeLabel(att.mime_type)"></span>
                                    </td>
                                    <td class="px-4 py-5">
                                        <template x-if="att.thread_id">
                                            <a :href="'/?thread=' + att.thread_id" class="text-xs text-blue-600 hover:underline font-black block truncate max-w-[300px] mb-1" x-text="att.email_subject"></a>
                                        </template>
                                        <span class="text-[10px] text-gray-500 font-bold">
                                            <template x-if="att.direction === 'sent'">
                                                <span><span class="text-gray-400">To:</span> <span x-text="att.to_address"></span></span>
                                            </template>
                                            <template x-if="att.direction !== 'sent'">
                                                <span><span class="text-gray-400">From:</span> <span x-text="att.from_label"></span></span>
                                            </template>
                                        </span>
                                    </td>
                                    <td class="px-4 py-5">
                                        <span class="text-xs text-gray-900 block font-bold mb-1" x-text="att.received_at"></span>
                                        <span class="text-[10px] text-gray-400 font-black uppercase tracking-tighter" x-text="att.size"></span>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <a :href="att.url" :download="att.filename" class="inline-flex p-3 bg-gray-50 text-gray-400 hover:bg-blue-600 hover:text-white rounded-2xl transition-all shadow-sm">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </div>

    {{-- プレビューモーダル --}}
    <div x-show="previewOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-md p-4" @click.self="previewOpen = false" style="display: none;">
        <div class="bg-white rounded-[3rem] shadow-2xl max-w-5xl w-full overflow-hidden flex flex-col max-h-[95vh] animate-in zoom-in duration-300">
            <div class="px-10 py-6 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                <div class="min-w-0">
                    <p class="text-lg font-black text-gray-900 truncate" x-text="previewFile?.filename"></p>
                    <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mt-1" x-text="previewFile?.size + ' • ' + mimeLabel(previewFile?.mime_type) + ' • ' + previewFile?.received_at"></p>
                </div>
                <div class="flex items-center gap-4 shrink-0 ml-8">
                    <a :href="previewFile?.url" :download="previewFile?.filename" class="flex items-center gap-2 text-xs bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-2xl transition-all font-black shadow-xl shadow-blue-100 uppercase tracking-widest">
                        Download
                    </a>
                    <button @click="previewOpen = false" class="text-gray-400 hover:text-red-500 transition-colors p-2"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
            </div>
            <div class="flex-1 overflow-auto p-12 flex items-center justify-center bg-white min-h-[400px]">
                <template x-if="previewFile?.is_image"><img :src="previewFile?.url" class="max-w-full max-h-[70vh] rounded-[2rem] shadow-2xl border border-gray-50 transition-all"></template>
                <template x-if="previewFile && !previewFile.is_image">
                    <div class="text-center">
                        <div class="text-9xl mb-8 filter drop-shadow-xl" x-text="mimeIcon(previewFile?.mime_type)"></div>
                        <p class="text-2xl font-black text-gray-900 mb-10 tracking-tight" x-text="previewFile?.filename"></p>
                        <a :href="previewFile?.url" :download="previewFile?.filename" class="inline-flex items-center gap-3 bg-gray-900 hover:bg-black text-white px-12 py-5 rounded-[2rem] transition-all text-sm font-black shadow-2xl hover:-translate-y-1 active:translate-y-0">
                            DOWNLOAD FILE
                        </a>
                    </div>
                </template>
            </div>
            <template x-if="previewFile?.email_subject">
                <div class="px-10 py-6 border-t border-gray-50 bg-gray-50/30 flex items-center gap-4 text-xs font-bold text-gray-500">
                    <div class="w-8 h-8 rounded-full bg-white shadow-sm flex items-center justify-center text-blue-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2v10a2 2 0 002 2z"/></svg></div>
                    <span class="truncate">
                        From: <span class="text-gray-900" x-text="previewFile?.from_label"></span>
                        <span class="text-gray-300 mx-2">|</span>
                        Subject: <a :href="'/?thread=' + previewFile?.thread_id" class="text-blue-600 hover:underline" x-text="previewFile?.email_subject"></a>
                    </span>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function attachmentApp() {
    return {
        loading: false,
        attachments: [],
        total: 0,
        searchQuery: '',
        typeFilter: '',
        direction: '',
        dateFrom: '',
        dateTo: '',
        sortOrder: 'desc',
        activeCustomerId: null,
        customerData: [],
        customersLoading: false,
        customerSearchQuery: '',
        viewMode: 'list',
        searchDebounce: null,
        previewOpen: false,
        previewFile: null,

        async init() {
            await Promise.all([this.load(), this.loadCustomers()]);
        },

        async load() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.searchQuery) params.set('q', this.searchQuery);
                if (this.typeFilter)  params.set('type', this.typeFilter);
                if (this.direction)   params.set('direction', this.direction);
                if (this.dateFrom)    params.set('date_from', this.dateFrom);
                if (this.dateTo)      params.set('date_to', this.dateTo);
                if (this.activeCustomerId) params.set('customer_id', this.activeCustomerId);
                params.set('sort', this.sortOrder);

                const res = await fetch('/attachments?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.attachments = data.attachments ?? [];
                this.total = data.total ?? 0;
            } finally {
                this.loading = false;
            }
        },

        async loadCustomers() {
            this.customersLoading = true;
            try {
                const res = await fetch('/customers/data');
                this.customerData = await res.json();
            } finally {
                this.customersLoading = false;
            }
        },

        get filteredCustomers() {
            if (!this.customerSearchQuery.trim()) return this.customerData;
            const q = this.customerSearchQuery.toLowerCase();
            return this.customerData.filter(c => c.name.toLowerCase().includes(q));
        },

        onSearchInput() {
            clearTimeout(this.searchDebounce);
            this.searchDebounce = setTimeout(() => this.load(), 300);
        },

        setTypeFilter(type) {
            this.typeFilter = type;
            this.load();
        },

        setDirection(dir) {
            this.direction = dir;
            this.load();
        },

        openPreview(att) {
            this.previewFile = att;
            this.previewOpen = true;
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
            if (!mime) return 'Unknown';
            const map = {
                'application/pdf': 'PDF',
                'application/msword': 'Word',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word',
                'application/vnd.ms-excel': 'Excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel',
                'text/plain': 'Text',
            };
            return map[mime] || mime.split('/').pop().toUpperCase();
        },
    };
}
</script>
@endsection
