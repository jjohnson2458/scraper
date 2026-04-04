<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Owner.com Engine
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class OwnerEngine extends AbstractEngine
{
    public function getPlatformSlug(): string { return 'owner'; }
    public function canHandle(string $url): bool { return (bool) preg_match('/owner\.com/i', $url); }

    protected function doScrape(string $url): array
    {
        try { $crawler = $this->fetchDom($url); } catch (\Exception $e) { $crawler = $this->fetchWithSelenium($url, 5); }
        $html = $crawler->html();
        $items = $this->extractFromJson($html);
        if (empty($items)) $items = $this->extractFromDom($crawler);
        $restaurant = $this->getInfo($crawler);
        return $this->success($items, $restaurant);
    }

    private function extractFromJson(string $html): array
    {
        $items = [];
        foreach (['/"categories"\s*:\s*(\[.*?\])\s*[,}]/s', '/"menuItems"\s*:\s*(\[.*?\])\s*[,}]/s'] as $p) {
            if (preg_match($p, $html, $m)) {
                $data = json_decode($m[1], true);
                if (!is_array($data)) continue;
                foreach ($data as $entry) {
                    if (isset($entry['items'])) {
                        $cat = $entry['name'] ?? null;
                        foreach ($entry['items'] as $mi) {
                            $items[] = $this->makeItem($mi['name'] ?? 'Unknown', isset($mi['price']) ? (float)$mi['price'] : null, $mi['description'] ?? null, $cat, $mi['imageUrl'] ?? null, ['external_id' => $mi['id'] ?? null]);
                        }
                    } elseif (isset($entry['name'])) {
                        $items[] = $this->makeItem($entry['name'], isset($entry['price']) ? (float)$entry['price'] : null, $entry['description'] ?? null, null, $entry['imageUrl'] ?? null);
                    }
                }
                if (!empty($items)) return $items;
            }
        }
        return $items;
    }

    private function extractFromDom(Crawler $c): array
    {
        $items = [];
        foreach (['.menu-item','[class*="MenuItem"]','[class*="menu-item"]','.product-card'] as $s) {
            try { $c->filter($s)->each(function(Crawler $n) use (&$items) { $name=null; foreach(['h3','h4','.name','strong'] as $ns) { try { $x=$n->filter($ns)->first(); if($x->count()){$name=trim($x->text());break;} } catch(\Exception $e){continue;} } $price=$this->extractPrice($n->text()); if($name&&strlen($name)>=2) $items[]=$this->makeItem($name,$price); }); if(!empty($items)) break; } catch(\Exception $e){continue;}
        }
        return $items;
    }

    private function getInfo(Crawler $c): array
    {
        $i=['name'=>null,'address'=>null,'phone'=>null,'logo_url'=>null,'banner_url'=>null];
        try{$h=$c->filter('h1')->first();if($h->count())$i['name']=trim($h->text());}catch(\Exception $e){}
        try{$o=$c->filter('meta[property="og:image"]')->first();if($o->count())$i['banner_url']=$o->attr('content');}catch(\Exception $e){}
        return $i;
    }

    public function healthCheck(): array
    {
        try{$this->http->head('https://www.owner.com',['timeout'=>10]);return['healthy'=>true,'message'=>'Owner.com reachable'];}catch(\Exception $e){return['healthy'=>false,'message'=>$e->getMessage()];}
    }
}
