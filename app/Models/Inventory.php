<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Inventory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Relasi polimorfik untuk mendapatkan model detail (PCDetail, NonPCDetail, dll).
     */
    public function inventoriable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relasi ke model Laboratorium.
     */
    public function laboratorium(): BelongsTo
    {
        return $this->belongsTo(Laboratorium::class);
    }

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        // Temporarily commenting out the global scope to debug 500 error
        /*
        static::addGlobalScope('lab-permissions', function (Builder $builder) {
            // Skip scope for console commands or when no user is authenticated
            if (app()->runningInConsole() || !Auth::check()) {
                return;
            }

            $user = Auth::user();

            // Super admin can see all inventory, no filtering needed
            if ($user->hasRole('super_admin')) {
                return;
            }

            // For all other users, only show inventory items from labs they have permission to access
            $authorizedLabIds = $user->getAuthorizedLabIds('view');
            $builder->whereIn('laboratorium_id', $authorizedLabIds);
        });
        */

        // Auto-generate nomor inventaris sebelum menyimpan
        static::creating(function ($inventory) {
            // Ambil nama laboratorium
            $laboratorium = Laboratorium::find($inventory->laboratorium_id);
            $namaLab = $laboratorium ? strtoupper($laboratorium->ruang) : 'LAB';

            // Helper function to get last number from kode_inventaris
            $getLastNumber = function ($query) {
                $last = $query->orderByRaw("CAST(SUBSTRING_INDEX(kode_inventaris, '/', -1) AS UNSIGNED) DESC")
                    ->first();

                if ($last && $last->kode_inventaris) {
                    $parts = explode('/', $last->kode_inventaris);
                    return (int) end($parts);
                }
                return 0;
            };

            // Generate nomor inventaris untuk PCDetail
            if ($inventory->inventoriable_type === 'App\Models\PCDetail') {
                $lastNumber = $getLastNumber(
                    self::where('laboratorium_id', $inventory->laboratorium_id)
                        ->where('inventoriable_type', 'App\Models\PCDetail')
                        ->whereNotNull('kode_inventaris')
                );

                $nomorUrut = str_pad($lastNumber + 1, 2, '0', STR_PAD_LEFT);

                // Format: UDN/LABKOM/INV/namalab/PC01
                $inventory->kode_inventaris = "UDN/LABKOM/INV/{$namaLab}/PC{$nomorUrut}";
            }

            // Generate nomor inventaris untuk NonPCDetail
            if ($inventory->inventoriable_type === 'App\Models\NonPCDetail') {
                $lastNumber = $getLastNumber(
                    self::where('laboratorium_id', $inventory->laboratorium_id)
                        ->where('inventoriable_type', 'App\Models\NonPCDetail')
                        ->whereNotNull('kode_inventaris')
                );

                $nomorUrut = str_pad($lastNumber + 1, 2, '0', STR_PAD_LEFT);

                // Format: UDN/LABKOM/INV/NON-PC/namalab/01
                $inventory->kode_inventaris = "UDN/LABKOM/INV/NON-PC/{$namaLab}/{$nomorUrut}";
            }

            // Generate nomor inventaris untuk SoftwareDetail
            if ($inventory->inventoriable_type === 'App\Models\SoftwareDetail') {
                $lastNumber = $getLastNumber(
                    self::where('laboratorium_id', $inventory->laboratorium_id)
                        ->where('inventoriable_type', 'App\Models\SoftwareDetail')
                        ->whereNotNull('kode_inventaris')
                );

                $nomorUrut = str_pad($lastNumber + 1, 2, '0', STR_PAD_LEFT);

                // Format: UDN/LABKOM/INV/SOFTWARE/namalab/01
                $inventory->kode_inventaris = "UDN/LABKOM/INV/SOFTWARE/{$namaLab}/{$nomorUrut}";
            }
        });

        static::updating(function ($inventory) {
            // Jangan ubah nomor inventaris saat update
            if ($inventory->isDirty('kode_inventaris') && $inventory->getOriginal('kode_inventaris')) {
                $inventory->kode_inventaris = $inventory->getOriginal('kode_inventaris');
            }
        });
    }
}
