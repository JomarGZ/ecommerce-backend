<?php

use App\Http\Controllers\Api\V1\Users\Products\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('products',[ProductController::class, 'index']);