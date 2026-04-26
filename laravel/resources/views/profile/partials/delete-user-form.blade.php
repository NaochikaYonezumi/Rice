<section class="space-y-6">
    <header>
        <h3 class="text-lg font-bold text-gray-900">
            Delete Account
        </h3>
        <p class="mt-1 text-sm text-gray-600">
            Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.
        </p>
    </header>

    <div x-data="{ confirmingUserDeletion: false }">
        <button
            type="button"
            class="bg-red-600 hover:bg-red-700 text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-md shadow-red-500/20 transition-all active:scale-95"
            @click="confirmingUserDeletion = true"
        >Delete Account</button>

        <template x-teleport="body">
            <div
                x-show="confirmingUserDeletion"
                class="fixed inset-0 z-[200] flex items-center justify-center overflow-y-auto px-4 py-6 sm:px-0"
                x-cloak
            >
                <div
                    x-show="confirmingUserDeletion"
                    class="fixed inset-0 transform transition-all"
                    @click="confirmingUserDeletion = false"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                >
                    <div class="absolute inset-0 bg-gray-500/75 backdrop-blur-sm"></div>
                </div>

                <div
                    x-show="confirmingUserDeletion"
                    class="mb-6 bg-white rounded-2xl overflow-hidden shadow-2xl transform transition-all sm:w-full sm:max-w-lg"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <form method="post" action="{{ route('profile.destroy') }}" class="p-8">
                        @csrf
                        @method('delete')

                        <h2 class="text-lg font-bold text-gray-900">
                            Are you sure you want to delete your account?
                        </h2>

                        <p class="mt-1 text-sm text-gray-600">
                            Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.
                        </p>

                        <div class="mt-6">
                            <label for="password" class="sr-only">Password</label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                                placeholder="Password"
                            />
                            @error('password', 'userDeletion')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-8 flex justify-end gap-3">
                            <button
                                type="button"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-xl text-sm font-bold transition-all active:scale-95"
                                @click="confirmingUserDeletion = false"
                            >
                                Cancel
                            </button>

                            <button
                                type="submit"
                                class="bg-red-600 hover:bg-red-700 text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-md shadow-red-500/20 transition-all active:scale-95"
                            >
                                Delete Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
</section>
