<?php

namespace App\Models;

use App\Enums\InventoryType;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'inventories';

    protected $fillable = [
        'quantity',
        'store_id',
        'product_id',
        'type',
        'user_id',
    ];

    protected $casts = [
        'type' => InventoryType::class,
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
