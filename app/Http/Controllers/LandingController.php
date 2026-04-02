<?php

namespace App\Http\Controllers;

use App\Services\PlanService;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\TwitterCard;
use Illuminate\View\View;

/**
 * Controller per le landing page dedicate ai diversi target di utenti.
 * Ogni metodo imposta i meta tag SEO specifici e restituisce la vista corrispondente.
 */
class LandingController extends Controller
{
    public function __construct(private readonly PlanService $planService) {}

    private function planData(): array
    {
        return [
            'plans' => $this->planService->getPlansForFrontend(),
            'proEnabled' => $this->planService->isProEnabled(),
            'annualDiscountPercent' => $this->planService->getAnnualDiscountPercent(),
        ];
    }

    public function investitori(): View
    {
        SEOMeta::setTitle('FinanzaMente Pro per Investitori — Portafoglio e Asset Allocation');
        SEOMeta::setDescription('Traccia ETF, azioni, crypto e obbligazioni. Visualizza asset allocation, indice di rischio e analisi del portafoglio con FinanzaMente Pro.');
        SEOMeta::setKeywords(['investimenti personali', 'asset allocation', 'portafoglio ETF', 'finanza personale investitore', 'analisi portafoglio']);
        SEOMeta::setCanonical(url('/per-investitori'));
        OpenGraph::setTitle('FinanzaMente Pro — Per chi investe');
        OpenGraph::setDescription('Spese quotidiane e portafoglio di investimento in un unico posto. Asset allocation con indice di rischio, analisi e proiezioni.');
        OpenGraph::setUrl(url('/per-investitori'));
        OpenGraph::addProperty('type', 'website');
        TwitterCard::setTitle('FinanzaMente Pro per Investitori');
        TwitterCard::setDescription('Portafoglio, asset allocation e finanze personali. Tutto in un unico posto.');
        TwitterCard::addValue('card', 'summary_large_image');

        return view('landing.investitori', $this->planData());
    }

    public function famiglie(): View
    {
        SEOMeta::setTitle('FinanzaMente Pro per Famiglie e Coppie — Finanze condivise senza conflitti');
        SEOMeta::setDescription('Gestisci le finanze di famiglia con household condivisi, inviti, ruoli e trasferimenti tra nuclei. FinanzaMente Pro per famiglie e coppie.');
        SEOMeta::setKeywords(['finanze di famiglia', 'gestione spese coppia', 'household condiviso', 'spese familiari', 'budget familiare']);
        SEOMeta::setCanonical(url('/per-famiglie'));
        OpenGraph::setTitle('FinanzaMente Pro — Per famiglie e coppie');
        OpenGraph::setDescription('Finanze condivise senza conflitti. Household multi-membro, inviti, ruoli e trasferimenti tra nuclei familiari.');
        OpenGraph::setUrl(url('/per-famiglie'));
        OpenGraph::addProperty('type', 'website');
        TwitterCard::setTitle('FinanzaMente Pro per Famiglie');
        TwitterCard::setDescription('Gestisci le finanze di famiglia con trasparenza e senza stress.');
        TwitterCard::addValue('card', 'summary_large_image');

        return view('landing.famiglie', $this->planData());
    }

    public function freelance(): View
    {
        SEOMeta::setTitle('FinanzaMente Pro per Freelance e Partita IVA — Gestione IVA e spese deducibili');
        SEOMeta::setDescription('Tieni sotto controllo IVA, spese deducibili e fatturazione. FinanzaMente Pro è pensato per freelance e professionisti con Partita IVA in Italia.');
        SEOMeta::setKeywords(['gestione IVA', 'freelance finanze', 'partita IVA', 'spese deducibili', 'budget freelance Italia']);
        SEOMeta::setCanonical(url('/per-freelance'));
        OpenGraph::setTitle('FinanzaMente Pro — Per Freelance e P.IVA');
        OpenGraph::setDescription('Gestione IVA, spese deducibili e finanze da libero professionista. Tutto in un\'unica app italiana.');
        OpenGraph::setUrl(url('/per-freelance'));
        OpenGraph::addProperty('type', 'website');
        TwitterCard::setTitle('FinanzaMente Pro per Freelance');
        TwitterCard::setDescription('IVA e spese deducibili sotto controllo, senza stress.');
        TwitterCard::addValue('card', 'summary_large_image');

        return view('landing.freelance', $this->planData());
    }

    public function lavoratori(): View
    {
        SEOMeta::setTitle('FinanzaMente Pro per Lavoratori Dipendenti — Detrazioni fiscali e 730');
        SEOMeta::setDescription('Marca le spese detraibili durante l\'anno ed esporta tutto in PDF per il 730. Nessuna spesa fiscale dimenticata con FinanzaMente Pro.');
        SEOMeta::setKeywords(['detrazioni fiscali', '730', 'spese detraibili', 'lavoratore dipendente finanze', 'dichiarazione redditi Italia']);
        SEOMeta::setCanonical(url('/per-lavoratori'));
        OpenGraph::setTitle('FinanzaMente Pro — Per lavoratori dipendenti');
        OpenGraph::setDescription('Detrazioni fiscali e 730 senza sorprese. Marca le spese durante l\'anno, esporta in PDF al momento della dichiarazione.');
        OpenGraph::setUrl(url('/per-lavoratori'));
        OpenGraph::addProperty('type', 'website');
        TwitterCard::setTitle('FinanzaMente Pro per Lavoratori Dipendenti');
        TwitterCard::setDescription('Non dimenticare più una spesa detraibile. Pronto per il 730.');
        TwitterCard::addValue('card', 'summary_large_image');

        return view('landing.lavoratori', $this->planData());
    }

    public function pianificatori(): View
    {
        SEOMeta::setTitle('FinanzaMente Pro per Pianificatori — Simulazioni finanziarie e obiettivi illimitati');
        SEOMeta::setDescription('Simula scenari finanziari, pianifica obiettivi illimitati e gestisci ricorrenti senza limiti. FinanzaMente Pro per chi vuole costruire il proprio futuro finanziario.');
        SEOMeta::setKeywords(['simulazioni finanziarie', 'obiettivi finanziari', 'pianificazione finanziaria', 'risparmio', 'futuro finanziario']);
        SEOMeta::setCanonical(url('/per-pianificatori'));
        OpenGraph::setTitle('FinanzaMente Pro — Per pianificatori finanziari');
        OpenGraph::setDescription('Simulazioni, obiettivi illimitati e ricorrenti senza limiti. Costruisci il tuo futuro finanziario con FinanzaMente Pro.');
        OpenGraph::setUrl(url('/per-pianificatori'));
        OpenGraph::addProperty('type', 'website');
        TwitterCard::setTitle('FinanzaMente Pro per Pianificatori');
        TwitterCard::setDescription('Simula il tuo futuro finanziario con scenari reali.');
        TwitterCard::addValue('card', 'summary_large_image');

        return view('landing.pianificatori', $this->planData());
    }

    public function techSavvy(): View
    {
        SEOMeta::setTitle('FinanzaMente Pro per Tech-Savvy — Telegram Bot e Inbox intelligente');
        SEOMeta::setDescription('Registra spese direttamente da Telegram con il bot di FinanzaMente. Gestisci l\'inbox delle transazioni in arrivo con potenza e velocità. Piano Pro.');
        SEOMeta::setKeywords(['telegram bot finanze', 'inserimento spese telegram', 'finanze tech', 'automazione finanze personali']);
        SEOMeta::setCanonical(url('/per-tech-savvy'));
        OpenGraph::setTitle('FinanzaMente Pro — Per tech-savvy');
        OpenGraph::setDescription('Inserisci spese via Telegram, gestisci l\'inbox automatico e automatizza le tue finanze con FinanzaMente Pro.');
        OpenGraph::setUrl(url('/per-tech-savvy'));
        OpenGraph::addProperty('type', 'website');
        TwitterCard::setTitle('FinanzaMente Pro per Tech-Savvy');
        TwitterCard::setDescription('Gestisci le finanze dal tuo Telegram. Veloce, automatico, pro.');
        TwitterCard::addValue('card', 'summary_large_image');

        return view('landing.tech-savvy', $this->planData());
    }

    public function crescita(): View
    {
        SEOMeta::setTitle('FinanzaMente Pro per la Crescita Personale — Lifestyle Inflation Score');
        SEOMeta::setDescription('Scopri se le tue spese voluttuarie crescono più velocemente delle entrate con il Lifestyle Inflation Score. Un indicatore unico per chi punta alla crescita finanziaria.');
        SEOMeta::setKeywords(['lifestyle inflation', 'crescita finanziaria', 'consapevolezza finanziaria', 'lifestyle score', 'abitudini di spesa']);
        SEOMeta::setCanonical(url('/crescita-personale'));
        OpenGraph::setTitle('FinanzaMente Pro — Per la crescita personale');
        OpenGraph::setDescription('Stai cadendo nella trappola dell\'inflazione del tenore di vita? Il Lifestyle Inflation Score di FinanzaMente Pro ti aiuta a capirlo.');
        OpenGraph::setUrl(url('/crescita-personale'));
        OpenGraph::addProperty('type', 'website');
        TwitterCard::setTitle('FinanzaMente — Lifestyle Inflation Score');
        TwitterCard::setDescription('Scopri se stai cadendo nella trappola dell\'inflazione del tenore di vita.');
        TwitterCard::addValue('card', 'summary_large_image');

        return view('landing.crescita', $this->planData());
    }
}
