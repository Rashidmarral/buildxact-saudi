<?php

use App\Http\Controllers\Api\V1\BillApiController;
use App\Http\Controllers\Api\V1\BillPaymentApiController;
use App\Http\Controllers\Api\V1\ClientApiController;
use App\Http\Controllers\Api\V1\InvoiceApiController;
use App\Http\Controllers\Api\V1\InvoicePaymentApiController;
use App\Http\Controllers\Api\V1\ItemApiController;
use App\Http\Controllers\Api\V1\JournalEntryApiController;
use App\Http\Controllers\Api\V1\ReportApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Every token carries a 'read' ability at minimum; 'write' is added only
// when the user explicitly creates a read-write token (see
// App\Http\Controllers\User\ApiTokenController). 'api.limit' meters and
// enforces the company's "API calls" plan limit (Module 07) once here,
// rather than in each controller action below.
Route::middleware(['auth:sanctum', 'throttle:api', 'api.limit', 'abilities:read'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/me', function (Request $request) {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'company' => $user->company?->name,
        ]);
    })->name('me');

    // Each resource is gated by the same company permission its web-panel
    // equivalent requires — a token only ever grants what its owning user
    // could already reach through the UI, never more.
    Route::middleware('permission:clients')->group(function () {
        Route::get('/clients', [ClientApiController::class, 'index'])->name('clients.index');
        Route::get('/clients/{client}', [ClientApiController::class, 'show'])->name('clients.show');
    });

    Route::middleware('permission:items')->group(function () {
        Route::get('/items', [ItemApiController::class, 'index'])->name('items.index');
        Route::get('/items/{item}', [ItemApiController::class, 'show'])->name('items.show');
    });

    Route::middleware('permission:invoices')->group(function () {
        Route::get('/invoices', [InvoiceApiController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceApiController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{invoice}/payments', [InvoicePaymentApiController::class, 'index'])->name('invoices.payments.index');
    });

    Route::middleware('permission:purchases')->group(function () {
        Route::get('/bills', [BillApiController::class, 'index'])->name('bills.index');
        Route::get('/bills/{bill}', [BillApiController::class, 'show'])->name('bills.show');
        Route::get('/bills/{bill}/payments', [BillPaymentApiController::class, 'index'])->name('bills.payments.index');
    });

    Route::middleware('permission:accounting')->group(function () {
        Route::get('/journal-entries', [JournalEntryApiController::class, 'index'])->name('journal-entries.index');
        Route::get('/journal-entries/{journalEntry}', [JournalEntryApiController::class, 'show'])->name('journal-entries.show');
    });

    Route::middleware('permission:reports')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/trial-balance', [ReportApiController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/balance-sheet', [ReportApiController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/income-statement', [ReportApiController::class, 'incomeStatement'])->name('income-statement');
    });
});

Route::middleware(['auth:sanctum', 'throttle:api', 'api.limit', 'abilities:write', 'permission:clients'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/clients', [ClientApiController::class, 'store'])->name('clients.store');
});
