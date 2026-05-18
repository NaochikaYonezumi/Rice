<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Rice')</title>
    
    <!-- AdminLTE & Bootstrap CSS (FreeScout Style) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('css')
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        
        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            @auth
            {{-- 選択中ルームを保ったまま メール/チャット/添付 を行き来できるピル群 --}}
            <li class="nav-item d-inline-flex align-items-center"
                style="gap:6px;margin-right:8px;"
                x-data="roomNavBar()">
                <a :href="urlFor('/')"
                   @click.prevent="navTo('/')"
                   class="d-inline-flex align-items-center"
                   :style="pillStyle('mail')"
                   title="メールへ">
                    <i class="fas fa-envelope" style="font-size:11px;margin-right:4px;"></i><span>メール</span>
                </a>
                <a :href="urlFor('/chat')"
                   @click.prevent="navTo('/chat')"
                   class="d-inline-flex align-items-center"
                   :style="pillStyle('chat')"
                   title="チャットへ">
                    <i class="fas fa-comments" style="font-size:11px;margin-right:4px;"></i><span>チャット</span>
                </a>
                <a :href="urlFor('/attachments')"
                   @click.prevent="navTo('/attachments')"
                   class="d-inline-flex align-items-center"
                   :style="pillStyle('att')"
                   title="添付ファイルへ">
                    <i class="fas fa-paperclip" style="font-size:11px;margin-right:4px;"></i><span>添付</span>
                </a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <span class="nav-link">{{ auth()->user()->name }}</span>
            </li>
            <li class="nav-item">
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-link nav-link">ログアウト</button>
                </form>
            </li>
            @endauth
        </ul>
    </nav>

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="/" class="brand-link">
            <span class="brand-text font-weight-light">Rice</span>
        </a>

        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="{{ route('emails.index') }}" class="nav-link {{ request()->routeIs('emails.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-envelope"></i>
                            <p>メール一覧</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <p>レポート</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('knowledge.index') }}" class="nav-link {{ request()->routeIs('knowledge.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-book"></i>
                            <p>ナレッジベース</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('chat.index') }}" class="nav-link {{ request()->routeIs('chat.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-comments"></i>
                            <p>Rice Chat</p>
                        </a>
                    </li>
                    @if(auth()->user() && auth()->user()->isAdmin())
                    <li class="nav-header">ADMIN</li>
                    <li class="nav-item">
                        <a href="{{ route('admin.invitations.index') }}" class="nav-link {{ request()->routeIs('admin.invitations.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-user-plus"></i>
                            <p>招待管理</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('settings.mail') }}" class="nav-link {{ request()->routeIs('settings.mail*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-envelope-config"></i>
                            <p>メール設定</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('settings.ai') }}" class="nav-link {{ request()->routeIs('settings.ai*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-robot"></i>
                            <p>AI設定</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('settings.sso') }}" class="nav-link {{ request()->routeIs('settings.sso*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-key"></i>
                            <p>SSO設定</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('master.statuses') }}" class="nav-link {{ request()->routeIs('master.statuses*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tasks"></i>
                            <p>ステータス管理</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('master.tags') }}" class="nav-link {{ request()->routeIs('master.tags*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-tags"></i>
                            <p>タグ管理</p>
                        </a>
                    </li>
                    @endif
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>@yield('header', '')</h1>
            </div>
        </section>

        {{-- グローバル「選択中ルーム」バナー : 3 画面で共有される localStorage 駆動。
             URL?room_id= 経由で来た場合は name が空なので、/customers から補完する。 --}}
        <div x-data="{
                async syncName() {
                    if (!this.$store.room.id || this.$store.room.name) return;
                    try {
                        const res = await fetch('/customers');
                        if (!res.ok) return;
                        const list = await res.json();
                        const c = list.find(x => x.id === this.$store.room.id);
                        if (c) this.$store.room.select({ id: c.id, name: c.name, is_personal: c.is_personal });
                    } catch (_) {}
                }
             }"
             x-init="syncName()"
             x-show="$store.room.id"
             x-cloak
             class="px-4 py-2 bg-indigo-50 border-b border-indigo-100 dark:bg-[#1F1B2E] dark:border-[#36324A] flex items-center gap-2 text-xs">
            <span class="text-[9px] font-black text-indigo-400 dark:text-[#7F77A8] uppercase tracking-widest">選択中ルーム:</span>
            <span class="inline-flex items-center gap-1.5 bg-white text-indigo-700 border border-indigo-200 dark:bg-[#2E2A24] dark:text-[#C8C0FA] dark:border-[#36324A] text-[10px] font-black px-2.5 py-0.5 rounded-full">
                <i class="fas fa-door-open text-[9px]"></i>
                <span x-text="$store.room.name || '(読み込み中…)'"></span>
                <span x-show="$store.room.isPersonal" class="ml-1 text-[8px] text-indigo-400 dark:text-[#7F77A8] uppercase tracking-widest">個人</span>
            </span>
            <button @click="$store.room.clear()"
                class="ml-1 text-indigo-300 hover:text-red-500 dark:text-[#7F77A8] dark:hover:text-red-400"
                title="ルーム選択を解除">
                <i class="fas fa-times-circle"></i>
            </button>
        </div>

        <section class="content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer text-sm">
        <strong>Rice (FreeScout Modular Architecture)</strong>
    </footer>
</div>

<!-- AdminLTE & Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

{{-- ルーム遷移ピル群の Alpine コンポーネント。$store.room と現在 URL から表示と遷移先を決める。 --}}
<script>
function roomNavBar() {
    return {
        get roomId()   { return this.$store?.room?.id ?? null; },
        get roomName() { return this.$store?.room?.name ?? null; },

        // 現在ページが mail / chat / att / other のどれか
        get current() {
            const p = window.location.pathname;
            if (p === '/' || p.startsWith('/emails')) return 'mail';
            if (p.startsWith('/chat'))               return 'chat';
            if (p.startsWith('/attachments'))         return 'att';
            return null;
        },

        // 遷移先 URL。room が選択されていれば ?room_id= を付与し、別タブ / 履歴経由でも引き継ぐ
        urlFor(path) {
            if (!this.roomId) return path;
            const u = new URL(path, window.location.origin);
            u.searchParams.set('room_id', this.roomId);
            return u.pathname + u.search;
        },

        // クリック時: localStorage と URL の双方を確実に揃えてから遷移
        navTo(path) {
            window.location.href = this.urlFor(path);
        },

        // ピルの色。アクティブ画面は濃色、非アクティブは淡色、ルーム未選択時はグレー
        pillStyle(key) {
            const base = 'text-decoration:none;font-size:11px;font-weight:700;padding:4px 10px;border-radius:9999px;line-height:1.2;cursor:pointer;';
            const isActive = this.current === key;
            const palette = {
                mail: { active: 'background:#b45309;color:#fff;border:1px solid #b45309;',
                        idle:   'background:#fffbeb;color:#b45309;border:1px solid #fde68a;' },
                chat: { active: 'background:#1d4ed8;color:#fff;border:1px solid #1d4ed8;',
                        idle:   'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;' },
                att:  { active: 'background:#047857;color:#fff;border:1px solid #047857;',
                        idle:   'background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;' },
            };
            return base + (isActive ? palette[key].active : palette[key].idle);
        },
    };
}
</script>

@yield('js')
</body>
</html>
