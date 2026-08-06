<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['account_id', 'date'], 'transactions_account_date_idx');
            $table->index(['category_id', 'date'], 'transactions_category_date_idx');
            $table->index(['debt_credit_id', 'date'], 'transactions_debt_credit_date_idx');
        });

        Schema::table('transaction_tag', function (Blueprint $table) {
            $table->index(['tag_id', 'transaction_id'], 'transaction_tag_tag_tx_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_account_date_idx');
            $table->dropIndex('transactions_category_date_idx');
            $table->dropIndex('transactions_debt_credit_date_idx');
        });

        Schema::table('transaction_tag', function (Blueprint $table) {
            $table->dropIndex('transaction_tag_tag_tx_idx');
        });
    }
};
