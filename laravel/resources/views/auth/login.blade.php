<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ログイン | Rice</title>
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
            <h1 class="h4">ログイン</h1>
        </div>
        <div class="card-body">
            @if(session('error'))
                <div class="alert alert-danger text-sm">
                    {{ session('error') }}
                </div>
            @endif

            @if(session('status'))
                <div class="alert alert-success text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger text-sm mb-3">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    <strong>ログインできませんでした</strong>
                    <ul class="mb-0 mt-1 pl-3" style="list-style: disc;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('login') }}" method="post">
                @csrf
                <div class="input-group mb-1">
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror"
                           placeholder="メールアドレス" required autofocus>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-envelope"></span>
                        </div>
                    </div>
                </div>
                @error('email')
                    <p class="text-danger text-sm mb-2 mt-0">{{ $message }}</p>
                @else
                    <div class="mb-3"></div>
                @enderror
                <div class="input-group mb-1">
                    <input type="password" name="password"
                           class="form-control @error('password') is-invalid @enderror"
                           placeholder="パスワード" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>
                @error('password')
                    <p class="text-danger text-sm mb-2 mt-0">{{ $message }}</p>
                @else
                    <div class="mb-3"></div>
                @enderror
                <div class="row">
                    <div class="col-8">
                        <div class="icheck-primary">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">ログイン状態を保持</label>
                        </div>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary btn-block">ログイン</button>
                    </div>
                </div>
            </form>

            <div class="social-auth-links text-center mt-4 mb-3">
                <p>- OR -</p>
                <a href="{{ route('auth.redirect', ['provider' => 'azure']) }}" class="btn btn-block btn-outline-primary">
                    <i class="fab fa-microsoft mr-2"></i> Microsoftアカウントでログイン
                </a>
            </div>

            @if(Route::has('password.request'))
                <p class="mb-1">
                    <a href="{{ route('password.request') }}" class="text-sm">パスワードを忘れた場合</a>
                </p>
            @endif
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
