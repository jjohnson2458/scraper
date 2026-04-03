<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Scan;
use App\Models\ScanItem;

/**
 * Unit tests for the ScanItem model.
 *
 * @package    ClaudeScraper
 * @subpackage Tests\Unit\Models
 * @author     J.J. Johnson <visionquest716@gmail.com>
 * @covers     \App\Models\ScanItem
 */
class ScanItemModelTest extends TestCase
{
    /** @var ScanItem */
    private ScanItem $scanItem;

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
        $this->scanItem = new ScanItem();
        $this->scan = new Scan();
    }

    /**
     * Test getByScan returns items for a valid scan.
     *
     * @return void
     */
    public function testGetByScanReturnsItems(): void
    {
        // Use the seeded sample scan (ID 1)
        $items = $this->scanItem->getByScan(1);
        $this->assertIsArray($items);
    }

    /**
     * Test bulkInsert creates multiple items.
     *
     * @return void
     */
    public function testBulkInsert(): void
    {
        // Create a test scan first
        $scanId = $this->scan->create([
            'user_id' => 1,
            'source_type' => 'url',
            'source_value' => 'https://test.com/bulk',
            'status' => 'pending',
        ]);

        $items = [
            ['name' => 'Test Item 1', 'price' => 9.99, 'category' => 'Test'],
            ['name' => 'Test Item 2', 'price' => 14.99, 'category' => 'Test'],
            ['name' => 'Test Item 3', 'price' => 7.50, 'category' => 'Other'],
        ];

        $count = $this->scanItem->bulkInsert($scanId, $items);
        $this->assertEquals(3, $count);

        $retrieved = $this->scanItem->getByScan($scanId);
        $this->assertCount(3, $retrieved);

        // Cleanup
        $this->scanItem->deleteByScan($scanId);
        $this->scan->delete($scanId);
    }

    /**
     * Test getSelectedByScan only returns selected items.
     *
     * @return void
     */
    public function testGetSelectedByScan(): void
    {
        $items = $this->scanItem->getSelectedByScan(1);
        $this->assertIsArray($items);
        foreach ($items as $item) {
            $this->assertEquals(1, $item['is_selected']);
        }
    }

    /**
     * Test getCategories returns distinct categories.
     *
     * @return void
     */
    public function testGetCategories(): void
    {
        $categories = $this->scanItem->getCategories(1);
        $this->assertIsArray($categories);
    }

    /**
     * Test deleteByScan removes all items for a scan.
     *
     * @return void
     */
    public function testDeleteByScan(): void
    {
        $scanId = $this->scan->create([
            'user_id' => 1,
            'source_type' => 'url',
            'source_value' => 'https://test.com/delete',
            'status' => 'pending',
        ]);

        $this->scanItem->bulkInsert($scanId, [
            ['name' => 'Delete Me 1', 'price' => 5.00],
            ['name' => 'Delete Me 2', 'price' => 10.00],
        ]);

        $this->scanItem->deleteByScan($scanId);
        $remaining = $this->scanItem->getByScan($scanId);
        $this->assertEmpty($remaining);

        $this->scan->delete($scanId);
    }
}
