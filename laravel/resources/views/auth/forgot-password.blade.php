<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>パスワード再設定 | Rice</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="hold-transition login-page bg-light">
<div class="login-box">
    <div class="login-logo">
        <b>Rice</b>
    </div>
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <h1 class="h4"><i class="fas fa-key mr-1"></i> パスワード再設定</h1>
        </div>
        <div class="card-body">
            <p class="text-sm text-muted mb-3">
                ご登録のメールアドレスを入力してください。<br>
                新しいパスワードを設定するためのリンクをメールでお送りします。
            </p>

            @if(session('status'))
                <div class="alert alert-success text-sm">
                    <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
                </div>
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

            <form action="{{ route('password.email') }}" method="post">
                @csrf
                <div class="input-group mb-3">
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror"
                           placeholder="メールアドレス" required autofocus>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-envelope"></span>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-paper-plane mr-1"></i> 再設定リンクを送信
                </button>
            </form>

            <hr>
            <p class="mb-1 text-center">
                <a href="{{ route('login') }}" class="text-sm">
                    <i class="fas fa-arrow-left mr-1"></i> ログイン画面に戻る
                </a>
            </p>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
