<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Google Maps Engine
 *
 * Scrapes restaurant menu data from Google Maps / Google Business profiles.
 * Google embeds menu data in structured formats within business listings.
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class GoogleMapsEngine extends AbstractEngine
{
    public function getPlatformSlug(): string { return 'google'; }
    public function canHandle(string $url): bool { return (bool) preg_match('/google\.com\/maps|maps\.google/i', $url); }

    protected function doScrape(string $url): array
    {
        // Google Maps is heavily JS-rendered
        $crawler = $this->fetchWithSelenium($url, 8);
        $html = $crawler->html();

        $items = $this->extractFromJson($html);
        if (empty($items)) {
            $items = $this->extractFromDom($crawler);
        }

        $restaurant = $this->getRestaurantInfo($crawler);
        return $this->success($items, $restaurant);
    }

    private function extractFromJson(string $html): array
    {
        $items = [];

        // Google embeds structured data
        if (preg_match_all('/"name"\s*:\s*"([^"]+)"[^}]*?"price"\s*:\s*"?(\$?[\d.]+)/s', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = $m[1];
                $price = $this->extractPrice($m[2]);
                if ($name && strlen($name) >= 2 && strlen($name) < 200) {
                    $items[] = $this->makeItem($name, $price);
                }
            }
        }

        // Try JSON-LD
        $crawler = new Crawler($html);
        try {
            $crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $s) use (&$items) {
                $d = json_decode($s->text(), true);
                if (!$d) return;
                if (isset($d['hasMenu'])) {
                    $this->walkMenu($d['hasMenu'], $items, null);
                }
            });
        } catch (\Exception $e) {}

        return $items;
    }

    private function walkMenu(array $data, array &$items, ?string $cat): void
    {
        $type = $data['@type'] ?? '';
        if (in_array($type, ['MenuItem', 'Product'])) {
            $p = isset($data['offers']['price']) ? (float)$data['offers']['price'] : null;
            $items[] = $this->makeItem($data['name'] ?? 'Unknown', $p, $data['description'] ?? null, $cat);
        }
        if (in_array($type, ['Menu', 'MenuSection'])) {
            foreach ($data['hasMenuSection'] ?? $data['hasMenuItem'] ?? [] as $c) {
                if (is_array($c)) $this->walkMenu($c, $items, $data['name'] ?? $cat);
            }
        }
    }

    private function extractFromDom(Crawler $crawler): array
    {
        $items = [];
        // Google Maps menu items appear in specific containers
        $selectors = ['[data-item-id]', '[class*="menu-item"]', '[role="listitem"]'];
        foreach ($selectors as $sel) {
            try {
                $crawler->filter($sel)->each(function (Crawler $n) use (&$items) {
                    $text = trim($n->text());
                    if (strlen($text) < 3 || strlen($text) > 500) return;
                    $price = $this->extractPrice($text);
                    $name = preg_replace('/\$[\d.]+/', '', $text);
                    $name = trim($name, " \t\n\r-.");
                    if ($name && $price && strlen($name) >= 2) {
                        $items[] = $this->makeItem($name, $price);
                    }
                });
                if (!empty($items)) break;
            } catch (\Exception $e) { continue; }
        }
        return $items;
    }

    private function getRestaurantInfo(Crawler $c): array
    {
        $i = ['name'=>null,'address'=>null,'phone'=>null,'logo_url'=>null,'banner_url'=>null];
        try { $h = $c->filter('h1')->first(); if ($h->count()) $i['name'] = trim($h->text()); } catch (\Exception $e) {}
        try { $o = $c->filter('meta[property="og:image"]')->first(); if ($o->count()) $i['banner_url'] = $o->attr('content'); } catch (\Exception $e) {}
        return $i;
    }

    public function healthCheck(): array
    {
        try { $this->http->head('https://www.google.com/maps', ['timeout'=>10]); return ['healthy'=>true,'message'=>'Google Maps reachable']; }
        catch (\Exception $e) { return ['healthy'=>false,'message'=>$e->getMessage()]; }
    }
}
