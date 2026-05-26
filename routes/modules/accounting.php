<?php

use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\AccountDocumentController;
use App\Http\Controllers\Accounting\AccountingAccountGroupController;
use App\Http\Controllers\Accounting\AccountingAuditController;
use App\Http\Controllers\Accounting\AccountingDashboardController;
use App\Http\Controllers\Accounting\AccountingIncotermController;
use App\Http\Controllers\Accounting\AccountingPaymentTermController;
use App\Http\Controllers\Accounting\AccountingReportController;
use App\Http\Controllers\Accounting\AccountingReportExportController;
use App\Http\Controllers\Accounting\AccountingSettingsController;
use App\Http\Controllers\Accounting\AccountingTaxGroupController;
use App\Http\Controllers\Accounting\AccountJournalController;
use App\Http\Controllers\Accounting\AccountMoveController;
use App\Http\Controllers\Accounting\AccountMoveLineController;
use App\Http\Controllers\Accounting\AccountPaymentController;
use App\Http\Controllers\Accounting\AccountTaxController;
use App\Http\Controllers\Accounting\CurrencyRateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Accounting module (Unified Accounting System)
|--------------------------------------------------------------------------
| Required from routes/web.php inside the auth middleware group.
*/
Route::prefix('accounting')->name('accounting.')->group(function () {

    // Dashboard / overview
    Route::get('/', [AccountingDashboardController::class, 'index'])
        ->middleware('permission:accounting.read')->name('dashboard');

    // Chart of Accounts
    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/',              [AccountController::class, 'read'])     ->middleware('permission:accounting.read')   ->name('index');
        Route::get('/create',        [AccountController::class, 'create'])   ->middleware('permission:accounting.create') ->name('create');
        Route::post('/',             [AccountController::class, 'store'])    ->middleware('permission:accounting.create') ->name('store');
        Route::get('/{account}',           [AccountController::class, 'show'])      ->middleware('permission:accounting.read')   ->name('show');
        Route::get('/{account}/edit',      [AccountController::class, 'edit'])      ->middleware('permission:accounting.write')  ->name('edit');
        Route::put('/{account}',           [AccountController::class, 'write'])     ->middleware('permission:accounting.write')  ->name('update');
        Route::patch('/{account}/archive', [AccountController::class, 'archive'])   ->middleware('permission:accounting.write')  ->name('archive');
        Route::patch('/{account}/unarchive', [AccountController::class, 'unarchive'])->middleware('permission:accounting.write') ->name('unarchive');
        Route::delete('/{account}',        [AccountController::class, 'unlink'])    ->middleware('permission:accounting.unlink') ->name('delete');
        Route::post('/{account}/comment',  [AccountController::class, 'addComment'])->middleware('permission:accounting.write')  ->name('comment');
    });

    // Journals
    Route::prefix('journals')->name('journals.')->group(function () {
        Route::get('/',              [AccountJournalController::class, 'read'])     ->middleware('permission:accounting.read')   ->name('index');
        Route::get('/create',        [AccountJournalController::class, 'create'])   ->middleware('permission:accounting.create') ->name('create');
        Route::post('/',             [AccountJournalController::class, 'store'])    ->middleware('permission:accounting.create') ->name('store');
        Route::get('/{journal}',           [AccountJournalController::class, 'show'])      ->middleware('permission:accounting.read')   ->name('show');
        Route::get('/{journal}/edit',      [AccountJournalController::class, 'edit'])      ->middleware('permission:accounting.write')  ->name('edit');
        Route::put('/{journal}',           [AccountJournalController::class, 'write'])     ->middleware('permission:accounting.write')  ->name('update');
        Route::patch('/{journal}/archive', [AccountJournalController::class, 'archive'])   ->middleware('permission:accounting.write')  ->name('archive');
        Route::patch('/{journal}/unarchive', [AccountJournalController::class, 'unarchive'])->middleware('permission:accounting.write') ->name('unarchive');
        Route::delete('/{journal}',        [AccountJournalController::class, 'unlink'])    ->middleware('permission:accounting.unlink') ->name('delete');
        Route::post('/{journal}/comment',  [AccountJournalController::class, 'addComment'])->middleware('permission:accounting.write')  ->name('comment');
    });

    // Customer Invoices
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/',                  [AccountDocumentController::class, 'invoices'])      ->middleware('permission:accounting.read')   ->name('index');
        Route::get('/create',            [AccountDocumentController::class, 'createInvoice']) ->middleware('permission:accounting.create') ->name('create');
        Route::post('/',                 [AccountDocumentController::class, 'storeInvoice'])  ->middleware('permission:accounting.create') ->name('store');
        Route::get('/{invoice}',         [AccountDocumentController::class, 'showInvoice'])   ->middleware('permission:accounting.read')   ->name('show');
        Route::get('/{invoice}/edit',    [AccountDocumentController::class, 'editInvoice'])   ->middleware('permission:accounting.write')  ->name('edit');
        Route::put('/{invoice}',         [AccountDocumentController::class, 'updateInvoice']) ->middleware('permission:accounting.write')  ->name('update');
        Route::patch('/{invoice}/post',  [AccountDocumentController::class, 'postInvoice'])   ->middleware('permission:accounting.post')   ->name('post');
        Route::patch('/{invoice}/pay',   [AccountDocumentController::class, 'payInvoice'])    ->middleware('permission:accounting.post')   ->name('pay');
        Route::post('/{invoice}/credit-note', [AccountDocumentController::class, 'creditInvoice'])->middleware('permission:accounting.post')->name('credit-note');
        Route::get('/{invoice}/print',   [AccountDocumentController::class, 'printInvoice'])  ->middleware('permission:accounting.read')   ->name('print');
        Route::patch('/{invoice}/draft', [AccountDocumentController::class, 'resetInvoice'])  ->middleware('permission:accounting.post')   ->name('reset-draft');
        Route::patch('/{invoice}/cancel', [AccountDocumentController::class, 'cancelInvoice'])->middleware('permission:accounting.post')   ->name('cancel');
        Route::delete('/{invoice}',      [AccountDocumentController::class, 'deleteInvoice']) ->middleware('permission:accounting.unlink') ->name('delete');
        Route::post('/{invoice}/comment', [AccountDocumentController::class, 'commentInvoice'])->middleware('permission:accounting.write')  ->name('comment');
    });

    // Customer Credit Notes
    Route::prefix('credit-notes')->name('credit-notes.')->group(function () {
        Route::get('/',               [AccountDocumentController::class, 'creditNotes'])      ->middleware('permission:accounting.read')   ->name('index');
        Route::get('/create',         [AccountDocumentController::class, 'createCreditNote']) ->middleware('permission:accounting.create') ->name('create');
        Route::post('/',              [AccountDocumentController::class, 'storeCreditNote'])  ->middleware('permission:accounting.create') ->name('store');
        Route::get('/{creditNote}',           [AccountDocumentController::class, 'showCreditNote'])   ->middleware('permission:accounting.read')   ->name('show');
        Route::get('/{creditNote}/edit',      [AccountDocumentController::class, 'editCreditNote'])   ->middleware('permission:accounting.write')  ->name('edit');
        Route::put('/{creditNote}',           [AccountDocumentController::class, 'updateCreditNote']) ->middleware('permission:accounting.write')  ->name('update');
        Route::patch('/{creditNote}/post',    [AccountDocumentController::class, 'postCreditNote'])   ->middleware('permission:accounting.post')   ->name('post');
        Route::patch('/{creditNote}/pay',     [AccountDocumentController::class, 'payCreditNote'])    ->middleware('permission:accounting.post')   ->name('pay');
        Route::get('/{creditNote}/print',     [AccountDocumentController::class, 'printCreditNote'])  ->middleware('permission:accounting.read')   ->name('print');
        Route::patch('/{creditNote}/draft',   [AccountDocumentController::class, 'resetCreditNote'])  ->middleware('permission:accounting.post')   ->name('reset-draft');
        Route::patch('/{creditNote}/cancel',  [AccountDocumentController::class, 'cancelCreditNote']) ->middleware('permission:accounting.post')   ->name('cancel');
        Route::delete('/{creditNote}',        [AccountDocumentController::class, 'deleteCreditNote']) ->middleware('permission:accounting.unlink') ->name('delete');
        Route::post('/{creditNote}/comment',  [AccountDocumentController::class, 'commentCreditNote'])->middleware('permission:accounting.write')  ->name('comment');
    });

    // Vendor Bills
    Route::prefix('bills')->name('bills.')->group(function () {
        Route::get('/',               [AccountDocumentController::class, 'bills'])      ->middleware('permission:accounting.read')   ->name('index');
        Route::get('/create',         [AccountDocumentController::class, 'createBill']) ->middleware('permission:accounting.create') ->name('create');
        Route::post('/',              [AccountDocumentController::class, 'storeBill'])  ->middleware('permission:accounting.create') ->name('store');
        Route::get('/{bill}',         [AccountDocumentController::class, 'showBill'])   ->middleware('permission:accounting.read')   ->name('show');
        Route::get('/{bill}/edit',    [AccountDocumentController::class, 'editBill'])   ->middleware('permission:accounting.write')  ->name('edit');
        Route::put('/{bill}',         [AccountDocumentController::class, 'updateBill']) ->middleware('permission:accounting.write')  ->name('update');
        Route::patch('/{bill}/post',  [AccountDocumentController::class, 'postBill'])   ->middleware('permission:accounting.post')   ->name('post');
        Route::patch('/{bill}/pay',   [AccountDocumentController::class, 'payBill'])    ->middleware('permission:accounting.post')   ->name('pay');
        Route::post('/{bill}/credit-note', [AccountDocumentController::class, 'creditBill'])->middleware('permission:accounting.post')->name('credit-note');
        Route::get('/{bill}/print',   [AccountDocumentController::class, 'printBill'])  ->middleware('permission:accounting.read')   ->name('print');
        Route::patch('/{bill}/draft', [AccountDocumentController::class, 'resetBill'])  ->middleware('permission:accounting.post')   ->name('reset-draft');
        Route::patch('/{bill}/cancel', [AccountDocumentController::class, 'cancelBill'])->middleware('permission:accounting.post')   ->name('cancel');
        Route::delete('/{bill}',      [AccountDocumentController::class, 'deleteBill']) ->middleware('permission:accounting.unlink') ->name('delete');
        Route::post('/{bill}/comment', [AccountDocumentController::class, 'commentBill'])->middleware('permission:accounting.write')  ->name('comment');
    });

    // Vendor Refunds
    Route::prefix('refunds')->name('refunds.')->group(function () {
        Route::get('/',             [AccountDocumentController::class, 'refunds'])       ->middleware('permission:accounting.read')   ->name('index');
        Route::get('/create',       [AccountDocumentController::class, 'createRefund'])  ->middleware('permission:accounting.create') ->name('create');
        Route::post('/',            [AccountDocumentController::class, 'storeRefund'])   ->middleware('permission:accounting.create') ->name('store');
        Route::get('/{refund}',          [AccountDocumentController::class, 'showRefund'])   ->middleware('permission:accounting.read')   ->name('show');
        Route::get('/{refund}/edit',     [AccountDocumentController::class, 'editRefund'])   ->middleware('permission:accounting.write')  ->name('edit');
        Route::put('/{refund}',          [AccountDocumentController::class, 'updateRefund']) ->middleware('permission:accounting.write')  ->name('update');
        Route::patch('/{refund}/post',   [AccountDocumentController::class, 'postRefund'])   ->middleware('permission:accounting.post')   ->name('post');
        Route::patch('/{refund}/pay',    [AccountDocumentController::class, 'payRefund'])    ->middleware('permission:accounting.post')   ->name('pay');
        Route::get('/{refund}/print',    [AccountDocumentController::class, 'printRefund'])  ->middleware('permission:accounting.read')   ->name('print');
        Route::patch('/{refund}/draft',  [AccountDocumentController::class, 'resetRefund'])  ->middleware('permission:accounting.post')   ->name('reset-draft');
        Route::patch('/{refund}/cancel', [AccountDocumentController::class, 'cancelRefund']) ->middleware('permission:accounting.post')   ->name('cancel');
        Route::delete('/{refund}',       [AccountDocumentController::class, 'deleteRefund']) ->middleware('permission:accounting.unlink') ->name('delete');
        Route::post('/{refund}/comment', [AccountDocumentController::class, 'commentRefund'])->middleware('permission:accounting.write')  ->name('comment');
    });

    // Payments
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/',        [AccountPaymentController::class, 'read'])       ->middleware('permission:accounting.read')   ->name('index');
        Route::get('/create',  [AccountPaymentController::class, 'create'])     ->middleware('permission:accounting.create') ->name('create');
        Route::post('/',       [AccountPaymentController::class, 'store'])      ->middleware('permission:accounting.create') ->name('store');
        Route::get('/{payment}',               [AccountPaymentController::class, 'show'])       ->middleware('permission:accounting.read')   ->name('show');
        // Posting-class actions: confirm posts the underlying account_move,
        // cancel/reset tear it down. All three require accounting.post — not
        // accounting.write — to keep parity with direct AccountMove posting
        // (otherwise accounting.write becomes "post journal entries via the
        // payment funnel", bypassing the accounting.post permission separation).
        Route::patch('/{payment}/confirm',     [AccountPaymentController::class, 'confirm'])    ->middleware('permission:accounting.post')   ->name('confirm');
        Route::patch('/{payment}/reset-draft', [AccountPaymentController::class, 'resetDraft']) ->middleware('permission:accounting.post')   ->name('reset-draft');
        Route::patch('/{payment}/cancel',      [AccountPaymentController::class, 'cancel'])     ->middleware('permission:accounting.post')   ->name('cancel');
        Route::delete('/{payment}',            [AccountPaymentController::class, 'unlink'])     ->middleware('permission:accounting.unlink') ->name('delete');
        Route::post('/{payment}/comment',      [AccountPaymentController::class, 'addComment']) ->middleware('permission:accounting.write')  ->name('comment');
    });

    // Journal Entries (manual moves in Phase 1)
    Route::prefix('moves')->name('moves.')->group(function () {
        Route::get('/',              [AccountMoveController::class, 'read'])     ->middleware('permission:accounting.read')   ->name('index');
        Route::get('/create',        [AccountMoveController::class, 'create'])   ->middleware('permission:accounting.create') ->name('create');
        Route::post('/',             [AccountMoveController::class, 'store'])    ->middleware('permission:accounting.create') ->name('store');
        Route::get('/{move}',              [AccountMoveController::class, 'show'])         ->middleware('permission:accounting.read')   ->name('show');
        Route::get('/{move}/edit',         [AccountMoveController::class, 'edit'])         ->middleware('permission:accounting.write')  ->name('edit');
        Route::put('/{move}',              [AccountMoveController::class, 'write'])        ->middleware('permission:accounting.write')  ->name('update');
        Route::patch('/{move}/post',       [AccountMoveController::class, 'post'])         ->middleware('permission:accounting.post')   ->name('post');
        Route::patch('/{move}/draft',      [AccountMoveController::class, 'resetToDraft']) ->middleware('permission:accounting.post')   ->name('reset-draft');
        Route::patch('/{move}/cancel',     [AccountMoveController::class, 'cancel'])       ->middleware('permission:accounting.post')   ->name('cancel');
        Route::post('/{move}/reverse',     [AccountMoveController::class, 'reverse'])      ->middleware('permission:accounting.post')   ->name('reverse');
        Route::delete('/{move}',           [AccountMoveController::class, 'unlink'])       ->middleware('permission:accounting.unlink') ->name('delete');
        Route::post('/{move}/comment',     [AccountMoveController::class, 'addComment'])   ->middleware('permission:accounting.write')  ->name('comment');
    });

    // Journal Items (move lines)
    Route::prefix('items')->name('items.')->group(function () {
        Route::get('/', [AccountMoveLineController::class, 'read'])
            ->middleware('permission:accounting.read')->name('index');
    });

    // Taxes
    Route::prefix('taxes')->name('taxes.')->group(function () {
        Route::get('/',                [AccountTaxController::class, 'read'])      ->middleware('permission:accounting.read')   ->name('index');
        Route::get('/create',          [AccountTaxController::class, 'create'])    ->middleware('permission:accounting.create') ->name('create');
        Route::post('/',               [AccountTaxController::class, 'store'])     ->middleware('permission:accounting.create') ->name('store');
        Route::get('/{tax}',           [AccountTaxController::class, 'show'])      ->middleware('permission:accounting.read')   ->name('show');
        Route::get('/{tax}/edit',      [AccountTaxController::class, 'edit'])      ->middleware('permission:accounting.write')  ->name('edit');
        Route::put('/{tax}',           [AccountTaxController::class, 'write'])     ->middleware('permission:accounting.write')  ->name('update');
        Route::patch('/{tax}/archive', [AccountTaxController::class, 'archive'])   ->middleware('permission:accounting.write')  ->name('archive');
        Route::patch('/{tax}/unarchive',[AccountTaxController::class, 'unarchive'])->middleware('permission:accounting.write')  ->name('unarchive');
        Route::delete('/{tax}',        [AccountTaxController::class, 'unlink'])    ->middleware('permission:accounting.unlink') ->name('delete');
        Route::post('/{tax}/comment',  [AccountTaxController::class, 'addComment'])->middleware('permission:accounting.write')  ->name('comment');
    });

    // Exchange Rates (multi-currency)
    Route::prefix('currencies')->name('currencies.')->group(function () {
        Route::get('/',                        [CurrencyRateController::class, 'read'])   ->middleware('permission:accounting.read')  ->name('index');
        Route::get('/create',                  [CurrencyRateController::class, 'create']) ->middleware('permission:accounting.write') ->name('create');
        Route::post('/',                       [CurrencyRateController::class, 'store'])  ->middleware('permission:accounting.write') ->name('store');
        Route::get('/{currencyRate}',           [CurrencyRateController::class, 'show'])  ->middleware('permission:accounting.read')  ->name('show');
        Route::get('/{currencyRate}/edit',      [CurrencyRateController::class, 'edit'])  ->middleware('permission:accounting.write') ->name('edit');
        Route::put('/{currencyRate}',           [CurrencyRateController::class, 'write']) ->middleware('permission:accounting.write') ->name('update');
        Route::delete('/{currencyRate}',        [CurrencyRateController::class, 'unlink'])       ->middleware('permission:accounting.write') ->name('delete');
        Route::post('/{currencyRate}/comment',  [CurrencyRateController::class, 'addComment']) ->middleware('permission:accounting.write') ->name('comment');
    });

    // Payment Terms
    Route::prefix('payment-terms')->name('payment-terms.')->group(function () {
        Route::get('/',                       [AccountingPaymentTermController::class, 'read'])      ->middleware('permission:accounting.read')   ->name('index');
        Route::get('/create',                 [AccountingPaymentTermController::class, 'create'])    ->middleware('permission:accounting.create') ->name('create');
        Route::post('/',                      [AccountingPaymentTermController::class, 'store'])     ->middleware('permission:accounting.create') ->name('store');
        Route::get('/{paymentTerm}',          [AccountingPaymentTermController::class, 'show'])      ->middleware('permission:accounting.read')   ->name('show');
        Route::get('/{paymentTerm}/edit',     [AccountingPaymentTermController::class, 'edit'])      ->middleware('permission:accounting.write')  ->name('edit');
        Route::put('/{paymentTerm}',          [AccountingPaymentTermController::class, 'write'])     ->middleware('permission:accounting.write')  ->name('update');
        Route::patch('/{paymentTerm}/archive',   [AccountingPaymentTermController::class, 'archive'])   ->middleware('permission:accounting.write')  ->name('archive');
        Route::patch('/{paymentTerm}/unarchive', [AccountingPaymentTermController::class, 'unarchive']) ->middleware('permission:accounting.write')  ->name('unarchive');
        Route::delete('/{paymentTerm}',        [AccountingPaymentTermController::class, 'unlink'])    ->middleware('permission:accounting.unlink') ->name('delete');
        Route::post('/{paymentTerm}/comment',  [AccountingPaymentTermController::class, 'addComment'])->middleware('permission:accounting.write')  ->name('comment');
    });

    // Incoterms
    Route::prefix('incoterms')->name('incoterms.')->group(function () {
        Route::get('/',                  [AccountingIncotermController::class, 'read'])    ->middleware('permission:accounting.read')   ->name('index');
        Route::get('/create',            [AccountingIncotermController::class, 'create'])  ->middleware('permission:accounting.create') ->name('create');
        Route::post('/',                 [AccountingIncotermController::class, 'store'])   ->middleware('permission:accounting.create') ->name('store');
        Route::get('/{incoterm}',        [AccountingIncotermController::class, 'show'])    ->middleware('permission:accounting.read')   ->name('show');
        Route::get('/{incoterm}/edit',   [AccountingIncotermController::class, 'edit'])    ->middleware('permission:accounting.write')  ->name('edit');
        Route::put('/{incoterm}',        [AccountingIncotermController::class, 'write'])   ->middleware('permission:accounting.write')  ->name('update');
        Route::delete('/{incoterm}',      [AccountingIncotermController::class, 'unlink'])    ->middleware('permission:accounting.unlink') ->name('delete');
        Route::post('/{incoterm}/comment',[AccountingIncotermController::class, 'addComment'])->middleware('permission:accounting.write')  ->name('comment');
    });

    // Tax Groups
    Route::prefix('tax-groups')->name('tax-groups.')->group(function () {
        Route::get('/',              [AccountingTaxGroupController::class, 'read'])    ->middleware('permission:accounting.read')   ->name('index');
        Route::get('/create',        [AccountingTaxGroupController::class, 'create'])  ->middleware('permission:accounting.create') ->name('create');
        Route::post('/',             [AccountingTaxGroupController::class, 'store'])   ->middleware('permission:accounting.create') ->name('store');
        Route::get('/{taxGroup}',        [AccountingTaxGroupController::class, 'show'])    ->middleware('permission:accounting.read')   ->name('show');
        Route::get('/{taxGroup}/edit',   [AccountingTaxGroupController::class, 'edit'])    ->middleware('permission:accounting.write')  ->name('edit');
        Route::put('/{taxGroup}',        [AccountingTaxGroupController::class, 'write'])   ->middleware('permission:accounting.write')  ->name('update');
        Route::delete('/{taxGroup}',      [AccountingTaxGroupController::class, 'unlink'])    ->middleware('permission:accounting.unlink') ->name('delete');
        Route::post('/{taxGroup}/comment',[AccountingTaxGroupController::class, 'addComment'])->middleware('permission:accounting.write')  ->name('comment');
    });

    // Account Groups
    Route::prefix('account-groups')->name('account-groups.')->group(function () {
        Route::get('/',                   [AccountingAccountGroupController::class, 'read'])    ->middleware('permission:accounting.read')   ->name('index');
        Route::get('/create',             [AccountingAccountGroupController::class, 'create'])  ->middleware('permission:accounting.create') ->name('create');
        Route::post('/',                  [AccountingAccountGroupController::class, 'store'])   ->middleware('permission:accounting.create') ->name('store');
        Route::get('/{accountGroup}',        [AccountingAccountGroupController::class, 'show'])    ->middleware('permission:accounting.read')   ->name('show');
        Route::get('/{accountGroup}/edit',   [AccountingAccountGroupController::class, 'edit'])    ->middleware('permission:accounting.write')  ->name('edit');
        Route::put('/{accountGroup}',        [AccountingAccountGroupController::class, 'write'])   ->middleware('permission:accounting.write')  ->name('update');
        Route::delete('/{accountGroup}',      [AccountingAccountGroupController::class, 'unlink'])    ->middleware('permission:accounting.unlink') ->name('delete');
        Route::post('/{accountGroup}/comment',[AccountingAccountGroupController::class, 'addComment'])->middleware('permission:accounting.write')  ->name('comment');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/general-ledger',    [AccountingReportController::class, 'generalLedger'])    ->middleware('permission:accounting.read')->name('general-ledger');
        Route::get('/trial-balance',     [AccountingReportController::class, 'trialBalance'])     ->middleware('permission:accounting.read')->name('trial-balance');
        Route::get('/profit-and-loss',   [AccountingReportController::class, 'profitAndLoss'])    ->middleware('permission:accounting.read')->name('profit-and-loss');
        Route::get('/balance-sheet',     [AccountingReportController::class, 'balanceSheet'])     ->middleware('permission:accounting.read')->name('balance-sheet');
        Route::get('/cash-flow',         [AccountingReportController::class, 'cashFlow'])         ->middleware('permission:accounting.read')->name('cash-flow');
        Route::get('/tax-report',        [AccountingReportController::class, 'taxReport'])        ->middleware('permission:accounting.read')->name('tax-report');
        Route::get('/partner-ledger',    [AccountingReportController::class, 'partnerLedger'])    ->middleware('permission:accounting.read')->name('partner-ledger');
        Route::get('/aged-receivable',   [AccountingReportController::class, 'agedReceivable'])   ->middleware('permission:accounting.read')->name('aged-receivable');
        Route::get('/aged-payable',      [AccountingReportController::class, 'agedPayable'])      ->middleware('permission:accounting.read')->name('aged-payable');
        Route::get('/journal-audit',     [AccountingReportController::class, 'journalAudit'])     ->middleware('permission:accounting.read')->name('journal-audit');
        Route::get('/bank-reconciliation',[AccountingReportController::class, 'bankReconciliation'])->middleware('permission:accounting.read')->name('bank-reconciliation');
        Route::get('/executive-summary', [AccountingReportController::class, 'executiveSummary'])  ->middleware('permission:accounting.read')->name('executive-summary');

        // Unified export endpoint — gated by accounting.export
        Route::get('/{report}/export', AccountingReportExportController::class)
            ->middleware('permission:accounting.export')->name('export');
    });

    // Accounting Settings (lock dates)
    Route::get('/settings',                [AccountingSettingsController::class, 'read'])
        ->middleware('permission:accounting.read')->name('settings');
    Route::put('/settings/{company}',      [AccountingSettingsController::class, 'write'])
        ->middleware('permission:accounting.lock')->name('settings.update');

    // Audit log
    Route::get('/audit', [AccountingAuditController::class, 'read'])
        ->middleware('permission:accounting.read')->name('audit');
});
