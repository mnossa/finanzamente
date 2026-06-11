<?php

namespace Tests\Feature;

use App\Models\FinancialVariable;
use App\Models\Household;
use App\Models\User;
use App\Services\FormulaResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancialVariableFormulaValidationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithActiveHousehold(): User
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        return $user->fresh();
    }

    #[Test]
    public function store_rejects_self_referencing_formula(): void
    {
        $user = $this->userWithActiveHousehold();

        $this->actingAs($user)
            ->postJson(route('formula-variables.store'), [
                'name' => 'Saldo ricorsivo',
                'code' => 'saldo_ricorsivo',
                'type' => FinancialVariable::TYPE_FORMULA,
                'formula_string' => '[saldo_ricorsivo]',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['formula_string']);
    }

    #[Test]
    public function store_rejects_formula_with_unknown_token(): void
    {
        $user = $this->userWithActiveHousehold();

        $this->actingAs($user)
            ->postJson(route('formula-variables.store'), [
                'name' => 'Invalida',
                'type' => FinancialVariable::TYPE_FORMULA,
                'formula_string' => '[codice_che_non_esiste]',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['formula_string']);
    }

    #[Test]
    public function dependency_validation_returns_422_status(): void
    {
        $user = User::factory()->create();

        FinancialVariable::factory()->for($user)->formula('[var_b]')->create(['code' => 'var_a', 'name' => 'A']);
        FinancialVariable::factory()->for($user)->formula('[var_a]')->create(['code' => 'var_b', 'name' => 'B']);

        try {
            app(FormulaResolverService::class)->validateDependencies($user, '[var_a]');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(422, $exception->status);
            $this->assertArrayHasKey('formula_string', $exception->errors());
        }
    }
}
