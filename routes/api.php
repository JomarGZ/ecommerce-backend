<?php

use App\Enums\RoleEnum;
use App\Http\Controllers\Api\V1\Users\Auth\AuthDataController;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', AuthDataController::class);