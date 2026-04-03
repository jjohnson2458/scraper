<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Scan;

/**
 * Unit tests for the Scan model.
 *
 * @package    ClaudeScraper
 * @subpackage Tests\Unit\Models
 * @author     J.J. Johnson <visionquest716@gmail.com>
 * @covers     \App\Models\Scan
 */
class ScanModelTest extends TestCase
{
    /** @var Scan */
    private Scan $scan;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../../config/app.php';
        $this->scan = new Scan();
    }

    /**
     * Test that all() returns an array.
     *
     * @return void
     */
    public function testAllReturnsArray(): void
    {
        $result = $this->scan->all();
        $this->assertIsArray($result);
    }

    /**
     * Test paginate returns correct structure.
     *
     * @return void
     */
    public function testPaginateReturnsCorrectStructure(): void
    {
        $result = $this->scan->paginate(1, 10);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('perPage', $result);
        $this->assertArrayHasKey('lastPage', $result);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(10, $result['perPage']);
    }

    /**
     * Test getStatusCounts returns associative array.
     *
     * @return void
     */
    public function testGetStatusCountsReturnsArray(): void
    {
        $result = $this->scan->getStatusCounts();
        $this->assertIsArray($result);
    }

    /**
     * Test getRecent returns limited results.
     *
     * @return void
     */
    public function testGetRecentReturnsLimitedResults(): void
    {
        $result = $this->scan->getRecent(3);
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(3, count($result));
    }

    /**
     * Test getFiltered with status filter.
     *
     * @return void
     */
    public function testGetFilteredWithStatus(): void
    {
        $result = $this->scan->getFiltered(1, 20, 'complete');
        $this->assertArrayHasKey('data', $result);
        foreach ($result['data'] as $scan) {
            $this->assertEquals('complete', $scan['status']);
        }
    }

    /**
     * Test creating and deleting a scan.
     *
     * @return void
     */
    public function testCreateAndDeleteScan(): void
    {
        $id = $this->scan->create([
            'user_id' => 1,
            'source_type' => 'url',
            'source_value' => 'https://test.com/menu',
            'status' => 'pending',
        ]);

        $this->assertNotEmpty($id);

        $found = $this->scan->find($id);
        $this->assertNotNull($found);
        $this->assertEquals('https://test.com/menu', $found['source_value']);

        $this->scan->delete($id);
        $deleted = $this->scan->find($id);
        $this->assertNull($deleted);
    }
}
