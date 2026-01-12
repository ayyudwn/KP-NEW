<x-filament-panels::page>
    <form wire:submit="findAvailableSlots" class="space-y-6">
        {{ $this->form }}

        <div class="flex gap-3">
            <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                Cari Slot Tersedia
            </x-filament::button>

            @if($showRecommendations)
                <x-filament::button type="button" color="gray" wire:click="resetRecommendations">
                    Reset
                </x-filament::button>
            @endif
        </div>
    </form>

    @if($showRecommendations)
        <div class="mt-8">
            {{-- Course Info Banner --}}
            @if($this->course)
                <div
                    class="bg-primary-50 dark:bg-primary-900/20 rounded-lg p-4 mb-6 border border-primary-200 dark:border-primary-800">
                    <div class="flex items-center gap-3">
                        <x-heroicon-o-academic-cap class="w-8 h-8 text-primary-600" />
                        <div>
                            <h3 class="font-semibold text-lg text-gray-900 dark:text-white">
                                {{ $this->course->name }}
                                @if($data['kelompok'] ?? null)
                                    <span class="text-primary-600">(Kelompok {{ $data['kelompok'] }})</span>
                                @endif
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $this->course->sks }} SKS ({{ $this->course->sks * 50 }} menit)
                                @if($this->course->prodi)
                                    • {{ $this->course->prodi->name }}
                                @endif
                                @if($this->course->jumlah_mahasiswa > 0)
                                    • {{ $this->course->jumlah_mahasiswa }} mahasiswa
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Day Tabs --}}
            <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                <nav class="flex space-x-1 overflow-x-auto" aria-label="Days">
                    @foreach(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as $day)
                            @php
                                $dayRecs = $recommendations[$day] ?? [];
                                $count = count($dayRecs);
                                $isSelected = $selectedDay === $day;
                            @endphp
                            <button type="button" wire:click="selectDay('{{ $day }}')"
                                class="px-4 py-3 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap
                                            {{ $isSelected
                        ? 'bg-primary-600 text-white'
                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800' }}">
                                {{ $day }}
                                <span class="ml-2 px-2 py-0.5 rounded-full text-xs 
                                            {{ $isSelected ? 'bg-primary-500 text-white' : 'bg-gray-200 dark:bg-gray-700' }}">
                                    {{ $count }}
                                </span>
                            </button>
                    @endforeach
                </nav>
            </div>

            {{-- Recommendations Grid --}}
            @if($selectedDay && isset($recommendations[$selectedDay]))
                @php $dayRecommendations = $recommendations[$selectedDay]; @endphp

                @if(count($dayRecommendations) > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        @foreach($dayRecommendations as $rec)
                            <div wire:click="createSchedule({{ $rec['lab_id'] }}, {{ $rec['slot_id'] }})" class="group cursor-pointer bg-white dark:bg-gray-800 rounded-xl border-2 
                                                    {{ $rec['is_priority']
                                    ? 'border-amber-400 hover:border-amber-500 hover:shadow-amber-100'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-primary-400' }}
                                                    hover:shadow-lg transition-all duration-200 p-4 relative overflow-hidden">
                                {{-- Priority Badge --}}
                                @if($rec['is_priority'])
                                    <div
                                        class="absolute top-0 right-0 bg-amber-400 text-amber-900 text-xs font-bold px-2 py-1 rounded-bl-lg">
                                        ⭐ Prioritas
                                    </div>
                                @endif

                                {{-- Lab Name --}}
                                <div class="flex items-center gap-2 mb-3">
                                    <x-heroicon-o-building-office class="w-5 h-5 text-primary-600" />
                                    <span class="font-bold text-lg text-gray-900 dark:text-white">
                                        {{ $rec['lab_name'] }}
                                    </span>
                                </div>

                                {{-- Time --}}
                                <div class="flex items-center gap-2 mb-2">
                                    <x-heroicon-o-clock class="w-5 h-5 text-green-600" />
                                    <span class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                                        {{ $rec['start_time'] }} - {{ $rec['end_time'] }}
                                    </span>
                                </div>

                                {{-- Capacity --}}
                                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                    <x-heroicon-o-computer-desktop class="w-4 h-4" />
                                    <span>{{ $rec['lab_capacity'] }} PC tersedia</span>
                                </div>

                                {{-- Hover instruction --}}
                                <div
                                    class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <span class="text-xs text-primary-600 dark:text-primary-400 font-medium">
                                        Klik untuk membuat jadwal →
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                        <x-heroicon-o-calendar class="w-12 h-12 mx-auto text-gray-400 mb-4" />
                        <p class="text-gray-600 dark:text-gray-400">
                            Tidak ada slot tersedia untuk hari <strong>{{ $selectedDay }}</strong>
                        </p>
                        <p class="text-sm text-gray-500 mt-1">
                            Coba pilih hari lain atau periksa ketersediaan lab.
                        </p>
                    </div>
                @endif
            @endif
        </div>
    @endif
</x-filament-panels::page>