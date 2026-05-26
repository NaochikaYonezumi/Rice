@extends('layouts.app')
@section('title', '招待管理')

@section('css')
<style>
    .content-header { display: none !important; }
    .content, .content > .container-fluid {
        padding: 0 !important;
        max-width: 100% !important;
        height: calc(100vh - 3.5rem);
        overflow-y: auto;
        background: #f9fafb;
    }
</style>
@endsection

@section('content')
<div class="py-5 space-y-5" style="padding-left:8.333%;padding-right:8.333%;">

    {{-- ヘッダー --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-extrabold text-gray-900">招待管理</h1>
            <p class="text-xs text-gray-500 mt-0.5">ユーザーをメールで招待し、ロールを設定できます</p>
        </div>
        <div class="text-xs text-gray-500">
            登録済み: <span class="font-bold text-gray-700">{{ count($invitations) }}</span> 件
        </div>
    </div>

    {{-- フラッシュメッセージ --}}
    @if(session('success'))
        <div class="bg-green-50 text-green-700 border border-green-200 rounded-lg px-4 py-3 text-sm font-semibold flex items-center gap-2">
            <i class="fas fa-check-circle"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-lg px-4 py-3 text-sm font-semibold flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- 新規招待フォーム --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        <div class="flex items-center gap-2 mb-3">
            <i class="fas fa-user-plus text-blue-500 text-sm"></i>
            <h2 class="text-sm font-bold text-gray-800">新しいユーザーを招待</h2>
        </div>
        <form action="{{ route('admin.invitations.store') }}" method="POST" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[240px]">
                <label class="block text-xs font-bold text-gray-500 mb-1">メールアドレス</label>
                <input type="email" name="email" required
                       placeholder="user@example.com"
                       value="{{ old('email') }}"
                       class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-300">
                @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="w-40">
                <label class="block text-xs font-bold text-gray-500 mb-1">ロール</label>
                <select name="role" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-300">
                    <option value="member" {{ old('role') === 'member' ? 'selected' : '' }}>メンバー</option>
                    <option value="admin"  {{ old('role') === 'admin'  ? 'selected' : '' }}>管理者</option>
                </select>
            </div>
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-bold inline-flex items-center gap-2 transition-all"
                    style="background-color:#2563eb;color:#ffffff;">
                <i class="fas fa-paper-plane"></i>
                招待を送信
            </button>
        </form>
    </div>

    {{-- 招待一覧 --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/60 flex items-center gap-2">
            <i class="fas fa-list text-blue-500 text-sm"></i>
            <h2 class="text-sm font-bold text-gray-800">招待一覧</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/60 border-b border-gray-100">
                        <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500" style="width:34%;">メール</th>
                        <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500" style="width:14%;">ロール</th>
                        <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500" style="width:18%;">招待者</th>
                        <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500" style="width:18%;">有効期限</th>
                        <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500" style="width:8%;">状態</th>
                        <th class="text-right px-4 py-2.5 text-xs font-bold text-gray-500" style="width:8%;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invitations as $invitation)
                        <tr class="border-b border-gray-100 hover:bg-gray-50/60 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">{{ $invitation->email }}</div>
                                <div class="text-xs text-gray-400 mt-0.5">{{ $invitation->created_at?->format('Y/m/d H:i') }} 招待</div>
                            </td>
                            <td class="px-4 py-3">
                                @if($invitation->role === 'admin')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        <i class="fas fa-shield-alt"></i> 管理者
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-700 border border-gray-200">
                                        <i class="fas fa-user"></i> メンバー
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                @if($invitation->inviter)
                                    {{ $invitation->inviter->name }}
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs">
                                {{ $invitation->expires_at?->format('Y/m/d') }}
                                <span class="text-gray-400">({{ $invitation->expires_at?->diffForHumans() }})</span>
                            </td>
                            <td class="px-4 py-3">
                                @if($invitation->accepted_at)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200">
                                        <i class="fas fa-check"></i> 受諾済
                                    </span>
                                @elseif(!$invitation->isValid())
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                                        <i class="fas fa-times"></i> 期限切れ
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                        <i class="fas fa-clock"></i> 承認待ち
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form action="{{ route('admin.invitations.destroy', $invitation) }}" method="POST"
                                      onsubmit="return confirm('この招待を取り消します。よろしいですか？')"
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 hover:border-red-200 border border-transparent transition-all"
                                            title="招待を取り消す">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-gray-400 py-12 text-sm">
                                <i class="fas fa-inbox text-3xl text-gray-300 mb-2 block"></i>
                                招待はまだありません
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
