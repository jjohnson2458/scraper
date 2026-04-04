<?php

namespace App\Services\Engines;

/**
 * Scraping Engine Interface
 *
 * All platform-specific scraping engines must implement this interface.
 * Each engine is tuned to a specific platform's DOM/API structure and
 * returns normalized menu data in a standard format.
 *
 * @package    ClaudeScraper
 * @subpackage Services\Engines
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
interface EngineInterface
{
    /**
     * Get the platform slug this engine handles.
     *
     * @return string
     */
    public function getPlatformSlug(): string;

    /**
     * Check if this engine can handle a given URL.
     *
     * @param string $url The URL to check.
     * @return bool
     */
    public function canHandle(string $url): bool;

    /**
     * Scrape a URL and return normalized menu data.
     *
     * @param string $url The restaurant menu URL to scrape.
     * @return array{
     *     success: bool,
     *     restaurant: array{name: string|null, address: string|null, phone: string|null, logo_url: string|null, banner_url: string|null},
     *     items: array<array{name: string, description: string|null, price: float|null, category: string|null, image_url: string|null, calories: int|null, dietary_tags: array|null, modifiers: array|null, external_id: string|null, raw_text: string|null}>,
     *     error: string|null,
     *     engine: string,
     *     scrape_time_ms: int
     * }
     */
    public function scrape(string $url): array;

    /**
     * Check if the engine is healthy (can reach the platform).
     *
     * @return array{healthy: bool, message: string}
     */
    public function healthCheck(): array;
}
