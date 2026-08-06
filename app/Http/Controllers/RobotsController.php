<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Restituisce robots.txt in modo dinamico così da includere
 * l'URL assoluto corretto della sitemap tramite APP_URL.
 */
class RobotsController extends Controller
{
    public function index(): Response
    {
        $content = view('robots')->render();

        return response($content, 200, ['Content-Type' => 'text/plain']);
    }
}
