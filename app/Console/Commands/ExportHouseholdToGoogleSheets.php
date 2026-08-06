<?php

namespace App\Console\Commands;

use App\Console\Concerns\RequiresLocalEnvironment;
use App\Models\Household;
use App\Models\User;
use App\Services\GoogleSheets\GoogleSheetsPushService;
use Illuminate\Console\Command;

class ExportHouseholdToGoogleSheets extends Command
{
    use RequiresLocalEnvironment;

    protected $signature = 'finanzamente:export-google-sheets
        {--user= : ID utente (default: unico utente o owner household)}
        {--household= : ID household (default: active_household_id dell\'utente)}
        {--with-trashed : Include record soft-deleted}
        {--skip-exchange-rates : Salta tab Exchange_Rates}
        {--output= : Directory CSV locale invece di push Google}
        {--share-with= : Email Google a cui condividere (override config)}
        {--credentials= : Path JSON service account (override config)}
        {--spreadsheet-id= : Usa foglio esistente (condiviso Editor con il service account)}
        {--raw : Dump grezzo di tutte le tabelle DB (non ottimizzato)}
        {--dry-run : Solo conteggi righe, nessuna scrittura}';

    protected $description = '[Solo local/development] Esporta tutti i dati di una household su Google Sheets (o CSV).';

    public function handle(GoogleSheetsPushService $pushService): int
    {
        if (! $this->blockUnlessLocalEnvironment()) {
            return self::FAILURE;
        }

        $user = $this->resolveUser();
        if (! $user) {
            return self::FAILURE;
        }

        $household = $this->resolveHousehold($user);
        if (! $household) {
            return self::FAILURE;
        }

        $this->info("Utente: {$user->email} (ID {$user->id})");
        $this->info("Household: {$household->name} (ID {$household->id})");

        $dryRun = (bool) $this->option('dry-run');
        $output = $this->option('output');
        $outputDir = is_string($output) && $output !== '' ? $output : null;

        try {
            $result = $pushService->export(
                household: $household,
                user: $user,
                withTrashed: (bool) $this->option('with-trashed'),
                includeExchangeRates: ! (bool) $this->option('skip-exchange-rates'),
                dryRun: $dryRun,
                csvOutputDir: $outputDir,
                shareWith: $this->option('share-with') ?: null,
                credentialsPath: $this->option('credentials') ?: null,
                spreadsheetId: $this->option('spreadsheet-id') ?: null,
                raw: (bool) $this->option('raw'),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Modalità: '.($result['mode'] ?? 'workbook'));
        $this->table(
            ['Foglio', 'Righe'],
            collect($result['counts'])->map(fn ($count, $title) => [$title, $count])->values()->all()
        );

        $totalRows = array_sum($result['counts']);
        $this->info('Totale righe dati: '.$totalRows);

        if ($dryRun) {
            $this->warn('Dry-run: nessun file creato / nessun push Google.');

            return self::SUCCESS;
        }

        if (isset($result['csvPath'])) {
            $this->info('CSV scritti in: '.$result['csvPath']);

            return self::SUCCESS;
        }

        if (isset($result['spreadsheetUrl'])) {
            $this->info('Spreadsheet: '.$result['spreadsheetUrl']);
            $this->line('ID: '.$result['spreadsheetId']);
            if (! empty($result['serviceAccountEmail'])) {
                $this->line('Service account: '.$result['serviceAccountEmail']);
            }
            if (! empty($result['appsheet'])) {
                $this->newLine();
                $this->warn('AppSheet: creazione automatica non supportata dall\'API Google.');
                $this->line($result['appsheet']['howto'] ?? '');
                $this->line('Portale: '.($result['appsheet']['url'] ?? 'https://www.appsheet.com/'));
            }
        }

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $userId = $this->option('user');
        if (is_string($userId) && $userId !== '') {
            $user = User::query()->find((int) $userId);
            if (! $user) {
                $this->error("Utente {$userId} non trovato.");

                return null;
            }

            return $user;
        }

        $count = User::query()->count();
        if ($count === 0) {
            $this->error('Nessun utente in database.');

            return null;
        }

        if ($count === 1) {
            return User::query()->first();
        }

        $withActive = User::query()->whereNotNull('active_household_id')->orderBy('id')->first();
        if ($withActive) {
            $this->warn("Più utenti presenti: uso ID {$withActive->id} ({$withActive->email}). Passa --user= per scegliere.");

            return $withActive;
        }

        $this->error('Più utenti senza active_household_id. Specifica --user=.');

        return null;
    }

    private function resolveHousehold(User $user): ?Household
    {
        $householdId = $this->option('household');
        if (is_string($householdId) && $householdId !== '') {
            $household = Household::query()->find((int) $householdId);
            if (! $household) {
                $this->error("Household {$householdId} non trovata.");

                return null;
            }

            return $household;
        }

        if ($user->active_household_id) {
            $household = Household::query()->find($user->active_household_id);
            if ($household) {
                return $household;
            }
        }

        $owned = Household::query()->where('owner_user_id', $user->id)->orderBy('id')->first();
        if ($owned) {
            return $owned;
        }

        $member = $user->households()->orderBy('households.id')->first();
        if ($member) {
            return $member;
        }

        $this->error('Nessuna household trovata per questo utente. Specifica --household=.');

        return null;
    }
}
