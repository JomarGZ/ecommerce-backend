<?php

use App\Enums\RoleEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    $user = $request->user()->load('roles', 'permissions');
    return response()->json([
        'success' => true,
        'data' => [
            'user' => $user->only(['id', 'name', 'email']),
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ],
        'message' => 'Fetch users data successfully.',
        'code' => Response::HTTP_OK
    ]);
});
