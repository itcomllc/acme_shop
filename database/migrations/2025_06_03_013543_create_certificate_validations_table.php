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
        Schema::create('certificate_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['http-01', 'dns-01', 'tls-alpn-01']);
            $table->string('token');
            $table->text('key_authorization');
            $table->enum('status', ['pending', 'valid', 'invalid'])->default('pending');
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index(['certificate_id', 'type']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_validations');
    }
};
