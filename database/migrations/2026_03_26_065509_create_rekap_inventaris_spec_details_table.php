<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekap_inventaris_spec_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekap_inventaris_spec_id')
                ->constrained('rekap_inventaris_specs')
                ->cascadeOnDelete();

            $table->string('komponen'); // motherboard, processor, dst
            $table->string('detail')->nullable(); // merk / tipe
            $table->enum('kondisi', ['Baik', 'Kurang Baik', 'Rusak'])->default('Baik');
            $table->text('catatan_kondisi')->nullable();
            $table->unsignedTinyInteger('urutan')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_inventaris_spec_details');
    }
};