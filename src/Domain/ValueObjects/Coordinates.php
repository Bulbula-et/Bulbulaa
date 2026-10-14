<?php
declare(strict_types=1);

namespace Bulbula\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable Geographic Coordinates Value Object (C-07 Nearby Discovery, C-10 Maps).
 * Provides indexed bounding-box calculation and Haversine distance without PostGIS.
 */
final class Coordinates
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude
    ) {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException('Latitude must be between -90 and 90 degrees.');
        }
        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException('Longitude must be between -180 and 180 degrees.');
        }
    }

    /**
     * Computes a SQL B-Tree index-friendly bounding box around this coordinate.
     *
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}
     */
    public function boundingBox(int $radiusMeters): array
    {
        $earthRadiusMeters = 6371000.0;
        $latDelta = rad2deg($radiusMeters / $earthRadiusMeters);
        $cosLat   = cos(deg2rad($this->latitude));
        $lngDelta = $cosLat > 0.0001
            ? rad2deg($radiusMeters / ($earthRadiusMeters * $cosLat))
            : 180.0;

        return [
            'min_lat' => round($this->latitude - $latDelta, 7),
            'max_lat' => round($this->latitude + $latDelta, 7),
            'min_lng' => round($this->longitude - $lngDelta, 7),
            'max_lng' => round($this->longitude + $lngDelta, 7),
        ];
    }

    /**
     * Spherical Haversine distance in meters.
     */
    public function distanceMetersTo(self $other): int
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($other->latitude - $this->latitude);
        $dLng = deg2rad($other->longitude - $this->longitude);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($this->latitude)) * cos(deg2rad($other->latitude)) * (sin($dLng / 2) ** 2);

        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));
        return (int) round($earthRadius * $c);
    }

    public static function formatDistanceLabel(?int $meters): string
    {
        if ($meters === null) {
            return '';
        }
        if ($meters < 1000) {
            return $meters . ' m away';
        }
        return number_format($meters / 1000, 1) . ' km away';
    }

    /**
     * C-10 Static-first Open-in-Maps Directions Hand-off URL.
     */
    public function googleMapsDirectionsUrl(): string
    {
        return sprintf(
            'https://www.google.com/maps/dir/?api=1&destination=%0.7f,%0.7f',
            $this->latitude,
            $this->longitude
        );
    }
}
