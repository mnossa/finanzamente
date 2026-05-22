<?php

namespace Tests\Unit;

use App\Support\TransactionRegexSearchValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransactionRegexSearchValidatorTest extends TestCase
{
    #[Test]
    public function accepts_valid_patterns(): void
    {
        $this->assertSame('carte|pos', TransactionRegexSearchValidator::validate('carte|pos'));
        $this->assertSame('^Bolletta', TransactionRegexSearchValidator::validate('^Bolletta'));
        $this->assertSame('ess.*unga', TransactionRegexSearchValidator::validate('ess.*unga'));
    }

    #[Test]
    public function rejects_empty_or_too_long_patterns(): void
    {
        $this->assertNull(TransactionRegexSearchValidator::validate(''));
        $this->assertNull(TransactionRegexSearchValidator::validate('   '));
        $this->assertNull(TransactionRegexSearchValidator::validate(str_repeat('a', 121)));
    }

    #[Test]
    public function rejects_invalid_or_dangerous_patterns(): void
    {
        $this->assertNull(TransactionRegexSearchValidator::validate('['));
        $this->assertNull(TransactionRegexSearchValidator::validate('(?{evil})'));
        $this->assertNull(TransactionRegexSearchValidator::validate('(a+)+'));
    }
}
