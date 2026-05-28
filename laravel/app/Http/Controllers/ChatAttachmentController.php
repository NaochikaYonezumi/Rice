<?php

namespace App\Http\Controllers;

use App\Models\ChatAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatAttachmentController extends Controller
{
    public const STORAGE_DISK = 'public';
    public const STORAGE_DIR  = 'chat-attachments';
    public const MAX_FILES    = 10;
    public const MAX_BYTES    = 10485760;   // 10MB / file

    /**
     * 投稿コメントにファイル群を保存する。
     * 呼び出し側: ThreadCommentController / ChatRoomController
     *
     * @param  \Illuminate\Http\UploadedFile[]  $files
     */
    public static function storeForComment(int $commentId, array $files): void
    {
        $disk = Storage::disk(self::STORAGE_DISK);
        $kept = 0;
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) continue;
            if ($kept >= self::MAX_FILES) break;
            if ($file->getSize() > self::MAX_BYTES) continue;

            $stored = $file->store(self::STORAGE_DIR . '/' . date('Y/m'), self::STORAGE_DISK);
            if (!$stored) continue;

            ChatAttachment::create([
                'comment_id'  => $commentId,
                'filename'    => $file->getClientOriginalName(),
                'stored_path' => $stored,
                'mime_type'   => $file->getMimeType(),
                'size_bytes'  => $file->getSize(),
            ]);
            $kept++;
        }
    }

    public function download(ChatAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk(self::STORAGE_DISK);
        if (!$disk->exists($attachment->stored_path)) {
            abort(404);
        }
        return $disk->download($attachment->stored_path, $attachment->filename);
    }

    /**
     * インラインで配信 (画像プレビュー用)。
     */
    public function inline(ChatAttachment $attachment)
    {
        $disk = Storage::disk(self::STORAGE_DISK);
        if (!$disk->exists($attachment->stored_path)) {
            abort(404);
        }
        return response()->file($disk->path($attachment->stored_path), [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
        ]);
    }
}
