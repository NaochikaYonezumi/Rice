<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>リカバリーコード | Rice</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .code-card { font-family: monospace; font-size: 18px; font-weight: bold; letter-spacing: 2px;
                     padding: 12px 16px; background-color: #f3f4f6; border-radius: 6px; text-align: center; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
        }
    </style>
</head>
<body class="hold-transition bg-light">
<div class="container" style="max-width: 640px; margin-top: 40px;">
    <div class="card card-outline card-warning">
        <div class="card-header">
            <h1 class="h4 mb-0">
                <i class="fas fa-key mr-1"></i> リカバリーコードを保管してください
            </h1>
        </div>
        <div class="card-body">
            <div class="alert alert-warning text-sm">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                <strong>この画面は一度しか表示されません。</strong><br>
                スマートフォン紛失などでメールを受信できない場合、これらのコードでログインできます。
                印刷するかパスワードマネージャーに保存してください。各コードは<strong>1回のみ</strong>使用できます。
            </div>

            <div class="row">
                @foreach($codes as $code)
                    <div class="col-6 mb-2">
                        <div class="code-card">{{ $code }}</div>
                    </div>
                @endforeach
            </div>

            <div class="row mt-3 no-print">
                <div class="col-6">
                    <button type="button" class="btn btn-secondary btn-block" onclick="window.print()">
                        <i class="fas fa-print mr-1"></i> 印刷
                    </button>
                </div>
                <div class="col-6">
                    <button type="button" id="copyBtn" class="btn btn-secondary btn-block">
                        <i class="fas fa-copy mr-1"></i> クリップボードにコピー
                    </button>
                </div>
            </div>

            <hr class="no-print">

            <form action="{{ route('two-factor.recovery-codes.acknowledge') }}" method="post" class="no-print">
                @csrf
                <div class="icheck-primary mb-3">
                    <input type="checkbox" id="ack" name="ack" value="1" required>
                    <label for="ack">リカバリーコードを安全な場所に保管しました</label>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-check mr-1"></i> 確認して次へ進む
                </button>
            </form>
        </div>
    </div>
</div>

<textarea id="codesPlain" style="position: absolute; left: -9999px;">@foreach($codes as $code){{ $code }}
@endforeach</textarea>

<script>
    document.getElementById('copyBtn').addEventListener('click', function () {
        const ta = document.getElementById('codesPlain');
        ta.select();
        try {
            document.execCommand('copy');
            this.innerHTML = '<i class="fas fa-check mr-1"></i> コピーしました';
        } catch (e) {
            alert('コピーに失敗しました');
        }
    });
</script>
</body>
</html>
