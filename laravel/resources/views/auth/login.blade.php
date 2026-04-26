<x-guest-layout>
    <h2 class="text-2xl font-black text-center text-gray-900 mb-8 tracking-tighter uppercase">Sign In</h2>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />
    @if(session('error'))
        <div class="mb-4 font-bold text-sm text-red-600 text-center bg-red-50 p-3 rounded-xl border border-red-100">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf

        <!-- Email Address -->
        <div class="space-y-1">
            <label for="email" class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="block w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="space-y-1">
            <label for="password" class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                class="block w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between px-1">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" name="remember">
                <span class="ms-2 text-sm font-bold text-gray-400">Remember me</span>
            </label>
            @if (Route::has('password.request'))
                <a class="text-sm font-bold text-blue-600 hover:text-blue-700 transition-colors" href="{{ route('password.request') }}">
                    Forgot password?
                </a>
            @endif
        </div>

        <div class="pt-4 flex flex-col gap-4">
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-2xl shadow-xl shadow-blue-100 transition-all active:scale-[0.98]">
                Log in
            </button>

            <div class="relative py-4 flex items-center justify-center">
                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-100"></div></div>
                <span class="relative bg-white px-4 text-[10px] font-black text-gray-300 uppercase tracking-widest">or</span>
            </div>

            <a href="{{ route('auth.redirect', 'google') }}" class="w-full bg-white border-2 border-gray-100 hover:border-gray-200 text-gray-700 font-bold py-4 rounded-2xl shadow-sm transition-all flex items-center justify-center gap-3 active:scale-[0.98]">
                <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" class="w-5 h-5">
                Sign in with Google
            </a>
            
            @if (config('app.signup_enabled', false))
                <a class="text-center text-sm font-bold text-gray-400 hover:text-gray-600 transition-colors mt-2" href="{{ route('register') }}">
                    Create an account
                </a>
            @endif
        </div>
    </form>
</x-guest-layout>
