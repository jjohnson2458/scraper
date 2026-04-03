<?php

/**
 * Database Seeder
 *
 * Seeds the database with initial admin user and sample data.
 * Run: php database/seeds/seed.php
 *
 * @package    ClaudeScraper
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getConnection();

// Seed admin user
$email = 'email4johnson@gmail.com';
$existing = $db->prepare("SELECT id FROM users WHERE email = :email");
$existing->execute(['email' => $email]);

if (!$existing->fetch()) {
    $stmt = $db->prepare(
        "INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)"
    );
    $stmt->execute([
        'username' => 'admin',
        'email' => $email,
        'password' => password_hash('24AdaPlace', PASSWORD_BCRYPT),
        'role' => 'admin',
    ]);
    echo "Admin user created: {$email}\n";
} else {
    echo "Admin user already exists.\n";
}

// Seed sample scan data for development
if (env('APP_ENV') === 'local') {
    $existingScans = $db->query("SELECT COUNT(*) FROM scans")->fetchColumn();
    if ($existingScans == 0) {
        $userId = $db->query("SELECT id FROM users WHERE email = '{$email}'")->fetchColumn();

        $db->prepare("INSERT INTO scans (user_id, source_type, source_value, title, status, item_count) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$userId, 'url', 'https://example-restaurant.com/menu', 'Example Restaurant', 'complete', 5]);

        $scanId = $db->lastInsertId();

        $items = [
            ['Classic Burger', 'Angus beef patty with lettuce, tomato, and special sauce', 12.99, 'Burgers'],
            ['Caesar Salad', 'Romaine lettuce, croutons, parmesan, caesar dressing', 9.99, 'Salads'],
            ['Margherita Pizza', 'Fresh mozzarella, basil, tomato sauce on thin crust', 14.99, 'Pizza'],
            ['Fish & Chips', 'Beer-battered cod with hand-cut fries and tartar sauce', 16.99, 'Entrees'],
            ['Chocolate Cake', 'Triple layer chocolate cake with ganache frosting', 8.99, 'Desserts'],
        ];

        $stmt = $db->prepare(
            "INSERT INTO scan_items (scan_id, name, description, price, category, sort_order) VALUES (?, ?, ?, ?, ?, ?)"
        );

        foreach ($items as $i => $item) {
            $stmt->execute([$scanId, $item[0], $item[1], $item[2], $item[3], $i + 1]);
        }

        echo "Sample scan data seeded ({$scanId}).\n";
    }
}

echo "Seeding complete.\n";
