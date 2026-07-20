<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PagerNumber extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'pager_numbers';

    protected $fillable = [
        'number',
        'status',
        'current_order_id',
        'buzzer_status',
    ];

    protected $casts = [
        'number' => 'integer',
        'status' => 'string',
        'buzzer_status' => 'string',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function currentOrder()
    {
        return $this->belongsTo(Order::class, 'current_order_id');
    }
}
