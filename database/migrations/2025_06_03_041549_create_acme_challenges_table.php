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
        Schema::create('acme_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('authorization_id')->constrained('acme_authorizations')->onDelete('cascade');
            $table->enum('type', ['http-01', 'dns-01', 'tls-alpn-01']);
            $table->enum('status', ['pending', 'processing', 'valid', 'invalid']);
            $table->string('token');
            $table->string('key_authorization')->nullable();
            $table->timestamp('validated')->nullable();
            $table->timestamps();
            
            $table->index(['authorization_id', 'type']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acme_challenges');
    }
};
