<section x-data="signatureForm({{ json_encode($user->signature_enabled !== false) }})">
    <header>
        <h3 class="text-lg font-bold text-gray-900">
            <i class="fas fa-signature mr-1 text-blue-500"></i> メール署名
        </h3>
        <p class="mt-1 text-sm text-gray-600">
            あなた個人のメール送信時に末尾へ付与される署名を設定します。未設定の場合はグローバルの「Agent 署名」(設定 → AI) が使用されます。
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        {{-- 既存の name/email を一緒に送信 (バリデーション required のため) --}}
        <input type="hidden" name="name"  value="{{ $user->name }}">
        <input type="hidden" name="email" value="{{ $user->email }}">

        {{-- 有効/無効トグル --}}
        <div class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded-xl px-4 py-3">
            <div>
                <p class="text-sm font-bold text-gray-800">署名を有効にする</p>
                <p class="text-xs text-gray-500">無効の場合、送信メールに署名は付与されません</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="signature_enabled" value="1" class="sr-only peer" x-model="enabled">
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
        </div>

        {{-- プレーンテキスト --}}
        <div>
            <label for="signature_text" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">テキスト署名</label>
            <textarea id="signature_text" name="signature_text" rows="5"
                      x-model="textSig"
                      :disabled="!enabled"
                      class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all disabled:opacity-50"
                      placeholder="---&#10;山田 太郎&#10;サポート窓口&#10;example@yourcompany.com">{{ old('signature_text', $user->signature_text) }}</textarea>
            @error('signature_text')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-gray-400">最大 2000 文字。HTML 署名が空の場合に使用されます。</p>
        </div>

        {{-- HTML 署名 --}}
        <div>
            <label for="signature_html" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">HTML 署名 (任意)</label>
            <textarea id="signature_html" name="signature_html" rows="6"
                      x-model="htmlSig"
                      :disabled="!enabled"
                      class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all disabled:opacity-50"
                      placeholder='<p><strong>山田 太郎</strong></p><p>サポート窓口</p>'>{{ old('signature_html', $user->signature_html) }}</textarea>
            @error('signature_html')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-gray-400">最大 10000 文字。HTML 署名が設定されていれば優先的に使用されます。script/style 等は除去されます。</p>
        </div>

        {{-- プレビュー --}}
        <div>
            <p class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">プレビュー</p>
            <div class="bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 min-h-[80px]"
                 x-show="enabled"
                 x-html="previewHtml"></div>
            <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-400 min-h-[80px] italic" x-show="!enabled">
                署名は無効になっています
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-xl text-sm transition-colors">
                署名を保存
            </button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-emerald-600 font-bold">保存しました</p>
            @endif
        </div>
    </form>
</section>

<script>
function signatureForm(initialEnabled) {
    return {
        enabled: !!initialEnabled,
        textSig: @json(old('signature_text', $user->signature_text ?? '')),
        htmlSig: @json(old('signature_html', $user->signature_html ?? '')),
        get previewHtml() {
            const escape = (s) => String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\n/g, '<br>');
            if (this.htmlSig && this.htmlSig.trim() !== '') {
                // HTML 署名はそのまま (サーバー側でサニタイズ済の値)
                return this.htmlSig;
            }
            if (this.textSig && this.textSig.trim() !== '') {
                return escape(this.textSig);
            }
            return '<span class="text-gray-400 italic">グローバル署名 (Agent 署名) が使用されます</span>';
        },
    };
}
</script>
