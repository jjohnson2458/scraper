<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\ImportService;

/**
 * Unit tests for the Import Service.
 *
 * @package    ClaudeScraper
 * @subpackage Tests\Unit\Services
 * @author     J.J. Johnson <visionquest716@gmail.com>
 * @covers     \App\Services\ImportService
 */
class ImportServiceTest extends TestCase
{
    /** @var ImportService */
    private ImportService $importService;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../../config/app.php';
        $this->importService = new ImportService();
    }

    /**
     * Test getAvailablePlatforms returns array.
     *
     * @return void
     */
    public function testGetAvailablePlatformsReturnsArray(): void
    {
        $platforms = $this->importService->getAvailablePlatforms();
        $this->assertIsArray($platforms);
        $this->assertNotEmpty($platforms);

        foreach ($platforms as $p) {
            $this->assertArrayHasKey('slug', $p);
            $this->assertArrayHasKey('name', $p);
            $this->assertArrayHasKey('available', $p);
        }
    }

    /**
     * Test getStores returns array for valid platform.
     *
     * @return void
     */
    public function testGetStoresReturnsArray(): void
    {
        $stores = $this->importService->getStores('claude_takeout');
        $this->assertIsArray($stores);
    }

    /**
     * Test getStores returns empty for unknown platform.
     *
     * @return void
     */
    public function testGetStoresReturnsEmptyForUnknown(): void
    {
        $stores = $this->importService->getStores('nonexistent_platform');
        $this->assertEmpty($stores);
    }

    /**
     * Test importItems fails for unknown platform.
     *
     * @return void
     */
    public function testImportItemsFailsForUnknownPlatform(): void
    {
        $result = $this->importService->importItems('nonexistent', 'default', [
            ['name' => 'Test', 'price' => 9.99],
        ]);

        $this->assertEquals('failed', $result['status']);
        $this->assertEquals(0, $result['imported']);
    }
}
