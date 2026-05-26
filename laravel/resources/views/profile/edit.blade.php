@extends('layouts.app')

@section('title', 'Profile Settings - Rice')

@section('content')
<main class="flex-1 overflow-y-auto p-8">
    <div class="max-w-4xl mx-auto space-y-6">
        <h2 class="text-2xl font-black text-gray-900 tracking-tight mb-8">Profile Settings</h2>

        <div class="p-8 bg-white border border-gray-100 shadow-sm rounded-2xl">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        {{-- テーマ設定 (ライト / ダーク) --}}
        <div class="p-8 bg-white border border-gray-100 shadow-sm rounded-2xl"
             x-data="themePicker()">
            <h3 class="text-lg font-bold text-gray-900">テーマ</h3>
            <p class="mt-1 text-sm text-gray-600">画面の配色を切り替えます。設定はこのブラウザに保存されます。</p>

            <div class="mt-4 grid grid-cols-2 gap-3 max-w-md">
                <label :style="theme === 'light' ? 'border-color:#2563eb;background:#eff6ff;' : 'border-color:#e5e7eb;background:#fff;'"
                       style="display:flex;align-items:center;gap:10px;padding:14px;border:2px solid;border-radius:10px;cursor:pointer;">
                    <input type="radio" value="light" x-model="theme" @change="apply()" style="margin:0;">
                    <div style="flex:1;">
                        <p style="margin:0;font-weight:700;font-size:13px;color:#111827;">
                            <i class="fas fa-sun" style="color:#f59e0b;margin-right:6px;"></i>ライト
                        </p>
                        <p style="margin:2px 0 0;font-size:11px;color:#6b7280;">デフォルトの明るい配色</p>
                    </div>
                </label>
                <label :style="theme === 'dark' ? 'border-color:#5865f2;background:#eef2ff;' : 'border-color:#e5e7eb;background:#fff;'"
                       style="display:flex;align-items:center;gap:10px;padding:14px;border:2px solid;border-radius:10px;cursor:pointer;">
                    <input type="radio" value="dark" x-model="theme" @change="apply()" style="margin:0;">
                    <div style="flex:1;">
                        <p style="margin:0;font-weight:700;font-size:13px;color:#111827;">
                            <i class="fas fa-moon" style="color:#5865f2;margin-right:6px;"></i>ダーク (Discord 風)
                        </p>
                        <p style="margin:2px 0 0;font-size:11px;color:#6b7280;">目に優しい暗い配色</p>
                    </div>
                </label>
            </div>
            <p class="mt-3 text-xs text-gray-500">
                <i class="fas fa-info-circle mr-1"></i>切り替えは即時反映されます。
            </p>
        </div>

        <div class="p-8 bg-white border border-gray-100 shadow-sm rounded-2xl">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        {{-- シグネチャ管理 (複数) --}}
        <div class="p-8 bg-white border border-gray-100 shadow-sm rounded-2xl" x-data="signaturesApp()" x-init="load()">
            <h3 class="text-lg font-bold text-gray-900">メール署名 (複数登録可)</h3>
            <p class="mt-1 text-sm text-gray-600">複数の署名を登録し、返信ウィンドウで選択できます。「デフォルト」は AI 生成・新規返信時に初期表示されます。</p>

            <div class="mt-4 space-y-3">
                <template x-for="(s, idx) in items" :key="s.id || ('new'+idx)">
                    <div class="border border-gray-200 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" x-model="s.name" placeholder="署名名 (例: 営業用)"
                                   class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="128">
                            <label class="text-xs inline-flex items-center gap-1">
                                <input type="checkbox" x-model="s.is_default" class="rounded">デフォルト
                            </label>
                            <button @click="save(s)" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1.5 rounded-lg">保存</button>
                            <button x-show="s.id" @click="del(s)" class="text-red-500 hover:text-red-700 text-xs px-2">削除</button>
                        </div>
                        <textarea x-model="s.body" rows="5" placeholder="---&#10;〇〇株式会社&#10;氏名: ..." class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono" maxlength="5000"></textarea>
                    </div>
                </template>
                <button @click="addNew()" class="text-sm text-blue-600 hover:underline">＋ 新しい署名を追加</button>
            </div>
        </div>

        {{-- メールテンプレート --}}
        <div class="p-8 bg-white border border-gray-100 shadow-sm rounded-2xl" x-data="templatesApp()" x-init="load()">
            <h3 class="text-lg font-bold text-gray-900">メールテンプレート</h3>
            <p class="mt-1 text-sm text-gray-600">よく使う本文をテンプレート登録。返信/新規ウィンドウのカーソル位置に挿入できます。</p>

            <div class="mt-4 space-y-3">
                <template x-for="(t, idx) in items" :key="t.id || ('new'+idx)">
                    <div class="border border-gray-200 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" x-model="t.name" placeholder="テンプレート名 (例: 受付完了)" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm" maxlength="128">
                            <button @click="save(t)" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1.5 rounded-lg">保存</button>
                            <button x-show="t.id" @click="del(t)" class="text-red-500 hover:text-red-700 text-xs px-2">削除</button>
                        </div>
                        {{-- 件名テンプレ欄は廃止 (テンプレートは本文挿入専用). 既存データの subject は保存時に空文字で上書きされる. --}}
                        <textarea x-model="t.body" rows="6" placeholder="本文テンプレ" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono" maxlength="50000"></textarea>
                    </div>
                </template>
                <button @click="addNew()" class="text-sm text-blue-600 hover:underline">＋ 新しいテンプレートを追加</button>
            </div>
        </div>

        <script>
        const _csrf = document.querySelector('meta[name="csrf-token"]').content;
        function themePicker() {
            return {
                theme: 'light',
                init() {
                    try { this.theme = localStorage.getItem('riceTheme') || 'light'; } catch (_) {}
                },
                apply() {
                    try { localStorage.setItem('riceTheme', this.theme); } catch (_) {}
                    if (this.theme === 'dark') {
                        document.documentElement.classList.add('theme-dark');
                    } else {
                        document.documentElement.classList.remove('theme-dark');
                    }
                },
            };
        }
        function signaturesApp() {
            return {
                items: [],
                async load() {
                    const r = await fetch('/api/user/signatures', { headers:{Accept:'application/json'} });
                    if (r.ok) this.items = (await r.json()).signatures || [];
                },
                addNew() { this.items.push({ id:null, name:'', body:'', is_default:false }); },
                async save(s) {
                    const url = s.id ? `/api/user/signatures/${s.id}` : '/api/user/signatures';
                    const r = await fetch(url, { method: s.id ? 'PUT' : 'POST',
                        headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':_csrf},
                        body: JSON.stringify({ name:s.name, body:s.body, is_default:s.is_default?1:0 })});
                    if (!r.ok) { alert('保存に失敗'); return; }
                    const d = await r.json();
                    Object.assign(s, d.signature);
                    if (s.is_default) this.items.forEach(x => { if (x.id !== s.id) x.is_default = false; });
                },
                async del(s) {
                    if (!confirm('削除しますか？')) return;
                    if (!s.id) { this.items = this.items.filter(x => x !== s); return; }
                    const r = await fetch(`/api/user/signatures/${s.id}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':_csrf,Accept:'application/json'} });
                    if (r.ok) this.items = this.items.filter(x => x.id !== s.id);
                },
            };
        }
        function templatesApp() {
            return {
                items: [],
                async load() {
                    const r = await fetch('/api/user/templates', { headers:{Accept:'application/json'} });
                    if (r.ok) this.items = (await r.json()).templates || [];
                },
                // テンプレートは本文挿入専用なので subject は常に空文字で送る (API 互換性のため).
                addNew() { this.items.push({ id:null, name:'', body:'' }); },
                async save(t) {
                    const url = t.id ? `/api/user/templates/${t.id}` : '/api/user/templates';
                    const r = await fetch(url, { method: t.id ? 'PUT' : 'POST',
                        headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':_csrf},
                        body: JSON.stringify({ name:t.name, subject:'', body:t.body })});
                    if (!r.ok) { alert('保存に失敗'); return; }
                    Object.assign(t, (await r.json()).template);
                },
                async del(t) {
                    if (!confirm('削除しますか？')) return;
                    if (!t.id) { this.items = this.items.filter(x => x !== t); return; }
                    const r = await fetch(`/api/user/templates/${t.id}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':_csrf,Accept:'application/json'} });
                    if (r.ok) this.items = this.items.filter(x => x.id !== t.id);
                },
            };
        }
        </script>

        <div class="p-8 bg-white border border-gray-100 shadow-sm rounded-2xl">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</main>
@endsection
