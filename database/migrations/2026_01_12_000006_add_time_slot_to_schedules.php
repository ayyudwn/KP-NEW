<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Adds time_slot_id reference to schedules table.
     * Keeps existing start_time/end_time columns for backward compatibility.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('time_slot_id')->nullable()->after('laboratorium_id')
                ->constrained('time_slots')->onDelete('set null');
            $table->integer('duration_slots')->default(1)->after('time_slot_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['time_slot_id']);
            $table->dropColumn(['time_slot_id', 'duration_slots']);
        });
    }
};
