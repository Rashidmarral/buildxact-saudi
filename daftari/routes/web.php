<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\User\BillingController;
use App\Http\Controllers\User\ClientController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\ExpenseCategoryController;
use App\Http\Controllers\User\ExpenseController;
use App\Http\Controllers\User\InvoiceController;
use App\Http\Controllers\User\ItemController;
use App\Http\Controllers\User\ReportController;
use App\Http\Controllers\User\SettingsController;
use App\Http\Controllers\User\TeamController;
use Illuminate\Support\Facades\Route;

// Marketing site
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/features', [HomeController::class, 'features'])->name('features');
Route::get('/pricing', [HomeController::class, 'pricing'])->name('pricing');
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::post('/contact', [HomeController::class, 'submitContact'])->name('contact.submit');
Route::get('/legal/{page}', [HomeController::class, 'legal'])->name('legal');
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// User panel
Route::prefix('app')->name('app.')->middleware(['auth', 'company.active'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('clients', ClientController::class)->except(['show']);
    Route::resource('items', ItemController::class)->except(['show']);

    Route::resource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])->name('invoices.payments.store');

    Route::resource('expenses', ExpenseController::class)->except(['show']);
    Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
    Route::delete('expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->name('expense-categories.destroy');

    Route::get('reports/vat', [ReportController::class, 'vat'])->name('reports.vat');

    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('billing/upgrade', [BillingController::class, 'upgrade'])->name('billing.upgrade');

    Route::middleware('role:owner')->group(function () {
        Route::get('team', [TeamController::class, 'index'])->name('team.index');
        Route::post('team', [TeamController::class, 'store'])->name('team.store');
        Route::delete('team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');

        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});

// Platform admin panel
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:super_admin'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::post('companies/{company}/suspend', [CompanyController::class, 'suspend'])->name('companies.suspend');
    Route::post('companies/{company}/activate', [CompanyController::class, 'activate'])->name('companies.activate');

    Route::resource('plans', PlanController::class)->except(['show']);

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');

    Route::get('admins', [AdminUserController::class, 'index'])->name('admins.index');
    Route::post('admins', [AdminUserController::class, 'store'])->name('admins.store');
    Route::delete('admins/{user}', [AdminUserController::class, 'destroy'])->name('admins.destroy');
});
