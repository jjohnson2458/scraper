<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Allmenus Engine
 *
 * Scrapes from allmenus.com — an aggregated menu database.
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class AllmenusEngine extends AbstractEngine
{
    public function getPlatformSlug(): string { return 'allmenus'; }
    public function canHandle(string $url): bool { return (bool) preg_match('/allmenus\.com/i', $url); }

    protected function doScrape(string $url): array
    {
        try { $crawler = $this->fetchDom($url); } catch (\Exception $e) { return $this->success([], [], "Site blocked the request. Try a different platform link for this restaurant."); }
        $items = $this->extractFromDom($crawler);
        return $this->success($items, $this->getInfo($crawler));
    }

    private function extractFromDom(Crawler $c): array
    {
        $items = [];
        $currentCategory = null;

        // Allmenus uses section-based layout
        foreach (['.menu-section','[class*="menu-section"]','.category-section'] as $catSel) {
            try {
                $c->filter($catSel)->each(function(Crawler $sec) use (&$items) {
                    $catName = null;
                    foreach (['h2','h3','.section-title','.category-name'] as $hs) {
                        try{$h=$sec->filter($hs)->first();if($h->count()){$catName=trim($h->text());break;}}catch(\Exception $e){continue;}
                    }
                    foreach (['.menu-item','.item','li'] as $iSel) {
                        try { $sec->filter($iSel)->each(function(Crawler $n) use (&$items,$catName) {
                            $name=null;
                            foreach(['h4','h5','.item-name','.name','strong','span.title'] as $ns) { try{$x=$n->filter($ns)->first();if($x->count()){$name=trim($x->text());break;}}catch(\Exception $e){continue;} }
                            $price=$this->extractPrice($n->text());
                            $desc=null; try{$d=$n->filter('p,.description,.desc')->first();if($d->count())$desc=trim($d->text());}catch(\Exception $e){}
                            if($name&&strlen($name)>=2) $items[]=$this->makeItem($name,$price,$desc,$catName);
                        }); } catch(\Exception $e){continue;}
                    }
                });
                if (!empty($items)) break;
            } catch(\Exception $e){continue;}
        }

        // Flat fallback
        if (empty($items)) {
            foreach (['.menu-item','[class*="menu-item"]','.item'] as $s) {
                try { $c->filter($s)->each(function(Crawler $n) use (&$items) { $name=null; foreach(['h3','h4','.name','strong'] as $ns) { try{$x=$n->filter($ns)->first();if($x->count()){$name=trim($x->text());break;}}catch(\Exception $e){continue;} } $price=$this->extractPrice($n->text()); if($name&&strlen($name)>=2) $items[]=$this->makeItem($name,$price); }); if(!empty($items)) break; } catch(\Exception $e){continue;}
            }
        }
        return $items;
    }

    private function getInfo(Crawler $c): array
    {
        $i=['name'=>null,'address'=>null,'phone'=>null,'logo_url'=>null,'banner_url'=>null];
        try{$h=$c->filter('h1')->first();if($h->count())$i['name']=trim($h->text());}catch(\Exception $e){}
        return $i;
    }

    public function healthCheck(): array
    {
        try{$this->http->head('https://www.allmenus.com',['timeout'=>10]);return['healthy'=>true,'message'=>'Allmenus reachable'];}catch(\Exception $e){return['healthy'=>false,'message'=>$e->getMessage()];}
    }
}
