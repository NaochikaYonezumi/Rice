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
        /* ブランドリンク */
        .main-sidebar .brand-link {
            display: flex !important;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1.15rem 0.5rem !important;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            transition: all 0.15s;
            background: linear-gradient(180deg, rgba(59,130,246,0.08), rgba(59,130,246,0));
        }
        .main-sidebar:hover .brand-link {
            justify-content: flex-start;
            padding-left: 1rem !important;
        }
        .main-sidebar .brand-link:hover { background-color: rgba(255,255,255,0.06); }
        .main-sidebar .brand-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: #ffffff;
            border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 20px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(59,130,246,0.5);
            letter-spacing: -0.02em;
        }
        .main-sidebar .brand-text {
            color: #ffffff !important;
            font-weight: 800 !important;
            font-size: 22px;
            letter-spacing: 0.03em;
            white-space: nowrap;
            opacity: 0; transition: opacity 0.15s;
            text-shadow: 0 1px 2px rgba(0,0,0,0.25);
        }
        .main-sidebar:hover .brand-text { opacity: 1; }

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
                    style="width:360px;max-height:440px;overflow-y:auto;display:block;">
                    <div class="dropdown-header d-flex justify-content-between align-items-center px-3 py-2">
                        <strong>通知</strong>
                        <button x-show="unread > 0" @click="readAll()" class="btn btn-xs btn-link text-muted p-0">すべて既読</button>
                    </div>
                    <template x-if="items.length === 0">
                        <div class="dropdown-item text-muted text-center py-3">通知はありません</div>
                    </template>
                    <template x-for="n in items" :key="n.id">
                        <a :href="bellLinkFor(n)" @click="markRead(n.id)"
                            class="dropdown-item border-bottom py-2"
                            :class="n.read_at ? 'text-muted' : 'font-weight-bold'">
                            <div class="d-flex align-items-start" style="gap:8px;">
                                <i :class="bellIcon(n)" class="mt-1 mr-2" style="min-width:18px;text-align:center;"></i>
                                <div style="min-width:0;flex:1;">
                                    <div class="text-truncate" style="max-width:280px" x-text="bellTitle(n)"></div>
                                    <small class="text-muted text-truncate d-block" style="max-width:280px;" x-text="bellSubtitle(n)"></small>
                                </div>
                            </div>
                        </a>
                    </template>
                    <template x-if="items.length > 0">
                        <div class="d-flex" style="gap:0;">
                            <a href="{{ route('approvals.index') }}" class="dropdown-item text-center text-primary py-2 flex-fill border-right" style="font-size:12px;">
                                <i class="fas fa-check-double mr-1"></i>承認
                            </a>
                            <a href="{{ route('chats.index') }}" class="dropdown-item text-center text-success py-2 flex-fill" style="font-size:12px;">
                                <i class="fas fa-comments mr-1"></i>チャット一覧
                            </a>
                        </div>
                    </template>
                </div>
            </li>
            {{-- ユーザーメニュー (アバター + 名前をクリックでドロップダウン) --}}
            <li class="nav-item" x-data="userMenu()" @click.away="open = false"
                style="position:relative;">
                <button type="button"
                        @click="toggle()"
                        class="nav-link d-inline-flex align-items-center"
                        style="background:transparent;border:0;padding:6px 14px 6px 8px;cursor:pointer;gap:8px;position:relative;z-index:1100;">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle font-weight-bold"
                          style="width:32px;height:32px;background:#2563eb;color:#fff;font-size:13px;">
                        {{ mb_substr(auth()->user()->name ?? '?', 0, 1) }}
                    </span>
                    <span class="d-none d-sm-inline" style="font-weight:600;color:#111827;">{{ auth()->user()->name }}</span>
                    <i class="fas fa-chevron-down" style="font-size:10px;color:#9ca3af;"></i>
                </button>

                <div x-show="open" x-cloak x-transition
                     class="shadow-lg"
                     style="position:absolute;top:calc(100% + 6px);right:0;width:340px;max-height:520px;overflow-y:auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;z-index:1200;">

                    {{-- プロフィールヘッダー --}}
                    <div style="padding:16px 18px;background:linear-gradient(135deg,#2563eb 0%,#4f46e5 100%);color:#fff;">
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle font-weight-bold flex-shrink-0"
                                  style="width:48px;height:48px;background:rgba(255,255,255,.25);font-size:20px;">
                                {{ mb_substr(auth()->user()->name ?? '?', 0, 1) }}
                            </span>
                            <div style="min-width:0;flex:1;">
                                <div style="font-weight:700;font-size:14px;" class="text-truncate">{{ auth()->user()->name }}</div>
                                <div style="font-size:11px;opacity:.85;" class="text-truncate">{{ auth()->user()->email }}</div>
                                <div style="font-size:9px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-top:2px;opacity:.85;">
                                    @if(auth()->user()->isAdmin())
                                        <i class="fas fa-shield-alt mr-1"></i>管理者
                                    @else
                                        <i class="fas fa-user mr-1"></i>メンバー
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 通知セクション --}}
                    <div style="padding:10px 14px 6px 14px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">
                            <i class="fas fa-bell mr-1"></i>通知 (<span x-text="userMenuItems.length"></span>)
                        </div>
                        <button x-show="userMenuItems.some(n => !n.read_at)" @click="userMenuReadAll()" class="btn btn-link btn-sm p-0" style="font-size:11px;">すべて既読</button>
                    </div>
                    <div style="max-height:200px;overflow-y:auto;">
                        <template x-if="userMenuItems.length === 0">
                            <div class="text-muted text-center" style="padding:24px;font-size:12px;">通知はありません</div>
                        </template>
                        <template x-for="n in userMenuItems" :key="n.id">
                            <a :href="userMenuLinkFor(n)" @click="userMenuMarkRead(n.id)"
                               class="d-block border-bottom"
                               :style="n.read_at ? 'padding:10px 14px;color:#6b7280;text-decoration:none;background:#fff;' : 'padding:10px 14px;color:#111827;text-decoration:none;background:#eff6ff;'">
                                <div class="d-flex align-items-start gap-2">
                                    <i :class="userMenuIcon(n)" style="margin-top:3px;"></i>
                                    <div style="min-width:0;flex:1;">
                                        <div class="text-truncate" style="font-size:12px;font-weight:600;" x-text="userMenuTitle(n)"></div>
                                        <div class="text-truncate text-muted" style="font-size:10px;" x-text="userMenuSubtitle(n)"></div>
                                    </div>
                                </div>
                            </a>
                        </template>
                    </div>

                    {{-- アクション --}}
                    <div style="padding:8px;border-top:1px solid #f3f4f6;background:#fafafa;">
                        <a href="{{ route('profile.edit') }}" class="dropdown-item" style="border-radius:8px;font-size:13px;font-weight:600;">
                            <i class="fas fa-user-cog mr-2 text-primary"></i>プロフィール設定
                        </a>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('settings.mail') }}" class="dropdown-item" style="border-radius:8px;font-size:13px;font-weight:600;">
                                <i class="fas fa-envelope mr-2 text-primary"></i>メール設定
                            </a>
                            <a href="{{ route('admin.invitations.index') }}" class="dropdown-item" style="border-radius:8px;font-size:13px;font-weight:600;">
                                <i class="fas fa-user-plus mr-2 text-primary"></i>招待管理
                            </a>
                        @endif
                        <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger" style="border-radius:8px;font-size:13px;font-weight:600;">
                                <i class="fas fa-sign-out-alt mr-2"></i>ログアウト
                            </button>
                        </form>
                    </div>
                </div>
            </li>
            @endauth
        </ul>
    </nav>

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="/" class="brand-link" title="Rice ホーム">
            <span class="brand-icon">R</span>
            <span class="brand-text">Rice</span>
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
                                承認・送信
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
                    <li class="nav-item">
                        <a href="{{ route('chats.index') }}" class="nav-link {{ request()->routeIs('chats.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-comments"></i>
                            <p>チャット一覧</p>
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
                        <a href="{{ route('settings.ai_skills.index') }}" class="nav-link {{ request()->routeIs('settings.ai_skills.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-magic"></i>
                            <p>AIスキル</p>
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
        },

        // 通知種別ごとに表示を切り替える (チャットメンション / 承認依頼 / 却下)
        bellIcon(n) {
            const k = n.data?.kind;
            if (k === 'chat_mention') return 'fas fa-at text-success';
            if (k === 'rejected')     return 'fas fa-times-circle text-danger';
            return 'fas fa-envelope-open-text text-primary';
        },
        bellTitle(n) {
            const k = n.data?.kind;
            if (k === 'chat_mention') return '@' + (n.data?.mentioner || '誰か') + ' があなたをメンション';
            if (k === 'rejected')     return '却下: ' + (n.data?.subject || '(無題)');
            return '承認依頼: ' + (n.data?.subject || '(無題)');
        },
        bellSubtitle(n) {
            const k = n.data?.kind;
            if (k === 'chat_mention') {
                const subj = n.data?.thread_subject || '';
                const prev = n.data?.preview || '';
                return subj ? (subj + ' — ' + prev) : prev;
            }
            if (k === 'rejected') return '却下理由: ' + (n.data?.rejection_reason || '(なし)');
            return '依頼者: ' + (n.data?.created_by || '不明');
        },
        bellLinkFor(n) {
            const k = n.data?.kind;
            if (k === 'chat_mention' && n.data?.thread_id) {
                // チャット一覧の該当スレッドを開き、コメント ID があれば該当行までスクロールさせる
                const cid = n.data?.comment_id;
                return '/chats#thread-' + n.data.thread_id + (cid ? ('&comment=' + cid) : '');
            }
            if (k === 'rejected') return '/drafts';
            return '{{ route('approvals.index') }}';
        }
    };
}

// ユーザーメニュー (アバター/名前クリック → プロフィール+通知ドロップダウン)
function userMenu() {
    return {
        open: false,
        userMenuItems: [],
        _userMenuTimer: null,

        async userMenuFetch() {
            try {
                const res = await fetch('/notifications', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                this.userMenuItems = await res.json();
            } catch (e) {}
        },
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.userMenuFetch();
                if (!this._userMenuTimer) {
                    this._userMenuTimer = setInterval(() => this.userMenuFetch(), 60000);
                }
            }
        },
        async userMenuMarkRead(id) {
            await fetch(`/notifications/${id}/read`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            this.userMenuFetch();
        },
        async userMenuReadAll() {
            await fetch('/notifications/read-all', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            this.userMenuFetch();
        },
        // 通知の種別ごとに表示を切り替え
        userMenuIcon(n) {
            const k = n.data?.kind;
            if (k === 'rejected')      return 'fas fa-times-circle text-danger';
            if (k === 'chat_mention')  return 'fas fa-at text-success';
            return 'fas fa-envelope-open-text text-primary';
        },
        userMenuTitle(n) {
            const k = n.data?.kind;
            if (k === 'rejected')     return '却下: ' + (n.data?.subject || '(無題)');
            if (k === 'chat_mention') return '@' + (n.data?.mentioner || '誰か') + ' があなたをメンション';
            return '承認依頼: ' + (n.data?.subject || '(無題)');
        },
        userMenuSubtitle(n) {
            const k = n.data?.kind;
            if (k === 'rejected')     return '却下理由: ' + (n.data?.rejection_reason || '(なし)');
            if (k === 'chat_mention') return (n.data?.thread_subject || '') + ' — ' + (n.data?.preview || '');
            return '依頼者: ' + (n.data?.created_by || '不明');
        },
        userMenuLinkFor(n) {
            const k = n.data?.kind;
            if (k === 'rejected')     return '/drafts';
            if (k === 'chat_mention' && n.data?.thread_id) {
                // チャット一覧の該当スレッドを開き、コメント ID があればその行までスクロール
                const cid = n.data?.comment_id;
                return '/chats#thread-' + n.data.thread_id + (cid ? ('&comment=' + cid) : '');
            }
            return '{{ route('approvals.index') }}';
        },
    };
}
</script>
@yield('js')
</body>
</html>
