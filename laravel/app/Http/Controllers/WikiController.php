<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use Illuminate\Http\Request;

/**
 * Wiki ページ (画面全体で複数カードを表示する版)。
 * カード CRUD のロジック自体は既存の ChatRoomController::*Wiki* メソッドを再利用するので、
 * ここでは初期ビューと選択中ルーム情報を出すだけ。
 */
class WikiController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()?->id;
        // 可視ルーム一覧 (個人ルームは自分のみ)
        // 編集 UI で「自分が作成者か」を判定するため created_by_user_id も渡す。
        $rooms = ChatRoom::visibleTo($userId)
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'is_private', 'wiki_updated_at', 'created_by_user_id']);

        return view('wiki.index', [
            'rooms' => $rooms->map(fn($r) => [
                'id'                 => $r->id,
                'name'               => $r->name,
                'is_private'         => (bool) $r->is_private,
                'created_by_user_id' => $r->created_by_user_id,
                'wiki_updated_at'    => $r->wiki_updated_at?->format('Y/m/d H:i'),
            ])->values(),
            // 編集/削除の権限判定 (個人ルームは作成者のみ操作可) で使う。
            'myId' => $userId,
        ]);
    }
}
