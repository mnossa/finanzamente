<?php

namespace Tests\Unit;

use App\Models\FinancialVariable;
use App\Models\User;
use App\Services\FormulaSyntaxValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaSyntaxValidatorTest extends TestCase
{
    use RefreshDatabase;

    private FormulaSyntaxValidator $validator;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = app(FormulaSyntaxValidator::class);
        $this->user = User::factory()->create();
    }

    #[Test]
    public function it_rejects_self_referencing_formula_on_create(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate($this->user, '[saldo_custom]', null, 'saldo_custom');
    }

    #[Test]
    public function it_rejects_unknown_variable_tokens(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate($this->user, '[variabile_inesistente] + 1');
    }

    #[Test]
    public function it_rejects_disallowed_characters(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate($this->user, 'eval(1)+[household_balance]');
    }

    #[Test]
    public function it_rejects_unbalanced_brackets(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate($this->user, '[household_balance');
    }

    #[Test]
    public function it_accepts_valid_system_formula(): void
    {
        $this->validator->validate($this->user, '[period_income] - [period_expenses]');

        $this->assertTrue(true);
    }

    #[Test]
    public function it_accepts_context_variables_in_formula(): void
    {
        $this->validator->validate($this->user, '[period_expenses] / [days_elapsed_in_month]');

        $this->assertTrue(true);
    }

    #[Test]
    public function it_rejects_division_by_zero_probe(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate($this->user, '[household_balance] / 0');
    }

    #[Test]
    public function it_accepts_user_variable_reference(): void
    {
        FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create([
            'code' => 'mio_saldo',
            'name' => 'Mio saldo',
        ]);

        $this->validator->validate($this->user, '[mio_saldo] * 2');

        $this->assertTrue(true);
    }
}
