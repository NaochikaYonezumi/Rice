<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Rice')</title>
    {{-- テーマ早期適用: ローカルストレージから読み込んで <html> にクラスを付与し、
         layouts.app と同じ theme-dark / 通常 を切り替える。
         compose-window などの fullpage ベース画面でもダークモードを揃えるため。 --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('riceTheme');
                if (t === 'dark') document.documentElement.classList.add('theme-dark');
            } catch (e) {}
            // Ctrl+Shift+L で本画面でもダーク/ライト切替を許可
            try {
                document.addEventListener('keydown', function (e) {
                    if (!e.ctrlKey || !e.shiftKey) return;
                    if (e.code !== 'KeyL' && e.key !== 'L' && e.key !== 'l') return;
                    e.preventDefault();
                    var cur = localStorage.getItem('riceTheme') === 'dark' ? 'dark' : 'light';
                    var next = cur === 'dark' ? 'light' : 'dark';
                    try { localStorage.setItem('riceTheme', next); } catch (_) {}
                    if (next === 'dark') document.documentElement.classList.add('theme-dark');
                    else document.documentElement.classList.remove('theme-dark');
                });
            } catch (e) {}
        })();
    </script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* ===== ダークモード共通スタイル (compose-window 等の fullpage 系) =====
             layouts.app と同じ CSS 変数を使い、 .theme-dark のときに白系背景を暗くする。
             Tailwind の bg-* / text-* / border-* / グラデーション まで包括的に上書き。
             個別画面で例外が要る時は @section('css') で更に詳細セレクタを当てる。 */
        html.theme-dark { color-scheme: dark; }
        html.theme-dark body { background-color: #1e1f22 !important; color: #e5e7eb !important; }

        /* ----- 背景: 白 / グレー系 ----- */
        html.theme-dark .bg-white,
        html.theme-dark .bg-gray-50,
        html.theme-dark .bg-gray-100,
        html.theme-dark .bg-slate-50,
        html.theme-dark .bg-slate-100 { background-color: #2b2d31 !important; }
        html.theme-dark .bg-gray-200,
        html.theme-dark .bg-slate-200 { background-color: #3f4147 !important; }

        /* ----- 背景: ライトカラー系 (薄色背景はすべてダークアクセントに) ----- */
        html.theme-dark .bg-blue-50,
        html.theme-dark .bg-blue-100,
        html.theme-dark .bg-indigo-50,
        html.theme-dark .bg-indigo-100,
        html.theme-dark .bg-violet-50,
        html.theme-dark .bg-purple-50 { background-color: #1e293b !important; color: #93c5fd !important; }
        html.theme-dark .bg-emerald-50,
        html.theme-dark .bg-emerald-100,
        html.theme-dark .bg-green-50,
        html.theme-dark .bg-green-100 { background-color: #102b1f !important; color: #6ee7b7 !important; }
        html.theme-dark .bg-amber-50,
        html.theme-dark .bg-amber-100,
        html.theme-dark .bg-yellow-50 { background-color: #2d2716 !important; color: #fde68a !important; }
        html.theme-dark .bg-red-50,
        html.theme-dark .bg-red-100,
        html.theme-dark .bg-rose-50 { background-color: #3a1a1f !important; color: #fca5a5 !important; }
        html.theme-dark .bg-pink-50,
        html.theme-dark .bg-fuchsia-50 { background-color: #321a2a !important; color: #f9a8d4 !important; }

        /* ----- グラデーション: 薄色 (50/100) を起点とするものだけ flat ダーク化.
                  濃色 (500/600) のアクセントボタンには触らず、コントラストを保つ. ----- */
        html.theme-dark .bg-gradient-to-b.from-amber-50,
        html.theme-dark .bg-gradient-to-r.from-amber-50,
        html.theme-dark .bg-gradient-to-b.from-yellow-50,
        html.theme-dark .bg-gradient-to-r.from-yellow-50 {
            background-image: none !important;
            background-color: #2d2716 !important;
        }
        html.theme-dark .bg-gradient-to-b.from-blue-50,
        html.theme-dark .bg-gradient-to-r.from-blue-50,
        html.theme-dark .bg-gradient-to-b.from-indigo-50,
        html.theme-dark .bg-gradient-to-r.from-indigo-50,
        html.theme-dark .bg-gradient-to-b.from-sky-50,
        html.theme-dark .bg-gradient-to-r.from-sky-50,
        html.theme-dark .bg-gradient-to-b.from-violet-50,
        html.theme-dark .bg-gradient-to-r.from-violet-50,
        html.theme-dark .bg-gradient-to-b.from-purple-50,
        html.theme-dark .bg-gradient-to-r.from-purple-50 {
            background-image: none !important;
            background-color: #1e293b !important;
        }
        html.theme-dark .bg-gradient-to-b.from-emerald-50,
        html.theme-dark .bg-gradient-to-r.from-emerald-50,
        html.theme-dark .bg-gradient-to-b.from-green-50,
        html.theme-dark .bg-gradient-to-r.from-green-50 {
            background-image: none !important;
            background-color: #102b1f !important;
        }
        html.theme-dark .bg-gradient-to-b.from-red-50,
        html.theme-dark .bg-gradient-to-r.from-red-50,
        html.theme-dark .bg-gradient-to-b.from-rose-50,
        html.theme-dark .bg-gradient-to-r.from-rose-50 {
            background-image: none !important;
            background-color: #3a1a1f !important;
        }
        html.theme-dark .bg-gradient-to-b.from-gray-50,
        html.theme-dark .bg-gradient-to-r.from-gray-50,
        html.theme-dark .bg-gradient-to-b.from-slate-50,
        html.theme-dark .bg-gradient-to-r.from-slate-50,
        html.theme-dark .bg-gradient-to-b.from-zinc-50,
        html.theme-dark .bg-gradient-to-r.from-zinc-50 {
            background-image: none !important;
            background-color: #2b2d31 !important;
        }

        /* ----- 罫線 ----- */
        html.theme-dark .border-gray-100,
        html.theme-dark .border-gray-200,
        html.theme-dark .border-slate-100,
        html.theme-dark .border-slate-200 { border-color: #3f4147 !important; }
        html.theme-dark .border-gray-300 { border-color: #52555c !important; }
        html.theme-dark .border-blue-100,
        html.theme-dark .border-blue-200,
        html.theme-dark .border-indigo-100,
        html.theme-dark .border-indigo-200 { border-color: #1e40af !important; }
        html.theme-dark .border-amber-100,
        html.theme-dark .border-amber-200,
        html.theme-dark .border-yellow-100,
        html.theme-dark .border-yellow-200 { border-color: #78350f !important; }
        html.theme-dark .border-emerald-100,
        html.theme-dark .border-emerald-200,
        html.theme-dark .border-green-100,
        html.theme-dark .border-green-200 { border-color: #065f46 !important; }
        html.theme-dark .border-red-100,
        html.theme-dark .border-red-200 { border-color: #7f1d1d !important; }
        html.theme-dark .border-red-400 { border-color: #ef4444 !important; }

        /* ----- 文字色 ----- */
        html.theme-dark .text-gray-300,
        html.theme-dark .text-gray-400,
        html.theme-dark .text-gray-500 { color: #9ca3af !important; }
        html.theme-dark .text-gray-600,
        html.theme-dark .text-gray-700,
        html.theme-dark .text-gray-800,
        html.theme-dark .text-gray-900 { color: #e5e7eb !important; }
        html.theme-dark .text-slate-700,
        html.theme-dark .text-slate-800,
        html.theme-dark .text-slate-900 { color: #e5e7eb !important; }
        html.theme-dark .text-blue-600,
        html.theme-dark .text-blue-700,
        html.theme-dark .text-blue-800,
        html.theme-dark .text-blue-900,
        html.theme-dark .text-indigo-600,
        html.theme-dark .text-indigo-700,
        html.theme-dark .text-indigo-800,
        html.theme-dark .text-indigo-900 { color: #93c5fd !important; }
        html.theme-dark .text-emerald-600,
        html.theme-dark .text-emerald-700,
        html.theme-dark .text-emerald-800,
        html.theme-dark .text-green-600,
        html.theme-dark .text-green-700 { color: #34d399 !important; }
        html.theme-dark .text-amber-600,
        html.theme-dark .text-amber-700,
        html.theme-dark .text-amber-800,
        html.theme-dark .text-amber-900,
        html.theme-dark .text-yellow-700 { color: #fbbf24 !important; }
        html.theme-dark .text-red-600,
        html.theme-dark .text-red-700,
        html.theme-dark .text-red-800,
        html.theme-dark .text-red-900,
        html.theme-dark .text-rose-700 { color: #fca5a5 !important; }
        html.theme-dark .text-purple-600,
        html.theme-dark .text-purple-700,
        html.theme-dark .text-violet-700 { color: #c4b5fd !important; }

        /* ----- フォーム要素 ----- */
        html.theme-dark input,
        html.theme-dark textarea,
        html.theme-dark select {
            background-color: #2b2d31 !important;
            color: #e5e7eb !important;
            border-color: #3f4147 !important;
        }
        html.theme-dark input::placeholder,
        html.theme-dark textarea::placeholder { color: #6b7280 !important; }
        html.theme-dark input[type="checkbox"],
        html.theme-dark input[type="radio"] { accent-color: #2563eb; }

        /* ----- 影 / リサイズハンドル ----- */
        html.theme-dark .shadow,
        html.theme-dark .shadow-sm,
        html.theme-dark .shadow-md,
        html.theme-dark .shadow-lg,
        html.theme-dark .shadow-xl,
        html.theme-dark .shadow-2xl { box-shadow: 0 1px 0 #18191c inset, 0 4px 12px rgba(0,0,0,.35) !important; }
        html.theme-dark .resize-handle { background-color: #3f4147 !important; }

        /* ----- ホバー ----- */
        html.theme-dark .hover\:bg-gray-50:hover,
        html.theme-dark .hover\:bg-gray-100:hover,
        html.theme-dark .hover\:bg-slate-50:hover { background-color: #313338 !important; }
        html.theme-dark .hover\:bg-blue-50:hover,
        html.theme-dark .hover\:bg-indigo-50:hover { background-color: #1e293b !important; }
        html.theme-dark .hover\:bg-emerald-50:hover,
        html.theme-dark .hover\:bg-green-50:hover { background-color: #14352a !important; }
        html.theme-dark .hover\:bg-amber-50:hover,
        html.theme-dark .hover\:bg-yellow-50:hover { background-color: #3a3217 !important; }
        html.theme-dark .hover\:bg-red-50:hover,
        html.theme-dark .hover\:bg-rose-50:hover { background-color: #3a1a1f !important; }

        /* ----- スクロールバー ----- */
        html.theme-dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #4b5057 !important; }
        html.theme-dark .custom-scrollbar::-webkit-scrollbar-track { background-color: transparent !important; }
    </style>
    @yield('css')
</head>
<body class="h-screen overflow-hidden bg-white text-gray-800">
    @yield('content')
    @yield('js')
</body>
</html>
