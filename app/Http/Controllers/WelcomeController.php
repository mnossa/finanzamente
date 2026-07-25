<?php

namespace App\Http\Controllers;

use App\Services\PlanService;
use App\Services\StructuredDataService;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\TwitterCard;
use Illuminate\View\View;

/**
 * Controller per la homepage pubblica.
 * Imposta i meta tag SEO e i dati strutturati JSON-LD tramite artesaos/seotools.
 */
class WelcomeController extends Controller
{
    public function __construct(
        private readonly PlanService $planService,
        private readonly StructuredDataService $structuredDataService,
    ) {}

    public function index(): View
    {
        $faqs = $this->faqs();

        SEOMeta::setTitle('Finanzamente - Finanza personale, dalle spese al patrimonio');
        SEOMeta::setDescription('App di finanza personale per chi vive in Italia: registri i movimenti, imposti i budget, segui patrimonio, investimenti e detrazioni. Piano gratuito senza scadenza.');
        SEOMeta::setKeywords(['finanza personale', 'gestione spese', 'budget mensile', 'patrimonio netto', 'asset allocation', 'detrazioni fiscali', 'risparmio', 'app italiana']);
        SEOMeta::setCanonical(url('/'));

        OpenGraph::setTitle('Finanzamente - Finanza personale, dalle spese al patrimonio');
        OpenGraph::setDescription('Movimenti, budget, patrimonio, investimenti e detrazioni in un unico quadro. Pensata per chi vive in Italia, con un piano gratuito senza scadenza.');
        OpenGraph::setUrl(url('/'));
        OpenGraph::addProperty('type', 'website');
        OpenGraph::addProperty('site_name', 'Finanzamente');
        OpenGraph::addProperty('locale', 'it_IT');

        TwitterCard::setTitle('Finanzamente - Dalle spese al patrimonio');
        TwitterCard::setDescription('Finanza personale per chi vive in Italia: movimenti, budget, patrimonio e detrazioni in un unico quadro.');
        TwitterCard::addValue('card', 'summary_large_image');

        $this->structuredDataService->forHomepage($faqs);

        return view('welcome', [
            'plans' => $this->planService->getPlansForFrontend(),
            'proEnabled' => $this->planService->isProEnabled(),
            'annualDiscountPercent' => $this->planService->getAnnualDiscountPercent(),
            'waitlistEnabled' => config('prelaunch.waitlist_enabled', false),
            'preLaunchMode' => config('prelaunch.enabled', false),
            'faqs' => $faqs,
        ]);
    }

    /**
     * Domande frequenti della homepage.
     *
     * Unica fonte per la sezione FAQ e per lo schema JSON-LD FAQPage:
     * le risposte devono restare allineate ai limiti reali in config/plans.php.
     *
     * @return list<array{question: string, answer: string}>
     */
    private function faqs(): array
    {
        return [
            [
                'question' => 'Cosa posso fare con il piano gratuito?',
                'answer' => 'Movimenti illimitati, budget mensili, categorie e tag tuoi, fino a 5 conti, un obiettivo di risparmio e l\'import da file. Non scade e non chiede la carta.',
            ],
            [
                'question' => 'Devo inserire ogni spesa a mano?',
                'answer' => 'Puoi farlo, ed è il modo più preciso per accorgerti di come spendi. Ma le voci ricorrenti si ripresentano da sole alla scadenza, uno storico lo carichi da un file CSV o Excel, e Finanzamente ti propone le ricorrenze che riconosce nei movimenti che hai già registrato.',
            ],
            [
                'question' => 'Posso usarlo con il partner o con i coinquilini?',
                'answer' => 'Sì. Create un nucleo condiviso e scegliete come gestirlo: portafoglio comune, oppure conti separati con percentuali di ripartizione sulle spese fisse. I movimenti che segni come privati restano visibili solo a te. Invitare altri membri richiede il piano Pro.',
            ],
            [
                'question' => 'Serve anche per gli investimenti?',
                'answer' => 'Sì, nel piano Pro: ETF, azioni, obbligazioni, crypto, piani di accumulo, asset allocation con indice di rischio da 1 a 7 e analisi di redditività. Se non investi, la sezione resta spenta e non ti intralcia.',
            ],
            [
                'question' => 'Funziona dal telefono?',
                'answer' => 'È pensata prima per il telefono. La installi dal browser come una normale app, e la barra di navigazione in basso la configuri con le sezioni che usi davvero.',
            ],
            [
                'question' => 'Che fine fanno i miei dati?',
                'answer' => 'Restano tuoi. Puoi esportarli in qualsiasi momento o cancellare l\'account, e i consensi li gestisci uno per uno dal profilo. Nessuna pubblicità.',
            ],
        ];
    }
}
