<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\AssetPriceController;
use App\Http\Controllers\AssetAllocationController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BalancePrivacyPreferenceController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardBoardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardLayoutController;
use App\Http\Controllers\DebtCreditController;
use App\Http\Controllers\DuplicateTransactionCandidateController;
use App\Http\Controllers\ExpenseDistributionController;
use App\Http\Controllers\FinancialGoalController;
use App\Http\Controllers\FinancialVariableController;
use App\Http\Controllers\FixedExpenseController;
use App\Http\Controllers\FormulaMarketplaceController;
use App\Http\Controllers\FormulaWidgetController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\InterHouseholdTransferController;
use App\Http\Controllers\InvestmentAnalysisController;
use App\Http\Controllers\InvestmentAssetController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\InvestmentImportController;
use App\Http\Controllers\InvestmentPacController;
use App\Http\Controllers\LifestyleScoreController;
use App\Http\Controllers\MobileBottomNavPreferenceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\PatrimonioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileQuizController;
use App\Http\Controllers\RecurrenceDetectionController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\SimulationScenarioController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaxDeductionExportController;
use App\Http\Controllers\TelegramLinkController;
use App\Http\Controllers\ThemePreferenceController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionExportController;
use App\Http\Controllers\TransactionImportController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

// Rotte che richiedono autenticazione ma NON household attiva
Route::middleware(['auth', 'verified', 'pre-launch'])->group(function () {
    // Quiz di profilazione (deve essere completato prima di accedere alle household)
    Route::get('/quiz-profilazione', [ProfileQuizController::class, 'show'])->name('profile-quiz.show');
    Route::post('/quiz-profilazione', [ProfileQuizController::class, 'store'])->name('profile-quiz.store');

    // Gestione Household (selezione/creazione) - richiedono il quiz completato
    Route::middleware(['profile-completed'])->group(function () {
        Route::get('/nuclei/seleziona', [HouseholdController::class, 'select'])->name('households.select');
        Route::get('/nuclei/crea', [HouseholdController::class, 'create'])->name('households.create');
        Route::post('/nuclei', [HouseholdController::class, 'store'])->name('households.store');
        Route::post('/nuclei/{household}/imposta-attivo', [HouseholdController::class, 'setActive'])->name('households.set-active');
    });

    // Abbonamento e fatturazione — accessibili senza household attiva
    Route::get('/profilo/abbonamento', [SubscriptionController::class, 'show'])->name('profile.subscription');
    Route::post('/abbonamento/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::get('/abbonamento/{subscription}/ritorno', [SubscriptionController::class, 'return'])->name('subscription.return');
    Route::post('/abbonamento/annulla', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
    Route::post('/abbonamento/aggiorna-metodo-pagamento', [SubscriptionController::class, 'updatePaymentMethod'])->name('subscription.update-payment-method');
    Route::get('/abbonamento/{subscription}/metodo-pagamento/ritorno', [SubscriptionController::class, 'paymentMethodReturn'])->name('subscription.payment-method.return');
    Route::patch('/abbonamento/fatturazione', [SubscriptionController::class, 'updateBilling'])->name('subscription.billing.update');
});

// Rotte che richiedono autenticazione E household attiva
Route::middleware(['auth', 'verified', 'pre-launch', 'household'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/patrimonio', [PatrimonioController::class, 'index'])->name('patrimonio.index');

    Route::post('/simulazioni/scenari', [SimulationScenarioController::class, 'store'])->name('simulation-scenarios.store');
    Route::put('/simulazioni/scenari/{saved_simulation_scenario}', [SimulationScenarioController::class, 'update'])->name('simulation-scenarios.update');
    Route::delete('/simulazioni/scenari/{saved_simulation_scenario}', [SimulationScenarioController::class, 'destroy'])->name('simulation-scenarios.destroy');

    Route::get('/analisi/patrimonio', [AnalyticsController::class, 'netWorth'])->name('analytics.net-worth');
    Route::get('/analisi/cashflow', [AnalyticsController::class, 'cashFlow'])->name('analytics.cash-flow');
    Route::get('/analisi/spese-categoria', [AnalyticsController::class, 'expensesByCategory'])->name('analytics.expenses-by-category');
    Route::get('/dashboard/layout', [DashboardLayoutController::class, 'show'])->name('dashboard.layout.show');
    Route::post('/dashboard/layout', [DashboardLayoutController::class, 'store'])->name('dashboard.layout.store');
    Route::delete('/dashboard/layout', [DashboardLayoutController::class, 'reset'])->name('dashboard.layout.reset');
    Route::get('/dashboard/boards', [DashboardBoardController::class, 'index'])->name('dashboard.boards.index');
    Route::post('/dashboard/boards', [DashboardBoardController::class, 'store'])->name('dashboard.boards.store');
    Route::patch('/dashboard/boards/{dashboard_layout}', [DashboardBoardController::class, 'update'])->name('dashboard.boards.update');
    Route::delete('/dashboard/boards/{dashboard_layout}', [DashboardBoardController::class, 'destroy'])->name('dashboard.boards.destroy');
    Route::post('/dashboard/boards/{dashboard_layout}/home', [DashboardBoardController::class, 'setHome'])->name('dashboard.boards.set-home');
    Route::get('/dashboard/formula-widget-payloads', [DashboardController::class, 'formulaWidgetPayloads'])->name('dashboard.formula-widget-payloads');
    Route::get('/dashboard/deferred-widgets', [DashboardController::class, 'deferredWidgets'])->name('dashboard.deferred-widgets');

    Route::get('/widget-formule', [FormulaWidgetController::class, 'index'])->name('formula-widgets.index');
    Route::get('/widget-formule/crea', [FormulaWidgetController::class, 'create'])->name('formula-widgets.create');
    Route::post('/widget-formule', [FormulaWidgetController::class, 'store'])->name('formula-widgets.store');
    Route::get('/widget-formule/{formula_widget}/modifica', [FormulaWidgetController::class, 'edit'])->name('formula-widgets.edit');
    Route::put('/widget-formule/{formula_widget}', [FormulaWidgetController::class, 'update'])->name('formula-widgets.update');
    Route::post('/widget-formule/anteprima', [FormulaWidgetController::class, 'preview'])->name('formula-widgets.preview');
    Route::delete('/widget-formule/{formula_widget}', [FormulaWidgetController::class, 'destroy'])->name('formula-widgets.destroy');
    Route::post('/widget-formule/{formula_widget}/ripristina', [FormulaWidgetController::class, 'restore'])->name('formula-widgets.restore');
    Route::get('/widget-formule/{formula_widget}/aggiungi', [FormulaWidgetController::class, 'choosePinBoard'])->name('formula-widgets.pin.choose');
    Route::post('/widget-formule/{formula_widget}/pin', [FormulaWidgetController::class, 'pin'])->name('formula-widgets.pin');

    Route::get('/widget-formule/variabili', [FinancialVariableController::class, 'index'])->name('formula-variables.index');
    Route::post('/widget-formule/variabili', [FinancialVariableController::class, 'store'])->name('formula-variables.store');
    Route::post('/widget-formule/variabili/assicurati', [FinancialVariableController::class, 'ensure'])->name('formula-variables.ensure');
    Route::patch('/widget-formule/variabili/{financial_variable}', [FinancialVariableController::class, 'update'])->name('formula-variables.update');
    Route::delete('/widget-formule/variabili/{financial_variable}', [FinancialVariableController::class, 'destroy'])->name('formula-variables.destroy');

    Route::get('/widget-formule/galleria', [FormulaMarketplaceController::class, 'index'])->name('formula-marketplace.index');
    Route::post('/widget-formule/galleria/anteprima', [FormulaMarketplaceController::class, 'preview'])->name('formula-marketplace.preview');
    Route::post('/widget-formule/galleria/template/{templateSlug}', [FormulaMarketplaceController::class, 'installTemplate'])->name('formula-marketplace.install-template');
    Route::delete('/widget-formule/galleria/template/{templateSlug}', [FormulaMarketplaceController::class, 'uninstallTemplate'])->name('formula-marketplace.uninstall-template');
    Route::post('/widget-formule/galleria/widget/{formula_widget}', [FormulaMarketplaceController::class, 'installWidget'])->name('formula-marketplace.install-widget');
    Route::delete('/widget-formule/galleria/widget/{formula_widget}', [FormulaMarketplaceController::class, 'uninstallWidget'])->name('formula-marketplace.uninstall-widget');
    Route::put('/dashboard/distribuzione-spese/soglie', [ExpenseDistributionController::class, 'updateThresholds'])->name('expense-distribution.thresholds.update');
    Route::delete('/dashboard/distribuzione-spese/soglie', [ExpenseDistributionController::class, 'resetThresholds'])->name('expense-distribution.thresholds.reset');

    // Notifiche in-app
    Route::post('/notifiche/{notification}/segna-letto', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifiche/segna-tutte-lette', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifiche/header', [NotificationController::class, 'header'])->name('notifications.header');

    // ===== ROTTE PRO — Inbox, Telegram =====
    Route::middleware(['requires-pro'])->group(function () {
        // Inbox / Staging Area (voci da Telegram o manuali, in attesa di revisione)
        Route::get('/posta-in-arrivo', [InboxController::class, 'index'])->name('inbox.index');
        Route::put('/posta-in-arrivo/{inboxItem}', [InboxController::class, 'update'])->name('inbox.update');
        Route::post('/posta-in-arrivo/{inboxItem}/conferma', [InboxController::class, 'confirm'])->name('inbox.confirm');
        Route::post('/posta-in-arrivo/{inboxItem}/rifiuta', [InboxController::class, 'reject'])->name('inbox.reject');
        Route::post('/posta-in-arrivo/unisci', [InboxController::class, 'merge'])->name('inbox.merge');
        Route::post('/posta-in-arrivo/conferma-separate', [InboxController::class, 'confirmSeparate'])->name('inbox.confirm-separate');
        Route::post('/posta-in-arrivo/conferma-tutte', [InboxController::class, 'confirmAll'])->name('inbox.confirm-all');
        Route::post('/posta-in-arrivo/rifiuta-tutte', [InboxController::class, 'rejectAll'])->name('inbox.reject-all');
        Route::delete('/posta-in-arrivo/{inboxItem}', [InboxController::class, 'destroy'])->name('inbox.destroy');
        Route::get('/posta-in-arrivo/{inboxItem}/immagine', [InboxController::class, 'image'])->name('inbox.image');

        // Collegamento account Telegram
        Route::get('/telegram/collegamento', [TelegramLinkController::class, 'show'])->name('telegram.link.show');
        Route::post('/telegram/collegamento/genera', [TelegramLinkController::class, 'generate'])->name('telegram.link.generate');
        Route::delete('/telegram/collegamento', [TelegramLinkController::class, 'unlink'])->name('telegram.link.unlink');
    });

    // Profilo utente
    Route::get('/profilo', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profilo', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profilo/consensi', [ProfileController::class, 'updateConsents'])->name('profile.consents.update');
    Route::post('/profilo/consensi/sync-analytics', [ProfileController::class, 'syncAnalyticsConsent'])->name('profile.consents.sync-analytics');
    Route::post('/profilo/consensi/revoca-opzionali', [ProfileController::class, 'revokeOptionalConsents'])->name('profile.consents.revoke-optional');
    Route::get('/profilo/consensi/export', [ProfileController::class, 'exportConsents'])->name('profile.consents.export');
    Route::get('/profilo/export-dati', [ProfileController::class, 'exportData'])
        ->middleware(['password.confirm', 'adv-throttle:3,10'])
        ->name('profile.data.export');
    Route::delete('/profilo', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware(['password.confirm'])->group(function () {
        Route::get('/profilo/sicurezza/mfa/abilita', [TwoFactorAuthenticationController::class, 'enable'])->name('profile.two-factor.enable');
        Route::post('/profilo/sicurezza/mfa/conferma', [TwoFactorAuthenticationController::class, 'confirm'])->name('profile.two-factor.confirm');
        Route::post('/profilo/sicurezza/mfa/disabilita', [TwoFactorAuthenticationController::class, 'disable'])->name('profile.two-factor.disable');
        Route::post('/profilo/sicurezza/mfa/codici-recupero', [TwoFactorAuthenticationController::class, 'regenerateRecoveryCodes'])->name('profile.two-factor.recovery-codes');
    });

    // Preferenze tema utente
    Route::patch('/utente/preferenze/tema', [ThemePreferenceController::class, 'update'])->name('user.preferences.theme');
    Route::patch('/utente/preferenze/saldi', [BalancePrivacyPreferenceController::class, 'update'])->name('user.preferences.hide_balances');
    Route::patch('/utente/preferenze/notifiche', [NotificationPreferenceController::class, 'update'])->name('user.preferences.notifications');
    Route::patch('/utente/preferenze/nav-mobile', [MobileBottomNavPreferenceController::class, 'update'])->name('user.preferences.mobile_bottom_nav');

    // Modifica impostazioni quiz di profilazione dal profilo
    Route::get('/profilo/impostazioni-quiz', [ProfileQuizController::class, 'edit'])->name('profile.quiz-settings.edit');
    Route::patch('/profilo/impostazioni-quiz', [ProfileQuizController::class, 'update'])->name('profile.quiz-settings.update');

    // Gestione Household (dettagli, modifica, membri) - gestisce permessi internamente
    Route::get('/nuclei/{household}', [HouseholdController::class, 'show'])->name('households.show');
    Route::patch('/nuclei/{household}', [HouseholdController::class, 'update'])->name('households.update');
    Route::delete('/nuclei/{household}', [HouseholdController::class, 'destroy'])->name('households.destroy');
    // Household invite/membri — solo Pro
    Route::middleware(['requires-pro'])->group(function () {
        Route::post('/nuclei/{household}/invita', [HouseholdController::class, 'invite'])->name('households.invite');
        Route::delete('/nuclei/{household}/inviti/{invitation}', [HouseholdController::class, 'cancelInvitation'])->name('households.cancel-invitation');
        Route::post('/nuclei/{household}/inviti/{invitation}/reinvia', [HouseholdController::class, 'resendInvitation'])->name('households.resend-invitation');
        Route::delete('/nuclei/{household}/membri/{member}', [HouseholdController::class, 'removeMember'])->name('households.remove-member');
    });
    Route::post('/nuclei/{household}/abbandona', [HouseholdController::class, 'leave'])->name('households.leave');

    // ===== ROTTE DI MODIFICA (richiedono permessi can-modify, bloccate per ospiti) =====
    Route::middleware(['can-modify'])->group(function () {
        // Attachments - gestione upload (modifica)
        Route::post('/allegati', [AttachmentController::class, 'store'])->name('attachments.store');
        Route::delete('/allegati/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

        // Accounts - modifica
        Route::get('/conti/crea', [AccountController::class, 'create'])->name('accounts.create');
        Route::post('/conti', [AccountController::class, 'store'])->name('accounts.store');
        Route::get('/conti/{account}/modifica', [AccountController::class, 'edit'])->name('accounts.edit');
        Route::patch('/conti/{account}', [AccountController::class, 'update'])->name('accounts.update');
        Route::delete('/conti/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
        Route::post('/conti/{account}/attiva-disattiva', [AccountController::class, 'toggleActive'])->name('accounts.toggle-active');
        Route::post('/conti/{account}/valore-ticket', [AccountController::class, 'storeUnitValue'])->name('accounts.meal-voucher-unit-value.store');

        // Transactions - modifica
        Route::get('/transazioni/anteprima-cambio', [TransactionController::class, 'fxPreview'])->name('transactions.fx-preview');
        Route::get('/transazioni/crea', [TransactionController::class, 'create'])->name('transactions.create');
        Route::post('/transazioni', [TransactionController::class, 'store'])->name('transactions.store');
        Route::delete('/transazioni/in-blocco', [TransactionController::class, 'bulkDestroy'])->name('transactions.bulk-destroy');
        Route::patch('/transazioni/in-blocco', [TransactionController::class, 'bulkUpdate'])->name('transactions.bulk-update');
        Route::get('/transazioni/duplicati', [DuplicateTransactionCandidateController::class, 'index'])->name('transactions.duplicates.index');
        Route::post('/transazioni/duplicati/ricontrolla', [DuplicateTransactionCandidateController::class, 'detect'])->name('transactions.duplicates.detect');
        Route::post('/transazioni/duplicati/risolvi-ricorrenze', [DuplicateTransactionCandidateController::class, 'resolveAllRecurring'])->name('transactions.duplicates.resolve-all-recurring');
        Route::post('/transazioni/duplicati/{candidate}/non-duplicato', [DuplicateTransactionCandidateController::class, 'dismiss'])->name('transactions.duplicates.dismiss');
        Route::post('/transazioni/duplicati/{candidate}/mantieni-ricorrenza', [DuplicateTransactionCandidateController::class, 'keepRecurring'])->name('transactions.duplicates.keep-recurring');
        Route::post('/transazioni/duplicati/{candidate}/elimina', [DuplicateTransactionCandidateController::class, 'remove'])->name('transactions.duplicates.remove');
        Route::get('/transazioni/{transaction}/modifica', [TransactionController::class, 'edit'])->name('transactions.edit');
        Route::patch('/transazioni/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
        Route::delete('/transazioni/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

        // Transaction Import
        Route::get('/transazioni/importa', [TransactionImportController::class, 'create'])->name('transactions.import');
        Route::get('/transazioni/importa/stato', [TransactionImportController::class, 'importStatus'])->name('transactions.import.status');
        Route::post('/transazioni/importa/anteprima', [TransactionImportController::class, 'preview'])->name('transactions.import.preview');
        Route::post('/transazioni/importa/fogli', [TransactionImportController::class, 'sheets'])->name('transactions.import.sheets');
        Route::post('/transazioni/importa/controlla-duplicati', [TransactionImportController::class, 'checkDuplicates'])->name('transactions.import.check-duplicates');
        Route::post('/transazioni/importa', [TransactionImportController::class, 'store'])->name('transactions.import.store');

        // Bank Import Layouts
        Route::get('/layout-banca', [TransactionImportController::class, 'layouts'])->name('bank-import-layouts.index');
        Route::post('/layout-banca', [TransactionImportController::class, 'storeLayout'])->name('bank-import-layouts.store');
        Route::patch('/layout-banca/{bankImportLayout}', [TransactionImportController::class, 'updateLayout'])->name('bank-import-layouts.update');
        Route::delete('/layout-banca/{bankImportLayout}', [TransactionImportController::class, 'destroyLayout'])->name('bank-import-layouts.destroy');

        // Categories - modifica
        Route::get('/categorie/crea', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categorie', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categorie/{category}/modifica', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::patch('/categorie/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categorie/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        Route::post('/categorie/{category}/punteggio-stile-vita', [CategoryController::class, 'toggleLifestyleScore'])->name('categories.toggle-lifestyle-score');

        // Transfers - modifica
        Route::get('/trasferimenti/crea', [TransferController::class, 'create'])->name('transfers.create');
        Route::post('/trasferimenti', [TransferController::class, 'store'])->name('transfers.store');
        Route::delete('/trasferimenti/{transfer}', [TransferController::class, 'destroy'])->name('transfers.destroy');

        // Refunds - modifica
        Route::get('/rimborsi/crea', [RefundController::class, 'create'])->name('refunds.create');
        Route::get('/rimborsi/cerca-transazioni', [RefundController::class, 'searchTransactions'])->name('refunds.search-transactions');
        Route::post('/rimborsi', [RefundController::class, 'store'])->name('refunds.store');
        Route::get('/rimborsi/{refund}/modifica', [RefundController::class, 'edit'])->name('refunds.edit');
        Route::patch('/rimborsi/{refund}', [RefundController::class, 'update'])->name('refunds.update');
        Route::delete('/rimborsi/{refund}', [RefundController::class, 'destroy'])->name('refunds.destroy');

        // Tags - modifica
        Route::get('/etichette/crea', [TagController::class, 'create'])->name('tags.create');
        Route::post('/etichette', [TagController::class, 'store'])->name('tags.store');
        Route::get('/etichette/{tag}/modifica', [TagController::class, 'edit'])->name('tags.edit');
        Route::put('/etichette/{tag}', [TagController::class, 'update'])->name('tags.update');
        Route::delete('/etichette/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

        // Budgets - modifica
        Route::get('/budget/crea', [BudgetController::class, 'create'])->name('budgets.create');
        Route::post('/budget', [BudgetController::class, 'store'])->name('budgets.store');
        Route::get('/budget/{budget}/modifica', [BudgetController::class, 'edit'])->name('budgets.edit');
        Route::put('/budget/{budget}', [BudgetController::class, 'update'])->name('budgets.update');
        Route::delete('/budget/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');

        // Debts & Credits - modifica
        Route::get('/debiti-crediti/crea', [DebtCreditController::class, 'create'])->name('debts-credits.create');
        Route::post('/debiti-crediti', [DebtCreditController::class, 'store'])->name('debts-credits.store');
        Route::get('/debiti-crediti/{debts_credit}/modifica', [DebtCreditController::class, 'edit'])->name('debts-credits.edit');
        Route::put('/debiti-crediti/{debts_credit}', [DebtCreditController::class, 'update'])->name('debts-credits.update');
        Route::post('/debiti-crediti/{debts_credit}/chiudi', [DebtCreditController::class, 'close'])->name('debts-credits.close');
        Route::post('/debiti-crediti/{debts_credit}/riapri', [DebtCreditController::class, 'reopen'])->name('debts-credits.reopen');
        Route::post('/debiti-crediti/{debts_credit}/adjustments', [DebtCreditController::class, 'addAdjustment'])->name('debts-credits.adjustments.store');
        Route::delete('/debiti-crediti/{debts_credit}', [DebtCreditController::class, 'destroy'])->name('debts-credits.destroy');

        // Recurrence Detection - avvia rilevamento e gestione suggerimenti
        Route::post('/rilevamento-ricorrenze/avvia', [RecurrenceDetectionController::class, 'detect'])->name('recurrence-detection.detect');
        Route::post('/rilevamento-ricorrenze/{suggestion}/accetta', [RecurrenceDetectionController::class, 'accept'])->name('recurrence-detection.accept');
        Route::post('/rilevamento-ricorrenze/{suggestion}/ignora', [RecurrenceDetectionController::class, 'ignore'])->name('recurrence-detection.ignore');

        // Recurring Transactions - modifica
        Route::get('/transazioni-ricorrenti/crea', [RecurringTransactionController::class, 'create'])->name('recurring-transactions.create');
        Route::post('/transazioni-ricorrenti', [RecurringTransactionController::class, 'store'])->name('recurring-transactions.store');
        Route::get('/transazioni-ricorrenti/{recurringTransaction}/modifica', [RecurringTransactionController::class, 'edit'])->name('recurring-transactions.edit');
        Route::put('/transazioni-ricorrenti/{recurringTransaction}', [RecurringTransactionController::class, 'update'])->name('recurring-transactions.update');
        Route::post('/transazioni-ricorrenti/{recurringTransaction}/genera', [RecurringTransactionController::class, 'generate'])->name('recurring-transactions.generate');
        Route::delete('/transazioni-ricorrenti/{recurringTransaction}', [RecurringTransactionController::class, 'destroy'])->name('recurring-transactions.destroy');

        // Financial Goals - modifica
        Route::get('/obiettivi-finanziari/crea', [FinancialGoalController::class, 'create'])->name('financial-goals.create');
        Route::post('/obiettivi-finanziari', [FinancialGoalController::class, 'store'])->name('financial-goals.store');
        Route::get('/obiettivi-finanziari/{financialGoal}/modifica', [FinancialGoalController::class, 'edit'])->name('financial-goals.edit');
        Route::put('/obiettivi-finanziari/{financialGoal}', [FinancialGoalController::class, 'update'])->name('financial-goals.update');
        Route::post('/obiettivi-finanziari/{financialGoal}/contribuisci', [FinancialGoalController::class, 'contribute'])->name('financial-goals.contribute');
        Route::put('/obiettivi-finanziari/{financialGoal}/cambia-stato', [FinancialGoalController::class, 'changeStatus'])->name('financial-goals.change-status');
        Route::delete('/obiettivi-finanziari/{financialGoal}', [FinancialGoalController::class, 'destroy'])->name('financial-goals.destroy');

        // ===== ROTTE PRO (require piano Pro) =====
        Route::middleware(['requires-pro'])->group(function () {
            // Inter-Household Transfers - modifica
            Route::get('/trasferimenti-tra-nuclei/crea', [InterHouseholdTransferController::class, 'create'])->name('inter-household-transfers.create');
            Route::post('/trasferimenti-tra-nuclei', [InterHouseholdTransferController::class, 'store'])->name('inter-household-transfers.store');
            Route::delete('/trasferimenti-tra-nuclei/{interHouseholdTransfer}', [InterHouseholdTransferController::class, 'destroy'])->name('inter-household-transfers.destroy');

            // Investment Analyses - modifica
            Route::post('/analisi-investimenti', [InvestmentAnalysisController::class, 'store'])->name('investment-analyses.store');
            Route::delete('/analisi-investimenti/{investmentAnalysis}', [InvestmentAnalysisController::class, 'destroy'])->name('investment-analyses.destroy');

            // Investment Assets - modifica
            Route::get('/asset-investimento/crea', [InvestmentAssetController::class, 'create'])->name('investment-assets.create');
            Route::post('/asset-investimento', [InvestmentAssetController::class, 'store'])->name('investment-assets.store');
            Route::get('/asset-investimento/{investmentAsset}/modifica', [InvestmentAssetController::class, 'edit'])->name('investment-assets.edit');
            Route::put('/asset-investimento/{investmentAsset}', [InvestmentAssetController::class, 'update'])->name('investment-assets.update');
            Route::delete('/asset-investimento/{investmentAsset}', [InvestmentAssetController::class, 'destroy'])->name('investment-assets.destroy');

            // Investments - modifica
            Route::get('/investimenti/importa', [InvestmentImportController::class, 'create'])->name('investments.import');
            Route::post('/investimenti/importa/anteprima', [InvestmentImportController::class, 'preview'])->name('investments.import.preview');
            Route::post('/investimenti/importa/fogli', [InvestmentImportController::class, 'sheets'])->name('investments.import.sheets');
            Route::post('/investimenti/importa', [InvestmentImportController::class, 'store'])->name('investments.import.store');
            Route::get('/investimenti/importa/layout', [InvestmentImportController::class, 'layouts'])->name('investments.import.layouts');
            Route::post('/investimenti/importa/layout', [InvestmentImportController::class, 'storeLayout'])->name('investments.import.layouts.store');
            Route::patch('/investimenti/importa/layout/{bankImportLayout}', [InvestmentImportController::class, 'updateLayout'])->name('investments.import.layouts.update');
            Route::delete('/investimenti/importa/layout/{bankImportLayout}', [InvestmentImportController::class, 'destroyLayout'])->name('investments.import.layouts.destroy');
            Route::get('/investimenti/crea', [InvestmentController::class, 'create'])->name('investments.create');
            Route::post('/investimenti', [InvestmentController::class, 'store'])->name('investments.store');
            Route::get('/investimenti/pac', [InvestmentPacController::class, 'index'])->name('investment-pacs.index');
            Route::get('/investimenti/pac/crea', [InvestmentPacController::class, 'create'])->name('investment-pacs.create');
            Route::post('/investimenti/pac', [InvestmentPacController::class, 'store'])->name('investment-pacs.store');
            Route::get('/investimenti/pac/{investmentPac}/modifica', [InvestmentPacController::class, 'edit'])->name('investment-pacs.edit');
            Route::put('/investimenti/pac/{investmentPac}', [InvestmentPacController::class, 'update'])->name('investment-pacs.update');
            Route::post('/investimenti/pac/{investmentPac}/attiva-disattiva', [InvestmentPacController::class, 'toggleStatus'])->name('investment-pacs.toggle-status');
            Route::post('/investimenti/pac/{investmentPac}/esegui-ora', [InvestmentPacController::class, 'runNow'])->name('investment-pacs.run-now');
            Route::delete('/investimenti/pac/{investmentPac}', [InvestmentPacController::class, 'destroy'])->name('investment-pacs.destroy');
            Route::get('/investimenti/{investment}/modifica', [InvestmentController::class, 'edit'])->name('investments.edit');
            Route::put('/investimenti/{investment}', [InvestmentController::class, 'update'])->name('investments.update');
            Route::post('/investimenti/{investment}/vendi', [InvestmentController::class, 'sell'])->name('investments.sell');
            Route::delete('/investimenti/{investment}', [InvestmentController::class, 'destroy'])->name('investments.destroy');
        }); // fine requires-pro
    }); // fine can-modify

    // ===== ROTTE DI SOLA LETTURA =====
    // NOTA: Queste devono venire DOPO le rotte con /create per evitare conflitti

    // Attachments - download (lettura)
    Route::get('/allegati/{attachment}/scarica', [AttachmentController::class, 'download'])->name('attachments.download');

    // ===== ROTTE BASE — lettura =====
    Route::get('/conti', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/conti/{account}', [AccountController::class, 'show'])->name('accounts.show');

    Route::get('/transazioni', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transazioni/esporta', [TransactionExportController::class, 'export'])->name('transactions.export');
    Route::get('/transazioni/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');

    Route::get('/categorie', [CategoryController::class, 'index'])->name('categories.index');

    Route::get('/trasferimenti', [TransferController::class, 'index'])->name('transfers.index');
    Route::get('/trasferimenti/{transfer}', [TransferController::class, 'show'])->name('transfers.show');

    Route::get('/etichette', [TagController::class, 'index'])->name('tags.index');
    Route::get('/etichette/cerca', [TagController::class, 'search'])->name('tags.search');
    Route::get('/etichette/{tag}', [TagController::class, 'show'])->name('tags.show');

    Route::get('/budget', [BudgetController::class, 'index'])->name('budgets.index');
    Route::get('/budget/{budget}', [BudgetController::class, 'show'])->name('budgets.show');

    Route::get('/rimborsi', [RefundController::class, 'index'])->name('refunds.index');
    Route::get('/rimborsi/{refund}', [RefundController::class, 'show'])->name('refunds.show');

    Route::get('/rilevamento-ricorrenze', [RecurrenceDetectionController::class, 'index'])->name('recurrence-detection.index');

    Route::get('/transazioni-ricorrenti', [RecurringTransactionController::class, 'index'])->name('recurring-transactions.index');
    Route::get('/transazioni-ricorrenti/{recurringTransaction}', [RecurringTransactionController::class, 'show'])->name('recurring-transactions.show');

    Route::get('/debiti-crediti', [DebtCreditController::class, 'index'])->name('debts-credits.index');
    Route::get('/debiti-crediti/{debts_credit}', [DebtCreditController::class, 'show'])->name('debts-credits.show');

    Route::get('/obiettivi-finanziari', [FinancialGoalController::class, 'index'])->name('financial-goals.index');
    Route::get('/obiettivi-finanziari/{financialGoal}', [FinancialGoalController::class, 'show'])->name('financial-goals.show');

    // ===== ROTTE PRO — lettura =====
    Route::middleware(['requires-pro'])->group(function () {
        Route::get('/trasferimenti-tra-nuclei', [InterHouseholdTransferController::class, 'index'])->name('inter-household-transfers.index');
        Route::get('/trasferimenti-tra-nuclei/{interHouseholdTransfer}', [InterHouseholdTransferController::class, 'show'])->name('inter-household-transfers.show');
        Route::get('/nuclei/{household}/conti', [InterHouseholdTransferController::class, 'getHouseholdAccounts'])->name('households.accounts');

        Route::get('/detrazioni-fiscali', [TaxDeductionExportController::class, 'index'])->name('tax-deductions.index');
        Route::get('/detrazioni-fiscali/esporta-pdf', [TaxDeductionExportController::class, 'exportPdf'])->name('tax-deductions.export-pdf');
        Route::get('/detrazioni-fiscali/esporta-allegati', [TaxDeductionExportController::class, 'exportAttachments'])->name('tax-deductions.export-attachments');

        Route::get('/punteggio-stile-vita', [LifestyleScoreController::class, 'index'])->name('lifestyle-score.index');
        Route::get('/punteggio-stile-vita/esporta-xls', [LifestyleScoreController::class, 'exportXls'])->name('lifestyle-score.export-xls');
        Route::get('/punteggio-stile-vita/esporta-pdf', [LifestyleScoreController::class, 'exportPdf'])->name('lifestyle-score.export-pdf');

        Route::get('/analisi-investimenti', [InvestmentAnalysisController::class, 'index'])->name('investment-analyses.index');

        Route::get('/asset-investimento', [InvestmentAssetController::class, 'index'])->name('investment-assets.index');

        Route::get('/investimenti', [InvestmentController::class, 'index'])->name('investments.index');
        Route::get('/investimenti/pac/{investmentPac}', [InvestmentPacController::class, 'show'])->name('investment-pacs.show');
        Route::get('/investimenti/{investment}', [InvestmentController::class, 'show'])->name('investments.show');

        Route::get('/allocazione-asset', [AssetAllocationController::class, 'index'])->name('asset-allocation.index');
        Route::get('/allocazione-asset/widget', [AssetAllocationController::class, 'widget'])->name('asset-allocation.widget');
    }); // fine requires-pro lettura

    // Fixed Expenses - lettura (disponibile per tutte le household con bilanciamento debiti)
    Route::get('/nuclei/{household}/spese-fisse', [FixedExpenseController::class, 'dashboard'])->name('fixed-expenses.dashboard');
    Route::get('/nuclei/{household}/spese-fisse/contributi', [FixedExpenseController::class, 'getContributions'])->name('fixed-expenses.contributions');
    Route::get('/nuclei/{household}/categorie/{category}/suggerisci-turno', [FixedExpenseController::class, 'suggestTurn'])->name('fixed-expenses.suggest-turn');

    // Fixed Expenses - modifica (solo owner può modificare impostazioni turni)
    Route::middleware(['can-modify'])->group(function () {
        Route::post('/nuclei/{household}/categorie/{category}/completa-turno', [FixedExpenseController::class, 'completeTurn'])->name('fixed-expenses.complete-turn');
        Route::patch('/nuclei/{household}/impostazioni-turni', [FixedExpenseController::class, 'updateTurnSettings'])->name('fixed-expenses.update-turn-settings');
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
