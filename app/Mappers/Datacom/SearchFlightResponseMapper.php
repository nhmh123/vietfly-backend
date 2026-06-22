<?php

namespace App\Mappers\Datacom;

class SearchFlightResponseMapper
{
    public static function toApplication(array $response): array
    {
        $groups = collect($response['ListGroup'] ?? []);

        $outboundGroup = $groups
            ->firstWhere('Leg', 0);

        $inboundGroup = $groups
            ->firstWhere('Leg', 1);

        return [
            'session' => $response['Session'] ?? null,
            'outbound_flights' => self::mapGroups($outboundGroup),
            'inbound_flights' => $inboundGroup
                ? self::mapGroups($inboundGroup)
                : [],
        ];
    }

    private static function mapGroups(?array $group): array
    {
        if (!$group) {
            return [];
        }

        return collect($group['ListAirOption'] ?? [])
            ->map(function ($airOption) {
                $flight = $airOption['ListFlightOption'][0]['ListFlight'][0] ?? [];
                $fare = $airOption['ListFareOption'][0] ?? [];

                return [
                    'option_id' => $airOption['OptionId'],
                    'airline' => $flight['Airline'] ?? null,
                    'flight_number' => $flight['FlightNumber'] ?? null,
                    'origin' => $flight['StartPoint'] ?? null,
                    'destination' => $flight['EndPoint'] ?? null,
                    'departure_date' => $flight['DepartDate'] ?? null,
                    'arrival_date' => $flight['ArriveDate'] ?? null,
                    'duration' => $flight['Duration'] ?? null,
                    'stop_num' => $flight['StopNum'] ?? 0,
                    'fare_class' => $fare['FareClass'] ?? null,
                    'cabin_name' => $fare['CabinCode'] ?? null,
                    'availability' => $fare['Availability'] ?? null,
                    'refundable' => $fare['Refundable'] ?? false,
                    'total_fare' => $fare['TotalFare'] ?? 0,
                    'currency' => $fare['Currency'] ?? 'VND',
                ];
            })
            ->values()
            ->all();
    }
}
