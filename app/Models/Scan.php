<?php

namespace App\Models;

use App\Core\BaseModel;

/**
 * Scan Model
 *
 * Represents a scraping scan (URL or photo-based).
 *
 * @package    ClaudeScraper
 * @subpackage Models
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class Scan extends BaseModel
{
    /** @var string The database table */
    protected string $table = 'scans';

    /** @var array<string> Mass-assignable columns */
    protected array $fillable = [
        'user_id', 'source_type', 'source_value', 'title',
        'status', 'item_count', 'error_message'
    ];

    /**
     * Get scans with pagination and optional filters.
     *
     * @param int         $page       Current page.
     * @param int         $perPage    Records per page.
     * @param string|null $status     Filter by status.
     * @param string|null $sourceType Filter by source type.
     * @param string|null $search     Search keyword.
     * @return array Paginated results.
     */
    public function getFiltered(
        int $page = 1,
        int $perPage = 20,
        ?string $status = null,
        ?string $sourceType = null,
        ?string $search = null
    ): array {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $conditions = [];
        $params = [];

        if ($status) {
            $conditions[] = "status = :status";
            $params['status'] = $status;
        }
        if ($sourceType) {
            $conditions[] = "source_type = :source_type";
            $params['source_type'] = $sourceType;
        }
        if ($search) {
            $conditions[] = "(title LIKE :search OR source_value LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
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
     * Get counts grouped by status.
     *
     * @return array Associative array of status => count.
     */
    public function getStatusCounts(): array
    {
        $stmt = $this->db->query(
            "SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status"
        );
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }
        return $counts;
    }

    /**
     * Get recent scans.
     *
     * @param int $limit Number of records.
     * @return array
     */
    public function getRecent(int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
