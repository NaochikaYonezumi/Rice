<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\PendingEmailController;
use App\Http\Controllers\ScrapeController;
use App\Models\ScrapedUrl;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\MasterTagController;
use App\Http\Controllers\StatusMasterController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TagNoteController;
use App\Http\Controllers\ThreadMergeController;
use App\Http\Controllers\ThreadMemoController;
use App\Http\Controllers\ThreadCommentController;
use Illuminate\Support\Facades\Route;

// メール
Route::get('/', [EmailController::class, 'index'])->name('emails.index');
Route::get('/emails/search', [EmailController::class, 'search'])->name('emails.search');
Route::get('/emails/{email}', [EmailController::class, 'show'])->name('emails.show');
Route::get('/threads/{thread}', [EmailController::class, 'thread'])->name('threads.show');
Route::post('/threads/{thread}/assign-customer', [CustomerController::class, 'assign'])->name('threads.assign-customer');
Route::post('/emails/bulk-assign-customer', [EmailController::class, 'bulkAssignCustomer'])->name('emails.bulk-assign-customer');
Route::put('/threads/{thread}/tags', [EmailController::class, 'updateTags'])->name('threads.tags');
Route::put('/threads/{thread}/status', [EmailController::class, 'updateStatus'])->name('threads.status');
Route::delete('/threads/{thread}', [EmailController::class, 'deleteThread'])->name('threads.delete');
Route::post('/threads/{thread}/merge', [ThreadMergeController::class, 'merge'])->name('threads.merge');
Route::delete('/thread-merges/{threadMerge}', [ThreadMergeController::class, 'unmerge'])->name('thread-merges.unmerge');

Route::get('/threads/{thread}/memos', [ThreadMemoController::class, 'index'])->name('threads.memos.index');
Route::post('/threads/{thread}/memos', [ThreadMemoController::class, 'store'])->name('threads.memos.store');

Route::get('/threads/{thread}/comments', [ThreadCommentController::class, 'index'])->name('threads.comments.index');
Route::post('/threads/{thread}/comments', [ThreadCommentController::class, 'store'])->name('threads.comments.store');
Route::delete('/thread-comments/{comment}', [ThreadCommentController::class, 'destroy'])->name('threads.comments.destroy');

Route::post('/emails/{email}/ai', [EmailController::class, 'askAi'])->name('emails.ai');
Route::post('/emails/{email}/reply', [EmailController::class, 'reply'])->name('emails.reply');
Route::post('/emails/compose', [EmailController::class, 'compose'])->name('emails.compose');
Route::post('/emails/fetch', [EmailController::class, 'fetch'])->name('emails.fetch');

// 添付ファイル一覧（/attachments/{id} より先に定義）
Route::get('/attachments', [AttachmentController::class, 'index'])->name('attachments.index');
// 添付ファイルダウンロード
Route::get('/attachments/{attachment}', [EmailController::class, 'downloadAttachment'])->name('attachments.download');

// 承認待ちメール (API)
Route::get('/pending-emails', [PendingEmailController::class, 'index'])->name('pending.index');
Route::post('/pending-emails/{pending}/approve', [PendingEmailController::class, 'approve'])->name('pending.approve');
Route::post('/pending-emails/{pending}/reject', [PendingEmailController::class, 'reject'])->name('pending.reject');

// 承認ページ
Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');

// 顧客
Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
Route::get('/customers/data', [CustomerController::class, 'data'])->name('customers.data');
Route::post('/customers/reorder', [CustomerController::class, 'reorder'])->name('customers.reorder');
Route::post('/customers/{customer}/move', [CustomerController::class, 'moveToGroup'])->name('customers.move');

// 顧客グループ (フォルダ)
Route::get('/customer-groups', [CustomerGroupController::class, 'index'])->name('customer-groups.index');
Route::post('/customer-groups', [CustomerGroupController::class, 'store'])->name('customer-groups.store');
Route::put('/customer-groups/{group}', [CustomerGroupController::class, 'update'])->name('customer-groups.update');
Route::delete('/customer-groups/{group}', [CustomerGroupController::class, 'destroy'])->name('customer-groups.destroy');
Route::post('/customer-groups/reorder', [CustomerGroupController::class, 'reorder'])->name('customer-groups.reorder');

// マスター
Route::get('/master/statuses', [StatusMasterController::class, 'index'])->name('master.statuses');
Route::post('/master/statuses', [StatusMasterController::class, 'store'])->name('master.statuses.store');
Route::get('/master/tags', [MasterTagController::class, 'index'])->name('master.tags');
Route::post('/master/tags', [MasterTagController::class, 'store'])->name('master.tags.store');
Route::put('/master/tags/{tag}', [MasterTagController::class, 'update'])->name('master.tags.update');
Route::delete('/master/tags/{tag}', [MasterTagController::class, 'destroy'])->name('master.tags.destroy');
Route::post('/master/tags/reorder', [MasterTagController::class, 'reorder'])->name('master.tags.reorder');

// タグリスト
Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
Route::get('/tags/data', [TagController::class, 'data'])->name('tags.data');

// タグノート
Route::get('/tag-notes/{tag}', [TagNoteController::class, 'show'])->name('tag-notes.show');
Route::put('/tag-notes/{tag}', [TagNoteController::class, 'update'])->name('tag-notes.update');

// ドキュメントアップロード
Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

// RAGチャット
Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
Route::get('/chat/models', [ChatController::class, 'models'])->name('chat.models');
Route::post('/query', [ChatController::class, 'query'])->name('chat.query');
Route::get('/query/{id}/result', [ChatController::class, 'result'])->name('chat.result');

// スクレイピング
Route::get('/scrape', [ScrapeController::class, 'index'])->name('scrape.index');
Route::post('/scrape', [ScrapeController::class, 'store'])->name('scrape.store');
Route::delete('/scrape/url/{scrapedUrl}', [ScrapeController::class, 'destroyUrl'])->name('scrape.url.destroy');
Route::delete('/scrape/collection/{collection}', [ScrapeController::class, 'destroy'])->name('scrape.destroy');

// 設定
Route::get('/settings/mail', [SettingsController::class, 'mail'])->name('settings.mail');
Route::post('/settings/mail', [SettingsController::class, 'updateMail'])->name('settings.mail.update');
Route::get('/settings/ai', [SettingsController::class, 'ai'])->name('settings.ai');
Route::post('/settings/ai', [SettingsController::class, 'updateAi'])->name('settings.ai.update');
Route::get('/settings/ai/default-prompt', [SettingsController::class, 'getDefaultPrompt'])->name('settings.ai.default-prompt.get');
Route::post('/settings/ai/default-prompt', [SettingsController::class, 'saveDefaultPrompt'])->name('settings.ai.default-prompt.save');
