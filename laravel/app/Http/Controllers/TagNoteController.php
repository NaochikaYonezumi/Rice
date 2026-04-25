<?php

namespace App\Http\Controllers;

use App\Models\TagNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagNoteController extends Controller
{
    public function show(string $tag): JsonResponse
    {
        $note = TagNote::firstOrNew(['tag' => $tag]);

        return response()->json([
            'tag'     => $tag,
            'content' => $note->content ?? [],
        ]);
    }

    public function update(Request $request, string $tag): JsonResponse
    {
        $validated = $request->validate([
            'content'         => 'required|array',
            'content.*.title' => 'required|string|max:200',
            'content.*.body'  => 'nullable|string|max:200000',
        ]);

        TagNote::updateOrCreate(['tag' => $tag], ['content' => $validated['content']]);

        return response()->json(['status' => 'ok']);
    }
}
