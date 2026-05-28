<?php

namespace App\Http\Controllers;

use App\Models\UserSignature;
use App\Models\UserTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAssetsController extends Controller
{
    // ===== Signatures =====
    public function indexSignatures(): JsonResponse
    {
        return response()->json([
            'signatures' => UserSignature::where('user_id', auth()->id())
                ->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn($s) => $this->presentSig($s))->values(),
        ]);
    }

    public function storeSignature(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:128',
            'body'       => 'required|string|max:5000',
            'is_default' => 'nullable|boolean',
        ]);
        $userId = auth()->id();
        $max = UserSignature::where('user_id', $userId)->max('sort_order') ?? 0;
        $sig = UserSignature::create([
            'user_id'   => $userId,
            'name'      => $data['name'],
            'body'      => $data['body'],
            'is_default'=> (bool) ($data['is_default'] ?? false),
            'sort_order'=> $max + 1,
        ]);
        $this->enforceSingleDefaultSig($sig);
        return response()->json(['status' => 'ok', 'signature' => $this->presentSig($sig->fresh())]);
    }

    public function updateSignature(Request $request, UserSignature $signature): JsonResponse
    {
        abort_if($signature->user_id !== auth()->id(), 403);
        $data = $request->validate([
            'name'       => 'required|string|max:128',
            'body'       => 'required|string|max:5000',
            'is_default' => 'nullable|boolean',
        ]);
        $signature->update([
            'name'      => $data['name'],
            'body'      => $data['body'],
            'is_default'=> (bool) ($data['is_default'] ?? $signature->is_default),
        ]);
        $this->enforceSingleDefaultSig($signature);
        return response()->json(['status' => 'ok', 'signature' => $this->presentSig($signature->fresh())]);
    }

    public function destroySignature(UserSignature $signature): JsonResponse
    {
        abort_if($signature->user_id !== auth()->id(), 403);
        $signature->delete();
        return response()->json(['status' => 'ok']);
    }

    private function enforceSingleDefaultSig(UserSignature $s): void
    {
        if ($s->is_default) {
            UserSignature::where('user_id', $s->user_id)->where('id', '!=', $s->id)
                ->where('is_default', true)->update(['is_default' => false]);
        }
    }

    private function presentSig(UserSignature $s): array
    {
        return [
            'id' => $s->id, 'name' => $s->name, 'body' => $s->body,
            'is_default' => (bool) $s->is_default,
            'sort_order' => $s->sort_order,
        ];
    }

    // ===== Templates =====
    public function indexTemplates(): JsonResponse
    {
        return response()->json([
            'templates' => UserTemplate::where('user_id', auth()->id())
                ->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn($t) => $this->presentTpl($t))->values(),
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:128',
            'subject' => 'nullable|string|max:500',
            'body'    => 'required|string|max:50000',
        ]);
        $userId = auth()->id();
        $max = UserTemplate::where('user_id', $userId)->max('sort_order') ?? 0;
        $t = UserTemplate::create([
            'user_id' => $userId,
            'name'    => $data['name'],
            'subject' => $data['subject'] ?? null,
            'body'    => $data['body'],
            'sort_order' => $max + 1,
        ]);
        return response()->json(['status' => 'ok', 'template' => $this->presentTpl($t)]);
    }

    public function updateTemplate(Request $request, UserTemplate $template): JsonResponse
    {
        abort_if($template->user_id !== auth()->id(), 403);
        $data = $request->validate([
            'name'    => 'required|string|max:128',
            'subject' => 'nullable|string|max:500',
            'body'    => 'required|string|max:50000',
        ]);
        $template->update($data);
        return response()->json(['status' => 'ok', 'template' => $this->presentTpl($template->fresh())]);
    }

    public function destroyTemplate(UserTemplate $template): JsonResponse
    {
        abort_if($template->user_id !== auth()->id(), 403);
        $template->delete();
        return response()->json(['status' => 'ok']);
    }

    private function presentTpl(UserTemplate $t): array
    {
        return [
            'id' => $t->id, 'name' => $t->name,
            'subject' => $t->subject, 'body' => $t->body,
            'sort_order' => $t->sort_order,
        ];
    }
}
