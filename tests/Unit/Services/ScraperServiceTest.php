<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\ScraperService;

/**
 * Unit tests for the Scraper Service.
 *
 * @package    ClaudeScraper
 * @subpackage Tests\Unit\Services
 * @author     J.J. Johnson <visionquest716@gmail.com>
 * @covers     \App\Services\ScraperService
 */
class ScraperServiceTest extends TestCase
{
    /** @var ScraperService */
    private ScraperService $scraper;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../../config/app.php';
        $this->scraper = new ScraperService();
    }

    /**
     * Test scrapeUrl returns expected structure on success.
     *
     * @return void
     */
    public function testScrapeUrlReturnsExpectedStructure(): void
    {
        // Use a known public page (may need to be updated if page changes)
        $result = $this->scraper->scrapeUrl('https://example.com');

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertIsBool($result['success']);
        $this->assertIsArray($result['items']);
    }

    /**
     * Test scrapeUrl handles invalid URLs.
     *
     * @return void
     */
    public function testScrapeUrlHandlesInvalidUrl(): void
    {
        $result = $this->scraper->scrapeUrl('https://this-domain-does-not-exist-12345.com');

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
        $this->assertEmpty($result['items']);
    }

    /**
     * Test downloadImage returns null for invalid URL.
     *
     * @return void
     */
    public function testDownloadImageReturnsNullForInvalidUrl(): void
    {
        $result = $this->scraper->downloadImage('https://this-does-not-exist-12345.com/image.jpg', 999);
        $this->assertNull($result);
    }
}
