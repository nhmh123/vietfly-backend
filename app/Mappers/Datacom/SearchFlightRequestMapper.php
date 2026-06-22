<?php

namespace App\Mappers\Datacom;

use Carbon\Carbon;

class SearchFlightRequestMapper
{
    public static function toDatacom(array $data): array
    {
        $routes = [
            [
                'Leg' => 0,
                'StartPoint' => strtoupper($data['origin']),
                'EndPoint' => strtoupper($data['destination']),
                'DepartDate' => Carbon::parse(
                    $data['departure_date']
                )->format('dmY'),
            ],
        ];

        if ($data['trip_type'] === 'round-trip') {
            $routes[] = [
                'Leg' => 1,
                'StartPoint' => strtoupper($data['destination']),
                'EndPoint' => strtoupper($data['origin']),
                'DepartDate' => Carbon::parse(
                    $data['return_date']
                )->format('dmY'),
            ];
        }

        return [
            'RequestInfo' => [
                // thông tin chung nếu Datacom yêu cầu
            ],

            'System' => 'VN',

            'Adt' => $data['adults'],

            'Chd' => $data['children'] ?? 0,

            'Inf' => $data['infants'] ?? 0,

            'TourCode' => '',

            'ListRoute' => $routes,

            'Option' => [
                'DirectOnly' => false,
                'NearByAirport' => false,
                'PreferCabin' => 'economy',
                'NdcOnly' => false,
                'CombineMode' => 'flight',
            ],
        ];
    }
}
