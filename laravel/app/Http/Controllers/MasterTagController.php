<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MasterTagController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // sort_orderが存在しない場合でも動くように、カラムの存在をチェックせずにorderByを試みる（エラーならname順にフォールバック）
            return response()->json(Tag::orderBy('sort_order')->orderBy('name')->get());
        } catch (\Exception $e) {
            Log::error('MasterTag index error: ' . $e->getMessage());
            return response()->json(Tag::orderBy('name')->get());
        }
    }

    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        foreach ($ids as $index => $id) {
            Tag::where('id', $id)->update(['sort_order' => $index]);
        }
        return response()->json(['status' => 'ok']);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
        ]);

        // 重複チェックを自前で行い、エラーを回避
        $tag = Tag::firstOrCreate(['name' => $validated['name']], ['color' => $validated['color'] ?? null]);
        return response()->json($tag);
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $tag->update($validated);
        return response()->json($tag);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $tagName = $tag->name;
        try {
            \App\Models\EmailThread::whereJsonContains('tags', $tagName)->get()->each(function($thread) use ($tagName) {
                $newTags = array_values(array_filter($thread->tags ?? [], fn($t) => $t !== $tagName));
                $thread->update(['tags' => $newTags]);
            });
            $tag->delete();
        } catch (\Exception $e) {
            Log::error('MasterTag destroy error: ' . $e->getMessage());
        }
        return response()->json(['status' => 'ok']);
    }
}
