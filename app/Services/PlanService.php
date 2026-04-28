<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;

class PlanService
{
    /**
     * Restituisce tutti i piani configurati.
     */
    public function getPlans(): array
    {
        return Config::get('plans.plans', []);
    }

    /**
     * Restituisce la definizione di un singolo piano.
     */
    public function getPlan(string $plan): ?array
    {
        return Config::get("plans.plans.{$plan}");
    }

    /**
     * Verifica se l'acquisto del piano Pro è abilitato.
     */
    public function isProEnabled(): bool
    {
        return (bool) Config::get('plans.pro_enabled', true);
    }

    /**
     * Restituisce la percentuale di sconto per il piano annuale.
     */
    public function getAnnualDiscountPercent(): int
    {
        return (int) Config::get('plans.annual_discount_percent', 20);
    }

    /**
     * Calcola il prezzo mensile del piano annuale dopo lo sconto.
     * Restituisce il prezzo in centesimi.
     */
    public function getAnnualMonthlyCents(string $plan): int
    {
        $planConfig = $this->getPlan($plan);
        if (! $planConfig) {
            return 0;
        }

        $monthly = $planConfig['price_monthly_cents'];
        $discount = $this->getAnnualDiscountPercent();

        return (int) round($monthly * (1 - $discount / 100));
    }

    /**
     * Calcola il totale annuale in centesimi.
     */
    public function getAnnualTotalCents(string $plan): int
    {
        return $this->getAnnualMonthlyCents($plan) * 12;
    }

    /**
     * Restituisce il prezzo effettivo in centesimi per il piano e ciclo scelti.
     */
    public function getPriceCents(string $plan, string $billingCycle): int
    {
        if ($billingCycle === 'annual') {
            return $this->getAnnualTotalCents($plan);
        }

        return (int) ($this->getPlan($plan)['price_monthly_cents'] ?? 0);
    }

    /**
     * Verifica se un piano esiste.
     */
    public function planExists(string $plan): bool
    {
        return ! is_null($this->getPlan($plan));
    }

    /**
     * Restituisce i dati del piano formattati per il frontend (prezzi in euro).
     */
    public function getPlansForFrontend(): array
    {
        $plans = $this->getPlans();
        $discount = $this->getAnnualDiscountPercent();
        $proEnabled = $this->isProEnabled();

        $result = [];
        foreach ($plans as $key => $plan) {
            $monthlyCents = $plan['price_monthly_cents'];
            $annualMonthlyCents = $monthlyCents > 0
                ? (int) round($monthlyCents * (1 - $discount / 100))
                : 0;

            $result[$key] = [
                'key' => $key,
                'name' => $plan['name'],
                'label' => $plan['label'],
                'price_monthly' => $monthlyCents / 100,
                'price_annual_monthly' => $annualMonthlyCents / 100,
                'price_annual_total' => ($annualMonthlyCents * 12) / 100,
                'annual_discount_percent' => $discount,
                'currency' => $plan['currency'],
                'features' => $plan['features'],
                'available' => $key === 'base' || $proEnabled,
            ];
        }

        return $result;
    }
}
