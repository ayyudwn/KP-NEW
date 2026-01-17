<?php

namespace App\Filament\Resources\SoftwareInventoryResource\Pages;

use App\Filament\Resources\SoftwareInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSoftwareInventory extends EditRecord
{
    protected static string $resource = SoftwareInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load data dari relasi inventoriable ke dalam form details
        if ($this->record->inventoriable) {
            $data['details'] = $this->record->inventoriable->toArray();
            // Set software_detail_id for the select dropdown
            $data['software_detail_id'] = $this->record->inventoriable_id;
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $softwareDetailId = $data['software_detail_id'] ?? null;
        $detailsData = $data['details'] ?? [];
        unset($data['details']);
        unset($data['software_detail_id']);

        // If software selection changed, update the inventoriable_id
        if ($softwareDetailId && $softwareDetailId != $record->inventoriable_id) {
            $data['inventoriable_id'] = $softwareDetailId;
            $data['inventoriable_type'] = \App\Models\SoftwareDetail::class;
        }

        // Update version info on the software detail if provided
        if ($record->inventoriable && !empty($detailsData)) {
            $record->inventoriable->update([
                'versi' => $detailsData['jenis_lisensi'] ?? $record->inventoriable->versi,
                'nomor_lisensi' => $detailsData['nomor_lisensi'] ?? $record->inventoriable->nomor_lisensi,
                'tanggal_kadaluarsa' => $detailsData['tanggal_kadaluarsa'] ?? $record->inventoriable->tanggal_kadaluarsa,
            ]);
        }

        // Update data pada tabel inventories
        $record->update($data);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        // Ambil ID laboratorium dari record yang baru diupdate
        $labId = $this->record->laboratorium_id;

        // Redirect ke halaman index dengan filter laboratorium yang sesuai
        return $this->getResource()::getUrl('index', [
            'tableFilters' => [
                'laboratorium' => [
                    'value' => $labId
                ]
            ]
        ]);
    }
}
