@extends('layouts.app')

@section('title', '認証アプリの設定')
@section('header', '認証アプリの設定')

@section('content')
<div class="row">
    <div class="col-md-8 col-lg-6 mx-auto">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-mobile-alt mr-1"></i>
                    認証アプリで二段階認証を設定
                </h5>
            </div>
            <div class="card-body">

                @if($alreadyEnabled)
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        既に認証アプリでの二段階認証が有効です. 別のアプリで再設定する場合は一度
                        「無効化」してから再度こちらのページから設定してください.
                    </div>
                    <form method="POST" action="{{ route('totp.disable') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="fas fa-power-off"></i> 認証アプリでの 2FA を無効化
                        </button>
                        <a href="{{ route('profile.edit') }}" class="btn btn-link">プロフィールへ戻る</a>
                    </form>
                @else
                    <ol class="mb-4" style="font-size:13px;line-height:1.8;">
                        <li>スマートフォンに <strong>Google Authenticator</strong> / <strong>Microsoft Authenticator</strong> / <strong>Authy</strong> のいずれかをインストール</li>
                        <li>アプリを開き、「アカウントを追加」 → 「QR コードをスキャン」</li>
                        <li>下の QR コードを読み取る</li>
                        <li>アプリに表示された 6 桁コードを下の入力欄に入力</li>
                    </ol>

                    <div class="text-center mb-3">
                        <img src="{{ $qrDataUrl }}" alt="2FA QR Code"
                             style="width:240px;height:240px;border:1px solid #e5e7eb;border-radius:8px;padding:8px;background:#fff;">
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">QR が読めない場合: 手動入力キー</label>
                        <input type="text" class="form-control text-monospace" readonly value="{{ $secret }}"
                               onclick="this.select()" style="font-size:12px;background:#f9fafb;">
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger">
                            @foreach($errors->all() as $err)
                                <div>{{ $err }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('totp.confirm') }}">
                        @csrf
                        <div class="form-group">
                            <label><strong>アプリに表示された 6 桁コード</strong></label>
                            <input type="text" name="code"
                                   class="form-control text-center"
                                   maxlength="10" autocomplete="one-time-code" inputmode="numeric"
                                   placeholder="123456" required
                                   style="font-size:24px;letter-spacing:0.4em;font-weight:bold;">
                        </div>
                        <div class="d-flex align-items-center">
                            <a href="{{ route('profile.edit') }}" class="btn btn-link">キャンセル</a>
                            <button type="submit" class="btn btn-primary ml-auto">
                                <i class="fas fa-check"></i> 有効化する
                            </button>
                        </div>
                    </form>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection
