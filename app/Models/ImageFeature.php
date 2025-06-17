<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageFeature extends Model
{
    protected $fillable = ['image_id', 'feature_vector'];

    protected $casts = [
        'feature_vector' => 'array',
    ];

    public function image()
    {
        return $this->belongsTo(ProductImage::class, 'image_id');
    }
}