<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Laboratorium extends Model
{
    protected $guarded = ['id'];

    protected $table = 'laboratoria';

    protected $fillable = [
        'kategori_id',
        'ruang',
        'kapasitas',
        'keterangan',
        'pc_siap',
        'pc_backup',
        'is_active',
        'operating_start',
        'operating_end',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'operating_start' => 'datetime:H:i',
        'operating_end' => 'datetime:H:i',
    ];

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

            // Super admin can see all labs, no filtering needed
            if ($user->hasRole('super_admin')) {
                return;
            }

            // For all other users, only show labs they have permission to access
            $authorizedLabIds = $user->getAuthorizedLabIds('view');
            $builder->whereIn('id', $authorizedLabIds);
        });
        */
    }

    /**
     * Relasi ke KlasifikasiLab (kategori lab)
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiLab::class, 'kategori_id');
    }

    /**
     * Relasi many-to-many ke SoftwareDetail
     * Software yang terinstal di lab ini dengan versi masing-masing
     */
    public function software(): BelongsToMany
    {
        return $this->belongsToMany(SoftwareDetail::class, 'lab_software')
            ->withPivot('version')
            ->withTimestamps();
    }

    /**
     * Relasi many-to-many ke Prodi dengan prioritas
     * Program studi yang diprioritaskan untuk lab ini
     */
    public function priorityProdis(): BelongsToMany
    {
        return $this->belongsToMany(Prodi::class, 'lab_prodi_priority')
            ->withPivot('priority_level')
            ->orderBy('pivot_priority_level');
    }

    /**
     * Relasi ke Schedule
     * Jadwal-jadwal yang menggunakan lab ini
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Cek apakah lab memiliki semua software yang dibutuhkan
     *
     * @param array $softwareIds Array of software_detail IDs
     * @return bool
     */
    public function hasAllSoftware(array $softwareIds): bool
    {
        if (empty($softwareIds)) {
            return true;
        }

        $installedCount = $this->software()
            ->whereIn('software_details.id', $softwareIds)
            ->count();

        return $installedCount >= count($softwareIds);
    }

    /**
     * Cek apakah lab memiliki kapasitas untuk jumlah mahasiswa
     *
     * @param int $studentCount Jumlah mahasiswa
     * @return bool
     */
    public function hasCapacityFor(int $studentCount): bool
    {
        return $this->pc_siap >= $studentCount;
    }

    /**
     * Cek apakah lab adalah prioritas untuk prodi tertentu
     *
     * @param int $prodiId
     * @return bool
     */
    public function isPriorityFor(int $prodiId): bool
    {
        return $this->priorityProdis()->where('prodis.id', $prodiId)->exists();
    }

    /**
     * Scope untuk lab yang aktif
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope untuk lab dengan kapasitas minimum
     */
    public function scopeWithMinCapacity(Builder $query, int $minCapacity): Builder
    {
        return $query->where('pc_siap', '>=', $minCapacity);
    }
}
