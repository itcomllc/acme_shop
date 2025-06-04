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
        Schema::table('subscriptions', function (Blueprint $table) {
            // プロバイダー設定関連
            $table->string('default_provider')->default('google_certificate_manager')->after('square_data');
            $table->json('provider_preferences')->nullable()->after('default_provider');
            
            // 自動更新設定
            $table->boolean('auto_renewal_enabled')->default(true)->after('provider_preferences');
            $table->integer('renewal_before_days')->default(30)->after('auto_renewal_enabled');
            
            // 統計情報
            $table->integer('certificates_issued')->default(0)->after('renewal_before_days');
            $table->integer('certificates_renewed')->default(0)->after('certificates_issued');
            $table->integer('certificates_failed')->default(0)->after('certificates_renewed');
            $table->timestamp('last_certificate_issued_at')->nullable()->after('certificates_failed');
            $table->timestamp('last_activity_at')->nullable()->after('last_certificate_issued_at');
            
            // インデックス追加
            $table->index('default_provider');
            $table->index('auto_renewal_enabled');
            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['default_provider']);
            $table->dropIndex(['auto_renewal_enabled']);
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn([
                'default_provider',
                'provider_preferences',
                'auto_renewal_enabled',
                'renewal_before_days',
                'certificates_issued',
                'certificates_renewed',
                'certificates_failed',
                'last_certificate_issued_at',
                'last_activity_at'
            ]);
        });
    }
};