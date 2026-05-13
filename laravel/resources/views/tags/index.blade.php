@extends('layouts.app')
@section('title', 'タグ・顧客リスト')

@section('content')
<div class="flex h-full" x-data="tagApp()" x-init="init()" x-cloak>

    {{-- 左パネル: タグ / 顧客 一覧 --}}
    <div class="w-72 shrink-0 border-r border-gray-200 bg-white flex flex-col">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">リスト一覧</h2>
                <div class="flex items-center gap-1">
                    <button x-show="leftTab === 'customers'" @click="customerModalOpen = true; loadRagCollections()"
                        class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1.5 rounded-full transition-colors font-medium">
                        + 新規顧客
                    </button>
                    <button @click="reloadData()"
                        class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-1.5 rounded-full transition-colors"
                        :class="{ 'opacity-50': loading }">
                        更新
                    </button>
                </div>
            </div>
            {{-- 切り替えタブ --}}
            <div class="flex p-1 bg-gray-100 rounded-lg">
                <button @click="leftTab = 'customers'; selectedId = null"
                    :class="leftTab === 'customers' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                    class="flex-1 text-xs py-1.5 rounded-md font-bold transition-all">顧客別</button>
                <button @click="leftTab = 'tags'; selectedId = null"
                    :class="leftTab === 'tags' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                    class="flex-1 text-xs py-1.5 rounded-md font-bold transition-all">タグ別</button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto py-2">
            <template x-if="loading">
                <div class="text-center py-10 text-sm text-gray-400 animate-pulse">読み込み中...</div>
            </template>
            
            {{-- タグ一覧 --}}
            <template x-if="!loading && leftTab === 'tags'">
                <div>
                    <template x-if="Object.keys(tagMap).length === 0">
                        <div class="text-center py-10 text-sm text-gray-400 px-4">タグはありません</div>
                    </template>
                    <template x-for="[tag, emails] in Object.entries(tagMap)" :key="tag">
                        <button @click="selectItem(tag, emails)"
                            class="w-full text-left px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors border-b border-gray-50"
                            :class="selectedId === tag ? 'bg-blue-50 border-l-2 border-l-blue-500' : ''">
                            <div class="flex items-center gap-2 min-w-0">
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                                <span class="text-sm font-medium text-gray-800 truncate" x-text="tag"></span>
                            </div>
                            <span class="text-xs bg-gray-100 text-gray-500 rounded-full px-2 py-0.5 shrink-0" x-text="emails.length"></span>
                        </button>
                    </template>
                </div>
            </template>

            {{-- 顧客一覧 --}}
            <template x-if="!loading && leftTab === 'customers'">
                <div>
                    <template x-if="customerData.length === 0">
                        <div class="text-center py-10 text-sm text-gray-400 px-4">顧客データはありません</div>
                    </template>
                    <template x-for="c in customerData" :key="c.id">
                        <button @click="selectItem(c.name, c.emails, c.id)"
                            class="w-full text-left px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors border-b border-gray-50"
                            :class="selectedId === c.name ? 'bg-blue-50 border-l-2 border-l-blue-500' : ''">
                            <div class="flex items-center gap-2 min-w-0">
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span class="text-sm font-medium text-gray-800 truncate" x-text="c.name"></span>
                            </div>
                            <span class="text-xs bg-gray-100 text-gray-500 rounded-full px-2 py-0.5 shrink-0" x-text="c.emails.length"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- 右パネル: メール一覧 + Wiki --}}
    <div class="flex-1 flex flex-col min-w-0 bg-gray-50 overflow-hidden">

        <template x-if="!selectedId">
            <div class="flex items-center justify-center h-full text-gray-400 text-sm">
                左の項目を選択してください
            </div>
        </template>

        <template x-if="selectedId">
            <div class="flex flex-col h-full">
                {{-- ヘッダー --}}
                <div class="px-8 py-4 bg-white border-b border-gray-200 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-50 rounded-lg">
                            <svg x-show="leftTab === 'tags'" class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            <svg x-show="leftTab === 'customers'" class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-gray-800" x-text="selectedId"></h1>
                            <p class="text-xs text-gray-400" x-text="currentEmails.length + ' 件のメール'"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="contentTab = 'emails'"
                            :class="contentTab === 'emails' ? 'bg-gray-200 text-gray-700 font-semibold' : 'text-gray-500 hover:bg-gray-100'"
                            class="text-xs px-4 py-1.5 rounded-full transition-colors">メール</button>
                        <button @click="contentTab = 'wiki'"
                            :class="contentTab === 'wiki' ? 'bg-green-100 text-green-700 font-semibold' : 'text-gray-500 hover:bg-gray-100'"
                            class="text-xs px-4 py-1.5 rounded-full transition-colors">Wiki / メモ</button>
                    </div>
                </div>

                {{-- コンテンツ --}}
                <div class="flex-1 flex overflow-hidden">

                    {{-- 中央パネル: リスト表示 --}}
                    <div class="flex-1 overflow-y-auto px-8 py-6">
                        {{-- メール一覧タブ --}}
                        <div x-show="contentTab === 'emails'">
                            <template x-if="currentEmails.length === 0">
                                <div class="text-center py-20 text-sm text-gray-400">メールがありません</div>
                            </template>
                            <div class="space-y-3">
                                <template x-for="email in currentEmails" :key="email.id">
                                    <button @click="loadThread(email.thread_id)"
                                        class="w-full text-left block bg-white border hover:shadow-md rounded-xl p-4 transition-all group"
                                        :class="selectedThreadId === email.thread_id ? 'border-blue-500 ring-1 ring-blue-500' : 'border-gray-200 hover:border-blue-400'">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded" x-text="email.from_label"></span>
                                                    <span class="text-xs text-gray-400" x-text="email.received_at"></span>
                                                </div>
                                                <h3 class="text-sm font-bold text-gray-800 mb-1 group-hover:text-blue-600 transition-colors" x-text="email.subject"></h3>
                                                <p class="text-xs text-gray-500 line-clamp-2 leading-relaxed" x-text="email.plain_body"></p>
                                            </div>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Wikiタブ --}}
                        <div x-show="contentTab === 'wiki'" class="max-w-4xl space-y-6">
                            {{-- ... (Wikiの内容) ... --}}
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-gray-500">Wiki / メモ一覧</h3>
                                <button @click="addWiki()"
                                    class="text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-full font-medium transition-colors">
                                    + 新規追加
                                </button>
                            </div>

                            <div class="space-y-4">
                                <template x-for="(item, index) in wikis" :key="index">
                                    <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                                            <input type="text" x-model="item.title" placeholder="題名..."
                                                class="bg-transparent border-none focus:ring-0 text-sm font-bold text-gray-800 flex-1 px-0">
                                            <div class="flex items-center gap-2">
                                                <button @click="item.mode = (item.mode === 'preview' ? 'edit' : 'preview')"
                                                    class="text-xs px-2 py-1 rounded text-gray-500 hover:bg-gray-200"
                                                    x-text="item.mode === 'preview' ? '編集' : 'プレビュー'"></button>
                                                <button @click="removeWiki(index)"
                                                    class="text-xs px-2 py-1 rounded text-red-400 hover:bg-red-50">削除</button>
                                            </div>
                                        </div>
                                        <div class="p-4">
                                            <div x-show="item.mode === 'edit'">
                                                <textarea x-model="item.body" rows="6" placeholder="内容（Markdown可）..."
                                                    class="w-full text-sm border-none focus:ring-0 p-0 resize-y font-mono"></textarea>
                                            </div>
                                            <div x-show="item.mode === 'preview'" class="prose prose-sm max-w-none min-h-[6rem]" x-html="renderMarkdown(item.body)"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <template x-if="wikis.length > 0">
                                <div class="flex items-center gap-4 pt-4 sticky bottom-0 bg-gray-50 py-4">
                                    <button @click="saveWikis()" :disabled="noteSaving"
                                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-8 py-2.5 rounded-full font-bold shadow-lg disabled:opacity-50 transition-colors">
                                        <span x-text="noteSaving ? '保存中...' : 'すべて保存'"></span>
                                    </button>
                                    <span x-show="noteSaved" class="text-xs text-green-600 font-bold">保存しました ✓</span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- 右パネル: スレッドサイドビュー --}}
                    <div x-show="contentTab === 'emails' && (selectedThread || loadingThread)"
                        :style="'width:' + threadPanelWidth + 'px'"
                        class="border-l border-gray-200 bg-white flex flex-col relative shadow-2xl animate-in slide-in-from-right duration-300">
                        
                        {{-- ドラッグリサイズハンドル --}}
                        <div class="absolute top-0 left-0 w-1 h-full cursor-col-resize hover:bg-blue-400 transition-colors z-10"
                            @mousedown.prevent="startResizeThread($event)">
                        </div>

                        <div class="flex flex-col h-full overflow-hidden">
                            {{-- スレッドヘッダー --}}
                            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between shrink-0 bg-gray-50">
                                <div class="min-w-0 flex-1">
                                    <template x-if="selectedThread">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="p-1 bg-white border border-gray-200 rounded shrink-0">
                                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            </div>
                                            <h2 class="text-sm font-bold text-gray-900 truncate" x-text="selectedThread.subject"></h2>
                                        </div>
                                    </template>
                                    <template x-if="loadingThread">
                                        <div class="h-4 w-48 bg-gray-200 animate-pulse rounded"></div>
                                    </template>
                                </div>
                                <button @click="selectedThread = null; selectedThreadId = null" class="text-gray-400 hover:text-gray-600 ml-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <div class="flex-1 flex overflow-hidden">
                                {{-- メール本文リスト --}}
                                <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-100/30">
                                    <template x-if="loadingThread">
                                        <div class="space-y-4">
                                            <div class="h-32 bg-white rounded-2xl animate-pulse"></div>
                                            <div class="h-32 bg-white rounded-2xl animate-pulse"></div>
                                            <div class="h-32 bg-white rounded-2xl animate-pulse"></div>
                                        </div>
                                    </template>
                                    <template x-if="!loadingThread && threadEmails.length > 0">
                                        <div class="space-y-4">
                                            <template x-for="email in threadEmails" :key="email.id">
                                                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                                                    <div @click="toggleEmail(email.id)" class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 cursor-pointer">
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm" x-text="(email.from_label || '?').charAt(0).toUpperCase()"></div>
                                                            <div>
                                                                <p class="text-sm font-bold text-gray-800" x-text="email.from_label"></p>
                                                                <p class="text-[10px] text-gray-400" x-text="email.received_at"></p>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <button @click.stop="openReply(email)" class="text-[10px] bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full hover:bg-blue-100">返信</button>
                                                            <button @click.stop="openAiPanel(email)" class="text-[10px] bg-purple-600 text-white px-2 py-0.5 rounded-full hover:bg-purple-700">AI</button>
                                                        </div>
                                                    </div>
                                                    <div x-show="expandedEmailIds.includes(email.id)" class="p-4">
                                                        <div class="prose prose-sm max-w-none text-gray-800 break-words whitespace-pre-wrap" x-html="email.body_html || email.body_text"></div>
                                                        <template x-if="email.attachments && email.attachments.length > 0">
                                                            <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap gap-2">
                                                                <template x-for="a in email.attachments" :key="a.id">
                                                                    <a :href="a.url" target="_blank" class="flex items-center gap-1.5 px-2 py-1 bg-gray-50 border border-gray-200 rounded text-[10px] text-gray-600 hover:bg-gray-100 transition-colors">
                                                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                                                                        <span x-text="a.filename"></span>
                                                                    </a>
                                                                </template>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>

                                {{-- 返信/AI アクションパネル --}}
                                <div x-show="rightTab" class="w-80 border-l border-gray-200 bg-white flex flex-col shadow-inner">
                                    {{-- 返信フォーム --}}
                                    <div x-show="rightTab === 'compose'" class="flex flex-col h-full">
                                        <div class="px-4 py-2 bg-gray-50 border-b flex items-center justify-between">
                                            <h3 class="text-xs font-bold text-gray-700">返信作成</h3>
                                            <button @click="rightTab = null" class="text-gray-400">✕</button>
                                        </div>
                                        <div class="flex-1 overflow-y-auto p-4 space-y-3">
                                            <input type="text" x-model="composeTo" placeholder="宛先" class="w-full text-xs border-b border-gray-100 outline-none py-1">
                                            <textarea x-model="composeBody" rows="10" placeholder="本文..." class="w-full text-xs border border-gray-100 rounded-lg p-2 outline-none resize-none"></textarea>
                                            
                                            <div class="flex flex-wrap gap-1">
                                                <template x-for="(f, i) in composeFilesPreview" :key="i">
                                                    <div class="relative group">
                                                        <template x-if="f.src"><img :src="f.src" class="w-12 h-12 object-cover rounded border"></template>
                                                        <template x-if="!f.src"><div class="w-12 h-12 bg-gray-50 rounded border flex items-center justify-center text-[6px] p-1" x-text="f.name"></div></template>
                                                        <button @click="removeFile(i)" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full p-0.5 text-[8px]">✕</button>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="p-3 bg-gray-50 border-t flex flex-col gap-2">
                                            <label class="flex items-center gap-1 cursor-pointer text-blue-600">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                                                <span class="text-[10px] font-bold">添付</span>
                                                <input type="file" multiple class="hidden" @change="handleFileChange">
                                            </label>
                                            <button @click="sendCompose()" :disabled="composeSending" class="w-full bg-blue-600 text-white py-2 rounded-lg text-xs font-bold shadow-md">
                                                <span x-text="composeSending ? '送信中...' : '承認依頼'"></span>
                                            </button>
                                        </div>
                                    </div>

                                    {{-- AIパネル --}}
                                    <div x-show="rightTab === 'ai'" class="flex flex-col h-full bg-purple-50/20">
                                        <div class="px-4 py-2 bg-purple-50 border-b flex items-center justify-between">
                                            <h3 class="text-xs font-bold text-purple-700">AI アシスト</h3>
                                            <button @click="rightTab = null" class="text-purple-400">✕</button>
                                        </div>
                                        <div class="flex-1 overflow-y-auto p-4 space-y-4">
                                            <textarea x-model="aiQuestion" rows="4" placeholder="指示..." class="w-full text-xs border border-purple-100 rounded-lg p-2 outline-none shadow-sm"></textarea>
                                            <button @click="askAi()" :disabled="aiLoading" class="w-full bg-purple-600 text-white py-2 rounded-lg text-xs font-bold shadow-md">
                                                <span x-text="aiLoading ? '中...' : 'AI実行'"></span>
                                            </button>
                                            <template x-if="aiAnswer">
                                                <div class="bg-white border border-purple-50 rounded-lg p-3 shadow-sm">
                                                    <div class="text-[11px] text-gray-700 leading-relaxed" x-text="aiAnswer"></div>
                                                    <button @click="insertAiText()" class="mt-2 w-full text-purple-600 text-[10px] font-bold border border-purple-200 py-1 rounded hover:bg-purple-50">本文に反映</button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
    {{-- 顧客追加モーダル --}}
    <template x-if="customerModalOpen">
        <div class="fixed inset-0 z-[110] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden" @click.stop>
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-800">新規顧客の追加</h3>
                    <button @click="customerModalOpen = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">顧客名 / 会社名</label>
                        <input type="text" x-model="newCustomerName" placeholder="株式会社 〇〇"
                            class="w-full text-sm border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">代表メール（任意）</label>
                        <input type="email" x-model="newCustomerEmail" placeholder="info@example.com"
                            class="w-full text-sm border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">ドメイン（任意）</label>
                        <input type="text" x-model="newCustomerDomain" placeholder="example.com"
                            class="w-full text-sm border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <p class="text-[10px] text-gray-400 mt-1">この顧客からのメールを RAG コレクションでマッチングするのに使用</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">RAG コレクション（任意）</label>
                        <div class="flex gap-2">
                            <select x-model="newCustomerRagCollection"
                                    class="flex-1 text-sm border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-400">
                                <option value="">自動判定（設定なし）</option>
                                <template x-for="c in ragCollections" :key="c.name">
                                    <option :value="c.name" x-text="c.name + ' (' + c.source + ')'"></option>
                                </template>
                            </select>
                            <button type="button" @click="loadRagCollections()" title="一覧を更新"
                                    class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                                <i class="fas fa-sync-alt text-gray-500" :class="ragCollectionsLoading ? 'animate-spin' : ''"></i>
                            </button>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-1">この顧客への AI 返信生成時に参照するナレッジコレクション</p>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
                    <button @click="customerModalOpen = false" class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-200 rounded-xl transition-colors">キャンセル</button>
                    <button @click="addCustomer()" :disabled="!newCustomerName"
                        class="px-6 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold disabled:opacity-50 transition-colors shadow-md">登録</button>
                </div>
            </div>
        </div>
    </template>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
function tagApp() {
    return {
        loading: false,
        leftTab: 'customers', // 'customers' | 'tags'
        contentTab: 'emails', // 'emails' | 'wiki'
        tagMap: {},
        customerData: [],
        selectedId: null,
        currentEmails: [],

        // Wikiエディタ
        wikis: [],
        noteSaving: false,
        noteSaved: false,

        // 顧客追加
        customerModalOpen: false,
        newCustomerName: '',
        newCustomerEmail: '',
        newCustomerDomain: '',
        newCustomerRagCollection: '',
        // Phase 6-1: RAG コレクション選択肢
        ragCollections: [],
        ragCollectionsLoading: false,

        async loadRagCollections() {
            if (this.ragCollectionsLoading) return;
            this.ragCollectionsLoading = true;
            try {
                const res = await fetch('/api/knowledge/collections', { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const data = await res.json();
                    this.ragCollections = data.collections || [];
                }
            } catch (e) {
                console.error('RAG コレクション取得失敗:', e);
            } finally {
                this.ragCollectionsLoading = false;
            }
        },

        async addCustomer() {
            if (!this.newCustomerName) return;
            try {
                this.noteSaving = true;
                const res = await fetch('/customers', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        name: this.newCustomerName,
                        email: this.newCustomerEmail,
                        domain: this.newCustomerDomain || null,
                        rag_collection: this.newCustomerRagCollection || null,
                    }),
                });
                if (res.ok) {
                    this.newCustomerName = '';
                    this.newCustomerEmail = '';
                    this.newCustomerDomain = '';
                    this.newCustomerRagCollection = '';
                    this.customerModalOpen = false;
                    await this.reloadData();
                }
            } catch (_) {
            } finally {
                this.noteSaving = false;
            }
        },

        // スレッド詳細
        selectedThreadId: null,
        selectedThread: null,
        threadEmails: [],
        threadMerges: [],
        expandedEmailIds: [],
        loadingThread: false,
        threadSortOrder: 'desc',
        threadPanelWidth: parseInt(localStorage.getItem('threadPanelWidth')) || 600,

        startResizeThread(e) {
            const startX = e.clientX;
            const startWidth = this.threadPanelWidth;
            const onMove = (me) => {
                const delta = startX - me.clientX;
                const maxWidth = window.innerWidth - 300;
                const newW = Math.max(400, Math.min(maxWidth, startWidth + delta));
                this.threadPanelWidth = newW;
                localStorage.setItem('threadPanelWidth', newW);
            };
            const onUp = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        // 返信・作成
        rightTab: null, // null | 'compose' | 'ai'
        composeType: 'reply',
        composeEmailRef: null,
        composeTo: '',
        composeCc: '',
        composeSubject: '',
        composeBody: '',
        composeMemo: '',
        composeCreatedBy: '',
        composeFiles: [],
        composeFilesPreview: [],
        composeSending: false,
        composeError: '',
        composeSent: false,

        // AI
        aiTargetEmail: null,
        aiQuestion: '',
        aiAnswer: '',
        aiSources: [],
        aiLoading: false,

        async init() {
            this.composeCreatedBy = localStorage.getItem('currentUser') || '';
            await this.reloadData();
            // URLからタグ指定がある場合は選択
            const params = new URLSearchParams(window.location.search);
            const tag = params.get('tag');
            if (tag) {
                this.leftTab = 'tags';
                const emails = this.tagMap[tag] || [];
                await this.selectItem(tag, emails);
            }
        },

        async reloadData() {
            this.loading = true;
            try {
                const [tagRes, custRes] = await Promise.all([
                    fetch('/tags/data', { headers: { 'Accept': 'application/json' } }),
                    fetch('/customers/data', { headers: { 'Accept': 'application/json' } })
                ]);
                this.tagMap = await tagRes.json();
                this.customerData = await custRes.json();
            } catch (_) {
            } finally {
                this.loading = false;
            }
        },

        async selectItem(name, emails, customerId = null) {
            this.selectedId = name;
            this.currentEmails = emails;
            this.contentTab = 'emails';
            this.selectedThread = null; // リセット
            this.noteSaved = false;
            await this.loadWikis(name);
        },

        async loadThread(threadId) {
            this.loadingThread = true;
            this.selectedThreadId = threadId;
            this.rightTab = null;
            try {
                const res = await fetch(`/threads/${threadId}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                this.selectedThread = data.thread;
                this.threadEmails = this.sortEmails(data.emails, this.threadSortOrder);
                this.threadMerges = data.merges || [];
                this.expandedEmailIds = data.emails.map(e => e.id);
            } catch (_) {
            } finally {
                this.loadingThread = false;
            }
        },

        sortEmails(emails, order) {
            return [...emails].sort((a, b) => {
                const cmp = (a.received_at ?? '').localeCompare(b.received_at ?? '');
                return order === 'asc' ? cmp : -cmp;
            });
        },

        toggleEmail(emailId) {
            const idx = this.expandedEmailIds.indexOf(emailId);
            if (idx === -1) this.expandedEmailIds.push(emailId);
            else this.expandedEmailIds.splice(idx, 1);
        },

        // 返信アクション
        openReply(email) {
            this.composeType = 'reply';
            this.composeEmailRef = email;
            this.composeTo = email.from_address;
            this.composeCc = '';
            this.composeSubject = email.subject.match(/^Re:/i) ? email.subject : 'Re: ' + email.subject;
            this.composeBody = '';
            this.composeFiles = [];
            this.composeFilesPreview = [];
            this.composeError = '';
            this.composeSent = false;
            this.rightTab = 'compose';
        },

        openReplyAll(email) {
            this.composeType = 'reply_all';
            this.composeEmailRef = email;
            this.composeTo = email.from_address;
            const ccList = [email.to_address, ...(email.cc ? email.cc.split(',').map(s => s.trim()) : [])]
                .filter(addr => addr && addr !== email.from_address);
            this.composeCc = [...new Set(ccList)].join(', ');
            this.composeSubject = email.subject.match(/^Re:/i) ? email.subject : 'Re: ' + email.subject;
            this.composeBody = '';
            this.composeFiles = [];
            this.composeFilesPreview = [];
            this.composeError = '';
            this.composeSent = false;
            this.rightTab = 'compose';
        },

        handleFileChange(e) {
            const files = Array.from(e.target.files);
            this.composeFiles = [...this.composeFiles, ...files];
            files.forEach(f => {
                if (f.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (re) => this.composeFilesPreview.push({ name: f.name, src: re.target.result });
                    reader.readAsDataURL(f);
                } else {
                    this.composeFilesPreview.push({ name: f.name, src: null });
                }
            });
        },

        removeFile(index) {
            this.composeFiles.splice(index, 1);
            this.composeFilesPreview.splice(index, 1);
        },

        async sendCompose() {
            this.composeSending = true;
            this.composeError = '';
            try {
                const formData = new FormData();
                formData.append('type', this.composeType);
                formData.append('to', this.composeTo);
                formData.append('cc', this.composeCc);
                formData.append('subject', this.composeSubject);
                formData.append('body', this.composeBody);
                formData.append('memo', this.composeMemo);
                formData.append('created_by', this.composeCreatedBy);
                if (this.composeEmailRef) formData.append('in_reply_to_email_id', this.composeEmailRef.id);
                this.composeFiles.forEach(f => formData.append('attachments[]', f));

                const res = await fetch('/emails/compose', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: formData
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || '送信失敗');

                this.composeSent = true;
                setTimeout(() => {
                    this.rightTab = null;
                    this.composeSent = false;
                }, 2000);
                if (this.selectedThreadId) await this.loadThread(this.selectedThreadId);
            } catch (e) {
                this.composeError = e.message;
            } finally {
                this.composeSending = false;
            }
        },

        // AI関連
        async openAiPanel(email) {
            this.aiTargetEmail = email;
            this.aiQuestion = '';
            this.aiAnswer = '';
            this.aiSources = [];
            this.rightTab = 'ai';
        },

        async askAi() {
            if (!this.aiQuestion.trim()) return;
            this.aiLoading = true;
            this.aiAnswer = '';
            try {
                const res = await fetch(`/emails/${this.aiTargetEmail.id}/ai`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ question: this.aiQuestion }),
                });
                const data = await res.json();
                this.aiAnswer = data.answer || data.error || '回答を取得できませんでした。';
                this.aiSources = data.sources || [];
            } catch (e) {
                this.aiAnswer = 'エラー: ' + e.message;
            } finally {
                this.aiLoading = false;
            }
        },

        insertAiText() {
            const sep = this.composeBody.trim() ? '\n\n' : '';
            this.composeBody = this.composeBody + sep + this.aiAnswer;
            this.rightTab = 'compose';
        },

        async loadWikis(name) {
            try {
                const res = await fetch(`/tag-notes/${encodeURIComponent(name)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                const content = Array.isArray(data.content) ? data.content : [];
                this.wikis = content.map(item => ({
                    title: item.title || '',
                    body: item.body || '',
                    mode: 'preview'
                }));
            } catch (_) {
                this.wikis = [];
            }
        },

        addWiki() {
            this.wikis.push({ title: '', body: '', mode: 'edit' });
            this.contentTab = 'wiki';
        },

        removeWiki(index) {
            if (confirm('このメモを削除しますか？')) {
                this.wikis.splice(index, 1);
            }
        },

        async saveWikis() {
            if (!this.selectedId) return;
            this.noteSaving = true;
            this.noteSaved = false;
            try {
                const payload = this.wikis.map(w => ({ title: w.title, body: w.body }));
                await fetch(`/tag-notes/${encodeURIComponent(this.selectedId)}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ content: payload }),
                });
                this.noteSaved = true;
                setTimeout(() => { this.noteSaved = false; }, 2000);
            } catch (_) {
            } finally {
                this.noteSaving = false;
            }
        },

        renderMarkdown(text) {
            if (!text) return '';
            return typeof marked !== 'undefined' ? marked.parse(text) : text.replace(/\n/g, '<br>');
        }
    };
}
</script>

<style>
.prose h1 { @apply text-xl font-bold mb-4; }
.prose h2 { @apply text-lg font-bold mb-3; }
.prose h3 { @apply text-base font-bold mb-2; }
.prose p { @apply mb-4 leading-relaxed; }
.prose ul { @apply list-disc ml-5 mb-4; }
.prose ol { @apply list-decimal ml-5 mb-4; }
.prose code { @apply bg-gray-100 px-1 rounded text-red-500 font-mono; }
.prose pre { @apply bg-gray-800 text-gray-100 p-4 rounded-lg mb-4 overflow-x-auto; }
.prose blockquote { @apply border-l-4 border-gray-200 pl-4 italic text-gray-600 mb-4; }
</style>
@endsection
