@extends('layouts.app')
@section('title', 'SSO設定')

@section('content')
<div class="flex flex-col h-full bg-gray-50 overflow-hidden">
    {{-- ヘッダー --}}
    <div class="shrink-0 px-10 py-8 bg-white border-b border-gray-200">
        <h1 class="text-3xl font-black text-gray-900 tracking-tighter uppercase mb-2">SSO設定</h1>
        <p class="text-sm text-gray-400 font-bold uppercase tracking-widest">Single Sign-On Configuration</p>
    </div>

    <div class="flex-1 overflow-y-auto p-10 space-y-10 custom-scrollbar">
        @if(session('success'))
            <div class="max-w-4xl bg-green-50 border border-green-100 text-green-700 px-6 py-4 rounded-2xl font-bold text-sm shadow-sm animate-in slide-in-from-top duration-300">
                {{ session('success') }}
            </div>
        @endif

        <div class="max-w-4xl bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100">
            <form action="{{ route('settings.sso.update') }}" method="POST">
                @csrf
                <div class="p-8 space-y-8">
                    {{-- 有効化スイッチ --}}
                    <div class="flex items-center justify-between p-6 bg-gray-50 rounded-2xl border border-gray-100">
                        <div>
                            <h3 class="text-base font-black text-gray-800 uppercase tracking-tighter">Google SSO を有効にする</h3>
                            <p class="text-xs text-gray-400 font-bold mt-1">Enable or disable Google authentication</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_enabled" value="1" class="sr-only peer" {{ $settings->is_enabled ? 'checked' : '' }}>
                            <div class="w-14 h-8 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    {{-- クライアントID --}}
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Google Client ID</label>
                        <input type="text" name="google_client_id" value="{{ old('google_client_id', $settings->google_client_id) }}" 
                            class="w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner"
                            placeholder="xxxxxxxxxxxx.apps.googleusercontent.com">
                    </div>

                    {{-- クライアントシークレット --}}
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Google Client Secret</label>
                        <input type="password" name="google_client_secret" value="{{ old('google_client_secret', $settings->google_client_secret) }}" 
                            class="w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner"
                            placeholder="••••••••••••••••••••">
                    </div>

                    {{-- リダイレクトURI --}}
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Redirect URI (ReadOnly)</label>
                        <input type="text" readonly value="{{ $settings->google_redirect_uri }}" 
                            class="w-full bg-gray-100 border-0 rounded-2xl px-5 py-4 text-gray-500 font-bold outline-none cursor-not-allowed">
                        <p class="text-[10px] text-amber-600 font-bold px-1">※Google Cloud Consoleの「承認済みのリダイレクト URI」にこれを登録してください。</p>
                    </div>

                    {{-- 招待必須スイッチ --}}
                    <div class="flex items-center justify-between p-6 bg-amber-50/30 rounded-2xl border border-amber-100">
                        <div>
                            <h3 class="text-base font-black text-amber-700 uppercase tracking-tighter">ログインに招待を必須とする</h3>
                            <p class="text-xs text-amber-600 font-bold mt-1">Restrict login to invited users only</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="require_invitation" value="1" class="sr-only peer" {{ $settings->require_invitation ? 'checked' : '' }}>
                            <div class="w-14 h-8 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>
                </div>

                <div class="px-8 py-6 bg-gray-50 border-t border-gray-100 flex justify-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-black px-12 py-4 rounded-2xl shadow-xl shadow-blue-100 transition-all active:scale-[0.98]">
                        設定を保存する
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
