<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Popmenu Engine
 *
 * Scrapes restaurant menus from Popmenu-powered sites.
 * Popmenu uses a mix of API endpoints and structured DOM.
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class PopmenuEngine extends AbstractEngine
{
    public function getPlatformSlug(): string { return 'popmenu'; }

    public function canHandle(string $url): bool
    {
        return (bool) preg_match('/popmenu\.com/i', $url);
    }

    protected function doScrape(string $url): array
    {
        try {
            $crawler = $this->fetchDom($url);
            $html = $crawler->html();
        } catch (\Exception $e) {
            $crawler = $this->fetchWithSelenium($url, 5);
            $html = $crawler->html();
        }

        // Popmenu often uses structured JSON-LD or inline JSON
        $items = $this->extractJsonLd($crawler);
        if (empty($items)) {
            $items = $this->extractFromJson($html);
        }
        if (empty($items)) {
            $items = $this->extractFromDom($crawler);
        }

        $restaurant = $this->extractRestaurantInfo($crawler);
        return $this->success($items, $restaurant);
    }

    private function extractJsonLd(Crawler $crawler): array
    {
        $items = [];
        try {
            $crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $script) use (&$items) {
                $data = json_decode($script->text(), true);
                if (!$data) return;
                $this->walkMenu($data, $items, null);
            });
        } catch (\Exception $e) {}
        return $items;
    }

    private function walkMenu(array $data, array &$items, ?string $category): void
    {
        if (isset($data['@graph'])) { foreach ($data['@graph'] as $n) { $this->walkMenu($n, $items, $category); } return; }
        $type = $data['@type'] ?? '';
        if (in_array($type, ['MenuItem', 'Product'])) {
            $price = isset($data['offers']['price']) ? (float) $data['offers']['price'] : null;
            $items[] = $this->makeItem($data['name'] ?? 'Unknown', $price, $data['description'] ?? null, $category,
                is_array($data['image'] ?? null) ? ($data['image'][0] ?? null) : ($data['image'] ?? null));
        }
        if (in_array($type, ['Menu', 'MenuSection'])) {
            $cat = $data['name'] ?? $category;
            foreach ($data['hasMenuItem'] ?? $data['hasMenuSection'] ?? [] as $child) {
                if (is_array($child)) $this->walkMenu($child, $items, $cat);
            }
        }
    }

    private function extractFromJson(string $html): array
    {
        $items = [];
        if (preg_match('/"menuSections"\s*:\s*(\[.*?\])\s*[,}]/s', $html, $matches)) {
            $sections = json_decode($matches[1], true);
            if (is_array($sections)) {
                foreach ($sections as $sec) {
                    $catName = $sec['name'] ?? null;
                    foreach ($sec['menuItems'] ?? $sec['items'] ?? [] as $mi) {
                        $items[] = $this->makeItem(
                            $mi['name'] ?? 'Unknown',
                            isset($mi['price']) ? (float) $mi['price'] : null,
                            $mi['description'] ?? null, $catName,
                            $mi['imageUrl'] ?? $mi['image'] ?? null,
                            ['external_id' => $mi['id'] ?? null]
                        );
                    }
                }
            }
        }
        return $items;
    }

    private function extractFromDom(Crawler $crawler): array
    {
        $items = [];
        $selectors = ['[class*="menu-item"]', '[class*="MenuItem"]', '.menu-card', '.dish-card'];
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
        try { $this->http->head('https://www.popmenu.com', ['timeout' => 10]); return ['healthy' => true, 'message' => 'Popmenu reachable']; }
        catch (\Exception $e) { return ['healthy' => false, 'message' => $e->getMessage()]; }
    }
}
