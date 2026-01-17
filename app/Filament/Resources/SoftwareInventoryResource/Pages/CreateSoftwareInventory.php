<?php

namespace App\Filament\Resources\SoftwareInventoryResource\Pages;

use App\Filament\Resources\SoftwareInventoryResource;
use App\Models\SoftwareDetail;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSoftwareInventory extends CreateRecord
{
    protected static string $resource = SoftwareInventoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ambil lab ID dari URL parameter jika ada
        $labId = request()->get('tableFilters')['laboratorium']['value'] ?? null;

        if ($labId) {
            $data['laboratorium_id'] = $labId;
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Get the selected software from master list
        $softwareDetailId = $data['software_detail_id'] ?? null;

        // Get details data (version, license, etc)
        $detailsData = $data['details'] ?? [];
        unset($data['details']);
        unset($data['software_detail_id']);

        // If software was selected from master list, use that ID
        if ($softwareDetailId) {
            $softwareDetail = SoftwareDetail::find($softwareDetailId);

            // Update the software detail with version info if provided
            if ($softwareDetail && !empty($detailsData)) {
                $softwareDetail->update([
                    'versi' => $detailsData['jenis_lisensi'] ?? $softwareDetail->versi,
                    'nomor_lisensi' => $detailsData['nomor_lisensi'] ?? $softwareDetail->nomor_lisensi,
                    'tanggal_kadaluarsa' => $detailsData['tanggal_kadaluarsa'] ?? $softwareDetail->tanggal_kadaluarsa,
                ]);
            }
        } else {
            // Fallback: create new software detail if no selection (shouldn't happen normally)
            $softwareDetail = SoftwareDetail::create($detailsData);
        }

        $data['inventoriable_id'] = $softwareDetail->id;
        $data['inventoriable_type'] = SoftwareDetail::class;

        return static::getModel()::create($data);
    }

    protected function getRedirectUrl(): string
    {
        // Ambil ID laboratorium dari record yang baru dibuat
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
