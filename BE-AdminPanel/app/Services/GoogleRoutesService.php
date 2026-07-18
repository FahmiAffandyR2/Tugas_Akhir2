<?php

namespace App\Services;

use Google\Auth\OAuth2;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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

        if (empty($api_key)) {
            return $this->computeWithOsrm($origin, $destination, $waypoints);
        }

        try {
            return $this->computeWithGoogle($origin, $destination, $waypoints, $api_key);
        } catch (GuzzleException $e) {
            Log::warning('Google Routes API unavailable; falling back to OSRM.', [
                'status' => method_exists($e, 'getResponse') && $e->getResponse()
                    ? $e->getResponse()->getStatusCode()
                    : null,
                'error' => $e->getMessage(),
            ]);

            return $this->computeWithOsrm($origin, $destination, $waypoints);
        }
    }

    private function computeWithGoogle($origin, $destination, $waypoints, $apiKey)
    {
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
            'X-Goog-Api-Key'  => $apiKey
        ];

        $response = $this->client->post($url, [
            'headers' => $headers,
            'json' => $payload
        ]);

        return json_decode($response->getBody(), true);
    }

    private function computeWithOsrm($origin, $destination, $waypoints = [])
    {
        $points = [$origin];

        foreach ($waypoints as $waypoint) {
            $latLng = $waypoint['location']['latLng'] ?? $waypoint['latLng'] ?? $waypoint;
            if (isset($latLng['latitude'], $latLng['longitude'])) {
                $points[] = $latLng;
            }
        }

        $points[] = $destination;
        $coordinates = collect($points)->map(function ($point) {
            return $point['longitude'] . ',' . $point['latitude'];
        })->implode(';');

        $response = $this->client->get(
            'https://router.project-osrm.org/route/v1/driving/' . $coordinates,
            [
                'query' => [
                    'alternatives' => 'true',
                    // A simplified geometry is detailed enough for map display
                    // and avoids persisting thousands of unnecessary points.
                    'overview' => 'simplified',
                    'geometries' => 'geojson',
                    'steps' => 'false',
                ],
            ]
        );

        $data = json_decode($response->getBody(), true);
        if (($data['code'] ?? null) !== 'Ok' || empty($data['routes'])) {
            throw new \RuntimeException($data['message'] ?? 'OSRM could not calculate this route.');
        }

        return [
            'provider' => 'osrm',
            'routes' => collect($data['routes'])->map(function ($route, $index) {
                return [
                    'description' => 'OpenStreetMap Route ' . ($index + 1),
                    'duration' => ($route['duration'] ?? 0) . 's',
                    'polyline' => [
                        'geoJsonLinestring' => [
                            'type' => 'LineString',
                            'coordinates' => $route['geometry']['coordinates'] ?? [],
                        ],
                    ],
                ];
            })->values()->all(),
        ];
    }
}
