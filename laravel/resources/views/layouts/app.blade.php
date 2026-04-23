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
</head>
<body class="h-full bg-gray-50 text-gray-800 font-sans">

<div class="flex h-full">
    {{-- グローバルサイドナビ --}}
    <nav class="w-52 shrink-0 bg-gray-900 text-gray-200 flex flex-col py-4 px-3 gap-1">
        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider px-2 mb-2">Rice</div>

        <a href="{{ route('emails.index') }}"
           class="flex items-center gap-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('emails.*') ? 'bg-gray-700 text-white' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            メール
        </a>
        <a href="{{ route('documents.index') }}"
           class="flex items-center gap-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('documents.*') ? 'bg-gray-700 text-white' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            ドキュメント
        </a>
        <a href="{{ route('chat.index') }}"
           class="flex items-center gap-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('chat.*') ? 'bg-gray-700 text-white' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            Rice Chat
        </a>
        <a href="{{ route('scrape.index') }}"
           class="flex items-center gap-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('scrape.*') ? 'bg-gray-700 text-white' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
            スクレイプ
        </a>
        <div class="text-xs font-bold text-gray-500 uppercase tracking-wider px-2 mt-3 mb-1">設定</div>
        <a href="{{ route('settings.ai') }}"
           class="flex items-center gap-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('settings.ai*') ? 'bg-gray-700 text-white' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            AI設定
        </a>
        <a href="{{ route('settings.mail') }}"
           class="flex items-center gap-2 px-2 py-1.5 rounded text-sm hover:bg-gray-700 {{ request()->routeIs('settings.mail*') ? 'bg-gray-700 text-white' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            メール設定
        </a>
    </nav>

    {{-- メインコンテンツ --}}
    <div class="flex-1 flex flex-col min-w-0">
        @yield('content')
    </div>
</div>

</body>
</html>
