<x-guest-layout>
    <h2 class="text-2xl font-black text-center text-gray-900 mb-4 tracking-tighter uppercase">アカウント登録</h2>

    <div class="mb-8 text-sm text-gray-500 font-medium text-center leading-relaxed">
        Riceへの招待を受けています。氏名とパスワードを設定して、アカウント作成を完了させてください。
        <div class="mt-2 text-blue-600 font-bold">{{ $invitation->email }}</div>
    </div>

    <form method="POST" action="{{ route('invitations.accept.store', $invitation->token) }}" class="space-y-6">
        @csrf

        <!-- Name -->
        <div class="space-y-1">
            <label for="name" class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">氏名 / 表示名</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                class="block w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="space-y-1">
            <label for="password" class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">パスワード</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                class="block w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="space-y-1">
            <label for="password_confirmation" class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">パスワード（確認用）</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                class="block w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="pt-4">
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-2xl shadow-xl shadow-blue-100 transition-all active:scale-[0.98]">
                アカウントを作成してログイン
            </button>
        </div>
    </form>
</x-guest-layout>

</x-guest-layout>
