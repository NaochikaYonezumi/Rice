@extends('layouts.app')

@section('title', $account->exists ? 'メールアカウント編集' : 'メールアカウント追加')
@section('header', $account->exists ? 'メールアカウント編集' : 'メールアカウント追加')

@section('content')
<div class="row">
    <div class="col-md-10 mx-auto">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pl-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $account->exists ? route('mail-accounts.update', $account) : route('mail-accounts.store') }}"
              id="mailAccountForm"
              x-data="{
                  inbox: '{{ old('inbox_protocol', $account->inbox_protocol ?: 'imap') }}',
                  smtp:  {{ old('smtp_enabled', $account->smtp_enabled) ? 'true' : 'false' }},
                  active: {{ old('is_active', $account->exists ? $account->is_active : true) ? 'true' : 'false' }},
                  testing: false,
                  testResult: null,
                  async runTest() {
                      this.testing = true;
                      this.testResult = null;
                      try {
                          const fd = new FormData(document.getElementById('mailAccountForm'));
                          @if($account->exists)
                            fd.append('mail_account_id', '{{ $account->id }}');
                          @endif
                          const res = await fetch('{{ route('mail-accounts.test') }}', {
                              method: 'POST',
                              headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                              body: fd,
                          });
                          this.testResult = await res.json();
                      } catch (e) {
                          this.testResult = { error: e.message };
                      } finally {
                          this.testing = false;
                      }
                  }
              }">
            @csrf
            @if($account->exists) @method('PUT') @endif

            <div class="card card-primary card-outline">
                <div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-id-card mr-1"></i> 基本情報</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>アカウント名 <span class="text-danger">*</span></label>
                            <input type="text" name="name" required maxlength="100"
                                   class="form-control" placeholder="個人Gmail / 仕事用 など"
                                   value="{{ old('name', $account->name) }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>メールアドレス <span class="text-danger">*</span></label>
                            <input type="email" name="email_address" required
                                   class="form-control" placeholder="you@example.com"
                                   value="{{ old('email_address', $account->email_address) }}">
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input" x-model="active">
                        <label for="is_active" class="form-check-label">このアカウントを有効にする (取得・送信を許可)</label>
                    </div>
                </div>
            </div>

            <div class="card card-info card-outline">
                <div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-inbox mr-1"></i> 受信設定</h5></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>受信プロトコル</label>
                        <select name="inbox_protocol" class="form-control" x-model="inbox">
                            <option value="imap">IMAP</option>
                            <option value="pop3">POP3</option>
                            <option value="disabled">受信しない</option>
                        </select>
                    </div>

                    <div x-show="inbox === 'imap'" x-cloak>
                        <div class="row">
                            <div class="col-md-9 form-group">
                                <label>IMAPホスト</label>
                                <input type="text" name="imap_host" class="form-control" placeholder="imap.example.com"
                                       value="{{ old('imap_host', $account->imap_host) }}">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>ポート</label>
                                <input type="number" name="imap_port" class="form-control"
                                       value="{{ old('imap_port', $account->imap_port ?: 993) }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>暗号化</label>
                                <select name="imap_encryption" class="form-control">
                                    @foreach(['ssl' => 'SSL', 'tls' => 'TLS', 'null' => 'なし'] as $v => $l)
                                        <option value="{{ $v }}" {{ old('imap_encryption', $account->imap_encryption ?: 'ssl') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8 form-group">
                                <label>ユーザー名</label>
                                <input type="text" name="imap_username" class="form-control" autocomplete="off"
                                       value="{{ old('imap_username', $account->imap_username) }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>パスワード {{ $account->exists ? '(空のままで変更しない)' : '' }}</label>
                                <input type="password" name="imap_password" class="form-control" autocomplete="new-password" placeholder="********">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>取得フォルダ</label>
                                <input type="text" name="imap_folder" class="form-control" placeholder="INBOX"
                                       value="{{ old('imap_folder', $account->imap_folder ?: 'INBOX') }}">
                            </div>
                        </div>
                    </div>

                    <div x-show="inbox === 'pop3'" x-cloak>
                        <div class="row">
                            <div class="col-md-9 form-group">
                                <label>POP3ホスト</label>
                                <input type="text" name="pop_host" class="form-control" placeholder="pop.example.com"
                                       value="{{ old('pop_host', $account->pop_host) }}">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>ポート</label>
                                <input type="number" name="pop_port" class="form-control"
                                       value="{{ old('pop_port', $account->pop_port ?: 995) }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>暗号化</label>
                                <select name="pop_encryption" class="form-control">
                                    @foreach(['ssl' => 'SSL', 'null' => 'なし'] as $v => $l)
                                        <option value="{{ $v }}" {{ old('pop_encryption', $account->pop_encryption ?: 'ssl') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8 form-group">
                                <label>ユーザー名</label>
                                <input type="text" name="pop_username" class="form-control" autocomplete="off"
                                       value="{{ old('pop_username', $account->pop_username) }}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>パスワード {{ $account->exists ? '(空のままで変更しない)' : '' }}</label>
                            <input type="password" name="pop_password" class="form-control" autocomplete="new-password" placeholder="********">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-success card-outline">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-paper-plane mr-1"></i> 送信設定 (SMTP)</h5>
                </div>
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input type="hidden" name="smtp_enabled" value="0">
                        <input type="checkbox" id="smtp_enabled" name="smtp_enabled" value="1" class="form-check-input" x-model="smtp">
                        <label for="smtp_enabled" class="form-check-label">このアカウントで送信を有効にする</label>
                    </div>

                    <div x-show="smtp" x-cloak>
                        <div class="row">
                            <div class="col-md-9 form-group">
                                <label>SMTPホスト</label>
                                <input type="text" name="smtp_host" class="form-control" placeholder="smtp.example.com"
                                       value="{{ old('smtp_host', $account->smtp_host) }}">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>ポート</label>
                                <input type="number" name="smtp_port" class="form-control"
                                       value="{{ old('smtp_port', $account->smtp_port ?: 587) }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>暗号化</label>
                                <select name="smtp_encryption" class="form-control">
                                    @foreach(['tls' => 'TLS', 'ssl' => 'SSL', 'null' => 'なし'] as $v => $l)
                                        <option value="{{ $v }}" {{ old('smtp_encryption', $account->smtp_encryption ?: 'tls') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8 form-group">
                                <label>ユーザー名</label>
                                <input type="text" name="smtp_username" class="form-control" autocomplete="off"
                                       value="{{ old('smtp_username', $account->smtp_username) }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>パスワード {{ $account->exists ? '(空のままで変更しない)' : '' }}</label>
                                <input type="password" name="smtp_password" class="form-control" autocomplete="new-password" placeholder="********">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>表示名 (From名)</label>
                                <input type="text" name="smtp_from_name" class="form-control"
                                       value="{{ old('smtp_from_name', $account->smtp_from_name) }}"
                                       placeholder="例: 山田 太郎 (省略時はアカウント名)">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 接続テスト結果 --}}
            <div x-show="testResult" x-cloak class="mb-3">
                <template x-if="testResult && testResult.inbox">
                    <div class="alert text-sm" :class="testResult.inbox.status === 'ok' ? 'alert-success' : 'alert-danger'">
                        <i class="fas" :class="testResult.inbox.status === 'ok' ? 'fa-check-circle' : 'fa-exclamation-triangle'"></i>
                        <strong>受信:</strong>
                        <span x-text="testResult.inbox.message"></span>
                        <template x-if="testResult.inbox.raw">
                            <details class="mt-1"><summary class="small">詳細</summary><code class="small" x-text="testResult.inbox.raw"></code></details>
                        </template>
                    </div>
                </template>
                <template x-if="testResult && testResult.smtp">
                    <div class="alert text-sm" :class="testResult.smtp.status === 'ok' ? 'alert-success' : 'alert-danger'">
                        <i class="fas" :class="testResult.smtp.status === 'ok' ? 'fa-check-circle' : 'fa-exclamation-triangle'"></i>
                        <strong>送信:</strong>
                        <span x-text="testResult.smtp.message"></span>
                        <template x-if="testResult.smtp.raw">
                            <details class="mt-1"><summary class="small">詳細</summary><code class="small" x-text="testResult.smtp.raw"></code></details>
                        </template>
                    </div>
                </template>
                <template x-if="testResult && !testResult.inbox && !testResult.smtp">
                    <div class="alert alert-warning text-sm">
                        <i class="fas fa-info-circle"></i> 受信プロトコルが「受信しない」でかつ SMTP も未有効なので、テストする対象がありません。
                    </div>
                </template>
                <template x-if="testResult && testResult.error">
                    <div class="alert alert-danger text-sm">
                        <i class="fas fa-times"></i> リクエスト失敗: <span x-text="testResult.error"></span>
                    </div>
                </template>
            </div>

            <div class="d-flex align-items-center">
                <a href="{{ route('mail-accounts.index') }}" class="btn btn-secondary">キャンセル</a>
                <button type="button" class="btn btn-outline-info ml-2" @click="runTest()" :disabled="testing">
                    <i class="fas" :class="testing ? 'fa-spinner fa-spin' : 'fa-plug'"></i>
                    <span x-text="testing ? 'テスト中...' : '接続テスト'"></span>
                </button>
                <button type="submit" class="btn btn-primary ml-auto" :disabled="testing">
                    <i class="fas fa-save mr-1"></i> 保存
                </button>
            </div>
        </form>
    </div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
@endsection
