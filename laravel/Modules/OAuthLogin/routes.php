<?php

use Illuminate\Support\Facades\Route;
use Modules\OAuthLogin\Http\Controllers\OAuthController;

Route::get('/auth/microsoft/redirect', [OAuthController::class, 'redirect'])->name('auth.microsoft.redirect');
Route::get('/auth/microsoft/callback', [OAuthController::class, 'callback'])->name('auth.microsoft.callback');
