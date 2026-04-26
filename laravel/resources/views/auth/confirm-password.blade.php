<x-guest-layout>
    <h2 class="text-2xl font-black text-center text-gray-900 mb-4 tracking-tighter uppercase">Confirm Security</h2>

        <div class="mb-8 text-sm text-gray-500 font-medium text-center leading-relaxed">
            This is a secure area of the application. Please confirm your password before continuing.
        </div>

        <form method="POST" action="{{ route('password.confirm') }}" class="space-y-6">
            @csrf

            <!-- Password -->
            <div class="space-y-1">
                <label for="password" class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                    class="block w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-2xl shadow-xl shadow-blue-100 transition-all active:scale-[0.98]">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</x-guest-layout>
