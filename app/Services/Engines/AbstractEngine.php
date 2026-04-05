<?php

namespace App\Services\Engines;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\ErrorLog;

/**
 * Abstract Scraping Engine
 *
 * Provides shared functionality for all platform engines:
 * HTTP client, DOM parsing, timing, error logging, and
 * common extraction helpers.
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
abstract class AbstractEngine implements EngineInterface
{
    /** @var Client HTTP client */
    protected Client $http;

    /** @var ErrorLog Error logger */
    protected ErrorLog $errorLog;

    /** @var array Common price regex patterns */
    protected const PRICE_PATTERNS = [
        '/\$\s*(\d+(?:\.\d{2})?)/u',
        '/(\d+(?:\.\d{2})?)\s*(?:USD|dollars?)/iu',
    ];

    /**
     * AbstractEngine constructor.
     */
    public function __construct()
    {
        $this->http = new Client([
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
     * {@inheritdoc}
     */
    public function scrape(string $url): array
    {
        $startTime = microtime(true);

        try {
            $result = $this->doScrape($url);
            $result['engine'] = static::class;
            $result['scrape_time_ms'] = (int) ((microtime(true) - $startTime) * 1000);
            return $result;
        } catch (\Exception $e) {
            $this->errorLog->log(
                $this->getPlatformSlug() . ' engine error: ' . $e->getMessage(),
                'error',
                ['url' => $url, 'engine' => static::class]
            );
            return [
                'success' => false,
                'restaurant' => ['name' => null, 'address' => null, 'phone' => null, 'logo_url' => null, 'banner_url' => null],
                'items' => [],
                'error' => $e->getMessage(),
                'engine' => static::class,
                'scrape_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ];
        }
    }

    /**
     * Perform the actual scraping. Override in each engine.
     *
     * @param string $url The URL to scrape.
     * @return array The scrape result.
     */
    abstract protected function doScrape(string $url): array;

    /**
     * {@inheritdoc}
     */
    public function healthCheck(): array
    {
        try {
            $response = $this->http->head('https://www.google.com', ['timeout' => 5]);
            return ['healthy' => true, 'message' => 'OK'];
        } catch (\Exception $e) {
            return ['healthy' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetch a URL and return a DomCrawler instance.
     *
     * @param string $url The URL to fetch.
     * @return Crawler
     */
    protected function fetchDom(string $url): Crawler
    {
        $response = $this->http->get($url);
        $html = (string) $response->getBody();
        return new Crawler($html, $url);
    }

    /**
     * Fetch a URL via Selenium (headless Chrome) for JS-rendered pages.
     *
     * @param string $url       The URL to fetch.
     * @param int    $waitSecs  Seconds to wait for JS rendering.
     * @return Crawler
     * @throws \RuntimeException If Selenium is not available.
     */
    protected function fetchWithSelenium(string $url, int $waitSecs = 5): Crawler
    {
        $options = new \Facebook\WebDriver\Chrome\ChromeOptions();
        $options->addArguments([
            '--headless=new',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--window-size=1920,1080',
            '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            '--disable-blink-features=AutomationControlled',
            '--disable-features=IsolateOrigins,site-per-process',
            '--disable-web-security',
            '--allow-running-insecure-content',
        ]);

        // Stealth: remove webdriver detection flags
        $options->setExperimentalOption('excludeSwitches', ['enable-automation']);
        $options->setExperimentalOption('useAutomationExtension', false);

        $capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
        $capabilities->setCapability(\Facebook\WebDriver\Chrome\ChromeOptions::CAPABILITY, $options);

        $seleniumUrls = ['http://localhost:4444/wd/hub', 'http://localhost:4444', 'http://localhost:9515'];
        $driver = null;

        foreach ($seleniumUrls as $seleniumUrl) {
            try {
                $driver = \Facebook\WebDriver\Remote\RemoteWebDriver::create($seleniumUrl, $capabilities, 60000, 60000);
                break;
            } catch (\Exception $e) {
                continue;
            }
        }

        if (!$driver) {
            throw new \RuntimeException('Selenium WebDriver is not running. Start ChromeDriver and try again.');
        }

        try {
            // Navigate to a blank page first to inject stealth script
            $driver->get('about:blank');
            try {
                $driver->executeScript(
                    "Object.defineProperty(navigator, 'webdriver', {get: () => undefined});"
                );
            } catch (\Exception $e) {
                // Some drivers don't support this — continue anyway
            }

            $driver->get($url);

            // Wait for Cloudflare challenge to resolve (up to 30s)
            $maxCfWait = 30;
            $waited = 0;
            while ($waited < $maxCfWait) {
                sleep(3);
                $waited += 3;
                $title = $driver->getTitle();
                if (stripos($title, 'Just a moment') === false && stripos($title, 'Checking') === false) {
                    break;
                }
            }

            // Additional wait for JS rendering after CF passes
            sleep(max(3, $waitSecs));

            $html = $driver->getPageSource();
            $driver->quit();
            return new Crawler($html, $url);
        } catch (\Exception $e) {
            if ($driver) {
                try { $driver->quit(); } catch (\Exception $ignored) {}
            }
            throw $e;
        }
    }

    /**
     * Scrape via screenshot + OCR: open Chrome, wait for render, take
     * sectioned screenshots of just the menu content, crop out nav/chrome,
     * OCR each section, quit Chrome fast.
     *
     * Safe for 1GB servers — single Chrome session, ~15s, then exits.
     *
     * @param string $url The URL to screenshot.
     * @return array
     */
    protected function scrapeViaScreenshot(string $url): array
    {
        $driver = null;
        $screenshots = [];
        $screenshotDir = sys_get_temp_dir() . '/scraper_screenshots';

        try {
            if (!is_dir($screenshotDir)) {
                mkdir($screenshotDir, 0755, true);
            }

            // Launch Chrome — minimal memory footprint
            $options = new \Facebook\WebDriver\Chrome\ChromeOptions();
            $options->addArguments([
                '--headless=new', '--disable-gpu', '--no-sandbox',
                '--disable-dev-shm-usage',
                '--window-size=1280,900',
                '--disable-extensions', '--disable-plugins',
                '--disable-images',  // Don't load images to save memory
                '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                '--disable-blink-features=AutomationControlled',
            ]);
            $options->setExperimentalOption('excludeSwitches', ['enable-automation']);
            $options->setExperimentalOption('useAutomationExtension', false);

            $capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
            $capabilities->setCapability(\Facebook\WebDriver\Chrome\ChromeOptions::CAPABILITY, $options);

            $seleniumUrls = ['http://localhost:9515', 'http://localhost:4444/wd/hub', 'http://localhost:4444'];
            foreach ($seleniumUrls as $seleniumUrl) {
                try {
                    $driver = \Facebook\WebDriver\Remote\RemoteWebDriver::create($seleniumUrl, $capabilities, 30000, 30000);
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$driver) {
                return $this->success([], [], 'ChromeDriver is not running. Start it with: sudo systemctl start chromedriver');
            }

            // Stealth + navigate
            $driver->get('about:blank');
            try { $driver->executeScript("Object.defineProperty(navigator, 'webdriver', {get: () => undefined});"); } catch (\Exception $e) {}
            $driver->get($url);

            // Wait for Cloudflare (max 20s)
            for ($i = 0; $i < 7; $i++) {
                sleep(3);
                $title = $driver->getTitle();
                if (stripos($title, 'Just a moment') === false && stripos($title, 'Checking') === false) {
                    break;
                }
            }

            // Check if CF still blocking
            $title = $driver->getTitle();
            if (stripos($title, 'Just a moment') !== false) {
                $driver->quit();
                return $this->success([], [], 'Cloudflare challenge could not be cleared. Try a photo of the menu instead.');
            }

            // Wait for JS rendering
            sleep(5);

            // Get restaurant name + banner
            $restaurantName = $title && strlen($title) > 2 ? $title : null;
            $bannerUrl = null;
            try {
                $html = $driver->getPageSource();
                if (preg_match('/property="og:image"\s+content="([^"]+)"/', $html, $m)) {
                    $bannerUrl = $m[1];
                }
            } catch (\Exception $e) {}

            // Dismiss cookie banners / overlays
            try {
                $driver->executeScript("
                    document.querySelectorAll('[class*=\"cookie\"], [class*=\"Cookie\"], [class*=\"consent\"], [id*=\"cookie\"], [class*=\"overlay\"], [class*=\"modal\"], [class*=\"banner\"]').forEach(el => {
                        if (el.offsetHeight > 0 && el.offsetHeight < 300) el.remove();
                    });
                    document.querySelectorAll('nav, header, [class*=\"nav\"], [class*=\"header\"], [role=\"navigation\"]').forEach(el => el.remove());
                    document.querySelectorAll('footer, [class*=\"footer\"], [role=\"contentinfo\"]').forEach(el => el.remove());
                ");
            } catch (\Exception $e) {}

            sleep(1);

            // Get page dimensions
            $totalHeight = (int) $driver->executeScript('return document.body.scrollHeight;');
            $viewportHeight = 900;
            $scrollPos = 0;
            $section = 0;

            // Scroll and screenshot in sections
            while ($scrollPos < $totalHeight && $section < 15) {
                $driver->executeScript("window.scrollTo(0, {$scrollPos});");
                usleep(500000); // 0.5s between screenshots

                $path = $screenshotDir . '/section_' . uniqid() . '_' . $section . '.png';
                $driver->takeScreenshot($path);

                if (file_exists($path) && filesize($path) > 1000) {
                    $screenshots[] = $path;
                }

                $scrollPos += (int)($viewportHeight * 0.85); // 15% overlap
                $section++;
            }

            // DONE with Chrome — quit immediately
            $driver->quit();
            $driver = null;

            if (empty($screenshots)) {
                return $this->success([], [], 'No screenshots captured.');
            }

            // Crop each screenshot: remove top 10% and bottom 10% (nav/footer remnants)
            // and left/right 5% (sidebars)
            $croppedPaths = [];
            foreach ($screenshots as $ssPath) {
                $cropped = $this->cropScreenshot($ssPath);
                if ($cropped) {
                    $croppedPaths[] = $cropped;
                }
                @unlink($ssPath); // Clean original
            }

            // OCR each cropped section and merge results
            $allItems = [];
            $ocrService = new \App\Services\OcrService();

            foreach ($croppedPaths as $cropPath) {
                $ocrResult = $ocrService->processImage($cropPath);
                if ($ocrResult['success'] && !empty($ocrResult['items'])) {
                    $allItems = array_merge($allItems, $ocrResult['items']);
                }
                @unlink($cropPath); // Clean cropped
            }

            // Deduplicate by name+price
            $allItems = $this->deduplicateItems($allItems);

            $restaurant = [
                'name' => $restaurantName,
                'banner_url' => $bannerUrl,
                'address' => null,
                'phone' => null,
                'logo_url' => null,
            ];

            if (empty($allItems)) {
                return $this->success([], $restaurant, 'Screenshots captured but OCR could not extract menu items. Try taking a photo of the physical menu instead.');
            }

            return $this->success($allItems, $restaurant);

        } catch (\Exception $e) {
            if ($driver) {
                try { $driver->quit(); } catch (\Exception $ignored) {}
            }
            // Clean up any screenshots
            foreach ($screenshots as $ss) { @unlink($ss); }
            $this->errorLog->log('Screenshot scrape failed: ' . $e->getMessage(), 'error', ['url' => $url]);
            return $this->success([], [], 'Screenshot scrape failed: ' . $e->getMessage());
        }
    }

    /**
     * Crop a screenshot to remove nav bars, footers, and sidebars.
     * Keeps the center 90% vertically and 90% horizontally.
     *
     * @param string $path Path to the screenshot PNG.
     * @return string|null Path to the cropped image, or null on failure.
     */
    protected function cropScreenshot(string $path): ?string
    {
        try {
            $img = imagecreatefrompng($path);
            if (!$img) return null;

            $width = imagesx($img);
            $height = imagesy($img);

            // Crop: remove top 8%, bottom 8%, left 5%, right 5%
            $cropX = (int)($width * 0.05);
            $cropY = (int)($height * 0.08);
            $cropW = (int)($width * 0.90);
            $cropH = (int)($height * 0.84);

            $cropped = imagecrop($img, [
                'x' => $cropX,
                'y' => $cropY,
                'width' => $cropW,
                'height' => $cropH,
            ]);

            imagedestroy($img);

            if (!$cropped) return null;

            $croppedPath = str_replace('.png', '_cropped.png', $path);
            imagepng($cropped, $croppedPath);
            imagedestroy($cropped);

            return $croppedPath;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Deduplicate items by name+price.
     *
     * @param array $items The items to deduplicate.
     * @return array
     */
    protected function deduplicateItems(array $items): array
    {
        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            $key = strtolower(trim($item['name'] ?? '')) . '|' . ($item['price'] ?? '');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $item;
            }
        }
        return $unique;
    }

    /**
     * Extract a price from a text string.
     *
     * @param string $text The text to search.
     * @return float|null
     */
    protected function extractPrice(string $text): ?float
    {
        foreach (self::PRICE_PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return (float) $matches[1];
            }
        }
        return null;
    }

    /**
     * Build a successful result array.
     *
     * @param array       $items      The extracted items.
     * @param array       $restaurant Restaurant metadata.
     * @param string|null $error      Optional warning message.
     * @return array
     */
    protected function success(array $items, array $restaurant = [], ?string $error = null): array
    {
        return [
            'success' => !empty($items),
            'restaurant' => array_merge(
                ['name' => null, 'address' => null, 'phone' => null, 'logo_url' => null, 'banner_url' => null],
                $restaurant
            ),
            'items' => $items,
            'error' => $error ?? (empty($items) ? 'No menu items found.' : null),
        ];
    }

    /**
     * Create a normalized item array.
     *
     * @param string      $name        Item name.
     * @param float|null  $price       Item price.
     * @param string|null $description Item description.
     * @param string|null $category    Category name.
     * @param string|null $imageUrl    Image URL.
     * @param array       $extra       Additional fields (calories, dietary_tags, modifiers, external_id).
     * @return array
     */
    protected function makeItem(
        string $name,
        ?float $price = null,
        ?string $description = null,
        ?string $category = null,
        ?string $imageUrl = null,
        array $extra = []
    ): array {
        return array_merge([
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'category' => $category,
            'image_url' => $imageUrl,
            'calories' => null,
            'dietary_tags' => null,
            'modifiers' => null,
            'external_id' => null,
            'raw_text' => null,
        ], $extra);
    }
}
