<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DebtCreditController;
use App\Http\Controllers\FinancialGoalController;
use App\Http\Controllers\FixedExpenseController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\HouseholdInvitationController;
use App\Http\Controllers\InterHouseholdTransferController;
use App\Http\Controllers\InvestmentAssetController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileQuizController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaxDeductionExportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\Api\AssetPriceController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return view('welcome');
});

// Rotte pubbliche per inviti household
Route::get('/invitations/{token}/register', [HouseholdInvitationController::class, 'showRegisterForm'])
    ->name('household-invitations.register');
Route::post('/invitations/{token}/register', [HouseholdInvitationController::class, 'registerAndAccept'])
    ->name('household-invitations.register.store');
Route::get('/invitations/{token}/accept', [HouseholdInvitationController::class, 'acceptInvitation'])
    ->name('household-invitations.accept');

// Rotte che richiedono autenticazione ma NON household attiva
Route::middleware(['auth', 'verified'])->group(function () {
    // Quiz di profilazione (deve essere completato prima di accedere alle household)
    Route::get('/profile-quiz', [ProfileQuizController::class, 'show'])->name('profile-quiz.show');
    Route::post('/profile-quiz', [ProfileQuizController::class, 'store'])->name('profile-quiz.store');
    
    // Gestione Household (selezione/creazione) - richiedono il quiz completato
    Route::middleware(['profile-completed'])->group(function () {
        Route::get('/households/select', [HouseholdController::class, 'select'])->name('households.select');
        Route::get('/households/create', [HouseholdController::class, 'create'])->name('households.create');
        Route::post('/households', [HouseholdController::class, 'store'])->name('households.store');
        Route::post('/households/{household}/set-active', [HouseholdController::class, 'setActive'])->name('households.set-active');
    });
});

// Rotte che richiedono autenticazione E household attiva
Route::middleware(['auth', 'verified', 'household'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profilo utente
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Modifica impostazioni quiz di profilazione dal profilo
    Route::get('/profile/quiz-settings', [ProfileQuizController::class, 'edit'])->name('profile.quiz-settings.edit');
    Route::patch('/profile/quiz-settings', [ProfileQuizController::class, 'update'])->name('profile.quiz-settings.update');
    Route::post('/profile/revenue-tracking/toggle', [ProfileQuizController::class, 'toggleRevenueTracking'])->name('profile.revenue-tracking.toggle');

    // Gestione Household (dettagli, modifica, membri) - gestisce permessi internamente
    Route::get('/households/{household}', [HouseholdController::class, 'show'])->name('households.show');
    Route::patch('/households/{household}', [HouseholdController::class, 'update'])->name('households.update');
    Route::delete('/households/{household}', [HouseholdController::class, 'destroy'])->name('households.destroy');
    Route::post('/households/{household}/invite', [HouseholdController::class, 'invite'])->name('households.invite');
    Route::delete('/households/{household}/invitations/{invitation}', [HouseholdController::class, 'cancelInvitation'])->name('households.cancel-invitation');
    Route::post('/households/{household}/invitations/{invitation}/resend', [HouseholdController::class, 'resendInvitation'])->name('households.resend-invitation');
    Route::delete('/households/{household}/members/{member}', [HouseholdController::class, 'removeMember'])->name('households.remove-member');
    Route::post('/households/{household}/leave', [HouseholdController::class, 'leave'])->name('households.leave');

    // ===== ROTTE DI MODIFICA (richiedono permessi can-modify, bloccate per ospiti) =====
    Route::middleware(['can-modify'])->group(function () {
        // Attachments - gestione upload (modifica)
        Route::post('/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
        Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

        // Accounts - modifica
        Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
        Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::get('/accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
        Route::patch('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
        Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
        Route::post('/accounts/{account}/toggle-active', [AccountController::class, 'toggleActive'])->name('accounts.toggle-active');

        // Transactions - modifica
        Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
        Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::get('/transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
        Route::patch('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

        // Categories - modifica
        Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        // Transfers - modifica
        Route::get('/transfers/create', [TransferController::class, 'create'])->name('transfers.create');
        Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::delete('/transfers/{transfer}', [TransferController::class, 'destroy'])->name('transfers.destroy');

        // Inter-Household Transfers - modifica
        Route::get('/inter-household-transfers/create', [InterHouseholdTransferController::class, 'create'])->name('inter-household-transfers.create');
        Route::post('/inter-household-transfers', [InterHouseholdTransferController::class, 'store'])->name('inter-household-transfers.store');
        Route::delete('/inter-household-transfers/{interHouseholdTransfer}', [InterHouseholdTransferController::class, 'destroy'])->name('inter-household-transfers.destroy');

        // Refunds - modifica
        Route::get('/refunds/create', [RefundController::class, 'create'])->name('refunds.create');
        Route::get('/refunds/search-transactions', [RefundController::class, 'searchTransactions'])->name('refunds.search-transactions');
        Route::post('/refunds', [RefundController::class, 'store'])->name('refunds.store');
        Route::get('/refunds/{refund}/edit', [RefundController::class, 'edit'])->name('refunds.edit');
        Route::patch('/refunds/{refund}', [RefundController::class, 'update'])->name('refunds.update');
        Route::delete('/refunds/{refund}', [RefundController::class, 'destroy'])->name('refunds.destroy');

        // Tags - modifica
        Route::get('/tags/create', [TagController::class, 'create'])->name('tags.create');
        Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
        Route::get('/tags/{tag}/edit', [TagController::class, 'edit'])->name('tags.edit');
        Route::put('/tags/{tag}', [TagController::class, 'update'])->name('tags.update');
        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

        // Budgets - modifica
        Route::get('/budgets/create', [BudgetController::class, 'create'])->name('budgets.create');
        Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');
        Route::get('/budgets/{budget}/edit', [BudgetController::class, 'edit'])->name('budgets.edit');
        Route::put('/budgets/{budget}', [BudgetController::class, 'update'])->name('budgets.update');
        Route::delete('/budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');

        // Debts & Credits - modifica
        Route::get('/debts-credits/create', [DebtCreditController::class, 'create'])->name('debts-credits.create');
        Route::post('/debts-credits', [DebtCreditController::class, 'store'])->name('debts-credits.store');
        Route::get('/debts-credits/{debts_credit}/edit', [DebtCreditController::class, 'edit'])->name('debts-credits.edit');
        Route::put('/debts-credits/{debts_credit}', [DebtCreditController::class, 'update'])->name('debts-credits.update');
        Route::post('/debts-credits/{debts_credit}/close', [DebtCreditController::class, 'close'])->name('debts-credits.close');
        Route::post('/debts-credits/{debts_credit}/reopen', [DebtCreditController::class, 'reopen'])->name('debts-credits.reopen');
        Route::delete('/debts-credits/{debts_credit}', [DebtCreditController::class, 'destroy'])->name('debts-credits.destroy');

        // Recurring Transactions - modifica
        Route::get('/recurring-transactions/create', [RecurringTransactionController::class, 'create'])->name('recurring-transactions.create');
        Route::post('/recurring-transactions', [RecurringTransactionController::class, 'store'])->name('recurring-transactions.store');
        Route::get('/recurring-transactions/{recurringTransaction}/edit', [RecurringTransactionController::class, 'edit'])->name('recurring-transactions.edit');
        Route::put('/recurring-transactions/{recurringTransaction}', [RecurringTransactionController::class, 'update'])->name('recurring-transactions.update');
        Route::post('/recurring-transactions/{recurringTransaction}/generate', [RecurringTransactionController::class, 'generate'])->name('recurring-transactions.generate');
        Route::delete('/recurring-transactions/{recurringTransaction}', [RecurringTransactionController::class, 'destroy'])->name('recurring-transactions.destroy');

        // Financial Goals - modifica
        Route::get('/financial-goals/create', [FinancialGoalController::class, 'create'])->name('financial-goals.create');
        Route::post('/financial-goals', [FinancialGoalController::class, 'store'])->name('financial-goals.store');
        Route::get('/financial-goals/{financialGoal}/edit', [FinancialGoalController::class, 'edit'])->name('financial-goals.edit');
        Route::put('/financial-goals/{financialGoal}', [FinancialGoalController::class, 'update'])->name('financial-goals.update');
        Route::post('/financial-goals/{financialGoal}/contribute', [FinancialGoalController::class, 'contribute'])->name('financial-goals.contribute');
        Route::put('/financial-goals/{financialGoal}/change-status', [FinancialGoalController::class, 'changeStatus'])->name('financial-goals.change-status');
        Route::delete('/financial-goals/{financialGoal}', [FinancialGoalController::class, 'destroy'])->name('financial-goals.destroy');

        // Investment Assets - modifica
        Route::get('/investment-assets/create', [InvestmentAssetController::class, 'create'])->name('investment-assets.create');
        Route::post('/investment-assets', [InvestmentAssetController::class, 'store'])->name('investment-assets.store');
        Route::get('/investment-assets/{investmentAsset}/edit', [InvestmentAssetController::class, 'edit'])->name('investment-assets.edit');
        Route::put('/investment-assets/{investmentAsset}', [InvestmentAssetController::class, 'update'])->name('investment-assets.update');
        Route::delete('/investment-assets/{investmentAsset}', [InvestmentAssetController::class, 'destroy'])->name('investment-assets.destroy');

        // Investments - modifica
        Route::get('/investments/create', [InvestmentController::class, 'create'])->name('investments.create');
        Route::post('/investments', [InvestmentController::class, 'store'])->name('investments.store');
        Route::get('/investments/{investment}/edit', [InvestmentController::class, 'edit'])->name('investments.edit');
        Route::put('/investments/{investment}', [InvestmentController::class, 'update'])->name('investments.update');
        Route::post('/investments/{investment}/sell', [InvestmentController::class, 'sell'])->name('investments.sell');
        Route::delete('/investments/{investment}', [InvestmentController::class, 'destroy'])->name('investments.destroy');
    });

    // ===== ROTTE DI SOLA LETTURA (accessibili anche agli ospiti) =====
    // NOTA: Queste devono venire DOPO le rotte con /create per evitare conflitti
    
    // Attachments - download (lettura)
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');

    // Tax Deductions - gestione detrazioni fiscali
    Route::get('/tax-deductions', [TaxDeductionExportController::class, 'index'])->name('tax-deductions.index');
    Route::get('/tax-deductions/export-pdf', [TaxDeductionExportController::class, 'exportPdf'])->name('tax-deductions.export-pdf');
    Route::get('/tax-deductions/export-attachments', [TaxDeductionExportController::class, 'exportAttachments'])->name('tax-deductions.export-attachments');

    // Accounts - lettura
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/{account}', [AccountController::class, 'show'])->name('accounts.show');

    // Transactions - lettura
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');

    // Categories - lettura
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');

    // Transfers - lettura
    Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::get('/transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');

    // Inter-Household Transfers - lettura
    Route::get('/inter-household-transfers', [InterHouseholdTransferController::class, 'index'])->name('inter-household-transfers.index');
    Route::get('/inter-household-transfers/{interHouseholdTransfer}', [InterHouseholdTransferController::class, 'show'])->name('inter-household-transfers.show');
    Route::get('/households/{household}/accounts', [InterHouseholdTransferController::class, 'getHouseholdAccounts'])->name('households.accounts');

    // Refunds - lettura
    Route::get('/refunds', [RefundController::class, 'index'])->name('refunds.index');
    Route::get('/refunds/{refund}', [RefundController::class, 'show'])->name('refunds.show');

    // Tags - lettura
    Route::get('/tags', [TagController::class, 'index'])->name('tags.index');

    // Budgets - lettura
    Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::get('/budgets/{budget}', [BudgetController::class, 'show'])->name('budgets.show');

    // Debts & Credits - lettura
    Route::get('/debts-credits', [DebtCreditController::class, 'index'])->name('debts-credits.index');
    Route::get('/debts-credits/{debts_credit}', [DebtCreditController::class, 'show'])->name('debts-credits.show');

    // Recurring Transactions - lettura
    Route::get('/recurring-transactions', [RecurringTransactionController::class, 'index'])->name('recurring-transactions.index');
    Route::get('/recurring-transactions/{recurringTransaction}', [RecurringTransactionController::class, 'show'])->name('recurring-transactions.show');

    // Financial Goals - lettura
    Route::get('/financial-goals', [FinancialGoalController::class, 'index'])->name('financial-goals.index');
    Route::get('/financial-goals/{financialGoal}', [FinancialGoalController::class, 'show'])->name('financial-goals.show');

    // Investment Assets - lettura
    Route::get('/investment-assets', [InvestmentAssetController::class, 'index'])->name('investment-assets.index');

    // Investments - lettura
    Route::get('/investments', [InvestmentController::class, 'index'])->name('investments.index');
    Route::get('/investments/{investment}', [InvestmentController::class, 'show'])->name('investments.show');

    // Fixed Expenses - lettura (disponibile per tutte le household con bilanciamento debiti)
    Route::get('/households/{household}/fixed-expenses', [FixedExpenseController::class, 'dashboard'])->name('fixed-expenses.dashboard');
    Route::get('/households/{household}/fixed-expenses/contributions', [FixedExpenseController::class, 'getContributions'])->name('fixed-expenses.contributions');
    Route::get('/households/{household}/categories/{category}/suggest-turn', [FixedExpenseController::class, 'suggestTurn'])->name('fixed-expenses.suggest-turn');
    
    // Fixed Expenses - modifica (solo owner può modificare impostazioni turni)
    Route::middleware(['can-modify'])->group(function () {
        Route::post('/households/{household}/categories/{category}/complete-turn', [FixedExpenseController::class, 'completeTurn'])->name('fixed-expenses.complete-turn');
        Route::patch('/households/{household}/turn-settings', [FixedExpenseController::class, 'updateTurnSettings'])->name('fixed-expenses.update-turn-settings');
    });

    // ===== API INTERNE (per AJAX/fetch dal frontend) =====
    Route::prefix('api/assets')->name('api.assets.')->group(function () {
        Route::get('/status', [AssetPriceController::class, 'status'])->name('status');
        Route::get('/search', [AssetPriceController::class, 'search'])->name('search');
        Route::get('/price/{symbol}', [AssetPriceController::class, 'currentPrice'])->name('price');
        Route::get('/price/{symbol}/history', [AssetPriceController::class, 'historicalPrice'])->name('price.history');
        Route::get('/ticker-to-isin/{ticker}', [AssetPriceController::class, 'tickerToIsin'])->name('ticker-to-isin');
        Route::get('/isin-to-ticker/{isin}', [AssetPriceController::class, 'isinToTicker'])->name('isin-to-ticker');
    });
});

require __DIR__.'/auth.php';
