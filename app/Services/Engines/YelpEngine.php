<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Yelp Engine
 *
 * Scrapes restaurant menus from Yelp business pages.
 * Yelp shows menu data on the "Full Menu" tab of restaurant listings.
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class YelpEngine extends AbstractEngine
{
    public function getPlatformSlug(): string { return 'yelp'; }
    public function canHandle(string $url): bool { return (bool) preg_match('/yelp\.com/i', $url); }

    protected function doScrape(string $url): array
    {
        // Ensure we're on the menu page
        if (!str_contains($url, '/menu')) {
            $url = rtrim($url, '/') . '/menu';
        }

        $crawler = $this->fetchWithSelenium($url, 5);
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

        // Yelp embeds menu data in JSON
        if (preg_match('/"menuSections"\s*:\s*(\[.*?\])\s*[,}]/s', $html, $matches)) {
            $sections = json_decode($matches[1], true);
            if (is_array($sections)) {
                foreach ($sections as $sec) {
                    $catName = $sec['title'] ?? $sec['name'] ?? null;
                    foreach ($sec['items'] ?? $sec['menuItems'] ?? [] as $mi) {
                        $items[] = $this->makeItem(
                            $mi['title'] ?? $mi['name'] ?? 'Unknown',
                            isset($mi['price']) ? $this->extractPrice($mi['price']) : null,
                            $mi['description'] ?? null, $catName,
                            $mi['photoUrl'] ?? $mi['imageUrl'] ?? null
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
        $currentCategory = null;

        // Yelp menu structure
        $selectors = ['.menu-section', '[class*="menuSection"]', '.menu-category'];
        foreach ($selectors as $catSel) {
            try {
                $crawler->filter($catSel)->each(function (Crawler $section) use (&$items) {
                    $catName = null;
                    foreach (['h2','h3','h4','.section-header','[class*="sectionHeader"]'] as $hs) {
                        try { $h = $section->filter($hs)->first(); if ($h->count()) { $catName = trim($h->text()); break; } } catch (\Exception $e) { continue; }
                    }

                    foreach (['.menu-item', '.menu-item-details', '[class*="menuItem"]'] as $iSel) {
                        try {
                            $section->filter($iSel)->each(function (Crawler $node) use (&$items, $catName) {
                                $name = null;
                                foreach (['h4','h5','.menu-item-name','.name','[class*="itemName"]'] as $ns) {
                                    try { $n = $node->filter($ns)->first(); if ($n->count()) { $name = trim($n->text()); break; } } catch (\Exception $e) { continue; }
                                }
                                $price = $this->extractPrice($node->text());
                                $desc = null;
                                try { $d = $node->filter('p, .menu-item-description, [class*="description"]')->first(); if ($d->count()) $desc = trim($d->text()); } catch (\Exception $e) {}
                                if ($name && strlen($name) >= 2) $items[] = $this->makeItem($name, $price, $desc, $catName);
                            });
                        } catch (\Exception $e) { continue; }
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
        try { $this->http->head('https://www.yelp.com', ['timeout'=>10]); return ['healthy'=>true,'message'=>'Yelp reachable']; }
        catch (\Exception $e) { return ['healthy'=>false,'message'=>$e->getMessage()]; }
    }
}
