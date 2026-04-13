<?php

namespace Tests\Feature;

use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $mockPlanService = Mockery::mock(PlanService::class);
        $mockPlanService->shouldReceive('getPlansForFrontend')->andReturn([
            'base' => [
                'key'      => 'base',
                'name'     => 'Base',
                'label'    => 'Piano Base',
                'features' => ['Dashboard', 'Budget'],
                'price_monthly' => 0,
                'price_annual_monthly' => 0,
                'price_annual_total' => 0,
                'annual_discount_percent' => 20,
                'currency' => 'EUR',
                'available' => true,
            ],
            'pro' => [
                'key'      => 'pro',
                'name'     => 'Pro',
                'label'    => 'Piano Pro',
                'features' => ['Investimenti', 'Asset Allocation'],
                'price_monthly' => 2.99,
                'price_annual_monthly' => 2.39,
                'price_annual_total' => 28.69,
                'annual_discount_percent' => 20,
                'currency' => 'EUR',
                'available' => false,
            ],
        ]);
        $mockPlanService->shouldReceive('isProEnabled')->andReturn(false);
        $mockPlanService->shouldReceive('getAnnualDiscountPercent')->andReturn(20);

        $this->app->instance(PlanService::class, $mockPlanService);
    }

    public static function landingRouteProvider(): array
    {
        return [
            'investitori' => ['/per-investitori', 'landing.investitori', 'Per chi investe'],
            'famiglie' => ['/per-famiglie', 'landing.famiglie', 'famiglie e coppie'],
            'freelance' => ['/per-freelance', 'landing.freelance', 'Freelance'],
            'lavoratori' => ['/per-lavoratori', 'landing.lavoratori', 'lavoratori'],
            'pianificatori' => ['/per-pianificatori', 'landing.pianificatori', 'pianificatori'],
            'tech-savvy' => ['/per-tech-savvy', 'landing.tech-savvy', 'tech-savvy'],
            'crescita' => ['/crescita-personale', 'landing.crescita', 'crescita'],
        ];
    }

    #[Test]
    #[DataProvider('landingRouteProvider')]
    public function landing_pages_are_accessible(string $url, string $routeName, string $keyword): void
    {
        $response = $this->get($url);

        $response->assertStatus(200);
    }

    #[Test]
    #[DataProvider('landingRouteProvider')]
    public function landing_pages_have_named_routes(string $url, string $routeName, string $keyword): void
    {
        $this->assertNotNull(route($routeName));
        $this->assertEquals(url($url), route($routeName));
    }

    #[Test]
    public function landing_pages_are_accessible_to_guests(): void
    {
        $urls = [
            '/per-investitori',
            '/per-famiglie',
            '/per-freelance',
            '/per-lavoratori',
            '/per-pianificatori',
            '/per-tech-savvy',
            '/crescita-personale',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertStatus(200);
        }
    }
}
