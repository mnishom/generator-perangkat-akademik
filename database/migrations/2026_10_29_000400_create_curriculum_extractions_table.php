<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_file_id')->constrained('library_files')->cascadeOnDelete();
            $table->char('sha256', 64)->index();
            $table->foreignId('prodi_id')->nullable()->constrained('prodis')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module_key', 64)->nullable();
            $table->string('status', 24)->default('queued')->index(); // queued|running|extracted|verified|failed|blocked
            $table->longText('raw')->nullable();
            $table->longText('data')->nullable();
            $table->json('anomalies')->nullable();
            $table->text('error')->nullable();
            $table->string('model', 64)->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::table('generation_sessions', function (Blueprint $table) {
            $table->foreign('curriculum_extraction_id')->references('id')->on('curriculum_extractions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('generation_sessions', function (Blueprint $table) {
            $table->dropForeign(['curriculum_extraction_id']);
        });
        Schema::dropIfExists('curriculum_extractions');
    }
};
