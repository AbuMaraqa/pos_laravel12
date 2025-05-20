<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $table = 'stores';

    protected $fillable = [
        'name',
        'address',
        'notes',
    ];

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }
}
