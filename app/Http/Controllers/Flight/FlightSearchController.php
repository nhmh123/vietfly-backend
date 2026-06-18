<?php

namespace App\Http\Controllers\Flight;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FlightSearchController extends Controller
{
    public function __construct() {}

    public function __invoke(Request $request) {
        return response()->json([
            'message' => 'Flight search endpoint hit',
            'data' => $request->all()
        ]);
    }
}
