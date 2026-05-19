<?php

namespace App\Services;

/**
 * Esito riconciliazione transazioni collegate a una ricorrenza.
 */
class RecurringReconcileResult
{
    public function __construct(
        public int $created = 0,
        public int $removed = 0,
    ) {}

    public function totalChanges(): int
    {
        return $this->created + $this->removed;
    }
}
