@extends('layouts.app')
@section('title', 'メール設定')

@section('content')
<div class="flex-1 overflow-y-auto p-6">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-xl font-semibold text-gray-800 mb-6">メール設定</h1>

        @if(session('success'))
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.mail.update') }}" class="space-y-8">
            @csrf

            {{-- SMTP送信設定 --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">SMTP（送信）</h2>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm text-gray-600 mb-1">SMTPホスト</label>
                        <input type="text" name="smtp_host" value="{{ old('smtp_host', $settings->smtp_host) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="smtp.example.com">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">ポート</label>
                        <input type="number" name="smtp_port" value="{{ old('smtp_port', $settings->smtp_port) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">暗号化</label>
                        <select name="smtp_encryption"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="tls" {{ old('smtp_encryption', $settings->smtp_encryption) === 'tls' ? 'selected' : '' }}>TLS (587)</option>
                            <option value="ssl" {{ old('smtp_encryption', $settings->smtp_encryption) === 'ssl' ? 'selected' : '' }}>SSL (465)</option>
                            <option value="null" {{ old('smtp_encryption', $settings->smtp_encryption) === 'null' ? 'selected' : '' }}>なし (25)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">ユーザー名</label>
                        <input type="text" name="smtp_username" value="{{ old('smtp_username', $settings->smtp_username) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="user@example.com">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">パスワード</label>
                        <input type="password" name="smtp_password" value="{{ old('smtp_password', $settings->smtp_password) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">送信元アドレス</label>
                        <input type="email" name="smtp_from_address" value="{{ old('smtp_from_address', $settings->smtp_from_address) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="user@example.com">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">送信元名</label>
                        <input type="text" name="smtp_from_name" value="{{ old('smtp_from_name', $settings->smtp_from_name) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Mail RAG">
                    </div>
                </div>
            </div>

            {{-- 受信設定 --}}
            <div x-data="{ protocol: '{{ old('inbox_protocol', $settings->inbox_protocol) }}' }"
                 class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">受信設定</h2>

                <div>
                    <label class="block text-sm text-gray-600 mb-1">プロトコル</label>
                    <select name="inbox_protocol" x-model="protocol"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="imap">IMAP</option>
                        <option value="pop3">POP3</option>
                    </select>
                </div>

                {{-- IMAP --}}
                <div x-show="protocol === 'imap'" class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm text-gray-600 mb-1">IMAPホスト</label>
                        <input type="text" name="imap_host" value="{{ old('imap_host', $settings->imap_host) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="imap.example.com">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">ポート</label>
                        <input type="number" name="imap_port" value="{{ old('imap_port', $settings->imap_port) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">暗号化</label>
                        <select name="imap_encryption"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="ssl" {{ old('imap_encryption', $settings->imap_encryption) === 'ssl' ? 'selected' : '' }}>SSL (993)</option>
                            <option value="tls" {{ old('imap_encryption', $settings->imap_encryption) === 'tls' ? 'selected' : '' }}>STARTTLS (143)</option>
                            <option value="null" {{ old('imap_encryption', $settings->imap_encryption) === 'null' ? 'selected' : '' }}>なし</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">ユーザー名</label>
                        <input type="text" name="imap_username" value="{{ old('imap_username', $settings->imap_username) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="user@example.com">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">パスワード</label>
                        <input type="password" name="imap_password" value="{{ old('imap_password', $settings->imap_password) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm text-gray-600 mb-1">フォルダ</label>
                        <input type="text" name="imap_folder" value="{{ old('imap_folder', $settings->imap_folder) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="INBOX">
                    </div>
                </div>

                {{-- POP3 --}}
                <div x-show="protocol === 'pop3'" class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm text-gray-600 mb-1">POP3ホスト</label>
                        <input type="text" name="pop_host" value="{{ old('pop_host', $settings->pop_host) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="pop.example.com">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">ポート</label>
                        <input type="number" name="pop_port" value="{{ old('pop_port', $settings->pop_port) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">暗号化</label>
                        <select name="pop_encryption"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="ssl" {{ old('pop_encryption', $settings->pop_encryption) === 'ssl' ? 'selected' : '' }}>SSL (995)</option>
                            <option value="tls" {{ old('pop_encryption', $settings->pop_encryption) === 'tls' ? 'selected' : '' }}>STARTTLS (110)</option>
                            <option value="null" {{ old('pop_encryption', $settings->pop_encryption) === 'null' ? 'selected' : '' }}>なし</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">ユーザー名</label>
                        <input type="text" name="pop_username" value="{{ old('pop_username', $settings->pop_username) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="user@example.com">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">パスワード</label>
                        <input type="password" name="pop_password" value="{{ old('pop_password', $settings->pop_password) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                    保存
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
