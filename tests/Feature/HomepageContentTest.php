<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Copertura del copy e dei CTA della homepage pubblica (build OSS).
 *
 * Il ton of voice della home non deve né alludere a operazioni bancarie né
 * negarle in modo difensivo: il tema non va proprio nominato.
 */
class HomepageContentTest extends TestCase
{
    use RefreshDatabase;

    // ═══════════════════════════════════════════════════════════════
    // TON OF VOICE
    // ═══════════════════════════════════════════════════════════════

    /**
     * Sia le allusioni sia le negazioni difensive sono vietate nel copy della home.
     *
     * @return list<array{string}>
     */
    public static function forbiddenVocabularyProvider(): array
    {
        return [
            'aggettivo bancario' => ['bancari'],
            'sostantivo banca' => ['banca'],
            'plurale banche' => ['banche'],
            'operazioni bancarie' => ['operazione bancaria'],
            'conto da collegare' => ['conto da collegare'],
            'collegamento conti' => ['collegare il conto'],
            'sincronizzazione' => ['sincronizzazione'],
            'open banking' => ['open banking'],
            'estratto conto' => ['estratto conto'],
        ];
    }

    #[Test]
    #[DataProvider('forbiddenVocabularyProvider')]
    public function test_homepage_copy_avoids_banking_vocabulary(string $forbidden): void
    {
        $content = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsStringIgnoringCase(
            $forbidden,
            $content,
            "La homepage non deve contenere \"{$forbidden}\": il tema delle operazioni bancarie non va né evocato né negato."
        );
    }

    #[Test]
    public function test_homepage_shows_the_new_positioning_headline(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Le spese di oggi e il patrimonio di domani', false)
            ->assertSee('app di finanza personale open source', false)
            ->assertSee('per chi vive in Italia', false);
    }

    #[Test]
    public function test_homepage_shows_the_four_pillars(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('Quattro aree, un unico quadro', false);
        $response->assertSee('Registra senza perderci tempo', false);
        $response->assertSee('Decidi prima, non a fine mese', false);
        $response->assertSee('Guarda oltre il mese corrente', false);
        $response->assertSee('Conti chiari anche in due', false);
    }

    #[Test]
    public function test_homepage_highlights_the_differentiating_sections(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('Quanto vali, non solo quanto spendi', false);
        $response->assertSee('La dashboard te la costruisci tu', false);
        $response->assertSee('I dettagli che fanno la differenza', false);
    }

    #[Test]
    public function test_homepage_keeps_the_anchors_used_by_the_footer(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('id="funzionalita"', false);
        $response->assertSee('id="come-funziona"', false);
    }

    // ═══════════════════════════════════════════════════════════════
    // FAQ E DATI STRUTTURATI
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function test_homepage_renders_the_faq_section(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertViewHas('faqs');
        $response->assertSee('Domande frequenti', false);
        $response->assertSee('Cosa posso fare con Finanzamente?', false);
        $response->assertSee('Devo inserire ogni spesa a mano?', false);
    }

    #[Test]
    public function test_homepage_exposes_faq_page_structured_data(): void
    {
        $content = $this->get('/')->assertOk()->getContent();

        // Le asserzioni restano indipendenti dalla formattazione del JSON-LD.
        $this->assertStringContainsString('FAQPage', $content);
        $this->assertStringContainsString('acceptedAnswer', $content);
        $this->assertStringContainsString('SoftwareApplication', $content);
    }

    #[Test]
    public function test_every_faq_answer_is_rendered(): void
    {
        $response = $this->get('/')->assertOk();

        /** @var list<array{question: string, answer: string}> $faqs */
        $faqs = $response->viewData('faqs');

        $this->assertNotEmpty($faqs);

        foreach ($faqs as $faq) {
            $response->assertSee($faq['question'], true);
            $response->assertSee($faq['answer'], true);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // CTA REGISTRAZIONE (OSS: nessun waitlist / prelaunch / piani)
    // ═══════════════════════════════════════════════════════════════

    #[Test]
    public function test_homepage_shows_registration_ctas_when_register_route_exists(): void
    {
        if (! Route::has('register')) {
            $this->markTestSkipped('Route register non disponibile in questo ambiente.');
        }

        $response = $this->get('/')->assertOk();

        $response->assertSee(route('register'), false);
        $response->assertSee('Inizia gratis', false);
        $response->assertSee('Crea un account gratuito', false);
    }
}
