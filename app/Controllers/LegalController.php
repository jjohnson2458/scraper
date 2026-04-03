<?php

namespace App\Controllers;

use App\Core\BaseController;

/**
 * Legal Controller
 *
 * Handles Terms of Service and Privacy Policy pages.
 *
 * @package    ClaudeScraper
 * @subpackage Controllers
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class LegalController extends BaseController
{
    /**
     * Display Terms of Service.
     *
     * @return void
     */
    public function terms(): void
    {
        $this->render('legal.terms', [
            'pageTitle' => 'Terms of Service - Claude Scraper',
        ]);
    }

    /**
     * Display Privacy Policy.
     *
     * @return void
     */
    public function privacy(): void
    {
        $this->render('legal.privacy', [
            'pageTitle' => 'Privacy Policy - Claude Scraper',
        ]);
    }
}
