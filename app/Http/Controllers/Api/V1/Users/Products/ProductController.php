<?php

namespace App\Http\Controllers\Api\V1\Users\Products;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Users\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $products = Product::select('id', 'name', 'slug', 'base_price', 'category_id')
            ->with(['category:id,name'])
            ->filterByCategory($request->category_id)
            ->paginate(9);

        return ProductResource::collection($products);
    }
}
