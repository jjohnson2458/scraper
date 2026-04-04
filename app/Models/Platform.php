<?php

namespace App\Models;

use App\Core\BaseModel;

/**
 * Platform Model
 *
 * Represents a scraping platform (Toast, DoorDash, etc.)
 * in the platform registry.
 *
 * @package    ClaudeScraper
 * @subpackage Models
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class Platform extends BaseModel
{
    /** @var string The database table */
    protected string $table = 'platforms';

    /** @var array<string> Mass-assignable columns */
    protected array $fillable = [
        'name', 'slug', 'category', 'tier', 'scrape_method',
        'url_pattern', 'engine_class', 'is_active',
        'health_status', 'last_health_check', 'notes'
    ];

    /**
     * Find a platform by slug.
     *
     * @param string $slug The platform slug.
     * @return array|null
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE slug = :slug LIMIT 1");
        $stmt->execute(['slug' => $slug]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all active platforms.
     *
     * @return array
     */
    public function getActive(): array
    {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY tier = 'high' DESC, name ASC"
        )->fetchAll();
    }

    /**
     * Get all platforms grouped by category for the UI dropdown.
     *
     * @return array
     */
    public function getAllGrouped(): array
    {
        $categoryLabels = [
            'ordering_pos' => 'Online Ordering & POS',
            'delivery_marketplace' => 'Delivery & Marketplace',
            'website_builder' => 'Website Builders & Menu Hosts',
            'data_aggregator' => 'Data Aggregators',
        ];

        $stmt = $this->db->query(
            "SELECT * FROM {$this->table} ORDER BY
             FIELD(category, 'ordering_pos', 'delivery_marketplace', 'website_builder', 'data_aggregator'),
             FIELD(tier, 'high', 'medium', 'low'),
             name ASC"
        );

        $platforms = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['group_label'] = $categoryLabels[$row['category']] ?? $row['category'];
            $platforms[] = $row;
        }

        return $platforms;
    }

    /**
     * Update a platform's health status.
     *
     * @param int    $id     The platform ID.
     * @param string $status The health status (green, yellow, red).
     * @return void
     */
    public function updateHealth(int $id, string $status): void
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET health_status = :status, last_health_check = NOW() WHERE id = :id"
        );
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /**
     * Get platform health summary.
     *
     * @return array
     */
    public function getHealthSummary(): array
    {
        $stmt = $this->db->query(
            "SELECT health_status, COUNT(*) as count FROM {$this->table} WHERE is_active = 1 GROUP BY health_status"
        );
        $summary = [];
        foreach ($stmt->fetchAll() as $row) {
            $summary[$row['health_status']] = (int) $row['count'];
        }
        return $summary;
    }
}
