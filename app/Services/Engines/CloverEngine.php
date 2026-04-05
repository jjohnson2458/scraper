<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Clover Engine
 *
 * Scrapes restaurant menus from Clover online ordering pages.
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class CloverEngine extends AbstractEngine
{
    public function getPlatformSlug(): string { return 'clover'; }

    public function canHandle(string $url): bool
    {
        return (bool) preg_match('/clover\.com/i', $url);
    }

    protected function doScrape(string $url): array
    {
        try {
            $crawler = $this->fetchDom($url);
            $html = $crawler->html();
        } catch (\Exception $e) {
            return $this->success([], [], 'Site blocked the request. Try a different platform link.');
        }

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
        $patterns = [
            '/"categories"\s*:\s*(\[.*?\])\s*[,}]/s',
            '/"items"\s*:\s*(\[.*?\])\s*[,}]/s',
            '/"menuItems"\s*:\s*(\[.*?\])\s*[,}]/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $data = json_decode($matches[1], true);
                if (!is_array($data)) continue;

                foreach ($data as $entry) {
                    if (isset($entry['items'])) {
                        $catName = $entry['name'] ?? null;
                        foreach ($entry['items'] as $mi) {
                            $items[] = $this->makeItem(
                                $mi['name'] ?? 'Unknown',
                                isset($mi['price']) ? (float) $mi['price'] / 100 : null,
                                $mi['description'] ?? null, $catName,
                                $mi['imageUrl'] ?? null,
                                ['external_id' => $mi['id'] ?? null]
                            );
                        }
                    } elseif (isset($entry['name']) && isset($entry['price'])) {
                        $items[] = $this->makeItem(
                            $entry['name'],
                            (float) $entry['price'] / 100,
                            $entry['description'] ?? null, null,
                            $entry['imageUrl'] ?? null,
                            ['external_id' => $entry['id'] ?? null]
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
        $selectors = ['[class*="menu-item"]', '[class*="MenuItem"]', '.item-card', '.menu-item'];
        foreach ($selectors as $sel) {
            try {
                $crawler->filter($sel)->each(function (Crawler $node) use (&$items) {
                    $name = null;
                    foreach (['h3','h4','.item-name','[class*="name"]'] as $ns) {
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
        try { $this->http->head('https://www.clover.com', ['timeout' => 10]); return ['healthy' => true, 'message' => 'Clover reachable']; }
        catch (\Exception $e) { return ['healthy' => false, 'message' => $e->getMessage()]; }
    }
}
