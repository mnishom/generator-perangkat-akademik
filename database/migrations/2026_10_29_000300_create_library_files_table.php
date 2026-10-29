<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_files', function (Blueprint $table) {
            $table->id();
            // null = berkas bersama (template resmi universitas yang disediakan admin)
            $table->foreignId('prodi_id')->nullable()->constrained('prodis')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 64)->index(); // kurikulum, contoh_rps, template_rps, ...
            $table->string('module_key', 64)->nullable()->index();
            $table->boolean('is_shared')->default(false);
            $table->string('original_name');
            $table->string('path');
            $table->string('extension', 16);
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64)->index();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_files');
    }
};
