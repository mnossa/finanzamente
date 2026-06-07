<?php

namespace Tests\Unit;

use App\Models\InvestmentAsset;
use App\Services\AssetClassificationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AssetClassificationServiceTest extends TestCase
{
    #[Test]
    public function sp500_etf_is_classified_as_equities(): void
    {
        $asset = new InvestmentAsset([
            'type' => 'etf',
            'name' => 'iShares Core S&P 500 UCITS ETF',
            'symbol' => 'CSSPX.MI',
        ]);

        $this->assertSame('equities', AssetClassificationService::resolveInvestmentAssetClass($asset));
    }

    #[Test]
    public function bond_etf_is_classified_as_bonds(): void
    {
        $asset = new InvestmentAsset([
            'type' => 'etf',
            'name' => 'iShares Core Global Aggregate Bond UCITS ETF',
            'symbol' => 'AGGH.MI',
        ]);

        $this->assertSame('bonds', AssetClassificationService::resolveInvestmentAssetClass($asset));
    }

    #[Test]
    public function manual_override_takes_precedence(): void
    {
        $asset = new InvestmentAsset([
            'type' => 'etf',
            'name' => 'iShares Core S&P 500 UCITS ETF',
            'symbol' => 'CSSPX.MI',
            'allocation_asset_class' => 'bonds',
        ]);

        $this->assertSame('bonds', AssetClassificationService::resolveInvestmentAssetClass($asset));
    }
}
