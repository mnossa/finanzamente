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
        SEOMeta::setTitle('Finanzamente - Gestisci le tue finanze con intelligenza');
        SEOMeta::setDescription('Finanzamente è la webapp di gestione finanziaria personale pensata per chi vive in Italia. Controlla le tue spese, pianifica il futuro e raggiungi i tuoi obiettivi finanziari con semplicità.');
        SEOMeta::setKeywords(['gestione finanze', 'budget personale', 'risparmio', 'spese', 'finanza personale', 'vivere in Italia', 'webapp finanze']);
        SEOMeta::setCanonical(url('/'));

        OpenGraph::setTitle('Finanzamente - Gestisci le tue finanze con intelligenza');
        OpenGraph::setDescription('Prendi il controllo totale delle tue finanze. Gestisci ogni transazione, pianifica il tuo budget e raggiungi i tuoi obiettivi finanziari. Per tutti chi vive in Italia.');
        OpenGraph::setUrl(url('/'));
        OpenGraph::addProperty('type', 'website');
        OpenGraph::addProperty('site_name', 'Finanzamente');
        OpenGraph::addProperty('locale', 'it_IT');

        TwitterCard::setTitle('Finanzamente - Gestisci le tue finanze');
        TwitterCard::setDescription('Webapp di gestione finanziaria personale per chi vive in Italia.');
        TwitterCard::addValue('card', 'summary_large_image');

        $this->structuredDataService->forHomepage();

        return view('welcome', [
            'plans' => $this->planService->getPlansForFrontend(),
            'proEnabled' => $this->planService->isProEnabled(),
            'annualDiscountPercent' => $this->planService->getAnnualDiscountPercent(),
            'waitlistEnabled' => config('prelaunch.waitlist_enabled', false),
            'preLaunchMode' => config('prelaunch.enabled', false),
        ]);
    }
}
