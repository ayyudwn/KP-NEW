<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekap_inventaris_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekap_inventaris_periode_id')
                ->constrained('rekap_inventaris_periodes')
                ->cascadeOnDelete();

            $table->string('kode_spek'); // contoh: D2B/2026A
            $table->unsignedInteger('urutan_kode')->default(1); // A=1, B=2, dst
            $table->string('fingerprint')->unique(); // identitas unik spesifikasi
            $table->timestamps();

            $table->unique(
                ['rekap_inventaris_periode_id', 'kode_spek'],
                'specs_periode_kode_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_inventaris_specs');
    }
};