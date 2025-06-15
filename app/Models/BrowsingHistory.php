<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrowsingHistory extends Model
{
    use HasFactory;

    protected $table = 'browsing_history'; // Explicitly set table name
    protected $fillable = ['user_id', 'product_id', 'viewed_at'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}