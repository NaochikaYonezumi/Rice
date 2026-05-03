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
        /* 左右の余白は基本ゼロにする (フル幅レイアウト) */
        .content,
        .content > .container-fluid,
        .content-wrapper > .content,
        section.content,
        section.content > .container-fluid {
            padding-left: 0 !important;
            padding-right: 0 !important;
            max-width: 100% !important;
        }
        /* Bootstrap container も 100% 幅 */
        .container-fluid { padding-left: 0 !important; padding-right: 0 !important; max-width: 100% !important; }

        /* =========================================================
           サイドバー: 普段は折り畳み (アイコンのみ)
           マウスホバーで展開 (テキスト表示)
           ========================================================= */
        :root {
            --sidebar-collapsed: 60px;
            --sidebar-expanded: 220px;
        }
        .main-sidebar {
            width: var(--sidebar-collapsed) !important;
            transition: width 0.2s ease-in-out;
            z-index: 1050;        /* コンテンツより前面で展開 */
            overflow: hidden;
        }
        .main-sidebar:hover {
            width: var(--sidebar-expanded) !important;
            box-shadow: 4px 0 12px rgba(0, 0, 0, 0.08);
        }
        /* コンテンツ・ヘッダー・フッターの margin は折り畳み幅で固定 (展開時はサイドバーが浮き上がる) */
        .main-header.navbar,
        .content-wrapper,
        .main-footer {
            margin-left: var(--sidebar-collapsed) !important;
            transition: margin-left 0.2s ease-in-out;
        }

        /* ラベル文字は通常非表示・ホバーでフェードイン */
        .main-sidebar .nav-link p,
        .main-sidebar .brand-text,
        .main-sidebar .nav-header,
        .main-sidebar .badge {
            opacity: 0;
            white-space: nowrap;
            transition: opacity 0.15s ease-in-out;
        }
        .main-sidebar:hover .nav-link p,
        .main-sidebar:hover .brand-text,
        .main-sidebar:hover .nav-header,
        .main-sidebar:hover .badge {
            opacity: 1;
        }

        /* 折り畳み時はアイコン中央寄せ */
        .main-sidebar .nav-icon {
            margin-right: 0 !important;
            text-align: center;
            width: 1.6rem;
        }
        .main-sidebar:hover .nav-icon {
            margin-right: 0.5rem !important;
        }
        /* ブランドリンク: アイコンを中央に */
        .main-sidebar .brand-link {
            padding-left: 0;
            padding-right: 0;
            text-align: center;
        }

        /* モバイル: ホバーが効かないので常時折り畳み (タップで展開する場合は body.sidebar-open など別実装が必要) */
        @media (max-width: 991px) {
            .main-header.navbar,
            .content-wrapper,
            .main-footer { margin-left: 0 !important; }
            .main-sidebar { display: none; }
        }

        /* content-header の余白調整 (使うページのみ) */
        .content-header { padding: .75rem 1rem; }
        .content-header h1 { font-size: 1.4rem; }

    </style>
    @yield('css')
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">

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

        @php
            // 自分宛の承認待ち件数 (バッジ表示用)
            $myPendingApprovalCount = auth()->check()
                ? \App\Models\PendingEmail::where('status', \App\Models\PendingEmail::STATUS_PENDING)
                    ->where('target_approver_user_id', auth()->id())
                    ->count()
                : 0;
        @endphp
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    {{-- 主要4項目 --}}
                    <li class="nav-item">
                        <a href="{{ route('emails.index') }}" class="nav-link {{ request()->routeIs('emails.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-envelope"></i>
                            <p>メール一覧</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('drafts.index') }}" class="nav-link {{ request()->routeIs('drafts.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>下書き</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('approvals.index') }}" class="nav-link {{ request()->routeIs('approvals.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-check-double"></i>
                            <p>
                                承認待ち一覧
                                @if($myPendingApprovalCount > 0)
                                    <span class="badge badge-warning right">{{ $myPendingApprovalCount > 99 ? '99+' : $myPendingApprovalCount }}</span>
                                @endif
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('attachments.index') }}" class="nav-link {{ request()->routeIs('attachments.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-paperclip"></i>
                            <p>添付ファイル</p>
                        </a>
                    </li>

                    {{-- 補助メニュー --}}
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
