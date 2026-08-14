<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\ToolsController;
use App\Http\Controllers\User\AccountController;
use App\Http\Controllers\User\BankAccountController;
use App\Http\Controllers\User\CostCenterController;
use App\Http\Controllers\User\JournalController;
use App\Http\Controllers\User\BankTransferController;
use App\Http\Controllers\User\BillController;
use App\Http\Controllers\User\BillingController;
use App\Http\Controllers\User\BranchController;
use App\Http\Controllers\User\ClientController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\ExpenseCategoryController;
use App\Http\Controllers\User\ExpenseController;
use App\Http\Controllers\User\InventoryController;
use App\Http\Controllers\User\InvoiceController;
use App\Http\Controllers\User\InvoiceTemplateController;
use App\Http\Controllers\User\ItemController;
use App\Http\Controllers\User\PaymentVoucherController;
use App\Http\Controllers\User\ProjectController;
use App\Http\Controllers\User\PurchaseOrderController;
use App\Http\Controllers\User\QuotationController;
use App\Http\Controllers\User\ReceiptVoucherController;
use App\Http\Controllers\User\ReportController;
use App\Http\Controllers\User\RoleController;
use App\Http\Controllers\User\SalespersonController;
use App\Http\Controllers\User\SettingsController;
use App\Http\Controllers\User\StockAdjustmentController;
use App\Http\Controllers\User\SupplierController;
use App\Http\Controllers\User\TeamController;
use App\Http\Controllers\User\WarehouseController;
use App\Http\Controllers\User\ZatcaController;
use Illuminate\Support\Facades\Route;

// Marketing site
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/features', [HomeController::class, 'features'])->name('features');
Route::get('/pricing', [HomeController::class, 'pricing'])->name('pricing');
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/compliance', [HomeController::class, 'compliance'])->name('compliance');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::post('/contact', [HomeController::class, 'submitContact'])->name('contact.submit');
Route::get('/legal/{page}', [HomeController::class, 'legal'])->name('legal');
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

Route::get('/glossary', [ToolsController::class, 'glossary'])->name('glossary');
Route::prefix('tools')->name('tools.')->group(function () {
    Route::get('/', [ToolsController::class, 'index'])->name('index');
    Route::get('/percentage-calculator', [ToolsController::class, 'percentageCalculator'])->name('percentage');
    Route::get('/discount-calculator', [ToolsController::class, 'discountCalculator'])->name('discount');
    Route::get('/vat-calculator', [ToolsController::class, 'vatCalculator'])->name('vat');
    Route::get('/zakat-calculator', [ToolsController::class, 'zakatCalculator'])->name('zakat');
    Route::get('/gosi-calculator', [ToolsController::class, 'gosiCalculator'])->name('gosi');
    Route::get('/end-of-service-calculator', [ToolsController::class, 'endOfServiceCalculator'])->name('end-of-service');
    Route::get('/zatca-penalty-calculator', [ToolsController::class, 'zatcaPenaltyCalculator'])->name('zatca-penalty');
    Route::get('/invoice-generator', [ToolsController::class, 'invoiceGenerator'])->name('invoice-generator');
    Route::get('/quotation-generator', [ToolsController::class, 'quotationGenerator'])->name('quotation-generator');
    Route::get('/receipt-voucher', [ToolsController::class, 'receiptVoucher'])->name('receipt-voucher');
    Route::get('/payment-voucher', [ToolsController::class, 'paymentVoucher'])->name('payment-voucher');
});

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
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard')->middleware('permission:dashboard');

    Route::middleware('permission:clients')->group(function () {
        Route::resource('clients', ClientController::class)->except(['show']);
        Route::get('clients/{client}/outstanding-invoices', [ClientController::class, 'outstandingInvoices'])->name('clients.outstanding-invoices');
    });
    Route::resource('items', ItemController::class)->except(['show'])->middleware('permission:items');

    Route::middleware('permission:invoices')->group(function () {
        Route::resource('invoices', InvoiceController::class);
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])->name('invoices.payments.store');
    });

    Route::middleware('permission:quotations')->group(function () {
        Route::resource('quotations', QuotationController::class);
        Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
        Route::post('quotations/{quotation}/accept', [QuotationController::class, 'accept'])->name('quotations.accept');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
        Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convertToInvoice'])->name('quotations.convert');
    });

    Route::middleware('permission:expenses')->group(function () {
        Route::resource('expenses', ExpenseController::class)->except(['show']);
        Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
        Route::delete('expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->name('expense-categories.destroy');
    });

    Route::middleware('permission:reports')->prefix('reports')->name('reports.')->group(function () {
        Route::get('vat', [ReportController::class, 'vat'])->name('vat');
        Route::get('sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('income-statement', [ReportController::class, 'incomeStatement'])->name('income-statement');
        Route::get('expenses', [ReportController::class, 'expenses'])->name('expenses');
        Route::get('cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('account-statement', [ReportController::class, 'accountStatement'])->name('account-statement');
    });

    Route::middleware('permission:accounting')->prefix('accounting')->group(function () {
        Route::prefix('accounts')->name('accounts.')->group(function () {
            Route::get('/', [AccountController::class, 'index'])->name('index');
            Route::post('/', [AccountController::class, 'store'])->name('store');
            Route::put('{account}', [AccountController::class, 'update'])->name('update');
            Route::delete('{account}', [AccountController::class, 'destroy'])->name('destroy');
            Route::post('{account}/deactivate', [AccountController::class, 'deactivate'])->name('deactivate');
            Route::post('{account}/activate', [AccountController::class, 'activate'])->name('activate');
        });
        Route::post('mappings', [AccountController::class, 'updateMapping'])->name('accounts.mappings.update');

        Route::prefix('journals')->name('journals.')->group(function () {
            Route::get('/', [JournalController::class, 'index'])->name('index');
            Route::get('{journalEntry}', [JournalController::class, 'show'])->name('show');
        });
        Route::get('ledger', [JournalController::class, 'ledger'])->name('ledger.index');

        Route::prefix('cost-centers')->name('cost-centers.')->group(function () {
            Route::get('/', [CostCenterController::class, 'index'])->name('index');
            Route::post('/', [CostCenterController::class, 'store'])->name('store');
            Route::put('{costCenter}', [CostCenterController::class, 'update'])->name('update');
            Route::delete('{costCenter}', [CostCenterController::class, 'destroy'])->name('destroy');
            Route::post('links', [CostCenterController::class, 'storeLink'])->name('links.store');
            Route::delete('links/{costCenterLink}', [CostCenterController::class, 'destroyLink'])->name('links.destroy');
        });
    });

    Route::middleware('permission:cash_banks')->group(function () {
        Route::resource('bank-accounts', BankAccountController::class)->except(['show']);
        Route::resource('receipt-vouchers', ReceiptVoucherController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('receipt-vouchers/{receiptVoucher}/void', [ReceiptVoucherController::class, 'void'])->name('receipt-vouchers.void');
        Route::resource('payment-vouchers', PaymentVoucherController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('payment-vouchers/{paymentVoucher}/void', [PaymentVoucherController::class, 'void'])->name('payment-vouchers.void');
        Route::resource('bank-transfers', BankTransferController::class)->only(['index', 'create', 'store']);
    });

    Route::middleware('permission:purchases')->group(function () {
        Route::resource('suppliers', SupplierController::class)->except(['show']);
        Route::get('suppliers/{supplier}/outstanding-bills', [SupplierController::class, 'outstandingBills'])->name('suppliers.outstanding-bills');

        Route::resource('bills', BillController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('bills/{bill}/post', [BillController::class, 'post'])->name('bills.post');
        Route::post('bills/{bill}/void', [BillController::class, 'void'])->name('bills.void');

        Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
        Route::post('purchase-orders/{purchaseOrder}/void', [PurchaseOrderController::class, 'void'])->name('purchase-orders.void');
        Route::post('purchase-orders/{purchaseOrder}/convert', [PurchaseOrderController::class, 'convertToBill'])->name('purchase-orders.convert');
    });

    Route::middleware('permission:inventory')->group(function () {
        Route::resource('warehouses', WarehouseController::class)->except(['show']);
        Route::post('warehouses/{warehouse}/make-default', [WarehouseController::class, 'makeDefault'])->name('warehouses.make-default');
        Route::resource('stock-adjustments', StockAdjustmentController::class)->only(['index', 'create', 'store']);
        Route::post('stock-adjustments/{stockAdjustment}/revoke', [StockAdjustmentController::class, 'revoke'])->name('stock-adjustments.revoke');
        Route::get('inventory/stock', [InventoryController::class, 'stock'])->name('inventory.stock');
    });

    Route::resource('salespersons', SalespersonController::class)->except(['show'])->middleware('permission:salespersons');

    Route::resource('projects', ProjectController::class)->middleware('permission:projects');

    Route::middleware('role:owner')->group(function () {
        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
        Route::post('billing/upgrade', [BillingController::class, 'upgrade'])->name('billing.upgrade');
    });

    Route::middleware('permission:members_roles')->group(function () {
        Route::get('team', [TeamController::class, 'index'])->name('team.index');
        Route::post('team', [TeamController::class, 'store'])->name('team.store');
        Route::delete('team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');

        Route::resource('roles', RoleController::class)->except(['show']);
        Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    });

    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update')->middleware('permission:settings');
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index')->middleware('permission:settings');

    Route::resource('branches', BranchController::class)->except(['show'])->middleware('permission:branches');
    Route::post('branches/{branch}/make-default', [BranchController::class, 'makeDefault'])->name('branches.make-default')->middleware('permission:branches');

    Route::middleware('permission:settings')->prefix('invoice-templates')->name('invoice-templates.')->group(function () {
        Route::get('/', [InvoiceTemplateController::class, 'index'])->name('index');
        Route::post('/', [InvoiceTemplateController::class, 'store'])->name('store');
        Route::post('use', [InvoiceTemplateController::class, 'useTemplate'])->name('use');
        Route::put('{invoiceTemplate}', [InvoiceTemplateController::class, 'update'])->name('update');
        Route::delete('{invoiceTemplate}', [InvoiceTemplateController::class, 'destroy'])->name('destroy');
        Route::post('{invoiceTemplate}/make-default', [InvoiceTemplateController::class, 'makeDefault'])->name('make-default');
    });

    Route::middleware('permission:zatca')->prefix('zatca')->name('zatca.')->group(function () {
        Route::get('/', [ZatcaController::class, 'dashboard'])->name('dashboard');
        Route::put('settings', [ZatcaController::class, 'updateSettings'])->name('settings.update');
        Route::post('csr', [ZatcaController::class, 'generateCsr'])->name('csr');
        Route::post('compliance-csid', [ZatcaController::class, 'issueComplianceCsid'])->name('compliance-csid');
        Route::post('compliance-check', [ZatcaController::class, 'runComplianceCheck'])->name('compliance-check');
        Route::post('production-csid', [ZatcaController::class, 'issueProductionCsid'])->name('production-csid');
        Route::post('reset', [ZatcaController::class, 'resetOnboarding'])->name('reset');
        Route::post('sync', [ZatcaController::class, 'sync'])->name('sync');
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
