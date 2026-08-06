<?php

namespace Tests\Unit;

use App\Support\TransactionSearchTokens;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransactionSearchTokensTest extends TestCase
{
    #[Test]
    public function it_extracts_significant_tokens_and_skips_stopwords(): void
    {
        $tokens = TransactionSearchTokens::fromQuery('il supermercato della coop');

        $this->assertSame(['supermercato', 'coop'], $tokens);
    }

    #[Test]
    public function it_returns_empty_for_blank_query(): void
    {
        $this->assertSame([], TransactionSearchTokens::fromQuery(''));
        $this->assertSame([], TransactionSearchTokens::fromQuery(null));
    }
}
