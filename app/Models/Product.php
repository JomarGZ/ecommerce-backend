<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
   
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        }); 

    }
  
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

    protected function scopeFilterByCategory(Builder $query, ?array $categoryIds): Builder
    {
        return $query->when($categoryIds, fn (Builder $query) => $query->whereIn('category_id', $categoryIds));
    }
}
