<?php

namespace Tests\Unit;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase as BaseTestCase;

class HouseholdTest extends BaseTestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_household_with_financial_management_type()
    {
        $user = User::factory()->create();
        
        $household = Household::create([
            'name' => 'Test Household',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);

        $this->assertEquals(Household::FINANCIAL_MANAGEMENT_SHARED_WALLET, $household->financial_management_type);
        $this->assertEquals('Portafoglio Comune', $household->getFinancialManagementTypeLabel());
    }

    #[Test]
    public function it_defaults_to_shared_wallet_mode()
    {
        $user = User::factory()->create();
        
        $household = Household::create([
            'name' => 'Test Household',
            'owner_user_id' => $user->id,
            // Non specifico il financial_management_type per testare il default
        ]);

        $household->refresh();

        // Il default dovrebbe essere impostato dalla migration
        $this->assertNotNull($household->financial_management_type);
    }

    #[Test]
    public function it_can_check_if_household_is_debt_balancing_mode()
    {
        $user = User::factory()->create();
        
        $household = Household::create([
            'name' => 'Test Household',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING,
        ]);

        $this->assertTrue($household->isDebtBalancingMode());
        $this->assertFalse($household->isSharedWalletMode());
    }

    #[Test]
    public function it_can_check_if_household_is_shared_wallet_mode()
    {
        $user = User::factory()->create();
        
        $household = Household::create([
            'name' => 'Test Household',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);

        $this->assertTrue($household->isSharedWalletMode());
        $this->assertFalse($household->isDebtBalancingMode());
    }

    #[Test]
    public function it_returns_correct_financial_management_type_labels()
    {
        $user = User::factory()->create();
        
        $sharedWalletHousehold = Household::create([
            'name' => 'Shared Wallet Household',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);

        $debtBalancingHousehold = Household::create([
            'name' => 'Debt Balancing Household',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING,
        ]);

        $this->assertEquals('Portafoglio Comune', $sharedWalletHousehold->getFinancialManagementTypeLabel());
        $this->assertEquals('Bilanciamento Debiti', $debtBalancingHousehold->getFinancialManagementTypeLabel());
    }

    #[Test]
    public function it_has_correct_financial_management_types_constants()
    {
        $this->assertEquals('shared_wallet', Household::FINANCIAL_MANAGEMENT_SHARED_WALLET);
        $this->assertEquals('debt_balancing', Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING);
        
        $expectedTypes = [
            'debt_balancing' => 'Bilanciamento Debiti',
            'shared_wallet' => 'Portafoglio Comune',
        ];
        
        $this->assertEquals($expectedTypes, Household::FINANCIAL_MANAGEMENT_TYPES);
    }

    #[Test]
    public function it_can_set_and_get_balance_percentages()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $household = Household::create([
            'name' => 'Test Household',
            'owner_user_id' => $user1->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING,
        ]);

        // Aggiungi utenti alla household
        $household->users()->attach([
            $user1->id => ['role' => 'owner', 'permissions' => json_encode(['manage' => true])],
            $user2->id => ['role' => 'member', 'permissions' => json_encode([])],
        ]);

        // Imposta percentuali personalizzate
        $percentages = [$user1->id => 70.0, $user2->id => 30.0];
        $household->setBalancePercentages($percentages);

        $this->assertEquals($percentages, $household->getBalancePercentages());
        $this->assertEquals(70.0, $household->getUserBalance($user1->id));
        $this->assertEquals(30.0, $household->getUserBalance($user2->id));
        $this->assertTrue($household->hasCustomBalancePercentages());
    }

    #[Test]
    public function it_calculates_equal_percentages_when_none_set()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        
        $household = Household::create([
            'name' => 'Test Household',
            'owner_user_id' => $user1->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING,
        ]);

        // Aggiungi utenti alla household
        $household->users()->attach([
            $user1->id => ['role' => 'owner', 'permissions' => json_encode(['manage' => true])],
            $user2->id => ['role' => 'member', 'permissions' => json_encode([])],
            $user3->id => ['role' => 'member', 'permissions' => json_encode([])],
        ]);

        $percentages = $household->getBalancePercentages();
        
        // Deve dividere equamente tra 3 utenti (33.33 + 33.33 + 33.34 = 100)
        $total = array_sum($percentages);
        $this->assertEquals(100, round($total, 2));
        $this->assertCount(3, $percentages);
        $this->assertFalse($household->hasCustomBalancePercentages());
    }

    #[Test]
    public function it_validates_balance_percentages_sum_to_100()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $household = Household::create([
            'name' => 'Test Household',
            'owner_user_id' => $user1->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING,
        ]);

        $household->users()->attach([
            $user1->id => ['role' => 'owner', 'permissions' => json_encode(['manage' => true])],
            $user2->id => ['role' => 'member', 'permissions' => json_encode([])],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le percentuali devono sommare esattamente al 100%');

        // Percentuali che non sommano a 100
        $invalidPercentages = [$user1->id => 60.0, $user2->id => 30.0]; // Somma = 90
        $household->setBalancePercentages($invalidPercentages);
    }

    #[Test]
    public function it_validates_users_belong_to_household_for_percentages()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create(); // Non nella household
        
        $household = Household::create([
            'name' => 'Test Household',
            'owner_user_id' => $user1->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING,
        ]);

        $household->users()->attach([
            $user1->id => ['role' => 'owner', 'permissions' => json_encode(['manage' => true])],
            $user2->id => ['role' => 'member', 'permissions' => json_encode([])],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("L'utente {$user3->id} non appartiene a questa household");

        // Percentuali per utente non nella household
        $invalidPercentages = [$user1->id => 50.0, $user3->id => 50.0];
        $household->setBalancePercentages($invalidPercentages);
    }

    #[Test]
    public function it_validates_balance_percentages_correctly()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $household = Household::create([
            'name' => 'Test Household',
            'owner_user_id' => $user1->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING,
        ]);

        $household->users()->attach([
            $user1->id => ['role' => 'owner', 'permissions' => json_encode(['manage' => true])],
            $user2->id => ['role' => 'member', 'permissions' => json_encode([])],
        ]);

        // Percentuali valide
        $validPercentages = [$user1->id => 70.0, $user2->id => 30.0];
        $household->setBalancePercentages($validPercentages);
        $this->assertTrue($household->areBalancePercentagesValid());

        // Household con shared_wallet deve sempre essere valida
        $sharedHousehold = Household::create([
            'name' => 'Shared Household',
            'owner_user_id' => $user1->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);
        $this->assertTrue($sharedHousehold->areBalancePercentagesValid());
    }
}
