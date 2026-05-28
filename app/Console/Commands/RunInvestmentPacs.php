<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Models\InvestmentPac;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RunInvestmentPacs extends Command
{
    protected $signature = 'investment-pacs:run';

    protected $description = 'Esegue i PAC mensili attivi e genera investimenti';

    public function handle(): int
    {
        $today = Carbon::today();
        $count = 0;

        $pacs = InvestmentPac::where('status', 'active')
            ->whereDate('start_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->get();

        foreach ($pacs as $pac) {
            $alreadyRunThisMonth = $pac->last_executed_at && Carbon::parse($pac->last_executed_at)->isSameMonth($today);
            if ($alreadyRunThisMonth) {
                continue;
            }

            $this->applyInflationIfDue($pac, $today);

            Investment::create([
                'user_id' => $pac->user_id,
                'household_id' => $pac->household_id,
                'account_id' => $pac->account_id,
                'asset_id' => $pac->investment_asset_id,
                'quantity' => 1,
                'buy_price' => (float) $pac->amount,
                'buy_date' => $today->toDateString(),
                'notes' => trim('PAC automatico'.($pac->notes ? ' - '.$pac->notes : '')),
                'is_private' => false,
            ]);

            $pac->update(['last_executed_at' => $today->toDateString()]);
            $count++;
        }

        $this->info("PAC eseguiti: {$count}");

        return self::SUCCESS;
    }

    private function applyInflationIfDue(InvestmentPac $pac, Carbon $today): void
    {
        if (! $pac->adjust_for_inflation || $pac->inflation_rate_annual === null) {
            return;
        }

        $anchor = $pac->last_inflation_adjusted_at ?? $pac->start_date;
        if (Carbon::parse($anchor)->diffInYears($today) < 1) {
            return;
        }

        $pac->amount = round((float) $pac->amount * (1 + ((float) $pac->inflation_rate_annual / 100)), 2);
        $pac->last_inflation_adjusted_at = $today->toDateString();
        $pac->save();
    }
}
