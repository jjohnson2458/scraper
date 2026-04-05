<?php

namespace App\Services\Engines;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Toast Engine
 *
 * Scrapes restaurant menus from Toast Tab (toasttab.com).
 * Toast uses Cloudflare protection and heavy JS rendering,
 * so this engine uses Selenium for page loading.
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class ToastEngine extends AbstractEngine
{
    /**
     * {@inheritdoc}
     */
    public function getPlatformSlug(): string
    {
        return 'toast';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandle(string $url): bool
    {
        return (bool) preg_match('/toasttab\.com|toast\.restaurant/i', $url);
    }

    /**
     * {@inheritdoc}
     */
    protected function doScrape(string $url): array
    {
        // Toast uses Cloudflare Turnstile — try Selenium with stealth
        try {
            $crawler = $this->fetchWithSelenium($url, 8);
        } catch (\RuntimeException $e) {
            // Selenium not available — try screenshot+OCR
            return $this->scrapeViaScreenshot($url, 8);
        }

        $html = $crawler->html();

        // Check if Cloudflare blocked us — fall back to screenshot+OCR
        if ($this->isCloudflareBlocked($html)) {
            return $this->scrapeViaScreenshot($url, 8);
        }

        // Try to extract JSON data from script tags (Toast embeds menu data)
        $items = $this->extractFromToastJson($crawler);

        // Fall back to DOM parsing
        if (empty($items)) {
            $items = $this->extractFromToastDom($crawler);
        }

        // If still nothing, try screenshot+OCR as last resort
        if (empty($items)) {
            return $this->scrapeViaScreenshot($url, 5);
        }

        // Extract restaurant info
        $restaurant = $this->extractRestaurantInfo($crawler, $url);

        return $this->success($items, $restaurant);
    }

    /**
     * Try to extract menu data from Toast's embedded JSON.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array Extracted items.
     */
    private function extractFromToastJson(Crawler $crawler): array
    {
        $items = [];

        try {
            // Toast sometimes embeds menu data in __NEXT_DATA__ or similar
            $scripts = $crawler->filter('script');
            $scripts->each(function (Crawler $script) use (&$items) {
                $text = $script->text();

                // Look for JSON-LD structured data
                if ($script->attr('type') === 'application/ld+json') {
                    $data = json_decode($text, true);
                    if ($data) {
                        $this->parseJsonLd($data, $items);
                    }
                    return;
                }

                // Look for __NEXT_DATA__ or similar hydration data
                if (str_contains($text, '"menuGroups"') || str_contains($text, '"menuItems"')) {
                    // Try to extract JSON from the script content
                    if (preg_match('/\{.*"menu(?:Groups|Items)".*\}/s', $text, $matches)) {
                        $data = json_decode($matches[0], true);
                        if ($data) {
                            $this->parseToastMenuData($data, $items);
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            // Silently fall through to DOM parsing
        }

        return $items;
    }

    /**
     * Parse JSON-LD menu data.
     *
     * @param array $data  The JSON-LD data.
     * @param array &$items The items collection.
     * @return void
     */
    private function parseJsonLd(array $data, array &$items): void
    {
        $type = $data['@type'] ?? '';

        if ($type === 'Menu' || $type === 'MenuSection') {
            $category = $data['name'] ?? null;
            $menuItems = $data['hasMenuItem'] ?? $data['hasMenuSection'] ?? [];
            foreach ($menuItems as $mi) {
                if (is_array($mi)) {
                    if (($mi['@type'] ?? '') === 'MenuItem') {
                        $price = null;
                        if (isset($mi['offers']['price'])) {
                            $price = (float) $mi['offers']['price'];
                        }
                        $items[] = $this->makeItem(
                            $mi['name'] ?? 'Unknown',
                            $price,
                            $mi['description'] ?? null,
                            $category,
                            is_array($mi['image'] ?? null) ? ($mi['image'][0] ?? null) : ($mi['image'] ?? null)
                        );
                    } else {
                        $this->parseJsonLd($mi, $items);
                    }
                }
            }
        }

        if (isset($data['@graph'])) {
            foreach ($data['@graph'] as $node) {
                $this->parseJsonLd($node, $items);
            }
        }
    }

    /**
     * Parse Toast's internal menu data structure.
     *
     * @param array $data  The menu data.
     * @param array &$items The items collection.
     * @return void
     */
    private function parseToastMenuData(array $data, array &$items): void
    {
        $groups = $data['menuGroups'] ?? $data['groups'] ?? [];
        foreach ($groups as $group) {
            $category = $group['name'] ?? null;
            $groupItems = $group['menuItems'] ?? $group['items'] ?? [];
            foreach ($groupItems as $mi) {
                $price = $mi['price'] ?? $mi['basePrice'] ?? null;
                if ($price !== null) {
                    $price = (float) $price;
                }

                $modifiers = [];
                foreach ($mi['modifierGroups'] ?? $mi['optionGroups'] ?? [] as $mg) {
                    $groupName = $mg['name'] ?? 'Options';
                    foreach ($mg['modifiers'] ?? $mg['options'] ?? [] as $mod) {
                        $modifiers[] = [
                            'group_name' => $groupName,
                            'option_name' => $mod['name'] ?? '',
                            'price_adjustment' => (float) ($mod['price'] ?? $mod['priceAdjustment'] ?? 0),
                            'is_default' => (bool) ($mod['isDefault'] ?? false),
                            'is_required' => (bool) ($mg['required'] ?? $mg['isRequired'] ?? false),
                        ];
                    }
                }

                $items[] = $this->makeItem(
                    $mi['name'] ?? 'Unknown',
                    $price,
                    $mi['description'] ?? null,
                    $category,
                    $mi['imageUrl'] ?? $mi['image'] ?? null,
                    [
                        'external_id' => $mi['guid'] ?? $mi['id'] ?? null,
                        'calories' => $mi['calories'] ?? null,
                        'modifiers' => !empty($modifiers) ? $modifiers : null,
                    ]
                );
            }
        }
    }

    /**
     * Extract menu items from Toast's DOM structure.
     *
     * @param Crawler $crawler The DOM crawler.
     * @return array
     */
    private function extractFromToastDom(Crawler $crawler): array
    {
        $items = [];
        $currentCategory = null;

        // Toast uses specific class patterns for menu groups and items
        $selectors = [
            '.menuGroup', '.menu-group', '[class*="MenuGroup"]',
            '.menuSection', '[class*="menuSection"]',
        ];

        foreach ($selectors as $groupSelector) {
            try {
                $groups = $crawler->filter($groupSelector);
                if ($groups->count() === 0) {
                    continue;
                }

                $groups->each(function (Crawler $group) use (&$items) {
                    // Get category name from group header
                    $category = null;
                    $headerSelectors = ['h2', 'h3', '.groupTitle', '[class*="groupTitle"]', '[class*="GroupName"]'];
                    foreach ($headerSelectors as $hs) {
                        try {
                            $header = $group->filter($hs)->first();
                            if ($header->count()) {
                                $category = trim($header->text());
                                break;
                            }
                        } catch (\Exception $e) {
                            continue;
                        }
                    }

                    // Get items within group
                    $itemSelectors = ['.menuItem', '.menu-item', '[class*="MenuItem"]', '[class*="menuItem"]'];
                    foreach ($itemSelectors as $is) {
                        try {
                            $group->filter($is)->each(function (Crawler $itemNode) use (&$items, $category) {
                                $item = $this->parseToastItemNode($itemNode, $category);
                                if ($item) {
                                    $items[] = $item;
                                }
                            });
                            if (!empty($items)) {
                                return;
                            }
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                });

                if (!empty($items)) {
                    break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $items;
    }

    /**
     * Parse a single Toast menu item DOM node.
     *
     * @param Crawler     $node     The item node.
     * @param string|null $category The category name.
     * @return array|null
     */
    private function parseToastItemNode(Crawler $node, ?string $category): ?array
    {
        $text = trim($node->text());
        if (empty($text) || strlen($text) < 3) {
            return null;
        }

        $name = null;
        $description = null;
        $price = null;
        $imageUrl = null;

        // Extract name
        $nameSelectors = ['h3', 'h4', '.itemName', '[class*="itemName"]', '[class*="ItemName"]', 'strong'];
        foreach ($nameSelectors as $ns) {
            try {
                $nameNode = $node->filter($ns)->first();
                if ($nameNode->count()) {
                    $name = trim($nameNode->text());
                    break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if (!$name) {
            return null;
        }

        // Extract price
        $priceSelectors = ['.itemPrice', '[class*="price"]', '[class*="Price"]', 'span'];
        foreach ($priceSelectors as $ps) {
            try {
                $priceNode = $node->filter($ps)->first();
                if ($priceNode->count()) {
                    $price = $this->extractPrice($priceNode->text());
                    if ($price) break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Extract description
        $descSelectors = ['.itemDescription', '[class*="description"]', '[class*="Description"]', 'p'];
        foreach ($descSelectors as $ds) {
            try {
                $descNode = $node->filter($ds)->first();
                if ($descNode->count()) {
                    $desc = trim($descNode->text());
                    if ($desc !== $name && strlen($desc) > 5) {
                        $description = $desc;
                        break;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Extract image
        try {
            $imgNode = $node->filter('img')->first();
            if ($imgNode->count()) {
                $imageUrl = $imgNode->attr('src') ?: $imgNode->attr('data-src');
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return $this->makeItem($name, $price, $description, $category, $imageUrl);
    }

    /**
     * Extract restaurant metadata from the page.
     *
     * @param Crawler $crawler The DOM crawler.
     * @param string  $url     The source URL.
     * @return array
     */
    private function extractRestaurantInfo(Crawler $crawler, string $url): array
    {
        $info = [
            'name' => null,
            'address' => null,
            'phone' => null,
            'logo_url' => null,
            'banner_url' => null,
        ];

        // Name from h1 or og:title
        $nameSelectors = ['h1', '.restaurantName', '[class*="RestaurantName"]', 'meta[property="og:title"]'];
        foreach ($nameSelectors as $ns) {
            try {
                $node = $crawler->filter($ns)->first();
                if ($node->count()) {
                    $info['name'] = $ns === 'meta[property="og:title"]'
                        ? $node->attr('content')
                        : trim($node->text());
                    if ($info['name']) break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Banner/hero image from og:image or large images
        try {
            $ogImage = $crawler->filter('meta[property="og:image"]')->first();
            if ($ogImage->count()) {
                $info['banner_url'] = $ogImage->attr('content');
            }
        } catch (\Exception $e) {}

        // Logo
        try {
            $logo = $crawler->filter('img[class*="logo"], img[alt*="logo"], .logo img')->first();
            if ($logo->count()) {
                $info['logo_url'] = $logo->attr('src') ?: $logo->attr('data-src');
            }
        } catch (\Exception $e) {}

        return $info;
    }

    /**
     * Check if the page is stuck on Cloudflare challenge.
     *
     * @param string $html The page HTML.
     * @return bool
     */
    private function isCloudflareBlocked(string $html): bool
    {
        $indicators = ['Just a moment...', 'Checking your browser', 'cf-browser-verification', '_cf_chl', 'challenge-platform'];
        foreach ($indicators as $ind) {
            if (stripos($html, $ind) !== false) {
                return true;
            }
        }
        // Also check: page has no price data at all
        if (!preg_match('/\$\s*\d+\.\d{2}/', $html) && strlen($html) < 50000) {
            return true;
        }
        return false;
    }

    /**
     * Get a user-friendly message when Toast blocks the scraper.
     *
     * @param string $url The original URL.
     * @return string
     */
    private function getBlockedMessage(string $url): string
    {
        // Extract restaurant name from URL for search suggestions
        $slug = '';
        if (preg_match('/\/online\/([^\/\?]+)/', $url, $m)) {
            $slug = str_replace('-', ' ', $m[1]);
            $slug = preg_replace('/\d+/', '', $slug);
            $slug = trim($slug);
        }

        $msg = 'Toast is blocking automated access (Cloudflare Turnstile). ';
        $msg .= 'Try searching for this restaurant on DoorDash, Uber Eats, or Grubhub instead — ';
        $msg .= 'those platforms have the same menu data without the block.';
        if ($slug) {
            $msg .= " Search for: \"{$slug}\"";
        }
        return $msg;
    }

    /**
     * {@inheritdoc}
     */
    public function healthCheck(): array
    {
        try {
            $response = $this->http->head('https://www.toasttab.com', ['timeout' => 10]);
            return ['healthy' => true, 'message' => 'Toast reachable (CF may block scraping)'];
        } catch (\Exception $e) {
            return ['healthy' => false, 'message' => $e->getMessage()];
        }
    }
}
