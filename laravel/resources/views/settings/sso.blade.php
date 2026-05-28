@extends('layouts.app')
@section('title', 'SSO設定')

@section('css')
<style>
    /* ===== SSO設定ページのダークモード上書き =====
       他の設定ページと統一感を持たせるため、ラベル・見出しを明示的に白系で上書き. */
    html.theme-dark .text-gray-600,
    html.theme-dark .text-gray-700,
    html.theme-dark .text-gray-800,
    html.theme-dark .text-gray-900 { color: #ffffff !important; }
    html.theme-dark label,
    html.theme-dark label > span { color: #ffffff !important; }
    html.theme-dark h1,
    html.theme-dark h2 { color: #ffffff !important; }
    html.theme-dark .text-xs.text-gray-400,
    html.theme-dark .text-xs.text-gray-500 { color: #dcddde !important; }
    /* オレンジ系警告ブロックは背景が暗いと文字が見えなくなるので少し色味を上げる */
    html.theme-dark .text-amber-700,
    html.theme-dark .text-amber-600 { color: #fcd34d !important; }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-y-auto p-6">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-xl font-semibold text-gray-800 mb-6">SSO設定</h1>

        @if(session('success'))
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('settings.sso.update') }}" method="POST"
              class="bg-white rounded-lg border border-gray-200 shadow-sm">
            @csrf
            <div class="p-6 space-y-5">
                {{-- 有効化スイッチ --}}
                <div class="flex items-center justify-between gap-4 px-4 py-3 bg-gray-50 rounded-lg border border-gray-100">
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-gray-800">Google SSO を有効にする</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Google アカウントでのログインを許可します</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" name="is_enabled" value="1" class="sr-only peer" {{ $settings->is_enabled ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                {{-- クライアントID --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Google Client ID</label>
                    <input type="text" name="google_client_id"
                           value="{{ old('google_client_id', $settings->google_client_id) }}"
                           placeholder="xxxxxxxxxxxx.apps.googleusercontent.com"
                           class="w-full bg-white border border-gray-300 rounded-md px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                </div>

                {{-- クライアントシークレット --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Google Client Secret</label>
                    <input type="password" name="google_client_secret"
                           value="{{ old('google_client_secret', $settings->google_client_secret) }}"
                           placeholder="••••••••••••••••••••"
                           class="w-full bg-white border border-gray-300 rounded-md px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                </div>

                {{-- リダイレクトURI --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Redirect URI <span class="text-gray-400 font-normal">(読み取り専用)</span></label>
                    <input type="text" readonly value="{{ $settings->google_redirect_uri }}"
                           class="w-full bg-gray-100 border border-gray-200 rounded-md px-3 py-2 text-sm text-gray-500 cursor-not-allowed font-mono">
                    <p class="text-xs text-amber-600 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        Google Cloud Console の「承認済みのリダイレクト URI」にこの値を登録してください。
                    </p>
                </div>

                {{-- 招待必須スイッチ --}}
                <div class="flex items-center justify-between gap-4 px-4 py-3 bg-amber-50/40 rounded-lg border border-amber-100">
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-amber-700">ログインに招待を必須とする</h3>
                        <p class="text-xs text-amber-600 mt-0.5">招待されたメールアドレスのみログイン可能になります</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" name="require_invitation" value="1" class="sr-only peer" {{ $settings->require_invitation ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                    </label>
                </div>
            </div>

            <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 flex justify-end">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold px-5 py-2 rounded-md shadow-sm transition-colors">
                    設定を保存
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
