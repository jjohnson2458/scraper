<?php

namespace App\Services;

use App\Models\ErrorLog;

/**
 * Import Service
 *
 * Handles importing scraped items into target platforms.
 * For buffaloeats: creates a demo store (or updates existing),
 * creates menu categories, and inserts menu items.
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
            'buffaloeats' => [
                'name' => 'Buffalo Eats',
                'description' => 'Restaurant ordering platform (demo store import)',
                'db_name' => 'buffaloeats',
                'path' => '/var/www/html/buffaloeats',
                'local_path' => 'C:/xampp/htdocs/claude_takeout',
            ],
            'claude_toolrental' => [
                'name' => 'Claude Tool Rental',
                'description' => 'Tool and equipment rental platform',
                'db_name' => 'toolrental',
                'path' => '/var/www/html/toolrental',
                'local_path' => 'C:/xampp/htdocs/claude_toolrental',
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
            $available[] = [
                'slug' => $key,
                'name' => $config['name'],
                'description' => $config['description'],
                'available' => true,
            ];
        }
        return $available;
    }

    /**
     * Get stores/businesses for a platform.
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
                return [['slug' => '_new_demo', 'name' => '+ Create New Demo Store']];
            }

            $stores = [];

            // Add "create new" option first
            $stores[] = ['slug' => '_new_demo', 'name' => '+ Create New Demo Store'];

            if ($platformSlug === 'buffaloeats') {
                // Show existing demo stores first, then all stores
                $stmt = $db->query(
                    "SELECT id, name, slug, site_status FROM businesses ORDER BY
                     CASE WHEN slug LIKE '%-demo' THEN 0 ELSE 1 END, name ASC LIMIT 50"
                );
                foreach ($stmt->fetchAll() as $store) {
                    $label = $store['name'];
                    if (str_ends_with($store['slug'], '-demo')) {
                        $label .= ' [DEMO]';
                    }
                    $label .= ' (' . $store['site_status'] . ')';
                    $stores[] = [
                        'slug' => $store['slug'],
                        'name' => $label,
                        'id' => $store['id'],
                    ];
                }
            }

            return $stores;
        } catch (\Exception $e) {
            $this->errorLog->log('Failed to get stores: ' . $e->getMessage(), 'warning');
            return [['slug' => '_new_demo', 'name' => '+ Create New Demo Store']];
        }
    }

    /**
     * Import items into a target platform.
     *
     * For buffaloeats: creates/updates a demo business, creates categories,
     * and inserts menu items with proper foreign keys.
     *
     * @param string $platformSlug The platform identifier.
     * @param string $storeSlug    The store slug (or '_new_demo' to create).
     * @param array  $items        Array of scan items to import.
     * @param array  $storeMeta    Optional store metadata (name, address, banner_url, etc.).
     * @return array{status: string, imported: int, failed: int, errors: array, store_slug: string|null}
     */
    public function importItems(string $platformSlug, string $storeSlug, array $items, array $storeMeta = []): array
    {
        if ($platformSlug === 'buffaloeats') {
            return $this->importToBuffaloEats($storeSlug, $items, $storeMeta);
        }

        // Generic fallback for other platforms
        return [
            'status' => 'failed',
            'imported' => 0,
            'failed' => count($items),
            'errors' => ["Import not yet implemented for: {$platformSlug}"],
            'store_slug' => null,
        ];
    }

    /**
     * Import scraped items into Buffalo Eats.
     *
     * Creates a demo store with "-demo" suffix, or updates an existing one.
     * On re-import to the same demo store, existing menu is wiped and replaced.
     *
     * @param string $storeSlug The target store slug or '_new_demo'.
     * @param array  $items     The scraped items.
     * @param array  $storeMeta Store metadata from the scan/form.
     * @return array
     */
    private function importToBuffaloEats(string $storeSlug, array $items, array $storeMeta): array
    {
        $imported = 0;
        $failed = 0;
        $errors = [];

        try {
            $db = $this->getPlatformDb('buffaloeats');
            if (!$db) {
                return [
                    'status' => 'failed', 'imported' => 0, 'failed' => count($items),
                    'errors' => ['Could not connect to buffaloeats database'],
                    'store_slug' => null,
                ];
            }

            $storeName = $storeMeta['store_name'] ?? 'Untitled Restaurant';

            // Determine demo slug
            if ($storeSlug === '_new_demo') {
                $demoSlug = $this->generateDemoSlug($storeName);

                // Check if this demo already exists
                $existing = $db->prepare("SELECT id FROM businesses WHERE slug = :slug");
                $existing->execute(['slug' => $demoSlug]);
                $row = $existing->fetch();

                if ($row) {
                    // Demo exists — update it
                    $businessId = (int) $row['id'];
                    $this->updateDemoBusiness($db, $businessId, $storeMeta);
                    $this->clearBusinessMenu($db, $businessId);
                    $storeSlug = $demoSlug;
                } else {
                    // Create new demo business
                    $businessId = $this->createDemoBusiness($db, $demoSlug, $storeMeta);
                    $storeSlug = $demoSlug;
                }
            } else {
                // Importing into an existing store
                $existing = $db->prepare("SELECT id FROM businesses WHERE slug = :slug");
                $existing->execute(['slug' => $storeSlug]);
                $row = $existing->fetch();

                if (!$row) {
                    return [
                        'status' => 'failed', 'imported' => 0, 'failed' => count($items),
                        'errors' => ["Store not found: {$storeSlug}"],
                        'store_slug' => $storeSlug,
                    ];
                }

                $businessId = (int) $row['id'];

                // If it's a demo store, wipe and replace
                if (str_ends_with($storeSlug, '-demo')) {
                    $this->updateDemoBusiness($db, $businessId, $storeMeta);
                    $this->clearBusinessMenu($db, $businessId);
                }
            }

            // Download and set banner image if provided
            if (!empty($storeMeta['banner_url'])) {
                $bannerPath = $this->downloadBanner($storeMeta['banner_url'], $businessId);
                if ($bannerPath) {
                    $db->prepare("UPDATE businesses SET banner_path = :path WHERE id = :id")
                       ->execute(['path' => $bannerPath, 'id' => $businessId]);
                }
            }

            // Pre-process items: $0.00 items are category headers, not real items.
            // Walk through and assign them as categories for items that follow.
            $processedItems = $this->preprocessItems($items);

            // Create categories from the processed data
            $categoryMap = $this->createCategories($db, $businessId, $processedItems);

            // Insert menu items (only real items with price > 0)
            $sortOrder = 0;
            foreach ($processedItems as $item) {
                try {
                    $categoryId = null;
                    $catName = $item['category'] ?? null;
                    if ($catName && isset($categoryMap[$catName])) {
                        $categoryId = $categoryMap[$catName];
                    }

                    $itemName = $item['name'] ?? 'Untitled Item';
                    $itemSlug = $this->slugify($itemName) . '-' . (++$sortOrder);

                    $stmt = $db->prepare(
                        "INSERT INTO menu_items
                         (business_id, category_id, name, slug, description, price, sort_order, is_available, in_store)
                         VALUES (:business_id, :category_id, :name, :slug, :description, :price, :sort_order, 1, 1)"
                    );
                    $stmt->execute([
                        'business_id' => $businessId,
                        'category_id' => $categoryId,
                        'name' => $itemName,
                        'slug' => $itemSlug,
                        'description' => $item['description'] ?? null,
                        'price' => $item['price'],
                        'sort_order' => $sortOrder,
                    ]);
                    $imported++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'item' => $item['name'] ?? 'Unknown',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $status = $failed === 0 ? 'complete' : ($imported === 0 ? 'failed' : 'partial');

            return [
                'status' => $status,
                'imported' => $imported,
                'failed' => $failed,
                'errors' => $errors,
                'store_slug' => $storeSlug,
                'business_id' => $businessId,
            ];
        } catch (\Exception $e) {
            $this->errorLog->log('Buffalo Eats import failed: ' . $e->getMessage(), 'error');
            return [
                'status' => 'failed', 'imported' => $imported, 'failed' => count($items) - $imported,
                'errors' => [$e->getMessage()], 'store_slug' => $storeSlug,
            ];
        }
    }

    /**
     * Generate a demo slug from a store name.
     *
     * @param string $name The store name.
     * @return string The slug with -demo suffix.
     */
    private function generateDemoSlug(string $name): string
    {
        return $this->slugify($name) . '-demo';
    }

    /**
     * Create a new demo business in buffaloeats.
     *
     * @param \PDO  $db       The database connection.
     * @param string $slug    The demo slug.
     * @param array  $meta    Store metadata.
     * @return int The new business ID.
     */
    private function createDemoBusiness(\PDO $db, string $slug, array $meta): int
    {
        $stmt = $db->prepare(
            "INSERT INTO businesses
             (name, slug, business_type, description, tagline,
              address_street, address_city, address_state, address_zip,
              phone, email, website_url, banner_path,
              site_status, is_active, timezone, tax_rate, currency,
              subscription_tier, subscription_status)
             VALUES
             (:name, :slug, 'restaurant', :description, :tagline,
              :address_street, :address_city, :address_state, :address_zip,
              :phone, :email, :website_url, :banner_path,
              'live', 1, 'America/New_York', 0.0800, 'USD',
              'free', 'active')"
        );

        $storeName = ($meta['store_name'] ?? 'Demo Restaurant') . ' (Demo)';

        $stmt->execute([
            'name' => $storeName,
            'slug' => $slug,
            'description' => $meta['description'] ?? 'Demo store created by Claude Scraper',
            'tagline' => $meta['tagline'] ?? null,
            'address_street' => $meta['address_street'] ?? null,
            'address_city' => $meta['address_city'] ?? null,
            'address_state' => $meta['address_state'] ?? null,
            'address_zip' => $meta['address_zip'] ?? null,
            'phone' => $meta['phone'] ?? null,
            'email' => $meta['email'] ?? null,
            'website_url' => $meta['website_url'] ?? null,
            'banner_path' => null,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Update an existing demo business with new metadata.
     *
     * @param \PDO  $db         The database connection.
     * @param int   $businessId The business ID.
     * @param array $meta       Store metadata.
     * @return void
     */
    private function updateDemoBusiness(\PDO $db, int $businessId, array $meta): void
    {
        $fields = [];
        $params = ['id' => $businessId];

        $mapping = [
            'store_name' => 'name',
            'description' => 'description',
            'tagline' => 'tagline',
            'address_street' => 'address_street',
            'address_city' => 'address_city',
            'address_state' => 'address_state',
            'address_zip' => 'address_zip',
            'phone' => 'phone',
            'email' => 'email',
            'website_url' => 'website_url',
        ];

        foreach ($mapping as $metaKey => $dbField) {
            if (!empty($meta[$metaKey])) {
                $value = $meta[$metaKey];
                if ($metaKey === 'store_name') {
                    $value .= str_ends_with($value, '(Demo)') ? '' : ' (Demo)';
                }
                $fields[] = "{$dbField} = :{$dbField}";
                $params[$dbField] = $value;
            }
        }

        if (!empty($fields)) {
            $sql = "UPDATE businesses SET " . implode(', ', $fields) . " WHERE id = :id";
            $db->prepare($sql)->execute($params);
        }
    }

    /**
     * Clear all menu items and categories for a business (for re-import).
     *
     * @param \PDO $db         The database connection.
     * @param int  $businessId The business ID.
     * @return void
     */
    private function clearBusinessMenu(\PDO $db, int $businessId): void
    {
        $db->prepare("DELETE FROM menu_items WHERE business_id = :id")->execute(['id' => $businessId]);
        $db->prepare("DELETE FROM menu_categories WHERE business_id = :id")->execute(['id' => $businessId]);
    }

    /**
     * Pre-process scraped items: items with $0.00 or null price are category headers.
     * They become the category for all items that follow until the next $0.00 item.
     *
     * @param array $items Raw scraped items.
     * @return array Cleaned items with proper categories (no $0.00 items).
     */
    private function preprocessItems(array $items): array
    {
        $processed = [];
        $currentCategory = null;

        foreach ($items as $item) {
            $price = isset($item['price']) ? (float) $item['price'] : 0.0;

            if ($price <= 0.0) {
                // This is a category header, not a real item
                $currentCategory = trim($item['name'] ?? '');
                continue;
            }

            // Assign the current running category if item doesn't already have one
            if (empty($item['category']) && $currentCategory) {
                $item['category'] = $currentCategory;
            }

            $processed[] = $item;
        }

        return $processed;
    }

    /**
     * Create menu categories from item data, returning a name => id map.
     *
     * @param \PDO  $db         The database connection.
     * @param int   $businessId The business ID.
     * @param array $items      The scraped items.
     * @return array<string, int> Category name => category ID.
     */
    private function createCategories(\PDO $db, int $businessId, array $items): array
    {
        $categories = [];
        $sortOrder = 0;

        foreach ($items as $item) {
            $catName = $item['category'] ?? null;
            if ($catName && !isset($categories[$catName])) {
                $catSlug = $this->slugify($catName);
                $stmt = $db->prepare(
                    "INSERT INTO menu_categories (business_id, name, slug, sort_order, is_active)
                     VALUES (:business_id, :name, :slug, :sort_order, 1)"
                );
                $stmt->execute([
                    'business_id' => $businessId,
                    'name' => $catName,
                    'slug' => $catSlug,
                    'sort_order' => ++$sortOrder,
                ]);
                $categories[$catName] = (int) $db->lastInsertId();
            }
        }

        // If no categories were found, create a default
        if (empty($categories)) {
            $stmt = $db->prepare(
                "INSERT INTO menu_categories (business_id, name, slug, sort_order, is_active)
                 VALUES (:business_id, 'Menu', 'menu', 1, 1)"
            );
            $stmt->execute(['business_id' => $businessId]);
            $categories['_default'] = (int) $db->lastInsertId();
        }

        return $categories;
    }

    /**
     * Download a banner image and save it for the business.
     *
     * @param string $url        The banner image URL.
     * @param int    $businessId The business ID.
     * @return string|null The saved path or null on failure.
     */
    private function downloadBanner(string $url, int $businessId): ?string
    {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 15, 'verify' => false]);
            $response = $client->get($url);
            $contentType = $response->getHeaderLine('Content-Type');

            $extensions = [
                'image/jpeg' => 'jpg', 'image/png' => 'png',
                'image/gif' => 'gif', 'image/webp' => 'webp',
            ];
            $ext = $extensions[$contentType] ?? 'jpg';
            $filename = "banner_demo_{$businessId}.{$ext}";

            // Save to buffaloeats uploads directory
            $dirs = [
                '/var/www/html/buffaloeats/public/uploads/banners',
                'C:/xampp/htdocs/claude_takeout/public/uploads/banners',
            ];

            foreach ($dirs as $dir) {
                if (is_dir(dirname($dir))) {
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    file_put_contents($dir . '/' . $filename, $response->getBody());
                    return '/uploads/banners/' . $filename;
                }
            }

            return null;
        } catch (\Exception $e) {
            $this->errorLog->log('Banner download failed: ' . $e->getMessage(), 'warning', ['url' => $url]);
            return null;
        }
    }

    /**
     * Convert a string to a URL-safe slug.
     *
     * @param string $text The text to slugify.
     * @return string
     */
    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
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
