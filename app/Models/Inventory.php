<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventory';

    protected $primaryKey = 'inventory_id';

    protected $fillable = [
        'owner_id',
        'inventory_name',
        'address',
        'phone',
    ];

    // Optional: Relationship to User (owner)
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
