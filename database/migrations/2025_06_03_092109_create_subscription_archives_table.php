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
        Schema::create('subscription_archives', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('original_subscription_id');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('subscription_data');
            $table->json('certificates_data');
            $table->timestamp('archived_at');
            $table->timestamps();
            
            $table->index(['user_id', 'archived_at']);
            $table->index('original_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_archives');
    }
};
