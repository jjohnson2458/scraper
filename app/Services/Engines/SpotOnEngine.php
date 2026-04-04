<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * SpotOn Engine
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class SpotOnEngine extends AbstractEngine
{
    public function getPlatformSlug(): string { return 'spoton'; }
    public function canHandle(string $url): bool { return (bool) preg_match('/spoton\.com/i', $url); }

    protected function doScrape(string $url): array
    {
        try { $crawler = $this->fetchDom($url); } catch (\Exception $e) { $crawler = $this->fetchWithSelenium($url, 5); }
        $items = $this->extractFromDom($crawler);
        return $this->success($items, $this->getInfo($crawler));
    }

    private function extractFromDom(Crawler $c): array
    {
        $items = [];
        foreach (['.menu-item','[class*="menu-item"]','[class*="MenuItem"]','.item-card','.product'] as $s) {
            try { $c->filter($s)->each(function(Crawler $n) use (&$items) { $name=null; foreach(['h3','h4','.name','.item-name','strong'] as $ns) { try{$x=$n->filter($ns)->first();if($x->count()){$name=trim($x->text());break;}}catch(\Exception $e){continue;} } $price=$this->extractPrice($n->text()); $desc=null; try{$d=$n->filter('p,.description,[class*="desc"]')->first();if($d->count())$desc=trim($d->text());}catch(\Exception $e){} if($name&&strlen($name)>=2) $items[]=$this->makeItem($name,$price,$desc); }); if(!empty($items)) break; } catch(\Exception $e){continue;}
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
        try{$this->http->head('https://www.spoton.com',['timeout'=>10]);return['healthy'=>true,'message'=>'SpotOn reachable'];}catch(\Exception $e){return['healthy'=>false,'message'=>$e->getMessage()];}
    }
}
