<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedInteger('jumlah_siswa')->nullable()->after('kelompok');
            $table->enum('sesi', ['pagi', 'siang', 'malam'])->nullable()->after('jumlah_siswa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['jumlah_siswa', 'sesi']);
        });
    }
};
