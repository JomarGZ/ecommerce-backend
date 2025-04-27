<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    protected $fillable = [
        'product_id',
        'variation_type',
        'variation_value',
        'product_image',
        'price_adjustment'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function skus()
    {
        return $this->belongsToMany(ProductSku::class, 'sku_variation_combinations');
    }
    
}
