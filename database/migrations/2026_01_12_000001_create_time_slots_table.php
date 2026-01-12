<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('slot_number');
            $table->timestamps();

            $table->unique(['start_time', 'end_time']);
        });

        // Seed default time slots (50-minute intervals from 07:00 to 21:00)
        $this->seedTimeSlots();
    }

    /**
     * Seed time slots with 50-minute intervals
     */
    private function seedTimeSlots(): void
    {
        $slots = [];
        $start = Carbon::createFromTime(7, 0);
        $end = Carbon::createFromTime(21, 0);
        $slotNumber = 1;

        while ($start->copy()->addMinutes(50)->lessThanOrEqualTo($end)) {
            $slotEnd = $start->copy()->addMinutes(50);
            $slots[] = [
                'start_time' => $start->format('H:i:s'),
                'end_time' => $slotEnd->format('H:i:s'),
                'slot_number' => $slotNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $start->addMinutes(50);
            $slotNumber++;
        }

        DB::table('time_slots')->insert($slots);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_slots');
    }
};
