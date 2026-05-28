@extends('layouts.app')
@section('title', 'AI設定')

@section('css')
<style>
    /* ===== ダークモードで AI 設定ページのラベルが見えない問題を解消 =====
       プロバイダ選択 (Ollama/Claude/Gemini) のラジオラベルやセクションタイトルが
       暗背景に対して同系色になってしまうため、明示的に白系へ上書き. */
    html.theme-dark .text-gray-600,
    html.theme-dark .text-gray-700,
    html.theme-dark .text-gray-800,
    html.theme-dark .text-gray-900 { color: #ffffff !important; }
    /* ラジオ/チェックボックスのラベルテキスト */
    html.theme-dark label,
    html.theme-dark label .text-sm,
    html.theme-dark label .text-xs,
    html.theme-dark label > span { color: #ffffff !important; }
    /* 補足の (APIキー要) などはやや薄めで OK */
    html.theme-dark label .text-gray-400 { color: #b9bbbe !important; }
    /* h1/h2 (AI設定 / デフォルトモデル / Gemini APIキー 等) も白に */
    html.theme-dark h1,
    html.theme-dark h2 { color: #ffffff !important; }
    /* セクション本文の説明文は若干薄めの白 (var(--rd-text-mute)) のまま見えるよう調整 */
    html.theme-dark .text-xs.text-gray-400,
    html.theme-dark .text-xs.text-gray-500 { color: #dcddde !important; }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-y-auto p-6">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-xl font-semibold text-gray-800 mb-6">AI設定</h1>

        @if(session('success'))
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.ai.update') }}"
              x-data="{
                  provider: '{{ old('default_provider', $settings->default_provider) }}',
                  showAnthropicKey: false,
                  showGeminiKey: false,
              }">
            @csrf

            {{-- Claude API キー --}}
            <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
                <h2 class="font-medium text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-orange-400 inline-block"></span>
                    Anthropic (Claude) API キー
                </h2>

                <div class="space-y-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">API キー</label>
                        <div class="flex gap-2">
                            <input
                                :type="showAnthropicKey ? 'text' : 'password'"
                                name="anthropic_api_key"
                                value="{{ old('anthropic_api_key') }}"
                                placeholder="{{ $settings->anthropic_api_key ? '変更する場合のみ入力（空欄で現在のキーを維持）' : 'sk-ant-api03-...' }}"
                                class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300 font-mono @error('anthropic_api_key') border-red-400 @enderror"
                                autocomplete="off"
                            >
                            <button type="button" @click="showAnthropicKey = !showAnthropicKey"
                                class="px-3 py-2 border border-gray-200 rounded-lg text-xs text-gray-500 hover:bg-gray-50 shrink-0">
                                <span x-text="showAnthropicKey ? '隠す' : '表示'"></span>
                            </button>
                        </div>
                        @error('anthropic_api_key')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-gray-400 mt-1">空欄のまま保存すると既存のキーが維持されます。</p>
                    </div>

                    @if($settings->anthropic_api_key)
                        <p class="text-xs text-green-600 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            API キーが設定されています
                        </p>
                    @else
                        <p class="text-xs text-amber-500 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            未設定（Claudeは使用できません）
                        </p>
                    @endif
                </div>
            </div>

            {{-- Gemini API キー --}}
            <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
                <h2 class="font-medium text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span>
                    Google (Gemini) API キー
                </h2>

                <div class="space-y-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">API キー</label>
                        <div class="flex gap-2">
                            <input
                                :type="showGeminiKey ? 'text' : 'password'"
                                name="gemini_api_key"
                                value="{{ old('gemini_api_key') }}"
                                placeholder="{{ $settings->gemini_api_key ? '変更する場合のみ入力（空欄で現在のキーを維持）' : 'AIza...' }}"
                                class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300 font-mono @error('gemini_api_key') border-red-400 @enderror"
                                autocomplete="off"
                            >
                            <button type="button" @click="showGeminiKey = !showGeminiKey"
                                class="px-3 py-2 border border-gray-200 rounded-lg text-xs text-gray-500 hover:bg-gray-50 shrink-0">
                                <span x-text="showGeminiKey ? '隠す' : '表示'"></span>
                            </button>
                        </div>
                        @error('gemini_api_key')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-gray-400 mt-1">空欄のまま保存すると既存のキーが維持されます。</p>
                    </div>

                    @if($settings->gemini_api_key)
                        <p class="text-xs text-green-600 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            API キーが設定されています
                        </p>
                    @else
                        <p class="text-xs text-amber-500 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            未設定（Geminiは使用できません）
                        </p>
                    @endif
                </div>
            </div>

            {{-- デフォルトモデル --}}
            <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
                <h2 class="font-medium text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span>
                    デフォルトモデル
                </h2>
                <p class="text-xs text-gray-400 mb-4">Rice Chat を開いたときに最初に選択されるモデルです。</p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-600 mb-2">プロバイダー</label>
                        <div class="flex flex-wrap gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="default_provider" value="ollama"
                                       x-model="provider" class="text-blue-600">
                                <span class="text-sm">Ollama（ローカル）</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="default_provider" value="claude"
                                       x-model="provider" class="text-blue-600">
                                <span class="text-sm">Claude（API）</span>
                                @if(!$settings->anthropic_api_key)
                                    <span class="text-xs text-gray-400">（APIキー要）</span>
                                @endif
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="default_provider" value="gemini"
                                       x-model="provider" class="text-blue-600">
                                <span class="text-sm">Gemini（API）</span>
                                @if(!$settings->gemini_api_key)
                                    <span class="text-xs text-gray-400">（APIキー要）</span>
                                @endif
                            </label>
                        </div>
                        @error('default_provider')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600 mb-1">モデル</label>

                        {{-- Ollama モデル選択（Ollama 選択時のみ送信） --}}
                        <div x-show="provider === 'ollama'">
                            <select name="default_model"
                                    :disabled="provider !== 'ollama'"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                                @forelse($models['ollama'] ?? [] as $m)
                                    <option value="{{ $m }}"
                                        {{ old('default_model', $settings->default_model) === $m ? 'selected' : '' }}>
                                        {{ $m }}
                                    </option>
                                @empty
                                    <option value="">モデルが見つかりません</option>
                                @endforelse
                            </select>
                            @if(empty($models['ollama']))
                                <p class="text-xs text-amber-500 mt-1">Ollamaにモデルがインストールされていません。<code class="bg-gray-100 px-1 rounded">docker compose exec ollama ollama pull llama3.2:1b</code> で追加できます（CPU向け軽量モデル）。</p>
                            @endif
                        </div>

                        {{-- Claude モデル選択（Claude 選択時のみ送信） --}}
                        <div x-show="provider === 'claude'">
                            <select name="default_model"
                                    :disabled="provider !== 'claude'"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                                @foreach($models['claude'] ?? [] as $m)
                                    <option value="{{ $m['id'] }}"
                                        {{ old('default_model', $settings->default_model) === $m['id'] ? 'selected' : '' }}>
                                        {{ $m['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @if(empty($models['claude']))
                                <p class="text-xs text-amber-500 mt-1">Claudeモデルの一覧を取得できませんでした。</p>
                            @endif
                        </div>

                        {{-- Gemini モデル選択（Gemini 選択時のみ送信） --}}
                        <div x-show="provider === 'gemini'">
                            <select name="default_model"
                                    :disabled="provider !== 'gemini'"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                                @foreach($models['gemini'] ?? [] as $m)
                                    <option value="{{ $m['id'] }}"
                                        {{ old('default_model', $settings->default_model) === $m['id'] ? 'selected' : '' }}>
                                        {{ $m['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @if(empty($models['gemini']))
                                <p class="text-xs text-amber-500 mt-1">Geminiモデルの一覧を取得できませんでした。</p>
                            @endif
                        </div>

                        @error('default_model')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- デフォルト返信プロンプト --}}
            <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
                <h2 class="font-medium text-gray-700 mb-1 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-indigo-400 inline-block"></span>
                    AIデフォルト返信プロンプト
                </h2>
                <p class="text-xs text-gray-400 mb-4">AIアシスタントがメール返信案を生成する際のデフォルト指示文です。AIパネルで上書き可能です。</p>
                <textarea
                    name="default_reply_prompt"
                    rows="5"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-y"
                    placeholder="例: このスレッドの内容を把握した上で、丁寧で的確な返信を日本語で作成してください。"
                >{{ old('default_reply_prompt', $settings->default_reply_prompt) }}</textarea>
                @error('default_reply_prompt')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-6 py-2 rounded-lg">
                保存
            </button>
        </form>
    </div>
</div>
@endsection
