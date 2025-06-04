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
        Schema::table('acme_orders', function (Blueprint $table) {
            // アカウントとの関連（既に存在するかチェック）
            if (!Schema::hasColumn('acme_orders', 'account_id')) {
                $table->foreignId('account_id')->nullable()->after('id')->constrained('acme_accounts')->onDelete('cascade');
            }
            
            if (!Schema::hasColumn('acme_orders', 'subscription_id')) {
                $table->foreignId('subscription_id')->nullable()->after('account_id')->constrained()->onDelete('cascade');
            }
            
            // プロバイダー選択
            $table->string('selected_provider')->nullable()->after('certificate_url');
            $table->json('provider_data')->nullable()->after('selected_provider');
            
            // インデックス追加
            if (Schema::hasColumn('acme_orders', 'account_id')) {
                $table->index(['account_id', 'status']);
            }
            if (Schema::hasColumn('acme_orders', 'subscription_id')) {
                $table->index(['subscription_id', 'status']);
            }
            $table->index('selected_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acme_orders', function (Blueprint $table) {
            // インデックスを削除
            if (Schema::hasColumn('acme_orders', 'account_id')) {
                $table->dropIndex(['account_id', 'status']);
                $table->dropForeign(['account_id']);
            }
            if (Schema::hasColumn('acme_orders', 'subscription_id')) {
                $table->dropIndex(['subscription_id', 'status']);
                $table->dropForeign(['subscription_id']);
            }
            $table->dropIndex(['selected_provider']);
            
            // カラムを削除
            $columnsToDelete = ['selected_provider', 'provider_data'];
            
            // このマイグレーションで追加した場合のみ削除
            if (!Schema::hasColumn('acme_orders', 'account_id')) {
                $columnsToDelete[] = 'account_id';
            }
            if (!Schema::hasColumn('acme_orders', 'subscription_id')) {
                $columnsToDelete[] = 'subscription_id';
            }
            
            $table->dropColumn($columnsToDelete);
        });
    }
};