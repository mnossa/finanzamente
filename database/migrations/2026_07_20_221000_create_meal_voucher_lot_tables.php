<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_voucher_unit_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->decimal('unit_value', 10, 2);
            $table->date('effective_from');
            $table->timestamps();

            $table->unique(['account_id', 'effective_from']);
        });

        Schema::create('meal_voucher_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->decimal('unit_value', 10, 2);
            $table->unsignedInteger('quantity_remaining');
            $table->date('acquired_on');
            $table->timestamps();

            $table->index(['account_id', 'acquired_on', 'id']);
        });

        Schema::create('meal_voucher_lot_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained('meal_voucher_lots')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->integer('quantity_delta');
            $table->date('occurred_on');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['transaction_id']);
        });

        $this->backfillExistingMealVoucherAccounts();
    }

    private function backfillExistingMealVoucherAccounts(): void
    {
        $accounts = DB::table('accounts')
            ->where('type', 'meal_voucher')
            ->whereNull('deleted_at')
            ->get(['id', 'ticket_unit_value', 'current_balance', 'initial_balance', 'created_at']);

        foreach ($accounts as $account) {
            $unit = (float) ($account->ticket_unit_value ?? 0);
            if ($unit <= 0) {
                continue;
            }

            $effectiveFrom = substr((string) $account->created_at, 0, 10) ?: now()->toDateString();

            DB::table('meal_voucher_unit_values')->insert([
                'account_id' => $account->id,
                'unit_value' => $unit,
                'effective_from' => $effectiveFrom,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $balance = (float) $account->current_balance;
            $qty = $balance > 0 ? (int) floor($balance / $unit) : 0;
            if ($qty <= 0) {
                continue;
            }

            $lotId = DB::table('meal_voucher_lots')->insertGetId([
                'account_id' => $account->id,
                'unit_value' => $unit,
                'quantity_remaining' => $qty,
                'acquired_on' => $effectiveFrom,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('meal_voucher_lot_movements')->insert([
                'lot_id' => $lotId,
                'transaction_id' => null,
                'quantity_delta' => $qty,
                'occurred_on' => $effectiveFrom,
                'note' => 'backfill',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_voucher_lot_movements');
        Schema::dropIfExists('meal_voucher_lots');
        Schema::dropIfExists('meal_voucher_unit_values');
    }
};
