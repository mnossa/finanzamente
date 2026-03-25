<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan');
            $table->string('billing_cycle')->nullable(); // monthly, annual
            $table->string('mollie_subscription_id')->nullable()->unique();
            $table->string('mollie_mandate_id')->nullable();
            $table->string('status')->default('pending'); // pending, active, cancelled, past_due, completed
            $table->string('currency', 3)->default('EUR');
            $table->integer('amount_cents')->default(0);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('next_payment_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            // Billing info
            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_address')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_zip')->nullable();
            $table->string('billing_country', 2)->nullable();
            $table->string('billing_vat')->nullable();
            $table->string('billing_company')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
