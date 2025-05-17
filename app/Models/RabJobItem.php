<?php

namespace App\Models;

// app/Models/RabJobItem.php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RabJobItem extends Model
{
    // ...
    protected $fillable = [
        'installation_job_id',
        'rab_item_id',
        'quantity',
        'price_at_creation',
        'line_total',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price_at_creation' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function installationJob(): BelongsTo
    {
        return $this->belongsTo(InstallationJob::class);
    }

    public function rabItem(): BelongsTo // Master item
    {
        return $this->belongsTo(RabItem::class, 'rab_item_id');
    }
}
