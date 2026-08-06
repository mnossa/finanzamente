<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duplicate_transaction_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('primary_transaction_id');
            $table->foreignId('candidate_transaction_id');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('distance_days')->default(0);
            $table->timestamps();
            $table->unique(['primary_transaction_id', 'candidate_transaction_id'], 'dup_tx_unique_pair');
            $table->foreign('user_id', 'dup_tx_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('primary_transaction_id', 'dup_tx_primary_fk')->references('id')->on('transactions')->cascadeOnDelete();
            $table->foreign('candidate_transaction_id', 'dup_tx_candidate_fk')->references('id')->on('transactions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_transaction_candidates');
    }
};
