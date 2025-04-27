<?php

namespace App\Http\Controllers\Api\V1\Users\ProductCategories;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Users\ProductCategories\ProductCategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function index() 
    {
        return ProductCategoryResource::collection(Category::select('id', 'name')->has('products')->get());
    }
}
