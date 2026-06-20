<?php

use Carbon\Carbon;

function validPayload(): array
{
    return [
        'trip_type' => 'one-way',
        'origin' => 'HAN',
        'destination' => 'SGN',
        'departure_date' => now()->addDay()->format('Y-m-d'),
        'adults' => 1,
        'children' => 0,
        'infants' => 0,
    ];
}

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

it('can search one way flight', function () {

    $response = $this->postJson(
        '/api/flight/search',
        validPayload()
    );

    $response->assertOk();
});

dataset('invalid search payloads', [

    'missing trip type' => [
        fn() => array_merge(validPayload(), [
            'trip_type' => null,
        ]),
        'trip_type',
    ],

    'invalid trip type' => [
        fn() => array_merge(validPayload(), [
            'trip_type' => 'abc',
        ]),
        'trip_type',
    ],

    'same origin and destination' => [
        fn() => array_merge(validPayload(), [
            'destination' => 'HAN',
        ]),
        'destination',
    ],

    'departure date in past' => [
        fn() => array_merge(validPayload(), [
            'departure_date' => '2020-01-01',
        ]),
        'departure_date',
    ],

    'round trip without return date' => [
        fn() => array_merge(validPayload(), [
            'trip_type' => 'round-trip',
        ]),
        'return_date',
    ],

    'one way with return date' => [
        fn() => array_merge(validPayload(), [
            'return_date' => now()->addDays(3)->format('Y-m-d'),
        ]),
        'return_date',
    ],

    'return date before departure date' => [
        fn() => array_merge(validPayload(), [
            'trip_type' => 'round-trip',
            'return_date' => now()->format('Y-m-d'),
            'departure_date' => now()->addDays(5)->format('Y-m-d'),
        ]),
        'return_date',
    ],

    'adult less than one' => [
        fn() => array_merge(validPayload(), [
            'adults' => 0,
        ]),
        'adults',
    ],

    'more than 9 adults' => [
        fn() => array_merge(validPayload(), [
            'adults' => 10,
        ]),
        'adults',
    ],

    'infants exceed adults' => [
        fn() => array_merge(validPayload(), [
            'infants' => 2,
        ]),
        'infants',
    ],

    'departure date exceeds one year' => [
        fn() => array_merge(validPayload(), [
            'departure_date' => Carbon::today()
                ->addYears(2)
                ->format('Y-m-d'),
        ]),
        'departure_date',
    ],

]);

it('validates invalid payload', function (
    Closure $payload,
    string $field
) {

    $response = $this->postJson(
        '/api/flight/search',
        $payload()
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

})->with('invalid search payloads');
