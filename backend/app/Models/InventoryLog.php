<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryLog extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'inventory_logs';

    protected $fillable = [
        'product_id',
        'quantity_change',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'reason' => 'string',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
