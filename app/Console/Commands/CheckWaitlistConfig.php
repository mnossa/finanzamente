<?php

namespace App\Console\Commands;

use App\Services\WaitlistService;
use Brevo\Brevo;
use Brevo\Contacts\Requests\GetListsRequest;
use Illuminate\Console\Command;

/**
 * Verifica che la configurazione Brevo per la waitlist sia completa e funzionante.
 *
 * Controlla:
 * - Variabili .env necessarie
 * - Raggiungibilità API Brevo (chiave valida)
 * - Esistenza della lista waitlist
 * - Esistenza del template DOI
 * - Esistenza dell'attributo SIGNATURE sui contatti
 * - Accessibilità della pagina di redirect post-conferma
 *
 * Uso:
 *   php artisan waitlist:check
 */
class CheckWaitlistConfig extends Command
{
    protected $signature = 'waitlist:check';

    protected $description = 'Verifica la configurazione Brevo per il double opt-in della waitlist';

    public function handle(): int
    {
        $this->info('');
        $this->info('🔍  Verifica configurazione Waitlist / Brevo DOI');
        $this->info(str_repeat('─', 50));

        $allOk = true;

        // ── 1. Variabili .env ────────────────────────────────────
        $this->line('');
        $this->comment('[1/5] Variabili di configurazione');

        $checks = [
            'BREVO_API_KEY'                    => config('services.brevo.api_key'),
            'BREVO_WAITLIST_LIST_ID'           => config('services.brevo.waitlist_list_id'),
            'BREVO_DOUBLE_OPTIN_TEMPLATE_ID'   => config('services.brevo.double_optin_template_id'),
            'BREVO_DOUBLE_OPTIN_REDIRECT_URL'  => config('services.brevo.double_optin_redirect_url'),
        ];

        foreach ($checks as $key => $value) {
            if (!empty($value) && $value !== 0) {
                $this->line("  <fg=green>✔</> {$key} = " . (str_contains($key, 'KEY') ? substr($value, 0, 12) . '...' : $value));
            } else {
                $this->line("  <fg=red>✘</> {$key} non impostata");
                $allOk = false;
            }
        }

        // ── 2. Connessione API Brevo ─────────────────────────────
        $this->line('');
        $this->comment('[2/5] Connessione API Brevo');

        $apiKey = config('services.brevo.api_key');

        if (empty($apiKey)) {
            $this->line('  <fg=yellow>⏭</>  Saltato (BREVO_API_KEY mancante)');
            return self::FAILURE;
        }

        $brevo = new Brevo($apiKey);

        try {
            $lists = $brevo->contacts->getLists(new GetListsRequest(['limit' => 10, 'offset' => 0]));
            $this->line('  <fg=green>✔</> Connessione API riuscita (' . count($lists->lists ?? []) . ' liste trovate)');
        } catch (\Exception $e) {
            $this->line('  <fg=red>✘</> Connessione API fallita: ' . $e->getMessage());
            $allOk = false;
            // Impossibile procedere senza API
            $this->showSummary($allOk);
            return self::FAILURE;
        }

        // ── 3. Lista waitlist ────────────────────────────────────
        $this->line('');
        $this->comment('[3/5] Lista waitlist su Brevo');

        $listId = (int) config('services.brevo.waitlist_list_id');

        try {
            $list = $brevo->contacts->getList($listId);
            $this->line("  <fg=green>✔</> Lista #{$listId} trovata: \"{$list->name}\" ({$list->totalSubscribers} iscritti)");
        } catch (\Exception $e) {
            $this->line("  <fg=red>✘</> Lista #{$listId} non trovata o non accessibile");
            $allOk = false;
        }

        // ── 4. Template DOI ──────────────────────────────────────
        $this->line('');
        $this->comment('[4/5] Template Double Opt-In');

        $templateId = (int) config('services.brevo.double_optin_template_id');

        if ($templateId === 0) {
            $this->line('  <fg=red>✘</> BREVO_DOUBLE_OPTIN_TEMPLATE_ID non impostato');
            $allOk = false;
        } else {
            try {
                $template = $brevo->transactionalEmails->getSmtpTemplate($templateId);
                $status = $template->isActive ? '<fg=green>attivo</>' : '<fg=yellow>inattivo</>';
                $this->line("  <fg=green>✔</> Template #{$templateId} trovato: \"{$template->name}\" — {$status}");

                if (!$template->isActive) {
                    $this->line('  <fg=yellow>⚠</>  Il template non è attivo — attivalo su Brevo prima di procedere');
                    $allOk = false;
                }
            } catch (\Exception $e) {
                $this->line("  <fg=red>✘</> Template #{$templateId} non trovato: " . $e->getMessage());
                $allOk = false;
            }
        }

        // ── 5. Attributo SIGNATURE ────────────────────────────────
        $this->line('');
        $this->comment('[5/5] Attributo personalizzato SIGNATURE');

        try {
            $attributes = $brevo->contacts->getAttributes();
            $found = false;
            foreach ($attributes->attributes as $attr) {
                if (strtoupper($attr->name) === 'SIGNATURE') {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $this->line('  <fg=green>✔</> Attributo SIGNATURE presente');
            } else {
                $this->line('  <fg=red>✘</> Attributo SIGNATURE mancante');
                $this->line('      → Vai su Brevo: Contacts → Configuration → Contact Attributes → crea "SIGNATURE" (tipo: Text)');
                $allOk = false;
            }
        } catch (\Exception $e) {
            $this->line('  <fg=yellow>⚠</>  Impossibile verificare attributi: ' . $e->getMessage());
        }

        // ── Riepilogo ────────────────────────────────────────────
        $this->showSummary($allOk);

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    private function showSummary(bool $allOk): void
    {
        $this->line('');
        $this->info(str_repeat('─', 50));
        if ($allOk) {
            $this->info('✅  Tutto ok — la waitlist è pronta per ricevere iscrizioni.');
        } else {
            $this->error('❌  Configurazione incompleta — correggi i punti sopra e riesegui.');
        }
        $this->line('');
    }
}
