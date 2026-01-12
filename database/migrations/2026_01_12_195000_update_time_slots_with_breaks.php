<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Updates time slots to account for break periods:
     * - Break 1: 12:00 - 12:30 (Istirahat siang)
     * - Break 2: 15:50 - 16:20 (Istirahat sore)
     * - Break 3: 18:00 - 18:30 (Istirahat malam)
     * 
     * Operating hours: 07:00 - 21:00
     * Slot duration: 50 minutes
     */
    public function up(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear existing time slots
        DB::table('time_slots')->truncate();

        // Regenerate with break times
        $this->seedTimeSlotsWithBreaks();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Seed time slots with 50-minute intervals, excluding break periods
     */
    private function seedTimeSlotsWithBreaks(): void
    {
        $slots = [];
        $slotNumber = 1;

        // Define break periods (start and end times)
        $breaks = [
            ['start' => '12:00', 'end' => '12:30'],
            ['start' => '15:50', 'end' => '16:20'],
            ['start' => '18:00', 'end' => '18:30'],
        ];

        // Define time periods (before/after each break)
        $periods = [
            // Pagi: 07:00 - 12:00 (sebelum istirahat siang)
            ['start' => '07:00', 'end' => '12:00'],
            // Siang: 12:30 - 15:50 (setelah istirahat siang, sebelum istirahat sore)
            ['start' => '12:30', 'end' => '15:50'],
            // Sore: 16:20 - 18:00 (setelah istirahat sore, sebelum istirahat malam)
            ['start' => '16:20', 'end' => '18:00'],
            // Malam: 18:30 - 21:00 (setelah istirahat malam)
            ['start' => '18:30', 'end' => '21:00'],
        ];

        foreach ($periods as $period) {
            $current = Carbon::createFromFormat('H:i', $period['start']);
            $periodEnd = Carbon::createFromFormat('H:i', $period['end']);

            while ($current->copy()->addMinutes(50)->lessThanOrEqualTo($periodEnd)) {
                $slotEnd = $current->copy()->addMinutes(50);

                $slots[] = [
                    'start_time' => $current->format('H:i:s'),
                    'end_time' => $slotEnd->format('H:i:s'),
                    'slot_number' => $slotNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $current->addMinutes(50);
                $slotNumber++;
            }
        }

        DB::table('time_slots')->insert($slots);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore original continuous slots
        DB::table('time_slots')->truncate();

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
};
