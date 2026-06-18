<?php

namespace App\Support\Http;

use Illuminate\Support\Facades\Http;

class HttpClient
{
    public static function mockapi(){
        return Http::timeout(30)
            ->acceptJson();
    }
}
