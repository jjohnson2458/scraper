<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Scan;
use App\Models\ScanItem;
use App\Services\ScraperService;
use App\Services\EngineManager;
use App\Services\OcrService;
use App\Models\ErrorLog;

/**
 * Scan Controller
 *
 * Handles creating, viewing, and managing scans.
 * Supports both URL-based scraping and photo/OCR scanning.
 *
 * @package    ClaudeScraper
 * @subpackage Controllers
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class ScanController extends BaseController
{
    /** @var Scan */
    private Scan $scanModel;

    /** @var ScanItem */
    private ScanItem $scanItemModel;

    /** @var ScraperService */
    private ScraperService $scraper;

    /** @var OcrService */
    private OcrService $ocr;

    /**
     * ScanController constructor.
     */
    public function __construct()
    {
        $this->scanModel = new Scan();
        $this->scanItemModel = new ScanItem();
        $this->scraper = new ScraperService();
        $this->ocr = new OcrService();
    }

    /**
     * List all scans with pagination and filters.
     *
     * @return void
     */
    public function index(): void
    {
        $page = (int) ($this->input('page') ?? 1);
        $status = $this->input('status');
        $type = $this->input('type');
        $search = $this->input('search');

        $result = $this->scanModel->getFiltered($page, 20, $status, $type, $search);

        $this->render('scans.index', [
            'pageTitle' => 'Scan History - Claude Scraper',
            'scans' => $result['data'],
            'pagination' => $result,
            'currentStatus' => $status,
            'currentType' => $type,
            'currentSearch' => $search,
        ]);
    }

    /**
     * Show the new scan form.
     *
     * @return void
     */
    public function create(): void
    {
        $activeTab = $this->input('tab', 'url');
        $tesseractStatus = $this->ocr->checkTesseract();

        $engineManager = new EngineManager();
        $platforms = $engineManager->getPlatformsForDropdown();

        $this->render('scans.create', [
            'pageTitle' => 'New Scan - Claude Scraper',
            'activeTab' => $activeTab,
            'tesseractInstalled' => $tesseractStatus['installed'],
            'platforms' => $platforms,
            'useReact' => true,
        ]);
    }

    /**
     * Process a URL scrape request.
     *
     * @return void
     */
    public function scrapeUrl(): void
    {
        $this->validateCsrf();

        $url = trim($this->rawInput('url'));
        $platformSlug = $this->input('platform', 'auto');

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->flash('error', 'Please enter a valid URL.');
            $this->redirect('/scans/new');
            return;
        }

        // Create scan record
        $scanId = $this->scanModel->create([
            'user_id' => $_SESSION['user_id'],
            'source_type' => 'url',
            'source_value' => $url,
            'status' => 'processing',
        ]);

        // Run scraper via EngineManager
        $engineManager = new EngineManager();
        $result = $engineManager->scrape($url, $platformSlug);

        if ($result['success'] && !empty($result['items'])) {
            $count = $this->scanItemModel->bulkInsert($scanId, $result['items']);

            $updateData = [
                'title' => $result['restaurant']['name'] ?? $result['title'] ?? null,
                'status' => 'complete',
                'item_count' => $count,
            ];

            // Save restaurant banner/logo if detected
            if (!empty($result['restaurant']['banner_url'])) {
                $updateData['banner_url'] = $result['restaurant']['banner_url'];
            }
            if (!empty($result['restaurant']['logo_url'])) {
                $updateData['logo_url'] = $result['restaurant']['logo_url'];
            }

            $this->scanModel->update($scanId, $updateData);

            $platformName = $result['platform']['name'] ?? 'Generic';
            $this->flash('success', "Scan complete! {$count} items extracted via {$platformName} engine.");
            $this->redirect("/scans/{$scanId}");
        } else {
            $error = $result['error'] ?? 'No menu items could be extracted.';
            $this->scanModel->update($scanId, [
                'status' => 'failed',
                'error_message' => $error,
            ]);

            $this->flash('error', "Scan failed: {$error}");
            $this->redirect('/scans/new');
        }
    }

    /**
     * Process a photo/OCR scrape request.
     *
     * @return void
     */
    public function scrapePhoto(): void
    {
        $this->validateCsrf();

        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Please upload a valid image file.');
            $this->redirect('/scans/new?tab=photo');
            return;
        }

        $file = $_FILES['photo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff'];

        if (!in_array($file['type'], $allowedTypes)) {
            $this->flash('error', 'Invalid file type. Please upload an image file.');
            $this->redirect('/scans/new?tab=photo');
            return;
        }

        // Save uploaded file
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'ocr_' . uniqid() . '.' . $ext;
        $uploadDir = __DIR__ . '/../../public/uploads/ocr';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $uploadPath = $uploadDir . '/' . $filename;
        move_uploaded_file($file['tmp_name'], $uploadPath);

        // Create scan record
        $scanId = $this->scanModel->create([
            'user_id' => $_SESSION['user_id'],
            'source_type' => 'photo',
            'source_value' => '/uploads/ocr/' . $filename,
            'status' => 'processing',
        ]);

        // Run OCR
        $result = $this->ocr->processImage($uploadPath);

        if ($result['success'] && !empty($result['items'])) {
            $count = $this->scanItemModel->bulkInsert($scanId, $result['items']);

            $this->scanModel->update($scanId, [
                'title' => 'Photo Scan - ' . date('M j, Y g:i A'),
                'status' => 'complete',
                'item_count' => $count,
            ]);

            $this->flash('success', "OCR complete! {$count} items extracted.");
            $this->redirect("/scans/{$scanId}");
        } else {
            $error = $result['error'] ?? 'No items could be extracted from the image.';
            $this->scanModel->update($scanId, [
                'status' => 'failed',
                'error_message' => $error,
            ]);

            $this->flash('error', "OCR scan failed: {$error}");
            $this->redirect('/scans/new?tab=photo');
        }
    }

    /**
     * Show a single scan with its items.
     *
     * @param string $id The scan ID.
     * @return void
     */
    public function show(string $id): void
    {
        $scan = $this->scanModel->find((int) $id);
        if (!$scan) {
            $this->flash('error', 'Scan not found.');
            $this->redirect('/scans');
            return;
        }

        $items = $this->scanItemModel->getByScan((int) $id);
        $categories = $this->scanItemModel->getCategories((int) $id);

        $this->render('scans.show', [
            'pageTitle' => ($scan['title'] ?? 'Scan') . ' - Claude Scraper',
            'scan' => $scan,
            'items' => $items,
            'categories' => $categories,
            'useReact' => true,
        ]);
    }

    /**
     * Save edited items for a scan.
     *
     * @param string $id The scan ID.
     * @return void
     */
    public function saveItems(string $id): void
    {
        $this->validateCsrf();

        $scan = $this->scanModel->find((int) $id);
        if (!$scan) {
            $this->json(['error' => 'Scan not found'], 404);
            return;
        }

        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);
        $items = $data['items'] ?? [];

        // Delete existing and re-insert
        $this->scanItemModel->deleteByScan((int) $id);
        $count = $this->scanItemModel->bulkInsert((int) $id, $items);

        $this->scanModel->update((int) $id, ['item_count' => $count]);

        $this->json(['success' => true, 'count' => $count]);
    }

    /**
     * Delete a scan and its items.
     *
     * @param string $id The scan ID.
     * @return void
     */
    public function destroy(string $id): void
    {
        $this->validateCsrf();

        $scan = $this->scanModel->find((int) $id);
        if (!$scan) {
            $this->flash('error', 'Scan not found.');
            $this->redirect('/scans');
            return;
        }

        $this->scanModel->delete((int) $id);
        $this->flash('success', 'Scan deleted.');
        $this->redirect('/scans');
    }

    /**
     * Export scan items as CSV.
     *
     * @param string $id The scan ID.
     * @return void
     */
    public function export(string $id): void
    {
        $scan = $this->scanModel->find((int) $id);
        if (!$scan) {
            $this->flash('error', 'Scan not found.');
            $this->redirect('/scans');
            return;
        }

        $items = $this->scanItemModel->getByScan((int) $id);
        $filename = 'scan_' . $id . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Description', 'Price', 'Category', 'Image URL']);

        foreach ($items as $item) {
            fputcsv($output, [
                $item['name'],
                $item['description'] ?? '',
                $item['price'] ?? '',
                $item['category'] ?? '',
                $item['image_url'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }
}
