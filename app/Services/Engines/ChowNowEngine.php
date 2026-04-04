<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * ChowNow Engine
 *
 * Scrapes restaurant menus from ChowNow (chownow.com).
 * ChowNow has clean JSON API endpoints for menu data,
 * making it one of the most reliable engines.
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class ChowNowEngine extends AbstractEngine
{
    /**
     * {@inheritdoc}
     */
    public function getPlatformSlug(): string
    {
        return 'chownow';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandle(string $url): bool
    {
        return (bool) preg_match('/chownow\.com|direct\.chownow\.com/i', $url);
    }

    /**
     * {@inheritdoc}
     */
    protected function doScrape(string $url): array
    {
        // Try to extract restaurant slug/ID from URL
        $restaurantId = $this->extractRestaurantId($url);

        // ChowNow pages often have clean JSON in the source
        try {
            $response = $this->http->get($url);
            $html = (string) $response->getBody();
            $crawler = new Crawler($html, $url);

            $items = $this->extractFromPageJson($html);
            if (empty($items) && $restaurantId) {
                $items = $this->fetchFromApi($restaurantId);
            }
            if (empty($items)) {
                $items = $this->extractFromDom($crawler);
            }

            $restaurant = $this->extractRestaurantInfo($crawler);
            return $this->success($items, $restaurant);
        } catch (\Exception $e) {
            // If blocked, try Selenium
            $crawler = $this->fetchWithSelenium($url, 5);
            $items = $this->extractFromPageJson($crawler->html());
            if (empty($items)) {
                $items = $this->extractFromDom($crawler);
            }
            $restaurant = $this->extractRestaurantInfo($crawler);
            return $this->success($items, $restaurant);
        }
    }

    /**
     * Extract restaurant ID from a ChowNow URL.
     *
     * @param string $url The URL.
     * @return string|null
     */
    private function extractRestaurantId(string $url): ?string
    {
        // Pattern: direct.chownow.com/order/restaurant/slug/12345
        if (preg_match('/\/(?:restaurant|order)\/[^\/]*\/(\d+)/i', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Fetch from ChowNow API.
     *
     * @param string $restaurantId The restaurant ID.
     * @return array
     */
    private function fetchFromApi(string $restaurantId): array
    {
        $items = [];
        try {
            $apiUrl = "https://api.chownow.com/api/restaurant/{$restaurantId}/menu";
            $response = $this->http->get($apiUrl, [
                'headers' => ['Accept' => 'application/json'],
            ]);
            $data = json_decode((string) $response->getBody(), true);

            foreach ($data['categories'] ?? $data['menu']['categories'] ?? [] as $cat) {
                $catName = $cat['name'] ?? null;
                foreach ($cat['items'] ?? [] as $mi) {
                    $items[] = $this->makeItem(
                        $mi['name'] ?? 'Unknown',
                        isset($mi['price']) ? (float) $mi['price'] : null,
                        $mi['description'] ?? null,
                        $catName,
                        $mi['image_url'] ?? $mi['image'] ?? null,
                        ['external_id' => $mi['id'] ?? null]
                    );
                }
            }
        } catch (\Exception $e) {
            // API may not be available
        }
        return $items;
    }

    /**
     * Extract menu data from inline JSON.
     *
     * @param string $html The page HTML.
     * @return array
     */
    private function extractFromPageJson(string $html): array
    {
        $items = [];

        // ChowNow often embeds menu data in script tags
        $patterns = [
            '/"categories"\s*:\s*(\[.*?\])\s*[,}]/s',
            '/"menuCategories"\s*:\s*(\[.*?\])\s*[,}]/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $categories = json_decode($matches[1], true);
                if (is_array($categories)) {
                    foreach ($categories as $cat) {
                        $catName = $cat['name'] ?? null;
                        foreach ($cat['items'] ?? $cat['menu_items'] ?? [] as $mi) {
                            $items[] = $this->makeItem(
                                $mi['name'] ?? 'Unknown',
                                isset($mi['price']) ? (float) $mi['price'] : null,
                                $mi['description'] ?? null,
                                $catName,
                                $mi['image_url'] ?? $mi['image'] ?? null,
                                ['external_id' => $mi['id'] ?? null]
                            );
                        }
                    }
                    if (!empty($items)) return $items;
                }
            }
        }

        return $items;
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
        $selectors = ['[class*="menu-item"]', '[class*="MenuItem"]', '.item-card'];

        foreach ($selectors as $selector) {
            try {
                $crawler->filter($selector)->each(function (Crawler $node) use (&$items) {
                    $name = null;
                    foreach (['h3', 'h4', '.item-name', '[class*="name"]'] as $ns) {
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

    /**
     * Extract restaurant metadata.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array
     */
    private function extractRestaurantInfo(Crawler $crawler): array
    {
        $info = ['name' => null, 'address' => null, 'phone' => null, 'logo_url' => null, 'banner_url' => null];
        try { $h1 = $crawler->filter('h1')->first(); if ($h1->count()) $info['name'] = trim($h1->text()); } catch (\Exception $e) {}
        try { $og = $crawler->filter('meta[property="og:image"]')->first(); if ($og->count()) $info['banner_url'] = $og->attr('content'); } catch (\Exception $e) {}
        return $info;
    }

    /**
     * {@inheritdoc}
     */
    public function healthCheck(): array
    {
        try { $this->http->head('https://www.chownow.com', ['timeout' => 10]); return ['healthy' => true, 'message' => 'ChowNow reachable']; }
        catch (\Exception $e) { return ['healthy' => false, 'message' => $e->getMessage()]; }
    }
}
