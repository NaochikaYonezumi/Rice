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

                    {{-- 送信ポリシー (作成者の自己送信を許可するか / 承認必須にするか) --}}
                    @php($isAdmin = auth()->user()?->isAdmin())
                    @php($policy  = old('send_policy', $settings->send_policy ?? 'flexible'))
                    <h5 class="mt-4 mb-3 text-primary border-bottom pb-2">
                        <i class="fas fa-shield-alt mr-1"></i> 送信ポリシー
                        @if(!$isAdmin)
                            <small class="text-muted ml-2">（管理者のみ変更可能）</small>
                        @endif
                    </h5>
                    <div class="form-group">
                        <label for="send_policy">承認フローの強制</label>
                        <select name="send_policy" id="send_policy" class="form-control" {{ $isAdmin ? '' : 'disabled' }}>
                            <option value="flexible" {{ $policy === 'flexible' ? 'selected' : '' }}>
                                自由（作成者の自己送信と承認経由の両方を許可）
                            </option>
                            <option value="approval_required" {{ $policy === 'approval_required' ? 'selected' : '' }}>
                                承認必須（送信は必ず承認者の承認を経由する）
                            </option>
                        </select>
                        <small class="form-text text-muted">
                            <strong>自由:</strong> 作成者は「今すぐ送信」(自己送信) と「承認を依頼」(承認経由) のどちらでも選べます。<br>
                            <strong>承認必須:</strong> 作成者の自己送信ボタンを無効化し、必ず承認者の承認後に送信されます。
                        </small>
                        @if(!$isAdmin)
                            {{-- disabled な input は送信されないので、現在値を hidden で送信 (no-op) --}}
                            <input type="hidden" name="send_policy" value="{{ $policy }}">
                        @endif
                    </div>

                    {{-- 保持期間 (ゴミ箱 / 迷惑メール の自動完全削除までの日数) ----------------
                         管理者のみ変更可. 既定 30 日. 範囲 1〜3650.
                         mail:purge-trash / mail:purge-spam が日次実行でこの日数を参照する. --}}
                    @php($trashDays = old('trash_retention_days', $settings->trash_retention_days ?? \App\Models\MailSetting::DEFAULT_TRASH_RETENTION_DAYS))
                    @php($spamDays  = old('spam_retention_days',  $settings->spam_retention_days  ?? \App\Models\MailSetting::DEFAULT_SPAM_RETENTION_DAYS))
                    <h5 class="mt-4 mb-3 text-primary border-bottom pb-2">
                        <i class="fas fa-trash-alt mr-1"></i> 保持期間 (自動完全削除)
                        @if(!$isAdmin)
                            <small class="text-muted ml-2">（管理者のみ変更可能）</small>
                        @endif
                    </h5>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="trash_retention_days">ゴミ箱の保持期間 (日)</label>
                            <input type="number" name="trash_retention_days" id="trash_retention_days"
                                   value="{{ $trashDays }}"
                                   min="{{ \App\Models\MailSetting::MIN_RETENTION_DAYS }}"
                                   max="{{ \App\Models\MailSetting::MAX_RETENTION_DAYS }}"
                                   class="form-control" {{ $isAdmin ? '' : 'disabled' }}>
                            <small class="form-text text-muted">
                                ゴミ箱に入ったスレッド / 個別メールが、この日数経過後に完全削除されます (cascade).
                                既定 30 日.
                            </small>
                            @if(!$isAdmin)
                                <input type="hidden" name="trash_retention_days" value="{{ $trashDays }}">
                            @endif
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="spam_retention_days">迷惑メールの保持期間 (日)</label>
                            <input type="number" name="spam_retention_days" id="spam_retention_days"
                                   value="{{ $spamDays }}"
                                   min="{{ \App\Models\MailSetting::MIN_RETENTION_DAYS }}"
                                   max="{{ \App\Models\MailSetting::MAX_RETENTION_DAYS }}"
                                   class="form-control" {{ $isAdmin ? '' : 'disabled' }}>
                            <small class="form-text text-muted">
                                迷惑メールに振り分けられたスレッドが、振り分け時刻から
                                この日数経過後に完全削除されます. 既定 30 日.
                            </small>
                            @if(!$isAdmin)
                                <input type="hidden" name="spam_retention_days" value="{{ $spamDays }}">
                            @endif
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
                            <input type="text" name="imap_folder" value="{{ old('imap_folder', $settings->imap_folder) }}" class="form-control" placeholder="INBOX (空欄なら全フォルダ取得)">
                            <small class="form-text text-muted">
                                空欄、または「<code>*</code>」「<code>ALL</code>」を指定すると、Sent/Drafts/Trash/Junk/Archive を除く全フォルダから取得します。
                                単一フォルダだけ取得したい場合はフォルダ名を入力してください (例: <code>INBOX</code>)。
                            </small>
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

                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div id="mail-test-result" style="flex:1;text-align:left;"></div>
                    <div>
                        <button type="button" id="btn-mail-test" class="btn btn-outline-secondary mr-2">
                            <i class="fas fa-plug mr-1"></i> 接続テスト
                        </button>
                        <button type="submit" class="btn btn-primary px-4">設定を保存</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card card-secondary card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-sync mr-1"></i> 手動同期</h3>
            </div>
            <div class="card-body text-center">
                <p>保存された設定を使用して、今すぐメールを取得します。</p>
                <button type="button" id="btn-mail-fetch-now" class="btn btn-info">
                    <i class="fas fa-download mr-1"></i> 今すぐ取得
                </button>
                <div id="mail-fetch-now-result" class="mt-3"></div>
            </div>
        </div>

        {{-- 取得結果モーダル (成功でも失敗でも、内容を必ず表示する) --}}
        <div id="mail-fetch-modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" style="display:none;">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content" id="mail-fetch-modal-content"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
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

    // ==== 「今すぐ取得」 (保存済み設定で実取得 + 結果モーダル) ====
    $('#btn-mail-fetch-now').on('click', function() {
        var $btn = $(this);
        var $result = $('#mail-fetch-now-result');
        $btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin mr-1"></i> 取得中...');
        $result.html('<span class="text-muted small"><i class="fas fa-spinner fa-spin"></i> メールサーバに接続しています...</span>');

        $.ajax({
            url: '{{ route("emails.fetch") }}',
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            data: {},
            // status=200 でも status=500 でも JSON で詳細が返るので両方を扱える complete に
            complete: function(xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-download mr-1"></i> 今すぐ取得');
                var data = {};
                try { data = xhr.responseJSON || JSON.parse(xhr.responseText || '{}'); } catch (e) {}

                if (xhr.status >= 200 && xhr.status < 300 && data.status === 'ok') {
                    // 成功 (取り込み件数を表示)
                    var msg = '取得完了: ' + (data.count || 0) + ' 件取り込みました';
                    if (data.error_count > 0) {
                        msg += ' (個別エラー ' + data.error_count + ' 件)';
                    }
                    $result.html('<div class="alert alert-success mb-0"><i class="fas fa-check-circle"></i> ' + msg + '</div>');
                    // 部分エラーがあればモーダルで詳細表示
                    if (data.error_count > 0) {
                        showMailFetchModal('取得は完了しましたが一部エラーがあります', data, false);
                    }
                } else {
                    // 失敗 (接続不能 / 認証失敗 等): モーダルで全文表示
                    var title = data.error || 'メールサーバーとの同期に失敗しました';
                    $result.html('<div class="alert alert-danger mb-0"><i class="fas fa-exclamation-triangle"></i> ' + escapeHtml(title) + ' <button type="button" class="btn btn-sm btn-link" id="btn-fetch-detail">詳細</button></div>');
                    $('#btn-fetch-detail').on('click', function() {
                        showMailFetchModal('メールサーバーとの同期に失敗しました', data, true);
                    });
                    // 即座にモーダルも開く (ユーザがクリックしなくても気付くように)
                    showMailFetchModal('メールサーバーとの同期に失敗しました', data, true);
                }
            }
        });
    });

    function escapeHtml(s) {
        return $('<div/>').text(s == null ? '' : String(s)).html();
    }

    function showMailFetchModal(title, data, isError) {
        var headerClass = isError ? 'bg-danger' : 'bg-warning';
        var icon = isError ? 'fa-exclamation-triangle' : 'fa-exclamation-circle';
        var body = '';

        if (isError) {
            // 接続詳細
            body += '<p class="mb-2 text-danger"><strong><i class="fas ' + icon + ' mr-1"></i> ' + escapeHtml(data.error || '') + '</strong></p>';
            if (data.connection_error && data.connection_error !== data.error) {
                body += '<div class="alert alert-light border small mb-2">' +
                        '<strong>接続詳細:</strong><br><code>' + escapeHtml(data.connection_error) + '</code></div>';
            }
            if (data.consecutive_failures && data.consecutive_failures > 1) {
                body += '<p class="small text-muted">連続失敗回数: <strong>' + data.consecutive_failures + '</strong></p>';
            }
            body += '<hr><p class="small mb-2"><strong>確認ポイント:</strong></p>';
            body += '<ul class="small">';
            body += '<li>ホスト名 / ポート / 暗号化 (SSL/TLS) の組合せは正しいか</li>';
            body += '<li>ユーザー名 / パスワードは正しいか (xserver は フルメールアドレス)</li>';
            body += '<li>プロトコル (IMAP/POP3) は適切か (xserver は IMAP 推奨)</li>';
            body += '</ul>';
            body += '<p class="small text-muted">画面上部の「接続テスト」ボタンで保存前に検証できます。</p>';
        } else {
            body += '<p class="mb-2"><strong><i class="fas ' + icon + ' mr-1 text-warning"></i> 取り込みは完了しましたが一部エラー (' + (data.error_count || 0) + ' 件) があります</strong></p>';
        }

        if (Array.isArray(data.errors) && data.errors.length > 0) {
            body += '<hr><p class="small mb-1"><strong>個別メールエラー (' + data.errors.length + ' 件):</strong></p>';
            body += '<div style="max-height:280px;overflow:auto;border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fef2f2;">';
            data.errors.slice(0, 50).forEach(function(err) {
                body += '<div class="small mb-2 pb-2 border-bottom">' +
                        '<div><strong>' + escapeHtml(err.subject || '(件名なし)') + '</strong></div>' +
                        '<div class="text-muted">From: ' + escapeHtml(err.from || '?') + '</div>' +
                        '<div style="color:#991b1b;">' + escapeHtml(err.error || '') + '</div>' +
                        '</div>';
            });
            body += '</div>';
        }

        var html = '<div class="modal-header ' + headerClass + ' text-white">' +
                   '<h5 class="modal-title">' + escapeHtml(title) + '</h5>' +
                   '<button type="button" class="close text-white" data-dismiss="modal">&times;</button>' +
                   '</div>' +
                   '<div class="modal-body">' + body + '</div>' +
                   '<div class="modal-footer">' +
                   '<button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>' +
                   '<button type="button" class="btn btn-primary" onclick="$(\'#btn-mail-fetch-now\').click(); $(\'#mail-fetch-modal\').modal(\'hide\');">再試行</button>' +
                   '</div>';
        $('#mail-fetch-modal-content').html(html);
        $('#mail-fetch-modal').modal('show');
    }

    // ==== 接続テスト (保存前に認証/接続/SSL を検証) ====
    $('#btn-mail-test').on('click', function() {
        var $btn = $(this);
        var $result = $('#mail-test-result');
        $btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin mr-1"></i> テスト中...');
        $result.html('<span class="text-muted small"><i class="fas fa-spinner fa-spin"></i> サーバに接続しています...</span>');

        // フォーム値をそのまま POST (DB 保存はしない)
        var form = $('form[action="{{ route("settings.mail.update") }}"]').first();
        var payload = form.serialize();

        $.ajax({
            url: '{{ route("settings.mail.test") }}',
            method: 'POST',
            data: payload,
            headers: { 'Accept': 'application/json' },
            success: function(res) {
                if (res.status === 'ok') {
                    $result.html(
                        '<span class="badge badge-success"><i class="fas fa-check"></i> 成功</span> ' +
                        '<span class="small">' + (res.message || '接続できました') + '</span>'
                    );
                } else {
                    // 200 OK だが status=error の場合 (テストエンドポイントは認証/接続失敗を 200 で返す)
                    $result.html(
                        '<span class="badge badge-danger"><i class="fas fa-times"></i> 失敗 (' + (res.stage || '?') + ')</span> ' +
                        '<span class="small text-danger">' + (res.message || 'エラー') + '</span>' +
                        (res.raw ? '<br><small class="text-muted">raw: ' + $('<div/>').text(res.raw).html() + '</small>' : '')
                    );
                }
            },
            error: function(xhr) {
                var data = xhr.responseJSON || {};
                var msg = data.message || ('HTTP ' + xhr.status);
                $result.html(
                    '<span class="badge badge-danger"><i class="fas fa-times"></i> 通信エラー</span> ' +
                    '<span class="small text-danger">' + msg + '</span>'
                );
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-plug mr-1"></i> 接続テスト');
            }
        });
    });
});
</script>
@endsection
