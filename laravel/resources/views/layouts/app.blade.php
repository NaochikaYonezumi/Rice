<!DOCTYPE html>
<html lang="ja" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Rice')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .sidebar-panel { min-width: 0; }
        .prose-email img { max-width: 100%; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="{{ asset('css/thread-list.css') }}">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</head>
<body class="h-full bg-gray-50 text-gray-800 font-sans">

<div class="flex h-full">
    {{-- グローバルサイドナビ --}}
    <nav
        x-data="{
            open: localStorage.getItem('navOpen') !== 'false',
            mailOpen: localStorage.getItem('navMailOpen') !== null ? localStorage.getItem('navMailOpen') === 'true' : {{ request()->routeIs('emails.*', 'attachments.*', 'approvals.*') ? 'true' : 'false' }},
            aiOpen: localStorage.getItem('navAiOpen') !== null ? localStorage.getItem('navAiOpen') === 'true' : {{ request()->routeIs('chat.*', 'scrape.*', 'documents.*') ? 'true' : 'false' }},
            toggle() { this.open = !this.open; localStorage.setItem('navOpen', this.open); },
            toggleMail() { this.mailOpen = !this.mailOpen; localStorage.setItem('navMailOpen', this.mailOpen); },
            toggleAi() { this.aiOpen = !this.aiOpen; localStorage.setItem('navAiOpen', this.aiOpen); }
        }"
        :class="open ? 'w-52' : 'w-12'"
        class="shrink-0 bg-gray-900 text-gray-200 flex flex-col py-4 gap-1 transition-all duration-200 overflow-hidden"
    >
        {{-- ロゴ --}}
        <div class="px-3 mb-2 flex items-center gap-2">
            <span x-show="open" class="text-xs font-bold text-gray-400 uppercase tracking-wider whitespace-nowrap">Rice</span>
        </div>

        {{-- 1. メール グループ --}}
        <div class="flex flex-col gap-1">
            <button type="button"
                @click="open ? toggleMail() : window.location.href = '{{ route('emails.index') }}'"
                :title="!open ? 'メール' : ''"
                class="flex items-center gap-2 mx-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 transition-colors {{ request()->routeIs('emails.*', 'attachments.*', 'approvals.*') ? 'text-white font-bold' : 'text-gray-400' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <span x-show="open" class="flex-1 text-left whitespace-nowrap">メール</span>
                <svg x-show="open" class="w-3 h-3 transition-transform duration-200" :class="mailOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/></svg>
            </button>

            <div x-show="open && mailOpen" x-cloak class="flex flex-col gap-1">
                <a href="{{ route('emails.index') }}"
                   class="flex items-center gap-2 mx-2 pl-7 pr-2 py-1 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('emails.*') ? 'bg-gray-700 text-white font-semibold' : 'text-gray-400' }}">
                    <span class="whitespace-nowrap">メール一覧</span>
                </a>
                <a href="{{ route('attachments.index') }}"
                   class="flex items-center gap-2 mx-2 pl-7 pr-2 py-1 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('attachments.*') ? 'bg-gray-700 text-white font-semibold' : 'text-gray-400' }}">
                    <span class="whitespace-nowrap">添付ファイル</span>
                </a>
                <a href="{{ route('approvals.index') }}"
                   class="flex items-center gap-2 mx-2 pl-7 pr-2 py-1 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('approvals.*') ? 'bg-gray-700 text-white font-semibold' : 'text-gray-400' }}">
                    <span class="whitespace-nowrap">承認</span>
                </a>
            </div>
        </div>

        {{-- 2. AI グループ --}}
        <div class="flex flex-col gap-1">
            <button type="button"
                @click="open ? toggleAi() : window.location.href = '{{ route('chat.index') }}'"
                :title="!open ? 'AI' : ''"
                class="flex items-center gap-2 mx-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 transition-colors {{ request()->routeIs('chat.*', 'scrape.*', 'documents.*') ? 'text-white font-bold' : 'text-gray-400' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                <span x-show="open" class="flex-1 text-left whitespace-nowrap">AI</span>
                <svg x-show="open" class="w-3 h-3 transition-transform duration-200" :class="aiOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/></svg>
            </button>

            <div x-show="open && aiOpen" x-cloak class="flex flex-col gap-1">
                <a href="{{ route('chat.index') }}"
                   class="flex items-center gap-2 mx-2 pl-7 pr-2 py-1 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('chat.*') ? 'bg-gray-700 text-white font-semibold' : 'text-gray-400' }}">
                    <span class="whitespace-nowrap">Rice Chat</span>
                </a>
                <a href="{{ route('scrape.index') }}"
                   class="flex items-center gap-2 mx-2 pl-7 pr-2 py-1 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('scrape.*') ? 'bg-gray-700 text-white font-semibold' : 'text-gray-400' }}">
                    <span class="whitespace-nowrap">スクレイプ</span>
                </a>
                <a href="{{ route('documents.index') }}"
                   class="flex items-center gap-2 mx-2 pl-7 pr-2 py-1 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('documents.*') ? 'bg-gray-700 text-white font-semibold' : 'text-gray-400' }}">
                    <span class="whitespace-nowrap">ドキュメント</span>
                </a>
            </div>
        </div>

        {{-- 設定セクション --}}
        <div x-show="open" class="text-xs font-bold text-gray-500 uppercase tracking-wider px-3 mt-3 mb-1 whitespace-nowrap">設定</div>
        <a href="{{ route('settings.ai') }}"
           :title="!open ? 'AI設定' : ''"
           class="flex items-center gap-2 mx-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('settings.ai*') ? 'bg-gray-700 text-white' : '' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span x-show="open" class="whitespace-nowrap">AI設定</span>
        </a>
        <a href="{{ route('settings.mail') }}"
           :title="!open ? 'メール設定' : ''"
           class="flex items-center gap-2 mx-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('settings.mail*') ? 'bg-gray-700 text-white' : '' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span x-show="open" class="whitespace-nowrap">メール設定</span>
        </a>

        {{-- トグルボタン --}}
        <div class="mt-auto px-2 pt-2">
            <button @click="toggle()"
                class="w-full flex items-center justify-center p-1.5 rounded hover:bg-gray-700 text-gray-400 hover:text-gray-200 transition-colors"
                :title="open ? 'メニューを閉じる' : 'メニューを開く'">
                <svg x-show="open" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                <svg x-show="!open" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" stroke-width="2"/></svg>
            </button>
        </div>
    </nav>

    {{-- メインコンテンツ --}}
    <div class="flex-1 flex flex-col min-w-0">
        @yield('content')
    </div>
</div>

<script src="{{ asset('js/thread-list-bulk.js') }}"></script>
</body>
</html>
