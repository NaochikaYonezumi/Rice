<?php

namespace App\Http\Controllers;

use App\Models\EmailAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function download(EmailAttachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->disk_path), 404, '添付ファイルが見つかりません');

        return Storage::disk('local')->download(
            $attachment->disk_path,
            $attachment->filename,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream']
        );
    }

    public function index(Request $request)
    {
        if (!$request->expectsJson()) {
            return view('attachments.index');
        }

        $q          = trim($request->input('q', ''));
        $typeFilter = $request->input('type', '');      // '' | 'image' | 'document' | 'other'
        $direction  = $request->input('direction', ''); // '' | 'received' | 'sent'
        $customerId = $request->input('customer_id');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');
        $sort       = $request->input('sort', 'desc'); // 'asc' | 'desc'

        $query = EmailAttachment::with('email.thread')
            ->when($customerId === 'none', function($query) {
                $query->whereHas('email.thread', fn($q) => $q->whereNull('customer_id'));
            })
            ->when($customerId && $customerId !== 'none', function($query) use ($customerId) {
                $query->whereHas('email.thread', fn($q) => $q->where('customer_id', $customerId));
            })
            ->when($q, function($query) use ($q) {
                $query->where(function($sub) use ($q) {
                    $sub->where('filename', 'like', "%{$q}%")
                        ->orWhereHas('email', fn($e) => $e->where('subject', 'like', "%{$q}%"));
                });
            })
            // 受信/送信フィルタ (Email::message_id が SENT_ で始まるものは送信)
            ->when($direction === 'sent', fn($query) =>
                $query->whereHas('email', fn($e) => $e->where('message_id', 'like', 'SENT\_%')))
            ->when($direction === 'received', fn($query) =>
                $query->whereHas('email', fn($e) => $e->where(function ($q) {
                    $q->whereNull('message_id')
                      ->orWhere('message_id', 'not like', 'SENT\_%');
                })))
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

        $perPage = max(10, min(100, (int) $request->input('per_page', 30)));
        $page    = max(1, (int) $request->input('page', 1));
        $total   = $query->count();
        $totalPages = (int) max(1, ceil($total / $perPage));
        // 範囲外の page はクランプ
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $attachments = $query->skip($offset)->take($perPage)->get()->map(function ($a) {
            $isSent = $a->email && is_string($a->email->message_id) && str_starts_with($a->email->message_id, 'SENT_');
            return [
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
                'to_address'    => $a->email?->to_address ?? '—',
                'received_at'   => $a->email?->received_at?->format('Y/m/d H:i') ?? '—',
                'thread_id'     => $a->email?->thread_id,
                'direction'     => $isSent ? 'sent' : 'received',
            ];
        });

        return response()->json([
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
            'attachments' => $attachments,
        ]);
    }
}
