<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductOrder extends Model
{
    use HasFactory;

    protected $primaryKey = 'product_order_id';

    protected $fillable = [
        'buyer_id',
        'seller_id',
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

    // Buyer relationship
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id', 'id');
    }

    // Seller relationship
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }
    public function product()
{
    return $this->belongsTo(Product::class, 'product_id');
}

}
