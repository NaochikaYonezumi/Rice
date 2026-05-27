@extends('layouts.app')
@section('title', '下書き一覧 - Rice')

@section('content')
<div x-data="draftApp()" x-init="load()" x-cloak>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold">下書き一覧</h3>
        </div>
        <div class="card-body p-0">
            <template x-if="loading">
                <div class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i>読み込み中...</div>
            </template>
            <template x-if="!loading && drafts.length === 0">
                <div class="text-center py-5 text-muted">保存済みの下書きはありません</div>
            </template>
            <template x-if="!loading && drafts.length > 0">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>種別</th>
                            <th>件名</th>
                            <th>宛先</th>
                            <th>本文プレビュー</th>
                            <th>作成日時</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="d in drafts" :key="d.id">
                            <tr>
                                <td>
                                    <span class="badge badge-secondary" x-text="d.reply_type_label"></span>
                                </td>
                                <td class="font-weight-bold" x-text="d.subject"></td>
                                <td class="text-muted" x-text="d.to_address"></td>
                                <td class="text-muted text-truncate" style="max-width:200px" x-text="d.body_preview"></td>
                                <td class="text-muted" x-text="d.created_at"></td>
                                <td class="text-right">
                                    <button @click="submitDraft(d)" :disabled="submitting === d.id"
                                        class="btn btn-sm btn-primary mr-1">
                                        <i class="fas fa-paper-plane"></i>
                                        <span x-show="submitting !== d.id">承認依頼</span>
                                        <span x-show="submitting === d.id">送信中...</span>
                                    </button>
                                    <button @click="deleteDraft(d)" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </template>
        </div>
    </div>

    {{-- トースト --}}
    <div x-show="toast" x-transition
         class="position-fixed"
         style="bottom:1.5rem;right:1.5rem;z-index:9999;">
        <div class="alert mb-0 shadow"
             :class="toastType === 'success' ? 'alert-success' : 'alert-danger'"
             x-text="toast"></div>
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

        async load() {
            this.loading = true;
            try {
                const res = await fetch('/drafts/list', { headers: { 'Accept': 'application/json' } });
                this.drafts = await res.json();
            } finally {
                this.loading = false;
            }
        },

        async submitDraft(draft) {
            if (!confirm(`「${draft.subject}」を承認依頼に送信しますか？`)) return;
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
            if (!confirm(`「${draft.subject}」を削除しますか？`)) return;
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
