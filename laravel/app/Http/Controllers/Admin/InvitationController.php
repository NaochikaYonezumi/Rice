<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvitationController extends Controller
{
    /**
     * Display a listing of invitations.
     */
    public function index(): View
    {
        $invitations = Invitation::with('inviter')->orderByDesc('created_at')->get();
        return view('admin.invitations.index', compact('invitations'));
    }

    /**
     * Store a newly created invitation.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email|unique:invitations,email',
            'role' => 'required|in:admin,member',
        ]);

        $invitation = Invitation::create([
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'invited_by' => auth()->id(),
        ]);

        try {
            Mail::to($invitation->email)->send(new InvitationMail($invitation));
        } catch (\Exception $e) {
            return back()->with('error', '招待メールの送信に失敗しました。');
        }

        return back()->with('success', '招待メールを送信しました。');
    }

    /**
     * Remove the specified invitation.
     */
    public function destroy(Invitation $invitation): RedirectResponse
    {
        $invitation->delete();
        return back()->with('success', '招待を取り消しました。');
    }
}
