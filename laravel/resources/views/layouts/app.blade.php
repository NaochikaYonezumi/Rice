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
    <style>
        /* ===== Responsive: 1920×1080 and common HD/FHD displays ===== */
        .main-sidebar { width: 220px; }
        .main-header.navbar { margin-left: 220px; }
        .content-wrapper, .main-footer { margin-left: 220px; }

        body.sidebar-collapse .main-sidebar { width: 4.6rem; }
        body.sidebar-collapse .main-header.navbar { margin-left: 4.6rem; }
        body.sidebar-collapse .content-wrapper,
        body.sidebar-collapse .main-footer { margin-left: 4.6rem; }

        /* FHD (1920px) - コンテンツ幅を広く */
        @media (min-width: 1600px) {
            .container-fluid { max-width: 1480px; }
            .main-sidebar { width: 240px; }
            body:not(.sidebar-collapse) .main-header.navbar,
            body:not(.sidebar-collapse) .content-wrapper,
            body:not(.sidebar-collapse) .main-footer { margin-left: 240px; }
            .card { border-radius: .75rem; }
            .card-header, .card-footer { padding: .9rem 1.25rem; }
            body { font-size: 15px; }
        }

        /* Wide FHD / 2K */
        @media (min-width: 1920px) {
            .container-fluid { max-width: 1760px; }
        }

        /* Smaller than HD */
        @media (max-width: 991px) {
            .main-header.navbar,
            .content-wrapper,
            .main-footer { margin-left: 0 !important; }
        }

        /* content-header の余白調整 */
        .content-header { padding: .75rem 1rem; }
        .content-header h1 { font-size: 1.4rem; }

    </style>
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
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-times"></i></a>
            </li>
        </ul>
        
        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            @auth
            {{-- 通知ベル --}}
            <li class="nav-item dropdown" x-data="notifApp()" x-init="poll()" @click.away="open = false">
                <a class="nav-link position-relative" href="#" @click.prevent="toggle()">
                    <i class="fas fa-bell"></i>
                    <span x-show="unread > 0"
                        class="badge badge-danger navbar-badge"
                        x-text="unread > 9 ? '9+' : unread"></span>
                </a>
                <div x-show="open" x-transition
                    class="dropdown-menu dropdown-menu-right shadow"
                    style="width:340px;max-height:400px;overflow-y:auto;display:block;">
                    <div class="dropdown-header d-flex justify-content-between align-items-center px-3 py-2">
                        <strong>承認依頼</strong>
                        <button x-show="unread > 0" @click="readAll()" class="btn btn-xs btn-link text-muted p-0">すべて既読</button>
                    </div>
                    <template x-if="items.length === 0">
                        <div class="dropdown-item text-muted text-center py-3">通知はありません</div>
                    </template>
                    <template x-for="n in items" :key="n.id">
                        <a :href="'{{ route('approvals.index') }}'" @click="markRead(n.id)"
                            class="dropdown-item border-bottom py-2"
                            :class="n.read_at ? 'text-muted' : 'font-weight-bold'">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fas fa-envelope-open-text text-primary mt-1 mr-2"></i>
                                <div style="min-width:0">
                                    <div class="text-truncate" style="max-width:240px" x-text="n.data.subject || '(無題)'"></div>
                                    <small class="text-muted" x-text="'依頼者: ' + (n.data.created_by || '不明')"></small>
                                </div>
                            </div>
                        </a>
                    </template>
                    <template x-if="items.length > 0">
                        <a href="{{ route('approvals.index') }}" class="dropdown-item text-center text-primary py-2">
                            承認ページを開く
                        </a>
                    </template>
                </div>
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
                        <a href="{{ route('drafts.index') }}" class="nav-link {{ request()->routeIs('drafts.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>下書き</p>
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

        <section class="content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer text-sm" style="display:none"></footer>
</div>

<!-- AdminLTE & Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
function notifApp() {
    return {
        open: false,
        items: [],
        unread: 0,
        _timer: null,

        async fetch() {
            try {
                const res = await fetch('/notifications', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                this.items = await res.json();
                this.unread = this.items.filter(n => !n.read_at).length;
            } catch (e) {}
        },

        poll() {
            this.fetch();
            this._timer = setInterval(() => this.fetch(), 60000);
        },

        toggle() {
            this.open = !this.open;
        },

        async markRead(id) {
            await fetch(`/notifications/${id}/read`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            this.fetch();
        },

        async readAll() {
            await fetch('/notifications/read-all', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            this.fetch();
        }
    };
}
</script>
@yield('js')
</body>
</html>
