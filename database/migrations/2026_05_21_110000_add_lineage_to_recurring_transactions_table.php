<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('recurring_transactions', 'successor_recurring_transaction_id')) {
            Schema::table('recurring_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('successor_recurring_transaction_id')->nullable()->after('debt_credit_id');
            });
        }

        if (! Schema::hasColumn('recurring_transactions', 'predecessor_recurring_transaction_id')) {
            Schema::table('recurring_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('predecessor_recurring_transaction_id')->nullable()->after('debt_credit_id');
            });
        }

        if (! $this->hasForeignKey('recurring_tx_successor_fk')) {
            Schema::table('recurring_transactions', function (Blueprint $table) {
                $table->foreign('successor_recurring_transaction_id', 'recurring_tx_successor_fk')
                    ->references('id')
                    ->on('recurring_transactions')
                    ->nullOnDelete();
            });
        }

        if (! $this->hasForeignKey('recurring_tx_predecessor_fk')) {
            Schema::table('recurring_transactions', function (Blueprint $table) {
                $table->foreign('predecessor_recurring_transaction_id', 'recurring_tx_predecessor_fk')
                    ->references('id')
                    ->on('recurring_transactions')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            if ($this->hasForeignKey('recurring_tx_successor_fk')) {
                $table->dropForeign('recurring_tx_successor_fk');
            }
            if ($this->hasForeignKey('recurring_tx_predecessor_fk')) {
                $table->dropForeign('recurring_tx_predecessor_fk');
            }
            if (Schema::hasColumn('recurring_transactions', 'successor_recurring_transaction_id')) {
                $table->dropColumn(['successor_recurring_transaction_id', 'predecessor_recurring_transaction_id']);
            }
        });
    }

    private function hasForeignKey(string $constraintName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            return false;
        }

        $database = $connection->getDatabaseName();

        $result = $connection->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, 'recurring_transactions', $constraintName, 'FOREIGN KEY']
        );

        return (int) ($result->c ?? 0) > 0;
    }
};
