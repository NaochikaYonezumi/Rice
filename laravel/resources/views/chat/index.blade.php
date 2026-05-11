@extends('layouts.app')
@section('title', 'Rice Chat')

@section('content')
<div class="flex flex-col h-full" x-data="riceChat()" x-init="loadModels()">

    {{-- ヘッダー + モデル選択 --}}
    <div class="px-6 py-3 border-b border-gray-200 bg-white flex items-center gap-4 shrink-0">
        <h1 class="font-semibold text-gray-800 shrink-0">Rice Chat</h1>

        <div class="flex items-center gap-2 ml-auto">
            {{-- プロバイダー切り替え --}}
            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-xs">
                <button @click="setProvider('ollama')"
                        :class="provider === 'ollama' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="px-3 py-1.5 transition-colors">Ollama</button>
                <button @click="setProvider('claude')"
                        :class="provider === 'claude' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        :title="!hasClaudeKey ? 'APIキーが未設定です' : ''"
                        class="px-3 py-1.5 transition-colors border-l border-gray-200">Claude</button>
                <button @click="setProvider('gemini')"
                        :class="provider === 'gemini' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        :title="!hasGeminiKey ? 'APIキーが未設定です' : ''"
                        class="px-3 py-1.5 transition-colors border-l border-gray-200">Gemini</button>
            </div>

            {{-- モデル選択 --}}
            <select x-model="selectedModel"
                    class="border border-gray-200 rounded-lg px-3 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-300 bg-white min-w-[180px]">
                <template x-if="loadingModels">
                    <option>読み込み中...</option>
                </template>
                <template x-if="!loadingModels && currentModels.length === 0">
                    <option value="">モデルなし</option>
                </template>
                <template x-for="m in currentModels" :key="m.id || m">
                    <option :value="m.id || m" x-text="m.name || m"></option>
                </template>
            </select>

            <template x-if="provider === 'claude' && !hasClaudeKey">
                <span class="text-xs text-amber-500">⚠ APIキー未設定</span>
            </template>
            <template x-if="provider === 'gemini' && !hasGeminiKey">
                <span class="text-xs text-amber-500">⚠ APIキー未設定</span>
            </template>
        </div>
    </div>

    {{-- メッセージ一覧 --}}
    <div class="flex-1 overflow-y-auto p-6 space-y-4" id="message-area">
        <template x-if="messages.length === 0">
            <p class="text-center text-gray-400 text-sm mt-16">質問を入力してください。登録済みのドキュメント・URLをもとに回答します。</p>
        </template>
        <template x-for="msg in messages" :key="msg.id">
            <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                <div class="max-w-[80%]">
                    <div :class="msg.role === 'user'
                        ? 'bg-blue-600 text-white rounded-2xl rounded-br-sm px-4 py-2 text-sm'
                        : 'bg-white border border-gray-200 rounded-2xl rounded-bl-sm px-4 py-2 text-sm text-gray-800 whitespace-pre-wrap'">
                        <template x-if="msg.status === 'pending'">
                            <span class="text-gray-400 animate-pulse">回答を生成中...</span>
                        </template>
                        <template x-if="msg.status !== 'pending'">
                            <span x-text="msg.text"></span>
                        </template>
                    </div>
                    <div class="flex flex-col gap-1 mt-1 px-1">
                        <template x-if="msg.modelLabel">
                            <span class="text-xs text-gray-300" x-text="msg.modelLabel"></span>
                        </template>
                        <template x-if="msg.sources && msg.sources.length > 0">
                            <div class="flex flex-col gap-1.5 mt-1">
                                <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">参照</span>
                                <template x-for="(src, idx) in msg.sources" :key="idx">
                                    <div class="bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 text-xs">
                                        <a :href="sourceHref(src)" target="_blank" rel="noopener"
                                           class="text-blue-600 hover:underline font-bold break-all"
                                           x-text="sourceLabel(src)"></a>
                                        <template x-if="sourceSnippet(src)">
                                            <p class="text-[11px] text-gray-500 mt-1 line-clamp-2" x-text="sourceSnippet(src)"></p>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- 入力欄 --}}
    <div class="shrink-0 border-t border-gray-200 bg-white px-6 py-4">
        <div class="flex gap-3">
            <input type="text" x-model="question" placeholder="質問を入力..."
                   @keydown.enter.prevent="send()"
                   :disabled="waiting"
                   class="flex-1 border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300 disabled:opacity-50">
            <button @click="send()" :disabled="waiting || !question.trim()"
                    class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm px-5 py-2 rounded-xl">
                送信
            </button>
        </div>
    </div>
</div>

<script>
function riceChat() {
    return {
        question: '',
        waiting: false,
        messages: [],
        _msgId: 0,

        provider: 'ollama',
        selectedModel: '',
        ollamaModels: [],
        claudeModels: [],
        geminiModels: [],
        hasClaudeKey: false,
        hasGeminiKey: false,
        loadingModels: true,

        get currentModels() {
            if (this.provider === 'claude') return this.claudeModels;
            if (this.provider === 'gemini') return this.geminiModels;
            return this.ollamaModels;
        },

        async loadModels() {
            try {
                const res = await fetch('{{ route("chat.models") }}');
                const data = await res.json();
                this.ollamaModels = data.ollama || [];
                this.claudeModels = data.claude || [];
                this.geminiModels = data.gemini || [];
                this.hasClaudeKey = data.has_claude_key || false;
                this.hasGeminiKey = data.has_gemini_key || false;
                if (this.ollamaModels.length > 0) this.selectedModel = this.ollamaModels[0];
            } catch (e) {
                console.error('モデル取得失敗:', e);
            } finally {
                this.loadingModels = false;
            }
        },

        setProvider(p) {
            this.provider = p;
            const models = this.currentModels;
            this.selectedModel = models.length > 0 ? (models[0].id || models[0]) : '';
        },

        async send() {
            const q = this.question.trim();
            if (!q || this.waiting) return;

            this.question = '';
            this.waiting = true;
            this._msgId++;

            const modelLabel = this.selectedModel
                ? `${this.provider === 'claude' ? 'Claude' : this.provider === 'gemini' ? 'Gemini' : 'Ollama'} / ${this.selectedModel}`
                : null;

            this.messages.push({ id: 'u' + this._msgId, role: 'user', text: q, status: 'done' });

            this._msgId++;
            const botId = 'b' + this._msgId;
            this.messages.push({ id: botId, role: 'bot', text: '', status: 'pending', sources: [], modelLabel });
            this.$nextTick(() => this._scrollBottom());

            try {
                const res = await fetch('{{ route("chat.query") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        question: q,
                        provider: this.provider,
                        model: this.selectedModel || null,
                    }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || res.status);

                await this._poll(data.id, botId);
            } catch (e) {
                this._updateMsg(botId, { text: 'エラー: ' + e.message, status: 'error' });
            } finally {
                this.waiting = false;
            }
        },

        async _poll(jobId, botId) {
            const maxWait = 180000;
            const interval = 2000;
            const started = Date.now();

            while (Date.now() - started < maxWait) {
                await new Promise(r => setTimeout(r, interval));
                try {
                    const res = await fetch(`/query/${jobId}/result`);
                    const data = await res.json();

                    if (data.status === 'done') {
                        this._updateMsg(botId, { text: data.answer, status: 'done', sources: data.sources || [] });
                        this._scrollBottom();
                        return;
                    }
                    if (data.status === 'error') {
                        this._updateMsg(botId, { text: 'エラー: ' + (data.error || '不明なエラー'), status: 'error' });
                        return;
                    }
                } catch (e) {
                    // 一時的なネットワークエラーは無視して再試行
                }
            }
            this._updateMsg(botId, { text: 'タイムアウト：回答の生成に時間がかかっています。', status: 'error' });
        },

        _updateMsg(id, patch) {
            const idx = this.messages.findIndex(m => m.id === id);
            if (idx !== -1) this.messages[idx] = { ...this.messages[idx], ...patch };
        },

        _scrollBottom() {
            const el = document.getElementById('message-area');
            if (el) el.scrollTop = el.scrollHeight;
        },

        // ソース表示用ヘルパー: rag-python は {text, score, url} の配列を返すが、
        // 文字列が来ても落ちないようにフォールバックを入れる
        _sourceUrl(src) {
            if (typeof src === 'string') return src;
            if (src && typeof src === 'object') return src.url || src.source_url || '';
            return '';
        },
        _sourceText(src) {
            if (src && typeof src === 'object') return src.text || src.snippet || '';
            return '';
        },
        sourceHref(src) {
            const url = this._sourceUrl(src);
            if (!url) return '#';
            // メール本文への内部リンクパターンを検出し、メール一覧画面が `?thread=` / `?email=` を解釈してスレッドを開く
            const m = url.match(/(?:^|\/)emails\/(\d+)(?:[\/?#]|$)/);
            if (m) return '/?email=' + m[1];
            const tm = url.match(/[?&]thread=(\d+)/);
            if (tm) return '/?thread=' + tm[1];
            return url;
        },
        sourceLabel(src) {
            const url = this._sourceUrl(src);
            if (url) {
                // 内部メールリンクの場合は分かりやすいラベルに置換
                const m = url.match(/(?:^|\/)emails\/(\d+)(?:[\/?#]|$)/) || url.match(/[?&]thread=(\d+)/);
                if (m) return 'メールスレッド #' + m[1];
                return url;
            }
            const text = this._sourceText(src);
            return text ? text.substring(0, 80) + (text.length > 80 ? '…' : '') : '(出典不明)';
        },
        sourceSnippet(src) {
            const text = this._sourceText(src);
            if (!text) return '';
            return text.length > 200 ? text.substring(0, 200) + '…' : text;
        },
    };
}
</script>
@endsection
