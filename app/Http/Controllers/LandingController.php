<?php

namespace App\Http\Controllers;

use App\Services\PlanService;
use App\Services\StructuredDataService;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\TwitterCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Controller per le landing page dedicate ai diversi target di utenti.
 * Ogni metodo imposta i meta tag SEO e i dati strutturati JSON-LD specifici per ogni target.
 */
class LandingController extends Controller
{
    public function __construct(
        private readonly PlanService $planService,
        private readonly StructuredDataService $structuredDataService,
    ) {}

    private function planData(): array
    {
        return [
            'plans' => $this->planService->getPlansForFrontend(),
            'proEnabled' => $this->planService->isProEnabled(),
            'annualDiscountPercent' => $this->planService->getAnnualDiscountPercent(),
            'waitlistEnabled' => config('prelaunch.waitlist_enabled', false),
            'preLaunchMode' => config('prelaunch.enabled', false),
        ];
    }

    public function investitori(): View
    {
        SEOMeta::setTitle('Finanzamente Pro per Investitori — Portafoglio e Asset Allocation');
        SEOMeta::setDescription('Traccia ETF, azioni, crypto e obbligazioni. Visualizza asset allocation, indice di rischio e analisi del portafoglio con Finanzamente Pro.');
        SEOMeta::setKeywords(['investimenti personali', 'asset allocation', 'portafoglio ETF', 'finanza personale investitore', 'analisi portafoglio']);
        SEOMeta::setCanonical(url('/per-investitori'));
        OpenGraph::setTitle('Finanzamente Pro — Per chi investe');
        OpenGraph::setDescription('Spese quotidiane e portafoglio di investimento in un unico posto. Asset allocation con indice di rischio, analisi e proiezioni.');
        OpenGraph::setUrl(url('/per-investitori'));
        OpenGraph::addProperty('type', 'website');
        TwitterCard::setTitle('Finanzamente Pro per Investitori');
        TwitterCard::setDescription('Portafoglio, asset allocation e finanze personali. Tutto in un unico posto.');
        TwitterCard::addValue('card', 'summary_large_image');

        $this->structuredDataService->forLandingPage(url('/per-investitori'), 'Per Investitori');

        return view('landing.investitori', $this->planData());
    }

    public function famiglie(): View
    {
        SEOMeta::setTitle('Finanzamente Pro per Famiglie e Coppie — Finanze condivise senza conflitti');
        SEOMeta::setDescription('Gestisci le finanze di famiglia con household condivisi, inviti, ruoli e trasferimenti tra nuclei. Finanzamente Pro per famiglie e coppie.');
        SEOMeta::setKeywords(['finanze di famiglia', 'gestione spese coppia', 'household condiviso', 'spese familiari', 'budget familiare']);
        SEOMeta::setCanonical(url('/per-famiglie'));
        OpenGraph::setTitle('Finanzamente Pro — Per famiglie e coppie');
        OpenGraph::setDescription('Finanze condivise senza conflitti. Household multi-membro, inviti, ruoli e trasferimenti tra nuclei familiari.');
        OpenGraph::setUrl(url('/per-famiglie'));
        OpenGraph::addProperty('type', 'website');
        TwitterCard::setTitle('Finanzamente Pro per Famiglie');
        TwitterCard::setDescription('Gestisci le finanze di famiglia con trasparenza e senza stress.');
        TwitterCard::addValue('card', 'summary_large_image');

        $this->structuredDataService->forLandingPage(url('/per-famiglie'), 'Per Famiglie e Coppie');

        return view('landing.famiglie', $this->planData());
    }

    public function freelance(): RedirectResponse
    {
        return redirect()->route('landing.investitori', [], 301);
    }

    public function lavoratori(): View
    {
        SEOMeta::setTitle('Finanzamente Pro per Lavoratori Dipendenti — Tracker spese detraibili');
        SEOMeta::setDescription('Marca le spese detraibili durante l\'anno ed esporta tutto in PDF per il CAF. Nessuna spesa fiscale dimenticata con Finanzamente Pro.');
        SEOMeta::setKeywords(['detrazioni fiscali', 'spese detraibili', 'lavoratore dipendente finanze', 'export CAF', 'spese mediche']);
        SEOMeta::setCanonical(url('/per-lavoratori'));
        OpenGraph::setTitle('Finanzamente Pro — Per lavoratori dipendenti');
        OpenGraph::setDescription('Tracker spese detraibili: marca le spese durante l\'anno, esporta in PDF per CAF o commercialista.');
        OpenGraph::setUrl(url('/per-lavoratori'));
        OpenGraph::addProperty('type', 'website');
        TwitterCard::setTitle('Finanzamente Pro per Lavoratori Dipendenti');
        TwitterCard::setDescription('Non dimenticare più una spesa detraibile. Export PDF pronto per CAF o commercialista.');
        TwitterCard::addValue('card', 'summary_large_image');

        $this->structuredDataService->forLandingPage(url('/per-lavoratori'), 'Per Lavoratori Dipendenti');

        return view('landing.lavoratori', $this->planData());
    }

    public function pianificatori(): View
    {
        SEOMeta::setTitle('Finanzamente per Pianificatori — Simulazioni gratuite e obiettivi finanziari');
        SEOMeta::setDescription('Simulazioni finanziarie gratuite e strumenti Pro per obiettivi illimitati e ricorrenti automatiche. Pianifica il tuo futuro con Finanzamente.');
        SEOMeta::setKeywords(['simulazioni finanziarie', 'obiettivi finanziari', 'pianificazione finanziaria', 'risparmio', 'futuro finanziario']);
        SEOMeta::setCanonical(url('/per-pianificatori'));
        OpenGraph::setTitle('Finanzamente Pro — Per pianificatori finanziari');
        OpenGraph::setDescription('Simulazioni gratuite e obiettivi illimitati con Pro. Costruisci il tuo futuro finanziario con Finanzamente.');
        OpenGraph::setUrl(url('/per-pianificatori'));
        OpenGraph::addProperty('type', 'website');
        TwitterCard::setTitle('Finanzamente Pro per Pianificatori');
        TwitterCard::setDescription('Simula il tuo futuro finanziario con scenari reali.');
        TwitterCard::addValue('card', 'summary_large_image');

        $this->structuredDataService->forLandingPage(url('/per-pianificatori'), 'Per Pianificatori');

        return view('landing.pianificatori', $this->planData());
    }

    public function techSavvy(): View
    {
        SEOMeta::setTitle('Finanzamente Pro per Tech-Savvy — Telegram Bot e Inbox intelligente');
        SEOMeta::setDescription('Registra spese direttamente da Telegram con il bot di Finanzamente. Gestisci l\'inbox delle transazioni in arrivo con potenza e velocità. Piano Pro.');
        SEOMeta::setKeywords(['telegram bot finanze', 'inserimento spese telegram', 'finanze tech', 'automazione finanze personali']);
        SEOMeta::setCanonical(url('/per-tech-savvy'));
        OpenGraph::setTitle('Finanzamente Pro — Per tech-savvy');
        OpenGraph::setDescription('Inserisci spese via Telegram, gestisci l\'inbox automatico e automatizza le tue finanze con Finanzamente Pro.');
        OpenGraph::setUrl(url('/per-tech-savvy'));
        OpenGraph::addProperty('type', 'website');
        TwitterCard::setTitle('Finanzamente Pro per Tech-Savvy');
        TwitterCard::setDescription('Gestisci le finanze dal tuo Telegram. Veloce, automatico, pro.');
        TwitterCard::addValue('card', 'summary_large_image');

        $this->structuredDataService->forLandingPage(url('/per-tech-savvy'), 'Per Tech-Savvy');

        return view('landing.tech-savvy', $this->planData());
    }

    public function crescita(): View
    {
        SEOMeta::setTitle('Finanzamente Pro per la Crescita Personale — Lifestyle Inflation Score');
        SEOMeta::setDescription('Scopri se le tue spese voluttuarie crescono più velocemente delle entrate con il Lifestyle Inflation Score. Un indicatore unico per chi punta alla crescita finanziaria.');
        SEOMeta::setKeywords(['lifestyle inflation', 'crescita finanziaria', 'consapevolezza finanziaria', 'lifestyle score', 'abitudini di spesa']);
        SEOMeta::setCanonical(url('/crescita-personale'));
        OpenGraph::setTitle('Finanzamente Pro — Per la crescita personale');
        OpenGraph::setDescription('Stai cadendo nella trappola dell\'inflazione del tenore di vita? Il Lifestyle Inflation Score di Finanzamente Pro ti aiuta a capirlo.');
        OpenGraph::setUrl(url('/crescita-personale'));
        OpenGraph::addProperty('type', 'website');
        TwitterCard::setTitle('Finanzamente — Lifestyle Inflation Score');
        TwitterCard::setDescription('Scopri se stai cadendo nella trappola dell\'inflazione del tenore di vita.');
        TwitterCard::addValue('card', 'summary_large_image');

        $this->structuredDataService->forLandingPage(url('/crescita-personale'), 'Crescita Personale');

        return view('landing.crescita', $this->planData());
    }
}
