<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Geo;

use CommonSight\Model\Value\Coordinate;

/**
 * Converts a point from MGI / Austria Lambert (EPSG:31287) to WGS84 (Q-W-AT-04, V6).
 *
 * Lambert conformal conic on the Bessel ellipsoid, then 7-parameter Helmert (position vector)
 * to WGS84; parameters as in the existing system, matched to the GeoSphere warning map.
 */
final class AustriaLambertProjection
{
    private const BESSEL_A = 6377397.155;
    private const BESSEL_RF = 299.1528128;
    private const WGS84_A = 6378137.0;
    private const WGS84_F = 1 / 298.257223563;
    private const FALSE_EASTING = 400000.0;
    private const FALSE_NORTHING = 400000.0;
    private const CENTRAL_MERIDIAN_DEG = 13.33333333333333;
    private const HELMERT = ['tx' => 577.326, 'ty' => 90.129, 'tz' => 463.919, 'rx' => 5.137, 'ry' => 1.474, 'rz' => 5.297, 'ppm' => 2.4232];

    private readonly float $e;
    private readonly float $n;
    private readonly float $af;
    private readonly float $rho0;

    public function __construct()
    {
        $f = 1 / self::BESSEL_RF;
        $this->e = sqrt(2 * $f - $f * $f);
        $phi1 = deg2rad(49.0);
        $phi2 = deg2rad(46.0);
        $this->n = log($this->m($phi1) / $this->m($phi2)) / log($this->t($phi1) / $this->t($phi2));
        $this->af = self::BESSEL_A * $this->m($phi1) / ($this->n * $this->t($phi1) ** $this->n);
        $this->rho0 = $this->af * $this->t(deg2rad(47.5)) ** $this->n;
    }

    /** @throws ProjectionOutOfRange if the result lies outside 9-18° E / 46-50° N */
    public function toWgs84(float $easting, float $northing): Coordinate
    {
        [$lat, $lon] = $this->besselGeodetic($easting, $northing);
        [$wgsLat, $wgsLon] = $this->helmertToWgs84($lat, $lon);
        $latDeg = rad2deg($wgsLat);
        $lonDeg = rad2deg($wgsLon);
        if (!is_finite($latDeg) || !is_finite($lonDeg) || $lonDeg < 9 || $lonDeg > 18 || $latDeg < 46 || $latDeg > 50) {
            throw new ProjectionOutOfRange(sprintf('EPSG:31287 point %F/%F lies outside Austria', $easting, $northing));
        }

        return Coordinate::fromLatLon(round($latDeg, 6), round($lonDeg, 6));
    }

    /** @return array{float, float} latitude and longitude on the Bessel ellipsoid in radians */
    private function besselGeodetic(float $easting, float $northing): array
    {
        $x = $easting - self::FALSE_EASTING;
        $y = $this->rho0 - ($northing - self::FALSE_NORTHING);
        $t = (hypot($x, $y) / $this->af) ** (1 / $this->n);
        $lat = M_PI / 2 - 2 * atan($t);
        for ($i = 0; $i < 15; $i++) {
            $es = $this->e * sin($lat);
            $next = M_PI / 2 - 2 * atan($t * ((1 - $es) / (1 + $es)) ** ($this->e / 2));
            $converged = abs($next - $lat) < 1e-13;
            $lat = $next;
            if ($converged) {
                break;
            }
        }
        $lon = deg2rad(self::CENTRAL_MERIDIAN_DEG) + atan2($x, $y) / $this->n;

        return [$lat, $lon];
    }

    /** @return array{float, float} latitude and longitude in WGS84 in radians */
    private function helmertToWgs84(float $lat, float $lon): array
    {
        $v = self::BESSEL_A / sqrt(1 - $this->e ** 2 * sin($lat) ** 2);
        $gx = $v * cos($lat) * cos($lon);
        $gy = $v * cos($lat) * sin($lon);
        $gz = $v * (1 - $this->e ** 2) * sin($lat);
        $rx = deg2rad(self::HELMERT['rx'] / 3600);
        $ry = deg2rad(self::HELMERT['ry'] / 3600);
        $rz = deg2rad(self::HELMERT['rz'] / 3600);
        $scale = 1 + self::HELMERT['ppm'] * 1e-6;
        $wx = self::HELMERT['tx'] + $scale * ($gx - $rz * $gy + $ry * $gz);
        $wy = self::HELMERT['ty'] + $scale * ($rz * $gx + $gy - $rx * $gz);
        $wz = self::HELMERT['tz'] + $scale * (-$ry * $gx + $rx * $gy + $gz);

        return [$this->wgs84Latitude($wx, $wy, $wz), atan2($wy, $wx)];
    }

    private function wgs84Latitude(float $x, float $y, float $z): float
    {
        $e2 = 2 * self::WGS84_F - self::WGS84_F ** 2;
        $p = hypot($x, $y);
        $lat = atan2($z, $p * (1 - $e2));
        for ($i = 0; $i < 15; $i++) {
            $v = self::WGS84_A / sqrt(1 - $e2 * sin($lat) ** 2);
            $next = atan2($z + $e2 * $v * sin($lat), $p);
            $converged = abs($next - $lat) < 1e-13;
            $lat = $next;
            if ($converged) {
                break;
            }
        }

        return $lat;
    }

    private function m(float $phi): float
    {
        return cos($phi) / sqrt(1 - $this->e ** 2 * sin($phi) ** 2);
    }

    private function t(float $phi): float
    {
        $es = $this->e * sin($phi);

        return tan(M_PI / 4 - $phi / 2) / ((1 - $es) / (1 + $es)) ** ($this->e / 2);
    }
}
