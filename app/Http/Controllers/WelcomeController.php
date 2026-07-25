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
        SEOMeta::setTitle('Finanzamente - Tracker e analisi per le tue finanze');
        SEOMeta::setDescription('Finanzamente è un tracker di finanze personali per chi vive in Italia: registri spese e entrate, segui budget e patrimonio, e usi strumenti di analisi. Nessuna connessione bancaria.');
        SEOMeta::setKeywords(['tracker finanze', 'gestione finanze', 'budget personale', 'risparmio', 'spese', 'finanza personale', 'analisi spese', 'vivere in Italia']);
        SEOMeta::setCanonical(url('/'));

        OpenGraph::setTitle('Finanzamente - Tracker e analisi per le tue finanze');
        OpenGraph::setDescription('Registra e analizza le tue finanze personali: transazioni, budget, patrimonio e obiettivi. Per chi vive in Italia. Nessuna connessione bancaria.');
        OpenGraph::setUrl(url('/'));
        OpenGraph::addProperty('type', 'website');
        OpenGraph::addProperty('site_name', 'Finanzamente');
        OpenGraph::addProperty('locale', 'it_IT');

        TwitterCard::setTitle('Finanzamente - Tracker e analisi finanze');
        TwitterCard::setDescription('Tracker di finanze personali con strumenti di analisi, per chi vive in Italia.');
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
