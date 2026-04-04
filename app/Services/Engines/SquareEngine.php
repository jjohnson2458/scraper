<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Square Online Engine
 *
 * Scrapes restaurant menus from Square Online storefronts
 * (squareup.com, square.site).
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class SquareEngine extends AbstractEngine
{
    /**
     * {@inheritdoc}
     */
    public function getPlatformSlug(): string
    {
        return 'square';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandle(string $url): bool
    {
        return (bool) preg_match('/squareup\.com|square\.site/i', $url);
    }

    /**
     * {@inheritdoc}
     */
    protected function doScrape(string $url): array
    {
        // Square sites are typically less JS-heavy; try direct first
        try {
            $crawler = $this->fetchDom($url);
            $html = $crawler->html();

            $items = $this->extractFromSquareJson($html);
            if (empty($items)) {
                $items = $this->extractFromDom($crawler);
            }

            if (!empty($items)) {
                $restaurant = $this->extractRestaurantInfo($crawler);
                return $this->success($items, $restaurant);
            }
        } catch (\Exception $e) {
            // Fall through to Selenium
        }

        // Selenium fallback
        $crawler = $this->fetchWithSelenium($url, 5);
        $html = $crawler->html();

        $items = $this->extractFromSquareJson($html);
        if (empty($items)) {
            $items = $this->extractFromDom($crawler);
        }

        $restaurant = $this->extractRestaurantInfo($crawler);
        return $this->success($items, $restaurant);
    }

    /**
     * Extract menu data from Square's inline JSON.
     *
     * @param string $html The page HTML.
     * @return array
     */
    private function extractFromSquareJson(string $html): array
    {
        $items = [];

        // Square embeds catalog data in script tags
        $patterns = [
            '/"items"\s*:\s*(\[.*?\])\s*[,}]/s',
            '/"catalog"\s*:\s*(\{.*?\})\s*[,}]/s',
            '/"menuItems"\s*:\s*(\[.*?\])\s*[,}]/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $data = json_decode($matches[1], true);
                if (is_array($data)) {
                    $this->parseSquareData($data, $items);
                    if (!empty($items)) return $items;
                }
            }
        }

        return $items;
    }

    /**
     * Parse Square catalog data.
     *
     * @param array $data   The data.
     * @param array &$items Items collection.
     * @return void
     */
    private function parseSquareData(array $data, array &$items): void
    {
        // Handle array of items directly
        foreach ($data as $entry) {
            if (!is_array($entry)) continue;

            $name = $entry['name'] ?? $entry['title'] ?? null;
            if (!$name) {
                // May be a category with nested items
                $catName = $entry['name'] ?? $entry['category_name'] ?? null;
                foreach ($entry['items'] ?? $entry['item_data'] ?? [] as $mi) {
                    if (is_array($mi)) {
                        $itemName = $mi['name'] ?? $mi['title'] ?? null;
                        if ($itemName) {
                            $price = null;
                            if (isset($mi['variations'][0]['price']['amount'])) {
                                $price = (float) $mi['variations'][0]['price']['amount'] / 100;
                            } elseif (isset($mi['price'])) {
                                $price = (float) $mi['price'];
                            }
                            $items[] = $this->makeItem(
                                $itemName, $price,
                                $mi['description'] ?? null, $catName,
                                $mi['image_url'] ?? $mi['image'] ?? null,
                                ['external_id' => $mi['id'] ?? null]
                            );
                        }
                    }
                }
                continue;
            }

            $price = null;
            if (isset($entry['variations'][0]['price']['amount'])) {
                $price = (float) $entry['variations'][0]['price']['amount'] / 100;
            } elseif (isset($entry['price'])) {
                $price = (float) $entry['price'];
            }

            $items[] = $this->makeItem(
                $name, $price,
                $entry['description'] ?? null,
                $entry['category_name'] ?? $entry['category'] ?? null,
                $entry['image_url'] ?? $entry['image'] ?? null,
                ['external_id' => $entry['id'] ?? null]
            );
        }
    }

    /**
     * DOM fallback.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array
     */
    private function extractFromDom(Crawler $crawler): array
    {
        $items = [];
        $selectors = [
            '[class*="menu-item"]', '[class*="item-card"]', '[class*="product-card"]',
            '.menu-item', '.product', '.item',
        ];

        foreach ($selectors as $selector) {
            try {
                $crawler->filter($selector)->each(function (Crawler $node) use (&$items) {
                    $name = null;
                    foreach (['h3', 'h4', '.item-title', '.product-title', '[class*="name"]'] as $ns) {
                        try { $n = $node->filter($ns)->first(); if ($n->count()) { $name = trim($n->text()); break; } } catch (\Exception $e) { continue; }
                    }
                    $price = $this->extractPrice($node->text());
                    $imageUrl = null;
                    try { $img = $node->filter('img')->first(); if ($img->count()) $imageUrl = $img->attr('src'); } catch (\Exception $e) {}
                    if ($name && strlen($name) >= 2) $items[] = $this->makeItem($name, $price, null, null, $imageUrl);
                });
                if (!empty($items)) break;
            } catch (\Exception $e) { continue; }
        }
        return $items;
    }

    /**
     * Extract restaurant info.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array
     */
    private function extractRestaurantInfo(Crawler $crawler): array
    {
        $info = ['name' => null, 'address' => null, 'phone' => null, 'logo_url' => null, 'banner_url' => null];
        try { $h1 = $crawler->filter('h1')->first(); if ($h1->count()) $info['name'] = trim($h1->text()); } catch (\Exception $e) {}
        try { $og = $crawler->filter('meta[property="og:image"]')->first(); if ($og->count()) $info['banner_url'] = $og->attr('content'); } catch (\Exception $e) {}
        try { $logo = $crawler->filter('img[class*="logo"], .logo img')->first(); if ($logo->count()) $info['logo_url'] = $logo->attr('src'); } catch (\Exception $e) {}
        return $info;
    }

    /**
     * {@inheritdoc}
     */
    public function healthCheck(): array
    {
        try { $this->http->head('https://squareup.com', ['timeout' => 10]); return ['healthy' => true, 'message' => 'Square reachable']; }
        catch (\Exception $e) { return ['healthy' => false, 'message' => $e->getMessage()]; }
    }
}
