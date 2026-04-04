<?php

namespace App\Services;

use App\Services\Engines\EngineInterface;
use App\Services\Engines\GenericEngine;
use App\Services\Engines\ToastEngine;
use App\Services\Engines\DoorDashEngine;
use App\Services\Engines\UberEatsEngine;
use App\Services\Engines\GrubhubEngine;
use App\Services\Engines\ChowNowEngine;
use App\Services\Engines\SquareEngine;
use App\Services\Engines\CloverEngine;
use App\Services\Engines\OloEngine;
use App\Services\Engines\BentoBoxEngine;
use App\Services\Engines\PopmenuEngine;
use App\Services\Engines\SinglePlatformEngine;
use App\Services\Engines\MenufyEngine;
use App\Services\Engines\YelpEngine;
use App\Services\Engines\GoogleMapsEngine;
use App\Models\Platform;

/**
 * Engine Manager
 *
 * Manages platform-specific scraping engines. Handles auto-detection
 * of platforms from URLs and dispatches to the correct engine.
 *
 * @package    ClaudeScraper
 * @subpackage Services
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class EngineManager
{
    /** @var array<EngineInterface> Registered engines */
    private array $engines = [];

    /** @var GenericEngine Fallback engine */
    private GenericEngine $fallback;

    /** @var Platform */
    private Platform $platformModel;

    /**
     * EngineManager constructor.
     */
    public function __construct()
    {
        $this->fallback = new GenericEngine();
        $this->platformModel = new Platform();
        $this->registerEngines();
    }

    /**
     * Register all available engines.
     *
     * @return void
     */
    private function registerEngines(): void
    {
        $this->engines = [
            // Phase 2: High priority
            new ToastEngine(),
            new DoorDashEngine(),
            new UberEatsEngine(),
            new GrubhubEngine(),
            new ChowNowEngine(),
            new SquareEngine(),
            // Phase 3: Medium priority
            new CloverEngine(),
            new OloEngine(),
            new BentoBoxEngine(),
            new PopmenuEngine(),
            new SinglePlatformEngine(),
            new MenufyEngine(),
            new YelpEngine(),
            new GoogleMapsEngine(),
        ];
    }

    /**
     * Auto-detect the platform from a URL and return the matching engine.
     *
     * @param string $url The URL to analyze.
     * @return array{engine: EngineInterface, platform: array|null}
     */
    public function detect(string $url): array
    {
        // Check registered engines first (fastest — in-memory)
        foreach ($this->engines as $engine) {
            if ($engine->canHandle($url)) {
                $platform = $this->platformModel->findBySlug($engine->getPlatformSlug());
                return ['engine' => $engine, 'platform' => $platform];
            }
        }

        // Check database platform registry (URL patterns)
        $platforms = $this->platformModel->getActive();
        foreach ($platforms as $platform) {
            if (!empty($platform['url_pattern']) && preg_match('/' . $platform['url_pattern'] . '/i', $url)) {
                // Try to instantiate the engine class
                $engineClass = $platform['engine_class'] ?? null;
                if ($engineClass && class_exists($engineClass)) {
                    return ['engine' => new $engineClass(), 'platform' => $platform];
                }
            }
        }

        // Fallback to generic engine
        return ['engine' => $this->fallback, 'platform' => null];
    }

    /**
     * Scrape a URL, auto-detecting the platform.
     *
     * @param string      $url          The URL to scrape.
     * @param string|null $platformSlug Force a specific platform engine (skip auto-detect).
     * @return array The scrape result with additional platform info.
     */
    public function scrape(string $url, ?string $platformSlug = null): array
    {
        if ($platformSlug && $platformSlug !== 'auto') {
            $engine = $this->getEngine($platformSlug);
            $platform = $this->platformModel->findBySlug($platformSlug);
        } else {
            $detected = $this->detect($url);
            $engine = $detected['engine'];
            $platform = $detected['platform'];
        }

        $result = $engine->scrape($url);

        // Add platform metadata to result
        $result['platform'] = $platform ? [
            'id' => $platform['id'],
            'name' => $platform['name'],
            'slug' => $platform['slug'],
        ] : [
            'id' => null,
            'name' => 'Generic',
            'slug' => 'generic',
        ];

        // Update platform health status
        if ($platform) {
            $this->platformModel->updateHealth(
                (int) $platform['id'],
                $result['success'] ? 'green' : 'red'
            );
        }

        return $result;
    }

    /**
     * Get a specific engine by platform slug.
     *
     * @param string $slug The platform slug.
     * @return EngineInterface
     */
    public function getEngine(string $slug): EngineInterface
    {
        foreach ($this->engines as $engine) {
            if ($engine->getPlatformSlug() === $slug) {
                return $engine;
            }
        }
        return $this->fallback;
    }

    /**
     * Get all registered engine slugs.
     *
     * @return array
     */
    public function getRegisteredEngines(): array
    {
        $list = [];
        foreach ($this->engines as $engine) {
            $list[] = $engine->getPlatformSlug();
        }
        $list[] = 'generic';
        return $list;
    }

    /**
     * Get available platforms for the UI dropdown.
     *
     * @return array
     */
    public function getPlatformsForDropdown(): array
    {
        $platforms = $this->platformModel->getAllGrouped();

        // Add auto-detect option at the top
        array_unshift($platforms, [
            'id' => null,
            'name' => 'Auto-Detect',
            'slug' => 'auto',
            'category' => null,
            'is_active' => 1,
            'health_status' => 'green',
            'group_label' => null,
        ]);

        return $platforms;
    }
}
