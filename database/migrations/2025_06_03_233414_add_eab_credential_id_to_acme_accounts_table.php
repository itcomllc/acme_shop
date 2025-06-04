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
        Schema::table('acme_accounts', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->foreignId('eab_credential_id')->nullable()->after('subscription_id')->constrained()->onDelete('set null');
            $table->index(['subscription_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acme_accounts', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->dropForeign(['eab_credential_id']);
            $table->dropColumn(['subscription_id', 'eab_credential_id']);
        });
    }
};
