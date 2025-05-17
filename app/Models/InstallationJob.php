<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallationJob extends Model
{

    protected $fillable = [
        'job_title',
        'job_reference_id',
        'technician_id',
        'odp_asset_id',
        'job_type',
        'status',
        'proposed_latitude',
        'proposed_longitude',
        'rab_estimated_total_cost',
        'actual_total_cost',
        'justification',
        'admin_approver_id',
        'approval_rejection_timestamp',
        'admin_comments',
        'scheduled_installation_date',
        'actual_completion_date'
    ];
    protected $casts = [
        'installation_date' => 'datetime'
    ];
    /**
     * Relasi ke Teknisi (User)
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * Relasi ke Admin Pemberi Persetujuan (User)
     */
    public function adminApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_approver_id');
    }

    /**
     * Relasi ke Aset ODP
     */
    public function odpAsset(): BelongsTo
    {
        return $this->belongsTo(OdpAsset::class, 'odp_asset_id');
    }

    /**
     * Relasi ke Item RAB Pekerjaan
     */
    public function rabJobItems(): HasMany
    {
        return $this->hasMany(RabJobItem::class);
    }

    /**
     * Relasi ke Foto Instalasi
     */
    public function installationPhotos(): HasMany
    {
        return $this->hasMany(InstallationPhoto::class);
    }

    /**
     * Relasi ke Catatan Pekerjaan Instalasi
     */
    public function installationJobNotes(): HasMany
    {
        return $this->hasMany(InstallationJobNote::class);
    }
}
