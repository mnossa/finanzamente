<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CohortInsightsAnalyzeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_in_app_notification_from_python_insights(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-12 10:00:00'));
        Config::set('cohort_insights.enabled', true);

        $user = $this->seedUserWithAprilSpending();

        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, '/health')) {
                return Http::response(['status' => 'ok'], 200);
            }
            if (str_contains($url, '/cohort-insights/analyze')) {
                $body = json_decode($request->body(), true);
                $ref = $body['rows'][0]['subject_ref'] ?? '';

                return Http::response([
                    'insights' => [[
                        'subject_ref' => $ref,
                        'insight_code' => 'cohort_wants_share_above_median',
                        'params' => ['approx_diff_range' => '15-25'],
                    ]],
                ], 200);
            }

            return Http::response('not found', 404);
        });

        $this->artisan('insights:cohort-analyze', ['--period' => '2026-04'])->assertSuccessful();

        $n = AppNotification::where('user_id', $user->id)->first();
        $this->assertNotNull($n);
        $this->assertStringContainsString('Extra', $n->message);
        $this->assertSame('cohort_wants_share_2026-04_'.$user->id, $n->notification_key);

        $this->artisan('insights:cohort-analyze', ['--period' => '2026-04'])->assertSuccessful();
        $this->assertSame(1, AppNotification::where('user_id', $user->id)->count());

        Carbon::setTestNow();
    }

    public function test_command_fails_when_python_health_unreachable(): void
    {
        Config::set('cohort_insights.enabled', true);
        $this->seedUserWithAprilSpending();

        Http::fake([
            '*' => Http::response('', 503),
        ]);

        $this->artisan('insights:cohort-analyze', ['--period' => '2026-04'])->assertFailed();
    }

    public function test_command_skips_when_disabled(): void
    {
        Config::set('cohort_insights.enabled', false);
        Http::fake();

        $this->artisan('insights:cohort-analyze')->assertSuccessful();
        Http::assertNothingSent();
    }

    private function seedUserWithAprilSpending(): User
    {
        $user = User::factory()->create([
            'income_band' => '35k_50k',
            'macro_region' => 'centro',
            'profile_completed' => true,
        ]);

        $household = Household::create([
            'name' => 'HH Cohort',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true, 'supervise' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        $account = Account::create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'name' => 'Conto',
            'type' => 'bank',
            'currency_code' => 'EUR',
            'initial_balance' => 0,
            'active' => true,
        ]);

        $needs = Category::factory()->expense()->create([
            'household_id' => $household->id,
            'name' => 'Bisogni',
            'expense_distribution' => Category::DISTRIBUTION_NEEDS,
        ]);
        $wants = Category::factory()->expense()->create([
            'household_id' => $household->id,
            'name' => 'Extra',
            'expense_distribution' => Category::DISTRIBUTION_WANTS,
        ]);

        foreach ([
            [$needs->id, -500],
            [$wants->id, -500],
        ] as [$catId, $amount]) {
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $catId,
                'amount' => $amount,
                'currency_code' => 'EUR',
                'exchange_rate_to_base' => 1,
                'amount_base' => abs($amount),
                'date' => '2026-04-10',
                'is_private' => false,
            ]);
        }

        return $user->fresh();
    }
}
