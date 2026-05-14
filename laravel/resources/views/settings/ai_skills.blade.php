@extends('layouts.app')
@section('title', 'AIスキル')

@section('content')
<div class="flex-1 flex h-full overflow-hidden bg-gray-50" x-data="aiSkillsApp()">

    {{-- 左: メインリスト --}}
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
        <div class="shrink-0 px-6 py-4 border-b border-gray-200 bg-white flex items-center justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-800">AIスキル</h1>
                <p class="text-xs text-gray-400 mt-0.5">AI 要約・返信生成で使うスキル (システムプロンプト) を自分専用に編集できます。</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="resetDefaults()"
                        class="text-xs text-gray-500 hover:text-red-600 inline-flex items-center gap-1">
                    <i class="fas fa-undo"></i>
                    <span>デフォルトに戻す</span>
                </button>
                <button type="button" @click="openNew()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-bold text-white"
                        style="background-color:#4f46e5;">
                    <i class="fas fa-plus"></i>新規追加
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            <template x-if="skills.length === 0">
                <div class="text-center py-20 text-gray-400">
                    <i class="fas fa-magic fa-3x mb-3 text-gray-200"></i>
                    <p class="text-sm">まだスキルがありません</p>
                    <button type="button" @click="openNew()" class="mt-3 text-sm text-indigo-600 hover:text-indigo-700 font-bold underline">
                        最初のスキルを作成する
                    </button>
                </div>
            </template>

            <div class="space-y-2 max-w-3xl">
                <template x-for="s in skills" :key="s.id">
                    <div @click="openEdit(s)"
                         class="group bg-white border rounded-xl px-4 py-3 cursor-pointer transition-all"
                         :class="selected?.id === s.id
                            ? 'border-indigo-400 ring-2 ring-indigo-100'
                            : 'border-gray-200 hover:border-indigo-200 hover:shadow-sm'">
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-lg shrink-0 inline-flex items-center justify-center"
                                 :class="s.is_active ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400'">
                                <i class="fas fa-magic"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <h3 class="font-bold text-gray-800 text-sm" x-text="s.name"></h3>
                                    <code class="text-[10px] bg-gray-100 px-1.5 py-0.5 rounded text-gray-500" x-text="s.skill_key"></code>
                                    <template x-if="s.is_default">
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200">デフォルト</span>
                                    </template>
                                    <template x-if="s.show_in_summary">
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200">
                                            <i class="fas fa-tag text-[8px] mr-0.5"></i>要約
                                        </span>
                                    </template>
                                    <template x-if="s.show_in_reply">
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fas fa-tag text-[8px] mr-0.5"></i>返信
                                        </span>
                                    </template>
                                    <template x-if="s.is_default_summary">
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-800 border border-indigo-300">
                                            <i class="fas fa-star text-[8px] mr-0.5"></i>要約デフォルト
                                        </span>
                                    </template>
                                    <template x-if="s.is_default_reply">
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            <i class="fas fa-star text-[8px] mr-0.5"></i>返信デフォルト
                                        </span>
                                    </template>
                                    <template x-if="!s.is_active">
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 border border-gray-200">無効</span>
                                    </template>
                                </div>
                                <p x-show="s.description" class="text-xs text-gray-500 mt-0.5 truncate" x-text="s.description"></p>
                                <p class="text-[11px] text-gray-400 mt-1 line-clamp-2 leading-relaxed" x-text="s.system_prompt"></p>
                            </div>
                            <div class="text-gray-300 group-hover:text-indigo-500 transition-colors shrink-0">
                                <i class="fas fa-chevron-right text-xs"></i>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- 右: 編集ドロワー (Notion 風サイドパネル) --}}
    <div x-show="drawerOpen" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-x-8 opacity-0"
         x-transition:enter-end="translate-x-0 opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="translate-x-0 opacity-100"
         x-transition:leave-end="translate-x-8 opacity-0"
         :style="'width:' + drawerWidth + 'px'"
         class="shrink-0 border-l border-gray-200 bg-white flex flex-col overflow-hidden relative"
         @keydown.escape.window="closeDrawer()">

        {{-- リサイズハンドル (左端) --}}
        <div class="absolute top-0 left-0 w-1.5 h-full cursor-col-resize hover:bg-indigo-400 z-50"
             @mousedown.prevent="startResizeDrawer($event)"
             title="ドラッグで幅を変更"></div>

        {{-- ヘッダ --}}
        <div class="shrink-0 px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 inline-flex items-center justify-center shrink-0">
                    <i class="fas fa-magic"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="font-bold text-sm text-gray-800" x-text="mode === 'new' ? '新規スキル' : 'スキル編集'"></h2>
                    <code x-show="mode === 'edit' && form.skill_key" class="text-[10px] text-gray-400" x-text="form.skill_key"></code>
                </div>
            </div>
            <button type="button" @click="closeDrawer()" class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100" title="閉じる (Esc)">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- 編集フォーム --}}
        <div class="flex-1 overflow-y-auto p-5 space-y-4">
            <template x-if="errorMessage">
                <div class="px-3 py-2 rounded-lg text-xs bg-red-50 border border-red-200 text-red-700" x-text="errorMessage"></div>
            </template>

            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">スキル名</label>
                <input type="text" x-model="form.name" maxlength="128"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200"
                       placeholder="例: クレーム返信テンプレ">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">説明 (任意)</label>
                <input type="text" x-model="form.description" maxlength="500"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200"
                       placeholder="ピッカーに表示される短い説明">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">システム指示プロンプト</label>
                    <span class="text-[10px] text-gray-400" x-text="(form.system_prompt?.length || 0) + ' / 20000'"></span>
                </div>
                <div class="relative prompt-editor-container">
                    {{-- 背面のシンタックスハイライト層 (textarea と同じ位置/サイズで /コレクション をグレーチップ化) --}}
                    <div x-ref="promptHighlight" class="prompt-editor-highlight" aria-hidden="true"
                         x-html="renderPromptHighlight(form.system_prompt)"></div>
                    <textarea x-ref="promptArea"
                              x-model="form.system_prompt"
                              @input="onPromptInput($event); syncHighlightScroll()"
                              @scroll="syncHighlightScroll()"
                              @keydown="onPromptKeyDown($event)"
                              @blur="setTimeout(() => slash.open = false, 150)"
                              rows="14" maxlength="20000"
                              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs font-mono leading-relaxed focus:outline-none focus:ring-2 focus:ring-indigo-200 resize-y prompt-editor-input"
                              placeholder="あなたは…です。例: /(コレクション名) を参照して、メールに対して丁寧に返信してください。"></textarea>

                    {{-- スラッシュコマンド候補ポップアップ --}}
                    <div x-show="slash.open" x-cloak
                         class="absolute left-0 right-0 z-30 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl max-h-72 overflow-y-auto"
                         style="top: 100%;">
                        <div class="sticky top-0 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                            <span><i class="fas fa-folder text-indigo-400 mr-1"></i>ナレッジコレクション</span>
                            <span class="text-[9px] text-gray-300" x-show="slash.loading">読み込み中...</span>
                            <span class="text-[9px] text-gray-300" x-show="!slash.loading"><kbd class="px-1 bg-white border border-gray-200 rounded">↑↓</kbd> 移動 / <kbd class="px-1 bg-white border border-gray-200 rounded">Enter</kbd> 選択 / <kbd class="px-1 bg-white border border-gray-200 rounded">Esc</kbd> 閉じる</span>
                        </div>
                        <template x-for="(c, idx) in filteredCollections" :key="c.name + idx">
                            <button type="button"
                                    @mousedown.prevent="insertCollection(c.name)"
                                    @mouseenter="slash.activeIdx = idx"
                                    :class="idx === slash.activeIdx ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'"
                                    class="w-full text-left px-3 py-2 text-sm flex items-center gap-2 transition-colors">
                                <i class="fas fa-folder text-indigo-400 text-xs"></i>
                                <span class="flex-1 font-mono" x-text="c.name"></span>
                                <span class="text-[10px] text-gray-400" x-text="c.source === 'rag-api' ? 'rag' : 'db'"></span>
                            </button>
                        </template>
                        <template x-if="!slash.loading && filteredCollections.length === 0">
                            <p class="px-3 py-3 text-xs text-gray-400 text-center">該当するコレクションがありません</p>
                        </template>
                    </div>
                </div>
                <p class="text-[10px] text-gray-400 mt-1">
                    AI に渡される基本指示。各リクエスト時の「追加指示」と組み合わさります。
                    <span class="text-indigo-500"><kbd class="px-1 bg-white border border-gray-200 rounded">/</kbd> でナレッジコレクションを参照</span>。
                </p>
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" x-model="form.is_active" class="rounded text-indigo-600 focus:ring-indigo-300">
                <span>このスキルを有効にする (無効化すると全ピッカーから消える)</span>
            </label>

            <div class="pt-3 border-t border-gray-100">
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2">表示先 (タグ)</p>
                <div class="space-y-2">
                    <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" x-model="form.show_in_summary" class="mt-1 rounded text-indigo-600 focus:ring-indigo-300">
                        <span class="flex-1">
                            <span class="font-bold inline-flex items-center gap-1">
                                <i class="fas fa-tag text-[10px] text-indigo-500"></i>AI要約 のピッカーに表示
                            </span>
                            <span class="block text-[11px] text-gray-500">スレッドの「AI要約」モーダルでこのスキルを選べるようにする</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" x-model="form.show_in_reply" class="mt-1 rounded text-emerald-600 focus:ring-emerald-300">
                        <span class="flex-1">
                            <span class="font-bold inline-flex items-center gap-1">
                                <i class="fas fa-tag text-[10px] text-emerald-500"></i>メール返信 のピッカーに表示
                            </span>
                            <span class="block text-[11px] text-gray-500">返信ウィンドウの AI 生成パネルでこのスキルを選べるようにする</span>
                        </span>
                    </label>
                </div>
                <template x-if="!form.show_in_summary && !form.show_in_reply">
                    <p class="text-[10px] text-amber-600 mt-2"><i class="fas fa-exclamation-triangle mr-1"></i>どちらにも表示されません。少なくとも 1 つチェックしてください。</p>
                </template>
            </div>

            <div class="pt-3 border-t border-gray-100">
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2">デフォルト設定</p>
                <div class="space-y-2">
                    <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" x-model="form.is_default_summary" class="mt-1 rounded text-indigo-600 focus:ring-indigo-300">
                        <span class="flex-1">
                            <span class="font-bold">AI要約のデフォルト</span>
                            <span class="block text-[11px] text-gray-500">スレッドの「AI要約」を開いたとき、最初に選択される</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" x-model="form.is_default_reply" class="mt-1 rounded text-emerald-600 focus:ring-emerald-300">
                        <span class="flex-1">
                            <span class="font-bold">メール返信のデフォルト</span>
                            <span class="block text-[11px] text-gray-500">返信ウィンドウで AI 返信生成を呼ぶとき、最初に選択される</span>
                        </span>
                    </label>
                </div>
                <p class="text-[10px] text-gray-400 mt-2">※ 各カテゴリのデフォルトは 1 つだけ。別のスキルを設定すると自動で入れ替わります。</p>
            </div>

            <template x-if="form.is_default">
                <div class="px-3 py-2 rounded-lg text-xs bg-amber-50 border border-amber-200 text-amber-800">
                    <i class="fas fa-info-circle mr-1"></i>これはデフォルトスキルです。削除しても「デフォルトに戻す」で復元できます。
                </div>
            </template>
        </div>

        {{-- フッター --}}
        <div class="shrink-0 px-5 py-3 border-t border-gray-100 flex items-center justify-between bg-gray-50">
            <button type="button" @click="deleteSkill()" x-show="mode === 'edit'"
                    class="text-xs text-red-500 hover:text-red-700 inline-flex items-center gap-1">
                <i class="fas fa-trash"></i><span>削除</span>
            </button>
            <div class="flex items-center gap-2 ml-auto">
                <button type="button" @click="closeDrawer()"
                        class="px-3 py-1.5 rounded-lg text-sm text-gray-700 bg-white border border-gray-200 hover:bg-gray-100">
                    キャンセル
                </button>
                <button type="button" @click="save()" :disabled="saving"
                        class="px-4 py-1.5 rounded-lg text-sm font-bold text-white disabled:opacity-50 inline-flex items-center gap-1.5"
                        style="background-color:#4f46e5;">
                    <i x-show="saving" class="fas fa-circle-notch fa-spin text-xs"></i>
                    <span x-text="saving ? '保存中…' : (mode === 'new' ? '追加' : '保存')"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function aiSkillsApp() {
    return {
        skills: @json($skills),
        drawerOpen: false,
        mode: 'edit', // 'edit' | 'new'
        selected: null,
        saving: false,
        errorMessage: '',
        form: { id: null, skill_key: '', name: '', description: '', system_prompt: '', is_active: true, is_default: false, is_default_summary: false, is_default_reply: false, show_in_summary: true, show_in_reply: true },
        csrfToken: document.querySelector('meta[name="csrf-token"]').content,
        // 編集ドロワー幅 (localStorage 永続化)
        drawerWidth: parseInt(localStorage.getItem('aiSkillsDrawerWidth') || '480', 10),

        startResizeDrawer(e) {
            const startX = e.clientX, startW = this.drawerWidth;
            const prevUserSelect = document.body.style.userSelect;
            document.body.style.userSelect = 'none';
            document.body.style.cursor = 'col-resize';
            const onMove = (me) => {
                // 左端ハンドル: 左ドラッグで広く、右ドラッグで狭く (符号反転)
                this.drawerWidth = Math.max(360, Math.min(1100, startW - (me.clientX - startX)));
            };
            const onUp = () => {
                localStorage.setItem('aiSkillsDrawerWidth', String(this.drawerWidth));
                document.body.style.userSelect = prevUserSelect;
                document.body.style.cursor = '';
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        // ===== スラッシュコマンド (ナレッジコレクション挿入) =====
        collections: [],
        collectionsLoaded: false,
        slash: { open: false, query: '', startPos: 0, activeIdx: 0, loading: false },

        get filteredCollections() {
            const q = (this.slash.query || '').toLowerCase();
            if (!q) return this.collections;
            return this.collections.filter(c => (c.name || '').toLowerCase().includes(q));
        },

        async loadCollections() {
            if (this.collectionsLoaded) return;
            this.slash.loading = true;
            try {
                const res = await fetch('/api/knowledge/collections', { headers: { Accept: 'application/json' } });
                if (res.ok) {
                    const data = await res.json();
                    this.collections = data.collections || [];
                }
            } catch (_) { /* 無視 */ }
            finally {
                this.collectionsLoaded = true;
                this.slash.loading = false;
            }
        },

        onPromptInput(e) {
            const ta = e.target;
            const pos = ta.selectionStart;
            const value = ta.value;

            // カーソル直前で最も近い「/」を探す。空白/改行で区切られている / 行頭の '/' のみ有効
            let validIdx = -1;
            for (let i = pos - 1; i >= 0; i--) {
                const ch = value[i];
                if (/\s/.test(ch)) break;        // 単語の区切り
                if (ch === '/') {
                    const prev = value[i - 1];
                    if (i === 0 || /\s/.test(prev)) validIdx = i;
                    break;
                }
            }

            if (validIdx === -1) { this.slash.open = false; return; }

            this.slash.startPos = validIdx;
            this.slash.query    = value.slice(validIdx + 1, pos);
            this.slash.activeIdx = 0;
            this.slash.open = true;
            this.loadCollections();
        },

        onPromptKeyDown(e) {
            if (!this.slash.open) return;
            const list = this.filteredCollections;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.slash.activeIdx = Math.min(this.slash.activeIdx + 1, Math.max(list.length - 1, 0));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.slash.activeIdx = Math.max(this.slash.activeIdx - 1, 0);
            } else if (e.key === 'Enter' || e.key === 'Tab') {
                if (list[this.slash.activeIdx]) {
                    e.preventDefault();
                    this.insertCollection(list[this.slash.activeIdx].name);
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                this.slash.open = false;
            }
        },

        insertCollection(name) {
            const ta = this.$refs.promptArea;
            if (!ta) return;
            const value = ta.value;
            const pos = ta.selectionStart;
            const before = value.slice(0, this.slash.startPos);
            const after  = value.slice(pos);
            const insertion = '/' + name + ' ';  // 末尾にスペース、後続テキストにつながるように
            const newValue = before + insertion + after;
            this.form.system_prompt = newValue;
            // 反映後にカーソル位置を調整
            this.$nextTick(() => {
                const newPos = before.length + insertion.length;
                try {
                    ta.focus();
                    ta.setSelectionRange(newPos, newPos);
                } catch (_) {}
                this.syncHighlightScroll();
            });
            this.slash.open = false;
        },

        // ===== /コレクション をハイライト =====
        // 行頭または空白の直後の "/トークン" をグレーチップで装飾する
        renderPromptHighlight(text) {
            const esc = (s) => (s || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            const tokenRegex = /(^|[\s])\/([^\s\/\\#?&]+)/gu;
            let result = '';
            let lastIndex = 0;
            for (const m of (text || '').matchAll(tokenRegex)) {
                const start = m.index + m[1].length; // / の位置
                result += esc((text || '').slice(lastIndex, start));
                const token = m[2];
                result += '<span class="col-tag">/' + esc(token) + '</span>';
                lastIndex = start + 1 + token.length;
            }
            result += esc((text || '').slice(lastIndex));
            // 末尾改行が高さに反映されるよう空白で締める
            if (result.endsWith('\n')) result += ' ';
            return result;
        },

        // テキストエリアのスクロール位置をハイライト層に同期
        syncHighlightScroll() {
            const ta = this.$refs.promptArea;
            const hi = this.$refs.promptHighlight;
            if (!ta || !hi) return;
            hi.scrollTop = ta.scrollTop;
            hi.scrollLeft = ta.scrollLeft;
        },

        openEdit(s) {
            this.mode = 'edit';
            this.selected = s;
            this.errorMessage = '';
            this.form = {
                id: s.id,
                skill_key: s.skill_key,
                name: s.name,
                description: s.description || '',
                system_prompt: s.system_prompt,
                is_active: !!s.is_active,
                is_default: !!s.is_default,
                is_default_summary: !!s.is_default_summary,
                is_default_reply: !!s.is_default_reply,
                show_in_summary: s.show_in_summary !== false,
                show_in_reply: s.show_in_reply !== false,
            };
            this.drawerOpen = true;
        },
        openNew() {
            this.mode = 'new';
            this.selected = null;
            this.errorMessage = '';
            this.form = { id: null, skill_key: '', name: '', description: '', system_prompt: '', is_active: true, is_default: false, is_default_summary: false, is_default_reply: false, show_in_summary: true, show_in_reply: true };
            this.drawerOpen = true;
        },
        closeDrawer() {
            this.drawerOpen = false;
            this.selected = null;
        },

        async save() {
            if (!this.form.name?.trim()) { this.errorMessage = 'スキル名を入力してください。'; return; }
            if (!this.form.system_prompt?.trim()) { this.errorMessage = 'システム指示プロンプトを入力してください。'; return; }
            this.saving = true;
            this.errorMessage = '';
            try {
                const isNew = this.mode === 'new';
                const url = isNew
                    ? '{{ route("settings.ai_skills.store") }}'
                    : `/settings/ai-skills/${this.form.id}`;
                const method = isNew ? 'POST' : 'PUT';
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        name: this.form.name,
                        description: this.form.description,
                        system_prompt: this.form.system_prompt,
                        is_active: this.form.is_active ? 1 : 0,
                        is_default_summary: this.form.is_default_summary ? 1 : 0,
                        is_default_reply: this.form.is_default_reply ? 1 : 0,
                        show_in_summary: this.form.show_in_summary ? 1 : 0,
                        show_in_reply: this.form.show_in_reply ? 1 : 0,
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.errorMessage = data.message || ('保存に失敗しました (HTTP ' + res.status + ')');
                    if (data.errors) {
                        this.errorMessage = Object.values(data.errors).flat().join(' / ');
                    }
                    return;
                }
                // ローカル一覧を更新 (自分以外のデフォルトフラグを解除して整合性を保つ)
                if (data.skill.is_default_summary) {
                    this.skills.forEach(x => { if (x.id !== data.skill.id) x.is_default_summary = false; });
                }
                if (data.skill.is_default_reply) {
                    this.skills.forEach(x => { if (x.id !== data.skill.id) x.is_default_reply = false; });
                }
                if (isNew) {
                    this.skills.push(data.skill);
                } else {
                    const idx = this.skills.findIndex(x => x.id === data.skill.id);
                    if (idx !== -1) this.skills[idx] = data.skill;
                }
                // 保存完了 → 一覧に戻る (ドロワーを閉じる)
                this.closeDrawer();
            } catch (e) {
                this.errorMessage = '通信エラー: ' + (e.message || '');
            } finally {
                this.saving = false;
            }
        },

        async deleteSkill() {
            if (!this.form.id) return;
            if (!confirm(`「${this.form.name}」を削除します。よろしいですか？`)) return;
            try {
                const res = await fetch(`/settings/ai-skills/${this.form.id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                });
                if (!res.ok) { this.errorMessage = '削除に失敗しました'; return; }
                this.skills = this.skills.filter(x => x.id !== this.form.id);
                this.closeDrawer();
            } catch (e) {
                this.errorMessage = '通信エラー: ' + (e.message || '');
            }
        },

        async resetDefaults() {
            if (!confirm('現在のスキルをすべて削除してデフォルトに戻します。よろしいですか？')) return;
            try {
                const res = await fetch('{{ route("settings.ai_skills.reset") }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) { alert('リセットに失敗しました'); return; }
                this.skills = data.skills || [];
                this.closeDrawer();
            } catch (e) {
                alert('通信エラー: ' + (e.message || ''));
            }
        },
    };
}
</script>

<style>
[x-cloak] { display: none !important; }
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* ===== プロンプト編集: /コレクション をグレーチップとして可視化 ===== */
.prompt-editor-container {
    position: relative;
}
/* 共通のフォント/サイズ/パディング/行間で完全に重ねる */
.prompt-editor-highlight,
.prompt-editor-input {
    font-family: ui-monospace, SFMono-Regular, "Menlo", "Consolas", "Liberation Mono", monospace;
    font-size: 0.75rem;        /* text-xs */
    line-height: 1.625;        /* leading-relaxed */
    padding: 0.5rem 0.75rem;   /* px-3 py-2 */
    letter-spacing: normal;
    word-spacing: normal;
    tab-size: 4;
}
.prompt-editor-highlight {
    position: absolute;
    inset: 0;
    border: 1px solid transparent;  /* textarea の border 分だけずらす */
    border-radius: 0.5rem;
    pointer-events: none;
    white-space: pre-wrap;
    word-wrap: break-word;
    overflow-y: auto;        /* スクロール同期するため */
    color: #111827;          /* この層がメインの表示 (textarea 側は透明) */
    background: transparent;
    z-index: 1;
}
.prompt-editor-input {
    position: relative;
    z-index: 2;
    background: transparent !important;
    color: transparent !important;         /* テキスト本体は透明にしてハイライト層に任せる */
    -webkit-text-fill-color: transparent;  /* Safari/Chrome 用 */
    caret-color: #111827;                  /* カーソルは黒で表示 */
}
.prompt-editor-input::selection {
    background-color: rgba(99, 102, 241, 0.25);
    color: transparent;
}
/* ハイライト層の本文はグレーの濃い字 (textarea が透明なので、見えるのはこちら) */
.prompt-editor-highlight {
    color: #111827;
}
/* /コレクション のチップ表示 */
.prompt-editor-highlight .col-tag {
    background-color: #e5e7eb;     /* gray-200 */
    color: #374151;                /* gray-700 visible で確認用 (透明上書きされるため見えない) */
    border-radius: 4px;
    padding: 1px 2px;
    margin: 0 -1px;                /* ハイライトのみで横幅変動しないよう吸収 */
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.04);
}
</style>
@endsection
