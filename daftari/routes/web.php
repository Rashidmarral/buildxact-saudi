<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PlatformDocumentController;
use App\Http\Controllers\Admin\PaymentGatewaySettingsController as AdminPaymentGatewaySettingsController;
use App\Http\Controllers\Admin\PlatformSettingsController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TeamInviteController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\FileServeController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PaymentWidgetController;
use App\Http\Controllers\PublicInvoiceController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\ToolsController;
use App\Http\Controllers\User\AccountController;
use App\Http\Controllers\User\ActivityLogController as UserActivityLogController;
use App\Http\Controllers\User\ApiTokenController;
use App\Http\Controllers\User\BankAccountController;
use App\Http\Controllers\User\BankTransferController;
use App\Http\Controllers\User\BillController;
use App\Http\Controllers\User\BillingController;
use App\Http\Controllers\User\BranchController;
use App\Http\Controllers\User\ClientController;
use App\Http\Controllers\User\CostCenterController;
use App\Http\Controllers\User\CreditNoteController;
use App\Http\Controllers\User\CustomsDeclarationController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\ExpenseCategoryController;
use App\Http\Controllers\User\ExpenseController;
use App\Http\Controllers\User\InventoryController;
use App\Http\Controllers\User\InvoiceController;
use App\Http\Controllers\User\InvoiceTemplateController;
use App\Http\Controllers\User\ItemController;
use App\Http\Controllers\User\UnitController;
use App\Http\Controllers\User\GlobalSearchController;
use App\Http\Controllers\User\JournalController;
use App\Http\Controllers\User\NotificationController;
use App\Http\Controllers\User\PaymentVoucherController;
use App\Http\Controllers\User\ProjectController;
use App\Http\Controllers\User\PurchaseOrderController;
use App\Http\Controllers\User\PurchaseReturnController;
use App\Http\Controllers\User\QuotationController;
use App\Http\Controllers\User\ReceiptVoucherController;
use App\Http\Controllers\User\RecurringInvoiceController;
use App\Http\Controllers\User\ReportController;
use App\Http\Controllers\User\RoleController;
use App\Http\Controllers\User\SalespersonController;
use App\Http\Controllers\User\SettingsController;
use App\Http\Controllers\User\StockAdjustmentController;
use App\Http\Controllers\User\SupplierController;
use App\Http\Controllers\User\TeamController;
use App\Http\Controllers\User\TwoFactorController;
use App\Http\Controllers\User\WarehouseController;
use App\Http\Controllers\User\PaymentGatewayController;
use App\Http\Controllers\User\WhatsappSettingsController;
use App\Http\Controllers\User\WebhookController;
use App\Http\Controllers\User\ZatcaController;
use Illuminate\Support\Facades\Route;

// Marketing site
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/features', [HomeController::class, 'features'])->name('features');
Route::get('/pricing', [HomeController::class, 'pricing'])->name('pricing');
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/compliance', [HomeController::class, 'compliance'])->name('compliance');
Route::get('/certificates', [HomeController::class, 'certificates'])->name('certificates');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::post('/contact', [HomeController::class, 'submitContact'])->name('contact.submit');
Route::get('/legal/{page}', [HomeController::class, 'legal'])->name('legal');
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');
Route::get('/files/{filepath}', [FileServeController::class, 'show'])->where('filepath', '.*')->name('files.show');

Route::get('/pay/invoices/{id}/{token}', [PublicInvoiceController::class, 'show'])->name('public.invoices.show');
Route::get('/pay/invoices/{id}/{token}/pdf', [PublicInvoiceController::class, 'downloadPdf'])->name('public.invoices.pdf');
Route::get('/pay/invoices/{id}/{token}/pay/{provider}', [PublicInvoiceController::class, 'pay'])->name('public.invoices.pay');

// Public payment-gateway plumbing: the hosted-widget page for HyperPay's
// COPYandPAY flow, and the single webhook endpoint every driver posts (or,
// for HyperPay's redirect flow, GETs) results to. Deliberately outside any
// auth middleware — these are called by the payment provider or by a
// payer's browser that may never have a Daftari session.
Route::get('/payments/widget/{provider}/{reference}', [PaymentWidgetController::class, 'show'])->name('payments.widget');
Route::match(['get', 'post'], '/payments/webhook/{provider}', [PaymentWebhookController::class, 'handle'])->name('payments.webhook');

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
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:password-email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
});
Route::get('/team/invite/{id}/{hash}', [TeamInviteController::class, 'show'])->middleware('signed')->name('team.invite.accept');
Route::post('/team/invite/{id}/{hash}', [TeamInviteController::class, 'accept'])->middleware('signed')->name('team.invite.store');

Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'verify'])->middleware('throttle:two-factor')->name('two-factor.verify');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('/stop-impersonating', [ImpersonationController::class, 'stop'])->middleware('auth')->name('stop-impersonating');

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [AuthController::class, 'showVerifyEmail'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])->middleware('throttle:6,1')->name('verification.send');
});

// User panel
Route::prefix('app')->name('app.')->middleware(['auth', 'company.active', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard')->middleware('permission:dashboard');

    Route::get('search', [GlobalSearchController::class, 'search'])->name('search');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::middleware('permission:clients')->group(function () {
        Route::resource('clients', ClientController::class)->except(['show']);
        Route::get('clients/{client}/outstanding-invoices', [ClientController::class, 'outstandingInvoices'])->name('clients.outstanding-invoices');
        Route::get('clients-import', [ClientController::class, 'showImport'])->name('clients.import');
        Route::post('clients-import', [ClientController::class, 'import'])->name('clients.import.store');
        Route::get('clients-import/template', [ClientController::class, 'importTemplate'])->name('clients.import.template');
    });
    Route::resource('items', ItemController::class)->except(['show'])->middleware('permission:items');
    Route::get('items-generate-barcode', [ItemController::class, 'generateBarcode'])->name('items.generate-barcode')->middleware('permission:items');
    Route::get('items-import', [ItemController::class, 'showImport'])->name('items.import')->middleware('permission:items');
    Route::post('items-import', [ItemController::class, 'import'])->name('items.import.store')->middleware('permission:items');
    Route::get('items-import/template', [ItemController::class, 'importTemplate'])->name('items.import.template')->middleware('permission:items');
    Route::resource('units', UnitController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:items');

    Route::middleware('permission:invoices')->group(function () {
        Route::resource('invoices', InvoiceController::class);
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])->name('invoices.payments.store');
        Route::post('invoices/{invoice}/attachments', [InvoiceController::class, 'storeAttachment'])->name('invoices.attachments.store');
        Route::delete('invoices/{invoice}/attachments/{attachment}', [InvoiceController::class, 'destroyAttachment'])->name('invoices.attachments.destroy');
        Route::get('invoices/{invoice}/xml', [InvoiceController::class, 'downloadXml'])->name('invoices.xml');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/email', [InvoiceController::class, 'emailInvoice'])->name('invoices.email');
        Route::post('invoices/{invoice}/whatsapp', [InvoiceController::class, 'sendWhatsapp'])->name('invoices.whatsapp');

        Route::get('credit-notes/eligible-invoices', [CreditNoteController::class, 'eligibleInvoices'])->name('credit-notes.eligible-invoices');
        Route::resource('credit-notes', CreditNoteController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('credit-notes/{creditNote}/void', [CreditNoteController::class, 'void'])->name('credit-notes.void');
        Route::get('credit-notes/{creditNote}/xml', [CreditNoteController::class, 'downloadXml'])->name('credit-notes.xml');
        Route::get('credit-notes/{creditNote}/pdf', [CreditNoteController::class, 'downloadPdf'])->name('credit-notes.pdf');

        Route::middleware('feature:recurring_invoices')->group(function () {
            Route::resource('recurring-invoices', RecurringInvoiceController::class)->except(['show']);
            Route::post('recurring-invoices/{recurringInvoice}/pause', [RecurringInvoiceController::class, 'pause'])->name('recurring-invoices.pause');
            Route::post('recurring-invoices/{recurringInvoice}/resume', [RecurringInvoiceController::class, 'resume'])->name('recurring-invoices.resume');
        });
    });

    Route::middleware(['permission:quotations', 'feature:quotations'])->group(function () {
        Route::resource('quotations', QuotationController::class);
        Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
        Route::post('quotations/{quotation}/accept', [QuotationController::class, 'accept'])->name('quotations.accept');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
        Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convertToInvoice'])->name('quotations.convert');
        Route::post('quotations/{quotation}/attachments', [QuotationController::class, 'storeAttachment'])->name('quotations.attachments.store');
        Route::delete('quotations/{quotation}/attachments/{attachment}', [QuotationController::class, 'destroyAttachment'])->name('quotations.attachments.destroy');
        Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'downloadPdf'])->name('quotations.pdf');
        Route::post('quotations/{quotation}/email', [QuotationController::class, 'emailQuotation'])->name('quotations.email');
    });

    Route::middleware('permission:expenses')->group(function () {
        Route::resource('expenses', ExpenseController::class)->except(['show']);
        Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
        Route::delete('expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->name('expense-categories.destroy');
    });

    Route::middleware('permission:reports')->prefix('reports')->name('reports.')->group(function () {
        Route::get('vat', [ReportController::class, 'vat'])->name('vat')->middleware('feature:vat_return_report');
        Route::get('sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('income-statement', [ReportController::class, 'incomeStatement'])->name('income-statement')->middleware('feature:financial_statements');
        Route::get('expenses', [ReportController::class, 'expenses'])->name('expenses');
        Route::get('cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet')->middleware('feature:financial_statements');
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

        Route::middleware('feature:cost_centers')->prefix('cost-centers')->name('cost-centers.')->group(function () {
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
        Route::get('bank-transactions', [BankAccountController::class, 'transactions'])->name('bank-transactions.index');
        Route::resource('receipt-vouchers', ReceiptVoucherController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('receipt-vouchers/{receiptVoucher}/void', [ReceiptVoucherController::class, 'void'])->name('receipt-vouchers.void');
        Route::get('receipt-vouchers/{receiptVoucher}/pdf', [ReceiptVoucherController::class, 'downloadPdf'])->name('receipt-vouchers.pdf');
        Route::resource('payment-vouchers', PaymentVoucherController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('payment-vouchers/{paymentVoucher}/void', [PaymentVoucherController::class, 'void'])->name('payment-vouchers.void');
        Route::get('payment-vouchers/{paymentVoucher}/pdf', [PaymentVoucherController::class, 'downloadPdf'])->name('payment-vouchers.pdf');
        Route::resource('bank-transfers', BankTransferController::class)->only(['index', 'create', 'store']);
    });

    Route::middleware('permission:purchases')->group(function () {
        Route::resource('suppliers', SupplierController::class)->except(['show']);
        Route::get('suppliers/{supplier}/outstanding-bills', [SupplierController::class, 'outstandingBills'])->name('suppliers.outstanding-bills');
        Route::get('suppliers/{supplier}/bills', [SupplierController::class, 'bills'])->name('suppliers.bills');
        Route::get('suppliers-import', [SupplierController::class, 'showImport'])->name('suppliers.import');
        Route::post('suppliers-import', [SupplierController::class, 'import'])->name('suppliers.import.store');
        Route::get('suppliers-import/template', [SupplierController::class, 'importTemplate'])->name('suppliers.import.template');

        Route::resource('bills', BillController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('bills/{bill}/post', [BillController::class, 'post'])->name('bills.post');
        Route::post('bills/{bill}/void', [BillController::class, 'void'])->name('bills.void');
        Route::post('bills/{bill}/attachments', [BillController::class, 'storeAttachment'])->name('bills.attachments.store');
        Route::delete('bills/{bill}/attachments/{attachment}', [BillController::class, 'destroyAttachment'])->name('bills.attachments.destroy');
        Route::get('bills/{bill}/pdf', [BillController::class, 'downloadPdf'])->name('bills.pdf');

        Route::middleware('feature:purchase_orders')->group(function () {
            Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show']);
            Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
            Route::post('purchase-orders/{purchaseOrder}/void', [PurchaseOrderController::class, 'void'])->name('purchase-orders.void');
            Route::post('purchase-orders/{purchaseOrder}/convert', [PurchaseOrderController::class, 'convertToBill'])->name('purchase-orders.convert');
            Route::post('purchase-orders/{purchaseOrder}/attachments', [PurchaseOrderController::class, 'storeAttachment'])->name('purchase-orders.attachments.store');
            Route::delete('purchase-orders/{purchaseOrder}/attachments/{attachment}', [PurchaseOrderController::class, 'destroyAttachment'])->name('purchase-orders.attachments.destroy');
            Route::get('purchase-orders/{purchaseOrder}/pdf', [PurchaseOrderController::class, 'downloadPdf'])->name('purchase-orders.pdf');
        });

        Route::resource('customs-declarations', CustomsDeclarationController::class)->only(['index', 'store', 'destroy']);

        Route::middleware('feature:debit_notes')->group(function () {
            Route::get('purchase-returns/eligible-bills', [PurchaseReturnController::class, 'eligibleBills'])->name('purchase-returns.eligible-bills');
            Route::resource('purchase-returns', PurchaseReturnController::class)->only(['index', 'create', 'store', 'show']);
            Route::post('purchase-returns/{purchaseReturn}/void', [PurchaseReturnController::class, 'void'])->name('purchase-returns.void');
            Route::get('purchase-returns/{purchaseReturn}/pdf', [PurchaseReturnController::class, 'downloadPdf'])->name('purchase-returns.pdf');
        });
    });

    Route::middleware('permission:inventory')->group(function () {
        Route::resource('warehouses', WarehouseController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('warehouses/{warehouse}/make-default', [WarehouseController::class, 'makeDefault'])->name('warehouses.make-default');
        Route::resource('stock-adjustments', StockAdjustmentController::class)->only(['index', 'store']);
        Route::post('stock-adjustments/{stockAdjustment}/revoke', [StockAdjustmentController::class, 'revoke'])->name('stock-adjustments.revoke');
        Route::get('inventory/stock', [InventoryController::class, 'stock'])->name('inventory.stock');
        Route::get('inventory/valuation', [InventoryController::class, 'valuation'])->name('inventory.valuation');
    });

    Route::resource('salespersons', SalespersonController::class)->except(['show'])->middleware('permission:salespersons');

    Route::resource('projects', ProjectController::class)->middleware('permission:projects');

    Route::middleware('role:owner')->group(function () {
        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
        Route::post('billing/upgrade', [BillingController::class, 'upgrade'])->name('billing.upgrade');
        Route::get('billing/bank-transfer/{payment}', [BillingController::class, 'bankTransferInstructions'])->name('billing.bank-transfer');
        Route::get('billing/payments/{payment}/receipt', [BillingController::class, 'downloadReceipt'])->name('billing.receipt');
        Route::post('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
        Route::post('billing/resume', [BillingController::class, 'resume'])->name('billing.resume');
        Route::get('activity', [UserActivityLogController::class, 'index'])->name('activity.index');
    });

    Route::middleware('permission:members_roles')->group(function () {
        Route::get('team', [TeamController::class, 'index'])->name('team.index');
        Route::post('team', [TeamController::class, 'store'])->name('team.store');
        Route::delete('team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');
        Route::post('team/{user}/resend-invite', [TeamController::class, 'resendInvite'])->name('team.resend-invite');

        Route::resource('roles', RoleController::class)->except(['show']);
        Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    });

    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update')->middleware('permission:settings');
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index')->middleware('permission:settings');
    Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('settings/sessions/logout-others', [SettingsController::class, 'logoutOtherSessions'])->name('settings.sessions.logout-others');
    Route::delete('settings/sessions/{sessionId}', [SettingsController::class, 'destroySession'])->name('settings.sessions.destroy');
    Route::post('settings/documents', [SettingsController::class, 'storeDocument'])->name('settings.documents.store')->middleware('permission:settings');
    Route::delete('settings/documents/{attachment}', [SettingsController::class, 'destroyDocument'])->name('settings.documents.destroy')->middleware('permission:settings');

    Route::get('settings/two-factor', [TwoFactorController::class, 'show'])->name('settings.two-factor');
    Route::post('settings/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('settings.two-factor.confirm');
    Route::post('settings/two-factor/disable', [TwoFactorController::class, 'disable'])->name('settings.two-factor.disable');
    Route::post('settings/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('settings.two-factor.recovery-codes');

    Route::get('settings/api-tokens', [ApiTokenController::class, 'index'])->name('settings.api-tokens');
    Route::post('settings/api-tokens', [ApiTokenController::class, 'store'])->name('settings.api-tokens.store');
    Route::delete('settings/api-tokens/{tokenId}', [ApiTokenController::class, 'destroy'])->name('settings.api-tokens.destroy');

    Route::get('settings/webhooks', [WebhookController::class, 'index'])->name('settings.webhooks.index');
    Route::post('settings/webhooks', [WebhookController::class, 'store'])->name('settings.webhooks.store');
    Route::get('settings/webhooks/{webhook}', [WebhookController::class, 'show'])->name('settings.webhooks.show');
    Route::put('settings/webhooks/{webhook}', [WebhookController::class, 'update'])->name('settings.webhooks.update');
    Route::post('settings/webhooks/{webhook}/toggle', [WebhookController::class, 'toggle'])->name('settings.webhooks.toggle');
    Route::post('settings/webhooks/{webhook}/regenerate-secret', [WebhookController::class, 'regenerateSecret'])->name('settings.webhooks.regenerate-secret');
    Route::post('settings/webhooks/{webhook}/send-test', [WebhookController::class, 'sendTest'])->name('settings.webhooks.send-test');
    Route::delete('settings/webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('settings.webhooks.destroy');

    Route::get('settings/payment-gateways', [PaymentGatewayController::class, 'index'])->name('settings.payment-gateways');
    Route::post('settings/payment-gateways/{provider}', [PaymentGatewayController::class, 'update'])->name('settings.payment-gateways.update');

    Route::get('settings/whatsapp', [WhatsappSettingsController::class, 'show'])->name('settings.whatsapp');
    Route::post('settings/whatsapp', [WhatsappSettingsController::class, 'update'])->name('settings.whatsapp.update');
    Route::post('settings/whatsapp/test', [WhatsappSettingsController::class, 'test'])->name('settings.whatsapp.test');

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

    Route::middleware(['permission:zatca', 'feature:zatca_phase2'])->prefix('zatca')->name('zatca.')->group(function () {
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
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:super_admin,admin_staff'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::middleware('admin.permission:companies')->group(function () {
        Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
        Route::post('companies/{company}/suspend', [CompanyController::class, 'suspend'])->name('companies.suspend');
        Route::post('companies/{company}/activate', [CompanyController::class, 'activate'])->name('companies.activate');
        Route::post('companies/{company}/change-plan', [CompanyController::class, 'changePlan'])->name('companies.change-plan');
        Route::post('companies/{company}/impersonate', [CompanyController::class, 'impersonate'])->name('companies.impersonate');
    });

    Route::middleware('admin.permission:plans')->group(function () {
        Route::resource('plans', PlanController::class)->except(['show']);
    });

    Route::middleware('admin.permission:payments')->group(function () {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
        Route::post('payments/{payment}/confirm-bank-transfer', [PaymentController::class, 'confirmBankTransfer'])->name('payments.confirm-bank-transfer');
    });

    Route::middleware('admin.permission:activity')->group(function () {
        Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');
    });

    // Managing other admin accounts, admin roles, and platform-wide
    // settings stays super_admin only — never delegable, so a granular
    // admin role can never be used to escalate itself or another account.
    Route::middleware('role:super_admin')->group(function () {
        Route::get('admins', [AdminUserController::class, 'index'])->name('admins.index');
        Route::post('admins', [AdminUserController::class, 'store'])->name('admins.store');
        Route::delete('admins/{user}', [AdminUserController::class, 'destroy'])->name('admins.destroy');

        Route::resource('admin-roles', AdminRoleController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('settings', [PlatformSettingsController::class, 'edit'])->name('settings.edit');
        Route::post('settings', [PlatformSettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/branding', [PlatformSettingsController::class, 'updateBranding'])->name('settings.branding');

        Route::get('settings/payment-gateways', [AdminPaymentGatewaySettingsController::class, 'index'])->name('settings.payment-gateways');
        Route::post('settings/payment-gateways/{provider}', [AdminPaymentGatewaySettingsController::class, 'update'])->name('settings.payment-gateways.update');

        Route::resource('certificates', PlatformDocumentController::class)->only(['index', 'store', 'destroy']);
    });
});
