<?php

namespace Tests\Selenium;

use PHPUnit\Framework\TestCase;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * Selenium WebDriver tests for scan functionality.
 *
 * @package    ClaudeScraper
 * @subpackage Tests\Selenium
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class ScanTest extends TestCase
{
    /** @var RemoteWebDriver */
    private RemoteWebDriver $driver;

    /** @var string Base URL for testing */
    private string $baseUrl = 'http://scraper.local';

    /**
     * Set up WebDriver and login before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->driver = RemoteWebDriver::create(
            'http://localhost:4444/wd/hub',
            DesiredCapabilities::chrome()
        );
        $this->driver->manage()->window()->maximize();
        $this->driver->manage()->timeouts()->implicitlyWait(10);

        // Login
        $this->driver->get($this->baseUrl . '/login');
        $this->driver->findElement(WebDriverBy::name('email'))->sendKeys('email4johnson@gmail.com');
        $this->driver->findElement(WebDriverBy::name('password'))->sendKeys('24AdaPlace');
        $this->driver->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();
        $this->driver->wait(5)->until(WebDriverExpectedCondition::urlContains('/'));
    }

    /**
     * Tear down WebDriver after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->driver->quit();
    }

    /**
     * Test new scan page has URL and Photo tabs.
     *
     * @return void
     */
    public function testNewScanPageHasTabs(): void
    {
        $this->driver->get($this->baseUrl . '/scans/new');

        $urlTab = $this->driver->findElement(WebDriverBy::xpath("//button[contains(text(), 'Scan URL')]"));
        $photoTab = $this->driver->findElement(WebDriverBy::xpath("//button[contains(text(), 'Scan Photo')]"));

        $this->assertTrue($urlTab->isDisplayed());
        $this->assertTrue($photoTab->isDisplayed());
    }

    /**
     * Test URL input is present on new scan page.
     *
     * @return void
     */
    public function testUrlInputPresent(): void
    {
        $this->driver->get($this->baseUrl . '/scans/new');
        $urlInput = $this->driver->findElement(WebDriverBy::name('url'));
        $this->assertTrue($urlInput->isDisplayed());
    }

    /**
     * Test scan history page loads.
     *
     * @return void
     */
    public function testScanHistoryPageLoads(): void
    {
        $this->driver->get($this->baseUrl . '/scans');
        $heading = $this->driver->findElement(WebDriverBy::cssSelector('.page-header h1'));
        $this->assertStringContainsString('Scan History', $heading->getText());
    }

    /**
     * Test viewing a scan detail page.
     *
     * @return void
     */
    public function testViewScanDetails(): void
    {
        $this->driver->get($this->baseUrl . '/scans/1');
        $table = $this->driver->findElement(WebDriverBy::id('items-table'));
        $this->assertTrue($table->isDisplayed());
    }

    /**
     * Test sidebar navigation works.
     *
     * @return void
     */
    public function testSidebarNavigation(): void
    {
        $this->driver->get($this->baseUrl . '/');

        // Navigate to Scan History
        $scanLink = $this->driver->findElement(
            WebDriverBy::xpath("//a[contains(@class, 'sidebar-link')]//span[text()='Scan History']/..")
        );
        $scanLink->click();
        $this->driver->wait(5)->until(WebDriverExpectedCondition::urlContains('/scans'));

        // Navigate to New Scan
        $newScanLink = $this->driver->findElement(
            WebDriverBy::xpath("//a[contains(@class, 'sidebar-link')]//span[text()='New Scan']/..")
        );
        $newScanLink->click();
        $this->driver->wait(5)->until(WebDriverExpectedCondition::urlContains('/scans/new'));
    }
}
