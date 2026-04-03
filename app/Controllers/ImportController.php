<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Import;
use App\Models\Scan;
use App\Models\ScanItem;
use App\Services\ImportService;

/**
 * Import Controller
 *
 * Manages importing scraped data into target platforms.
 *
 * @package    ClaudeScraper
 * @subpackage Controllers
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class ImportController extends BaseController
{
    /** @var Import */
    private Import $importModel;

    /** @var Scan */
    private Scan $scanModel;

    /** @var ScanItem */
    private ScanItem $scanItemModel;

    /** @var ImportService */
    private ImportService $importService;

    /**
     * ImportController constructor.
     */
    public function __construct()
    {
        $this->importModel = new Import();
        $this->scanModel = new Scan();
        $this->scanItemModel = new ScanItem();
        $this->importService = new ImportService();
    }

    /**
     * List all imports with pagination.
     *
     * @return void
     */
    public function index(): void
    {
        $page = (int) ($this->input('page') ?? 1);
        $result = $this->importModel->getWithScans($page);

        $this->render('imports.index', [
            'pageTitle' => 'Imports - Claude Scraper',
            'imports' => $result['data'],
            'pagination' => $result,
        ]);
    }

    /**
     * Show the import form for a scan.
     *
     * @param string $scanId The scan ID.
     * @return void
     */
    public function create(string $scanId): void
    {
        $scan = $this->scanModel->find((int) $scanId);
        if (!$scan) {
            $this->flash('error', 'Scan not found.');
            $this->redirect('/scans');
            return;
        }

        $items = $this->scanItemModel->getSelectedByScan((int) $scanId);
        $platforms = $this->importService->getAvailablePlatforms();

        $this->render('imports.create', [
            'pageTitle' => 'Import Items - Claude Scraper',
            'scan' => $scan,
            'items' => $items,
            'platforms' => $platforms,
            'useReact' => true,
        ]);
    }

    /**
     * Process an import.
     *
     * @return void
     */
    public function store(): void
    {
        $this->validateCsrf();

        $scanId = (int) $this->input('scan_id');
        $platform = $this->input('platform');
        $storeSlug = $this->input('store_slug');

        $scan = $this->scanModel->find($scanId);
        if (!$scan) {
            $this->flash('error', 'Scan not found.');
            $this->redirect('/scans');
            return;
        }

        $items = $this->scanItemModel->getSelectedByScan($scanId);
        if (empty($items)) {
            $this->flash('error', 'No items selected for import.');
            $this->redirect("/scans/{$scanId}");
            return;
        }

        // Create import record
        $importId = $this->importModel->create([
            'scan_id' => $scanId,
            'user_id' => $_SESSION['user_id'],
            'target_platform' => $platform,
            'target_store_slug' => $storeSlug,
            'status' => 'processing',
            'total_items' => count($items),
        ]);

        // Execute import
        $result = $this->importService->importItems($platform, $storeSlug, $items);

        $this->importModel->update($importId, [
            'status' => $result['status'],
            'imported_items' => $result['imported'],
            'failed_items' => $result['failed'],
            'error_log' => !empty($result['errors']) ? json_encode($result['errors']) : null,
        ]);

        // Update scan status
        if ($result['status'] === 'complete') {
            $this->scanModel->update($scanId, ['status' => 'imported']);
        }

        $this->flash(
            $result['status'] === 'complete' ? 'success' : 'warning',
            "Import {$result['status']}: {$result['imported']} items imported, {$result['failed']} failed."
        );

        $this->redirect("/imports/{$importId}");
    }

    /**
     * Show import details.
     *
     * @param string $id The import ID.
     * @return void
     */
    public function show(string $id): void
    {
        $import = $this->importModel->find((int) $id);
        if (!$import) {
            $this->flash('error', 'Import not found.');
            $this->redirect('/imports');
            return;
        }

        $scan = $this->scanModel->find($import['scan_id']);

        $this->render('imports.show', [
            'pageTitle' => 'Import Details - Claude Scraper',
            'import' => $import,
            'scan' => $scan,
        ]);
    }
}
