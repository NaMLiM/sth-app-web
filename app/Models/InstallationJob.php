<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallationJob extends Model
{
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
