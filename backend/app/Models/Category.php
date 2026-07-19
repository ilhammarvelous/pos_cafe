<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Relationships
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
