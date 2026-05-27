@extends('layouts.app')

@section('title', 'メールアカウント')
@section('header', '個人メールアカウント')

@section('content')
<div class="row">
    <div class="col-md-10 mx-auto">
        @if(session('status'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <i class="fas fa-check mr-1"></i> {{ session('status') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
            </div>
        @endif

        <div class="card card-primary card-outline">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title flex-grow-1"><i class="fas fa-envelope-open-text mr-1"></i> 個人メールアカウント一覧</h3>
                <a href="{{ route('mail-accounts.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i> 追加
                </a>
            </div>
            <div class="card-body p-0">
                @if($accounts->isEmpty())
                    <div class="p-4 text-center text-muted">
                        まだメールアカウントを登録していません。<br>
                        <a href="{{ route('mail-accounts.create') }}" class="btn btn-link">最初のアカウントを追加</a>
                    </div>
                @else
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>名前 / メールアドレス</th>
                                <th class="text-center">受信</th>
                                <th class="text-center">送信</th>
                                <th class="text-center">有効</th>
                                <th>最終取得</th>
                                <th class="text-right">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accounts as $account)
                                <tr>
                                    <td>
                                        <div class="font-weight-bold">{{ $account->name }}</div>
                                        <div class="text-sm text-muted">{{ $account->email_address }}</div>
                                    </td>
                                    <td class="text-center">
                                        @if($account->inbox_protocol === 'imap')
                                            <span class="badge badge-info">IMAP</span>
                                        @elseif($account->inbox_protocol === 'pop3')
                                            <span class="badge badge-secondary">POP3</span>
                                        @else
                                            <span class="badge badge-light">無効</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($account->smtp_enabled)
                                            <span class="badge badge-success">SMTP</span>
                                        @else
                                            <span class="badge badge-light">なし</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($account->is_active)
                                            <i class="fas fa-check text-success"></i>
                                        @else
                                            <i class="fas fa-times text-muted"></i>
                                        @endif
                                    </td>
                                    <td class="text-sm text-muted">
                                        {{ $account->last_fetched_at?->format('Y/m/d H:i') ?? '—' }}
                                    </td>
                                    <td class="text-right">
                                        @if($account->canReceive())
                                            <form action="{{ route('mail-accounts.fetch', $account) }}" method="post" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-info" title="今すぐ取得">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('mail-accounts.edit', $account) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('mail-accounts.destroy', $account) }}" method="post" class="d-inline"
                                              onsubmit="return confirm('「{{ $account->name }}」を削除します。よろしいですか?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="card-footer text-muted text-sm">
                ここで追加したアカウントから取得したメールは <strong>あなた本人のみ閲覧可能</strong> です。<br>
                取得は5分おきの自動ジョブで実行されます (個別に「今すぐ取得」も可能)。
            </div>
        </div>
    </div>
</div>
@endsection
