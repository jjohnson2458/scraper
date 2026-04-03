<?php

namespace App\Services;

use App\Models\ErrorLog;

/**
 * Import Service
 *
 * Handles importing scraped items into target platforms
 * (claude_takeout, claude_toolrental, etc.) by directly
 * inserting into their databases.
 *
 * @package    ClaudeScraper
 * @subpackage Services
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class ImportService
{
    /** @var ErrorLog */
    private ErrorLog $errorLog;

    /** @var array Platform configuration */
    private array $platforms = [];

    /**
     * ImportService constructor.
     */
    public function __construct()
    {
        $this->errorLog = new ErrorLog();
        $this->loadPlatformConfig();
    }

    /**
     * Load available platform configurations.
     *
     * @return void
     */
    private function loadPlatformConfig(): void
    {
        $this->platforms = [
            'claude_takeout' => [
                'name' => 'Claude Takeout',
                'description' => 'Restaurant ordering platform',
                'db_name' => 'takeout',
                'item_table' => 'menu_items',
                'path' => 'C:/xampp/htdocs/claude_takeout',
                'field_map' => [
                    'name' => 'name',
                    'description' => 'description',
                    'price' => 'price',
                    'category' => 'category_name',
                    'image_path' => 'image',
                ],
            ],
            'claude_toolrental' => [
                'name' => 'Claude Tool Rental',
                'description' => 'Tool and equipment rental platform',
                'db_name' => 'toolrental',
                'item_table' => 'inventory_items',
                'path' => 'C:/xampp/htdocs/claude_toolrental',
                'field_map' => [
                    'name' => 'name',
                    'description' => 'description',
                    'price' => 'daily_rate',
                    'category' => 'category',
                    'image_path' => 'image_url',
                ],
            ],
        ];
    }

    /**
     * Get available platforms for import.
     *
     * @return array
     */
    public function getAvailablePlatforms(): array
    {
        $available = [];
        foreach ($this->platforms as $key => $config) {
            // Check if platform directory exists
            $exists = is_dir($config['path']);
            $available[] = [
                'slug' => $key,
                'name' => $config['name'],
                'description' => $config['description'],
                'available' => $exists,
            ];
        }
        return $available;
    }

    /**
     * Get stores/instances for a platform.
     *
     * @param string $platformSlug The platform identifier.
     * @return array
     */
    public function getStores(string $platformSlug): array
    {
        if (!isset($this->platforms[$platformSlug])) {
            return [];
        }

        $config = $this->platforms[$platformSlug];

        try {
            $db = $this->getPlatformDb($config['db_name']);
            if (!$db) {
                return [['slug' => 'default', 'name' => 'Default Store']];
            }

            // Try to find stores/tenants table
            $storeTables = ['stores', 'restaurants', 'tenants', 'businesses'];
            foreach ($storeTables as $table) {
                try {
                    $stmt = $db->query("SELECT * FROM {$table} ORDER BY name ASC LIMIT 50");
                    $stores = $stmt->fetchAll();
                    if (!empty($stores)) {
                        return array_map(function ($store) {
                            return [
                                'slug' => $store['slug'] ?? $store['id'],
                                'name' => $store['name'] ?? 'Store #' . $store['id'],
                            ];
                        }, $stores);
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        } catch (\Exception $e) {
            $this->errorLog->log('Failed to get stores: ' . $e->getMessage(), 'warning');
        }

        return [['slug' => 'default', 'name' => 'Default Store']];
    }

    /**
     * Import items into a target platform.
     *
     * @param string $platformSlug The platform identifier.
     * @param string $storeSlug    The store identifier.
     * @param array  $items        Array of scan items to import.
     * @return array{status: string, imported: int, failed: int, errors: array}
     */
    public function importItems(string $platformSlug, string $storeSlug, array $items): array
    {
        if (!isset($this->platforms[$platformSlug])) {
            return [
                'status' => 'failed',
                'imported' => 0,
                'failed' => count($items),
                'errors' => ['Platform not found: ' . $platformSlug],
            ];
        }

        $config = $this->platforms[$platformSlug];
        $fieldMap = $config['field_map'];
        $imported = 0;
        $failed = 0;
        $errors = [];

        try {
            $db = $this->getPlatformDb($config['db_name']);
            if (!$db) {
                return [
                    'status' => 'failed',
                    'imported' => 0,
                    'failed' => count($items),
                    'errors' => ['Could not connect to platform database: ' . $config['db_name']],
                ];
            }

            foreach ($items as $item) {
                try {
                    $data = [];
                    foreach ($fieldMap as $srcField => $destField) {
                        $data[$destField] = $item[$srcField] ?? null;
                    }

                    // Add store reference if applicable
                    if ($storeSlug !== 'default') {
                        $data['store_slug'] = $storeSlug;
                    }

                    $columns = implode(', ', array_keys($data));
                    $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));

                    $stmt = $db->prepare(
                        "INSERT INTO {$config['item_table']} ({$columns}) VALUES ({$placeholders})"
                    );
                    $stmt->execute($data);
                    $imported++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'item' => $item['name'] ?? 'Unknown',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->errorLog->log('Import failed: ' . $e->getMessage(), 'error');
            return [
                'status' => 'failed',
                'imported' => $imported,
                'failed' => count($items) - $imported,
                'errors' => [$e->getMessage()],
            ];
        }

        $status = $failed === 0 ? 'complete' : ($imported === 0 ? 'failed' : 'partial');

        return [
            'status' => $status,
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Get a PDO connection to a platform's database.
     *
     * @param string $dbName The database name.
     * @return \PDO|null
     */
    private function getPlatformDb(string $dbName): ?\PDO
    {
        try {
            $config = require __DIR__ . '/../../config/app.php';
            $db = $config['db'];

            return new \PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $db['host'], $db['port'], $dbName, $db['charset']),
                $db['username'],
                $db['password'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );
        } catch (\Exception $e) {
            $this->errorLog->log("Cannot connect to {$dbName}: " . $e->getMessage(), 'error');
            return null;
        }
    }
}
