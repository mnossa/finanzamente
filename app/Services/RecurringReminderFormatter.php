<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use Carbon\Carbon;

/**
 * Testi promemoria ricorrenze (in-app ed email).
 */
class RecurringReminderFormatter
{
    /**
     * @return array{title: string, message: string, direction: string, direction_label: string, category_name: string, description: string, amount_formatted: string}
     */
    public function format(RecurringTransaction $recurring, Carbon $dueDate): array
    {
        $recurring->loadMissing('category');

        $isIncome = $this->isIncome($recurring);
        $direction = $isIncome ? 'income' : 'expense';
        $directionLabel = $isIncome ? 'entrata' : 'uscita';
        $categoryName = $recurring->category?->name ?? 'Senza categoria';
        $description = $recurring->description ?: 'Senza descrizione';
        $amountFormatted = number_format(abs((float) $recurring->amount), 2, ',', '.').' €';

        $dueFormatted = $dueDate->format('d/m/Y');

        $message = "Domani ({$dueFormatted}) è prevista un'{$directionLabel} di {$amountFormatted} "
            ."in categoria {$categoryName} — causale: {$description}.";

        return [
            'title' => '🔁 Ricorrenza in scadenza domani',
            'message' => $message,
            'direction' => $direction,
            'direction_label' => $directionLabel,
            'category_name' => $categoryName,
            'description' => $description,
            'amount_formatted' => $amountFormatted,
        ];
    }

    public function isIncome(RecurringTransaction $recurring): bool
    {
        if ((float) $recurring->amount > 0) {
            return true;
        }

        if ((float) $recurring->amount < 0) {
            return false;
        }

        return $recurring->category?->type === 'income';
    }
}
