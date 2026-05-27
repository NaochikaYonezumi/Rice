<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>新しいパスワード設定 | Rice</title>
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
            <h1 class="h4"><i class="fas fa-lock mr-1"></i> 新しいパスワードを設定</h1>
        </div>
        <div class="card-body">
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

            <form action="{{ route('password.store') }}" method="post">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div class="input-group mb-3">
                    <input type="email" name="email" value="{{ old('email', $request->email) }}"
                           class="form-control @error('email') is-invalid @enderror"
                           placeholder="メールアドレス" required autofocus autocomplete="username">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-envelope"></span>
                        </div>
                    </div>
                </div>

                <div class="input-group mb-3">
                    <input type="password" name="password"
                           class="form-control @error('password') is-invalid @enderror"
                           placeholder="新しいパスワード" required autocomplete="new-password">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>

                <div class="input-group mb-3">
                    <input type="password" name="password_confirmation"
                           class="form-control"
                           placeholder="新しいパスワード (確認)" required autocomplete="new-password">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-check mr-1"></i> パスワードを変更する
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
