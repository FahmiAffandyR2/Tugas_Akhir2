<?php

namespace App\Services;

use Google\Auth\OAuth2;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class GoogleRoutesService
{
    private $client;

    public function __construct()
    {
        $options = [];
        $caBundle = config('services.google_maps.ca_bundle');

        if ($caBundle) {
            $options['verify'] = $caBundle;
        }

        $this->client = new Client($options);
    }

    public function computeRoutes($origin, $destination, $waypoints = [])
    {
        $api_key = config('services.google_maps.api_key');
        $url = 'https://routes.googleapis.com/directions/v2:computeRoutes';

        $payload = [
            'origin' => [
                'location' => [
                    'latLng' => $origin,
                ]
            ],
            'destination' => [
                'location' => [
                    'latLng' => $destination,
                ]
            ],
            'travelMode' => 'DRIVE',
            'routingPreference' => 'TRAFFIC_AWARE',
            'computeAlternativeRoutes' => true,
            'languageCode' => 'en-US',
            'units' => 'IMPERIAL',
            'intermediates' => $waypoints,
            'polylineEncoding' => 'GEO_JSON_LINESTRING',
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'X-Goog-FieldMask' => 'routes.duration,routes.description,routes.polyline',
            'X-Goog-Api-Key'  => $api_key
        ];

        $response = $this->client->post($url, [
            'headers' => $headers,
            'json' => $payload
        ]);

        return json_decode($response->getBody(), true);
    }
}
