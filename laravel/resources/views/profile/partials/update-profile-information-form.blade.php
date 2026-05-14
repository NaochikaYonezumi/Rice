<section>
    <header>
        <h3 class="text-lg font-bold text-gray-900">
            Profile Information
        </h3>
        <p class="mt-1 text-sm text-gray-600">
            Update your account's profile information and email address.
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Name</label>
            <input id="name" name="name" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name" />
            @error('name')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="display_name" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">表示名 (任意)</label>
            <input id="display_name" name="display_name" type="text" maxlength="128"
                   value="{{ old('display_name', $user->display_name) }}"
                   placeholder="メール署名や AI 生成に使われる表示名"
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
            <p class="mt-1 text-xs text-gray-500">空ならアカウント名 (Name) が使われます。</p>
            @error('display_name')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="signature" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">メール署名</label>
            <textarea id="signature" name="signature" rows="6" maxlength="5000"
                      placeholder="---&#10;〇〇株式会社&#10;氏名: 米住 直親&#10;Email: example@example.com"
                      class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all resize-y">{{ old('signature', $user->signature) }}</textarea>
            <p class="mt-1 text-xs text-gray-500">
                AI 返信生成や手動返信で末尾に挿入される署名。空なら全体設定 (AI設定の Agent 署名) を使用します。
            </p>
            @error('signature')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Email</label>
            <input id="email" name="email" type="email" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" value="{{ old('email', $user->email) }}" required autocomplete="username" />
            @error('email')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        Your email address is unverified.
                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Click here to re-send the verification email.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            A new verification link has been sent to your email address.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-md shadow-blue-500/20 transition-all active:scale-95">
                Save
            </button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >Saved.</p>
            @endif
        </div>
    </form>
</section>
