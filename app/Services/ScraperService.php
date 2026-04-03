<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DomCrawler\Crawler;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use App\Models\ErrorLog;

/**
 * Scraper Service
 *
 * Handles web scraping of restaurant menus and inventory pages.
 * Uses Guzzle for HTTP requests and Symfony DomCrawler for HTML parsing.
 *
 * @package    ClaudeScraper
 * @subpackage Services
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class ScraperService
{
    /** @var Client Guzzle HTTP client */
    private Client $httpClient;

    /** @var ErrorLog Error logger */
    private ErrorLog $errorLog;

    /** @var array Common menu-related CSS selectors to try */
    private const MENU_SELECTORS = [
        // Common menu containers
        '.menu', '#menu', '[class*="menu"]',
        '.food-menu', '.restaurant-menu', '.dinner-menu',
        // Item-level selectors
        '.menu-item', '.food-item', '.dish', '.product',
        '[class*="menu-item"]', '[class*="food-item"]',
        // Price patterns
        '.price', '.cost', '.amount', '[class*="price"]',
        // List-based menus
        '.menu-list li', '.menu-section', '.menu-category',
        // Generic containers that often hold menus
        'article', '.card', '.item', '.entry',
    ];

    /** @var array Price regex patterns */
    private const PRICE_PATTERNS = [
        '/\$\s*(\d+(?:\.\d{2})?)/u',             // $12.99
        '/(\d+(?:\.\d{2})?)\s*(?:USD|dollars?)/iu', // 12.99 USD
        '/(?:Price|Cost)[\s:]*\$?(\d+(?:\.\d{2})?)/iu',
    ];

    /**
     * ScraperService constructor.
     */
    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ],
            'verify' => false,
        ]);
        $this->errorLog = new ErrorLog();
    }

    /**
     * Scrape a URL and extract menu items.
     *
     * @param string $url The target URL to scrape.
     * @return array{success: bool, items: array, title: string|null, error: string|null}
     */
    public function scrapeUrl(string $url): array
    {
        // Try Guzzle first (fast, no browser needed)
        try {
            $response = $this->httpClient->get($url);
            $html = (string) $response->getBody();

            // Detect Cloudflare/bot protection pages
            if ($this->isBotProtectionPage($html)) {
                return $this->scrapeWithSelenium($url);
            }

            return $this->parseHtml($html, $url);

        } catch (ClientException $e) {
            $code = $e->getResponse()->getStatusCode();
            // 403 Forbidden or 503 Service Unavailable = likely bot protection
            if (in_array($code, [403, 503])) {
                $this->errorLog->log("HTTP {$code} from Guzzle, falling back to Selenium", 'info', ['url' => $url]);
                return $this->scrapeWithSelenium($url);
            }
            $this->errorLog->log($e->getMessage(), 'error', ['url' => $url]);
            return ['success' => false, 'items' => [], 'title' => null, 'error' => "HTTP {$code}: Site blocked the request. Selenium fallback also failed or is unavailable."];

        } catch (\Exception $e) {
            $this->errorLog->log($e->getMessage(), 'error', ['url' => $url]);
            return ['success' => false, 'items' => [], 'title' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if the HTML looks like a bot protection / challenge page.
     *
     * @param string $html The response HTML.
     * @return bool
     */
    private function isBotProtectionPage(string $html): bool
    {
        $indicators = [
            'Just a moment...',
            'Checking your browser',
            'cf-browser-verification',
            'challenge-platform',
            '_cf_chl',
            'Attention Required',
            'Access denied',
            'Please enable JavaScript',
            'DDoS protection by',
        ];

        foreach ($indicators as $indicator) {
            if (stripos($html, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse HTML content into structured menu items.
     *
     * @param string $html The HTML content.
     * @param string $url  The source URL.
     * @return array
     */
    private function parseHtml(string $html, string $url): array
    {
        $crawler = new Crawler($html, $url);

        $title = $this->extractTitle($crawler);
        $items = $this->extractStructuredData($crawler);

        if (empty($items)) {
            $items = $this->extractFromDom($crawler, $url);
        }

        $items = $this->enrichWithImages($crawler, $items, $url);

        return [
            'success' => true,
            'items' => $items,
            'title' => $title,
            'error' => null,
            'source_html' => mb_substr($html, 0, 50000),
        ];
    }

    /**
     * Scrape a URL using Selenium WebDriver (for JS-rendered or bot-protected pages).
     *
     * Falls back gracefully if Selenium/ChromeDriver is not available.
     *
     * @param string $url The target URL.
     * @return array
     */
    private function scrapeWithSelenium(string $url): array
    {
        $driver = null;
        try {
            $options = new ChromeOptions();
            $options->addArguments([
                '--headless',
                '--disable-gpu',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--window-size=1920,1080',
                '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ]);

            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

            // Try common Selenium server URLs
            $seleniumUrls = [
                'http://localhost:4444/wd/hub',
                'http://localhost:4444',
                'http://localhost:9515',  // ChromeDriver standalone
            ];

            $driver = null;
            foreach ($seleniumUrls as $seleniumUrl) {
                try {
                    $driver = RemoteWebDriver::create($seleniumUrl, $capabilities, 10000, 10000);
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$driver) {
                return [
                    'success' => false,
                    'items' => [],
                    'title' => null,
                    'error' => 'Site requires a browser to load (bot protection detected). Selenium WebDriver is not running. Start ChromeDriver or Selenium Server and try again.',
                ];
            }

            $driver->get($url);

            // Wait for page to load (JS rendering, Cloudflare challenge)
            sleep(5);

            // Check if still on a challenge page, wait longer
            $pageSource = $driver->getPageSource();
            if ($this->isBotProtectionPage($pageSource)) {
                sleep(10);
                $pageSource = $driver->getPageSource();
            }

            // If still blocked after waiting
            if ($this->isBotProtectionPage($pageSource)) {
                $driver->quit();
                return [
                    'success' => false,
                    'items' => [],
                    'title' => null,
                    'error' => 'Site bot protection could not be bypassed even with a browser. Try taking a photo of the menu instead.',
                ];
            }

            $result = $this->parseHtml($pageSource, $url);
            $driver->quit();
            return $result;

        } catch (\Exception $e) {
            if ($driver) {
                try { $driver->quit(); } catch (\Exception $ignored) {}
            }
            $this->errorLog->log('Selenium scrape failed: ' . $e->getMessage(), 'error', ['url' => $url]);
            return [
                'success' => false,
                'items' => [],
                'title' => null,
                'error' => 'Browser-based scraping failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Extract the page title.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return string|null
     */
    private function extractTitle(Crawler $crawler): ?string
    {
        // Try common restaurant name patterns
        $selectors = ['h1', '.restaurant-name', '.business-name', '[class*="restaurant"]', 'title'];
        foreach ($selectors as $selector) {
            try {
                $node = $crawler->filter($selector)->first();
                if ($node->count()) {
                    $text = trim($node->text());
                    if (!empty($text) && strlen($text) < 200) {
                        return $text;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return null;
    }

    /**
     * Extract menu items from JSON-LD or Microdata structured data.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array Extracted items.
     */
    private function extractStructuredData(Crawler $crawler): array
    {
        $items = [];

        // Try JSON-LD
        try {
            $scripts = $crawler->filter('script[type="application/ld+json"]');
            $scripts->each(function (Crawler $script) use (&$items) {
                $data = json_decode($script->text(), true);
                if (!$data) {
                    return;
                }

                $this->parseJsonLdItems($data, $items);
            });
        } catch (\Exception $e) {
            // Silently continue
        }

        return $items;
    }

    /**
     * Parse JSON-LD data recursively for menu items.
     *
     * @param array $data  The JSON-LD data.
     * @param array &$items Collection of extracted items.
     * @return void
     */
    private function parseJsonLdItems(array $data, array &$items): void
    {
        // Handle @graph arrays
        if (isset($data['@graph'])) {
            foreach ($data['@graph'] as $node) {
                $this->parseJsonLdItems($node, $items);
            }
            return;
        }

        $type = $data['@type'] ?? '';

        // Menu items
        if (in_array($type, ['MenuItem', 'Product', 'Offer'])) {
            $item = [
                'name' => $data['name'] ?? 'Unknown Item',
                'description' => $data['description'] ?? null,
                'price' => null,
                'category' => null,
                'image_url' => null,
            ];

            // Extract price
            if (isset($data['offers']['price'])) {
                $item['price'] = (float) $data['offers']['price'];
            } elseif (isset($data['price'])) {
                $item['price'] = (float) $data['price'];
            }

            // Extract image
            if (isset($data['image'])) {
                $item['image_url'] = is_array($data['image']) ? ($data['image'][0] ?? null) : $data['image'];
            }

            $items[] = $item;
        }

        // Menu sections
        if ($type === 'MenuSection' || $type === 'Menu') {
            $category = $data['name'] ?? null;
            $menuItems = $data['hasMenuItem'] ?? $data['hasMenuSection'] ?? [];
            foreach ($menuItems as $menuItem) {
                if (is_array($menuItem)) {
                    $menuItem['_category'] = $category;
                    $this->parseJsonLdItems($menuItem, $items);
                }
            }
        }

        // Apply category from parent
        if (isset($data['_category']) && !empty($items)) {
            $lastIndex = count($items) - 1;
            if ($items[$lastIndex]['category'] === null) {
                $items[$lastIndex]['category'] = $data['_category'];
            }
        }
    }

    /**
     * Extract menu items by analyzing the DOM structure.
     *
     * @param Crawler $crawler The DOM crawler.
     * @param string  $baseUrl The base URL for resolving relative URLs.
     * @return array Extracted items.
     */
    private function extractFromDom(Crawler $crawler, string $baseUrl): array
    {
        $items = [];
        $bestSelector = null;
        $maxItems = 0;

        // Find the best selector that yields menu-like content
        foreach (self::MENU_SELECTORS as $selector) {
            try {
                $nodes = $crawler->filter($selector);
                $count = $nodes->count();
                if ($count > $maxItems && $count < 200) {
                    // Check if nodes look like menu items (have text with potential prices)
                    $hasMenuContent = false;
                    $nodes->each(function (Crawler $node) use (&$hasMenuContent) {
                        $text = $node->text();
                        foreach (self::PRICE_PATTERNS as $pattern) {
                            if (preg_match($pattern, $text)) {
                                $hasMenuContent = true;
                            }
                        }
                    });
                    if ($hasMenuContent || $count >= 3) {
                        $maxItems = $count;
                        $bestSelector = $selector;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if (!$bestSelector) {
            // Fallback: try to find any list items with prices
            return $this->extractByPricePatterns($crawler);
        }

        // Extract items from the best selector
        try {
            $crawler->filter($bestSelector)->each(function (Crawler $node) use (&$items) {
                $item = $this->parseItemNode($node);
                if ($item && !empty($item['name'])) {
                    $items[] = $item;
                }
            });
        } catch (\Exception $e) {
            $this->errorLog->log('DOM extraction failed: ' . $e->getMessage(), 'warning');
        }

        return $items;
    }

    /**
     * Parse a single DOM node into a menu item.
     *
     * @param Crawler $node The DOM node.
     * @return array|null The parsed item or null.
     */
    private function parseItemNode(Crawler $node): ?array
    {
        $text = trim($node->text());
        if (empty($text) || strlen($text) < 3) {
            return null;
        }

        $item = [
            'name' => null,
            'description' => null,
            'price' => null,
            'category' => null,
            'image_url' => null,
            'raw_text' => $text,
        ];

        // Extract price
        foreach (self::PRICE_PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $item['price'] = (float) $matches[1];
                break;
            }
        }

        // Try to find name in heading tags
        $nameSelectors = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', '.name', '.title', 'strong', 'b'];
        foreach ($nameSelectors as $sel) {
            try {
                $nameNode = $node->filter($sel)->first();
                if ($nameNode->count()) {
                    $item['name'] = trim($nameNode->text());
                    break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // If no name found, use first line of text
        if (!$item['name']) {
            $lines = preg_split('/[\n\r]+/', $text);
            $item['name'] = trim($lines[0] ?? '');
            // Remove price from name
            $item['name'] = preg_replace('/\$\s*\d+(?:\.\d{2})?/', '', $item['name']);
            $item['name'] = trim($item['name'], " \t\n\r\0\x0B-.");
        }

        // Try to find description
        try {
            $descNode = $node->filter('p, .description, .desc, [class*="desc"]')->first();
            if ($descNode->count()) {
                $item['description'] = trim($descNode->text());
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Try to find image
        try {
            $imgNode = $node->filter('img')->first();
            if ($imgNode->count()) {
                $src = $imgNode->attr('src') ?: $imgNode->attr('data-src');
                if ($src) {
                    $item['image_url'] = $src;
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Skip items that are too short to be real menu items
        if (strlen($item['name']) < 2) {
            return null;
        }

        return $item;
    }

    /**
     * Fallback extraction: scan the entire page for price patterns.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array Extracted items.
     */
    private function extractByPricePatterns(Crawler $crawler): array
    {
        $items = [];
        $bodyText = '';

        try {
            $bodyText = $crawler->filter('body')->text();
        } catch (\Exception $e) {
            return $items;
        }

        // Split by lines and look for price patterns
        $lines = preg_split('/[\n\r]+/', $bodyText);
        $currentItem = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            foreach (self::PRICE_PATTERNS as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    $name = preg_replace($pattern, '', $line);
                    $name = trim($name, " \t\n\r\0\x0B-.");

                    if (strlen($name) >= 2) {
                        $items[] = [
                            'name' => $name,
                            'description' => null,
                            'price' => (float) $matches[1],
                            'category' => null,
                            'image_url' => null,
                            'raw_text' => $line,
                        ];
                    }
                    break;
                }
            }
        }

        return $items;
    }

    /**
     * Enrich items with images found nearby in the DOM.
     *
     * @param Crawler $crawler The DOM crawler.
     * @param array   $items   The items to enrich.
     * @param string  $baseUrl The base URL.
     * @return array Enriched items.
     */
    private function enrichWithImages(Crawler $crawler, array $items, string $baseUrl): array
    {
        // Collect all images on the page
        $images = [];
        try {
            $crawler->filter('img')->each(function (Crawler $img) use (&$images, $baseUrl) {
                $src = $img->attr('src') ?: $img->attr('data-src') ?: $img->attr('data-lazy-src');
                if ($src) {
                    // Resolve relative URLs
                    if (!str_starts_with($src, 'http')) {
                        $parsed = parse_url($baseUrl);
                        $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
                        $src = rtrim($base, '/') . '/' . ltrim($src, '/');
                    }
                    $alt = $img->attr('alt') ?? '';
                    $images[] = ['src' => $src, 'alt' => strtolower($alt)];
                }
            });
        } catch (\Exception $e) {
            // Ignore
        }

        // Try to match images to items by alt text
        foreach ($items as &$item) {
            if (!empty($item['image_url'])) {
                continue;
            }
            $itemName = strtolower($item['name'] ?? '');
            foreach ($images as $img) {
                if (!empty($img['alt']) && (
                    str_contains($img['alt'], $itemName) ||
                    str_contains($itemName, $img['alt'])
                )) {
                    $item['image_url'] = $img['src'];
                    break;
                }
            }
        }

        return $items;
    }

    /**
     * Download and save an image locally.
     *
     * @param string $imageUrl The remote image URL.
     * @param int    $scanId   The scan ID for organizing files.
     * @return string|null The local path or null on failure.
     */
    public function downloadImage(string $imageUrl, int $scanId): ?string
    {
        try {
            $response = $this->httpClient->get($imageUrl);
            $contentType = $response->getHeaderLine('Content-Type');

            $extensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];

            $ext = $extensions[$contentType] ?? 'jpg';
            $filename = 'scan_' . $scanId . '_' . uniqid() . '.' . $ext;
            $dir = __DIR__ . '/../../public/uploads/scans/' . $scanId;

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $path = $dir . '/' . $filename;
            file_put_contents($path, $response->getBody());

            return '/uploads/scans/' . $scanId . '/' . $filename;
        } catch (\Exception $e) {
            $this->errorLog->log('Image download failed: ' . $e->getMessage(), 'warning', ['url' => $imageUrl]);
            return null;
        }
    }
}
