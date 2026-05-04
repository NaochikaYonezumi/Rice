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
</style>
@endsection

@section('content')
<script>
    document.body.classList.add('attachments-page');
    window.addEventListener('beforeunload', function() {
        document.body.classList.remove('attachments-page');
    });
</script>

<div class="att-root flex bg-gray-50" x-data="attachmentApp()" x-init="init()" x-cloak>

    {{-- ===== メイン (顧客リストは廃止し、ツールバーのドロップダウンで絞込む) ===== --}}
    <main class="flex-1 min-w-0"
          style="width:100%;height:100%;display:flex;flex-direction:column;min-height:0;overflow:hidden;">

        {{-- ヘッダー (1行コンパクト: タイトル + 件数 + フィルタバッジ + 更新) --}}
        <div class="px-5 py-2 bg-white border-b border-gray-200 flex items-center justify-between gap-3"
             style="flex-shrink:0;">
            <div class="min-w-0 flex-1 inline-flex items-center gap-2">
                <i class="fas fa-paperclip text-blue-500 text-xs shrink-0"></i>
                <h1 class="text-sm font-extrabold text-gray-900 truncate"
                    x-text="activeCustomerId ? (customerData.find(c=>c.id===activeCustomerId)?.name || '未設定') : 'すべての添付ファイル'"></h1>
                <span class="text-[11px] font-bold text-gray-500 shrink-0" x-text="'(' + total + ')'"></span>
                <template x-if="hasActiveFilter">
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold shrink-0"
                          style="background-color:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;">
                        <i class="fas fa-filter"></i> フィルタ適用中
                    </span>
                </template>
            </div>
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

                {{-- 顧客フィルタ (ドロップダウン) --}}
                <div class="relative shrink-0" @click.outside="customerDropdownOpen = false">
                    <button type="button" @click="customerDropdownOpen = !customerDropdownOpen"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold transition-colors"
                        :style="activeCustomerId
                            ? 'background-color:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;'
                            : 'background-color:#f9fafb;color:#4b5563;border:1px solid #e5e7eb;'">
                        <i class="fas fa-user"></i>
                        <span class="max-w-[160px] truncate"
                              x-text="activeCustomerId ? (customerData.find(c=>c.id===activeCustomerId)?.name || '未設定') : '顧客: すべて'"></span>
                        <i class="fas fa-chevron-down text-[9px]"></i>
                    </button>
                    <div x-show="customerDropdownOpen" x-cloak x-transition.duration.150ms
                         class="absolute right-0 mt-1 w-72 rounded-xl shadow-xl z-30 overflow-hidden flex flex-col"
                         style="background-color:#ffffff;border:1px solid #e5e7eb;max-height:380px;">
                        <div class="shrink-0 p-2 border-b border-gray-100">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                <input type="text" x-model="customerSearchQuery" placeholder="顧客を絞り込み..."
                                       class="w-full pl-8 pr-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-300">
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto custom-scrollbar p-1 min-h-0">
                            <button type="button"
                                @click="activeCustomerId = null; customerDropdownOpen = false; load()"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold transition-colors flex items-center justify-between"
                                :style="activeCustomerId === null
                                    ? 'background-color:#dbeafe;color:#1e40af;'
                                    : 'background-color:transparent;color:#4b5563;'"
                                onmouseover="if(this.dataset.active!=='1')this.style.backgroundColor='#f3f4f6';"
                                onmouseout="if(this.dataset.active!=='1')this.style.backgroundColor='transparent';"
                                :data-active="activeCustomerId === null ? '1' : '0'">
                                <span class="inline-flex items-center gap-2"><i class="fas fa-globe-asia text-[10px]"></i> すべての顧客</span>
                                <span class="text-[10px] opacity-70" x-text="totalAcrossCustomers"></span>
                            </button>
                            <template x-if="customersLoading">
                                <p class="text-center text-[11px] text-gray-400 py-3">読み込み中...</p>
                            </template>
                            <template x-if="!customersLoading && filteredCustomers.length === 0">
                                <p class="text-center text-[11px] text-gray-400 py-3">顧客がありません</p>
                            </template>
                            <template x-for="c in filteredCustomers" :key="c.id">
                                <button type="button"
                                    @click="activeCustomerId = c.id; customerDropdownOpen = false; load()"
                                    class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold transition-colors flex items-center justify-between"
                                    :style="activeCustomerId === c.id
                                        ? 'background-color:#dbeafe;color:#1e40af;'
                                        : 'background-color:transparent;color:#4b5563;'"
                                    onmouseover="if(this.dataset.active!=='1')this.style.backgroundColor='#f3f4f6';"
                                    onmouseout="if(this.dataset.active!=='1')this.style.backgroundColor='transparent';"
                                    :data-active="activeCustomerId === c.id ? '1' : '0'">
                                    <span class="truncate pr-2" x-text="c.name"></span>
                                    <span class="text-[10px] opacity-70 shrink-0" x-text="c.emails?.length || 0"></span>
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
                    <table class="w-full text-sm">
                        <thead style="background-color:#f9fafb;">
                            <tr class="border-b border-gray-100">
                                <th class="px-3 py-2.5 w-14"></th>
                                <th class="text-left px-3 py-2.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider">ファイル名</th>
                                <th class="text-left px-3 py-2.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider">件名 / 相手</th>
                                <th class="text-left px-3 py-2.5 text-[10px] font-bold text-gray-500 uppercase tracking-wider w-[160px]">日時 / サイズ</th>
                                <th class="px-3 py-2.5 w-16"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="att in attachments" :key="att.id">
                                <tr class="transition-colors"
                                    onmouseover="this.style.backgroundColor='#f9fafb';"
                                    onmouseout="this.style.backgroundColor='';">
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
                                    <td class="px-3 py-2.5 max-w-0">
                                        <div class="flex items-center gap-2 mb-1 min-w-0">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold shrink-0"
                                                  :style="att.direction === 'sent'
                                                    ? 'background-color:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;'
                                                    : 'background-color:#d1fae5;color:#047857;border:1px solid #a7f3d0;'">
                                                <i class="fas" :class="att.direction === 'sent' ? 'fa-paper-plane' : 'fa-inbox'"></i>
                                                <span x-text="att.direction === 'sent' ? '送信' : '受信'"></span>
                                            </span>
                                            <button @click="openPreview(att)"
                                                    class="text-xs font-bold text-gray-800 hover:text-blue-600 truncate text-left transition-colors min-w-0"
                                                    x-text="att.filename" :title="att.filename"></button>
                                        </div>
                                        <span class="text-[10px] text-gray-400 font-semibold" x-text="mimeLabel(att.mime_type)"></span>
                                    </td>
                                    <td class="px-3 py-2.5 max-w-0">
                                        <template x-if="att.thread_id">
                                            <a :href="'/?thread=' + att.thread_id"
                                               class="text-[12px] text-blue-600 hover:underline font-bold block mb-0.5 leading-snug"
                                               style="word-break:break-word;overflow-wrap:anywhere;white-space:normal;"
                                               x-text="att.email_subject" :title="att.email_subject"></a>
                                        </template>
                                        <p class="text-[10px] text-gray-500 truncate">
                                            <template x-if="att.direction === 'sent'">
                                                <span><span class="text-gray-400">To:</span> <span x-text="att.to_address"></span></span>
                                            </template>
                                            <template x-if="att.direction !== 'sent'">
                                                <span><span class="text-gray-400">From:</span> <span x-text="att.from_label"></span></span>
                                            </template>
                                        </p>
                                    </td>
                                    <td class="px-3 py-2.5 whitespace-nowrap">
                                        <p class="text-[11px] text-gray-700 font-semibold" x-text="att.received_at"></p>
                                        <p class="text-[10px] text-gray-400 font-semibold" x-text="att.size"></p>
                                    </td>
                                    <td class="px-3 py-2.5 text-right">
                                        <a :href="att.url" :download="att.filename"
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg transition-colors"
                                            style="background-color:#f3f4f6;color:#6b7280;"
                                            onmouseover="this.style.backgroundColor='#2563eb';this.style.color='#ffffff';"
                                            onmouseout="this.style.backgroundColor='#f3f4f6';this.style.color='#6b7280';"
                                            title="ダウンロード">
                                            <i class="fas fa-download text-xs"></i>
                                        </a>
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
        searchDebounce: null,
        previewOpen: false,
        previewFile: null,
        customerDropdownOpen: false,
        // ページング
        page: 1,
        perPage: 30,
        totalPages: 1,

        typeTabs: [
            { key: '',         label: 'すべて',  icon: 'fa-layer-group' },
            { key: 'image',    label: '画像',    icon: 'fa-image' },
            { key: 'document', label: '文書',    icon: 'fa-file-lines' },
            { key: 'other',    label: 'その他',  icon: 'fa-box' },
        ],

        async init() {
            await Promise.all([this.load(), this.loadCustomers()]);
        },

        get hasActiveFilter() {
            return !!(this.searchQuery || this.typeFilter || this.direction || this.dateFrom || this.dateTo || this.activeCustomerId);
        },

        get totalAcrossCustomers() {
            return this.customerData.reduce((sum, c) => sum + (c.emails?.length || 0), 0);
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
                if (this.activeCustomerId) params.set('customer_id', this.activeCustomerId);
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
            return this.customerData.filter(c => (c.name || '').toLowerCase().includes(q));
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
            this.activeCustomerId = null;
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
