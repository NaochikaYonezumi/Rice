@extends('layouts.app')

@section('title', 'メール設定')
@section('header', 'メール設定')

@section('content')
<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-envelope mr-1"></i> メールの送受信設定</h3>
            </div>
            
            <form method="POST" action="{{ route('settings.mail.update') }}">
                @csrf
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                            <h5><i class="icon fas fa-check"></i> 成功</h5>
                            {{ session('success') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                            <h5><i class="icon fas fa-ban"></i> エラー</h5>
                            <ul>
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- SMTP送信設定 -->
                    <h5 class="mb-3 text-primary border-bottom pb-2">SMTP送信（送信）</h5>
                    <div class="row">
                        <div class="col-md-9 form-group">
                            <label>SMTPホスト</label>
                            <input type="text" name="smtp_host" value="{{ old('smtp_host', $settings->smtp_host) }}" class="form-control" placeholder="smtp.example.com">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>ポート</label>
                            <input type="number" name="smtp_port" value="{{ old('smtp_port', $settings->smtp_port) }}" class="form-control" placeholder="587">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>暗号化</label>
                            <select name="smtp_encryption" class="form-control">
                                <option value="tls" {{ old('smtp_encryption', $settings->smtp_encryption) === 'tls' ? 'selected' : '' }}>TLS</option>
                                <option value="ssl" {{ old('smtp_encryption', $settings->smtp_encryption) === 'ssl' ? 'selected' : '' }}>SSL</option>
                                <option value="null" {{ old('smtp_encryption', $settings->smtp_encryption) === 'null' ? 'selected' : '' }}>なし</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>ユーザー名</label>
                            <input type="text" name="smtp_username" value="{{ old('smtp_username', $settings->smtp_username) }}" class="form-control">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>パスワード</label>
                            <input type="password" name="smtp_password" value="{{ old('smtp_password', $settings->smtp_password) }}" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>送信元アドレス</label>
                            <input type="email" name="smtp_from_address" value="{{ old('smtp_from_address', $settings->smtp_from_address) }}" class="form-control" placeholder="no-reply@example.com">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>送信元名</label>
                            <input type="text" name="smtp_from_name" value="{{ old('smtp_from_name', $settings->smtp_from_name) }}" class="form-control" placeholder="Rice Support">
                        </div>
                    </div>

                    <!-- 受信設定 -->
                    <h5 class="mt-4 mb-3 text-primary border-bottom pb-2">受信設定</h5>
                    <div class="form-group">
                        <label>プロトコル</label>
                        <select name="inbox_protocol" id="inbox_protocol" class="form-control">
                            <option value="imap" {{ old('inbox_protocol', $settings->inbox_protocol) === 'imap' ? 'selected' : '' }}>IMAP</option>
                            <option value="pop3" {{ old('inbox_protocol', $settings->inbox_protocol) === 'pop3' ? 'selected' : '' }}>POP3</option>
                        </select>
                    </div>

                    <!-- IMAP設定 -->
                    <div id="imap_settings" class="protocol-settings">
                        <div class="row">
                            <div class="col-md-9 form-group">
                                <label>IMAPホスト</label>
                                <input type="text" name="imap_host" value="{{ old('imap_host', $settings->imap_host) }}" class="form-control" placeholder="imap.example.com">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>ポート</label>
                                <input type="number" name="imap_port" value="{{ old('imap_port', $settings->imap_port) }}" class="form-control" placeholder="993">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>暗号化</label>
                                <select name="imap_encryption" class="form-control">
                                    <option value="ssl" {{ old('imap_encryption', $settings->imap_encryption) === 'ssl' ? 'selected' : '' }}>SSL</option>
                                    <option value="tls" {{ old('imap_encryption', $settings->imap_encryption) === 'tls' ? 'selected' : '' }}>TLS</option>
                                    <option value="null" {{ old('imap_encryption', $settings->imap_encryption) === 'null' ? 'selected' : '' }}>なし</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>ユーザー名</label>
                                <input type="text" name="imap_username" value="{{ old('imap_username', $settings->imap_username) }}" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>パスワード</label>
                                <input type="password" name="imap_password" value="{{ old('imap_password', $settings->imap_password) }}" class="form-control">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>取得フォルダ</label>
                            <input type="text" name="imap_folder" value="{{ old('imap_folder', $settings->imap_folder) }}" class="form-control" placeholder="INBOX">
                        </div>
                    </div>

                    <!-- POP3設定 -->
                    <div id="pop3_settings" class="protocol-settings" style="display: none;">
                        <div class="row">
                            <div class="col-md-9 form-group">
                                <label>POP3ホスト</label>
                                <input type="text" name="pop_host" value="{{ old('pop_host', $settings->pop_host) }}" class="form-control" placeholder="pop.example.com">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>ポート</label>
                                <input type="number" name="pop_port" value="{{ old('pop_port', $settings->pop_port) }}" class="form-control" placeholder="995">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>暗号化</label>
                                <select name="pop_encryption" class="form-control">
                                    <option value="ssl" {{ old('pop_encryption', $settings->pop_encryption) === 'ssl' ? 'selected' : '' }}>SSL</option>
                                    <option value="tls" {{ old('pop_encryption', $settings->pop_encryption) === 'tls' ? 'selected' : '' }}>TLS</option>
                                    <option value="null" {{ old('pop_encryption', $settings->pop_encryption) === 'null' ? 'selected' : '' }}>なし</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>ユーザー名</label>
                                <input type="text" name="pop_username" value="{{ old('pop_username', $settings->pop_username) }}" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>パスワード</label>
                                <input type="password" name="pop_password" value="{{ old('pop_password', $settings->pop_password) }}" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary px-4">設定を保存</button>
                </div>
            </form>
        </div>

        <div class="card card-secondary card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-sync mr-1"></i> 手動同期</h3>
            </div>
            <div class="card-body text-center">
                <p>保存された設定を使用して、今すぐメールを取得します。</p>
                <form action="{{ route('emails.fetch') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-download mr-1"></i> 今すぐ取得
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
// layouts/app.blade.php の jQuery / Bootstrap / AdminLTE は defer 読み込みのため、
// インラインスクリプトがパース到達した時点では $ が未定義。
// DOMContentLoaded は defer スクリプトの実行後に発火するので、ここで待つ。
document.addEventListener('DOMContentLoaded', function() {
$(function() {
    function toggleProtocol() {
        var protocol = $('#inbox_protocol').val();
        $('.protocol-settings').hide();
        if (protocol === 'imap') {
            $('#imap_settings').show();
        } else if (protocol === 'pop3') {
            $('#pop3_settings').show();
        }
    }

    $('#inbox_protocol').on('change', toggleProtocol);
    toggleProtocol();
});
});
</script>
@endsection
