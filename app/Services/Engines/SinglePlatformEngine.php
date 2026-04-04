<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * SinglePlatform Engine
 *
 * Scrapes restaurant menus from SinglePlatform (TripAdvisor-owned).
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class SinglePlatformEngine extends AbstractEngine
{
    public function getPlatformSlug(): string { return 'singleplatform'; }
    public function canHandle(string $url): bool { return (bool) preg_match('/singleplatform\.com/i', $url); }

    protected function doScrape(string $url): array
    {
        try {
            $crawler = $this->fetchDom($url);
        } catch (\Exception $e) {
            $crawler = $this->fetchWithSelenium($url, 5);
        }

        $items = $this->extractJsonLd($crawler);
        if (empty($items)) $items = $this->extractFromDom($crawler);

        $restaurant = $this->getRestaurantInfo($crawler);
        return $this->success($items, $restaurant);
    }

    private function extractJsonLd(Crawler $crawler): array
    {
        $items = [];
        try {
            $crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $s) use (&$items) {
                $d = json_decode($s->text(), true);
                if ($d) $this->walkLd($d, $items, null);
            });
        } catch (\Exception $e) {}
        return $items;
    }

    private function walkLd(array $d, array &$items, ?string $cat): void
    {
        if (isset($d['@graph'])) { foreach ($d['@graph'] as $n) $this->walkLd($n, $items, $cat); return; }
        $t = $d['@type'] ?? '';
        if (in_array($t, ['MenuItem','Product'])) {
            $p = isset($d['offers']['price']) ? (float)$d['offers']['price'] : null;
            $items[] = $this->makeItem($d['name'] ?? 'Unknown', $p, $d['description'] ?? null, $cat, is_array($d['image'] ?? null) ? ($d['image'][0] ?? null) : ($d['image'] ?? null));
        }
        if (in_array($t, ['Menu','MenuSection'])) {
            foreach ($d['hasMenuItem'] ?? $d['hasMenuSection'] ?? [] as $c) { if (is_array($c)) $this->walkLd($c, $items, $d['name'] ?? $cat); }
        }
    }

    private function extractFromDom(Crawler $crawler): array
    {
        $items = [];
        foreach (['.menu-item', '.sp-menu-item', '[class*="menuItem"]', '.item'] as $sel) {
            try {
                $crawler->filter($sel)->each(function (Crawler $n) use (&$items) {
                    $name = null;
                    foreach (['h3','h4','.name','strong'] as $ns) { try { $x = $n->filter($ns)->first(); if ($x->count()) { $name = trim($x->text()); break; } } catch (\Exception $e) { continue; } }
                    $price = $this->extractPrice($n->text());
                    if ($name && strlen($name) >= 2) $items[] = $this->makeItem($name, $price);
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
        try { $this->http->head('https://www.singleplatform.com', ['timeout'=>10]); return ['healthy'=>true,'message'=>'SinglePlatform reachable']; }
        catch (\Exception $e) { return ['healthy'=>false,'message'=>$e->getMessage()]; }
    }
}
