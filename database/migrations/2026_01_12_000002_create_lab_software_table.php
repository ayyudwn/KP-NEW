<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Pivot table connecting laboratories and installed software.
     * Used for constraint checking in scheduling - a lab must have
     * all required software for a course to be eligible.
     */
    public function up(): void
    {
        Schema::create('lab_software', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratorium_id')->constrained('laboratoria')->onDelete('cascade');
            $table->foreignId('software_detail_id')->constrained('software_details')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['laboratorium_id', 'software_detail_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_software');
    }
};
