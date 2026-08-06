<?php

namespace App\Services;

use App\Models\InvestmentPac;
use Carbon\Carbon;

class InvestmentPacReminderFormatter
{
    /**
     * @return array{title: string, message: string, amount_formatted: string, asset_name: string}
     */
    public function format(InvestmentPac $pac, Carbon $dueDate): array
    {
        $pac->loadMissing('asset');

        $amountFormatted = number_format((float) $pac->amount, 2, ',', '.').' '.$pac->currency_code;
        $assetName = $pac->asset?->name ?? 'Asset';
        $dueFormatted = $dueDate->format('d/m/Y');
        $feesSuffix = $pac->fees !== null
            ? ' (commissioni '.number_format((float) $pac->fees, 2, ',', '.').' '.$pac->currency_code.')'
            : '';

        return [
            'title' => '📈 PAC in scadenza domani',
            'message' => "Domani ({$dueFormatted}) è previsto un acquisto PAC di {$amountFormatted} su {$assetName}{$feesSuffix}.",
            'amount_formatted' => $amountFormatted,
            'asset_name' => $assetName,
        ];
    }
}
