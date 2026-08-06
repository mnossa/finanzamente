<?php

namespace App\Http\Controllers;

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
        private readonly StructuredDataService $structuredDataService,
    ) {}

    public function index(): View
    {
        $faqs = $this->faqs();

        SEOMeta::setTitle('Finanzamente - Finanza personale open source');
        SEOMeta::setDescription('App di finanza personale open source (MIT) per chi vive in Italia. Self-host con Docker: movimenti, budget, patrimonio, investimenti e detrazioni.');
        SEOMeta::setKeywords(['finanza personale', 'open source', 'self-host', 'gestione spese', 'budget mensile', 'patrimonio netto', 'detrazioni fiscali', 'app italiana']);
        SEOMeta::setCanonical(url('/'));

        OpenGraph::setTitle('Finanzamente - Finanza personale open source');
        OpenGraph::setDescription('Movimenti, budget, patrimonio e detrazioni. Licenza MIT, installazione self-host con Docker.');
        OpenGraph::setUrl(url('/'));
        OpenGraph::addProperty('type', 'website');
        OpenGraph::addProperty('site_name', 'Finanzamente');
        OpenGraph::addProperty('locale', 'it_IT');

        TwitterCard::setTitle('Finanzamente - Open source, self-host');
        TwitterCard::setDescription('Finanza personale per chi vive in Italia. MIT, Docker, senza piani a pagamento.');
        TwitterCard::addValue('card', 'summary_large_image');

        $this->structuredDataService->forHomepage($faqs);

        return view('welcome', [
            'faqs' => $faqs,
        ]);
    }

    /**
     * Domande frequenti della homepage.
     *
     * Unica fonte per la sezione FAQ e per lo schema JSON-LD FAQPage.
     *
     * @return list<array{question: string, answer: string}>
     */
    private function faqs(): array
    {
        return [
            [
                'question' => 'Cosa posso fare con Finanzamente?',
                'answer' => 'Movimenti, budget mensili, categorie e tag, conti, obiettivi di risparmio, investimenti (prezzi aggiornati a mano), detrazioni e import da file. Open source, licenza MIT, senza piani a pagamento.',
            ],
            [
                'question' => 'Come la installo?',
                'answer' => 'Serve Docker. Clona il repository, copia .env.example in .env, genera la APP_KEY e avvia lo stack con make up. I dettagli sono nel README e nella documentazione tecnica.',
            ],
            [
                'question' => 'Devo inserire ogni spesa a mano?',
                'answer' => 'Puoi farlo, ed è il modo più preciso per accorgerti di come spendi. Le voci ricorrenti si ripresentano alla scadenza, uno storico lo carichi da CSV o Excel, e l\'app segnala ricorrenze che riconosce nei movimenti già registrati. Opzionale: bot Telegram per inserimenti da chat.',
            ],
            [
                'question' => 'Posso usarlo con il partner o con i coinquilini?',
                'answer' => 'Sì. Create un nucleo condiviso e scegliete come gestirlo: portafoglio comune, oppure conti separati con percentuali di ripartizione sulle spese fisse. I movimenti che segni come privati restano visibili solo a te.',
            ],
            [
                'question' => 'Serve anche per gli investimenti?',
                'answer' => 'Sì: ETF, azioni, obbligazioni, crypto, piani di accumulo, asset allocation con indice di rischio da 1 a 7 e analisi di redditività. I prezzi li aggiorni tu: non c\'è sync automatico da provider di mercato. Se non investi, la sezione resta spenta.',
            ],
            [
                'question' => 'Che fine fanno i miei dati?',
                'answer' => 'Dipende da chi ospita l\'istanza. Se la installi tu, i dati restano sul tuo server. Puoi esportarli o cancellare l\'account dall\'app. Compila privacy e termini della tua installazione prima di esporre il servizio ad altri.',
            ],
        ];
    }
}
