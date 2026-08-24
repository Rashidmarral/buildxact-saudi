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

    Route::get('/clients', [ClientApiController::class, 'index'])->name('clients.index');
    Route::get('/clients/{client}', [ClientApiController::class, 'show'])->name('clients.show');

    Route::get('/items', [ItemApiController::class, 'index'])->name('items.index');
    Route::get('/items/{item}', [ItemApiController::class, 'show'])->name('items.show');

    Route::get('/invoices', [InvoiceApiController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceApiController::class, 'show'])->name('invoices.show');
});

Route::middleware(['auth:sanctum', 'abilities:write'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/clients', [ClientApiController::class, 'store'])->name('clients.store');
});
