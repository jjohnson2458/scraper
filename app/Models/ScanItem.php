<?php

namespace App\Models;

use App\Core\BaseModel;

/**
 * ScanItem Model
 *
 * Represents an individual item extracted from a scan.
 *
 * @package    ClaudeScraper
 * @subpackage Models
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class ScanItem extends BaseModel
{
    /** @var string The database table */
    protected string $table = 'scan_items';

    /** @var array<string> Mass-assignable columns */
    protected array $fillable = [
        'scan_id', 'name', 'description', 'price', 'category',
        'image_url', 'image_path', 'sort_order', 'is_selected', 'raw_text'
    ];

    /**
     * Get all items for a scan.
     *
     * @param int $scanId The scan ID.
     * @return array
     */
    public function getByScan(int $scanId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE scan_id = :scan_id ORDER BY sort_order ASC"
        );
        $stmt->execute(['scan_id' => $scanId]);
        return $stmt->fetchAll();
    }

    /**
     * Get selected items for a scan (for import).
     *
     * @param int $scanId The scan ID.
     * @return array
     */
    public function getSelectedByScan(int $scanId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE scan_id = :scan_id AND is_selected = 1 ORDER BY sort_order ASC"
        );
        $stmt->execute(['scan_id' => $scanId]);
        return $stmt->fetchAll();
    }

    /**
     * Bulk insert items for a scan.
     *
     * @param int   $scanId The scan ID.
     * @param array $items  Array of item data arrays.
     * @return int Number of items inserted.
     */
    public function bulkInsert(int $scanId, array $items): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (scan_id, name, description, price, category, image_url, sort_order, raw_text)
             VALUES (:scan_id, :name, :description, :price, :category, :image_url, :sort_order, :raw_text)"
        );

        $count = 0;
        foreach ($items as $i => $item) {
            $stmt->execute([
                'scan_id' => $scanId,
                'name' => $item['name'] ?? 'Untitled Item',
                'description' => $item['description'] ?? null,
                'price' => $item['price'] ?? null,
                'category' => $item['category'] ?? null,
                'image_url' => $item['image_url'] ?? null,
                'sort_order' => $item['sort_order'] ?? $i + 1,
                'raw_text' => $item['raw_text'] ?? null,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Delete all items for a scan.
     *
     * @param int $scanId The scan ID.
     * @return bool
     */
    public function deleteByScan(int $scanId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE scan_id = :scan_id");
        return $stmt->execute(['scan_id' => $scanId]);
    }

    /**
     * Get distinct categories for a scan.
     *
     * @param int $scanId The scan ID.
     * @return array
     */
    public function getCategories(int $scanId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT category FROM {$this->table} WHERE scan_id = :scan_id AND category IS NOT NULL ORDER BY category"
        );
        $stmt->execute(['scan_id' => $scanId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
