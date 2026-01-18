<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inter_household_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Source (chi invia)
            $table->foreignId('source_household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('source_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('source_user_id')->constrained('users')->cascadeOnDelete();
            
            // Destination (chi riceve)
            $table->foreignId('dest_household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('dest_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('dest_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Importi
            $table->decimal('source_amount', 18, 8);
            $table->string('source_currency', 10);
            $table->decimal('dest_amount', 18, 8);
            $table->string('dest_currency', 10);
            
            // Tasso di cambio e commissioni
            $table->decimal('exchange_rate', 28, 12)->nullable();
            $table->decimal('fee', 18, 8)->nullable();
            
            // Descrizione e note
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            
            // Data del trasferimento
            $table->date('transfer_date');
            
            // Stato del trasferimento
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'completed'])->default('pending');
            
            // Transazioni collegate (create solo quando approved)
            $table->foreignId('source_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('dest_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            
            // Data di approvazione/rifiuto
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indici per performance
            $table->index('source_household_id');
            $table->index('dest_household_id');
            $table->index('status');
            $table->index('transfer_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inter_household_transfers');
    }
};
