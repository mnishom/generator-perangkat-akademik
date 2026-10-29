<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fakultas', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('singkatan', 32)->nullable();
            $table->string('nama_dekan')->nullable();
            $table->timestamps();
        });

        Schema::create('prodis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fakultas_id')->nullable()->constrained('fakultas')->nullOnDelete();
            $table->string('kode', 32)->nullable()->unique();
            $table->string('jenjang', 16)->nullable();
            $table->string('nama');
            $table->string('nama_kaprodi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prodis');
        Schema::dropIfExists('fakultas');
    }
};
