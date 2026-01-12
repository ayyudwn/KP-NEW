<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Pivot table for lab-prodi priority relationships.
     * Labs can have priority for specific programs, which affects
     * sorting in the schedule form (priority labs appear first).
     */
    public function up(): void
    {
        Schema::create('lab_prodi_priority', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratorium_id')->constrained('laboratoria')->onDelete('cascade');
            $table->foreignId('prodi_id')->constrained('prodis')->onDelete('cascade');
            $table->integer('priority_level')->default(1); // 1 = highest priority
            $table->timestamps();

            $table->unique(['laboratorium_id', 'prodi_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_prodi_priority');
    }
};
