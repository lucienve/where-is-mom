<?php

/**
 * File containing ExifExtractorTest.
 *
 * @category Utility
 * @package  WhereIsMom
 * @author   WhereIsMom <lucienve@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/lucienve/where-is-mom
 */

declare(strict_types=1);

namespace Tests;

use App\ExifExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Class ExifExtractorTest
 *
 * Tests the ExifExtractor utility class.
 *
 * @category Utility
 * @package  WhereIsMom
 * @author   WhereIsMom <lucienve@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/lucienve/where-is-mom
 */
class ExifExtractorTest extends TestCase
{
    /**
     * Tests that a file without valid EXIF GPS data gracefully returns null.
     *
     * @return void
     */
    public function testExtractGpsReturnsNullForMissingOrInvalidFile(): void
    {
        // Give it a non-existent file or a file without EXIF
        $result = ExifExtractor::extractGps(__DIR__ . '/dummy-non-existent.jpg');
        $this->assertNull($result, 'Expected null when EXIF GPS data is missing or unreadable.');
    }

    /**
     * Tests that a valid image with GPS and timestamp parses correctly.
     *
     * @return void
     */
    public function testExtractGpsValidImage(): void
    {
        $result = ExifExtractor::extractGps(__DIR__ . '/fixtures/valid_gps.jpg');

        $this->assertIsArray($result);
        $this->assertEqualsWithDelta(40.758333, $result['lat'], 0.0001);
        $this->assertEqualsWithDelta(-73.983333, $result['lng'], 0.0001);
        $this->assertEquals('2026:04:17 12:00:00', $result['timestamp']);
    }

    /**
     * Tests that an image with EXIF but no GPS gracefully returns null.
     *
     * @return void
     */
    public function testExtractGpsMissingGpsData(): void
    {
        $result = ExifExtractor::extractGps(__DIR__ . '/fixtures/missing_gps.jpg');
        $this->assertNull($result, 'Expected null when GPS data is missing from EXIF.');
    }

    /**
     * Tests that an image with GPS but no timestamp parses GPS and returns null for timestamp.
     *
     * @return void
     */
    public function testExtractGpsMissingTimestamp(): void
    {
        $result = ExifExtractor::extractGps(__DIR__ . '/fixtures/missing_timestamp.jpg');

        $this->assertIsArray($result);
        $this->assertEqualsWithDelta(40.758333, $result['lat'], 0.0001);
        $this->assertEqualsWithDelta(-73.983333, $result['lng'], 0.0001);
        $this->assertNull($result['timestamp']);
    }
}
