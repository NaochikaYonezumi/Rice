<x-guest-layout>
    <h2 class="text-2xl font-black text-center text-gray-900 mb-8 tracking-tighter uppercase">Create Account</h2>

    <form method="POST" action="{{ route('register') }}" class="space-y-6">
            @csrf

            <!-- Name -->
            <div class="space-y-1">
                <label for="name" class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                    class="block w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <!-- Email Address -->
            <div class="space-y-1">
                <label for="email" class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                    class="block w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <!-- Password -->
            <div class="space-y-1">
                <label for="password" class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Password</label>
                <input id="password" type="password" name="password" required autocomplete="new-password"
                    class="block w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <!-- Confirm Password -->
            <div class="space-y-1">
                <label for="password_confirmation" class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Confirm Password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                    class="block w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="pt-4 flex flex-col gap-4">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-2xl shadow-xl shadow-blue-100 transition-all active:scale-[0.98]">
                    Register
                </button>
                <a class="text-center text-sm font-bold text-gray-400 hover:text-gray-600 transition-colors" href="{{ route('login') }}">
                    Already registered?
                </a>
            </div>
        </form>
    </div>
</x-guest-layout>
