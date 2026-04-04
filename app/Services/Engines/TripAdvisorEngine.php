<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * TripAdvisor Engine
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class TripAdvisorEngine extends AbstractEngine
{
    public function getPlatformSlug(): string { return 'tripadvisor'; }
    public function canHandle(string $url): bool { return (bool) preg_match('/tripadvisor\.com/i', $url); }

    protected function doScrape(string $url): array
    {
        $crawler = $this->fetchWithSelenium($url, 6);
        $html = $crawler->html();
        $items = $this->extractJsonLd($crawler);
        if (empty($items)) $items = $this->extractFromDom($crawler);
        return $this->success($items, $this->getInfo($crawler));
    }

    private function extractJsonLd(Crawler $c): array
    {
        $items = [];
        try {
            $c->filter('script[type="application/ld+json"]')->each(function(Crawler $s) use (&$items) {
                $d = json_decode($s->text(), true);
                if (!$d) return;
                if (isset($d['hasMenu'])) $this->walkMenu($d['hasMenu'], $items, null);
                $this->walkMenu($d, $items, null);
            });
        } catch (\Exception $e) {}
        return $items;
    }

    private function walkMenu(array $d, array &$items, ?string $cat): void
    {
        $t = $d['@type'] ?? '';
        if (in_array($t, ['MenuItem','Product'])) {
            $p = isset($d['offers']['price']) ? (float)$d['offers']['price'] : null;
            $items[] = $this->makeItem($d['name'] ?? 'Unknown', $p, $d['description'] ?? null, $cat);
        }
        if (in_array($t, ['Menu','MenuSection'])) {
            foreach ($d['hasMenuSection'] ?? $d['hasMenuItem'] ?? [] as $c2) {
                if (is_array($c2)) $this->walkMenu($c2, $items, $d['name'] ?? $cat);
            }
        }
        if (isset($d['@graph'])) { foreach ($d['@graph'] as $n) $this->walkMenu($n, $items, $cat); }
    }

    private function extractFromDom(Crawler $c): array
    {
        $items = [];
        foreach (['.menu-item','[class*="menuItem"]','[data-test-target*="menu"]','.item'] as $s) {
            try { $c->filter($s)->each(function(Crawler $n) use (&$items) { $name=null; foreach(['h3','h4','.name','strong','span'] as $ns) { try{$x=$n->filter($ns)->first();if($x->count()){$t=trim($x->text());if(strlen($t)>=2&&strlen($t)<200){$name=$t;break;}}}catch(\Exception $e){continue;} } $price=$this->extractPrice($n->text()); if($name) $items[]=$this->makeItem($name,$price); }); if(!empty($items)) break; } catch(\Exception $e){continue;}
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
        try{$this->http->head('https://www.tripadvisor.com',['timeout'=>10]);return['healthy'=>true,'message'=>'TripAdvisor reachable'];}catch(\Exception $e){return['healthy'=>false,'message'=>$e->getMessage()];}
    }
}
