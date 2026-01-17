<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header dengan dropdown laboratorium -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Pilih Laboratorium
                </h2>

                <div class="max-w-md">
                    <select wire:model.live="selectedLabId"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="">-- Pilih Laboratorium --</option>
                        @foreach(\App\Models\Laboratorium::where('is_active', true)->orderBy('ruang')->get() as $lab)
                            <option value="{{ $lab->id }}">{{ $lab->ruang }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if($selectedLabId)
            @php
                $selectedLab = \App\Models\Laboratorium::find($selectedLabId);
            @endphp

            <!-- Header Jadwal Lab -->
            <div style="background-color: #b91c1c !important;" class="rounded-xl shadow-lg border border-red-600">
                <div class="p-6">
                    <h2 style="color: white !important;" class="text-xl font-bold mb-1">
                        Penggunaan Ruang {{ $selectedLab?->ruang }}
                    </h2>
                    <p style="color: #fecaca !important;" class="text-sm">
                        Universitas Dian Nuswantoro {{ date('Y') }} / {{ date('Y') + 1 }}
                    </p>
                    <p style="color: #fca5a5 !important;" class="text-xs mt-1">
                        Jalan Nakula I nomor 5 - 11 Semarang Telepon (024) 3517261, 3520165
                    </p>
                </div>
            </div>

            <!-- Tabel Jadwal -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-700 border-b-2 border-gray-300 dark:border-gray-600">
                                <th class="px-4 py-3 text-left font-bold text-gray-700 dark:text-gray-200 border-r border-gray-300 dark:border-gray-600 w-24">
                                    Hari
                                </th>
                                <th class="px-4 py-3 text-left font-bold text-gray-700 dark:text-gray-200 border-r border-gray-300 dark:border-gray-600 w-32">
                                    Jadwal
                                </th>
                                <th class="px-4 py-3 text-left font-bold text-gray-700 dark:text-gray-200 border-r border-gray-300 dark:border-gray-600">
                                    Nama Mata Kuliah
                                </th>
                                <th class="px-4 py-3 text-left font-bold text-gray-700 dark:text-gray-200 border-r border-gray-300 dark:border-gray-600 w-28">
                                    Kelompok
                                </th>
                                <th class="px-4 py-3 text-left font-bold text-gray-700 dark:text-gray-200 w-64">
                                    Dosen
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as $dayIndex => $day)
                                @php
                                    $daySchedules = $schedulesByDay[$day] ?? collect();
                                    $timeSlots = $this->getTimeSlots();
                                @endphp

                                @foreach($timeSlots as $slotIndex => $timeSlot)
                                    @php
                                        // Convert current slot to minutes for comparison
                                        $slotStart = \Carbon\Carbon::createFromFormat('H:i', $timeSlot);
                                        $slotEnd = $slotStart->copy()->addMinutes(50);

                                        // Find schedule that covers this time slot
                                        // A schedule covers this slot if: schedule.start_time <= slotStart AND schedule.end_time > slotStart
                                        $schedule = $daySchedules->first(function($s) use ($slotStart) {
                                            $scheduleStart = \Carbon\Carbon::parse($s->start_time);
                                            $scheduleEnd = \Carbon\Carbon::parse($s->end_time);

                                            // Check if this slot falls within the schedule's time range
                                            return $scheduleStart->lte($slotStart) && $scheduleEnd->gt($slotStart);
                                        });
                                    @endphp
                                    <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 {{ $slotIndex === 0 ? 'border-t-2 border-t-gray-400 dark:border-t-gray-500' : '' }}">
                                        @if($slotIndex === 0)
                                            <td class="px-4 py-2 font-semibold text-gray-800 dark:text-gray-200 border-r border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 align-top"
                                                rowspan="{{ count($timeSlots) }}">
                                                {{ $day }}
                                            </td>
                                        @endif

                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400 border-r border-gray-300 dark:border-gray-600 font-mono text-xs whitespace-nowrap">
                                            {{ $timeSlot }}-{{ $slotEnd->format('H:i') }}
                                        </td>

                                        @if($schedule)
                                            <td class="px-4 py-2 text-gray-900 dark:text-white border-r border-gray-300 dark:border-gray-600 font-medium bg-blue-50 dark:bg-blue-900/20">
                                                {{ strtoupper($schedule->course?->name ?? '-') }}
                                            </td>
                                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300 border-r border-gray-300 dark:border-gray-600 bg-blue-50 dark:bg-blue-900/20">
                                                {{ $schedule->kelompok ?? '-' }}
                                            </td>
                                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-blue-50 dark:bg-blue-900/20">
                                                {{ strtoupper($schedule->lecturer?->name ?? '-') }}
                                            </td>
                                        @else
                                            <td class="px-4 py-2 text-gray-300 dark:text-gray-600 border-r border-gray-300 dark:border-gray-600">
                                                -
                                            </td>
                                            <td class="px-4 py-2 text-gray-300 dark:text-gray-600 border-r border-gray-300 dark:border-gray-600">
                                                -
                                            </td>
                                            <td class="px-4 py-2 text-gray-300 dark:text-gray-600">
                                                -
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer Keterangan -->
            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-4 border border-yellow-200 dark:border-yellow-800">
                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                    <span class="font-semibold">*</span>Untuk Permohonan Pemindahan Ruang / Jadwal Praktikum dan Kelas Tambahan
                    Dapat Menghubungi Petugas di Ruang Koordinator Laboratorium
                </p>
            </div>

        @else
            <!-- Pesan jika belum ada laboratorium yang dipilih -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-12 text-center border border-gray-200 dark:border-gray-700">
                <svg class="mx-auto h-16 w-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Pilih Laboratorium</h3>
                <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                    Silakan pilih laboratorium di atas untuk melihat jadwal penggunaan ruang dalam format tabel.
                </p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
