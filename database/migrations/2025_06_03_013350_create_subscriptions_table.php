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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('square_subscription_id')->nullable(); // nullableに変更
            $table->string('square_customer_id')->nullable();
            $table->enum('plan_type', ['basic', 'professional', 'enterprise']);
            $table->enum('status', ['active', 'past_due', 'cancelled', 'paused', 'suspended'])->default('active');
            $table->integer('max_domains');
            $table->enum('certificate_type', ['DV', 'OV', 'EV']);
            $table->enum('billing_period', ['MONTHLY', 'QUARTERLY', 'ANNUALLY']);
            $table->integer('price'); // in cents
            $table->json('domains');
            $table->timestamp('next_billing_date')->nullable();
            $table->timestamp('last_payment_date')->nullable();
            $table->integer('payment_failed_attempts')->default(0);
            $table->timestamp('last_payment_failure')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->json('square_data')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('square_subscription_id');
            $table->index('square_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
