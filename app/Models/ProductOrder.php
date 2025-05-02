<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductOrder extends Model
{
    use HasFactory;

    protected $primaryKey = 'product_order_id';

    protected $fillable = [
        'user_id',
        'delivery_address',
        'estimated_delivery',
        'order_date',
        'order_status',
        'payment_status',
    ];

    public function items()
    {
        return $this->hasMany(ProductOrderItem::class, 'product_order_id', 'product_order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
    