<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'sku',
        'type',
        'status',
        'regular_price',
        'sale_price',
        'price',
        'sale_start_at',
        'sale_end_at',
        'stock_status',
        'stock_quantity',
        'manage_stock',
        'weight',
        'dimensions',
        'description',
        'short_description',
        'featured_image',
        'gallery',
        'categories',
        'tags',
        'attributes',
        'variations',
        'external_url',
        'button_text',
        'remote_wp_id',
        'synced_at',
    ];

    protected $casts = [
        'sale_start_at' => 'datetime',
        'sale_end_at'   => 'datetime',
        'synced_at'     => 'datetime',
        'manage_stock'  => 'boolean',
        'gallery'       => 'array',
        'categories'    => 'array',
        'tags'          => 'array',
        'attributes'    => 'array',
        'variations'    => 'array',
        'dimensions'    => 'array',
    ];

    // Self-relation for variable product variations
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    // Convenient scopes
    public function scopeParents($q)   { return $q->whereNull('parent_id'); }
    public function scopeChildren($q)  { return $q->whereNotNull('parent_id'); }
    public function scopeVariable($q)  { return $q->where('type', 'variable'); }
    public function scopeVariation($q) { return $q->where('type', 'variation'); }
}
