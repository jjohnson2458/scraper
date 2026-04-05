<?php
/**
 * Overnight import script — imports scans 18, 19, 20 to Buffalo Eats demo stores.
 * Run: sudo -u www-data php scripts/overnight_import.php
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/database.php';

$importService = new App\Services\ImportService();
$scanItemModel = new App\Models\ScanItem();
$importModel = new App\Models\Import();
$scanModel = new App\Models\Scan();

$scans = [
    18 => ['store_name' => 'Francos Pizza', 'address_city' => 'Tonawanda', 'address_state' => 'NY'],
    19 => ['store_name' => 'Duffs Famous Wings', 'address_city' => 'Buffalo', 'address_state' => 'NY'],
    20 => ['store_name' => 'La Nova Pizzeria', 'address_city' => 'Buffalo', 'address_state' => 'NY'],
];

foreach ($scans as $scanId => $meta) {
    echo "\n=== Importing scan #{$scanId} ({$meta['store_name']}) ===\n";

    $scan = $scanModel->find($scanId);
    if (!$scan) { echo "Scan not found\n"; continue; }

    $items = $scanItemModel->getSelectedByScan($scanId);
    echo "Items to import: " . count($items) . "\n";

    $storeMeta = array_merge($meta, [
        'website_url' => $scan['source_value'],
        'banner_url' => $scan['banner_url'] ?? null,
        'logo_url' => $scan['logo_url'] ?? null,
    ]);

    $result = $importService->importItems('buffaloeats', '_new_demo', $items, $storeMeta);

    echo "Status: {$result['status']}\n";
    echo "Imported: {$result['imported']} / Failed: {$result['failed']}\n";
    echo "Store slug: " . ($result['store_slug'] ?? '?') . "\n";

    if (!empty($result['store_slug'])) {
        echo "URL: https://{$result['store_slug']}.buffaloeatsonline.com\n";
    }

    $importId = $importModel->create([
        'scan_id' => $scanId,
        'user_id' => 1,
        'target_platform' => 'buffaloeats',
        'target_store_slug' => $result['store_slug'] ?? null,
        'status' => $result['status'],
        'total_items' => count($items),
        'imported_items' => $result['imported'],
        'failed_items' => $result['failed'],
        'error_log' => !empty($result['errors']) ? json_encode($result['errors']) : null,
    ]);

    if ($result['status'] === 'complete') {
        $scanModel->update($scanId, ['status' => 'imported']);
    }

    echo "Import #{$importId} created\n";

    if (!empty($result['errors'])) {
        echo "First errors:\n";
        foreach (array_slice($result['errors'], 0, 3) as $err) {
            echo '  ' . (is_array($err) ? $err['item'] . ': ' . $err['error'] : $err) . "\n";
        }
    }
}

echo "\nDone.\n";
