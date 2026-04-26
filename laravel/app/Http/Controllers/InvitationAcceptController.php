<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class InvitationAcceptController extends Controller
{
    /**
     * Show the invitation acceptance form.
     */
    public function show(string $token): View|RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if (! $invitation->isValid()) {
            abort(403, 'この招待リンクは期限切れか、既に使用されています。');
        }

        return view('auth.invitation-accept', compact('invitation'));
    }

    /**
     * Handle the invitation acceptance.
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if (! $invitation->isValid()) {
            abort(403, 'この招待リンクは期限切れか、既に使用されています。');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $invitation->email,
            'password' => Hash::make($request->password),
            'role' => $invitation->role,
            'email_verified_at' => now(),
        ]);

        $invitation->update(['accepted_at' => now()]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('emails.index');
    }
}
