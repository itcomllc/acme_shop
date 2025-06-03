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
        Schema::create('acme_accounts', function (Blueprint $table) {
            $table->id();
            $table->text('public_key');
            $table->string('public_key_thumbprint')->unique();
            $table->json('contacts')->nullable();
            $table->boolean('terms_of_service_agreed')->default(false);
            $table->enum('status', ['valid', 'deactivated', 'revoked']);
            $table->timestamps();
            
            $table->index('public_key_thumbprint');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acme_accounts');
    }
};
