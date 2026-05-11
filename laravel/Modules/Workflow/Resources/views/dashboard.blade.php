@extends('layouts.app')
@section('title', 'レポートダッシュボード')
@section('header', 'レポート統計')

@section('content')
<div class="row">
    {{-- サマリーカード --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $stats['total_threads'] }}</h3>
                <p>総スレッド数</p>
            </div>
            <div class="icon">
                <i class="fas fa-envelope"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $stats['open_threads'] }}</h3>
                <p>未対応スレッド</p>
            </div>
            <div class="icon">
                <i class="fas fa-inbox"></i>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- エージェント別統計 --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">エージェント別返信数</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>エージェント</th>
                            <th>返信数</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stats['agent_replies'] as $agent)
                        <tr>
                            <td>{{ $agent->name }}</td>
                            <td><span class="badge bg-primary">{{ $agent->reply_count }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ステータス別統計 --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">ステータス分布</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>ステータス</th>
                            <th>スレッド数</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stats['status_stats'] as $stat)
                        <tr>
                            <td>{{ __('mail.status.' . $stat->status, [], 'ja') !== 'mail.status.' . $stat->status ? __('mail.status.' . $stat->status) : $stat->status }}</td>
                            <td>{{ $stat->count }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- タグ別統計 --}}
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">人気タグ</h3>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($stats['tag_stats'] as $tag => $count)
                    <div class="p-2 border rounded bg-light mr-2 mb-2">
                        <strong>#{{ $tag }}</strong>: {{ $count }}
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
