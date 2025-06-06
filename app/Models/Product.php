<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $primaryKey = 'product_id';

    protected $fillable = [
        'store_id',
        'product_name',
        'description',
        'price',
        'inventory_price',
        'stock',
        'category',
        'type',
        'condition' 
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    // ✅ Relationship to images
    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }

    // ✅ Optional: Get only the primary image
    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class, 'product_id')->where('is_primary', true);
    }
    public function ratings()
{
    return $this->hasMany(ProductRating::class, 'product_id', 'product_id');
}

// ⭐ Average rating accessor (optional)
public function getAverageRatingAttribute()
{
    return $this->ratings()->avg('rating');
}

}
