<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSku extends Model
{

    protected $fillable = [
        'product_id',
        'sku_code',
        'price',
        'stock',
        'is_active'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variations()
    {
        return $this->belongsToMany(ProductSku::class, 'sku_variation_combinations');
    }
    
}
