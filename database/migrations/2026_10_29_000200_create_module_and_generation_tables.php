<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Status per modul; baris dibuat otomatis untuk setiap modul di config/modules.php.
        Schema::create('modules', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->boolean('enabled')->default(true);
            $table->boolean('generate_open')->default(true);
            $table->string('model', 64)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        // Pengecualian akses modul per prodi atau per user (mengalahkan status global modul).
        Schema::create('module_access', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 64)->index();
            $table->string('scope', 16); // prodi | user
            $table->unsignedBigInteger('scope_id');
            $table->boolean('allowed');
            $table->timestamps();
            $table->unique(['module_key', 'scope', 'scope_id']);
        });

        Schema::create('generation_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 64)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prodi_id')->nullable()->constrained('prodis')->nullOnDelete();
            $table->unsignedBigInteger('curriculum_extraction_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->date('tanggal_penyusunan')->nullable();
            $table->string('tahun_akademik', 16)->nullable();
            $table->json('identity')->nullable();
            $table->json('inputs')->nullable();   // peran berkas => library_file_id
            $table->json('prompts')->nullable();  // jenis prompt => isi prompt yang dipakai
            $table->json('options')->nullable();
            $table->string('wizard_step', 64)->nullable();
            $table->string('model', 64)->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('generation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_session_id')->constrained()->cascadeOnDelete();
            $table->string('ref_code', 64)->nullable();
            $table->string('ref_label')->nullable();
            $table->json('input')->nullable();
            $table->boolean('selected')->default(true);
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['generation_session_id', 'ref_code']);
        });

        // Satu baris = satu Job: rangkaian langkah dalam satu container yang sama.
        Schema::create('generation_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 128);
            $table->string('label')->nullable();
            $table->json('steps');
            $table->json('skills')->nullable();
            $table->unsignedInteger('current_step')->default(0);
            $table->string('status', 24)->default('queued')->index();
            $table->string('model', 64)->nullable();
            $table->string('container_id')->nullable();
            $table->timestamp('container_expires_at')->nullable();
            $table->longText('conversation')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['generation_session_id', 'key']);
        });

        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('generation_task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prodi_id')->nullable()->constrained('prodis')->nullOnDelete();
            $table->string('module_key', 64)->index();
            $table->string('doc_type', 64);
            $table->string('format', 16)->default('docx');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('parent_id')->nullable()->constrained('generated_documents')->nullOnDelete();
            $table->string('ref_code', 64)->nullable();
            $table->string('path')->nullable();
            $table->string('filename')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('anthropic_file_id')->nullable();
            $table->json('report')->nullable();
            $table->json('checks')->nullable();
            $table->string('status', 24)->default('ok')->index();
            $table->unsignedInteger('autofix_count')->default(0);
            $table->text('instruction')->nullable();
            $table->string('model', 64)->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->boolean('is_final')->default(false);
            $table->timestamps();
            $table->index(['generation_session_id', 'generation_item_id', 'doc_type']);
        });

        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prodi_id')->nullable()->constrained('prodis')->nullOnDelete();
            $table->string('module_key', 64)->nullable()->index();
            $table->foreignId('generation_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('generation_task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 24)->default('generate'); // generate | extract | smoke
            $table->string('model', 64);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cache_read_tokens')->default(0);
            $table->unsignedBigInteger('cache_write_tokens')->default(0);
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
        Schema::dropIfExists('generated_documents');
        Schema::dropIfExists('generation_tasks');
        Schema::dropIfExists('generation_items');
        Schema::dropIfExists('generation_sessions');
        Schema::dropIfExists('module_access');
        Schema::dropIfExists('modules');
    }
};
