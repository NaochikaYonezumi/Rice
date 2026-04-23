<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\ScrapeController;
use App\Models\ScrapedUrl;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// メール
Route::get('/', [EmailController::class, 'index'])->name('emails.index');
Route::get('/emails/search', [EmailController::class, 'search'])->name('emails.search');
Route::get('/emails/{email}', [EmailController::class, 'show'])->name('emails.show');
Route::get('/threads/{thread}', [EmailController::class, 'thread'])->name('threads.show');
Route::put('/threads/{thread}/tags', [EmailController::class, 'updateTags'])->name('threads.tags');
Route::post('/emails/{email}/ai', [EmailController::class, 'askAi'])->name('emails.ai');
Route::post('/emails/{email}/reply', [EmailController::class, 'reply'])->name('emails.reply');
Route::post('/emails/fetch', [EmailController::class, 'fetch'])->name('emails.fetch');

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
