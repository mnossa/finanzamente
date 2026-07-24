<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garantisce che i nomi di rotta usati in resources/js/utils/mobilePrimaryFab.ts
 * esistano ancora dopo refactor delle rotte.
 */
class MobilePrimaryFabRoutesExistTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_mobile_primary_fab_target_routes_are_registered(): void
    {
        $names = [
            'transactions.create',
            'accounts.create',
            'categories.create',
            'budgets.create',
            'financial-goals.create',
            'recurring-transactions.create',
            'tags.create',
            'debts-credits.create',
            'transfers.create',
            'refunds.create',
            'inter-household-transfers.create',
            'investments.create',
            'investment-pacs.create',
            'investment-assets.create',
            'formula-widgets.create',
            'households.create',
        ];

        foreach ($names as $name) {
            $this->assertTrue(Route::has($name), 'Route mancante per mobile FAB: '.$name);
        }
    }

    #[Test]
    public function test_guided_create_route_names_are_registered(): void
    {
        // Allineato a resources/js/utils/guidedCreate.ts GUIDED_CREATE_ROUTE_NAMES
        $names = [
            'transactions.create',
            'accounts.create',
            'categories.create',
            'tags.create',
            'budgets.create',
            'financial-goals.create',
            'recurring-transactions.create',
            'debts-credits.create',
            'transfers.create',
            'refunds.create',
            'inter-household-transfers.create',
            'investments.create',
            'investment-assets.create',
            'households.create',
        ];

        foreach ($names as $name) {
            $this->assertTrue(Route::has($name), 'Route mancante per guided create chrome: '.$name);
        }
    }
}
