<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * BentoBox Engine
 *
 * Scrapes restaurant menus from BentoBox-powered websites.
 * BentoBox is a popular restaurant website builder with
 * structured menu pages.
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class BentoBoxEngine extends AbstractEngine
{
    public function getPlatformSlug(): string { return 'bentobox'; }

    public function canHandle(string $url): bool
    {
        return (bool) preg_match('/bentobox\.com|getbento\.com/i', $url);
    }

    protected function doScrape(string $url): array
    {
        try {
            $crawler = $this->fetchDom($url);
        } catch (\Exception $e) {
            return $this->success([], [], 'Site blocked the request. Try a different platform link.');
        }

        // BentoBox often uses JSON-LD structured data
        $items = $this->extractJsonLd($crawler);
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
                $this->walkJsonLd($data, $items, null);
            });
        } catch (\Exception $e) {}
        return $items;
    }

    private function walkJsonLd(array $data, array &$items, ?string $category): void
    {
        if (isset($data['@graph'])) {
            foreach ($data['@graph'] as $node) { $this->walkJsonLd($node, $items, $category); }
            return;
        }

        $type = $data['@type'] ?? '';
        if (in_array($type, ['MenuItem', 'Product'])) {
            $price = isset($data['offers']['price']) ? (float) $data['offers']['price'] : null;
            $items[] = $this->makeItem(
                $data['name'] ?? 'Unknown', $price,
                $data['description'] ?? null, $category,
                is_array($data['image'] ?? null) ? ($data['image'][0] ?? null) : ($data['image'] ?? null)
            );
        }
        if (in_array($type, ['Menu', 'MenuSection'])) {
            $cat = $data['name'] ?? $category;
            foreach ($data['hasMenuItem'] ?? $data['hasMenuSection'] ?? [] as $child) {
                if (is_array($child)) $this->walkJsonLd($child, $items, $cat);
            }
        }
    }

    private function extractFromDom(Crawler $crawler): array
    {
        $items = [];
        // BentoBox uses specific class patterns
        $selectors = [
            '.menu-item', '.bento-menu-item', '[class*="menu_item"]',
            '[class*="MenuCard"]', '.dish', '.menu-entry',
        ];

        foreach ($selectors as $sel) {
            try {
                $crawler->filter($sel)->each(function (Crawler $node) use (&$items) {
                    $name = null;
                    foreach (['h3','h4','.item-title','.dish-name','[class*="name"]','strong'] as $ns) {
                        try { $n = $node->filter($ns)->first(); if ($n->count()) { $name = trim($n->text()); break; } } catch (\Exception $e) { continue; }
                    }
                    $price = $this->extractPrice($node->text());
                    $desc = null;
                    try { $d = $node->filter('p, .description, [class*="desc"]')->first(); if ($d->count()) $desc = trim($d->text()); } catch (\Exception $e) {}
                    $img = null;
                    try { $i = $node->filter('img')->first(); if ($i->count()) $img = $i->attr('src') ?: $i->attr('data-src'); } catch (\Exception $e) {}
                    if ($name && strlen($name) >= 2) $items[] = $this->makeItem($name, $price, $desc, null, $img);
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
        try { $logo = $crawler->filter('img[class*="logo"], .logo img')->first(); if ($logo->count()) $info['logo_url'] = $logo->attr('src'); } catch (\Exception $e) {}
        return $info;
    }

    public function healthCheck(): array
    {
        try { $this->http->head('https://www.bentobox.com', ['timeout' => 10]); return ['healthy' => true, 'message' => 'BentoBox reachable']; }
        catch (\Exception $e) { return ['healthy' => false, 'message' => $e->getMessage()]; }
    }
}
