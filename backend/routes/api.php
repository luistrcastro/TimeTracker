<?php

use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResendVerificationController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\Contractor\ClientsController;
use App\Http\Controllers\Api\Contractor\CompanyController;
use App\Http\Controllers\Api\Contractor\EntriesController as ContractorEntriesController;
use App\Http\Controllers\Api\Contractor\InvoicesController;
use App\Http\Controllers\Api\Replicon\CredentialsController;
use App\Http\Controllers\Api\Replicon\EntriesController as RepliconEntriesController;
use App\Http\Controllers\Api\Replicon\ProjectsCacheController;
use App\Http\Controllers\Api\Replicon\RowMapController;
use App\Http\Controllers\Api\Replicon\SubmitController;
use App\Http\Controllers\Api\Replicon\SyncController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register',        [RegisterController::class, 'store']);
Route::post('/auth/login',           [LoginController::class, 'store']);
Route::post('/auth/forgot-password', [ForgotPasswordController::class, 'store']);
Route::post('/auth/reset-password',  [ResetPasswordController::class, 'store']);

Route::get('/auth/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware('signed')
    ->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/resend-verification', [ResendVerificationController::class, 'store']);
    Route::post('/auth/logout',              [LogoutController::class, 'destroy']);
    Route::get('/me', fn () => response()->json(auth()->user()));
});

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Contractor
    Route::apiResource('contractor/entries',  ContractorEntriesController::class);
    Route::apiResource('contractor/clients',  ClientsController::class);
    Route::apiResource('contractor/invoices', InvoicesController::class);
    Route::get('contractor/invoices/{invoice}/pdf', [InvoicesController::class, 'pdf']);
    Route::get('contractor/company', [CompanyController::class, 'show']);
    Route::put('contractor/company', [CompanyController::class, 'update']);

    // Replicon — Phase 5
    Route::apiResource('replicon/entries', RepliconEntriesController::class);
    Route::get   ('replicon/credentials', [CredentialsController::class, 'show']);
    Route::put   ('replicon/credentials', [CredentialsController::class, 'update']);
    Route::delete('replicon/credentials', [CredentialsController::class, 'destroy']);
    Route::get   ('replicon/projects',    [ProjectsCacheController::class, 'index']);
    Route::get   ('replicon/row-map',     [RowMapController::class, 'index']);
    Route::put   ('replicon/row-map',     [RowMapController::class, 'update']);
    Route::post  ('replicon/row-map',     [RowMapController::class, 'storeFromBookmarklet']);
    Route::post  ('replicon/sync',        [SyncController::class, 'store']);
    Route::post  ('replicon/submit',      [SubmitController::class, 'store']);
});

Route::get('/health', fn () => response()->json(['ok' => true, 'time' => now()]));
