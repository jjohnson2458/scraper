<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Scan;
use App\Models\Import;

/**
 * Dashboard Controller
 *
 * Displays the admin dashboard with scan/import summaries.
 *
 * @package    ClaudeScraper
 * @subpackage Controllers
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class DashboardController extends BaseController
{
    /** @var Scan */
    private Scan $scanModel;

    /** @var Import */
    private Import $importModel;

    /**
     * DashboardController constructor.
     */
    public function __construct()
    {
        $this->scanModel = new Scan();
        $this->importModel = new Import();
    }

    /**
     * Display the dashboard.
     *
     * @return void
     */
    public function index(): void
    {
        $statusCounts = $this->scanModel->getStatusCounts();
        $recentScans = $this->scanModel->getRecent(5);
        $totalScans = array_sum($statusCounts);
        $completedScans = $statusCounts['complete'] ?? 0;
        $importedScans = $statusCounts['imported'] ?? 0;

        $this->render('dashboard.index', [
            'pageTitle' => 'Dashboard - Claude Scraper',
            'totalScans' => $totalScans,
            'completedScans' => $completedScans,
            'importedScans' => $importedScans,
            'statusCounts' => $statusCounts,
            'recentScans' => $recentScans,
        ]);
    }
}
