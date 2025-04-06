<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'store_name',
        'address',
        'phone',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
