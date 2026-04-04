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
            '--headless', '--disable-gpu', '--no-sandbox',
            '--disable-dev-shm-usage', '--window-size=1920,1080',
            '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        $capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
        $capabilities->setCapability(\Facebook\WebDriver\Chrome\ChromeOptions::CAPABILITY, $options);

        $seleniumUrls = ['http://localhost:4444/wd/hub', 'http://localhost:4444', 'http://localhost:9515'];
        $driver = null;

        foreach ($seleniumUrls as $seleniumUrl) {
            try {
                $driver = \Facebook\WebDriver\Remote\RemoteWebDriver::create($seleniumUrl, $capabilities, 10000, 10000);
                break;
            } catch (\Exception $e) {
                continue;
            }
        }

        if (!$driver) {
            throw new \RuntimeException('Selenium WebDriver is not running. Start ChromeDriver and try again.');
        }

        try {
            $driver->get($url);
            sleep($waitSecs);
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
