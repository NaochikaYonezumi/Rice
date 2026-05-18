<?php

namespace App\Http\Controllers;

use App\Models\EmailAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /**
     * 添付ファイルを local ディスクからダウンロードさせる。
     * EmailFetcher と PendingEmailController が disk_path を local 上に保存する設計。
     */
    public function download(EmailAttachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->disk_path), 404);

        return Storage::disk('local')->download(
            $attachment->disk_path,
            $attachment->filename
        );
    }

    public function index(Request $request)
    {
        if (!$request->expectsJson()) {
            return view('attachments.index');
        }

        $q          = trim($request->input('q', ''));
        $typeFilter = $request->input('type', ''); // '' | 'image' | 'document' | 'other'
        $customerId = $request->input('customer_id');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');
        $sort       = $request->input('sort', 'desc'); // 'asc' | 'desc'

        $query = EmailAttachment::with('email.thread')
            ->when($customerId === 'none', function($query) {
                // 代表ルームも pivot 所属もないスレッドの添付
                $query->whereHas('email.thread', fn($q) => $q->whereNull('customer_id')->whereDoesntHave('customers'));
            })
            ->when($customerId && $customerId !== 'none', function($query) use ($customerId) {
                // pivot 経由 (代表ルームも pivot に含まれる) で対象スレッドを抽出
                $query->whereHas('email.thread.customers', fn($q) => $q->where('customers.id', $customerId));
            })
            ->when($q, function($query) use ($q) {
                $query->where(function($sub) use ($q) {
                    $sub->where('filename', 'like', "%{$q}%")
                        ->orWhereHas('email', fn($e) => $e->where('subject', 'like', "%{$q}%"));
                });
            })
            ->when($dateFrom, fn($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->when($typeFilter === 'image',    fn($q) => $q->where('mime_type', 'like', 'image/%'))
            ->when($typeFilter === 'document', fn($q) => $q->where(function ($q) {
                $q->where('mime_type', 'like', 'application/pdf')
                  ->orWhere('mime_type', 'like', 'application/msword')
                  ->orWhere('mime_type', 'like', 'application/vnd.openxmlformats%')
                  ->orWhere('mime_type', 'like', 'application/vnd.ms-%')
                  ->orWhere('mime_type', 'like', 'text/%');
            }))
            ->when($typeFilter === 'other',    fn($q) => $q->where('mime_type', 'not like', 'image/%')
                ->where('mime_type', 'not like', 'application/pdf')
                ->where('mime_type', 'not like', 'application/msword')
                ->where('mime_type', 'not like', 'application/vnd.openxmlformats%')
                ->where('mime_type', 'not like', 'application/vnd.ms-%')
                ->where('mime_type', 'not like', 'text/%'))
            ->orderBy('created_at', $sort === 'asc' ? 'asc' : 'desc');

        $total       = $query->count();
        $attachments = $query->get()->map(fn($a) => [
            'id'            => $a->id,
            'filename'      => $a->filename,
            'mime_type'     => $a->mime_type,
            'size'          => $a->humanSize(),
            'size_bytes'    => $a->size,
            'is_image'      => $a->isImage(),
            'url'           => route('attachments.download', $a->id),
            'email_id'      => $a->email_id,
            'email_subject' => $a->email?->subject ?? '(削除済み)',
            'from_label'    => $a->email?->from_label ?? '—',
            'received_at'   => $a->email?->received_at?->format('Y/m/d H:i') ?? '—',
            'thread_id'     => $a->email?->thread_id,
        ]);

        return response()->json([
            'total'       => $total,
            'attachments' => $attachments,
        ]);
    }
}
