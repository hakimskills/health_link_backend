<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductOrderItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'product_order_item_id';

    protected $fillable = [
        'product_order_id',
        'product_id',
        'quantity',
    ];

    public function order()
    {
        return $this->belongsTo(ProductOrder::class, 'product_order_id', 'product_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
}
