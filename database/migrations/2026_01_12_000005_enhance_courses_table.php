<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Adds code, semester, and student count to courses table.
     * Used for constraint checking - lab must have capacity >= student count.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id');
            $table->integer('semester')->nullable()->after('sks');
            $table->integer('jumlah_mahasiswa')->default(0)->after('semester');
        });

        // Add unique constraint separately to handle nullable
        Schema::table('courses', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'semester', 'jumlah_mahasiswa']);
        });
    }
};
