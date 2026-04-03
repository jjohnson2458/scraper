<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\OcrService;

/**
 * Unit tests for the OCR Service.
 *
 * @package    ClaudeScraper
 * @subpackage Tests\Unit\Services
 * @author     J.J. Johnson <visionquest716@gmail.com>
 * @covers     \App\Services\OcrService
 */
class OcrServiceTest extends TestCase
{
    /** @var OcrService */
    private OcrService $ocr;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../../config/app.php';
        $this->ocr = new OcrService();
    }

    /**
     * Test parseMenuText extracts items with prices.
     *
     * @return void
     */
    public function testParseMenuTextExtractsItems(): void
    {
        $text = <<<EOT
APPETIZERS
Loaded Nachos $12.99
Crispy chicken, cheese, jalapenos, sour cream

Mozzarella Sticks $8.99
Served with marinara sauce

ENTREES
Grilled Salmon $24.99
Fresh Atlantic salmon with lemon butter

Ribeye Steak $32.99
12oz USDA Choice, served with mashed potatoes
EOT;

        $items = $this->ocr->parseMenuText($text);

        $this->assertIsArray($items);
        $this->assertGreaterThanOrEqual(4, count($items), 'Should extract at least 4 items');

        // Check first item
        $nachos = $items[0];
        $this->assertStringContainsString('Nachos', $nachos['name']);
        $this->assertEquals(12.99, $nachos['price']);
        $this->assertEquals('Appetizers', $nachos['category']);
    }

    /**
     * Test parseMenuText handles text without categories.
     *
     * @return void
     */
    public function testParseMenuTextWithoutCategories(): void
    {
        $text = <<<EOT
Cheeseburger $10.99
Classic Fries $4.99
Milkshake $6.50
EOT;

        $items = $this->ocr->parseMenuText($text);
        $this->assertGreaterThanOrEqual(3, count($items));

        foreach ($items as $item) {
            $this->assertNull($item['category']);
            $this->assertNotNull($item['price']);
        }
    }

    /**
     * Test parseMenuText skips non-menu content.
     *
     * @return void
     */
    public function testParseMenuTextSkipsNonMenuContent(): void
    {
        $text = <<<EOT
Phone: 555-1234
Hours: Mon-Fri 9am-9pm
www.example.com
Burger $12.99
EOT;

        $items = $this->ocr->parseMenuText($text);
        $this->assertCount(1, $items);
        $this->assertStringContainsString('Burger', $items[0]['name']);
    }

    /**
     * Test parseMenuText with descriptions.
     *
     * @return void
     */
    public function testParseMenuTextWithDescriptions(): void
    {
        $text = <<<EOT
Fish and Chips $16.99
Beer-battered cod with hand-cut fries and tartar sauce on the side
EOT;

        $items = $this->ocr->parseMenuText($text);
        $this->assertNotEmpty($items);
        $this->assertStringContainsString('Fish', $items[0]['name']);
        $this->assertEquals(16.99, $items[0]['price']);
    }

    /**
     * Test processImage returns error for missing file.
     *
     * @return void
     */
    public function testProcessImageReturnsErrorForMissingFile(): void
    {
        $result = $this->ocr->processImage('/nonexistent/file.jpg');
        $this->assertFalse($result['success']);
        $this->assertEquals('Image file not found.', $result['error']);
    }

    /**
     * Test checkTesseract returns correct structure.
     *
     * @return void
     */
    public function testCheckTesseractReturnsStructure(): void
    {
        $result = $this->ocr->checkTesseract();
        $this->assertArrayHasKey('installed', $result);
        $this->assertArrayHasKey('version', $result);
        $this->assertArrayHasKey('path', $result);
        $this->assertIsBool($result['installed']);
    }
}
