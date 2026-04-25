<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\EmailThread;
use App\Models\Email;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Customer::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'nullable|email|max:255|unique:customers',
            'domain' => 'nullable|string|max:255',
            'notes'  => 'nullable|string',
        ]);

        $customer = Customer::create($validated);

        // 代表メールが入力された場合、過去のメール（スレッド）を自動紐付け
        if ($customer->email) {
            $threadIds = Email::where('from_address', $customer->email)
                ->pluck('thread_id')
                ->unique();
            
            EmailThread::whereIn('id', $threadIds)
                ->whereNull('customer_id')
                ->update(['customer_id' => $customer->id]);
        }

        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'nullable|email|max:255|unique:customers,email,' . $customer->id,
            'domain' => 'nullable|string|max:255',
            'notes'  => 'nullable|string',
        ]);

        $customer->update($validated);
        return response()->json($customer);
    }

    public function assign(Request $request, EmailThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $thread->update(['customer_id' => $validated['customer_id']]);
        return response()->json(['status' => 'ok', 'customer' => $thread->customer]);
    }
    
    public function data(): JsonResponse
    {
        $data = [];
        
        // 顧客未設定のメールを最優先で取得
        $unassignedThreads = EmailThread::whereNull('customer_id')->with('emails')->get();
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

        // 顧客別のメール一覧データを生成
        $customers = Customer::with(['emailThreads.emails'])->orderBy('name')->get();
        
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
                'id' => $c->id,
                'name' => $c->name,
                'emails' => $emails,
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
