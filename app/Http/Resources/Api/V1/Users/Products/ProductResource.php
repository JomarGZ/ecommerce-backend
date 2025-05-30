<?php

namespace App\Http\Resources\Api\V1\Users\Products;

use App\Http\Resources\Api\V1\Users\ProductCategories\ProductCategoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->whenNotNull($this->id),
            'name' => $this->whenNotNull($this->name),
            'base_price' => $this->whenNotNull($this->base_price),
            'description' => $this->whenNotNull($this->description),
            'category' =>   ProductCategoryResource::make($this->whenLoaded('category'))
        ];
    }
}
