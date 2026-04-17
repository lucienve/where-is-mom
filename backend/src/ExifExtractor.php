<?php

declare(strict_types=1);

namespace App;

/**
 * Class ExifExtractor
 *
 * Provides utility methods for extracting GPS coordinates from image files.
 */
class ExifExtractor
{
    /**
     * Extracts latitude and longitude from an image file's EXIF data.
     *
     * @param string $filePath The path to the temporary uploaded image file.
     * @return array<string, float|string|null>|null Returns associative array with 'lat' and 'lng', or null if missing.
     */
    public static function extractGps(string $filePath): ?array
    {
        // Suppress warnings as exif_read_data can complain about certain invalid JPEGs
        $exif = @exif_read_data($filePath);

        if (
            !$exif || !isset($exif['GPSLatitude']) || !isset($exif['GPSLongitude']) ||
            !isset($exif['GPSLatitudeRef']) || !isset($exif['GPSLongitudeRef'])
        ) {
            return null;
        }

        $lat = self::getGpsCoordinate($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
        $lng = self::getGpsCoordinate($exif['GPSLongitude'], $exif['GPSLongitudeRef']);

        // Also try to extract datetime original
        $timestamp = null;
        if (isset($exif['DateTimeOriginal'])) {
            $timestamp = $exif['DateTimeOriginal'];
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'timestamp' => $timestamp
        ];
    }

    /**
     * Converts EXIF GPS coordinate arrays to a decimal float.
     *
     * @param array<int, string> $coordinateArray
     * @param string $hemisphereRef
     * @return float
     */
    private static function getGpsCoordinate(array $coordinateArray, string $hemisphereRef): float
    {
        $degrees = self::evalFraction($coordinateArray[0] ?? "0/1");
        $minutes = self::evalFraction($coordinateArray[1] ?? "0/1");
        $seconds = self::evalFraction($coordinateArray[2] ?? "0/1");


        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        $hemisphereRef = strtoupper($hemisphereRef);
        if ($hemisphereRef === 'S' || $hemisphereRef === 'W') {
            $decimal *= -1;
        }

        return $decimal;
    }

    /**
     * Evaluates EXIF fraction string (e.g. "300/10") into a float.
     *
     * @param string $fraction
     * @return float
     */
    private static function evalFraction(string $fraction): float
    {
        $parts = explode('/', $fraction);
        if (count($parts) === 2) {
            $denominator = (float)$parts[1];
            if ($denominator === 0.0) {
                return 0.0;
            }
            return (float)$parts[0] / $denominator;
        }
        return (float)$fraction;
    }
}
