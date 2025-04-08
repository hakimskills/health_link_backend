<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'description', 
        'phone',
        'email',
        'address',
        'specialties',
        'logo_path',
        'is_verified'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'specialties' => 'array'
    ];

    // Relationship to owner (user)
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    // Relationship to products
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // Accessor for logo URL
    public function getLogoUrlAttribute()
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : null;
    }
}