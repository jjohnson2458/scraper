<?php

namespace Tests\Selenium;

use PHPUnit\Framework\TestCase;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * Selenium WebDriver tests for authentication flow.
 *
 * @package    ClaudeScraper
 * @subpackage Tests\Selenium
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class LoginTest extends TestCase
{
    /** @var RemoteWebDriver */
    private RemoteWebDriver $driver;

    /** @var string Base URL for testing */
    private string $baseUrl = 'http://scraper.local';

    /**
     * Set up WebDriver before each test.
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
     * Test that the login page loads correctly.
     *
     * @return void
     */
    public function testLoginPageLoads(): void
    {
        $this->driver->get($this->baseUrl . '/login');
        $heading = $this->driver->findElement(WebDriverBy::tagName('h2'));
        $this->assertStringContainsString('Claude Scraper', $heading->getText());
    }

    /**
     * Test successful login redirects to dashboard.
     *
     * @return void
     */
    public function testSuccessfulLogin(): void
    {
        $this->driver->get($this->baseUrl . '/login');

        $this->driver->findElement(WebDriverBy::name('email'))->sendKeys('email4johnson@gmail.com');
        $this->driver->findElement(WebDriverBy::name('password'))->sendKeys('24AdaPlace');
        $this->driver->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        $this->driver->wait(5)->until(
            WebDriverExpectedCondition::urlContains('/')
        );

        $sidebar = $this->driver->findElement(WebDriverBy::id('sidebar'));
        $this->assertTrue($sidebar->isDisplayed());
    }

    /**
     * Test invalid login shows error message.
     *
     * @return void
     */
    public function testInvalidLoginShowsError(): void
    {
        $this->driver->get($this->baseUrl . '/login');

        $this->driver->findElement(WebDriverBy::name('email'))->sendKeys('wrong@email.com');
        $this->driver->findElement(WebDriverBy::name('password'))->sendKeys('wrongpassword');
        $this->driver->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        $this->driver->wait(5)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.alert-danger'))
        );

        $alert = $this->driver->findElement(WebDriverBy::cssSelector('.alert-danger'));
        $this->assertStringContainsString('Invalid', $alert->getText());
    }
}
