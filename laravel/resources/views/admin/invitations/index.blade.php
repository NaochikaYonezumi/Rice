@extends('layouts.app')
@section('title', '招待管理')

@section('content')
<div class="flex flex-col h-full bg-gray-50 overflow-hidden">
    {{-- ヘッダー --}}
    <div class="shrink-0 px-10 py-8 bg-white border-b border-gray-200">
        <h1 class="text-3xl font-black text-gray-900 tracking-tighter uppercase mb-2">招待管理</h1>
        <p class="text-sm text-gray-400 font-bold uppercase tracking-widest">Manage User Invitations</p>
    </div>

    <div class="flex-1 overflow-y-auto p-10 space-y-10 custom-scrollbar">
        {{-- 新規招待フォーム --}}
        <div class="max-w-4xl bg-white rounded-3xl shadow-xl p-8 border border-gray-100">
            <h3 class="text-lg font-black text-gray-800 mb-6 uppercase tracking-tighter">新しいユーザーを招待</h3>
            
            <form action="{{ route('admin.invitations.store') }}" method="POST" class="flex flex-wrap items-end gap-6">
                @csrf
                <div class="flex-1 min-w-[300px] space-y-1">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Email Address</label>
                    <input type="email" name="email" required placeholder="user@example.com"
                        class="w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner">
                    @error('email') <p class="text-red-600 text-xs mt-1 font-bold">{{ $message }}</p> @enderror
                </div>

                <div class="w-48 space-y-1">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Role</label>
                    <select name="role" class="w-full bg-gray-50 border-0 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 transition-all text-gray-900 font-bold outline-none shadow-inner">
                        <option value="member">Member</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>

                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-black px-10 py-4 rounded-2xl shadow-xl shadow-blue-100 transition-all active:scale-[0.98]">
                    招待を送信
                </button>
            </form>
        </div>

        {{-- 招待一覧 --}}
        <div class="max-w-4xl bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100">
            <div class="px-8 py-6 bg-gray-50/50 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-black text-gray-500 uppercase tracking-widest">Active Invitations</h3>
                <span class="text-[10px] font-black bg-gray-200 text-gray-600 px-3 py-1 rounded-full">{{ count($invitations) }} TOTAL</span>
            </div>

            <table class="w-full">
                <thead>
                    <tr class="text-left border-b border-gray-50">
                        <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Email</th>
                        <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Role</th>
                        <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Expires At</th>
                        <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($invitations as $invitation)
                        <tr class="group hover:bg-gray-50 transition-colors">
                            <td class="px-8 py-5">
                                <div class="font-bold text-gray-900">{{ $invitation->email }}</div>
                                @if($invitation->accepted_at)
                                    <span class="text-[9px] font-black text-green-500 uppercase">Accepted at {{ $invitation->accepted_at->format('Y/m/d') }}</span>
                                @elseif(!$invitation->isValid())
                                    <span class="text-[9px] font-black text-red-500 uppercase">Expired</span>
                                @else
                                    <span class="text-[9px] font-black text-blue-500 uppercase tracking-tighter">Waiting...</span>
                                @endif
                            </td>
                            <td class="px-8 py-5">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase {{ $invitation->role === 'admin' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $invitation->role }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-sm text-gray-500 font-bold">
                                {{ $invitation->expires_at->diffForHumans() }}
                            </td>
                            <td class="px-8 py-5 text-right">
                                <form action="{{ route('admin.invitations.destroy', $invitation) }}" method="POST" onsubmit="return confirm('本当にこの招待を取り消しますか？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:text-red-600 transition-colors p-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-8 py-20 text-center text-gray-400 font-bold uppercase tracking-widest">No active invitations found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
