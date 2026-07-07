<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileDataExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_data_export_requires_password_confirmation(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this->actingAs($user)->get('/profilo/export-dati');

        $response->assertRedirect(route('password.confirm'));
    }

    public function test_data_export_returns_structured_json_after_password_confirmation(): void
    {
        $user = $this->createUserWithActiveHousehold();

        app(ConsentService::class)->setConsent($user, 'marketing_email', 'granted', [
            'source' => 'test',
            'legal_basis' => 'consent',
            'policy_version' => config('legal.privacy_policy_version'),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/profilo/export-dati');

        $response->assertOk();
        $response->assertHeader('content-disposition');

        $payload = json_decode($response->streamedContent(), true);
        $this->assertSame('1.0', $payload['export_version']);
        $this->assertSame($user->email, $payload['user']['email']);
        $this->assertCount(1, $payload['households']);
        $this->assertNotEmpty($payload['consents']);
    }

    private function createUserWithActiveHousehold(): User
    {
        $user = User::factory()->create(['profile_completed' => true]);

        $household = Household::create([
            'name' => 'Household Export',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);

        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true, 'supervise' => true]),
        ]);

        $user->update(['active_household_id' => $household->id]);

        return $user->fresh();
    }
}
