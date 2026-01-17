<?php

namespace App\Exports;

use App\Models\Laboratorium;
use App\Models\Schedule;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LabScheduleSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected Laboratorium $lab;
    protected array $schedulesByDay = [];
    protected int $slotsPerDay;
    protected array $blueSeparatorRows = [];
    protected array $dayMergeRanges = [];
    protected int $dataEndRow = 0;

    public function __construct(Laboratorium $lab)
    {
        $this->lab = $lab;
        $this->slotsPerDay = count($this->getTimeSlots());
        $this->loadSchedules();
    }

    public function title(): string
    {
        return $this->lab->ruang;
    }

    protected function loadSchedules(): void
    {
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

        foreach ($days as $day) {
            $this->schedulesByDay[$day] = collect();
        }

        $schedules = Schedule::with(['course', 'lecturer'])
            ->where('laboratorium_id', $this->lab->id)
            ->orderBy('start_time')
            ->get();

        foreach ($schedules as $schedule) {
            if (isset($this->schedulesByDay[$schedule->day])) {
                $this->schedulesByDay[$schedule->day]->push($schedule);
            }
        }
    }

    protected function getTimeSlots(): array
    {
        $slots = [];
        $current = Carbon::createFromTime(7, 0);
        $maxEnd = Carbon::createFromTime(21, 0);

        // Break times
        $breaks = [
            ['start' => '12:00', 'end' => '12:30'],
            ['start' => '15:50', 'end' => '16:20'],
            ['start' => '18:00', 'end' => '18:30'],
        ];

        while ($current->lt($maxEnd)) {
            $slotEnd = $current->copy()->addMinutes(50);

            // Check if slot would end after max time
            if ($slotEnd->gt($maxEnd)) {
                break;
            }

            // Check if current position is inside a break - if so, skip to break end
            $insideBreak = false;
            foreach ($breaks as $break) {
                $breakStart = Carbon::createFromFormat('H:i', $break['start']);
                $breakEnd = Carbon::createFromFormat('H:i', $break['end']);

                if ($current->gte($breakStart) && $current->lt($breakEnd)) {
                    $current = $breakEnd->copy();
                    $insideBreak = true;
                    break;
                }
            }

            if ($insideBreak) {
                continue;
            }

            // Check if slot would cross into a break
            $crossesBreak = false;
            foreach ($breaks as $break) {
                $breakStart = Carbon::createFromFormat('H:i', $break['start']);

                if ($current->lt($breakStart) && $slotEnd->gt($breakStart)) {
                    $slots[] = $current->format('H:i');
                    $current = Carbon::createFromFormat('H:i', $break['end']);
                    $crossesBreak = true;
                    break;
                }
            }

            if ($crossesBreak) {
                continue;
            }

            // Normal slot
            $slots[] = $current->format('H:i');
            $current->addMinutes(50);
        }

        return $slots;
    }

    public function array(): array
    {
        $data = [];

        // Row 1: Title
        $data[] = ['Penggunaan Ruang ' . $this->lab->ruang];
        // Row 2: University
        $data[] = ['Universitas Dian Nuswantoro ' . date('Y') . ' / ' . (date('Y') + 1)];
        // Row 3: Address
        $data[] = ['Jalan Nakula I nomor 5 - 11 Semarang Telepon (024) 3517261, 3520165'];
        // Row 4: Table header
        $data[] = ['Hari', 'Jadwal', 'Mata Kuliah', 'Kelompok', 'Dosen'];

        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $timeSlots = $this->getTimeSlots();

        // Excel row number (1-indexed): data starts at row 5
        $excelRow = 5;

        foreach ($days as $dayIndex => $day) {
            $daySchedules = $this->schedulesByDay[$day] ?? collect();

            // Add blue separator row BEFORE each day (except first)
            if ($dayIndex > 0) {
                $data[] = ['', '', '', '', '']; // Empty row for blue separator
                $this->blueSeparatorRows[] = $excelRow;
                $excelRow++;
            }

            $dayStartRow = $excelRow;

            foreach ($timeSlots as $slotIndex => $timeSlot) {
                $slotStart = Carbon::createFromFormat('H:i', $timeSlot);
                $slotEnd = $slotStart->copy()->addMinutes(50);

                // Find schedule that covers this time slot
                $schedule = $daySchedules->first(function ($s) use ($slotStart) {
                    $scheduleStart = Carbon::parse($s->start_time);
                    $scheduleEnd = Carbon::parse($s->end_time);

                    return $scheduleStart->lte($slotStart) && $scheduleEnd->gt($slotStart);
                });

                // Only put day name in first row of each day
                $row = [
                    $slotIndex === 0 ? $day : '',
                    $timeSlot . '-' . $slotEnd->format('H:i'),
                    $schedule ? strtoupper($schedule->course?->name ?? '') : '',
                    $schedule ? ($schedule->kelompok ?? '') : '',
                    $schedule ? strtoupper($schedule->lecturer?->name ?? '') : '',
                ];

                $data[] = $row;
                $excelRow++;
            }

            // Store merge range for this day's Hari column
            $dayEndRow = $excelRow - 1;
            $this->dayMergeRanges[] = "A{$dayStartRow}:A{$dayEndRow}";
        }

        $this->dataEndRow = $excelRow - 1;

        // Footer
        $data[] = [];
        $data[] = ['*Untuk Permohonan Pemindahan Ruang / Jadwal Praktikum dan Kelas Tambahan Dapat Menghubungi Petugas di Ruang Koordinator Laboratorium'];
        $data[] = [];
        $data[] = ['Koordinator Penjadwalan : Riza Agung Nugroho, S.Kom', '', '', Carbon::now()->format('n/j/y G:i'), ''];

        return $data;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // Hari
            'B' => 15,  // Jadwal
            'C' => 40,  // Nama Mata Kuliah
            'D' => 15,  // Kelompok
            'E' => 35,  // Dosen
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Merge Hari column cells for each day
                foreach ($this->dayMergeRanges as $range) {
                    $sheet->mergeCells($range);
                    $sheet->getStyle($range)->applyFromArray([
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                        'font' => [
                            'bold' => true,
                        ],
                    ]);
                }

                // Apply blue background to separator rows
                foreach ($this->blueSeparatorRows as $row) {
                    $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '1E40AF'], // Blue-800
                        ],
                    ]);
                    // Set row height for separator
                    $sheet->getRowDimension($row)->setRowHeight(8);
                }
            },
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header styling (rows 1-3) - BOLD BLACK
        $sheet->mergeCells('A1:E1');
        $sheet->mergeCells('A2:E2');
        $sheet->mergeCells('A3:E3');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => '000000'],
            ],
        ]);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
                'color' => ['rgb' => '000000'],
            ],
        ]);

        $sheet->getStyle('A3')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
                'color' => ['rgb' => '000000'],
            ],
        ]);

        // Table header styling (row 4) - Blue background like separator
        $sheet->getStyle('A4:E4')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'], // White text
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E40AF'], // Blue-800
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Data rows - thin borders (row 5 onwards)
        $sheet->getStyle("A5:E{$this->dataEndRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Footer styling
        $footerRow = $this->dataEndRow + 2;
        $sheet->mergeCells("A{$footerRow}:E{$footerRow}");
        $sheet->getStyle("A{$footerRow}")->applyFromArray([
            'font' => [
                'italic' => true,
                'size' => 9,
                'color' => ['rgb' => 'B45309'],
            ],
        ]);

        return [];
    }
}
