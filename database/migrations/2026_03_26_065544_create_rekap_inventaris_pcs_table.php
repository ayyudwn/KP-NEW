<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekap_inventaris_pcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekap_inventaris_periode_id')
                ->constrained('rekap_inventaris_periodes')
                ->cascadeOnDelete();

            $table->foreignId('rekap_inventaris_spec_id')
                ->nullable()
                ->constrained('rekap_inventaris_specs')
                ->nullOnDelete();

            $table->string('no_pc'); // B01, B02, dst
            $table->enum('lokasi', ['Client', 'Laboran', 'Dosen']);
            $table->enum('kondisi', ['Baik', 'Rusak'])->default('Baik');
            $table->timestamps();

            $table->unique(['rekap_inventaris_periode_id', 'no_pc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_inventaris_pcs');
    }
};