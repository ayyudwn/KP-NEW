<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Adds operational hours and active status to laboratories.
     * Used for constraint checking - schedules must be within operating hours.
     */
    public function up(): void
    {
        Schema::table('laboratoria', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('keterangan');
            $table->time('operating_start')->default('07:00')->after('is_active');
            $table->time('operating_end')->default('21:00')->after('operating_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laboratoria', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'operating_start', 'operating_end']);
        });
    }
};
