<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valuta di default che l'utente preferisce quando non la specifica
     * esplicitamente nel bot Telegram o nei form. NULL = comportamento
     * legacy "EUR sempre". Inserire un valore esplicito attiva il preset.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('default_currency_code', 3)->nullable()->after('telegram_chat_id');
            $table->foreign('default_currency_code')->references('code')->on('currencies');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_currency_code']);
            $table->dropColumn('default_currency_code');
        });
    }
};
