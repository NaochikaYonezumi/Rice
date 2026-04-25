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
    <link rel="stylesheet" href="{{ asset('css/thread-list.css') }}">
</head>
<body class="h-full bg-gray-50 text-gray-800 font-sans">

<div class="flex h-full">
    {{-- グローバルサイドナビ --}}
    <nav
        x-data="{
            open: localStorage.getItem('navOpen') !== 'false',
            toggle() { this.open = !this.open; localStorage.setItem('navOpen', this.open); }
        }"
        :class="open ? 'w-52' : 'w-12'"
        class="shrink-0 bg-gray-900 text-gray-200 flex flex-col py-4 gap-1 transition-all duration-200 overflow-hidden"
    >
        {{-- ロゴ --}}
        <div class="px-3 mb-2 flex items-center gap-2">
            <span x-show="open" class="text-xs font-bold text-gray-400 uppercase tracking-wider whitespace-nowrap">Rice</span>
        </div>

        {{-- ナビリンク --}}
        <a href="{{ route('emails.index') }}"
           :title="!open ? 'メール' : ''"
           class="flex items-center gap-2 mx-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('emails.*') ? 'bg-gray-700 text-white' : '' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span x-show="open" class="whitespace-nowrap">メール</span>
        </a>
        <a href="{{ route('documents.index') }}"
           :title="!open ? 'ドキュメント' : ''"
           class="flex items-center gap-2 mx-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('documents.*') ? 'bg-gray-700 text-white' : '' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span x-show="open" class="whitespace-nowrap">ドキュメント</span>
        </a>
        <a href="{{ route('chat.index') }}"
           :title="!open ? 'Rice Chat' : ''"
           class="flex items-center gap-2 mx-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('chat.*') ? 'bg-gray-700 text-white' : '' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            <span x-show="open" class="whitespace-nowrap">Rice Chat</span>
        </a>
        <a href="{{ route('scrape.index') }}"
           :title="!open ? 'スクレイプ' : ''"
           class="flex items-center gap-2 mx-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('scrape.*') ? 'bg-gray-700 text-white' : '' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
            <span x-show="open" class="whitespace-nowrap">スクレイプ</span>
        </a>
        <a href="{{ route('attachments.index') }}"
           :title="!open ? '添付ファイル' : ''"
           class="flex items-center gap-2 mx-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('attachments.index') ? 'bg-gray-700 text-white' : '' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
            <span x-show="open" class="whitespace-nowrap">添付ファイル</span>
        </a>
        <a href="{{ route('approvals.index') }}"
           :title="!open ? '承認' : ''"
           class="flex items-center gap-2 mx-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('approvals.*') ? 'bg-gray-700 text-white' : '' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span x-show="open" class="whitespace-nowrap">承認</span>
        </a>

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
                <svg x-show="!open" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
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
