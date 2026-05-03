@extends('layouts.app')
@section('title', '下書き一覧 - Rice')

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
</style>
@endsection

@section('content')
<div class="px-6 py-5 max-w-6xl mx-auto space-y-4" x-data="draftApp()" x-init="load()" x-cloak>

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900">下書き一覧</h1>
            <p class="text-xs text-gray-500 mt-0.5">保存済みの下書きを編集・送信・削除できます</p>
        </div>
        <div class="flex items-center gap-3 text-xs text-gray-500">
            <span>合計: <span class="font-bold text-gray-700" x-text="drafts.length"></span> 件</span>
            <span x-show="rejectedCount > 0" class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-red-50 text-red-700 border border-red-200 font-bold">
                <i class="fas fa-times-circle"></i>
                却下後 <span x-text="rejectedCount"></span> 件
            </span>
            <button @click="load()" class="text-gray-400 hover:text-blue-600" title="更新">
                <i class="fas fa-sync-alt" :class="loading ? 'animate-spin text-blue-600' : ''"></i>
            </button>
        </div>
    </div>

    {{-- 空状態 --}}
    <template x-if="loading">
        <div class="bg-white border border-gray-200 rounded-xl p-12 text-center text-gray-400">
            <i class="fas fa-spinner fa-spin fa-2x mb-3 text-gray-300"></i>
            <p class="text-sm">読み込み中...</p>
        </div>
    </template>
    <template x-if="!loading && drafts.length === 0">
        <div class="bg-white border border-gray-200 rounded-xl p-16 text-center text-gray-400">
            <i class="fas fa-inbox fa-3x mb-3 text-gray-200"></i>
            <p class="text-sm font-semibold text-gray-600">保存済みの下書きはありません</p>
            <p class="text-xs text-gray-400 mt-1">受信トレイから「下書き保存」を押すか、却下されたメールが自動的にここに集まります。</p>
        </div>
    </template>

    {{-- リスト --}}
    <template x-if="!loading && drafts.length > 0">
        <div class="space-y-3">
            <template x-for="d in drafts" :key="d.id">
                <div class="bg-white border rounded-xl shadow-sm overflow-hidden transition-all hover:shadow-md"
                     :class="d.is_rejected ? 'border-red-300' : 'border-gray-200'">

                    {{-- 却下バナー --}}
                    <template x-if="d.is_rejected">
                        <div class="bg-red-50 border-b border-red-200 px-5 py-2.5 flex items-center justify-between gap-3 flex-wrap">
                            <div class="flex items-center gap-2 min-w-0">
                                <i class="fas fa-times-circle text-red-600"></i>
                                <span class="text-xs font-bold text-red-800">却下されました</span>
                                <span class="text-[11px] text-red-600">
                                    by <span class="font-bold" x-text="d.rejected_by_name || '不明'"></span>
                                    <span class="text-red-400 ml-1" x-text="d.rejected_at"></span>
                                </span>
                            </div>
                            <span class="text-[10px] font-bold text-red-700 bg-white border border-red-200 px-2 py-0.5 rounded-full">
                                編集して再依頼可
                            </span>
                        </div>
                    </template>

                    {{-- 本体 --}}
                    <div class="px-5 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-gray-100 text-gray-700 border border-gray-200"
                                          x-text="d.reply_type_label"></span>
                                    <template x-if="d.thread_subject">
                                        <span class="text-[10px] text-gray-400">→ <span x-text="d.thread_subject"></span></span>
                                    </template>
                                    <template x-if="d.attachment_count > 0">
                                        <span class="text-[10px] text-blue-600 inline-flex items-center gap-1">
                                            <i class="fas fa-paperclip"></i><span x-text="d.attachment_count"></span> 件
                                        </span>
                                    </template>
                                </div>
                                <p class="text-base font-bold text-gray-900 mb-1" x-text="d.subject || '(無題)'"></p>
                                <p class="text-xs text-gray-500 mb-2">
                                    <span class="font-bold">To:</span> <span x-text="d.to_address || '(未指定)'"></span>
                                </p>
                                <p class="text-xs text-gray-600 line-clamp-2 leading-relaxed" x-text="d.body_preview"></p>

                                {{-- 却下理由表示 --}}
                                <template x-if="d.is_rejected && d.rejection_reason">
                                    <div class="mt-3 p-3 bg-red-50 border border-red-100 rounded-lg">
                                        <p class="text-[10px] font-bold text-red-700 uppercase tracking-wider mb-1">
                                            <i class="fas fa-comment-alt-times mr-1"></i>却下理由
                                        </p>
                                        <p class="text-xs text-red-900 whitespace-pre-wrap leading-relaxed" x-text="d.rejection_reason"></p>
                                    </div>
                                </template>

                                <p class="text-[10px] text-gray-400 mt-2">
                                    更新: <span x-text="d.updated_at || d.created_at"></span>
                                </p>
                            </div>

                            {{-- 操作ボタン --}}
                            <div class="flex items-center gap-2 shrink-0">
                                <a :href="`/drafts/${d.id}/edit`" target="_blank"
                                   class="inline-flex items-center gap-1.5 bg-white border border-blue-200 text-blue-600 hover:bg-blue-50 px-3 py-2 rounded-lg text-xs font-bold transition-all"
                                   title="新しいウィンドウで編集">
                                    <i class="fas fa-edit"></i> 編集
                                </a>
                                <button @click="submitDraft(d)" :disabled="submitting === d.id"
                                        class="inline-flex items-center gap-1.5 text-white px-3 py-2 rounded-lg text-xs font-bold transition-all disabled:opacity-50"
                                        style="background-color:#2563eb;"
                                        title="承認依頼として送信">
                                    <i class="fas fa-paper-plane"></i>
                                    <span x-show="submitting !== d.id">承認依頼</span>
                                    <span x-show="submitting === d.id">送信中...</span>
                                </button>
                                <button @click="deleteDraft(d)"
                                        class="inline-flex items-center justify-center w-9 h-9 text-gray-400 hover:text-red-500 hover:bg-red-50 border border-gray-200 hover:border-red-200 rounded-lg transition-all"
                                        title="削除">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>

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
        toast: null,
        toastType: 'success',

        get rejectedCount() {
            return this.drafts.filter(d => d.is_rejected).length;
        },

        async load() {
            this.loading = true;
            try {
                const res = await fetch('/drafts/list', { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                this.drafts = await res.json();
            } catch (e) {
                this.showToast('読み込みに失敗しました', 'error');
            } finally {
                this.loading = false;
            }
        },

        async submitDraft(draft) {
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
