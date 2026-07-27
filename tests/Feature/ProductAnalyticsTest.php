<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\ProductAnalyticsDaily;
use App\Models\RetentionPolicy;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER_EMAIL = 'owner@example.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['prelaunch.magazine_admin_email' => self::OWNER_EMAIL]);
    }

    private function owner(): User
    {
        return User::factory()->create([
            'email' => self::OWNER_EMAIL,
            'email_verified_at' => now(),
        ]);
    }

    private function regularUser(): User
    {
        return User::factory()->create([
            'email' => 'utente@example.com',
            'email_verified_at' => now(),
        ]);
    }

    private function grantAnalyticsConsent(User $user): void
    {
        Consent::query()->create([
            'user_id' => $user->id,
            'purpose' => 'analytics_tracking',
            'status' => 'granted',
            'source' => 'test',
            'legal_basis' => 'consent',
            'policy_version' => config('legal.privacy_policy_version'),
        ]);
    }

    #[Test]
    public function ingest_requires_authentication(): void
    {
        $this->postJson(route('product-analytics.ingest'), [
            'events' => [['name' => 'nav.bottom_bar', 'data' => ['destination' => 'dashboard']]],
        ])->assertUnauthorized();
    }

    #[Test]
    public function ingest_requires_analytics_consent(): void
    {
        $user = $this->regularUser();

        $this->actingAs($user)
            ->postJson(route('product-analytics.ingest'), [
                'events' => [['name' => 'nav.bottom_bar', 'data' => ['destination' => 'dashboard']]],
            ])
            ->assertOk()
            ->assertJson(['accepted' => 0, 'reason' => 'consent_required']);

        $this->assertDatabaseCount('product_analytics_daily', 0);
    }

    #[Test]
    public function ingest_stores_sanitized_aggregates_with_consent(): void
    {
        $user = $this->regularUser();
        $this->grantAnalyticsConsent($user);

        $this->actingAs($user)
            ->postJson(route('product-analytics.ingest'), [
                'events' => [
                    [
                        'name' => 'form.abandoned',
                        'data' => [
                            'form' => 'transaction.create',
                            'form_seconds' => 20,
                            'email' => 'should-strip@example.com',
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['accepted' => 1]);

        $this->assertDatabaseHas('product_analytics_daily', [
            'event_name' => 'form.abandoned',
            'event_kind' => 'friction',
            'feature_key' => 'transaction',
            'event_count' => 1,
        ]);

        $row = ProductAnalyticsDaily::query()->first();
        $this->assertArrayNotHasKey('email', $row->dimensions ?? []);
    }

    #[Test]
    public function admin_dashboard_forbidden_for_non_owner(): void
    {
        $this->actingAs($this->regularUser())
            ->get(route('admin.product-analytics.index'))
            ->assertForbidden();
    }

    #[Test]
    public function admin_dashboard_ok_for_owner(): void
    {
        ProductAnalyticsDaily::query()->create([
            'day' => now()->toDateString(),
            'event_kind' => 'used',
            'feature_key' => 'transaction',
            'event_name' => 'transaction.created',
            'dimensions_hash' => '',
            'dimensions' => null,
            'event_count' => 5,
        ]);

        $this->actingAs($this->owner())
            ->get(route('admin.product-analytics.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/ProductAnalytics/Index')
                ->has('analytics.top_features')
                ->where('analytics.top_features.0.feature_key', 'transaction')
            );
    }

    #[Test]
    public function retention_command_purges_old_aggregates(): void
    {
        $policy = RetentionPolicy::query()
            ->where('policy_key', 'product_analytics_daily')
            ->first();

        $this->assertNotNull($policy, 'Migration must seed product_analytics_daily retention policy');
        $policy->update([
            'retention_days' => 30,
            'is_active' => true,
        ]);

        ProductAnalyticsDaily::query()->create([
            'day' => now()->subDays(40)->toDateString(),
            'event_kind' => 'used',
            'feature_key' => 'old',
            'event_name' => 'old.event',
            'dimensions_hash' => '',
            'dimensions' => null,
            'event_count' => 1,
        ]);

        ProductAnalyticsDaily::query()->create([
            'day' => now()->subDays(5)->toDateString(),
            'event_kind' => 'used',
            'feature_key' => 'new',
            'event_name' => 'new.event',
            'dimensions_hash' => '',
            'dimensions' => null,
            'event_count' => 1,
        ]);

        $this->artisan('product-analytics:enforce-retention')
            ->expectsOutput('Deleted daily aggregates: 1')
            ->assertExitCode(0);

        $this->assertDatabaseCount('product_analytics_daily', 1);
        $this->assertDatabaseHas('product_analytics_daily', ['feature_key' => 'new']);
    }
}
