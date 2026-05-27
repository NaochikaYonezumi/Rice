@extends('layouts.app')
@section('title', '下書き / 予約送信 一覧 - Rice')

@section('css')
<style>
    .content-header { display: none !important; }
    .content, .content > .container-fluid {
        padding: 0 !important;
        max-width: 100% !important;
        height: calc(100vh - 3.5rem);
        overflow-y: auto;
        background: #f9fafb;
    }
    [x-cloak] { display: none !important; }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>
@endsection

@section('content')
<div class="flex flex-col h-full bg-white" x-data="draftApp()" x-cloak>

    {{-- ヘッダーバー (メール一覧のステータスタブ部分に相当) --}}
    <div class="shrink-0 px-4 py-3 border-b border-gray-200 bg-gray-50 flex items-center gap-3">
        {{-- 共有メール / 個人メール 切替タブ --}}
        <div class="flex items-center bg-white border border-gray-200 rounded-lg overflow-hidden shrink-0">
            <button @click="setInboxScope('shared')"
                    :class="inboxScope === 'shared'
                        ? 'px-3 py-1.5 text-[11px] font-bold bg-blue-600 text-white'
                        : 'px-3 py-1.5 text-[11px] font-semibold bg-white text-gray-600 hover:bg-gray-50'">
                <i class="fas fa-users mr-1"></i>共有メール
            </button>
            <button @click="setInboxScope('personal')"
                    :class="inboxScope === 'personal'
                        ? 'px-3 py-1.5 text-[11px] font-bold bg-blue-600 text-white'
                        : 'px-3 py-1.5 text-[11px] font-semibold bg-white text-gray-600 hover:bg-gray-50'">
                <i class="fas fa-user mr-1"></i>個人メール
            </button>
        </div>
        <div class="flex items-center gap-2 min-w-0">
            <h1 class="text-[13px] font-extrabold text-gray-800 truncate">下書き / 予約送信</h1>
            <span class="text-[11px] text-gray-500 font-medium">合計 <span class="font-bold text-gray-700" x-text="drafts.length"></span> 件</span>
            <span x-show="scheduledCount > 0"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 text-[10px] font-black uppercase tracking-tighter shadow-sm">
                <i class="fas fa-clock text-[9px]"></i>
                予約中 <span x-text="scheduledCount"></span>
            </span>
            <span x-show="rejectedCount > 0"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-200 text-[10px] font-black uppercase tracking-tighter shadow-sm">
                <i class="fas fa-times-circle text-[9px]"></i>
                却下 <span x-text="rejectedCount"></span>
            </span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <button @click="load()" class="p-2 text-gray-400 hover:text-blue-600 transition-colors" title="更新">
                <i class="fas fa-sync-alt" :class="loading ? 'animate-spin text-blue-600' : ''"></i>
            </button>
        </div>
    </div>

    {{-- リスト本体 --}}
    <div class="flex-1 min-h-0 overflow-y-auto bg-white custom-scrollbar">

        {{-- ローディング --}}
        <template x-if="loading">
            <div class="flex flex-col items-center justify-center py-20 text-gray-300">
                <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                <p class="text-[11px] font-bold tracking-widest uppercase">Loading...</p>
            </div>
        </template>

        {{-- 空状態 --}}
        <template x-if="!loading && drafts.length === 0">
            <div class="flex flex-col items-center justify-center py-24 text-gray-400 px-6">
                <div class="w-16 h-16 rounded-2xl bg-gray-50 flex items-center justify-center text-gray-300 mb-4 shadow-inner">
                    <i class="fas fa-inbox fa-lg"></i>
                </div>
                <p class="text-sm font-bold text-gray-600">保存済みの下書き / 予約送信はありません</p>
                <p class="text-xs text-gray-400 mt-1 text-center max-w-sm leading-relaxed">受信トレイから「下書き保存」を押すか、却下されたメール・予約送信メールが自動的にここに集まります。</p>
            </div>
        </template>

        {{-- 行リスト (メール一覧のスタイルに合わせる) --}}
        <template x-for="d in drafts" :key="d.id">
            <div class="group/row w-full cursor-pointer border-b border-gray-100 hover:bg-blue-50 transition-all duration-200 relative"
                 :class="d.is_rejected ? 'bg-red-50/30' : (d.is_scheduled ? 'bg-indigo-50/30' : '')"
                 @click="openEdit(d)">

                {{-- 却下バー (左端の赤い縦線で簡潔に表現) --}}
                <div x-show="d.is_rejected" class="absolute left-0 top-0 w-1 h-full bg-red-500"></div>
                {{-- 予約中バー (左端のインディゴ縦線) --}}
                <div x-show="d.is_scheduled" class="absolute left-0 top-0 w-1 h-full bg-indigo-500"></div>

                {{-- 行コンテンツ --}}
                <div class="px-5 py-2 flex flex-col justify-center gap-1">

                    {{-- 1段目: 種別バッジ + 宛先 --}}
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-tighter shrink-0 border"
                              :class="d.is_rejected
                                ? 'bg-red-100 text-red-700 border-red-200'
                                : 'bg-gray-100 text-gray-700 border-gray-200'"
                              x-text="d.reply_type_label"></span>
                        <span x-show="d.is_rejected"
                              class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-tighter shrink-0 bg-red-100 text-red-700 border border-red-200 inline-flex items-center gap-1">
                            <i class="fas fa-times-circle text-[9px]"></i>却下
                        </span>
                        <span x-show="d.is_scheduled"
                              class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-tighter shrink-0 bg-indigo-100 text-indigo-700 border border-indigo-200 inline-flex items-center gap-1"
                              :title="'予約日時: ' + (d.scheduled_for_label || '')">
                            <i class="fas fa-clock text-[9px]"></i>予約 <span x-text="d.scheduled_for_label"></span>
                        </span>
                        <span class="text-[13px] font-bold text-gray-900 truncate"
                              x-text="(d.to_address || '(宛先未指定)')"
                              :title="'To: ' + (d.to_address || '(未指定)')"></span>
                    </div>

                    {{-- 2段目: 件名 (2行クランプ) --}}
                    <div class="text-[13px] text-gray-700 font-medium leading-snug break-words"
                         style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"
                         x-text="d.subject || '(無題)'"></div>

                    {{-- 3段目: 更新日 + メタ --}}
                    <div class="flex items-center gap-1.5 flex-wrap min-h-[18px]">
                        <span class="text-[11px] text-gray-400 font-medium shrink-0 inline-flex items-center gap-1">
                            <i class="fas fa-clock text-[9px]"></i>
                            <span x-text="d.updated_at || d.created_at"></span>
                        </span>

                        <template x-if="d.attachment_count > 0">
                            <span class="px-2 py-0.5 rounded text-[10px] font-black text-blue-600 bg-blue-50 border border-blue-100 inline-flex items-center gap-1">
                                <i class="fas fa-paperclip text-[9px]"></i>
                                <span x-text="d.attachment_count"></span>
                            </span>
                        </template>

                        <template x-if="d.thread_subject">
                            <span class="text-[10px] text-gray-400 truncate max-w-[260px]"
                                  :title="d.thread_subject">
                                → <span x-text="d.thread_subject"></span>
                            </span>
                        </template>

                        <template x-if="d.is_rejected && d.rejected_by_name">
                            <span class="text-[10px] text-red-500 font-bold">
                                却下: <span x-text="d.rejected_by_name"></span>
                            </span>
                        </template>
                    </div>

                    {{-- 却下理由 (折りたたまずに簡潔に1行) --}}
                    <template x-if="d.is_rejected && d.rejection_reason">
                        <p class="text-[11px] text-red-700 bg-red-50 border border-red-100 rounded-md px-2 py-1 leading-relaxed mt-0.5"
                           style="display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;"
                           :title="d.rejection_reason">
                            <i class="fas fa-comment-alt-times text-[9px] mr-1"></i><span x-text="d.rejection_reason"></span>
                        </p>
                    </template>
                </div>

                {{-- ホバー時のアクションボタン (メール一覧の削除ボタン位置に合わせる).
                     - draft : [編集][承認依頼][削除]
                     - scheduled (予約中) : [編集][予約取消] のみ.
                       予約は「ユーザが個別に送信」なので承認依頼は出来ない.
                       削除も「予約取消 → 下書きへ」を強制し、その後 draft 状態でなら削除可能.
                --}}
                <div class="absolute right-3 top-1/2 -translate-y-1/2 z-10 flex items-center gap-1 opacity-0 group-hover/row:opacity-100 transition-all">
                    <a :href="`/drafts/${d.id}/edit`" target="_blank" @click.stop
                       class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50 shadow-sm"
                       title="編集">
                        <i class="fas fa-edit text-xs"></i>
                    </a>
                    <template x-if="!d.is_scheduled">
                        <button @click.stop="submitDraft(d)" :disabled="submitting === d.id"
                                class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-blue-600 text-white hover:bg-blue-700 shadow-sm disabled:opacity-50"
                                :title="d.is_rejected ? '再依頼' : '承認依頼'">
                            <i class="fas" :class="submitting === d.id ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                        </button>
                    </template>
                    <template x-if="d.is_scheduled">
                        <button @click.stop="unscheduleDraft(d)" :disabled="unscheduling === d.id"
                                class="px-3 h-8 inline-flex items-center justify-center gap-1 rounded-lg bg-white border border-indigo-300 text-indigo-700 hover:bg-indigo-50 text-[11px] font-bold shadow-sm disabled:opacity-50"
                                title="予約を取り消して下書きに戻す">
                            <i class="fas" :class="unscheduling === d.id ? 'fa-spinner fa-spin' : 'fa-ban'"></i>
                            予約取消
                        </button>
                    </template>
                    <template x-if="!d.is_scheduled">
                        <button @click.stop="deleteDraft(d)"
                                class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:text-red-500 hover:border-red-200 hover:bg-red-50 shadow-sm"
                                title="削除">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- トースト --}}
    <div x-show="toast" x-transition
         class="fixed bottom-6 right-6 z-50 max-w-md">
        <div class="px-4 py-3 rounded-lg shadow-2xl text-sm font-semibold flex items-center gap-2"
             :class="toastType === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'">
            <i class="fas" :class="toastType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'"></i>
            <span x-text="toast"></span>
        </div>
    </div>
</div>

<script>
function draftApp() {
    return {
        drafts: [],
        loading: true,
        submitting: null,
        unscheduling: null,
        toast: null,
        toastType: 'success',
        // 共有メール / 個人メール 切替 (メール一覧と同じ localStorage キーを共用)
        inboxScope: (() => {
            const v = localStorage.getItem('inboxScope');
            return (v === 'personal' || v === 'shared') ? v : 'shared';
        })(),

        async init() {
            // compose-window が「下書き保存して閉じる」した場合、バックエンドは
            // PendingEmail を新 ID で作り直して旧 ID を削除する。
            // この一覧画面は postMessage を受けて再読込しないと、削除済の旧 ID を
            // 編集リンクとして残してしまい、クリック時に 404 になる。
            window.addEventListener('message', (e) => {
                if (e.origin !== window.location.origin) return;
                const t = e?.data?.type;
                if (t === 'rice-mail-draft-saved' || t === 'rice-mail-sent') {
                    this.load();
                }
            });
            // タブが手前に戻ったタイミングでも再読込 (window.close() 後の戻り想定)
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') this.load();
            });
            await this.load();
        },

        get rejectedCount() {
            return this.drafts.filter(d => d.is_rejected).length;
        },
        get scheduledCount() {
            return this.drafts.filter(d => d.is_scheduled).length;
        },

        async load() {
            this.loading = true;
            try {
                const scope = this.inboxScope || 'shared';
                const res = await fetch('/drafts/list?scope=' + encodeURIComponent(scope), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                this.drafts = await res.json();
            } catch (e) {
                this.showToast('読み込みに失敗しました', 'error');
            } finally {
                this.loading = false;
            }
        },

        setInboxScope(scope) {
            if (scope !== 'shared' && scope !== 'personal') return;
            if (this.inboxScope === scope) return;
            this.inboxScope = scope;
            try { localStorage.setItem('inboxScope', scope); } catch (_) {}
            this.load();
        },

        openEdit(d) {
            // 行クリックで編集タブを開く (メール一覧でスレッドを開くのと同じ要領)
            window.open(`/drafts/${d.id}/edit`, '_blank');
        },

        async submitDraft(draft) {
            // 予約中のメールに対しては承認依頼は不可 (UI 側でもボタン非表示).
            // 仕様: 「ユーザが予約を設定した = 自己送信」なので、承認フローには載せない.
            if (draft.is_scheduled) {
                this.showToast('予約中のメールは承認依頼できません。「予約取消」してから依頼してください。', 'error');
                return;
            }
            const msg = draft.is_rejected
                ? `「${draft.subject || '(無題)'}」を再度承認依頼として送信しますか？\n\n※却下後そのままの内容で送信されます。修正したい場合は「編集」ボタンを使ってください。`
                : `「${draft.subject || '(無題)'}」を承認依頼として送信しますか？`;
            if (!confirm(msg)) return;
            this.submitting = draft.id;
            try {
                const res = await fetch(`/drafts/${draft.id}/submit`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                if (!res.ok) throw new Error('送信失敗');
                this.showToast('承認依頼に送信しました', 'success');
                await this.load();
            } catch(e) {
                this.showToast('送信に失敗しました', 'error');
            } finally {
                this.submitting = null;
            }
        },

        // 予約送信の取消 (→ 下書きに戻す).
        // 取り消し後に編集/削除/承認依頼などの通常下書きアクションが使えるようになる.
        async unscheduleDraft(draft) {
            const when = draft.scheduled_for_label || '指定日時';
            if (!confirm(`「${draft.subject || '(無題)'}」の予約送信 (${when}) を取り消して下書きに戻しますか？`)) return;
            this.unscheduling = draft.id;
            try {
                const res = await fetch(`/pending-emails/${draft.id}/unschedule`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.status === 'error') {
                    this.showToast(data.message || '予約取消に失敗しました', 'error');
                    return;
                }
                this.showToast('予約を取り消し、下書きに戻しました', 'success');
                await this.load();
            } catch (e) {
                this.showToast('予約取消に失敗しました: ' + (e.message || ''), 'error');
            } finally {
                this.unscheduling = null;
            }
        },

        async deleteDraft(draft) {
            if (!confirm(`「${draft.subject || '(無題)'}」を削除しますか？`)) return;
            try {
                const res = await fetch(`/drafts/${draft.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                if (!res.ok) throw new Error();
                this.showToast('削除しました', 'success');
                await this.load();
            } catch(e) {
                this.showToast('削除に失敗しました', 'error');
            }
        },

        showToast(msg, type = 'success') {
            this.toast = msg;
            this.toastType = type;
            setTimeout(() => { this.toast = null; }, 3000);
        }
    };
}
</script>
@endsection
