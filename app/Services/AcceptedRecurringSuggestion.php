<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RecurringTransaction;

/** Esito accettazione suggerimento ricorrenza (servizio + redirect UI). */
final class AcceptedRecurringSuggestion
{
    public function __construct(
        public readonly RecurringTransaction $recurring,
        public readonly int $removedFutureTransactionCount,
        public readonly int $alignedTransactionCount = 0,
    ) {}
}
