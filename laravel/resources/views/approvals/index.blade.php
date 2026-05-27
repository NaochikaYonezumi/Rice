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
{{--
  グローバルショートカット:
    J / K           : 次 / 前の依頼 (selectedId が無ければ先頭を選択)
    Enter           : 選択中の依頼を「承認・送信」(可能な状態のみ)
    Shift+Enter     : 「予約して承認」モーダルを開く
    R               : 却下モーダルを開く
    Esc             : モーダル / 選択を閉じる
    Ctrl+Z          : 直近の取消可能アクション (予約取消 / 取り下げ) を 1 つ巻き戻す
  入力欄 (input/textarea) フォーカス時、または承認/予約モーダル表示中は無効化.
--}}
<div class="flex h-full bg-gray-50" x-data="approvalApp()" x-cloak
     @keydown.window="onGlobalKey($event)">

    {{-- 左: 承認・送信リスト (メール一覧と同じパネルスタイルに揃え) --}}
    <div class="flex flex-col flex-shrink-0 overflow-hidden bg-white border-r border-gray-200 relative z-20 shadow-sm"
         :style="'width:' + panelWidth + 'px'">
        {{-- ヘッダー (タイトル + 更新ボタン + 任意のフィルタ description) --}}
        <div class="shrink-0 px-4 py-3 border-b border-gray-200 bg-white flex flex-col gap-2 relative">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-extrabold text-gray-900 truncate">承認・送信</h2>
                <button @click="loadPending()"
                    class="h-9 w-9 inline-flex items-center justify-center rounded-lg text-gray-400 hover:text-blue-600 hover:bg-gray-50 transition-all"
                    :class="{ 'animate-spin text-blue-600': loading }"
                    title="一覧を更新">
                    <i class="fas fa-sync-alt text-sm"></i>
                </button>
            </div>
            {{-- 共有メール / 個人メール 切替タブ --}}
            <div class="flex items-center bg-white border border-gray-200 rounded-lg overflow-hidden">
                <button @click="setInboxScope('shared')"
                        :class="inboxScope === 'shared'
                            ? 'flex-1 px-3 py-1.5 text-[11px] font-bold bg-blue-600 text-white'
                            : 'flex-1 px-3 py-1.5 text-[11px] font-semibold bg-white text-gray-600 hover:bg-gray-50'">
                    <i class="fas fa-users mr-1"></i>共有メール
                </button>
                <button @click="setInboxScope('personal')"
                        :class="inboxScope === 'personal'
                            ? 'flex-1 px-3 py-1.5 text-[11px] font-bold bg-blue-600 text-white'
                            : 'flex-1 px-3 py-1.5 text-[11px] font-semibold bg-white text-gray-600 hover:bg-gray-50'">
                    <i class="fas fa-user mr-1"></i>個人メール
                </button>
            </div>
            <p class="text-[10px] text-gray-400 font-medium min-h-[14px] truncate" x-text="filterDescription"></p>
        </div>

        {{-- ステータスタブ (メール一覧と同じスタイル) --}}
        <div class="shrink-0 px-3 py-2 border-b border-gray-200 bg-gray-50 flex items-center gap-2">
            <div class="flex items-center gap-1 bg-gray-200/50 p-1 rounded-xl shadow-inner flex-1 overflow-hidden">
                <button @click="setStatusTab('pending')"
                        :class="statusTab === 'pending' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-800'"
                        class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all truncate">承認待ち</button>
                <button @click="setStatusTab('scheduled')"
                        :class="statusTab === 'scheduled' ? 'bg-white shadow text-indigo-600' : 'text-gray-500 hover:text-gray-800'"
                        class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all truncate">予約中</button>
                <button @click="setStatusTab('approved')"
                        :class="statusTab === 'approved' ? 'bg-white shadow text-green-600' : 'text-gray-500 hover:text-gray-800'"
                        class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all truncate">送信済</button>
                <button @click="setStatusTab('rejected')"
                        :class="statusTab === 'rejected' ? 'bg-white shadow text-red-600' : 'text-gray-500 hover:text-gray-800'"
                        class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all truncate">却下済</button>
            </div>
        </div>

        {{-- 対象者フィルタ (承認待ち時のみ。非表示時もスペースは確保) --}}
        <div class="shrink-0 px-3 py-2 border-b border-gray-200 bg-gray-50 flex items-center gap-2">
            <div class="flex items-center gap-1 bg-gray-200/50 p-1 rounded-xl shadow-inner flex-1 overflow-hidden">
                <button @click="setFilter('me')"
                        :class="filter === 'me' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-800'"
                        class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all truncate">あなた宛</button>
                <button @click="setFilter('mine')"
                        :class="filter === 'mine' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-800'"
                        class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all truncate">自分が依頼</button>
                <button @click="setFilter('all')"
                        :class="filter === 'all' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-800'"
                        class="flex-1 py-1.5 rounded-lg text-[10px] font-black transition-all truncate">すべて</button>
            </div>
        </div>

        {{-- リスト (メール一覧と同じフラット行スタイル) --}}
        <div class="flex-1 min-h-0 overflow-y-auto bg-white custom-scrollbar relative">
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
                        <span x-show="statusTab === 'pending' && filter === 'me'">あなたを承認者に指定した依頼はありません</span>
                        <span x-show="statusTab === 'pending' && filter === 'mine'">あなたが依頼した承認待ちはありません</span>
                        <span x-show="statusTab === 'pending' && filter === 'all'">承認待ちの依頼はありません</span>
                        <span x-show="statusTab === 'approved' && filter === 'me'">あなたが承認したものはありません</span>
                        <span x-show="statusTab === 'approved' && filter === 'mine'">あなたが依頼して承認されたものはありません</span>
                        <span x-show="statusTab === 'approved' && filter === 'all'">承認済の依頼はありません</span>
                        <span x-show="statusTab === 'rejected' && filter === 'me'">あなたが却下したものはありません</span>
                        <span x-show="statusTab === 'rejected' && filter === 'mine'">あなたが依頼して却下されたものはありません</span>
                        <span x-show="statusTab === 'rejected' && filter === 'all'">却下された依頼はありません</span>
                        <span x-show="statusTab === 'scheduled' && filter === 'me'">あなたが予約に関わるものはありません</span>
                        <span x-show="statusTab === 'scheduled' && filter === 'mine'">あなたが依頼した予約中の案件はありません</span>
                        <span x-show="statusTab === 'scheduled' && filter === 'all'">予約中の案件はありません</span>
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
                <div @click="selectEmail(p)"
                     :data-approval-row-id="p.id"
                     class="group/row w-full cursor-pointer border-b border-gray-100 hover:bg-blue-50 transition-all duration-200 relative"
                     :class="selectedId === p.id ? 'bg-blue-50 ring-1 ring-inset ring-blue-200' : ''">
                    <div class="px-5 py-2 flex flex-col justify-center gap-1">
                        {{-- 1 行目: ステータスバッジ + 依頼者 + 日時 --}}
                        <div class="flex items-center gap-1.5 min-w-0">
                            <template x-if="p.status === 'approved'">
                                <span class="text-[9px] font-black text-white bg-emerald-600 px-1.5 py-0.5 rounded shrink-0 inline-flex items-center gap-0.5" title="承認して送信済">
                                    <i class="fas fa-check"></i>送信済
                                </span>
                            </template>
                            <template x-if="p.status === 'pending'">
                                <span class="text-[9px] font-black text-white bg-amber-500 px-1.5 py-0.5 rounded shrink-0 inline-flex items-center gap-0.5" title="承認待ち">
                                    <i class="fas fa-hourglass-half"></i>承認待ち
                                </span>
                            </template>
                            <template x-if="p.status === 'rejected'">
                                <span class="text-[9px] font-black text-white bg-red-500 px-1.5 py-0.5 rounded shrink-0 inline-flex items-center gap-0.5" title="却下済">
                                    <i class="fas fa-times"></i>却下
                                </span>
                            </template>
                            <template x-if="p.status === 'scheduled'">
                                {{-- 予約バッジ: 白文字 indigo 背景は視認性が低かったので、薄背景 + 濃文字に変更.
                                     日時もインラインで表示し、ホバーしなくても予約時刻が読めるようにする. --}}
                                <span class="text-[10px] font-bold text-indigo-800 bg-indigo-50 border border-indigo-300 px-2 py-0.5 rounded shrink-0 inline-flex items-center gap-1"
                                      :title="'予約日時: ' + (p.scheduled_for_label || '')">
                                    <i class="fas fa-clock text-[9px]"></i>
                                    <span>予約 <span x-text="p.scheduled_for_label || '?'"></span></span>
                                </span>
                            </template>
                            <span class="text-[9px] font-black text-white bg-blue-600 px-1.5 py-0.5 rounded shrink-0"
                                x-text="p.reply_type_label"></span>
                            <span class="text-[12px] font-bold text-gray-900 truncate"
                                x-text="p.created_by_user_id === {{ auth()->id() }} ? 'あなたの依頼' : (p.created_by || '不明') + ' から'"></span>
                        </div>

                        {{-- 2 行目: 件名 (最大 2 行) --}}
                        <div class="text-[11px] text-gray-700 font-medium leading-snug break-words"
                             style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"
                             x-text="p.subject"></div>

                        {{-- 3 行目: メタデータ (日時 + 承認者 / 却下 / メモ など) --}}
                        <div class="flex items-center gap-1.5 flex-wrap min-h-[18px]">
                            <span class="text-[10px] text-gray-400 font-medium shrink-0 inline-flex items-center gap-1">
                                <i class="fas fa-clock text-[8px]"></i>
                                <span x-text="p.created_at"></span>
                            </span>
                            <template x-if="p.target_approver_name">
                                <span class="bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded text-[9px] font-black inline-flex items-center gap-1">
                                    <i class="fas fa-user-check text-[8px]"></i>
                                    <span class="truncate max-w-[100px]" x-text="p.target_approver_name"></span>
                                </span>
                            </template>
                            <template x-if="p.status === 'rejected' && p.rejected_by_name">
                                <span class="bg-red-50 text-red-700 border border-red-200 px-2 py-0.5 rounded text-[9px] font-black inline-flex items-center gap-1">
                                    <i class="fas fa-times-circle text-[8px]"></i>
                                    却下: <span class="truncate max-w-[80px]" x-text="p.rejected_by_name"></span>
                                </span>
                            </template>
                            <template x-if="p.status === 'approved' && p.approved_by_name && !p.is_self_sent">
                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded text-[9px] font-black inline-flex items-center gap-1" title="承認した人">
                                    <i class="fas fa-check-double text-[8px]"></i>
                                    <span class="truncate max-w-[100px]" x-text="p.approved_by_name"></span>
                                </span>
                            </template>
                            <template x-if="p.status === 'approved' && p.is_self_sent">
                                <span class="bg-sky-50 text-sky-700 border border-sky-200 px-2 py-0.5 rounded text-[9px] font-black inline-flex items-center gap-1" title="作成者本人が承認フローを経由せず直接送信">
                                    <i class="fas fa-paper-plane text-[8px]"></i>
                                    自己送信
                                </span>
                            </template>
                            <template x-if="p.memo">
                                <span class="bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded text-[9px] font-black inline-flex items-center gap-1" :title="p.memo">
                                    <i class="fas fa-comment-dots text-[8px]"></i> メモ
                                </span>
                            </template>
                        </div>
                    </div>
                    {{-- 選択中の左ライン --}}
                    <div x-show="selectedId === p.id" class="absolute left-0 top-0 w-1.5 h-full bg-blue-600"></div>
                </div>
            </template>
        </div>
        {{-- ドラッグリサイズハンドル (メール一覧と同じ) --}}
        <div class="absolute top-0 right-0 w-1 h-full cursor-col-resize hover:bg-blue-500 z-50"
             @mousedown.prevent="startResizePanel($event)"></div>
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
                    {{-- 前/次の依頼ナビゲーション (メール一覧と同じパターン) --}}
                    <div class="flex items-center gap-0.5 shrink-0 border-r border-gray-100 pr-3 mt-1">
                        <button @click="goToPrevEmail()" title="前の依頼"
                                :disabled="!hasPrevEmail"
                                class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:text-blue-600 hover:bg-gray-50 transition-all disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent">
                            <i class="fas fa-chevron-up text-xs"></i>
                        </button>
                        <button @click="goToNextEmail()" title="次の依頼"
                                :disabled="!hasNextEmail"
                                class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:text-blue-600 hover:bg-gray-50 transition-all disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                    </div>
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
                            {{-- 予約送信中バッジ (status=scheduled の時のみ表示) --}}
                            <template x-if="selectedEmail.status === 'scheduled' && selectedEmail.scheduled_for_label">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    <i class="fas fa-clock"></i> 予約: <span x-text="selectedEmail.scheduled_for_label"></span>
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
                                {{-- 予約して承認 (承認者が日時を指定して予約化) --}}
                                <button @click="openScheduleModal(selectedEmail)"
                                    :disabled="actionLoading"
                                    title="承認後、指定日時まで送信を待機する"
                                    class="bg-white text-indigo-700 border border-indigo-300 text-xs font-bold px-3 py-2 rounded-lg hover:bg-indigo-50 transition-all disabled:opacity-50 inline-flex items-center gap-1.5">
                                    <i class="fas fa-clock"></i>予約して承認
                                </button>
                                {{-- 承認・今すぐ送信 (デフォルト) --}}
                                <button @click="approve(selectedEmail, 'immediate')"
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
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-times-circle text-red-600"></i>
                                <p class="text-xs font-bold text-red-700">
                                    却下: <span x-text="selectedEmail.rejected_by_name || '不明'"></span>
                                    <span class="text-red-500 ml-2 font-medium" x-text="selectedEmail.rejected_at"></span>
                                </p>
                            </div>
                            {{-- 履歴から削除ボタン (依頼者本人 or 却下を実行した承認者本人のみ) --}}
                            <template x-if="canDeleteRejected(selectedEmail)">
                                <button @click="deleteRejected(selectedEmail)"
                                        :disabled="actionLoading"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-red-700 bg-white border border-red-300 px-3 py-1.5 rounded-lg hover:bg-red-600 hover:text-white hover:border-red-600 transition-all disabled:opacity-50"
                                        title="却下履歴から完全に削除する">
                                    <i class="fas fa-trash"></i>履歴から削除
                                </button>
                            </template>
                        </div>
                        <p x-show="selectedEmail.rejection_reason" class="text-sm text-red-900 whitespace-pre-wrap leading-relaxed"
                           x-text="selectedEmail.rejection_reason"></p>
                        <p x-show="!selectedEmail.rejection_reason" class="text-xs text-red-500 italic">却下理由は入力されていません</p>
                        <p class="text-[10px] text-red-600 mt-2"><i class="fas fa-info-circle mr-1"></i>この内容は依頼者の「下書き」に再生成されています。依頼者は <code class="bg-white px-1 rounded">/drafts</code> から再編集可能です。</p>
                    </div>
                </template>

                {{-- 承認情報 (approved タブで詳細表示).
                     自己送信 (is_self_sent) と承認経由を文言で区別する. --}}
                <template x-if="statusTab === 'approved' && selectedEmail">
                    <div class="mx-8 mt-4 shrink-0 bg-green-50 border border-green-200 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-1">
                            <i class="fas fa-check-circle text-green-600"></i>
                            <p class="text-xs font-bold text-green-700">
                                <template x-if="selectedEmail.is_self_sent">
                                    <span>
                                        自己送信: <span x-text="selectedEmail.approved_by_name || selectedEmail.created_by || '本人'"></span>
                                        <span class="text-green-500 ml-2 font-medium" x-text="selectedEmail.approved_at"></span>
                                    </span>
                                </template>
                                <template x-if="!selectedEmail.is_self_sent">
                                    <span>
                                        承認: <span x-text="selectedEmail.approved_by_name || '不明'"></span>
                                        <span class="text-green-500 ml-2 font-medium" x-text="selectedEmail.approved_at"></span>
                                    </span>
                                </template>
                            </p>
                        </div>
                        <template x-if="selectedEmail.is_self_sent">
                            <p class="text-[10px] text-green-700 mt-1"><i class="fas fa-paper-plane mr-1"></i>このメールは作成者本人が「今すぐ送信」を選択し、承認フローを経由せず SMTP 経由で送信されています。</p>
                        </template>
                        <template x-if="!selectedEmail.is_self_sent">
                            <p class="text-[10px] text-green-700 mt-1"><i class="fas fa-paper-plane mr-1"></i>このメールは承認後、自動的に SMTP 経由で送信されています。</p>
                        </template>
                    </div>
                </template>

                {{-- 予約中バナー (scheduled タブで詳細表示).
                     - 取消できるのは: 作成者本人 / 予約に切替えた承認者 / 管理者 (backend で同条件をチェック).
                     - 取消すると status=draft に戻る → /drafts (作成者) や承認待ち再依頼が可能.
                --}}
                <template x-if="statusTab === 'scheduled' && selectedEmail">
                    <div class="mx-8 mt-4 shrink-0 bg-indigo-50 border border-indigo-200 rounded-xl p-4">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <i class="fas fa-clock text-indigo-600 shrink-0"></i>
                                <p class="text-xs font-bold text-indigo-700 truncate">
                                    予約送信:
                                    <span class="text-indigo-900 ml-1" x-text="selectedEmail.scheduled_for_label || '不明'"></span>
                                    に自動送信予定
                                    <template x-if="selectedEmail.is_self_sent">
                                        <span class="text-indigo-500 ml-2 font-medium text-[10px]">(作成者の予約)</span>
                                    </template>
                                    <template x-if="!selectedEmail.is_self_sent && selectedEmail.approved_by_name">
                                        <span class="text-indigo-500 ml-2 font-medium text-[10px]">
                                            (承認者: <span x-text="selectedEmail.approved_by_name"></span> が予約)
                                        </span>
                                    </template>
                                </p>
                            </div>
                            <template x-if="canCancelSchedule(selectedEmail)">
                                <button @click="unschedule(selectedEmail)"
                                        :disabled="actionLoading"
                                        class="shrink-0 inline-flex items-center gap-1 text-[11px] font-bold text-indigo-700 bg-white border border-indigo-300 px-3 py-1.5 rounded-lg hover:bg-indigo-100 transition-all disabled:opacity-50"
                                        title="予約を取り消して下書きに戻す">
                                    <i class="fas" :class="actionLoading ? 'fa-spinner fa-spin' : 'fa-ban'"></i>
                                    予約取消
                                </button>
                            </template>
                        </div>
                        <p class="text-[10px] text-indigo-600 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            指定日時になると自動的に SMTP 経由で送信されます。それまでは「予約取消」で下書きに戻して編集できます。
                        </p>
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
                                <template x-for="att in selectedEmail.attachments" :key="att.index">
                                    <a :href="att.download_url"
                                       :download="att.filename"
                                       class="inline-flex items-center gap-2 text-xs font-semibold bg-gray-50 hover:bg-blue-50 border border-gray-200 hover:border-blue-300 text-gray-700 hover:text-blue-700 px-3 py-1.5 rounded-lg transition-colors"
                                       :title="'ダウンロード: ' + att.filename">
                                        <i class="fas" :class="attachmentIcon(att.mime_type, att.filename)"></i>
                                        <span x-text="att.filename"></span>
                                        <span class="text-[10px] text-gray-400" x-text="'(' + att.size + ')'"></span>
                                        <i class="fas fa-download text-[10px] opacity-60"></i>
                                    </a>
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

    {{-- 予約承認モーダル (承認者が送信日時を指定する) --}}
    <template x-if="scheduleModalOpen">
        <div class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-md p-4" @click.self="closeScheduleModal()">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="bg-indigo-50 px-6 py-5 border-b border-indigo-100 flex items-center gap-3">
                    <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center text-indigo-600">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-indigo-900">予約して承認</h3>
                        <p class="text-xs text-indigo-600 mt-0.5">指定日時に自動で SMTP 経由で送信されます</p>
                    </div>
                </div>
                <div class="px-6 py-5 space-y-3">
                    <div class="text-xs text-gray-700 bg-gray-50 px-3 py-2 rounded-lg border border-gray-200">
                        <span class="font-bold">対象:</span>
                        <span class="ml-1" x-text="schedulingEmail?.subject"></span>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">送信日時 <span class="text-red-600">*</span></label>
                        <input type="datetime-local" x-model="scheduleFor" :min="scheduleMinValue"
                               class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300">
                        <p class="text-[10px] text-gray-400 mt-1">現在以降の日時を指定してください。承認後、指定日時まで送信を待機します。</p>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex gap-2 justify-end">
                    <button @click="closeScheduleModal()"
                            class="bg-white border border-gray-200 text-gray-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gray-100 transition-all">
                        キャンセル
                    </button>
                    <button @click="confirmSchedule()" :disabled="actionLoading || !scheduleFor"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                            style="background-color:#4f46e5;color:#ffffff;">
                        <i class="fas fa-clock"></i>
                        <span x-text="actionLoading ? '処理中...' : '予約して承認'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function approvalApp() {
    return {
        // MIME / 拡張子から FontAwesome のアイコンクラスを返す
        attachmentIcon(mime, filename) {
            const m = (mime || '').toLowerCase();
            const ext = (filename || '').split('.').pop().toLowerCase();
            if (m.startsWith('image/')) return 'fa-file-image';
            if (m === 'application/pdf' || ext === 'pdf') return 'fa-file-pdf';
            if (m.includes('word') || ['doc','docx'].includes(ext)) return 'fa-file-word';
            if (m.includes('excel') || m.includes('spreadsheet') || ['xls','xlsx','csv'].includes(ext)) return 'fa-file-excel';
            if (m.includes('powerpoint') || m.includes('presentation') || ['ppt','pptx'].includes(ext)) return 'fa-file-powerpoint';
            if (m.startsWith('text/') || ['txt','md','log'].includes(ext)) return 'fa-file-alt';
            if (m.includes('zip') || m.includes('compressed') || ['zip','tar','gz','rar','7z'].includes(ext)) return 'fa-file-archive';
            return 'fa-file';
        },
        loading: false,
        allEmails: [],
        selectedId: null,
        selectedEmail: null,
        actionLoading: false,
        // 共有メール / 個人メール 切替 (メール一覧と共通 localStorage キー)
        inboxScope: (() => {
            const v = localStorage.getItem('inboxScope');
            return (v === 'personal' || v === 'shared') ? v : 'shared';
        })(),
        setInboxScope(scope) {
            if (scope !== 'shared' && scope !== 'personal') return;
            if (this.inboxScope === scope) return;
            this.inboxScope = scope;
            try { localStorage.setItem('inboxScope', scope); } catch (_) {}
            this.loadPending();
        },
        actionMessage: '',
        actionError: false,
        statusTab: 'pending',  // 'pending' / 'approved' / 'rejected'
        filter: 'me',          // 'me' / 'mine' / 'all' (pending タブ時のみ)

        // 左パネル幅 (ドラッグで調整可能、localStorage に永続化)
        panelWidth: parseInt(localStorage.getItem('approvalsPanelWidth')) || 360,
        rejectModalOpen: false,
        rejectingEmail: null,
        rejectReason: '',
        // 予約承認モーダル
        scheduleModalOpen: false,
        schedulingEmail: null,
        scheduleFor: '',  // datetime-local 値
        get scheduleMinValue() {
            const d = new Date(Date.now() + 60 * 1000);
            const pad = n => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        },

        get filterDescription() {
            // タブ × フィルタの 9 通りを正しく言い分ける。
            // 「あなた宛」の意味はタブによって変わる:
            //   - 承認待ち : 自分が承認者として指定された依頼
            //   - 送信済   : 自分が実際に承認したもの
            //   - 却下済   : 自分が実際に却下したもの
            // 「自分が依頼」は全タブ共通で「他のユーザに承認を依頼したもの」。
            if (this.statusTab === 'pending') {
                if (this.filter === 'me')   return '他のユーザーがあなたを承認者に指定した依頼';
                if (this.filter === 'mine') return 'あなたが他のユーザーに承認を依頼した待機中の案件';
                return '全ての承認待ち';
            }
            if (this.statusTab === 'approved') {
                if (this.filter === 'me')   return 'あなたが承認して送信した履歴';
                if (this.filter === 'mine') return 'あなたが他のユーザーに依頼し、承認・送信された履歴';
                return '全ての送信済 (承認済) 履歴';
            }
            // rejected
            if (this.filter === 'me')   return 'あなたが却下した履歴';
            if (this.filter === 'mine') return 'あなたが他のユーザーに依頼し、却下された履歴';
            return '全ての却下済履歴';
        },
        get emptyIconClass() {
            if (this.statusTab === 'approved') return 'fas fa-check-circle';
            if (this.statusTab === 'rejected') return 'fas fa-times-circle';
            if (this.statusTab === 'scheduled') return 'fas fa-clock';
            return 'fas fa-inbox';
        },

        async init() {
            // URL クエリパラメータで初期タブを決定 (?tab=approved|rejected|pending、または ?view=sent)
            // ?view=sent は「送信済一覧」メニューからの旧 URL の互換性のために残し、approved タブと同義として扱う。
            try {
                const params = new URLSearchParams(window.location.search);
                if (params.get('view') === 'sent') {
                    this.statusTab = 'approved';
                } else {
                    const tab = params.get('tab');
                    if (tab === 'approved' || tab === 'rejected' || tab === 'pending' || tab === 'scheduled') {
                        this.statusTab = tab;
                    }
                }
            } catch (_) {}
            await this.loadPending();
        },

        // 左パネルのドラッグリサイズ (300〜700px)
        startResizePanel(e) {
            const startX = e.clientX, startW = this.panelWidth;
            const onMove = (me) => {
                this.panelWidth = Math.max(300, Math.min(700, startW + (me.clientX - startX)));
            };
            const onUp = () => {
                localStorage.setItem('approvalsPanelWidth', this.panelWidth);
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        setStatusTab(tab) {
            if (this.statusTab === tab) return;
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
                // 旧実装は statusTab === 'pending' の時しか for_me / mine を送っていなかった。
                // そのため 送信済 / 却下済 タブでは あなた宛 / 自分が依頼 / すべて を切り替えても
                // 同じ全件リストが返ってきていた。フィルタは常に効かせる。
                if (this.filter === 'me')   params.set('for_me', '1');
                if (this.filter === 'mine') params.set('mine',   '1');
                // 共有 / 個人 切替に連動
                params.set('scope', this.inboxScope || 'shared');
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

        // ============= グローバルキーボードショートカット =============
        // メール画面と同じ感覚で承認画面でも J/K ナビと Ctrl+Z 巻き戻しが使えるようにする.
        // 入力欄やモーダル表示中は無効化して、ユーザの文字入力を奪わない.
        undoStack: [],
        maxUndoStack: 12,
        _pushUndoApproval(label, undoFn) {
            if (typeof undoFn !== 'function') return;
            this.undoStack.push({ label, undoFn, ts: Date.now() });
            if (this.undoStack.length > this.maxUndoStack) this.undoStack.shift();
        },
        async undoLastApproval() {
            const action = this.undoStack.pop();
            if (!action) { this.actionMessage = '元に戻せる操作がありません'; this.actionError = false; return; }
            try {
                await action.undoFn();
                this.actionMessage = '元に戻しました: ' + action.label;
                this.actionError = false;
                this.loadPending();
            } catch (e) {
                this.actionMessage = '戻せませんでした: ' + (e?.message || e);
                this.actionError = true;
            }
        },
        onGlobalKey(e) {
            // 入力欄フォーカス中はネイティブ動作を尊重 (テキスト編集 / IME 入力など)
            const tag = (e.target?.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
            if (e.target?.isContentEditable) return;
            // モーダル表示中は J/K / R / Enter を奪わない (モーダル内の UI に任せる)
            if (this.rejectModalOpen || this.scheduleModalOpen) {
                if (e.key === 'Escape') {
                    if (this.rejectModalOpen) this.closeRejectModal?.();
                    if (this.scheduleModalOpen) this.closeScheduleModal?.();
                    e.preventDefault();
                }
                return;
            }
            const ctrlOrCmd = e.ctrlKey || e.metaKey;
            // Ctrl+Z (undo)
            if (ctrlOrCmd && (e.key === 'z' || e.key === 'Z') && !e.shiftKey) {
                e.preventDefault();
                this.undoLastApproval();
                return;
            }
            if (ctrlOrCmd || e.altKey) return;  // それ以外の修飾キー付きはブラウザ既定に譲る

            switch (e.key) {
                case 'j': case 'J':
                    e.preventDefault();
                    if (!this.selectedId && this.allEmails.length > 0) {
                        this.selectEmail(this.allEmails[0]);
                    } else {
                        this.goToNextEmail();
                    }
                    this._scrollSelectedRowIntoView();
                    break;
                case 'k': case 'K':
                    e.preventDefault();
                    if (!this.selectedId && this.allEmails.length > 0) {
                        this.selectEmail(this.allEmails[this.allEmails.length - 1]);
                    } else {
                        this.goToPrevEmail();
                    }
                    this._scrollSelectedRowIntoView();
                    break;
                case 'Enter':
                    // 承認可能な依頼のみ反応
                    if (this.statusTab === 'pending' && this.selectedEmail
                        && this.selectedEmail.created_by_user_id !== {{ auth()->id() }}
                        && (!this.selectedEmail.target_approver_user_id || this.selectedEmail.target_approver_user_id === {{ auth()->id() }})) {
                        e.preventDefault();
                        if (e.shiftKey) {
                            this.openScheduleModal?.(this.selectedEmail);
                        } else {
                            this.approve(this.selectedEmail, 'immediate');
                        }
                    }
                    break;
                case 'r': case 'R':
                    if (this.statusTab === 'pending' && this.selectedEmail
                        && this.selectedEmail.created_by_user_id !== {{ auth()->id() }}) {
                        e.preventDefault();
                        this.openRejectModal?.(this.selectedEmail);
                    }
                    break;
                case 'Escape':
                    if (this.selectedId) {
                        e.preventDefault();
                        this.selectedId = null;
                        this.selectedEmail = null;
                    }
                    break;
                case '?':
                    // 既存のグローバルヘルプを開く (layouts/app の関数を呼ぶ)
                    e.preventDefault();
                    if (typeof window.riceShowKeyboardShortcuts === 'function') window.riceShowKeyboardShortcuts();
                    break;
            }
        },
        _scrollSelectedRowIntoView() {
            this.$nextTick(() => {
                const el = document.querySelector('[data-approval-row-id="' + this.selectedId + '"]');
                if (el && typeof el.scrollIntoView === 'function') {
                    el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            });
        },

        // ============= 前/次の依頼ナビゲーション (メール一覧と同じパターン) =============
        get _currentEmailIndex() {
            if (!this.selectedId) return -1;
            return this.allEmails.findIndex(p => p.id === this.selectedId);
        },
        get hasPrevEmail() {
            return this._currentEmailIndex > 0;
        },
        get hasNextEmail() {
            const idx = this._currentEmailIndex;
            return idx !== -1 && idx < this.allEmails.length - 1;
        },
        goToPrevEmail() {
            const idx = this._currentEmailIndex;
            if (idx > 0) this.selectEmail(this.allEmails[idx - 1]);
        },
        goToNextEmail() {
            const idx = this._currentEmailIndex;
            if (idx !== -1 && idx < this.allEmails.length - 1) {
                this.selectEmail(this.allEmails[idx + 1]);
            }
        },

        // 承認・送信 (デフォルトは immediate). 予約承認は openScheduleModal()→confirmSchedule() 経由.
        async approve(p, mode, scheduledFor) {
            mode = mode || 'immediate';
            const isScheduled = mode === 'scheduled' && scheduledFor;
            const scheduledLabel = isScheduled ? this._fmtDateLocal(scheduledFor) : '';
            const msg = isScheduled
                ? `${scheduledLabel} に送信する予約として承認しますか？\n(その時刻になると自動で送信されます)`
                : 'このメールを承認し、今すぐ送信しますか？';
            if (!confirm(msg)) return;
            this.actionLoading = true;
            this.actionMessage = '';
            this.actionError = false;
            try {
                const fd = new FormData();
                fd.append('mode', mode);
                if (isScheduled) fd.append('scheduled_for', scheduledFor);
                const res = await fetch(`/pending-emails/${p.id}/approve`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: fd,
                });
                const data = await res.json();
                if (!res.ok || data.status === 'error') {
                    this.actionMessage = data.message || '送信に失敗しました';
                    this.actionError = true;
                } else {
                    this.actionMessage = isScheduled
                        ? `承認しました。${scheduledLabel} に自動送信されます。`
                        : '承認しました。メールを送信しました。';
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

        // datetime-local 文字列 (YYYY-MM-DDTHH:MM) を "M/D HH:MM" にフォーマット
        _fmtDateLocal(s) {
            if (!s) return '';
            try {
                const d = new Date(s);
                if (isNaN(d.getTime())) return s;
                return d.getMonth()+1 + '/' + d.getDate() + ' ' +
                       String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
            } catch (_) { return s; }
        },

        // 予約承認モーダルを開く (datetime-local の初期値は「現在 + 5 分」)
        openScheduleModal(p) {
            this.schedulingEmail = p;
            const d = new Date(Date.now() + 5 * 60 * 1000);
            const pad = n => String(n).padStart(2, '0');
            this.scheduleFor = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
            this.scheduleModalOpen = true;
        },
        closeScheduleModal() {
            this.scheduleModalOpen = false;
            this.schedulingEmail = null;
            this.scheduleFor = '';
        },
        async confirmSchedule() {
            if (!this.schedulingEmail) return;
            if (!this.scheduleFor) { this.actionMessage = '送信日時を指定してください'; this.actionError = true; return; }
            const when = new Date(this.scheduleFor);
            if (isNaN(when.getTime()) || when.getTime() <= Date.now()) {
                this.actionMessage = '送信日時は現在以降を指定してください';
                this.actionError = true;
                return;
            }
            const p = this.schedulingEmail;
            const sched = this.scheduleFor;
            this.closeScheduleModal();
            await this.approve(p, 'scheduled', sched);
        },

        // 「予約取消」できるか判定 (UI 表示用. backend 側でも同条件を再チェック).
        // - 作成者本人
        // - 予約に切替えた承認者 (approved_by_user_id)
        // - admin (フロントからは判別困難なので backend に任せ、UI 上はとりあえず表示してエラー時にトースト)
        canCancelSchedule(p) {
            if (!p) return false;
            const myId = {{ auth()->id() }};
            return p.created_by_user_id === myId || p.approved_by_user_id === myId;
        },

        async unschedule(p) {
            if (!p) return;
            if (!confirm(`予約送信 (${p.scheduled_for_label || ''}) を取り消し、下書きに戻しますか？`)) return;
            this.actionLoading = true;
            this.actionMessage = '';
            this.actionError = false;
            try {
                const res = await fetch(`/pending-emails/${p.id}/unschedule`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.status === 'error') {
                    this.actionMessage = data.message || '予約取消に失敗しました';
                    this.actionError = true;
                } else {
                    this.actionMessage = '予約を取り消し、下書きに戻しました。';
                    this.actionError = false;
                    setTimeout(() => {
                        this.selectedId = null;
                        this.selectedEmail = null;
                        this.actionMessage = '';
                        this.loadPending();
                    }, 1200);
                }
            } catch (e) {
                this.actionMessage = 'エラー: ' + (e.message || '');
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

        // 却下済を「履歴から削除」する権限判定: 依頼者本人 or 却下した本人だけ.
        // バックエンドにも同じ判定があるが、UI 側でも事前に隠して誤操作を防ぐ.
        canDeleteRejected(p) {
            if (!p || p.status !== 'rejected') return false;
            const myId = {{ auth()->id() ?? 'null' }};
            if (myId === null) return false;
            if (p.created_by_user_id === myId) return true;
            if (p.rejected_by_user_id && p.rejected_by_user_id === myId) return true;
            return false;
        },

        async deleteRejected(p) {
            if (!p) return;
            if (!confirm('この却下履歴を完全に削除しますか?\n\n注意: 削除後は復元できません。下書きとして再生成された分は /drafts に残ります。')) return;
            this.actionLoading = true;
            this.actionMessage = '';
            this.actionError = false;
            try {
                const res = await fetch(`/pending-emails/${p.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.status === 'error') {
                    this.actionMessage = data.message || '削除に失敗しました';
                    this.actionError = true;
                } else {
                    this.actionMessage = '却下履歴を削除しました';
                    this.actionError = false;
                    setTimeout(() => {
                        this.selectedId = null;
                        this.selectedEmail = null;
                        this.actionMessage = '';
                        this.loadPending();
                    }, 1200);
                }
            } catch (e) {
                this.actionMessage = 'エラー: ' + e.message;
                this.actionError = true;
            } finally {
                this.actionLoading = false;
            }
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
