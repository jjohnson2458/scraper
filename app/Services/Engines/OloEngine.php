<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Olo Engine
 *
 * Scrapes restaurant menus from Olo-powered ordering sites.
 * Olo powers chains like Sweetgreen, Shake Shack, etc.
 * Uses API endpoints for menu data.
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class OloEngine extends AbstractEngine
{
    public function getPlatformSlug(): string { return 'olo'; }

    public function canHandle(string $url): bool
    {
        return (bool) preg_match('/olo\.com|ordering\.app/i', $url);
    }

    protected function doScrape(string $url): array
    {
        $crawler = $this->fetchWithSelenium($url, 6);
        $html = $crawler->html();

        $items = $this->extractFromJson($html);
        if (empty($items)) {
            $items = $this->extractFromDom($crawler);
        }

        $restaurant = $this->extractRestaurantInfo($crawler);
        return $this->success($items, $restaurant);
    }

    private function extractFromJson(string $html): array
    {
        $items = [];

        // Olo embeds menu data in various JSON structures
        $patterns = [
            '/"categories"\s*:\s*(\[.*?\])\s*,"(?:vendor|restaurant)/s',
            '/"menuCategories"\s*:\s*(\[.*?\])\s*[,}]/s',
            '/"products"\s*:\s*(\[.*?\])\s*[,}]/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $data = json_decode($matches[1], true);
                if (!is_array($data)) continue;

                foreach ($data as $cat) {
                    $catName = $cat['name'] ?? $cat['title'] ?? null;
                    $catItems = $cat['products'] ?? $cat['items'] ?? $cat['menuItems'] ?? [];

                    if (empty($catItems) && isset($cat['name']) && isset($cat['cost'])) {
                        // This is an item, not a category
                        $items[] = $this->makeItem(
                            $cat['name'], (float) ($cat['cost'] ?? $cat['price'] ?? 0) / 100,
                            $cat['description'] ?? null, null, $cat['imageUrl'] ?? null,
                            ['external_id' => $cat['id'] ?? null]
                        );
                        continue;
                    }

                    foreach ($catItems as $mi) {
                        $price = isset($mi['cost']) ? (float) $mi['cost'] / 100 : (isset($mi['price']) ? (float) $mi['price'] : null);

                        $modifiers = [];
                        foreach ($mi['modifierGroups'] ?? $mi['optionGroups'] ?? [] as $mg) {
                            $gName = $mg['name'] ?? 'Options';
                            foreach ($mg['modifiers'] ?? $mg['options'] ?? [] as $mod) {
                                $modifiers[] = [
                                    'group_name' => $gName,
                                    'option_name' => $mod['name'] ?? '',
                                    'price_adjustment' => isset($mod['cost']) ? (float) $mod['cost'] / 100 : 0,
                                    'is_default' => (bool) ($mod['isDefault'] ?? false),
                                    'is_required' => (bool) ($mg['isRequired'] ?? false),
                                ];
                            }
                        }

                        $items[] = $this->makeItem(
                            $mi['name'] ?? 'Unknown', $price,
                            $mi['description'] ?? null, $catName,
                            $mi['imageUrl'] ?? $mi['image'] ?? null,
                            [
                                'external_id' => $mi['id'] ?? null,
                                'calories' => $mi['calories'] ?? null,
                                'modifiers' => !empty($modifiers) ? $modifiers : null,
                            ]
                        );
                    }
                }
                if (!empty($items)) return $items;
            }
        }
        return $items;
    }

    private function extractFromDom(Crawler $crawler): array
    {
        $items = [];
        $selectors = ['[class*="product-card"]', '[class*="menu-item"]', '[class*="MenuItem"]'];
        foreach ($selectors as $sel) {
            try {
                $crawler->filter($sel)->each(function (Crawler $node) use (&$items) {
                    $name = null;
                    foreach (['h3','h4','.product-name','[class*="name"]'] as $ns) {
                        try { $n = $node->filter($ns)->first(); if ($n->count()) { $name = trim($n->text()); break; } } catch (\Exception $e) { continue; }
                    }
                    $price = $this->extractPrice($node->text());
                    if ($name && strlen($name) >= 2) $items[] = $this->makeItem($name, $price);
                });
                if (!empty($items)) break;
            } catch (\Exception $e) { continue; }
        }
        return $items;
    }

    private function extractRestaurantInfo(Crawler $crawler): array
    {
        $info = ['name' => null, 'address' => null, 'phone' => null, 'logo_url' => null, 'banner_url' => null];
        try { $h1 = $crawler->filter('h1')->first(); if ($h1->count()) $info['name'] = trim($h1->text()); } catch (\Exception $e) {}
        try { $og = $crawler->filter('meta[property="og:image"]')->first(); if ($og->count()) $info['banner_url'] = $og->attr('content'); } catch (\Exception $e) {}
        return $info;
    }

    public function healthCheck(): array
    {
        try { $this->http->head('https://www.olo.com', ['timeout' => 10]); return ['healthy' => true, 'message' => 'Olo reachable']; }
        catch (\Exception $e) { return ['healthy' => false, 'message' => $e->getMessage()]; }
    }
}
