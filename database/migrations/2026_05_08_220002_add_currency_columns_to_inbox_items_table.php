<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge tracking valuta agli inbox_items (Telegram, OCR, manuale).
     *
     * Al momento del parsing del messaggio Telegram l'utente potrebbe non aver
     * ancora associato un account (la conferma avviene successivamente nella UI).
     * Salviamo quindi la valuta originaria che il parser ha riconosciuto e,
     * se l'utente ha specificato un override del tasso (`~rate`), già il rate
     * snapshot. La conversione finale ad `account.currency_code` avviene al
     * momento della conferma in `InboxController`.
     *
     * Backfill: gli inbox_items esistenti sono di fatto in EUR.
     */
    public function up(): void
    {
        Schema::table('inbox_items', function (Blueprint $table) {
            $table->string('currency_code', 3)->nullable()->after('amount');
            $table->decimal('exchange_rate_to_base', 20, 10)->nullable()->after('currency_code');
            $table->decimal('amount_base', 15, 2)->nullable()->after('exchange_rate_to_base');
            $table->decimal('original_amount', 15, 2)->nullable()->after('amount_base');
            $table->string('original_currency_code', 3)->nullable()->after('original_amount');

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->foreign('original_currency_code')->references('code')->on('currencies');
        });

        DB::table('inbox_items')
            ->whereNotNull('amount')
            ->update([
                'currency_code' => 'EUR',
                'exchange_rate_to_base' => 1,
                'amount_base' => DB::raw('amount'),
            ]);
    }

    public function down(): void
    {
        Schema::table('inbox_items', function (Blueprint $table) {
            $table->dropForeign(['currency_code']);
            $table->dropForeign(['original_currency_code']);
            $table->dropColumn([
                'currency_code',
                'exchange_rate_to_base',
                'amount_base',
                'original_amount',
                'original_currency_code',
            ]);
        });
    }
};
