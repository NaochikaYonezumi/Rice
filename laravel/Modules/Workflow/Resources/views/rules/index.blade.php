@extends('layouts.app')
@section('title', '自動割当ルール - Rice')

@section('content')
<div class="container-fluid py-3" x-data="ruleApp({{ $users->toJson() }})" x-init="load()">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-1"><i class="fas fa-project-diagram mr-2 text-primary"></i>自動割当ルール</h3>
            <p class="text-muted mb-0" style="font-size:12px;">送信元アドレス・ドメインなどの条件で、受信スレッドの担当者を自動割当します。ルールにマッチしない場合はラウンドロビン。</p>
        </div>
        <button @click="openCreate()" class="btn btn-primary btn-sm">
            <i class="fas fa-plus mr-1"></i>ルール追加
        </button>
    </div>

    <div class="card">
        <table class="table table-sm mb-0">
            <thead class="thead-light">
                <tr>
                    <th style="width:60px;">優先度</th>
                    <th>名前</th>
                    <th>マッチ種別</th>
                    <th>マッチ値</th>
                    <th>担当者</th>
                    <th style="width:80px;">有効</th>
                    <th style="width:170px;">最終更新</th>
                    <th style="width:120px;"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="r in rules" :key="r.id">
                    <tr>
                        <td><strong x-text="r.priority"></strong></td>
                        <td x-text="r.name"></td>
                        <td><span class="badge badge-info" x-text="matchTypeLabel(r.match_type)"></span></td>
                        <td><code x-text="r.match_value"></code></td>
                        <td x-text="r.assignee_name || '—'"></td>
                        <td>
                            <button @click="toggle(r)" class="btn btn-sm" :class="r.is_active ? 'btn-success' : 'btn-outline-secondary'" :title="r.is_active ? '無効化する' : '有効化する'">
                                <i class="fas" :class="r.is_active ? 'fa-check-circle' : 'fa-times-circle'"></i>
                            </button>
                        </td>
                        <td><small x-text="r.updated_at"></small></td>
                        <td class="text-right">
                            <button @click="openEdit(r)" class="btn btn-link btn-sm text-primary p-0 mr-2"><i class="fas fa-edit"></i></button>
                            <button @click="remove(r)" class="btn btn-link btn-sm text-danger p-0"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                </template>
                <template x-if="rules.length === 0 && !loading">
                    <tr><td colspan="8" class="text-center text-muted py-4">ルールがまだ登録されていません</td></tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- モーダル --}}
    <div x-show="modalOpen" x-cloak class="modal d-block" style="background:rgba(0,0,0,0.5);" @click.self="modalOpen = false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" x-text="editing.id ? 'ルール編集' : '新規ルール'"></h5>
                    <button @click="modalOpen = false" class="close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="small font-weight-bold">名前</label>
                        <input type="text" class="form-control form-control-sm" x-model="editing.name" placeholder="例: MF 経由は田中さん">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-5">
                            <label class="small font-weight-bold">マッチ種別</label>
                            <select class="form-control form-control-sm" x-model="editing.match_type">
                                <option value="from_address">送信元アドレス (完全一致)</option>
                                <option value="from_domain">送信元ドメイン</option>
                                <option value="subject_contains">件名に含む</option>
                                <option value="to_address">宛先アドレス</option>
                            </select>
                        </div>
                        <div class="form-group col-md-7">
                            <label class="small font-weight-bold">マッチ値</label>
                            <input type="text" class="form-control form-control-sm" x-model="editing.match_value" :placeholder="placeholderForMatch(editing.match_type)">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label class="small font-weight-bold">担当者</label>
                            <select class="form-control form-control-sm" x-model.number="editing.assign_user_id">
                                <option :value="null" disabled>選択してください</option>
                                <template x-for="u in users" :key="u.id">
                                    <option :value="u.id" x-text="u.name + ' <' + u.email + '>'"></option>
                                </template>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="small font-weight-bold">優先度 (小さいほど先)</label>
                            <input type="number" min="0" max="1000" class="form-control form-control-sm" x-model.number="editing.priority">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-sm" @click="modalOpen = false">キャンセル</button>
                    <button class="btn btn-primary btn-sm" @click="save()" :disabled="!canSave"><i class="fas fa-save mr-1"></i>保存</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
function ruleApp(users) {
    return {
        rules: [],
        users: users || [],
        loading: false,
        modalOpen: false,
        editing: { id: null, name: '', match_type: 'from_address', match_value: '', assign_user_id: null, priority: 100 },

        get canSave() {
            return this.editing.name && this.editing.match_value && this.editing.assign_user_id;
        },

        matchTypeLabel(t) {
            return ({
                from_address: '送信元',
                from_domain: 'ドメイン',
                subject_contains: '件名 contains',
                to_address: '宛先',
            })[t] || t;
        },

        placeholderForMatch(t) {
            return ({
                from_address: 'someone@example.com',
                from_domain: 'example.com',
                subject_contains: '請求書',
                to_address: 'support@yourcompany.com',
            })[t] || '';
        },

        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },

        async load() {
            this.loading = true;
            try {
                const res = await fetch('/admin/workflow-rules/list', { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const data = await res.json();
                    this.rules = data.rules || [];
                }
            } catch (e) { console.error(e); }
            finally { this.loading = false; }
        },

        openCreate() {
            this.editing = { id: null, name: '', match_type: 'from_address', match_value: '', assign_user_id: null, priority: 100 };
            this.modalOpen = true;
        },
        openEdit(r) {
            this.editing = { ...r };
            this.modalOpen = true;
        },

        async save() {
            const isEdit = !!this.editing.id;
            const url = isEdit ? `/admin/workflow-rules/${this.editing.id}` : '/admin/workflow-rules';
            const method = isEdit ? 'PUT' : 'POST';
            const res = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                body: JSON.stringify(this.editing),
            });
            if (res.ok) {
                this.modalOpen = false;
                this.load();
            } else {
                const data = await res.json().catch(() => ({}));
                alert(data.message || '保存に失敗しました');
            }
        },

        async toggle(r) {
            const res = await fetch(`/admin/workflow-rules/${r.id}/toggle`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
            });
            if (res.ok) this.load();
        },

        async remove(r) {
            if (!confirm(`ルール「${r.name}」を削除しますか？`)) return;
            const res = await fetch(`/admin/workflow-rules/${r.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
            });
            if (res.ok) this.load();
        },
    };
}
</script>
@endpush
@endsection
