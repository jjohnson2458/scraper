<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Scan;
use App\Models\ScanItem;
use App\Services\ScraperService;
use App\Services\OcrService;
use App\Services\ImportService;

/**
 * API Controller
 *
 * Provides JSON endpoints for the React frontend
 * including preview scraping, OCR processing, and data retrieval.
 *
 * @package    ClaudeScraper
 * @subpackage Controllers
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class ApiController extends BaseController
{
    /**
     * Preview a URL scrape without saving (AJAX).
     *
     * @return void
     */
    public function previewScrape(): void
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);
        $url = $data['url'] ?? '';

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->json(['error' => 'Invalid URL'], 400);
            return;
        }

        $scraper = new ScraperService();
        $result = $scraper->scrapeUrl($url);

        $this->json($result);
    }

    /**
     * Process an image via OCR (AJAX).
     *
     * @return void
     */
    public function processOcr(): void
    {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'No valid image uploaded'], 400);
            return;
        }

        $tmpPath = $_FILES['image']['tmp_name'];
        $ocr = new OcrService();
        $result = $ocr->processImage($tmpPath);

        $this->json($result);
    }

    /**
     * Get items for a scan (AJAX).
     *
     * @param string $id The scan ID.
     * @return void
     */
    public function getScanItems(string $id): void
    {
        $scanItemModel = new ScanItem();
        $items = $scanItemModel->getByScan((int) $id);
        $this->json(['items' => $items]);
    }

    /**
     * Update items for a scan (AJAX).
     *
     * @param string $id The scan ID.
     * @return void
     */
    public function updateScanItems(string $id): void
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);
        $items = $data['items'] ?? [];

        $scanItemModel = new ScanItem();
        $scanItemModel->deleteByScan((int) $id);
        $count = $scanItemModel->bulkInsert((int) $id, $items);

        $this->json(['success' => true, 'count' => $count]);
    }

    /**
     * Get available import platforms.
     *
     * @return void
     */
    public function getPlatforms(): void
    {
        $importService = new ImportService();
        $this->json(['platforms' => $importService->getAvailablePlatforms()]);
    }

    /**
     * Get stores for a platform.
     *
     * @param string $slug The platform slug.
     * @return void
     */
    public function getStores(string $slug): void
    {
        $importService = new ImportService();
        $this->json(['stores' => $importService->getStores($slug)]);
    }

    /**
     * Update banner_url and logo_url for a scan (AJAX).
     *
     * @param string $id The scan ID.
     * @return void
     */
    public function updateScanImages(string $id): void
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        $scanModel = new Scan();
        $scan = $scanModel->find((int) $id);
        if (!$scan) {
            $this->json(['error' => 'Scan not found'], 404);
            return;
        }

        $scanModel->update((int) $id, [
            'banner_url' => $data['banner_url'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
        ]);

        $this->json(['success' => true]);
    }
}
