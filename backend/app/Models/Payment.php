<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'amount',
        'method',
        'status',
        'midtrans_transaction_id',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'method' => 'string',
        'status' => 'string',
        'created_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
