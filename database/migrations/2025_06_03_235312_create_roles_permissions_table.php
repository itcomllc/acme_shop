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
        // Roles table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->string('description')->nullable();
            $table->string('color', 7)->default('#6b7280'); // Hex color for UI
            $table->integer('priority')->default(999); // Lower number = higher priority
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Additional role data
            $table->timestamps();

            $table->index(['name', 'is_active']);
            $table->index('priority');
        });

        // Permissions table
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->string('description')->nullable();
            $table->string('category')->default('general'); // Group permissions
            $table->string('resource')->nullable(); // What resource (users, certificates, etc.)
            $table->string('action')->nullable(); // What action (view, create, edit, delete)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index(['resource', 'action']);
            $table->index('name');
        });

        // Role-Permission pivot table
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
            $table->index('role_id');
            $table->index('permission_id');
        });

        // User-Role pivot table
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('expires_at')->nullable(); // Optional role expiration
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->text('notes')->nullable(); // Assignment notes
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
            $table->index('user_id');
            $table->index('role_id');
            $table->index('expires_at');
        });

        // User-Permission direct assignments (for fine-grained control)
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['grant', 'deny'])->default('grant'); // Allow or explicitly deny
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'permission_id']);
            $table->index('user_id');
            $table->index('permission_id');
            $table->index(['type', 'expires_at']);
        });

        // Add role tracking to users table
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_role_change')->nullable()->after('updated_at');
            $table->foreignId('primary_role_id')->nullable()->constrained('roles')->after('email_verified_at');
            
            $table->index('primary_role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['primary_role_id']);
            $table->dropColumn(['last_role_change', 'primary_role_id']);
        });

        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};