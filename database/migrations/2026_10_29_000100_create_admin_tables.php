<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->string('reason')->nullable();
            $table->timestamp('until')->nullable();
            $table->timestamps();
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64)->index();
            $table->string('target_type', 64)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('usage_limits', function (Blueprint $table) {
            $table->id();
            // global | module | user
            $table->string('scope', 16);
            $table->string('module_key', 64)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('max_generate_per_day')->nullable();
            $table->unsignedInteger('max_generate_per_month')->nullable();
            $table->unsignedBigInteger('max_tokens_per_month')->nullable();
            $table->timestamps();
            $table->unique(['scope', 'module_key', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_limits');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('account_status_logs');
    }
};
