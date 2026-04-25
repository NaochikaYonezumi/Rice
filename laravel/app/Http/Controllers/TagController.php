<?php

namespace App\Http\Controllers;

use App\Models\EmailThread;
use App\Models\Email;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagController extends Controller
{
    public function index(): View
    {
        return view('tags.index');
    }

    public function data(): array
    {
        $threads = EmailThread::with('emails')->get();
        $tagMap = [];
        foreach ($threads as $t) {
            $tags = $t->tags ?? [];
            foreach ($tags as $tag) {
                if (!isset($tagMap[$tag])) {
                    $tagMap[$tag] = [];
                }
                foreach ($t->emails as $e) {
                    $tagMap[$tag][] = [
                        'id' => $e->id,
                        'thread_id' => $t->id,
                        'subject' => $t->subject,
                        'from_label' => $e->from_label,
                        'received_at' => $e->received_at->format('Y-m-d H:i'),
                        'plain_body' => \Illuminate\Support\Str::limit($e->plain_body, 100),
                        'is_read' => $e->is_read,
                    ];
                }
            }
        }
        return $tagMap;
    }
}
