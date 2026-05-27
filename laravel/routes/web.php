<?php

use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\AiTaskController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\MailAccountController;
use App\Http\Controllers\PendingEmailController;
use App\Http\Controllers\ScrapeController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StatusMasterController;
use App\Http\Controllers\ThreadMergeController;
use App\Http\Controllers\ThreadMemoController;
use App\Http\Controllers\ThreadChatController;
use App\Http\Controllers\ThreadCommentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes (Breeze + Socialite + Invitation)
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';

// SSO
Route::get('/auth/{provider}/redirect', [SocialiteController::class, 'redirect'])
    ->name('auth.redirect');
Route::get('/auth/{provider}/callback', [SocialiteController::class, 'callback'])
    ->name('auth.callback');

// 招待受諾
Route::get('/invitations/accept/{token}', [InvitationAcceptController::class, 'show'])
    ->name('invitations.accept');
Route::post('/invitations/accept/{token}', [InvitationAcceptController::class, 'store'])
    ->name('invitations.accept.store');

/*
|--------------------------------------------------------------------------
| Protected Application Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    // プロフィール
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // メール
    // 個人メールアカウント (IMAP/POP3/SMTP)
    Route::get('/mail-accounts', [MailAccountController::class, 'index'])->name('mail-accounts.index');
    Route::get('/mail-accounts/create', [MailAccountController::class, 'create'])->name('mail-accounts.create');
    Route::post('/mail-accounts', [MailAccountController::class, 'store'])->name('mail-accounts.store');
    Route::get('/mail-accounts/{mailAccount}/edit', [MailAccountController::class, 'edit'])->name('mail-accounts.edit');
    Route::put('/mail-accounts/{mailAccount}', [MailAccountController::class, 'update'])->name('mail-accounts.update');
    Route::delete('/mail-accounts/{mailAccount}', [MailAccountController::class, 'destroy'])->name('mail-accounts.destroy');
    Route::post('/mail-accounts/{mailAccount}/fetch', [MailAccountController::class, 'fetchNow'])->name('mail-accounts.fetch');

    Route::get('/', [EmailController::class, 'index'])->name('emails.index');
    Route::get('/emails/pinned', [EmailController::class, 'pinned'])->name('emails.pinned');
    Route::get('/emails/search', [EmailController::class, 'search'])->name('emails.search');
    // ステータスタブを件数連動 (= 0 件は非表示) で出すための軽量集計エンドポイント.
    Route::get('/emails/status-counts', [EmailController::class, 'statusCounts'])->name('emails.status_counts');

    // 作成専用ウィンドウ (返信・全員返信・新規)
    Route::get('/emails/compose-window', [EmailController::class, 'composeWindow'])->name('emails.composeWindow');
    Route::get('/emails/{email}/reply-window', [EmailController::class, 'replyWindow'])->name('emails.replyWindow');
    // 転送 (Forward) 用のコンポーズウィンドウ. mode='forward' + 引用本文 + 添付継承候補が渡る.
    Route::get('/emails/{email}/forward-window', [EmailController::class, 'forwardWindow'])->name('emails.forwardWindow');
    // 転送送信. POST /emails/{email}/forward. attachments + inherit_attachment_ids[] を受け取る.
    Route::post('/emails/{email}/forward', [EmailController::class, 'forward'])->name('emails.forward');

    Route::get('/threads/{thread}', [EmailController::class, 'thread'])->name('threads.show');
    Route::post('/threads/{thread}/assign-customer', [CustomerController::class, 'assign'])->name('threads.assign-customer');
    Route::post('/emails/bulk-assign-customer', [EmailController::class, 'bulkAssignCustomer'])->name('emails.bulk-assign-customer');
    Route::put('/threads/{thread}/status', [EmailController::class, 'updateStatus'])->name('threads.status');
    Route::post('/threads/{thread}/pin', [EmailController::class, 'togglePin'])->name('threads.pin');
    Route::put('/threads/{thread}/assignee', [EmailController::class, 'updateAssignee'])->name('threads.assignee');
    Route::get('/users', [EmailController::class, 'users'])->name('users.index');
    Route::delete('/threads/{thread}', [EmailController::class, 'deleteThread'])->name('threads.delete');
    // スレッド内の個別メール削除 (1 通だけ消す. スレッドの最後の 1 通を消したら親スレッドも一緒に消す.)
    Route::delete('/emails/{email}', [EmailController::class, 'destroyEmail'])->name('emails.destroy');
    // ゴミ箱 (Trash) 関連: 削除 → 復元 → 完全削除 のライフサイクルを操作する.
    //   GET    /trash              : ゴミ箱ビュー (?kind=thread|email)
    //   POST   /threads/{t}/restore: スレッドを復元 (status=inbox に戻す)
    //   POST   /emails/{e}/restore : 個別メールを復元 (親スレッドも復元)
    // ※ 即時完全削除は既存の DELETE /threads/{t}?hard=1 / DELETE /emails/{e}?hard=1 で兼用.
    Route::get('/trash',                       [EmailController::class, 'trashIndex'])->name('trash.index');
    Route::post('/threads/{thread}/restore',   [EmailController::class, 'restoreThread'])->name('threads.restore');
    Route::post('/emails/{email}/restore',     [EmailController::class, 'restoreEmail'])->name('emails.restore');
    // メールをスレッドから分離 (削除せず、新しい独立スレッドに付け替える).
    // 「本来このスレッドに混ざるべきでなかったメールを別件として独立させたい」時に使用.
    Route::post('/emails/{email}/detach', [EmailController::class, 'detachEmail'])->name('emails.detach');
    Route::post('/threads/{thread}/merge', [ThreadMergeController::class, 'merge'])->name('threads.merge');
    Route::delete('/thread-merges/{threadMerge}', [ThreadMergeController::class, 'unmerge'])->name('thread-merges.unmerge');

    Route::get('/threads/{thread}/memos', [ThreadMemoController::class, 'index'])->name('threads.memos.index');
    Route::post('/threads/{thread}/memos', [ThreadMemoController::class, 'store'])->name('threads.memos.store');

    Route::get('/threads/{thread}/comments', [ThreadCommentController::class, 'index'])->name('threads.comments.index');
    Route::post('/threads/{thread}/comments', [ThreadCommentController::class, 'store'])->name('threads.comments.store');
    Route::delete('/thread-comments/{comment}', [ThreadCommentController::class, 'destroy'])->name('threads.comments.destroy');

    // チャット一覧 (スレッド毎のチャット集約ページ)
    Route::get('/chats', [ThreadChatController::class, 'index'])->name('chats.index');
    Route::get('/chats/threads', [ThreadChatController::class, 'listThreads'])->name('chats.threads');

    Route::post('/emails/ai-compose', [EmailController::class, 'askAiCompose'])->name('emails.ai_compose');
    Route::post('/threads/{thread}/ai-summary', [EmailController::class, 'summarizeThread'])->name('threads.ai_summary');
    Route::post('/emails/{email}/ai', [EmailController::class, 'askAi'])->name('emails.ai');
    Route::get('/ai-tasks/recent', [AiTaskController::class, 'recent'])->name('ai_tasks.recent');
    Route::get('/ai-tasks/{task}', [AiTaskController::class, 'show'])->name('ai_tasks.show');
    Route::post('/emails/{email}/reply', [EmailController::class, 'reply'])->name('emails.reply');
    Route::post('/emails/compose', [EmailController::class, 'compose'])->name('emails.compose');
    Route::post('/emails/fetch', [EmailController::class, 'fetch'])->name('emails.fetch');
    Route::get('/emails/fetch-status', [EmailController::class, 'fetchStatus'])->name('emails.fetch_status');

    // 添付ファイル
    Route::get('/attachments', [AttachmentController::class, 'index'])->name('attachments.index');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::post('/attachments/upload', [AttachmentController::class, 'upload'])->name('attachments.upload');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // 迷惑メール (ブロックルール CRUD + 個別スレッド操作)
    Route::get('/api/mail-block-rules',                       [\App\Http\Controllers\MailBlockRuleController::class, 'index'])->name('mail_block_rules.index');
    Route::post('/api/mail-block-rules',                      [\App\Http\Controllers\MailBlockRuleController::class, 'store'])->name('mail_block_rules.store');
    Route::put('/api/mail-block-rules/{rule}',                [\App\Http\Controllers\MailBlockRuleController::class, 'update'])->name('mail_block_rules.update');
    Route::delete('/api/mail-block-rules/{rule}',             [\App\Http\Controllers\MailBlockRuleController::class, 'destroy'])->name('mail_block_rules.destroy');
    Route::post('/threads/{thread}/mark-spam',                [\App\Http\Controllers\MailBlockRuleController::class, 'markThreadAsSpam'])->name('threads.mark_spam');
    Route::post('/threads/{thread}/unmark-spam',              [\App\Http\Controllers\MailBlockRuleController::class, 'unmarkThreadAsSpam'])->name('threads.unmark_spam');
    // 迷惑メール設定画面
    Route::get('/settings/spam',                              [\App\Http\Controllers\MailBlockRuleController::class, 'page'])->name('settings.spam');

    // 承認待ちメール (API)
    Route::get('/pending-emails', [PendingEmailController::class, 'index'])->name('pending.index');
    Route::post('/pending-emails/{pending}/approve', [PendingEmailController::class, 'approve'])->name('pending.approve');
    Route::post('/pending-emails/{pending}/reject', [PendingEmailController::class, 'reject'])->name('pending.reject');
    Route::post('/pending-emails/{pending}/withdraw', [PendingEmailController::class, 'withdraw'])->name('pending.withdraw');
    // 予約送信: 下書きを「指定日時に送信」する状態にする / 取り消す.
    Route::post('/pending-emails/{pending}/schedule',   [PendingEmailController::class, 'schedule'])->name('pending.schedule');
    Route::post('/pending-emails/{pending}/unschedule', [PendingEmailController::class, 'unschedule'])->name('pending.unschedule');
    // 自己送信 (作成者本人による即時 / 予約送信. 承認フロー非経由).
    // ポリシーが SEND_POLICY_APPROVAL_REQUIRED の場合は 403.
    Route::post('/pending-emails/{pending}/self-send',  [PendingEmailController::class, 'selfSend'])->name('pending.self_send');
    // 送信ポリシーの取得 (UI が「自己送信ボタンを出していいか」を判定するため). 全ユーザ可読.
    Route::get('/api/send-policy', function () {
        $s = \App\Models\MailSetting::getSettings();
        return response()->json([
            'send_policy'         => $s->send_policy ?? 'flexible',
            'approval_required'   => $s->isApprovalRequired(),
        ]);
    })->name('send_policy.get');
    // 送信ポリシーの更新 (管理者のみ). middleware は既存の admin チェックに合わせて Controller 側で行う.
    Route::post('/api/send-policy', function (\Illuminate\Http\Request $request) {
        $u = auth()->user();
        if (!$u || !(method_exists($u, 'isAdmin') ? $u->isAdmin() : false)) {
            return response()->json(['status' => 'error', 'message' => '管理者のみ変更可能です'], 403);
        }
        $data = $request->validate([
            'send_policy' => ['required', 'in:flexible,approval_required'],
        ]);
        $s = \App\Models\MailSetting::getSettings();
        $s->update(['send_policy' => $data['send_policy']]);
        return response()->json(['status' => 'ok', 'send_policy' => $s->send_policy]);
    })->name('send_policy.update');
    // 却下済の依頼を手動で削除する (履歴から永久に消す).
    Route::delete('/pending-emails/{pending}', [PendingEmailController::class, 'destroy'])->name('pending.destroy');
    Route::get('/pending-emails/{pending}/attachments/{index}/download', [PendingEmailController::class, 'downloadAttachment'])
        ->whereNumber('index')->name('pending.attachment.download');

    // 通知
    Route::get('/notifications', function () {
        $notifications = auth()->user()->unreadNotifications()->latest()->take(20)->get();
        return response()->json($notifications);
    })->name('notifications.index');
    Route::post('/notifications/{id}/read', function (string $id) {
        auth()->user()->notifications()->where('id', $id)->update(['read_at' => now()]);
        return response()->json(['status' => 'ok']);
    })->name('notifications.read');
    Route::post('/notifications/read-all', function () {
        auth()->user()->unreadNotifications->markAsRead();
        return response()->json(['status' => 'ok']);
    })->name('notifications.read-all');

    // 承認ページ
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');

    // 下書き
    Route::get('/drafts', [DraftController::class, 'index'])->name('drafts.index');
    Route::get('/drafts/list', [DraftController::class, 'list'])->name('drafts.list');
    Route::get('/drafts/{draft}/edit', [DraftController::class, 'edit'])->name('drafts.edit');
    Route::post('/drafts/{draft}/submit', [DraftController::class, 'submit'])->name('drafts.submit');
    Route::delete('/drafts/{draft}', [DraftController::class, 'destroy'])->name('drafts.destroy');

    // 顧客・タグ
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    Route::get('/customers/data', [CustomerController::class, 'data'])->name('customers.data');
    Route::post('/customers/reorder', [CustomerController::class, 'reorder'])->name('customers.reorder');
    Route::post('/customers/{customer}/move', [CustomerController::class, 'moveToGroup'])->name('customers.move');

    Route::get('/customer-groups', [CustomerGroupController::class, 'index'])->name('customer-groups.index');
    Route::post('/customer-groups', [CustomerGroupController::class, 'store'])->name('customer-groups.store');
    Route::put('/customer-groups/{group}', [CustomerGroupController::class, 'update'])->name('customer-groups.update');
    Route::delete('/customer-groups/{group}', [CustomerGroupController::class, 'destroy'])->name('customer-groups.destroy');
    Route::post('/customer-groups/reorder', [CustomerGroupController::class, 'reorder'])->name('customer-groups.reorder');

    // AI 関連
    Route::get('/knowledge', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'index'])->name('knowledge.index');
    Route::post('/knowledge/crawl', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'crawl'])->name('knowledge.crawl');
    Route::post('/knowledge/upload', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'uploadFile'])->name('knowledge.upload');
    Route::get('/knowledge/from-email/{email}', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'previewEmail'])->name('knowledge.from_email.preview');
    Route::post('/knowledge/from-email/{email}', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'storeFromEmail'])->name('knowledge.from_email.store');
    Route::get('/knowledge/sources/statuses', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'statuses'])->name('knowledge.statuses');
    Route::get('/knowledge/sources/{source}', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'show'])->name('knowledge.show');
    Route::put('/knowledge/sources/{source}/content', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'updateContent'])->name('knowledge.update_content');
    Route::put('/knowledge/sources/{source}/collection', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'updateCollection'])->name('knowledge.update_collection');
    Route::post('/knowledge/sources/bulk-collection', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'bulkUpdateCollection'])->name('knowledge.bulk_collection');
    Route::post('/knowledge/sources/{source}/refresh', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'refresh'])->name('knowledge.refresh');
    Route::delete('/knowledge/sources/{source}', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'destroy'])->name('knowledge.destroy');
    // Phase 6-1: RAG コレクション一覧 (顧客編集 UI などから利用)
    Route::get('/api/knowledge/collections', [\Modules\Knowledge\Http\Controllers\KnowledgeController::class, 'collections'])->name('knowledge.collections');
    Route::get('/reports', [\Modules\Workflow\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
    Route::post('/threads/{thread}/ai-generate', [\Modules\AIReply\Http\Controllers\AIReplyController::class, 'generate'])->name('ai.generate');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/models', [ChatController::class, 'models'])->name('chat.models');
    Route::post('/query', [ChatController::class, 'query'])->name('chat.query');
    Route::get('/query/{id}/result', [ChatController::class, 'result'])->name('chat.result');

    Route::get('/scrape', [ScrapeController::class, 'index'])->name('scrape.index');
    Route::post('/scrape', [ScrapeController::class, 'store'])->name('scrape.store');
    Route::delete('/scrape/url/{scrapedUrl}', [ScrapeController::class, 'destroyUrl'])->name('scrape.url.destroy');
    Route::delete('/scrape/collection/{collection}', [ScrapeController::class, 'destroy'])->name('scrape.destroy');

    Route::get('/settings/ai/default-prompt', [SettingsController::class, 'getDefaultPrompt'])->name('settings.ai.default-prompt.get');
    Route::post('/settings/ai/default-prompt', [SettingsController::class, 'saveDefaultPrompt'])->name('settings.ai.default-prompt.save');

    // チャットピン / リアクション
    Route::post('/api/chats/pin',                    [\App\Http\Controllers\ChatExtrasController::class, 'togglePin'])->name('chats.pin');
    Route::get('/api/comments/{comment}/reactions',  [\App\Http\Controllers\ChatExtrasController::class, 'reactionsList'])->name('chats.reactions.list');
    Route::post('/api/comments/{comment}/reactions', [\App\Http\Controllers\ChatExtrasController::class, 'toggleReaction'])->name('chats.reactions.toggle');

    // チャット添付ファイル
    Route::get('/chat-attachments/{attachment}/download', [\App\Http\Controllers\ChatAttachmentController::class, 'download'])->name('chat_attachments.download');
    Route::get('/chat-attachments/{attachment}/inline',   [\App\Http\Controllers\ChatAttachmentController::class, 'inline'])->name('chat_attachments.inline');

    // スタンドアロンチャットルーム
    Route::get('/api/chat-rooms',                    [\App\Http\Controllers\ChatRoomController::class, 'index'])->name('chat_rooms.index');
    Route::post('/api/chat-rooms',                   [\App\Http\Controllers\ChatRoomController::class, 'store'])->name('chat_rooms.store');
    Route::put('/api/chat-rooms/{room}',             [\App\Http\Controllers\ChatRoomController::class, 'update'])->name('chat_rooms.update');
    Route::delete('/api/chat-rooms/{room}',          [\App\Http\Controllers\ChatRoomController::class, 'destroy'])->name('chat_rooms.destroy');
    Route::get('/api/chat-rooms/{room}/messages',    [\App\Http\Controllers\ChatRoomController::class, 'messages'])->name('chat_rooms.messages');
    Route::post('/api/chat-rooms/{room}/messages',   [\App\Http\Controllers\ChatRoomController::class, 'postMessage'])->name('chat_rooms.post_message');
    // ルームにメールスレッドをまとめる (バンドル)
    Route::get('/api/chat-rooms/{room}/threads',     [\App\Http\Controllers\ChatRoomController::class, 'bundledThreads'])->name('chat_rooms.threads.index');
    Route::get('/api/chat-rooms/{room}/emails',      [\App\Http\Controllers\ChatRoomController::class, 'bundledEmails'])->name('chat_rooms.emails.index');
    Route::get('/api/chat-rooms/{room}/report',      [\App\Http\Controllers\ChatRoomController::class, 'getReport'])->name('chat_rooms.report.show');
    Route::put('/api/chat-rooms/{room}/report',      [\App\Http\Controllers\ChatRoomController::class, 'updateReport'])->name('chat_rooms.report.update');
    // 全画面 Wiki ページ
    Route::get('/wiki', [\App\Http\Controllers\WikiController::class, 'index'])->name('wiki.index');

    Route::get('/api/chat-rooms/{room}/wiki',        [\App\Http\Controllers\ChatRoomController::class, 'getWiki'])->name('chat_rooms.wiki.show');
    Route::put('/api/chat-rooms/{room}/wiki',        [\App\Http\Controllers\ChatRoomController::class, 'updateWiki'])->name('chat_rooms.wiki.update');
    // 複数カード対応の Wiki CRUD
    Route::get('/api/chat-rooms/{room}/wikis',                [\App\Http\Controllers\ChatRoomController::class, 'listWikis'])->name('chat_rooms.wikis.index');
    Route::post('/api/chat-rooms/{room}/wikis',               [\App\Http\Controllers\ChatRoomController::class, 'storeWiki'])->name('chat_rooms.wikis.store');
    Route::put('/api/chat-rooms/{room}/wikis/{wiki}',         [\App\Http\Controllers\ChatRoomController::class, 'updateWikiCard'])->name('chat_rooms.wikis.update');
    Route::delete('/api/chat-rooms/{room}/wikis/{wiki}',      [\App\Http\Controllers\ChatRoomController::class, 'destroyWiki'])->name('chat_rooms.wikis.destroy');
    Route::post('/api/chat-rooms/{room}/threads',    [\App\Http\Controllers\ChatRoomController::class, 'attachThread'])->name('chat_rooms.threads.attach');
    Route::delete('/api/chat-rooms/{room}/threads/{thread}', [\App\Http\Controllers\ChatRoomController::class, 'detachThread'])->name('chat_rooms.threads.detach');
    // ルームの親子関係 (フォルダ構成) — parent_room_id を変更する.
    Route::post('/api/chat-rooms/{room}/move',       [\App\Http\Controllers\ChatRoomController::class, 'moveRoom'])->name('chat_rooms.move');
    // ルームのマージ — source の中身を target に統合して source を削除する.
    Route::post('/api/chat-rooms/{room}/merge',      [\App\Http\Controllers\ChatRoomController::class, 'mergeRoom'])->name('chat_rooms.merge');
    Route::get('/api/chat-rooms/_/pickable-threads', [\App\Http\Controllers\ChatRoomController::class, 'pickableThreads'])->name('chat_rooms.pickable_threads');

    // ルーム管理ページ (メインメニュー「ルーム」)
    Route::get('/rooms', [\App\Http\Controllers\ChatRoomController::class, 'page'])->name('rooms.index');

    // ルームごとの振り分けルール (パターン/フィルタ) CRUD
    Route::get('/api/chat-rooms/{room}/routing-rules',                   [\App\Http\Controllers\ChatRoomController::class, 'listRoutingRules'])->name('chat_rooms.routing_rules.index');
    Route::post('/api/chat-rooms/{room}/routing-rules',                  [\App\Http\Controllers\ChatRoomController::class, 'storeRoutingRule'])->name('chat_rooms.routing_rules.store');
    Route::put('/api/chat-rooms/{room}/routing-rules/{rule}',            [\App\Http\Controllers\ChatRoomController::class, 'updateRoutingRule'])->name('chat_rooms.routing_rules.update');
    Route::delete('/api/chat-rooms/{room}/routing-rules/{rule}',         [\App\Http\Controllers\ChatRoomController::class, 'destroyRoutingRule'])->name('chat_rooms.routing_rules.destroy');
    Route::post('/api/chat-rooms/{room}/routing-rules/_/reapply',        [\App\Http\Controllers\ChatRoomController::class, 'reapplyRoutingRules'])->name('chat_rooms.routing_rules.reapply');
    Route::post('/api/chat-rooms/_/reapply-all-rules',                   [\App\Http\Controllers\ChatRoomController::class, 'reapplyRoutingRulesAll'])->name('chat_rooms.routing_rules.reapply_all');

    // 全体表示 (全ルーム + 全スレッドのコメントを時系列マージ・読み取り専用)
    Route::get('/api/chats/all-messages',            [ThreadChatController::class, 'allMessages'])->name('chats.all_messages');
    // サイドバーの非表示/表示切り替え (per-user)
    Route::post('/api/chats/hide',                   [ThreadChatController::class, 'hide'])->name('chats.hide');
    Route::post('/api/chats/unhide',                 [ThreadChatController::class, 'unhide'])->name('chats.unhide');

    Route::get('/chats/rooms',                       function () { return view('chats.rooms'); })->name('chats.rooms');

    // 個人用シグネチャ
    Route::get('/api/user/signatures',                [\App\Http\Controllers\UserAssetsController::class, 'indexSignatures'])->name('user.signatures.index');
    Route::post('/api/user/signatures',               [\App\Http\Controllers\UserAssetsController::class, 'storeSignature'])->name('user.signatures.store');
    Route::put('/api/user/signatures/{signature}',    [\App\Http\Controllers\UserAssetsController::class, 'updateSignature'])->name('user.signatures.update');
    Route::delete('/api/user/signatures/{signature}', [\App\Http\Controllers\UserAssetsController::class, 'destroySignature'])->name('user.signatures.destroy');

    // 個人用テンプレート
    Route::get('/api/user/templates',                 [\App\Http\Controllers\UserAssetsController::class, 'indexTemplates'])->name('user.templates.index');
    Route::post('/api/user/templates',                [\App\Http\Controllers\UserAssetsController::class, 'storeTemplate'])->name('user.templates.store');
    Route::put('/api/user/templates/{template}',      [\App\Http\Controllers\UserAssetsController::class, 'updateTemplate'])->name('user.templates.update');
    Route::delete('/api/user/templates/{template}',   [\App\Http\Controllers\UserAssetsController::class, 'destroyTemplate'])->name('user.templates.destroy');

    // 個人用 AI スキル (ユーザー毎)
    Route::get('/settings/ai-skills',                [\App\Http\Controllers\UserAiSkillController::class, 'index'])->name('settings.ai_skills.index');
    Route::post('/settings/ai-skills',               [\App\Http\Controllers\UserAiSkillController::class, 'store'])->name('settings.ai_skills.store');
    Route::put('/settings/ai-skills/{skill}',        [\App\Http\Controllers\UserAiSkillController::class, 'update'])->name('settings.ai_skills.update');
    Route::delete('/settings/ai-skills/{skill}',     [\App\Http\Controllers\UserAiSkillController::class, 'destroy'])->name('settings.ai_skills.destroy');
    Route::post('/settings/ai-skills/reset',         [\App\Http\Controllers\UserAiSkillController::class, 'reset'])->name('settings.ai_skills.reset');

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->group(function () {
        Route::get('/settings/mail', [SettingsController::class, 'mail'])->name('settings.mail');
        Route::post('/settings/mail', [SettingsController::class, 'updateMail'])->name('settings.mail.update');
        // 接続テスト (保存前の検証用)
        Route::post('/settings/mail/test', [SettingsController::class, 'testMailConnection'])->name('settings.mail.test');
        Route::get('/settings/ai', [SettingsController::class, 'ai'])->name('settings.ai');
        Route::post('/settings/ai', [SettingsController::class, 'updateAi'])->name('settings.ai.update');
        Route::get('/settings/sso', [SettingsController::class, 'sso'])->name('settings.sso');
        Route::post('/settings/sso', [SettingsController::class, 'updateSso'])->name('settings.sso.update');

        Route::get('/admin/invitations', [InvitationController::class, 'index'])->name('admin.invitations.index');
        Route::post('/admin/invitations', [InvitationController::class, 'store'])->name('admin.invitations.store');
        Route::delete('/admin/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('admin.invitations.destroy');
        Route::delete('/admin/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('admin.users.destroy');

        Route::get('/master/statuses', [StatusMasterController::class, 'index'])->name('master.statuses');
        Route::post('/master/statuses', [StatusMasterController::class, 'store'])->name('master.statuses.store');
    });
});
