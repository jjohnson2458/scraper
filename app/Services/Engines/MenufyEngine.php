<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Menufy Engine
 *
 * Scrapes restaurant menus from Menufy (menufy.com).
 * Menufy provides online ordering + menu hosting with
 * relatively clean DOM structure.
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class MenufyEngine extends AbstractEngine
{
    public function getPlatformSlug(): string { return 'menufy'; }
    public function canHandle(string $url): bool { return (bool) preg_match('/menufy\.com/i', $url); }

    protected function doScrape(string $url): array
    {
        try {
            $crawler = $this->fetchDom($url);
        } catch (\Exception $e) {
            return $this->success([], [], 'Site blocked the request. Try a different platform link.');
        }

        $items = $this->extractFromDom($crawler);
        $restaurant = $this->getRestaurantInfo($crawler);
        return $this->success($items, $restaurant);
    }

    private function extractFromDom(Crawler $crawler): array
    {
        $items = [];
        $currentCategory = null;

        // Menufy uses section headers + item lists
        $selectors = ['.menu-category', '.menu-section', '[class*="menuCategory"]'];
        foreach ($selectors as $catSel) {
            try {
                $crawler->filter($catSel)->each(function (Crawler $section) use (&$items) {
                    $catName = null;
                    foreach (['h2','h3','.category-name','[class*="categoryName"]'] as $hs) {
                        try { $h = $section->filter($hs)->first(); if ($h->count()) { $catName = trim($h->text()); break; } } catch (\Exception $e) { continue; }
                    }

                    $itemSels = ['.menu-item', '.item', '[class*="menuItem"]', 'li'];
                    foreach ($itemSels as $iSel) {
                        try {
                            $section->filter($iSel)->each(function (Crawler $node) use (&$items, $catName) {
                                $name = null;
                                foreach (['h4','h5','.item-name','.name','strong'] as $ns) {
                                    try { $n = $node->filter($ns)->first(); if ($n->count()) { $name = trim($n->text()); break; } } catch (\Exception $e) { continue; }
                                }
                                $price = $this->extractPrice($node->text());
                                $desc = null;
                                try { $d = $node->filter('p, .description, [class*="desc"]')->first(); if ($d->count()) $desc = trim($d->text()); } catch (\Exception $e) {}
                                $img = null;
                                try { $i = $node->filter('img')->first(); if ($i->count()) $img = $i->attr('src') ?: $i->attr('data-src'); } catch (\Exception $e) {}
                                if ($name && strlen($name) >= 2) $items[] = $this->makeItem($name, $price, $desc, $catName, $img);
                            });
                        } catch (\Exception $e) { continue; }
                    }
                });
                if (!empty($items)) break;
            } catch (\Exception $e) { continue; }
        }

        // Flat fallback if no category structure
        if (empty($items)) {
            foreach (['.menu-item', '[class*="menuItem"]', '.item-card'] as $sel) {
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
        try { $this->http->head('https://www.menufy.com', ['timeout'=>10]); return ['healthy'=>true,'message'=>'Menufy reachable']; }
        catch (\Exception $e) { return ['healthy'=>false,'message'=>$e->getMessage()]; }
    }
}
