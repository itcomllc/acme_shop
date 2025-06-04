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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('primary_role_id')->nullable()->after('email_verified_at');
            $table->timestamp('last_role_change')->nullable()->after('primary_role_id');
            $table->timestamp('last_login_at')->nullable()->after('last_role_change');

            $table->foreign('primary_role_id')->references('id')->on('roles')->onDelete('set null');
            $table->index('primary_role_id');
            $table->index('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['primary_role_id']);
            $table->dropIndex(['primary_role_id']);
            $table->dropIndex(['last_login_at']);
            $table->dropColumn(['primary_role_id', 'last_role_change', 'last_login_at']);
        });
    }
};