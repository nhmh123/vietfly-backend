<?php

use App\Http\Controllers\Flight\FlightSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'UP'
    ]);
});

Route::post('/flight/search', FlightSearchController::class)->name('flight.search');
