<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $primaryKey = 'product_id';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'product_name',
        'description',
        'image',
        'store_price',
        'inventory_price',
        'stock',
        'category',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
