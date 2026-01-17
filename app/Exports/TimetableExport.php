<?php

namespace App\Exports;

use App\Models\Laboratorium;
use App\Models\Schedule;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TimetableExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        $sheets = [];

        // Get all active labs
        $labs = Laboratorium::where('is_active', true)
            ->orderBy('ruang')
            ->get();

        foreach ($labs as $lab) {
            $sheets[] = new LabScheduleSheet($lab);
        }

        return $sheets;
    }
}
