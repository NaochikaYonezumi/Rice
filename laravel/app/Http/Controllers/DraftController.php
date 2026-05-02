<?php

namespace App\Http\Controllers;

use App\Models\PendingEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftController extends Controller
{
    public function index()
    {
        return view('drafts.index');
    }

    public function list(): JsonResponse
    {
        $drafts = PendingEmail::where('status', PendingEmail::STATUS_DRAFT)
            ->where('created_by_user_id', auth()->id())
            ->latest()
            ->get()
            ->map(fn($d) => [
                'id'          => $d->id,
                'subject'     => $d->subject,
                'to_address'  => $d->to_address,
                'body_preview'=> $d->body_preview,
                'reply_type'  => $d->reply_type,
                'reply_type_label' => $d->reply_type_label,
                'created_at'  => $d->created_at?->format('Y/m/d H:i'),
            ]);

        return response()->json($drafts);
    }

    public function submit(Request $request, PendingEmail $draft): JsonResponse
    {
        abort_unless($draft->status === PendingEmail::STATUS_DRAFT, 422, 'Not a draft');

        $draft->update(['status' => PendingEmail::STATUS_PENDING]);

        $admins = \App\Models\User::where('role', 'admin')
            ->where('id', '!=', auth()->id())
            ->get();
        \Illuminate\Support\Facades\Notification::send(
            $admins,
            new \App\Notifications\ApprovalRequestedNotification($draft)
        );

        return response()->json(['status' => 'ok']);
    }

    public function destroy(PendingEmail $draft): JsonResponse
    {
        abort_unless($draft->status === PendingEmail::STATUS_DRAFT, 422, 'Not a draft');
        $draft->delete();
        return response()->json(['status' => 'ok']);
    }
}
