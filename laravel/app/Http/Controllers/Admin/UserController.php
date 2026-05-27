<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        // 自分自身は削除できない (ログイン中ユーザーが消えてしまう)
        if ($user->id === $request->user()->id) {
            return back()->with('error', '自分自身は削除できません。');
        }

        $name = $user->name;
        $email = $user->email;
        $user->delete();

        return back()->with('success', "ユーザー「{$name}」({$email}) を削除しました。");
    }
}
