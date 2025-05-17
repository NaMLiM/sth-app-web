<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdpAsset extends Model
{
    // Di App\Models\OdpAsset.php
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_user_id');
    }

    public function installationJobs()
    {
        return $this->hasMany(InstallationJob::class, 'odp_asset_id');
    }
}
