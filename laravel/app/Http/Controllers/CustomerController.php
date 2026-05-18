<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\EmailThread;
use App\Models\Email;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Customer::visibleTo($request->user())->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'nullable|email|max:255|unique:customers',
            'is_personal' => 'sometimes|boolean',
        ]);

        $validated['is_personal']    = $validated['is_personal'] ?? false;
        $validated['owner_user_id']  = $validated['is_personal'] ? $request->user()?->id : null;

        $customer = Customer::create($validated);

        // 代表メールが入力された場合、過去のメール（スレッド）を自動紐付け
        if ($customer->email) {
            $threadIds = Email::where('from_address', $customer->email)
                ->pluck('thread_id')
                ->unique();

            EmailThread::whereIn('id', $threadIds)
                ->whereNull('customer_id')
                ->update(['customer_id' => $customer->id]);

            // pivot にも同期 (代表 + 兼属 両方を反映)
            $customer->emailThreads()->syncWithoutDetaching($threadIds->all());
        }

        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'nullable|email|max:255|unique:customers,email,' . $customer->id,
        ]);

        $customer->update($validated);
        return response()->json($customer);
    }

    /**
     * 代表ルームを設定する。customer_id を更新し、pivot にも追加する (既存の兼属は維持)。
     */
    public function assign(Request $request, EmailThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $thread->update(['customer_id' => $validated['customer_id']]);

        if ($validated['customer_id']) {
            $thread->customers()->syncWithoutDetaching([$validated['customer_id']]);
        }

        return response()->json([
            'status'    => 'ok',
            'customer'  => $thread->fresh()->customer,
            'customers' => $thread->customers()->orderBy('name')->get(['customers.id', 'customers.name']),
        ]);
    }

    /**
     * スレッドに兼属ルームを追加する (代表ルーム customer_id は維持)。
     */
    public function attachThread(Request $request, EmailThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        $thread->customers()->syncWithoutDetaching([$validated['customer_id']]);

        // 代表ルーム未設定の場合は今回の追加を代表にする
        if (! $thread->customer_id) {
            $thread->update(['customer_id' => $validated['customer_id']]);
        }

        return response()->json([
            'status'    => 'ok',
            'customers' => $thread->customers()->orderBy('name')->get(['customers.id', 'customers.name']),
        ]);
    }

    /**
     * スレッドから兼属ルームを外す。代表ルームを外した場合は、残った兼属の中から代表を選び直す。
     */
    public function detachThread(Request $request, EmailThread $thread, Customer $customer): JsonResponse
    {
        $thread->customers()->detach($customer->id);

        if ((int) $thread->customer_id === $customer->id) {
            $next = $thread->customers()->orderBy('customer_email_thread.id')->first();
            $thread->update(['customer_id' => $next?->id]);
        }

        return response()->json([
            'status'    => 'ok',
            'customers' => $thread->customers()->orderBy('name')->get(['customers.id', 'customers.name']),
        ]);
    }
    
    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = [];

        // 顧客未設定のメールを最優先で取得 (代表ルームも pivot 兼属もない)
        $unassignedThreads = EmailThread::whereNull('customer_id')
            ->whereDoesntHave('customers')
            ->with('emails')
            ->get();
        $unassignedEmails = [];
        foreach ($unassignedThreads as $t) {
            foreach ($t->emails as $e) {
                $unassignedEmails[] = [
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
        $data[] = [
            'id' => 'none', // フロントエンド識別のために 'none' に変更
            'name' => '未設定',
            'emails' => $unassignedEmails,
        ];

        // 顧客別のメール一覧データを生成 (ログインユーザに見えるルームだけ)
        $customers = Customer::visibleTo($user)
            ->with(['emailThreads.emails'])
            ->orderBy('name')
            ->get();
        
        foreach ($customers as $c) {
            $emails = [];
            foreach ($c->emailThreads as $t) {
                foreach ($t->emails as $e) {
                    $emails[] = [
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
            $data[] = [
                'id'          => $c->id,
                'name'        => $c->name,
                'is_personal' => $c->is_personal,
                'emails'      => $emails,
            ];
        }

        return response()->json($data);
    }

    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        foreach ($ids as $index => $id) {
            Customer::where('id', $id)->update(['sort_order' => $index]);
        }
        return response()->json(['status' => 'ok']);
    }

    public function moveToGroup(Request $request, Customer $customer): JsonResponse
    {
        $groupId = $request->input('group_id');
        $customer->update(['group_id' => $groupId === 'none' ? null : $groupId]);
        return response()->json($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();
        return response()->json(['status' => 'ok']);
    }
}
