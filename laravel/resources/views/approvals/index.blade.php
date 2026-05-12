@extends('layouts.app')
@section('title', '承認待ち')

@section('css')
<style>
    .content-header { display: none !important; }
    .content, .content > .container-fluid {
        padding: 0 !important;
        max-width: 100% !important;
        height: calc(100vh - 3.5rem);
        overflow: hidden;
        background: #f9fafb;
    }
    .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
    [x-cloak] { display: none !important; }
</style>
@endsection

@section('content')
<div class="flex h-full bg-gray-50" x-data="approvalApp()" x-init="init()" x-cloak>

    {{-- 左: 承認待ちリスト --}}
    <div class="w-[360px] shrink-0 border-r border-gray-200 bg-white flex flex-col">
        {{-- ヘッダー --}}
        <div class="px-5 py-4 border-b border-gray-100 bg-white">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-extrabold text-gray-900" x-text="viewMode === 'sent' ? '送信済' : '承認待ち'"></h2>
                <button @click="loadPending()"
                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-gray-400 hover:text-blue-600 hover:bg-gray-50 transition-all"
                    :class="{ 'animate-spin text-blue-600': loading }"
                    title="更新">
                    <i class="fas fa-sync-alt text-sm"></i>
                </button>
            </div>

            {{-- 表示モードタブ (viewMode により表示タブを切り替え) --}}
            {{-- viewMode === 'approval' : 承認待ち / 却下済 のみ --}}
            {{-- viewMode === 'sent'     : 送信済 のみ --}}
            <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-lg mb-2 w-full">
                <template x-if="viewMode === 'approval'">
                    <div class="flex items-center gap-1 flex-1">
                        <button @click="setStatusTab('pending')"
                                :class="statusTab === 'pending' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-500 hover:text-gray-800'"
                                class="flex-1 min-w-0 py-1.5 px-2 rounded-md text-[11px] font-bold transition-all inline-flex items-center justify-center gap-1 whitespace-nowrap">
                            <i class="fas fa-hourglass-half text-[10px]"></i>
                            <span>承認待ち</span>
                        </button>
                        <button @click="setStatusTab('rejected')"
                                :class="statusTab === 'rejected' ? 'bg-white shadow-sm text-red-600' : 'text-gray-500 hover:text-gray-800'"
                                class="flex-1 min-w-0 py-1.5 px-2 rounded-md text-[11px] font-bold transition-all inline-flex items-center justify-center gap-1 whitespace-nowrap">
                            <i class="fas fa-times-circle text-[10px]"></i>
                            <span>却下済</span>
                        </button>
                    </div>
                </template>
                <template x-if="viewMode === 'sent'">
                    <div class="flex-1 min-w-0 py-1.5 px-2 rounded-md text-[11px] font-bold inline-flex items-center justify-center gap-1 whitespace-nowrap bg-white shadow-sm text-green-600">
                        <i class="fas fa-paper-plane text-[10px]"></i>
                        <span>送信済</span>
                    </div>
                </template>
            </div>

            {{-- 対象者フィルタ (承認待ち時のみ表示。レイアウトシフトを避けるため、非表示時も同じ高さを維持) --}}
            <div class="flex items-center gap-1 bg-gray-50 p-1 rounded-lg mb-2 transition-opacity" :class="statusTab === 'pending' ? '' : 'invisible pointer-events-none'">
                <button @click="setFilter('me')"
                        :class="filter === 'me' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-500 hover:text-gray-800'"
                        class="flex-1 py-1 rounded-md text-[11px] font-bold transition-all flex items-center justify-center gap-1">
                    <i class="fas fa-user-check text-[10px]"></i>
                    あなた宛
                    <span x-show="filter === 'me' && allEmails.length > 0"
                          class="ml-1 bg-blue-600 text-white text-[9px] font-black px-1.5 rounded-full"
                          x-text="allEmails.length"></span>
                </button>
                <button @click="setFilter('mine')"
                        :class="filter === 'mine' ? 'bg-white shadow-sm text-emerald-600' : 'text-gray-500 hover:text-gray-800'"
                        class="flex-1 py-1 rounded-md text-[11px] font-bold transition-all flex items-center justify-center gap-1">
                    <i class="fas fa-paper-plane text-[10px]"></i>
                    自分が依頼
                    <span x-show="filter === 'mine' && allEmails.length > 0"
                          class="ml-1 bg-emerald-600 text-white text-[9px] font-black px-1.5 rounded-full"
                          x-text="allEmails.length"></span>
                </button>
                <button @click="setFilter('all')"
                        :class="filter === 'all' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-500 hover:text-gray-800'"
                        class="flex-1 py-1 rounded-md text-[11px] font-bold transition-all flex items-center justify-center gap-1">
                    <i class="fas fa-list text-[10px]"></i>
                    すべて
                </button>
            </div>
            <p class="text-[11px] text-gray-400 font-medium min-h-[16px]" x-text="filterDescription"></p>
        </div>

        {{-- リスト --}}
        <div class="overflow-y-auto flex-1 px-3 py-3 space-y-2 custom-scrollbar">
            <template x-if="loading">
                <div class="flex flex-col items-center justify-center py-16 text-gray-300">
                    <i class="fas fa-circle-notch fa-spin fa-2x mb-2"></i>
                    <p class="text-xs font-bold">読み込み中...</p>
                </div>
            </template>
            <template x-if="!loading && allEmails.length === 0">
                <div class="text-center py-16 px-4 text-gray-400">
                    <i :class="emptyIconClass" class="fa-2x text-gray-300 mb-3"></i>
                    <p class="text-sm font-semibold text-gray-600 mb-1">
                        <span x-show="statusTab === 'pending' && filter === 'me'">あなた宛の承認依頼はありません</span>
                        <span x-show="statusTab === 'pending' && filter === 'mine'">自分が依頼した承認待ちはありません</span>
                        <span x-show="statusTab === 'pending' && filter === 'all'">承認待ちの依頼はありません</span>
                        <span x-show="statusTab === 'approved'">承認済の依頼はありません</span>
                        <span x-show="statusTab === 'rejected'">却下された依頼はありません</span>
                    </p>
                    <p class="text-[11px] text-gray-400" x-show="statusTab === 'pending' && filter === 'me'">
                        他のユーザーが承認者にあなたを指定すると、ここに表示されます。
                    </p>
                    <p class="text-[11px] text-gray-400" x-show="statusTab === 'pending' && filter === 'mine'">
                        新規作成・返信から「承認を依頼する」を送信すると、ここに表示されます。
                    </p>
                </div>
            </template>

            <template x-for="p in allEmails" :key="p.id">
                <div class="group border rounded-xl overflow-hidden cursor-pointer transition-all"
                    :class="selectedId === p.id ? 'border-blue-500 ring-2 ring-blue-100 bg-white' : 'border-gray-200 hover:border-gray-300 bg-white'"
                    @click="selectEmail(p)">
                    <div class="px-3 py-2 border-b border-gray-50 flex items-center justify-between gap-2"
                         :class="selectedId === p.id ? 'bg-blue-50' : 'bg-gray-50/40 group-hover:bg-gray-50'">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="text-[9px] font-black text-white bg-blue-600 px-1.5 py-0.5 rounded shrink-0"
                                x-text="p.reply_type_label"></span>
                            <span class="text-[11px] font-semibold text-gray-700 truncate"
                                x-text="p.created_by_user_id === {{ auth()->id() }} ? 'あなたの依頼' : (p.created_by || '不明') + ' から'"></span>
                        </div>
                        <span class="text-[10px] text-gray-400 shrink-0" x-text="p.created_at"></span>
                    </div>
                    <div class="px-3 py-2.5">
                        <p class="text-[13px] font-bold text-gray-800 mb-1 line-clamp-2" x-text="p.subject"></p>
                        <p class="text-[11px] text-gray-500 truncate">
                            <span class="text-gray-400">To:</span> <span x-text="p.to_address"></span>
                        </p>
                        <template x-if="p.target_approver_name">
                            <p class="text-[10px] text-amber-700 mt-1.5 inline-flex items-center gap-1 bg-amber-50 px-2 py-0.5 rounded border border-amber-100">
                                <i class="fas fa-user-check"></i> 承認者: <span class="font-bold" x-text="p.target_approver_name"></span>
                            </p>
                        </template>
                        <template x-if="statusTab === 'rejected'">
                            <div class="mt-1.5 space-y-1">
                                <p class="text-[10px] text-red-700 inline-flex items-center gap-1 bg-red-50 px-2 py-0.5 rounded border border-red-200">
                                    <i class="fas fa-times-circle"></i>
                                    却下: <span class="font-bold" x-text="p.rejected_by_name || '不明'"></span>
                                    <span class="text-red-400 ml-1" x-text="p.rejected_at"></span>
                                </p>
                                <p x-show="p.rejection_reason" class="text-[10px] text-red-700 bg-red-50 px-2 py-1 rounded border border-red-100 line-clamp-2">
                                    理由: <span x-text="p.rejection_reason"></span>
                                </p>
                            </div>
                        </template>
                        <template x-if="statusTab === 'approved'">
                            <p class="text-[10px] text-green-700 mt-1.5 inline-flex items-center gap-1 bg-green-50 px-2 py-0.5 rounded border border-green-200">
                                <i class="fas fa-check-circle"></i>
                                承認: <span class="font-bold" x-text="p.approved_by_name || '不明'"></span>
                                <span class="text-green-500 ml-1" x-text="p.approved_at"></span>
                            </p>
                        </template>
                        <template x-if="p.memo">
                            <div class="mt-2 p-2 bg-amber-50 border border-amber-100 rounded text-[11px] text-amber-700 line-clamp-2">
                                <i class="fas fa-comment-dots mr-1"></i><span x-text="p.memo"></span>
                            </div>
                        </template>
                        <p class="text-[11px] text-gray-500 mt-2 line-clamp-2 leading-relaxed" x-text="p.body_preview"></p>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- 右: 詳細 + 承認/却下 --}}
    <div class="flex-1 flex flex-col min-w-0 bg-gray-50 overflow-hidden">

        <template x-if="!selectedEmail">
            <div class="flex flex-col items-center justify-center h-full text-gray-300 px-6">
                <i class="fas fa-file-alt fa-3x mb-4 text-gray-200"></i>
                <p class="text-sm font-semibold text-gray-500">左の一覧から選択してください</p>
            </div>
        </template>

        <template x-if="selectedEmail">
            <div class="flex flex-col h-full animate-in fade-in duration-200">
                {{-- アクションヘッダー --}}
                <div class="px-8 py-5 bg-white border-b border-gray-200 flex items-start justify-between gap-6 shrink-0">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                            <span class="text-[10px] font-black text-white bg-blue-600 px-2 py-0.5 rounded uppercase tracking-wider"
                                x-text="selectedEmail.reply_type_label"></span>
                            <span class="text-xs font-bold text-gray-600"
                                x-text="(selectedEmail.created_by || '不明') + ' による依頼'"></span>
                            <span class="text-[11px] text-gray-400" x-text="selectedEmail.created_at"></span>
                            <template x-if="selectedEmail.target_approver_name">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                    <i class="fas fa-user-check"></i> 承認者: <span x-text="selectedEmail.target_approver_name"></span>
                                </span>
                            </template>
                        </div>
                        <h1 class="text-lg font-extrabold text-gray-900 leading-tight mb-2" x-text="selectedEmail.subject"></h1>
                        <div class="space-y-0.5">
                            <p class="text-xs text-gray-600">
                                <span class="text-gray-400 font-bold mr-1">To:</span>
                                <span x-text="selectedEmail.to_address"></span>
                            </p>
                            <template x-if="selectedEmail.cc">
                                <p class="text-xs text-gray-600">
                                    <span class="text-gray-400 font-bold mr-1">Cc:</span>
                                    <span x-text="selectedEmail.cc"></span>
                                </p>
                            </template>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        {{-- 自分の依頼: 承認待ちなら取り下げ可・承認/却下済はバッジのみ --}}
                        <template x-if="statusTab === 'pending' && selectedEmail.created_by_user_id === {{ auth()->id() }}">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-2 rounded-lg border border-amber-200">
                                    <i class="fas fa-info-circle mr-1"></i>あなたの依頼
                                </span>
                                <button @click="withdraw(selectedEmail)"
                                    :disabled="actionLoading"
                                    class="bg-white text-orange-600 border border-orange-200 text-xs font-bold px-4 py-2 rounded-lg hover:bg-orange-50 transition-all disabled:opacity-50 inline-flex items-center gap-1.5"
                                    style="background-color:#ffffff;color:#ea580c;border-color:#fed7aa;">
                                    <i class="fas fa-undo"></i>
                                    <span x-text="actionLoading ? '処理中...' : '取り下げ'"></span>
                                </button>
                            </div>
                        </template>
                        <template x-if="statusTab !== 'pending' && selectedEmail.created_by_user_id === {{ auth()->id() }}">
                            <span class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-2 rounded-lg border border-amber-200">
                                <i class="fas fa-info-circle mr-1"></i>あなたの依頼
                            </span>
                        </template>
                        <template x-if="statusTab === 'pending' && selectedEmail.created_by_user_id !== {{ auth()->id() }} && (!selectedEmail.target_approver_user_id || selectedEmail.target_approver_user_id === {{ auth()->id() }})">
                            <div class="flex items-center gap-2">
                                <button @click="openRejectModal(selectedEmail)"
                                    :disabled="actionLoading"
                                    class="bg-white text-red-600 border border-red-200 text-xs font-bold px-4 py-2 rounded-lg hover:bg-red-50 transition-all disabled:opacity-50">
                                    <i class="fas fa-times mr-1"></i>却下
                                </button>
                                <button @click="approve(selectedEmail)"
                                    :disabled="actionLoading"
                                    class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-5 py-2 rounded-lg transition-all flex items-center gap-1.5 disabled:opacity-50"
                                    style="background-color:#2563eb;color:#ffffff;">
                                    <i class="fas fa-check"></i>
                                    <span x-text="actionLoading ? '処理中...' : '承認・送信'"></span>
                                </button>
                            </div>
                        </template>
                        <template x-if="statusTab === 'pending' && selectedEmail.target_approver_user_id && selectedEmail.target_approver_user_id !== {{ auth()->id() }} && selectedEmail.created_by_user_id !== {{ auth()->id() }}">
                            <span class="text-xs font-bold text-gray-500 bg-gray-100 px-3 py-2 rounded-lg border border-gray-200">
                                <i class="fas fa-lock mr-1"></i>他のユーザーが承認者です
                            </span>
                        </template>
                        <template x-if="statusTab === 'rejected'">
                            <span class="inline-flex items-center gap-1 text-xs font-bold text-red-700 bg-red-50 px-3 py-2 rounded-lg border border-red-200">
                                <i class="fas fa-times-circle"></i>
                                却下済み (下書きとして再生成済)
                            </span>
                        </template>
                        <template x-if="statusTab === 'approved'">
                            <span class="inline-flex items-center gap-1 text-xs font-bold text-green-700 bg-green-50 px-3 py-2 rounded-lg border border-green-200">
                                <i class="fas fa-check-circle"></i>
                                送信済み
                            </span>
                        </template>
                    </div>
                </div>

                {{-- 却下情報 (rejected タブで詳細表示) --}}
                <template x-if="statusTab === 'rejected' && selectedEmail">
                    <div class="mx-8 mt-4 shrink-0 bg-red-50 border border-red-200 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="fas fa-times-circle text-red-600"></i>
                            <p class="text-xs font-bold text-red-700">
                                却下: <span x-text="selectedEmail.rejected_by_name || '不明'"></span>
                                <span class="text-red-500 ml-2 font-medium" x-text="selectedEmail.rejected_at"></span>
                            </p>
                        </div>
                        <p x-show="selectedEmail.rejection_reason" class="text-sm text-red-900 whitespace-pre-wrap leading-relaxed"
                           x-text="selectedEmail.rejection_reason"></p>
                        <p x-show="!selectedEmail.rejection_reason" class="text-xs text-red-500 italic">却下理由は入力されていません</p>
                        <p class="text-[10px] text-red-600 mt-2"><i class="fas fa-info-circle mr-1"></i>この内容は依頼者の「下書き」に再生成されています。依頼者は <code class="bg-white px-1 rounded">/drafts</code> から再編集可能です。</p>
                    </div>
                </template>

                {{-- 承認情報 (approved タブで詳細表示) --}}
                <template x-if="statusTab === 'approved' && selectedEmail">
                    <div class="mx-8 mt-4 shrink-0 bg-green-50 border border-green-200 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-1">
                            <i class="fas fa-check-circle text-green-600"></i>
                            <p class="text-xs font-bold text-green-700">
                                承認: <span x-text="selectedEmail.approved_by_name || '不明'"></span>
                                <span class="text-green-500 ml-2 font-medium" x-text="selectedEmail.approved_at"></span>
                            </p>
                        </div>
                        <p class="text-[10px] text-green-700 mt-1"><i class="fas fa-paper-plane mr-1"></i>このメールは承認後、自動的に SMTP 経由で送信されています。</p>
                    </div>
                </template>

                {{-- アクションメッセージ --}}
                <template x-if="actionMessage">
                    <div class="mx-8 mt-4 shrink-0 text-xs font-bold px-4 py-3 rounded-lg border"
                        :class="actionError ? 'bg-red-50 text-red-700 border-red-200' : 'bg-green-50 text-green-700 border-green-200'">
                        <i class="fas mr-1" :class="actionError ? 'fa-exclamation-circle' : 'fa-check-circle'"></i>
                        <span x-text="actionMessage"></span>
                    </div>
                </template>

                {{-- 詳細スクロール --}}
                <div class="flex-1 overflow-y-auto px-8 py-6 space-y-5 custom-scrollbar">

                    {{-- メモ --}}
                    <template x-if="selectedEmail.memo">
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                            <p class="text-[10px] font-bold text-amber-700 uppercase tracking-wider mb-2">
                                <i class="fas fa-comment-dots mr-1"></i><span x-text="selectedEmail.created_by || '担当者'"></span> からの申し送り
                            </p>
                            <p class="text-sm text-amber-900 font-medium leading-relaxed whitespace-pre-wrap" x-text="selectedEmail.memo"></p>
                        </div>
                    </template>

                    {{-- 返信元メール --}}
                    <template x-if="selectedEmail.in_reply_to">
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-envelope mr-1"></i>返信元メール
                                </p>
                                <span class="text-[10px] text-gray-400" x-text="selectedEmail.in_reply_to.received_at"></span>
                            </div>
                            <div class="px-4 py-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 font-bold text-xs"
                                         x-text="selectedEmail.in_reply_to.from_label.charAt(0)"></div>
                                    <div>
                                        <p class="text-xs font-bold text-gray-800" x-text="selectedEmail.in_reply_to.from_label"></p>
                                        <p class="text-[10px] text-gray-400" x-text="selectedEmail.in_reply_to.subject"></p>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-700 whitespace-pre-wrap leading-relaxed bg-gray-50 p-3 rounded-lg border border-gray-100"
                                    x-text="selectedEmail.in_reply_to.plain_body"></div>
                            </div>
                        </div>
                    </template>

                    {{-- 添付ファイル --}}
                    <template x-if="selectedEmail.attachments && selectedEmail.attachments.length > 0">
                        <div class="bg-white rounded-xl border border-gray-200 p-4">
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-3">
                                <i class="fas fa-paperclip mr-1"></i>添付ファイル (<span x-text="selectedEmail.attachments.length"></span>)
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="att in selectedEmail.attachments" :key="att.filename">
                                    <span class="inline-flex items-center gap-2 text-xs font-semibold bg-gray-50 border border-gray-200 text-gray-700 px-3 py-1.5 rounded-lg">
                                        <i class="fas fa-file"></i>
                                        <span x-text="att.filename"></span>
                                        <span class="text-[10px] text-gray-400" x-text="'(' + att.size + ')'"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- 返信本文 --}}
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-100">
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-pen mr-1"></i>送信予定の本文
                            </p>
                        </div>
                        <div class="p-5">
                            <pre class="text-sm text-gray-800 whitespace-pre-wrap font-sans leading-relaxed" x-text="selectedEmail.body"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- 却下モーダル --}}
    <template x-if="rejectModalOpen">
        <div class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-md p-4" @click.self="closeRejectModal()">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="bg-red-50 px-6 py-5 border-b border-red-100 flex items-center gap-3">
                    <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center text-red-600">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-red-900">承認依頼を却下</h3>
                        <p class="text-xs text-red-600 mt-0.5">理由を入力すると依頼者に通知されます</p>
                    </div>
                </div>
                <div class="px-6 py-5 space-y-3">
                    <div class="text-xs text-gray-700 bg-gray-50 px-3 py-2 rounded-lg border border-gray-200">
                        <span class="font-bold">対象:</span>
                        <span class="ml-1" x-text="rejectingEmail?.subject"></span>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">
                            却下理由 <span class="text-red-600">*</span> <span class="text-[10px] text-gray-400 font-normal">(必須)</span>
                        </label>
                        <textarea x-model="rejectReason" rows="4" required minlength="1"
                                  placeholder="例: 文面の○○の表現を修正してください..."
                                  :class="rejectReason.trim() === '' ? 'border-red-300 ring-2 ring-red-50' : 'border-gray-200'"
                                  class="w-full bg-gray-50 border rounded-lg px-3 py-2 text-sm text-gray-700 outline-none focus:ring-2 focus:ring-red-100 focus:border-red-300 resize-y"></textarea>
                        <p x-show="rejectReason.trim() === ''" class="text-[11px] text-red-600 mt-1 font-bold">
                            <i class="fas fa-exclamation-circle"></i> 却下理由を入力してください
                        </p>
                        <p class="text-[10px] text-gray-400 mt-1">却下後、依頼者の下書きに本メールがコピーされ、却下メモ + 理由が追記されます。</p>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex gap-2 justify-end">
                    <button @click="closeRejectModal()"
                            class="bg-white border border-gray-200 text-gray-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gray-100 transition-all">
                        キャンセル
                    </button>
                    <button @click="confirmReject()" :disabled="actionLoading || rejectReason.trim() === ''"
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                            style="background-color:#dc2626;color:#ffffff;">
                        <i class="fas fa-times"></i>
                        <span x-text="actionLoading ? '処理中...' : '却下する'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function approvalApp() {
    return {
        loading: false,
        allEmails: [],
        selectedId: null,
        selectedEmail: null,
        actionLoading: false,
        actionMessage: '',
        actionError: false,
        statusTab: 'pending',  // 'pending' / 'rejected' / 'approved'
        viewMode: 'approval',  // 'approval' (承認待ち画面: pending/rejected) / 'sent' (送信済画面: approved)
        filter: 'me',          // 'me' / 'all' (pending タブ時のみ)
        rejectModalOpen: false,
        rejectingEmail: null,
        rejectReason: '',

        get filterDescription() {
            if (this.statusTab === 'rejected') return '過去に却下された依頼の履歴';
            if (this.statusTab === 'approved') return '承認されて送信完了した依頼の履歴';
            if (this.filter === 'me')   return '他のユーザーがあなたを承認者に指定した依頼';
            if (this.filter === 'mine') return 'あなたが送信した承認依頼 (相手の承認待ち)';
            return '全ての承認待ち';
        },
        get emptyIconClass() {
            if (this.statusTab === 'approved') return 'fas fa-check-circle';
            if (this.statusTab === 'rejected') return 'fas fa-times-circle';
            return 'fas fa-inbox';
        },

        async init() {
            // URL クエリパラメータで画面モードと初期タブを決定
            //   ?view=sent : 送信済画面 (承認済データを表示。タブは「送信済」のみ)
            //   それ以外    : 承認待ち画面 (タブは「承認待ち / 却下済」)
            try {
                const params = new URLSearchParams(window.location.search);
                const view = params.get('view');
                if (view === 'sent') {
                    this.viewMode = 'sent';
                    this.statusTab = 'approved';
                } else {
                    this.viewMode = 'approval';
                    // 承認待ち画面では tab=rejected のみ受け付ける。それ以外は pending。
                    const tab = params.get('tab');
                    this.statusTab = (tab === 'rejected') ? 'rejected' : 'pending';
                }
            } catch (_) {}
            await this.loadPending();
        },

        setStatusTab(tab) {
            if (this.statusTab === tab) return;
            // 'sent' ビュー (送信済画面) では statusTab は 'approved' 固定
            if (this.viewMode === 'sent') return;
            // 'approval' ビューでは 'approved' (送信済) を表示しない
            if (this.viewMode === 'approval' && tab === 'approved') return;
            this.statusTab = tab;
            this.selectedId = null;
            this.selectedEmail = null;
            this.actionMessage = '';
            this.loadPending();
        },

        setFilter(f) {
            if (this.filter === f) return;
            this.filter = f;
            this.selectedId = null;
            this.selectedEmail = null;
            this.actionMessage = '';
            this.loadPending();
        },

        async loadPending() {
            this.loading = true;
            try {
                const params = new URLSearchParams({ status: this.statusTab });
                if (this.statusTab === 'pending' && this.filter === 'me')   params.set('for_me', '1');
                if (this.statusTab === 'pending' && this.filter === 'mine') params.set('mine',   '1');
                const res = await fetch('/pending-emails?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                this.allEmails = await res.json();
                if (this.selectedId) {
                    const updated = this.allEmails.find(p => p.id === this.selectedId);
                    if (updated) {
                        this.selectedEmail = updated;
                    } else {
                        this.selectedId = null;
                        this.selectedEmail = null;
                    }
                }
            } catch (e) {
                console.error('Failed to load pending emails:', e);
            } finally {
                this.loading = false;
            }
        },

        selectEmail(p) {
            this.selectedId = p.id;
            this.selectedEmail = p;
            this.actionMessage = '';
            this.actionError = false;
        },

        async approve(p) {
            if (!confirm('このメールを承認し、実際に送信しますか？')) return;
            this.actionLoading = true;
            this.actionMessage = '';
            this.actionError = false;
            try {
                const res = await fetch(`/pending-emails/${p.id}/approve`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
                const data = await res.json();
                if (!res.ok || data.status === 'error') {
                    this.actionMessage = data.message || '送信に失敗しました';
                    this.actionError = true;
                } else {
                    this.actionMessage = '承認しました。メールを送信しました。';
                    this.actionError = false;
                    setTimeout(() => {
                        this.selectedId = null;
                        this.selectedEmail = null;
                        this.actionMessage = '';
                        this.loadPending();
                    }, 1500);
                }
            } catch (e) {
                this.actionMessage = 'エラー: ' + e.message;
                this.actionError = true;
            } finally {
                this.actionLoading = false;
            }
        },

        async withdraw(p) {
            if (!confirm('この承認依頼を取り下げますか？\n下書きに戻り、後から再編集・再依頼できます。')) return;
            this.actionLoading = true;
            this.actionMessage = '';
            this.actionError = false;
            try {
                const res = await fetch(`/pending-emails/${p.id}/withdraw`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (!res.ok || data.status === 'error') {
                    this.actionMessage = data.message || '取り下げに失敗しました';
                    this.actionError = true;
                } else {
                    this.actionMessage = data.message || '依頼を取り下げ、下書きに戻しました';
                    this.actionError = false;
                    setTimeout(() => {
                        this.selectedId = null;
                        this.selectedEmail = null;
                        this.actionMessage = '';
                        this.loadPending();
                    }, 1500);
                }
            } catch (e) {
                this.actionMessage = 'エラー: ' + e.message;
                this.actionError = true;
            } finally {
                this.actionLoading = false;
            }
        },

        // モーダルで却下理由を入力
        openRejectModal(p) {
            this.rejectingEmail = p;
            this.rejectReason = '';
            this.rejectModalOpen = true;
        },
        closeRejectModal() {
            this.rejectModalOpen = false;
            this.rejectingEmail = null;
            this.rejectReason = '';
        },

        async confirmReject() {
            if (!this.rejectingEmail) return;
            this.actionLoading = true;
            this.actionMessage = '';
            this.actionError = false;
            try {
                const res = await fetch(`/pending-emails/${this.rejectingEmail.id}/reject`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ rejection_reason: this.rejectReason }),
                });
                const data = await res.json();
                if (!res.ok || data.status === 'error') {
                    this.actionMessage = data.message || '却下に失敗しました';
                    this.actionError = true;
                } else {
                    this.actionMessage = data.message || '却下しました';
                    this.actionError = false;
                    this.closeRejectModal();
                    setTimeout(() => {
                        this.selectedId = null;
                        this.selectedEmail = null;
                        this.actionMessage = '';
                        this.loadPending();
                    }, 1500);
                }
            } catch (e) {
                this.actionMessage = 'エラー: ' + e.message;
                this.actionError = true;
            } finally {
                this.actionLoading = false;
            }
        },
    };
}
</script>
@endsection
