@extends('layouts.app')
@section('title', 'レポート')

@section('css')
<style>
    .content-header { display: none !important; }
    .content, .content > .container-fluid {
        padding: 0 !important;
        max-width: 100% !important;
        height: calc(100vh - 3.5rem);
        overflow-y: auto;
        background: #f9fafb;
    }
    .stat-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 18px 20px;
        box-shadow: 0 1px 2px rgba(0,0,0,.04);
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 18px;
        flex-shrink: 0;
    }
    .stat-value { font-size: 22px; font-weight: 800; color: #111827; line-height: 1.1; }
    .stat-label { font-size: 11px; color: #6b7280; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; margin-top: 2px; }
    .panel {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(0,0,0,.04);
    }
    .panel-header {
        padding: 14px 18px;
        border-bottom: 1px solid #f3f4f6;
        display: flex;
        align-items: center;
        gap: 10px;
        background: linear-gradient(180deg, #ffffff 0%, #f9fafb 100%);
    }
    .panel-title { font-size: 13px; font-weight: 800; color: #111827; }
    .panel-sub { font-size: 11px; color: #6b7280; }
    .pill {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 8px; border-radius: 9999px;
        font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em;
        border: 1px solid;
    }
    .pill-inbox     { color:#1d4ed8; background:#eff6ff; border-color:#bfdbfe; }
    .pill-hold      { color:#b45309; background:#fffbeb; border-color:#fde68a; }
    .pill-completed { color:#15803d; background:#f0fdf4; border-color:#bbf7d0; }
    .pill-pending   { color:#c2410c; background:#fff7ed; border-color:#fed7aa; }
    .seg-bar { display:flex; height: 8px; border-radius: 9999px; overflow: hidden; background:#f3f4f6; }
    .seg { height: 100%; }
    .seg-inbox     { background:#3b82f6; }
    .seg-hold      { background:#f59e0b; }
    .seg-completed { background:#10b981; }
    .seg-pending   { background:#f97316; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .data-table th { text-align: left; padding: 10px 16px; font-size: 10px; color:#6b7280; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; background:#fafafa; border-bottom: 1px solid #f3f4f6; }
    .data-table td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    .data-table tr:last-child td { border-bottom: 0; }
    .data-table tr:hover { background:#f9fafb; }
    .num { font-variant-numeric: tabular-nums; font-weight: 700; color: #111827; }
    .chart-grid { display: grid; gap: 6px; }
    .day-row { display: grid; grid-template-columns: 70px 1fr 60px; gap: 10px; align-items: center; }
    .day-label { font-size: 11px; color:#6b7280; font-weight: 700; }
    .day-bar-track { height: 18px; background:#f3f4f6; border-radius: 6px; overflow: hidden; position: relative; }
    .day-bar { height: 100%; background: linear-gradient(90deg, #3b82f6 0%, #1d4ed8 100%); border-radius: 6px; transition: width .2s; }
    .day-num { font-size: 11px; color:#111827; font-weight: 800; text-align:right; }
    .tag-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 12px;
        background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
        border: 1px solid #e9d5ff;
        color: #7e22ce;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 700;
    }
    .tag-chip .count { background:#7e22ce; color:#fff; padding: 2px 8px; border-radius: 9999px; font-size: 10px; }
    .empty { padding: 28px; text-align: center; color: #9ca3af; font-size: 12px; font-weight: 600; }
</style>
@endsection

@section('content')
<div class="px-6 py-5 space-y-5">

    {{-- ヘッダー: 期間フィルタ --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900">レポート</h1>
            <p class="text-xs text-gray-500 mt-0.5">担当者別・日付別の集計</p>
        </div>
        <form method="GET" action="{{ route('reports.index') }}" class="flex items-end gap-2 flex-wrap">
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">開始日</label>
                <input type="date" name="from" value="{{ $stats['period']['from'] }}"
                       class="border border-gray-200 rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-700 focus:ring-2 focus:ring-blue-100 outline-none">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">終了日</label>
                <input type="date" name="to" value="{{ $stats['period']['to'] }}"
                       class="border border-gray-200 rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-700 focus:ring-2 focus:ring-blue-100 outline-none">
            </div>
            <button type="submit"
                    class="px-4 py-2 rounded-lg text-xs font-bold text-white"
                    style="background-color:#2563eb;">
                <i class="fas fa-filter mr-1"></i> 期間で絞る
            </button>
            <a href="{{ route('reports.index') }}"
               class="px-4 py-2 rounded-lg text-xs font-bold text-gray-600 bg-white border border-gray-200 hover:bg-gray-50">
                リセット
            </a>
        </form>
    </div>

    {{-- サマリーカード --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#3b82f6;"><i class="fas fa-envelope"></i></div>
            <div>
                <div class="stat-value">{{ number_format($stats['totals']['threads_total']) }}</div>
                <div class="stat-label">総スレッド数</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#f59e0b;"><i class="fas fa-inbox"></i></div>
            <div>
                <div class="stat-value">{{ number_format($stats['totals']['open_threads']) }}</div>
                <div class="stat-label">未対応 (受信)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#10b981;"><i class="fas fa-paper-plane"></i></div>
            <div>
                <div class="stat-value">{{ number_format($stats['totals']['emails_in_period']) }}</div>
                <div class="stat-label">期間内メール</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#8b5cf6;"><i class="fas fa-comments"></i></div>
            <div>
                <div class="stat-value">{{ number_format($stats['totals']['threads_in_period']) }}</div>
                <div class="stat-label">期間内スレッド</div>
            </div>
        </div>
    </div>

    {{-- ステータス内訳バー --}}
    <div class="panel">
        <div class="panel-header">
            <i class="fas fa-tasks text-blue-500"></i>
            <div class="panel-title">ステータス内訳 (全スレッド)</div>
        </div>
        <div class="px-5 py-4">
            @php
                $byStatus = $stats['by_status'];
                $statusTotal = max(1, array_sum($byStatus));
                $pct = fn($n) => round(($n / $statusTotal) * 100, 1);
            @endphp
            <div class="seg-bar mb-3">
                <div class="seg seg-inbox"     style="width: {{ $pct($byStatus['inbox'])     }}%"></div>
                <div class="seg seg-hold"      style="width: {{ $pct($byStatus['hold'])      }}%"></div>
                <div class="seg seg-completed" style="width: {{ $pct($byStatus['completed']) }}%"></div>
                <div class="seg seg-pending"   style="width: {{ $pct($byStatus['pending'])   }}%"></div>
            </div>
            <div class="flex flex-wrap gap-3 text-xs">
                <div class="flex items-center gap-2"><span class="pill pill-inbox">受信</span><span class="num">{{ $byStatus['inbox'] }}</span></div>
                <div class="flex items-center gap-2"><span class="pill pill-hold">保留</span><span class="num">{{ $byStatus['hold'] }}</span></div>
                <div class="flex items-center gap-2"><span class="pill pill-completed">完了</span><span class="num">{{ $byStatus['completed'] }}</span></div>
                <div class="flex items-center gap-2"><span class="pill pill-pending">承認待ち</span><span class="num">{{ $byStatus['pending'] }}</span></div>
            </div>
        </div>
    </div>

    {{-- 担当者別 --}}
    <div class="panel">
        <div class="panel-header">
            <i class="fas fa-user-friends text-blue-500"></i>
            <div class="panel-title">担当者別</div>
            <div class="panel-sub">スレッド数とステータス内訳・期間内メール数</div>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:24%;">担当者</th>
                        <th style="width:10%;">合計</th>
                        <th style="width:36%;">ステータス内訳</th>
                        <th style="width:10%;">受信</th>
                        <th style="width:10%;">保留</th>
                        <th style="width:10%;">完了</th>
                        <th style="width:10%;">承認待ち</th>
                        <th style="width:12%;">期間内メール</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stats['by_assignee'] as $row)
                        @php
                            $rowTotal = max(1, $row['total']);
                            $rp = fn($n) => round(($n / $rowTotal) * 100, 1);
                        @endphp
                        <tr>
                            <td>
                                <div class="flex items-center gap-2">
                                    @if($row['user_id'])
                                        <i class="fas fa-user-circle text-gray-400"></i>
                                    @else
                                        <i class="fas fa-question-circle text-gray-300"></i>
                                    @endif
                                    <span class="font-bold text-gray-800">{{ $row['name'] }}</span>
                                </div>
                            </td>
                            <td><span class="num">{{ $row['total'] }}</span></td>
                            <td>
                                <div class="seg-bar" title="受信:{{ $row['inbox'] }} / 保留:{{ $row['hold'] }} / 完了:{{ $row['completed'] }} / 承認待ち:{{ $row['pending'] }}">
                                    <div class="seg seg-inbox"     style="width: {{ $rp($row['inbox'])     }}%"></div>
                                    <div class="seg seg-hold"      style="width: {{ $rp($row['hold'])      }}%"></div>
                                    <div class="seg seg-completed" style="width: {{ $rp($row['completed']) }}%"></div>
                                    <div class="seg seg-pending"   style="width: {{ $rp($row['pending'])   }}%"></div>
                                </div>
                            </td>
                            <td><span class="num">{{ $row['inbox'] }}</span></td>
                            <td><span class="num">{{ $row['hold'] }}</span></td>
                            <td><span class="num">{{ $row['completed'] }}</span></td>
                            <td><span class="num">{{ $row['pending'] }}</span></td>
                            <td><span class="num">{{ $row['emails_period'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty">担当者データがありません</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        {{-- 日付別 (棒グラフ) --}}
        <div class="panel">
            <div class="panel-header">
                <i class="fas fa-calendar-alt text-blue-500"></i>
                <div class="panel-title">日付別</div>
                <div class="panel-sub">期間内のメール件数</div>
            </div>
            <div class="px-5 py-4">
                @php
                    $byDate = $stats['by_date'];
                    $maxE = max(1, $byDate['max_emails']);
                @endphp
                @if(empty($byDate['rows']))
                    <div class="empty">期間内のデータがありません</div>
                @else
                    {{--
                        日付別パネル: 期間内の日付数だけ縦に並ぶ自然な高さ.
                        max-height は付けない (ユーザ要望: 元のまま伸ばす).
                    --}}
                    <div class="chart-grid">
                        @foreach($byDate['rows'] as $d)
                            <div class="day-row">
                                <div class="day-label">{{ $d['label'] }} <span class="text-gray-400">({{ $d['weekday'] }})</span></div>
                                <div class="day-bar-track">
                                    <div class="day-bar" style="width: {{ round(($d['emails'] / $maxE) * 100, 1) }}%;"></div>
                                </div>
                                <div class="day-num">{{ $d['emails'] }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3 text-[10px] text-gray-400 font-semibold flex items-center gap-3">
                        <span><span class="inline-block w-2 h-2 rounded-full" style="background:#3b82f6;"></span> メール件数</span>
                        <span>最大: <span class="num">{{ $byDate['max_emails'] }}</span></span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ===== 共有ルーム毎のメール件数 ===== --}}
        <div class="panel">
            <div class="panel-header">
                <i class="fas fa-hashtag text-amber-500"></i>
                <div class="panel-title">共有ルーム毎のメール件数</div>
                <div class="panel-sub">期間内に受信したメール数</div>
            </div>
            <div class="px-5 py-4">
                @php
                    $maxRoomEmails = max(1, collect($stats['rooms'])->max('emails_count') ?? 0);
                @endphp
                @if(empty($stats['rooms']))
                    <div class="empty">共有ルームがありません</div>
                @else
                    {{--
                        共有ルームパネル: 日付別パネルと同じ自然な縦伸び (max-height 無し).
                        2 カラムで横並びにした時、両方が同じ伸び方をするのでレイアウトが揃う.
                    --}}
                    <div class="chart-grid">
                        @foreach($stats['rooms'] as $room)
                            <div class="day-row" style="grid-template-columns: 140px 1fr 60px;">
                                <div class="day-label" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <a href="{{ url('/chats?room=' . $room['id']) }}" target="_blank"
                                       class="hover:text-blue-600"
                                       title="{{ $room['name'] }}">
                                        <i class="fas fa-hashtag text-gray-400 text-[9px] mr-0.5"></i>{{ $room['name'] }}
                                    </a>
                                </div>
                                <div class="day-bar-track">
                                    <div class="day-bar" style="width: {{ round(($room['emails_count'] / $maxRoomEmails) * 100, 1) }}%; background: linear-gradient(90deg, #a855f7 0%, #7e22ce 100%);"></div>
                                </div>
                                <div class="day-num">{{ $room['emails_count'] }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3 text-[10px] text-gray-400 font-semibold flex items-center gap-3">
                        <span><span class="inline-block w-2 h-2 rounded-full" style="background:#a855f7;"></span> 期間内メール件数</span>
                        <span>最大: <span class="num">{{ $maxRoomEmails }}</span></span>
                    </div>
                @endif
            </div>
        </div>

    </div>

    <div class="text-center text-[10px] text-gray-400 pt-2 pb-6">
        期間: <span class="font-bold">{{ $stats['period']['from'] }} 〜 {{ $stats['period']['to'] }}</span>
    </div>
</div>
@endsection
