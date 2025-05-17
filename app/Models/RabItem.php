<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RabItem extends Model
{
    protected $fillable = [
        'item_code',
        'name',
        'description',
        'unit_of_measure',
        'quantity',
        'price',
        'category',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id'
    ];
    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
