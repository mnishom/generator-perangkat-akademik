<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 64);
            $table->string('prompt_key', 64);
            $table->string('kind', 16); // default | personal
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->longText('body');
            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['module_key', 'prompt_key', 'kind', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompts');
    }
};
