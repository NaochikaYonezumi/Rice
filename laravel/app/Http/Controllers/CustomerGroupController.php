<?php

namespace App\Http\Controllers;

use App\Models\CustomerGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerGroupController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // ルートフォルダ（parent_idがnullのもの）を取得し、子フォルダを再帰的にロード
            $groups = CustomerGroup::whereNull('parent_id')
                ->with(['customers', 'children.customers', 'children.children.customers']) // 3階層までロード
                ->orderBy('sort_order')
                ->get();
            return response()->json($groups);
        } catch (\Exception $e) {
            Log::error('CustomerGroup index error: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'parent_id' => 'nullable|exists:customer_groups,id',
        ]);

        $group = CustomerGroup::create($validated);
        return response()->json($group);
    }

    public function update(Request $request, CustomerGroup $group): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'parent_id' => 'nullable|exists:customer_groups,id',
        ]);

        $group->update($validated);
        return response()->json($group);
    }

    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        $parentId = $request->input('parent_id'); // 移動先の親フォルダID

        foreach ($ids as $index => $id) {
            CustomerGroup::where('id', $id)->update([
                'sort_order' => $index,
                'parent_id' => $parentId === 'root' ? null : $parentId
            ]);
        }
        return response()->json(['status' => 'ok']);
    }

    public function destroy(CustomerGroup $group): JsonResponse
    {
        // 紐づいている顧客を未分類に戻す
        $group->customers()->update(['group_id' => null]);
        // 子フォルダがある場合、それらもルートに移動させるなどの処理が必要だが、
        // ここではシンプルに削除（cascadeで子も消える設定）
        $group->delete();
        return response()->json(['status' => 'ok']);
    }
}
