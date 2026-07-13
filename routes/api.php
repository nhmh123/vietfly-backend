<?php

use App\Http\Controllers\Flight\FlightSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'UP'
    ]);
});

Route::prefix('/flights')->group(function () {
    Route::post('search', [FlightSearchController::class, 'search'])->name('flight.search');
});
