<?php

use App\Http\Controllers\Api\V1\ClientApiController;
use App\Http\Controllers\Api\V1\InvoiceApiController;
use App\Http\Controllers\Api\V1\ItemApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Every token carries a 'read' ability at minimum; 'write' is added only
// when the user explicitly creates a read-write token (see
// App\Http\Controllers\User\ApiTokenController).
Route::middleware(['auth:sanctum', 'abilities:read'])->prefix('v1')->name('api.v1.')->group(function () {
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
    });
});

Route::middleware(['auth:sanctum', 'abilities:write', 'permission:clients'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/clients', [ClientApiController::class, 'store'])->name('clients.store');
});
