<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/up', function () {
    return response()->json([
        'status' => 'ok',
        'time' => now()->toIso8601String(),
        'version' => config('app.version', 'dev'),
    ]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    // TODO: expose a public API for programmatic downloads.
});
