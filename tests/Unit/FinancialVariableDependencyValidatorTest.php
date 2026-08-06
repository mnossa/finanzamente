<?php

namespace Tests\Unit;

use App\Models\FinancialVariable;
use App\Models\User;
use App\Services\FinancialVariableDependencyValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancialVariableDependencyValidatorTest extends TestCase
{
    use RefreshDatabase;

    private FinancialVariableDependencyValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = app(FinancialVariableDependencyValidator::class);
    }

    #[Test]
    public function it_detects_circular_dependencies(): void
    {
        $user = User::factory()->create();

        FinancialVariable::factory()->for($user)->formula('[var_b]')->create(['code' => 'var_a', 'name' => 'A']);
        FinancialVariable::factory()->for($user)->formula('[var_a]')->create(['code' => 'var_b', 'name' => 'B']);

        $this->expectException(ValidationException::class);

        $this->validator->validate($user, '[var_a]');
    }

    #[Test]
    public function it_rejects_formulas_exceeding_max_depth(): void
    {
        $user = User::factory()->create();

        FinancialVariable::factory()->for($user)->formula('[lvl_3]')->create(['code' => 'lvl_4', 'name' => 'L4']);
        FinancialVariable::factory()->for($user)->formula('[lvl_2]')->create(['code' => 'lvl_3', 'name' => 'L3']);
        FinancialVariable::factory()->for($user)->formula('[lvl_1]')->create(['code' => 'lvl_2', 'name' => 'L2']);
        FinancialVariable::factory()->for($user)->formula('[total_income]')->create(['code' => 'lvl_1', 'name' => 'L1']);

        $this->expectException(ValidationException::class);

        $this->validator->validate($user, '[lvl_4]');
    }

    #[Test]
    public function it_allows_valid_dependency_chain_within_depth_limit(): void
    {
        $user = User::factory()->create();

        FinancialVariable::factory()->for($user)->formula('[total_income]')->create(['code' => 'base', 'name' => 'Base']);
        FinancialVariable::factory()->for($user)->formula('[base] * 2')->create(['code' => 'derived', 'name' => 'Derived']);

        $this->validator->validate($user, '[derived] + 1');

        $this->assertTrue(true);
    }
}
