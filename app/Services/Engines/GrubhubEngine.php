<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Grubhub Engine
 *
 * Scrapes restaurant menus from Grubhub (grubhub.com).
 * Grubhub exposes REST API endpoints for menu data.
 * Also handles Seamless URLs (same backend).
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class GrubhubEngine extends AbstractEngine
{
    /**
     * {@inheritdoc}
     */
    public function getPlatformSlug(): string
    {
        return 'grubhub';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandle(string $url): bool
    {
        return (bool) preg_match('/grubhub\.com|seamless\.com/i', $url);
    }

    /**
     * {@inheritdoc}
     */
    protected function doScrape(string $url): array
    {
        // Try to extract restaurant ID from URL and hit the API
        $restaurantId = $this->extractRestaurantId($url);
        if ($restaurantId) {
            $items = $this->fetchFromApi($restaurantId);
            if (!empty($items)) {
                $crawler = $this->fetchWithSelenium($url, 4);
                $restaurant = $this->extractRestaurantInfo($crawler);
                return $this->success($items, $restaurant);
            }
        }

        // Fallback to Selenium + DOM
        $crawler = $this->fetchWithSelenium($url, 6);
        $html = $crawler->html();

        $items = $this->extractFromPageJson($html);
        if (empty($items)) {
            $items = $this->extractFromDom($crawler);
        }

        $restaurant = $this->extractRestaurantInfo($crawler);
        return $this->success($items, $restaurant);
    }

    /**
     * Extract the restaurant ID from a Grubhub URL.
     *
     * @param string $url The URL.
     * @return string|null
     */
    private function extractRestaurantId(string $url): ?string
    {
        // Pattern: grubhub.com/restaurant/name-slug/12345
        if (preg_match('/\/restaurant\/[^\/]+\/(\d+)/i', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Fetch menu data from Grubhub's API.
     *
     * @param string $restaurantId The restaurant ID.
     * @return array
     */
    private function fetchFromApi(string $restaurantId): array
    {
        $items = [];
        try {
            $apiUrl = "https://api-gtm.grubhub.com/restaurants/{$restaurantId}/menu";
            $response = $this->http->get($apiUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0',
                ],
            ]);
            $data = json_decode((string) $response->getBody(), true);

            if (isset($data['restaurant']['menu_category_list'])) {
                foreach ($data['restaurant']['menu_category_list'] as $cat) {
                    $catName = $cat['name'] ?? null;
                    foreach ($cat['menu_item_list'] ?? [] as $mi) {
                        $price = isset($mi['price']['amount'])
                            ? (float) $mi['price']['amount'] / 100
                            : null;

                        $modifiers = [];
                        foreach ($mi['choice_category_list'] ?? [] as $cg) {
                            $groupName = $cg['name'] ?? 'Options';
                            foreach ($cg['choice_option_list'] ?? [] as $opt) {
                                $adjPrice = isset($opt['price']['amount']) ? (float) $opt['price']['amount'] / 100 : 0;
                                $modifiers[] = [
                                    'group_name' => $groupName,
                                    'option_name' => $opt['description'] ?? '',
                                    'price_adjustment' => $adjPrice,
                                    'is_default' => false,
                                    'is_required' => (bool) ($cg['min_choice_options'] ?? 0),
                                ];
                            }
                        }

                        $items[] = $this->makeItem(
                            $mi['name'] ?? 'Unknown',
                            $price,
                            $mi['description'] ?? null,
                            $catName,
                            $mi['media_image']['base_url'] ?? null,
                            [
                                'external_id' => $mi['id'] ?? null,
                                'calories' => $mi['calories'] ?? null,
                                'modifiers' => !empty($modifiers) ? $modifiers : null,
                            ]
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            // API may be blocked — fall through
        }

        return $items;
    }

    /**
     * Extract menu data from inline JSON in page source.
     *
     * @param string $html The page HTML.
     * @return array
     */
    private function extractFromPageJson(string $html): array
    {
        $items = [];

        if (preg_match('/"menuCategoryList"\s*:\s*(\[.*?\])\s*[,}]/s', $html, $matches)) {
            $categories = json_decode($matches[1], true);
            if (is_array($categories)) {
                foreach ($categories as $cat) {
                    $catName = $cat['name'] ?? null;
                    foreach ($cat['menuItemList'] ?? $cat['menu_item_list'] ?? [] as $mi) {
                        $price = null;
                        if (isset($mi['price']['amount'])) {
                            $price = (float) $mi['price']['amount'] / 100;
                        }
                        $items[] = $this->makeItem(
                            $mi['name'] ?? 'Unknown',
                            $price,
                            $mi['description'] ?? null,
                            $catName,
                            $mi['mediaImage']['baseUrl'] ?? null,
                            ['external_id' => $mi['id'] ?? null]
                        );
                    }
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
        $selectors = ['[class*="menuItem"]', '[data-testid*="menu-item"]', '.menuItem'];

        foreach ($selectors as $selector) {
            try {
                $crawler->filter($selector)->each(function (Crawler $node) use (&$items) {
                    $name = null;
                    foreach (['h3', 'h4', '[class*="itemName"]', '[class*="name"]'] as $ns) {
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
        try { $this->http->head('https://www.grubhub.com', ['timeout' => 10]); return ['healthy' => true, 'message' => 'Grubhub reachable']; }
        catch (\Exception $e) { return ['healthy' => false, 'message' => $e->getMessage()]; }
    }
}
