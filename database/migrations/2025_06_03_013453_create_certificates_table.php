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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->string('domain');
            $table->enum('type', ['DV', 'OV', 'EV']);
            $table->enum('status', ['pending_validation', 'processing', 'issued', 'expired', 'revoked']);
            $table->foreignId('acme_order_id')->nullable()->constrained('acme_orders');
            $table->integer('gogetssl_order_id')->nullable();
            $table->text('private_key')->nullable();
            $table->json('certificate_data')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            
            $table->index(['subscription_id', 'status']);
            $table->index('domain');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
