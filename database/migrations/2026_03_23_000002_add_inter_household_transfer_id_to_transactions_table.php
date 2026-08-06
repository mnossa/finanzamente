<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('inter_household_transfer_id')
                ->nullable()
                ->after('transfer_id')
                ->constrained('inter_household_transfers')
                ->nullOnDelete()
                ->comment('FK verso inter_household_transfers: se valorizzato, la transazione è stata generata da un trasferimento inter-household');
        });

        // Backfill: collega le transazioni già esistenti ai rispettivi trasferimenti
        // Usa DB::table (non Eloquent) per stabilità nelle migration
        DB::table('inter_household_transfers')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('source_transaction_id')
                    ->orWhereNotNull('dest_transaction_id');
            })
            ->select(['id', 'source_transaction_id', 'dest_transaction_id'])
            ->get()
            ->each(function ($transfer) {
                if ($transfer->source_transaction_id) {
                    DB::table('transactions')
                        ->where('id', $transfer->source_transaction_id)
                        ->whereNull('inter_household_transfer_id')
                        ->update(['inter_household_transfer_id' => $transfer->id]);
                }
                if ($transfer->dest_transaction_id) {
                    DB::table('transactions')
                        ->where('id', $transfer->dest_transaction_id)
                        ->whereNull('inter_household_transfer_id')
                        ->update(['inter_household_transfer_id' => $transfer->id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['inter_household_transfer_id']);
            $table->dropColumn('inter_household_transfer_id');
        });
    }
};
