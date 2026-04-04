<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Uber Eats Engine
 *
 * Scrapes restaurant menus from Uber Eats (ubereats.com).
 * Uber Eats uses a GraphQL backend with menu data embedded
 * in the page's hydration state.
 *
 * Also handles Postmates URLs (same backend).
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class UberEatsEngine extends AbstractEngine
{
    /**
     * {@inheritdoc}
     */
    public function getPlatformSlug(): string
    {
        return 'ubereats';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandle(string $url): bool
    {
        return (bool) preg_match('/ubereats\.com|postmates\.com/i', $url);
    }

    /**
     * {@inheritdoc}
     */
    protected function doScrape(string $url): array
    {
        $crawler = $this->fetchWithSelenium($url, 6);
        $html = $crawler->html();

        $items = $this->extractFromHydrationState($html);

        if (empty($items)) {
            $items = $this->extractFromDom($crawler);
        }

        $restaurant = $this->extractRestaurantInfo($crawler);

        return $this->success($items, $restaurant);
    }

    /**
     * Extract menu data from Uber Eats' hydration/Redux state.
     *
     * @param string $html The page HTML.
     * @return array
     */
    private function extractFromHydrationState(string $html): array
    {
        $items = [];

        // Uber Eats embeds data in various script patterns
        $patterns = [
            '/"sections"\s*:\s*(\[.*?\])\s*,"catalogSectionsMap"/s',
            '/"subsectionsMap"\s*:\s*(\{.*?\})\s*[,}]/s',
            '/"menuDisplayItems"\s*:\s*(\{.*?\})\s*[,}]/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $data = json_decode($matches[1], true);
                if (is_array($data)) {
                    $this->parseUberData($data, $items);
                    if (!empty($items)) return $items;
                }
            }
        }

        // Try to find any JSON with menu-like structure
        if (preg_match_all('/"title"\s*:\s*"([^"]+)"[^}]*"price"\s*:\s*(\{[^}]*\}|\d+)/s', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = $m[1];
                $priceData = $m[2];
                $price = null;

                if (is_numeric($priceData)) {
                    $price = (float) $priceData / 100;
                } elseif (preg_match('/"unitAmount"\s*:\s*(\d+)/', $priceData, $pm)) {
                    $price = (float) $pm[1] / 100;
                }

                if ($name && strlen($name) >= 2 && strlen($name) < 200) {
                    $items[] = $this->makeItem($name, $price);
                }
            }
        }

        return $items;
    }

    /**
     * Parse Uber Eats data structures.
     *
     * @param array $data   The data to parse.
     * @param array &$items Items collection.
     * @return void
     */
    private function parseUberData(array $data, array &$items): void
    {
        foreach ($data as $section) {
            if (!is_array($section)) continue;

            $category = $section['title'] ?? $section['name'] ?? null;
            $sectionItems = $section['itemUuids'] ?? $section['items'] ?? $section['subsections'] ?? [];

            foreach ($sectionItems as $item) {
                if (!is_array($item)) continue;

                $name = $item['title'] ?? $item['name'] ?? null;
                if (!$name) {
                    // May be a subsection — recurse
                    $this->parseUberData([$item], $items);
                    continue;
                }

                $price = null;
                if (isset($item['price']['unitAmount'])) {
                    $price = (float) $item['price']['unitAmount'] / 100;
                } elseif (isset($item['price'])) {
                    $price = is_numeric($item['price']) ? (float) $item['price'] / 100 : $this->extractPrice((string) $item['price']);
                }

                $items[] = $this->makeItem(
                    $name,
                    $price,
                    $item['description'] ?? $item['itemDescription'] ?? null,
                    $category,
                    $item['imageUrl'] ?? $item['image'] ?? null,
                    [
                        'external_id' => $item['uuid'] ?? $item['id'] ?? null,
                        'calories' => $item['nutritionalInfo']['calories'] ?? null,
                    ]
                );
            }
        }
    }

    /**
     * DOM fallback extraction.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array
     */
    private function extractFromDom(Crawler $crawler): array
    {
        $items = [];
        $selectors = ['[data-testid*="store-item"]', '[class*="StoreItem"]', 'li[class*="menu"]'];

        foreach ($selectors as $selector) {
            try {
                $crawler->filter($selector)->each(function (Crawler $node) use (&$items) {
                    $name = null;
                    foreach (['h3', 'h4', 'span[class*="title"]'] as $ns) {
                        try { $n = $node->filter($ns)->first(); if ($n->count()) { $name = trim($n->text()); break; } } catch (\Exception $e) { continue; }
                    }
                    $price = $this->extractPrice($node->text());
                    if ($name && strlen($name) >= 2) $items[] = $this->makeItem($name, $price);
                });
                if (!empty($items)) break;
            } catch (\Exception $e) { continue; }
        }

        return $items;
    }

    /**
     * Extract restaurant info.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array
     */
    private function extractRestaurantInfo(Crawler $crawler): array
    {
        $info = ['name' => null, 'address' => null, 'phone' => null, 'logo_url' => null, 'banner_url' => null];
        try { $h1 = $crawler->filter('h1')->first(); if ($h1->count()) $info['name'] = trim($h1->text()); } catch (\Exception $e) {}
        try { $og = $crawler->filter('meta[property="og:image"]')->first(); if ($og->count()) $info['banner_url'] = $og->attr('content'); } catch (\Exception $e) {}
        return $info;
    }

    /**
     * {@inheritdoc}
     */
    public function healthCheck(): array
    {
        try { $this->http->head('https://www.ubereats.com', ['timeout' => 10]); return ['healthy' => true, 'message' => 'Uber Eats reachable']; }
        catch (\Exception $e) { return ['healthy' => false, 'message' => $e->getMessage()]; }
    }
}
