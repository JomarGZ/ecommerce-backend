<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'base_price',
        'thumbnail',
        'description',
        'published'
    ];
   
  
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function skus()
    {
        return $this->hasMany(ProductSku::class);
    }
}
