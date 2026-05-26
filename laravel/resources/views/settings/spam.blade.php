@extends('layouts.app')
@section('title', '迷惑メール設定')
@section('header', '迷惑メール設定')

@section('css')
<style>
    /* ===== ダークモード上書き (settings/spam 専用) =====
       rooms/index と同じトーンを共有するため、属性セレクタで inline-style を上書き. */
    html.theme-dark [style*="background:#f9fafb"],
    html.theme-dark [style*="background:#fafafa"],
    html.theme-dark [style*="background: #f9fafb"],
    html.theme-dark [style*="background: #fafafa"] {
        background: var(--rd-bg-hover) !important;
        color: var(--rd-text) !important;
    }
    html.theme-dark [style*="color:#1f2937"],
    html.theme-dark [style*="color: #1f2937"] { color: var(--rd-text) !important; }
    html.theme-dark [style*="color:#374151"],
    html.theme-dark [style*="color: #374151"] { color: var(--rd-text) !important; }
    html.theme-dark [style*="color:#9ca3af"],
    html.theme-dark [style*="color: #9ca3af"] { color: var(--rd-text-dim) !important; }
    html.theme-dark [style*="color:#2563eb"],
    html.theme-dark [style*="color: #2563eb"] { color: #93c5fd !important; }
    html.theme-dark code {
        background: transparent !important;
        color: var(--rd-text) !important;
    }
    html.theme-dark table.table,
    html.theme-dark table.table-sm {
        color: var(--rd-text) !important;
        background-color: transparent !important;
    }
    html.theme-dark table.table thead th {
        background-color: var(--rd-bg-3) !important;
        color: var(--rd-text-mute) !important;
        border-bottom-color: var(--rd-border) !important;
    }
    html.theme-dark table.table tbody tr {
        background-color: var(--rd-bg-2) !important;
    }
    html.theme-dark table.table tbody tr:hover {
        background-color: var(--rd-bg-hover) !important;
    }
    html.theme-dark table.table td,
    html.theme-dark table.table th { border-color: var(--rd-border) !important; }
    html.theme-dark .badge-secondary {
        background: var(--rd-bg-active) !important;
        color: var(--rd-text) !important;
    }
    html.theme-dark .badge-light {
        background: var(--rd-bg-hover) !important;
        color: var(--rd-text) !important;
    }
    html.theme-dark .alert {
        border-color: var(--rd-border) !important;
        background-color: var(--rd-bg-2) !important;
        color: var(--rd-text) !important;
    }
    html.theme-dark .alert-success { background-color: rgba(87,242,135,0.15) !important; color: #6ee7b7 !important; }
    html.theme-dark .alert-danger  { background-color: rgba(237,66,69,0.15) !important; color: #fca5a5 !important; }
</style>
@endsection

@section('content')
<div class="p-4" x-data="spamSettings()" x-init="load()" x-cloak>

    {{--
        ★ ルーム管理 (/rooms) の「振り分けルール」カードと同じ UI 構成にする:
            ヘッダ (アイコン + タイトル + バッジ + 再読込)
            → 追加フォーム (灰色背景 / type + pattern + 追加)
            → 一覧テーブル
        差分: 迷惑メールは「単一の全体ルール集」なのでルーム選択ペインは不要.
    --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">

        {{-- カードヘッダ --}}
        <div class="px-4 py-3 border-b border-gray-200 d-flex align-items-center gap-2">
            <i class="fas fa-shield-alt" style="color:#dc2626;font-size:14px;"></i>
            <h3 class="mb-0 font-weight-bold" style="color:#1f2937;font-size:14px;">迷惑メール ブロックルール</h3>
            <span class="badge badge-light ml-1" x-text="rules.length"></span>
            <button type="button" class="btn btn-sm btn-link p-0 ml-auto" @click="load()" title="再読込">
                <i class="fas fa-sync-alt small"></i>
            </button>
        </div>

        {{-- 追加フォーム --}}
        <div class="px-4 py-3 border-b border-gray-100" style="background:#fafafa;">
            <p class="small font-weight-bold mb-2" style="color:#374151;">新しいルールを追加</p>
            {{--
                ルーム管理側と完全に同じレイアウト構造:
                  - タイプ select / パターン input / 追加 button の 3 カラム
                  - align-items-end でコントロール底辺揃え
                  - ヒント文は行の外に出して列高さの不均衡を回避
            --}}
            <div class="d-flex flex-wrap gap-2 align-items-end">
                <div style="min-width:240px;">
                    <label class="text-[11px] font-weight-bold text-gray-500 d-block mb-1">タイプ</label>
                    {{-- <select> 内に <template x-for> を入れない (option ずれ防止) --}}
                    <select class="form-control form-control-sm" x-model="form.type" style="min-width:240px;">
                        <option value="sender_address">送信元アドレス (From 完全一致)</option>
                        <option value="sender_domain">送信元ドメイン (From のみ)</option>
                        <option value="recipient_address">宛先アドレス (To/Cc/Bcc 完全一致)</option>
                        <option value="recipient_domain">宛先ドメイン (To/Cc/Bcc)</option>
                        <option value="recipient_contains">宛先に含む (To/Cc/Bcc 部分一致)</option>
                        <option value="subject_keyword">件名キーワード (部分一致)</option>
                        <option value="body_keyword">本文キーワード (部分一致)</option>
                    </select>
                </div>
                <div style="flex:1 1 280px;">
                    <label class="text-[11px] font-weight-bold text-gray-500 d-block mb-1">パターン</label>
                    <input type="text" class="form-control form-control-sm" x-model="form.pattern"
                           :placeholder="placeholderForType(form.type)" maxlength="255"
                           @keydown.enter.prevent="addRule()">
                </div>
                <button type="button" class="btn btn-sm btn-primary"
                        :disabled="!form.pattern.trim() || saving"
                        @click="addRule()">
                    <i class="fas fa-plus mr-1"></i>
                    <span x-text="saving ? '追加中...' : '追加'"></span>
                </button>
            </div>
            <p class="text-[10px] text-gray-500 mt-2 mb-0" x-text="hintForType(form.type)"></p>
            <p class="text-[10px] text-gray-400 mt-1 mb-0">
                <i class="fas fa-lightbulb mr-1" style="color:#f59e0b;"></i>
                メール一覧で対象メールを開いて <kbd>S</kbd> キー (または「迷惑メールに振り分け」) を押すと、
                そのメールの 件名 / From / To / Cc / ドメイン を <strong>ワンタップで選択</strong> できるモーダルが開きます.
            </p>
        </div>

        {{-- 一覧 --}}
        <div x-show="loading" class="p-4 text-center text-gray-400 small">
            <i class="fas fa-circle-notch fa-spin mr-2"></i>読込中...
        </div>
        <div x-show="!loading && rules.length === 0" class="p-4 text-center text-gray-400 small">
            まだルールがありません。上のフォームから追加するか、メール一覧で「迷惑メール」操作を行うと自動登録されます。
        </div>
        <table x-show="!loading && rules.length > 0" class="table table-sm mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width:5%;">有効</th>
                    <th style="width:18%;">タイプ</th>
                    <th>パターン</th>
                    <th style="width:12%;">作成者</th>
                    <th style="width:9%;" class="text-right">マッチ回数</th>
                    <th style="width:12%;">最終マッチ</th>
                    <th style="width:8%;" class="text-right">操作</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="r in rules" :key="r.id">
                    <tr>
                        <td>
                            <label :style="r.is_mine || isAdmin ? 'cursor:pointer;margin:0;' : 'cursor:not-allowed;margin:0;opacity:0.5;'"
                                   :title="r.is_mine || isAdmin ? '' : '他のユーザが作成したルールです'">
                                <input type="checkbox" :checked="r.enabled"
                                       :disabled="!r.is_mine && !isAdmin"
                                       @change="toggle(r)">
                            </label>
                        </td>
                        <td>
                            <span class="badge badge-secondary" x-text="typeLabel(r.type)"></span>
                        </td>
                        <td>
                            <code style="font-size:12px;color:#1f2937;background:transparent;word-break:break-all;" x-text="r.pattern"></code>
                        </td>
                        <td>
                            <small :style="r.is_mine ? 'color:#2563eb;font-weight:700;' : 'color:#6b7280;'"
                                   x-text="r.created_by_name || '(不明)'"
                                   :title="r.is_mine ? '自分が作成したルール' : '他のユーザのルール'"></small>
                        </td>
                        <td class="text-right small" x-text="r.match_count.toLocaleString()"></td>
                        <td><small x-text="r.last_matched_at || '-'"></small></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    :disabled="!r.is_mine && !isAdmin"
                                    :title="r.is_mine || isAdmin ? '削除' : '他のユーザのルールは削除できません'"
                                    @click="remove(r)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- フローティングメッセージ (右下) --}}
    <div x-show="msg" x-cloak x-transition.duration.300ms
         class="position-fixed" style="bottom:24px;right:24px;z-index:9999;">
        <div class="alert" :class="msgError ? 'alert-danger' : 'alert-success'" x-text="msg" style="margin:0;"></div>
    </div>
</div>

<script>
function spamSettings() {
    return {
        loading: false,
        saving: false,
        rules: [],
        types: [],
        form: { type: 'sender_address', pattern: '' },
        msg: '', msgError: false,
        csrf: document.querySelector('meta[name="csrf-token"]')?.content,
        isAdmin: {{ auth()->user()?->isAdmin() ? 'true' : 'false' }},

        // ローカルの placeholder / hint / label マップ.
        // API 側 (/api/mail-block-rules の types) と一致させているが、フロントでも保持して
        // API 未到着のタイミングで「ずれて見える」事象を防ぐ.
        _typeLabels: {
            sender_address:     '送信元アドレス',
            sender_domain:      '送信元ドメイン',
            recipient_address:  '宛先アドレス (To/Cc/Bcc)',
            recipient_domain:   '宛先ドメイン (To/Cc/Bcc)',
            recipient_contains: '宛先に含む (To/Cc/Bcc)',
            subject_keyword:    '件名キーワード',
            body_keyword:       '本文キーワード',
        },
        _typePlaceholders: {
            sender_address:     'spam@example.com',
            sender_domain:      'spam.example.com',
            recipient_address:  'list@example.com',
            recipient_domain:   'example.com',
            recipient_contains: 'support@',
            subject_keyword:    '副業, 当選, …',
            body_keyword:       '怪しいフレーズ',
        },
        _typeHints: {
            sender_address:     'From アドレス完全一致 (大文字小文字無視)',
            sender_domain:      'From の @ 以降と完全一致',
            recipient_address:  'To/Cc/Bcc のいずれかと完全一致',
            recipient_domain:   'To/Cc/Bcc のいずれかのドメインと完全一致',
            recipient_contains: 'To/Cc/Bcc 全体に部分一致',
            subject_keyword:    '件名に部分一致 (大文字小文字無視)',
            body_keyword:       '本文に部分一致',
        },

        placeholderForType(type) { return this._typePlaceholders[type] || ''; },
        hintForType(type)        { return this._typeHints[type]        || ''; },
        typeLabel(type)          { return this._typeLabels[type]       || type; },

        notify(text, isErr = false) {
            this.msg = text; this.msgError = isErr;
            setTimeout(() => { this.msg = ''; }, 2500);
        },

        async load() {
            this.loading = true;
            try {
                const r = await fetch('/api/mail-block-rules', { headers: { Accept: 'application/json' } });
                if (!r.ok) throw new Error('読込失敗 ' + r.status);
                const d = await r.json();
                this.rules = d.rules || [];
                this.types = d.types || [];
            } catch (e) {
                this.notify('読込失敗: ' + e.message, true);
            } finally { this.loading = false; }
        },

        async addRule() {
            if (!this.form.pattern.trim()) return;
            this.saving = true;
            try {
                const r = await fetch('/api/mail-block-rules', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify({ type: this.form.type, pattern: this.form.pattern, enabled: true }),
                });
                if (!r.ok) {
                    const e = await r.json().catch(() => ({}));
                    throw new Error(e.message || ('HTTP ' + r.status));
                }
                this.form.pattern = '';
                await this.load();
                this.notify('追加しました');
            } catch (e) {
                this.notify('追加失敗: ' + e.message, true);
            } finally { this.saving = false; }
        },

        async toggle(rule) {
            const next = !rule.enabled;
            try {
                const r = await fetch(`/api/mail-block-rules/${rule.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify({ enabled: next }),
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                rule.enabled = next;
                this.notify(next ? '有効化しました' : '無効化しました');
            } catch (e) { this.notify('切替失敗: ' + e.message, true); }
        },

        async remove(rule) {
            if (!confirm(`「${rule.pattern}」を削除しますか?`)) return;
            try {
                const r = await fetch(`/api/mail-block-rules/${rule.id}`, {
                    method: 'DELETE',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                this.rules = this.rules.filter(x => x.id !== rule.id);
                this.notify('削除しました');
            } catch (e) { this.notify('削除失敗: ' + e.message, true); }
        },
    };
}
</script>
@endsection
