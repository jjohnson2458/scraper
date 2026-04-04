<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Lightspeed Engine
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class LightspeedEngine extends AbstractEngine
{
    public function getPlatformSlug(): string { return 'lightspeed'; }
    public function canHandle(string $url): bool { return (bool) preg_match('/lightspeed\.com|lsku\.io/i', $url); }

    protected function doScrape(string $url): array
    {
        try { $crawler = $this->fetchDom($url); } catch (\Exception $e) { $crawler = $this->fetchWithSelenium($url, 5); }
        $html = $crawler->html();
        $items = $this->extractFromJson($html);
        if (empty($items)) $items = $this->extractFromDom($crawler);
        return $this->success($items, $this->getInfo($crawler));
    }

    private function extractFromJson(string $html): array
    {
        $items = [];
        if (preg_match('/"products"\s*:\s*(\[.*?\])\s*[,}]/s', $html, $m)) {
            $data = json_decode($m[1], true);
            if (is_array($data)) {
                foreach ($data as $mi) {
                    $items[] = $this->makeItem($mi['name'] ?? 'Unknown', isset($mi['price']) ? (float)$mi['price'] : null, $mi['description'] ?? null, $mi['category'] ?? null, $mi['image'] ?? null);
                }
            }
        }
        return $items;
    }

    private function extractFromDom(Crawler $c): array
    {
        $items = [];
        foreach (['.menu-item','[class*="product"]','[class*="menu-item"]','.item'] as $s) {
            try { $c->filter($s)->each(function(Crawler $n) use (&$items) { $name=null; foreach(['h3','h4','.name','strong'] as $ns) { try{$x=$n->filter($ns)->first();if($x->count()){$name=trim($x->text());break;}}catch(\Exception $e){continue;} } $price=$this->extractPrice($n->text()); if($name&&strlen($name)>=2) $items[]=$this->makeItem($name,$price); }); if(!empty($items)) break; } catch(\Exception $e){continue;}
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
        try{$this->http->head('https://www.lightspeedhq.com',['timeout'=>10]);return['healthy'=>true,'message'=>'Lightspeed reachable'];}catch(\Exception $e){return['healthy'=>false,'message'=>$e->getMessage()];}
    }
}
