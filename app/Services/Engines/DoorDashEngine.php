<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * DoorDash Engine
 *
 * Scrapes restaurant menus from DoorDash (doordash.com).
 * DoorDash embeds menu data as JSON in the page source via
 * __NEXT_DATA__ or similar hydration scripts.
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class DoorDashEngine extends AbstractEngine
{
    /**
     * {@inheritdoc}
     */
    public function getPlatformSlug(): string
    {
        return 'doordash';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandle(string $url): bool
    {
        return (bool) preg_match('/doordash\.com/i', $url);
    }

    /**
     * {@inheritdoc}
     */
    protected function doScrape(string $url): array
    {
        // DoorDash is JS-heavy — use Selenium
        $crawler = $this->fetchWithSelenium($url, 6);
        $html = $crawler->html();

        // Try to extract JSON data from __NEXT_DATA__ or inline scripts
        $items = $this->extractFromNextData($html);

        if (empty($items)) {
            $items = $this->extractFromDom($crawler);
        }

        $restaurant = $this->extractRestaurantInfo($crawler);

        return $this->success($items, $restaurant);
    }

    /**
     * Extract menu data from __NEXT_DATA__ script tag.
     *
     * @param string $html The page HTML.
     * @return array
     */
    private function extractFromNextData(string $html): array
    {
        $items = [];

        // DoorDash puts menu data in __NEXT_DATA__ or redux state
        if (preg_match('/<script[^>]*id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $matches)) {
            $data = json_decode($matches[1], true);
            if ($data) {
                $this->walkDoorDashData($data, $items);
            }
        }

        // Also try to find JSON in other script tags
        if (empty($items) && preg_match_all('/"menuCategories"\s*:\s*(\[.*?\])\s*[,}]/s', $html, $matches)) {
            foreach ($matches[1] as $jsonStr) {
                $categories = json_decode($jsonStr, true);
                if (is_array($categories)) {
                    foreach ($categories as $cat) {
                        $catName = $cat['name'] ?? $cat['title'] ?? null;
                        foreach ($cat['items'] ?? $cat['menuItems'] ?? [] as $mi) {
                            $items[] = $this->parseDoorDashItem($mi, $catName);
                        }
                    }
                }
            }
        }

        return array_filter($items);
    }

    /**
     * Recursively walk DoorDash's nested data for menu items.
     *
     * @param array $data   The data to walk.
     * @param array &$items Items collection.
     * @return void
     */
    private function walkDoorDashData(array $data, array &$items): void
    {
        // Look for menu structures at any depth
        if (isset($data['menuCategories']) || isset($data['categories'])) {
            $categories = $data['menuCategories'] ?? $data['categories'] ?? [];
            foreach ($categories as $cat) {
                $catName = $cat['name'] ?? $cat['title'] ?? null;
                foreach ($cat['items'] ?? $cat['menuItems'] ?? [] as $mi) {
                    $item = $this->parseDoorDashItem($mi, $catName);
                    if ($item) $items[] = $item;
                }
            }
            return;
        }

        // Recurse into nested arrays/objects
        foreach ($data as $value) {
            if (is_array($value) && !empty($value)) {
                $this->walkDoorDashData($value, $items);
                if (!empty($items)) return; // Stop once we find items
            }
        }
    }

    /**
     * Parse a single DoorDash menu item from JSON.
     *
     * @param array       $mi      The item data.
     * @param string|null $category The category name.
     * @return array|null
     */
    private function parseDoorDashItem(array $mi, ?string $category): ?array
    {
        $name = $mi['name'] ?? $mi['title'] ?? null;
        if (!$name) return null;

        $price = null;
        if (isset($mi['price'])) {
            $price = is_numeric($mi['price']) ? (float) $mi['price'] / 100 : (float) $mi['price'];
        } elseif (isset($mi['displayPrice'])) {
            $price = $this->extractPrice($mi['displayPrice']);
        }

        $modifiers = [];
        foreach ($mi['extras'] ?? $mi['optionGroups'] ?? $mi['modifierGroups'] ?? [] as $group) {
            $groupName = $group['name'] ?? $group['title'] ?? 'Options';
            foreach ($group['items'] ?? $group['options'] ?? [] as $opt) {
                $adjPrice = 0;
                if (isset($opt['price'])) {
                    $adjPrice = is_numeric($opt['price']) ? (float) $opt['price'] / 100 : (float) $opt['price'];
                }
                $modifiers[] = [
                    'group_name' => $groupName,
                    'option_name' => $opt['name'] ?? $opt['title'] ?? '',
                    'price_adjustment' => $adjPrice,
                    'is_default' => (bool) ($opt['isDefault'] ?? false),
                    'is_required' => (bool) ($group['isRequired'] ?? $group['minNumOptions'] ?? false),
                ];
            }
        }

        return $this->makeItem(
            $name,
            $price,
            $mi['description'] ?? null,
            $category,
            $mi['imageUrl'] ?? $mi['image'] ?? $mi['coverPhoto'] ?? null,
            [
                'external_id' => $mi['id'] ?? $mi['menuItemId'] ?? null,
                'calories' => $mi['calories'] ?? $mi['nutritionInfo']['calories'] ?? null,
                'modifiers' => !empty($modifiers) ? $modifiers : null,
            ]
        );
    }

    /**
     * Extract items from DOM as fallback.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array
     */
    private function extractFromDom(Crawler $crawler): array
    {
        $items = [];
        $selectors = ['[data-testid*="MenuItem"]', '[class*="MenuItemCard"]', '[class*="menu-item"]'];

        foreach ($selectors as $selector) {
            try {
                $crawler->filter($selector)->each(function (Crawler $node) use (&$items) {
                    $name = null;
                    $price = null;

                    foreach (['h3', 'h4', 'span[class*="name"]', 'span[class*="Name"]'] as $ns) {
                        try {
                            $n = $node->filter($ns)->first();
                            if ($n->count()) { $name = trim($n->text()); break; }
                        } catch (\Exception $e) { continue; }
                    }

                    $price = $this->extractPrice($node->text());

                    if ($name && strlen($name) >= 2) {
                        $items[] = $this->makeItem($name, $price);
                    }
                });
                if (!empty($items)) break;
            } catch (\Exception $e) { continue; }
        }

        return $items;
    }

    /**
     * Extract restaurant metadata.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array
     */
    private function extractRestaurantInfo(Crawler $crawler): array
    {
        $info = ['name' => null, 'address' => null, 'phone' => null, 'logo_url' => null, 'banner_url' => null];

        try {
            $h1 = $crawler->filter('h1')->first();
            if ($h1->count()) $info['name'] = trim($h1->text());
        } catch (\Exception $e) {}

        try {
            $og = $crawler->filter('meta[property="og:image"]')->first();
            if ($og->count()) $info['banner_url'] = $og->attr('content');
        } catch (\Exception $e) {}

        return $info;
    }

    /**
     * {@inheritdoc}
     */
    public function healthCheck(): array
    {
        try {
            $this->http->head('https://www.doordash.com', ['timeout' => 10]);
            return ['healthy' => true, 'message' => 'DoorDash reachable'];
        } catch (\Exception $e) {
            return ['healthy' => false, 'message' => $e->getMessage()];
        }
    }
}
