<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../ExifExtractor.php';

class ExifExtractorTest extends TestCase
{
    /**
     * Tests that a file without valid EXIF GPS data gracefully returns null.
     */
    public function testExtractGpsReturnsNullForMissingOrInvalidFile(): void
    {
        // Give it a non-existent file or a file without EXIF
        $result = ExifExtractor::extractGps(__DIR__ . '/dummy-non-existent.jpg');
        $this->assertNull($result, 'Expected null when EXIF GPS data is missing or unreadable.');
    }

    /**
     * Use Reflection to test the private getGpsCoordinate method logic.
     */
    public function testGetGpsCoordinateCalculation(): void
    {
        $reflection = new ReflectionClass('ExifExtractor');
        $method = $reflection->getMethod('getGpsCoordinate');
        $method->setAccessible(true);

        // Typical N/E coordinate
        // 40 degrees, 45 minutes, 30 seconds
        $coordArray = ["40/1", "45/1", "300/10"]; 
        // 40 + (45/60) + (30/3600) = 40 + 0.75 + 0.008333... = 40.758333...
        $result = $method->invoke(null, $coordArray, 'N');
        $this->assertEqualsWithDelta(40.758333, $result, 0.0001);

        // Typical S/W coordinate (should be negative)
        // 73 degrees, 59 minutes, 0 seconds
        $coordArrayW = ["73/1", "59/1", "0/1"];
        // 73 + (59/60) = 73.98333...
        $resultW = $method->invoke(null, $coordArrayW, 'W');
        $this->assertEqualsWithDelta(-73.983333, $resultW, 0.0001);
    }

    /**
     * Use Reflection to test the private evalFraction method logic.
     */
    public function testEvalFraction(): void
    {
        $reflection = new ReflectionClass('ExifExtractor');
        $method = $reflection->getMethod('evalFraction');
        $method->setAccessible(true);

        $this->assertEquals(40.0, $method->invoke(null, "40/1"));
        $this->assertEquals(0.75, $method->invoke(null, "3/4"));
        $this->assertEquals(30.0, $method->invoke(null, "300/10"));
        
        // Handle malformed/zero denominator safely
        $this->assertEquals(0.0, $method->invoke(null, "5/0"));
        
        // Handle pure numbers / malformed
        $this->assertEquals(45.0, $method->invoke(null, "45"));
    }
}
