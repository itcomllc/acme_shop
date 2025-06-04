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
        Schema::table('certificates', function (Blueprint $table) {
            // プロバイダー関連のカラムを追加
            $table->string('provider')->default('gogetssl')->after('status');
            $table->string('provider_certificate_id')->nullable()->after('provider');
            $table->json('provider_data')->nullable()->after('provider_certificate_id');
            
            // 失効関連のカラムを追加（存在しない場合）
            if (!Schema::hasColumn('certificates', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('issued_at');
                $table->string('revocation_reason')->nullable()->after('revoked_at');
            }
            
            // 証明書交換関連
            $table->timestamp('replaced_at')->nullable()->after('revoked_at');
            $table->foreignId('replaced_by')->nullable()->after('replaced_at');
            
            // インデックスを追加
            $table->index('provider');
            $table->index('provider_certificate_id');
            $table->foreign('replaced_by')->references('id')->on('certificates')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['replaced_by']);
            $table->dropIndex(['provider']);
            $table->dropIndex(['provider_certificate_id']);
            
            // 追加したカラムを削除
            $table->dropColumn([
                'provider',
                'provider_certificate_id', 
                'provider_data',
                'replaced_at',
                'replaced_by'
            ]);
            
            // revoked_at と revocation_reason を削除（このマイグレーションで追加した場合のみ）
            if (Schema::hasColumn('certificates', 'revoked_at')) {
                $table->dropColumn(['revoked_at', 'revocation_reason']);
            }
        });
    }
};