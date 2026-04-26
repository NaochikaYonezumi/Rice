<x-guest-layout>
    <h2 class="text-2xl font-black text-center text-gray-900 mb-4 tracking-tighter uppercase">Verify Email</h2>

        <div class="mb-8 text-sm text-gray-500 font-medium text-center leading-relaxed">
            Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="mb-6 font-bold text-sm text-green-600 text-center">
                A new verification link has been sent to the email address you provided during registration.
            </div>
        @endif

        <div class="mt-4 flex flex-col gap-4">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-2xl shadow-xl shadow-blue-100 transition-all active:scale-[0.98]">
                    Resend Verification Email
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-sm font-bold text-gray-400 hover:text-gray-600 transition-colors py-2">
                    Log Out
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>
