<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Generic Scraping Engine
 *
 * Fallback engine for unrecognized URLs. Uses Guzzle + DomCrawler
 * with heuristic-based menu detection. Falls back to Selenium
 * if Cloudflare or bot protection is detected.
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class GenericEngine extends AbstractEngine
{
    /** @var array CSS selectors commonly used for menu items */
    private const MENU_SELECTORS = [
        '.menu', '#menu', '[class*="menu"]',
        '.food-menu', '.restaurant-menu',
        '.menu-item', '.food-item', '.dish', '.product',
        '[class*="menu-item"]', '[class*="food-item"]',
        '.menu-list li', '.menu-section', '.menu-category',
        'article', '.card', '.item',
    ];

    /** @var array Cloudflare / bot protection indicators */
    private const BOT_INDICATORS = [
        'Just a moment...', 'Checking your browser', 'cf-browser-verification',
        'challenge-platform', '_cf_chl', 'Attention Required',
        'Access denied', 'Please enable JavaScript', 'DDoS protection by',
    ];

    /**
     * {@inheritdoc}
     */
    public function getPlatformSlug(): string
    {
        return 'generic';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandle(string $url): bool
    {
        return true; // Fallback — handles anything
    }

    /**
     * {@inheritdoc}
     */
    protected function doScrape(string $url): array
    {
        // Guzzle only — no Selenium on production (1GB RAM limit)
        try {
            $response = $this->http->get($url);
            $html = (string) $response->getBody();

            if ($this->isBotProtected($html)) {
                return $this->success([], [], 'This site has bot protection (Cloudflare). Try finding this restaurant on DoorDash, Grubhub, or Yelp instead.');
            }

            return $this->parseHtml($html, $url);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $code = $e->getResponse()->getStatusCode();
            return $this->success([], [], "Site returned HTTP {$code}. The site may be blocking automated access. Try a different platform link for this restaurant.");
        }
    }

    /**
     * Check if HTML looks like a bot protection page.
     *
     * @param string $html The HTML content.
     * @return bool
     */
    private function isBotProtected(string $html): bool
    {
        foreach (self::BOT_INDICATORS as $indicator) {
            if (stripos($html, $indicator) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Parse HTML and extract menu items.
     *
     * @param string $html The HTML content.
     * @param string $url  The source URL.
     * @return array
     */
    private function parseHtml(string $html, string $url): array
    {
        $crawler = new Crawler($html, $url);

        // Try structured data first
        $items = $this->extractStructuredData($crawler);

        // Fall back to DOM heuristics
        if (empty($items)) {
            $items = $this->extractFromDom($crawler);
        }

        // Extract restaurant info
        $restaurant = $this->extractRestaurantInfo($crawler);

        // Enrich with images
        $items = $this->enrichWithImages($crawler, $items, $url);

        return $this->success($items, $restaurant);
    }

    /**
     * Extract from JSON-LD structured data.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array
     */
    private function extractStructuredData(Crawler $crawler): array
    {
        $items = [];
        try {
            $crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $script) use (&$items) {
                $data = json_decode($script->text(), true);
                if ($data) {
                    $this->walkJsonLd($data, $items, null);
                }
            });
        } catch (\Exception $e) {}
        return $items;
    }

    /**
     * Recursively walk JSON-LD data for menu items.
     *
     * @param array       $data     The JSON data.
     * @param array       &$items   Items collection.
     * @param string|null $category Current category.
     * @return void
     */
    private function walkJsonLd(array $data, array &$items, ?string $category): void
    {
        if (isset($data['@graph'])) {
            foreach ($data['@graph'] as $node) {
                $this->walkJsonLd($node, $items, $category);
            }
            return;
        }

        $type = $data['@type'] ?? '';

        if (in_array($type, ['MenuItem', 'Product'])) {
            $price = isset($data['offers']['price']) ? (float) $data['offers']['price'] : null;
            if ($price === null && isset($data['price'])) {
                $price = (float) $data['price'];
            }
            $items[] = $this->makeItem(
                $data['name'] ?? 'Unknown',
                $price,
                $data['description'] ?? null,
                $category,
                is_array($data['image'] ?? null) ? ($data['image'][0] ?? null) : ($data['image'] ?? null)
            );
        }

        if (in_array($type, ['Menu', 'MenuSection'])) {
            $cat = $data['name'] ?? $category;
            foreach ($data['hasMenuItem'] ?? $data['hasMenuSection'] ?? [] as $child) {
                if (is_array($child)) {
                    $this->walkJsonLd($child, $items, $cat);
                }
            }
        }
    }

    /**
     * Extract items from DOM using heuristic selectors.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array
     */
    private function extractFromDom(Crawler $crawler): array
    {
        $items = [];
        $bestSelector = null;
        $maxScore = 0;

        foreach (self::MENU_SELECTORS as $selector) {
            try {
                $nodes = $crawler->filter($selector);
                $count = $nodes->count();
                if ($count < 2 || $count > 200) continue;

                $priceCount = 0;
                $nodes->each(function (Crawler $node) use (&$priceCount) {
                    if ($this->extractPrice($node->text())) {
                        $priceCount++;
                    }
                });

                $score = $priceCount * 2 + $count;
                if ($score > $maxScore) {
                    $maxScore = $score;
                    $bestSelector = $selector;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if ($bestSelector) {
            try {
                $crawler->filter($bestSelector)->each(function (Crawler $node) use (&$items) {
                    $item = $this->parseItemNode($node);
                    if ($item) $items[] = $item;
                });
            } catch (\Exception $e) {}
        }

        // Fallback: scan for price patterns in body text
        if (empty($items)) {
            $items = $this->extractByPricePatterns($crawler);
        }

        return $items;
    }

    /**
     * Parse a single DOM node into an item.
     *
     * @param Crawler $node The DOM node.
     * @return array|null
     */
    private function parseItemNode(Crawler $node): ?array
    {
        $text = trim($node->text());
        if (empty($text) || strlen($text) < 3) return null;

        $name = null;
        foreach (['h1','h2','h3','h4','h5','h6','.name','.title','strong','b'] as $sel) {
            try {
                $n = $node->filter($sel)->first();
                if ($n->count()) { $name = trim($n->text()); break; }
            } catch (\Exception $e) { continue; }
        }

        if (!$name) {
            $lines = preg_split('/[\n\r]+/', $text);
            $name = preg_replace('/\$\s*\d+(?:\.\d{2})?/', '', trim($lines[0] ?? ''));
            $name = trim($name, " \t\n\r\0\x0B-.");
        }

        if (strlen($name) < 2) return null;

        $price = $this->extractPrice($text);
        $description = null;
        $imageUrl = null;

        try {
            $descNode = $node->filter('p, .description, [class*="desc"]')->first();
            if ($descNode->count()) $description = trim($descNode->text());
        } catch (\Exception $e) {}

        try {
            $imgNode = $node->filter('img')->first();
            if ($imgNode->count()) $imageUrl = $imgNode->attr('src') ?: $imgNode->attr('data-src');
        } catch (\Exception $e) {}

        return $this->makeItem($name, $price, $description, null, $imageUrl, ['raw_text' => $text]);
    }

    /**
     * Scan body text for lines with price patterns.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array
     */
    private function extractByPricePatterns(Crawler $crawler): array
    {
        $items = [];
        try {
            $text = $crawler->filter('body')->text();
            foreach (preg_split('/[\n\r]+/', $text) as $line) {
                $line = trim($line);
                $price = $this->extractPrice($line);
                if ($price) {
                    $name = preg_replace('/\$\s*\d+(?:\.\d{2})?/', '', $line);
                    $name = trim($name, " \t\n\r\0\x0B-.");
                    if (strlen($name) >= 2) {
                        $items[] = $this->makeItem($name, $price, null, null, null, ['raw_text' => $line]);
                    }
                }
            }
        } catch (\Exception $e) {}
        return $items;
    }

    /**
     * Extract restaurant metadata.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array
     */
    private function extractRestaurantInfo(Crawler $crawler): array
    {
        $info = ['name' => null, 'address' => null, 'phone' => null, 'logo_url' => null, 'banner_url' => null];

        foreach (['h1', 'meta[property="og:title"]'] as $sel) {
            try {
                $node = $crawler->filter($sel)->first();
                if ($node->count()) {
                    $info['name'] = $sel === 'h1' ? trim($node->text()) : $node->attr('content');
                    if ($info['name']) break;
                }
            } catch (\Exception $e) { continue; }
        }

        try {
            $og = $crawler->filter('meta[property="og:image"]')->first();
            if ($og->count()) $info['banner_url'] = $og->attr('content');
        } catch (\Exception $e) {}

        return $info;
    }

    /**
     * Try to match images to items.
     *
     * @param Crawler $crawler The DOM crawler.
     * @param array   $items   The items.
     * @param string  $baseUrl The base URL.
     * @return array
     */
    private function enrichWithImages(Crawler $crawler, array $items, string $baseUrl): array
    {
        $images = [];
        try {
            $crawler->filter('img')->each(function (Crawler $img) use (&$images, $baseUrl) {
                $src = $img->attr('src') ?: $img->attr('data-src');
                if ($src) {
                    if (!str_starts_with($src, 'http')) {
                        $parsed = parse_url($baseUrl);
                        $src = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . '/' . ltrim($src, '/');
                    }
                    $images[] = ['src' => $src, 'alt' => strtolower($img->attr('alt') ?? '')];
                }
            });
        } catch (\Exception $e) {}

        foreach ($items as &$item) {
            if (!empty($item['image_url'])) continue;
            $name = strtolower($item['name'] ?? '');
            foreach ($images as $img) {
                if (!empty($img['alt']) && (str_contains($img['alt'], $name) || str_contains($name, $img['alt']))) {
                    $item['image_url'] = $img['src'];
                    break;
                }
            }
        }

        return $items;
    }
}
