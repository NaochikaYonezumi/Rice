<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>二段階認証 | Rice</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .otp-input { letter-spacing: 12px; font-size: 24px; text-align: center; font-weight: bold; }
        .recovery-input { font-family: monospace; text-transform: uppercase; }
    </style>
</head>
<body class="hold-transition login-page bg-light">
<div class="login-box" style="width: 420px;">
    <div class="login-logo">
        <b>Rice</b>
    </div>
    <div class="card card-outline card-primary" x-data="{ mode: 'code' }" x-cloak>
        <div class="card-header text-center">
            <h1 class="h4"><i class="fas fa-shield-alt mr-1"></i> 二段階認証</h1>
        </div>
        <div class="card-body">
            @if(session('error'))
                <div class="alert alert-danger text-sm">{{ session('error') }}</div>
            @endif
            @if(session('status'))
                <div class="alert alert-success text-sm">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger text-sm mb-3">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    <ul class="mb-0 mt-1 pl-3" style="list-style: disc;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($usesTotp))
                <p class="text-sm text-muted mb-3">
                    <i class="fas fa-mobile-alt"></i>
                    <strong>認証アプリ</strong>に表示された 6 桁コードを入力してください。<br>
                    <span style="font-size:11px;color:#9ca3af;">
                        アプリが使えない場合は「メールでの再送」ボタンを押してメール経由で受け取ることもできます.
                    </span>
                </p>
            @else
                <p class="text-sm text-muted mb-3">
                    <strong>{{ $maskedEmail }}</strong> 宛に認証コードを送信しました。<br>
                    メールに記載された6桁のコードを入力してください。
                </p>
            @endif

            {{-- コード入力モード --}}
            <div x-show="mode === 'code'">
                <form action="{{ route('two-factor.verify') }}" method="post">
                    @csrf
                    <div class="form-group">
                        <input type="text" name="code"
                               class="form-control otp-input @error('code') is-invalid @enderror"
                               inputmode="numeric"
                               autocomplete="one-time-code"
                               maxlength="6"
                               placeholder="------"
                               required autofocus>
                    </div>
                    <div class="icheck-primary mb-3">
                        <input type="checkbox" id="trust_device" name="trust_device" value="1">
                        <label for="trust_device">このブラウザを{{ config('two_factor.trusted_device_days', 30) }}日間信頼する</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt mr-1"></i> ログイン
                    </button>
                </form>

                <form action="{{ route('two-factor.resend') }}" method="post" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-link btn-block text-sm">
                        <i class="fas fa-redo mr-1"></i> コードを再送する
                    </button>
                </form>

                <hr>
                <p class="mb-1 text-center">
                    <a href="#" class="text-sm" @click.prevent="mode = 'recovery'">
                        <i class="fas fa-key mr-1"></i> リカバリーコードでログイン
                    </a>
                </p>
            </div>

            {{-- リカバリーコード入力モード --}}
            <div x-show="mode === 'recovery'" style="display: none;">
                <form action="{{ route('two-factor.recovery') }}" method="post">
                    @csrf
                    <p class="text-sm text-muted">
                        登録時に保存したリカバリーコードを1つ入力してください。
                    </p>
                    <div class="form-group">
                        <input type="text" name="recovery_code"
                               class="form-control recovery-input @error('recovery_code') is-invalid @enderror"
                               placeholder="XXXXX-XXXXX"
                               autocomplete="off"
                               required>
                    </div>
                    <button type="submit" class="btn btn-warning btn-block">
                        <i class="fas fa-unlock-alt mr-1"></i> リカバリーコードでログイン
                    </button>
                </form>
                <p class="mt-3 mb-1 text-center">
                    <a href="#" class="text-sm" @click.prevent="mode = 'code'">
                        <i class="fas fa-arrow-left mr-1"></i> コード入力に戻る
                    </a>
                </p>
            </div>

            <hr>
            <form action="{{ route('logout') }}" method="post" class="text-center">
                @csrf
                <button type="submit" class="btn btn-link text-sm text-muted">
                    <i class="fas fa-times mr-1"></i> ログインをキャンセル
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
</body>
</html>
