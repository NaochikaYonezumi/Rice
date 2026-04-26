<x-guest-layout>
    <h2 class="text-2xl font-black text-center text-gray-900 mb-4 tracking-tighter uppercase">Forgot Password</h2>
        
        <div class="mb-8 text-sm text-gray-500 font-medium text-center leading-relaxed">
            Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="mb-6" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
            @csrf

            <!-- Email Address -->
            <div class="space-y-1">
                <label for="email" class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="block w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="pt-4 flex flex-col gap-4">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-2xl shadow-xl shadow-blue-100 transition-all active:scale-[0.98]">
                    Email Password Reset Link
                </button>
                <a class="text-center text-sm font-bold text-gray-400 hover:text-gray-600 transition-colors" href="{{ route('login') }}">
                    Back to Login
                </a>
            </div>
        </form>
    </div>
</x-guest-layout>
