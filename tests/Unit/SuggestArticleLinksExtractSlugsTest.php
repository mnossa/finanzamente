<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Commands\SuggestArticleLinks;
use PHPUnit\Framework\TestCase;

/**
 * Copre l'estrazione degli slug magazine già linkati in un contenuto markdown,
 * usata da `magazine:link-suggestions` per evitare di suggerire link duplicati.
 */
class SuggestArticleLinksExtractSlugsTest extends TestCase
{
    public function test_extracts_relative_markdown_link(): void
    {
        $md = 'Vedi [questa guida](/magazine/come-risparmiare) per saperne di più.';

        $this->assertSame(['come-risparmiare'], SuggestArticleLinks::extractLinkedSlugs($md));
    }

    public function test_extracts_absolute_markdown_link(): void
    {
        $md = 'Approfondisci su [Finanzamente](https://finanzamente.it/magazine/investire-bene).';

        $this->assertSame(['investire-bene'], SuggestArticleLinks::extractLinkedSlugs($md));
    }

    public function test_extracts_link_with_query_string_and_anchor(): void
    {
        $md = '[a](/magazine/budget-mensile?utm=feed) e [b](/magazine/spese-fisse#sezione-due)';

        $slugs = SuggestArticleLinks::extractLinkedSlugs($md);

        $this->assertEqualsCanonicalizing(
            ['budget-mensile', 'spese-fisse'],
            $slugs
        );
    }

    public function test_extracts_html_anchor_links(): void
    {
        $html = '<p>Leggi <a href="/magazine/tasse-2026">la guida</a> '
            .'oppure <a href=\'https://finanzamente.it/magazine/iva-regime-forfettario?ref=home\'>questa</a>.</p>';

        $slugs = SuggestArticleLinks::extractLinkedSlugs($html);

        $this->assertEqualsCanonicalizing(
            ['tasse-2026', 'iva-regime-forfettario'],
            $slugs
        );
    }

    public function test_deduplicates_and_normalizes_case(): void
    {
        $md = '[a](/magazine/Foo-Bar) [b](/magazine/foo-bar) [c](https://finanzamente.it/magazine/foo-bar?x=1)';

        $slugs = SuggestArticleLinks::extractLinkedSlugs($md);

        $this->assertSame(['foo-bar'], $slugs);
    }

    public function test_ignores_unrelated_links(): void
    {
        $md = '[esterno](https://example.com/articolo) [interno](/dashboard) [contatti](/magazine/) ';

        $this->assertSame([], SuggestArticleLinks::extractLinkedSlugs($md));
    }
}
