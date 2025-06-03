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
        Schema::create('acme_orders', function (Blueprint $table) {
            $table->id();
            $table->json('identifiers');
            $table->string('profile')->default('classic');
            $table->enum('status', ['pending', 'ready', 'processing', 'valid', 'invalid']);
            $table->timestamp('expires');
            $table->string('certificate_url')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acme_orders');
    }
};
