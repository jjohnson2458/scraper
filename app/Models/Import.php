<?php

namespace App\Models;

use App\Core\BaseModel;

/**
 * Import Model
 *
 * Represents an import operation from a scan to a target platform.
 *
 * @package    ClaudeScraper
 * @subpackage Models
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class Import extends BaseModel
{
    /** @var string The database table */
    protected string $table = 'imports';

    /** @var array<string> Mass-assignable columns */
    protected array $fillable = [
        'scan_id', 'user_id', 'target_platform', 'target_store_slug',
        'status', 'total_items', 'imported_items', 'failed_items', 'error_log'
    ];

    /**
     * Get imports with scan info.
     *
     * @param int $page    Current page.
     * @param int $perPage Records per page.
     * @return array Paginated results with scan data joined.
     */
    public function getWithScans(int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->db->query("SELECT COUNT(*) FROM {$this->table}");
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT i.*, s.title as scan_title, s.source_type
             FROM {$this->table} i
             LEFT JOIN scans s ON i.scan_id = s.id
             ORDER BY i.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Get imports for a specific scan.
     *
     * @param int $scanId The scan ID.
     * @return array
     */
    public function getByScan(int $scanId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE scan_id = :scan_id ORDER BY created_at DESC"
        );
        $stmt->execute(['scan_id' => $scanId]);
        return $stmt->fetchAll();
    }
}
