<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ScheduleResource;
use App\Models\Course;
use App\Models\TimeSlot;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSchedule extends EditRecord
{
    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->syncTimeSlotData($data);
    }

    protected function syncTimeSlotData(array $data): array
    {
        if (!empty($data['time_slot_id']) && !empty($data['course_id'])) {
            $slot = TimeSlot::find($data['time_slot_id']);
            $course = Course::find($data['course_id']);

            if ($slot && $course) {
                $data['start_time'] = Carbon::parse($slot->start_time)->format('H:i:s');
                $data['duration_slots'] = $course->sks;

                $service = app(SchedulingService::class);
                $data['end_time'] = $service->calculateEndTime($slot, $course->sks) . ':00';
            }
        }

        return $data;
    }
}
