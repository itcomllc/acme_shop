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
        Schema::create('acme_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('acme_orders')->onDelete('cascade');
            $table->json('identifier');
            $table->enum('status', ['pending', 'valid', 'invalid', 'deactivated', 'expired', 'revoked']);
            $table->timestamp('expires');
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acme_authorizations');
    }
};
