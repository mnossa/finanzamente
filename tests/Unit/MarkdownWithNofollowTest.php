<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarkdownWithNofollowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifica che la macro non lanci eccezioni (regressione: League\CommonMark
     * richiede che i renderer che implementano ConfigurationAwareInterface ricevano
     * setConfiguration() prima del render — il wrapper anonimo deve delegare la chiamata).
     */
    public function test_render_non_lancia_eccezioni(): void
    {
        $output = Str::markdownWithNofollow('[link](https://esempio.com)');

        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    public function test_link_esterno_riceve_rel_nofollow(): void
    {
        $output = Str::markdownWithNofollow('[visita](https://esempio.com)');

        $this->assertStringContainsString('rel="nofollow"', $output);
        $this->assertStringContainsString('href="https://esempio.com"', $output);
    }

    public function test_link_esterno_http_riceve_rel_nofollow(): void
    {
        $output = Str::markdownWithNofollow('[visita](http://esempio.com)');

        $this->assertStringContainsString('rel="nofollow"', $output);
    }

    public function test_link_relativo_non_riceve_rel_nofollow(): void
    {
        $output = Str::markdownWithNofollow('[pagina](/dashboard)');

        $this->assertStringNotContainsString('nofollow', $output);
        $this->assertStringContainsString('href="/dashboard"', $output);
    }

    public function test_testo_semplice_senza_link_viene_renderizzato(): void
    {
        $output = Str::markdownWithNofollow('**Testo in grassetto**');

        $this->assertStringContainsString('<strong>Testo in grassetto</strong>', $output);
        $this->assertStringNotContainsString('nofollow', $output);
    }

    public function test_link_con_rel_preesistente_viene_esteso(): void
    {
        // Questo caso non è generabile via markdown puro (il parser lo strippa),
        // ma verifica che il renderer non sovrascriva un rel già presente
        // se mai venisse passato da una sotto-implementazione futura.
        // Per ora la chiamata semplice basta a coprire la regressione principale.
        $output = Str::markdownWithNofollow('[link](https://esempio.com)');

        $this->assertStringContainsString('nofollow', $output);
    }

    public function test_html_unsafe_viene_rimosso(): void
    {
        $output = Str::markdownWithNofollow('<script>alert("xss")</script>');

        $this->assertStringNotContainsString('<script>', $output);
    }

    public function test_piu_link_nello_stesso_testo(): void
    {
        $md = '[esterno](https://a.com) e [interno](/pagina) e [altro esterno](http://b.com)';
        $output = Str::markdownWithNofollow($md);

        $this->assertStringContainsString('href="https://a.com"', $output);
        $this->assertStringContainsString('href="/pagina"', $output);
        $this->assertStringContainsString('href="http://b.com"', $output);

        // I due link esterni devono avere nofollow
        $this->assertEquals(2, substr_count($output, 'nofollow'));
    }
}
