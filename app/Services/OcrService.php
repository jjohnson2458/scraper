<?php

namespace App\Services;

use thiagoalessio\TesseractOCR\TesseractOCR;
use App\Models\ErrorLog;

/**
 * OCR Service
 *
 * Handles optical character recognition for menu photos
 * using Tesseract OCR. Parses extracted text into structured
 * menu items.
 *
 * @package    ClaudeScraper
 * @subpackage Services
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class OcrService
{
    /** @var ErrorLog Error logger */
    private ErrorLog $errorLog;

    /** @var array Price regex patterns */
    private const PRICE_PATTERNS = [
        '/\$\s*(\d+(?:\.\d{2})?)/u',
        '/(\d+(?:\.\d{2})?)\s*(?:USD|dollars?)/iu',
    ];

    /**
     * OcrService constructor.
     */
    public function __construct()
    {
        $this->errorLog = new ErrorLog();
    }

    /**
     * Process an uploaded image file and extract text via OCR.
     *
     * @param string $imagePath Absolute path to the image file.
     * @return array{success: bool, text: string|null, items: array, error: string|null}
     */
    public function processImage(string $imagePath): array
    {
        try {
            if (!file_exists($imagePath)) {
                return [
                    'success' => false,
                    'text' => null,
                    'items' => [],
                    'error' => 'Image file not found.',
                ];
            }

            $ocr = new TesseractOCR($imagePath);
            $ocr->lang('eng');
            $ocr->psm(6); // Assume uniform block of text

            $text = $ocr->run();

            if (empty(trim($text))) {
                return [
                    'success' => false,
                    'text' => '',
                    'items' => [],
                    'error' => 'No text could be extracted from the image.',
                ];
            }

            $items = $this->parseMenuText($text);

            return [
                'success' => true,
                'text' => $text,
                'items' => $items,
                'error' => null,
            ];
        } catch (\Exception $e) {
            $this->errorLog->log('OCR processing failed: ' . $e->getMessage(), 'error', [
                'image' => $imagePath,
            ]);
            return [
                'success' => false,
                'text' => null,
                'items' => [],
                'error' => 'OCR processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Parse raw OCR text into structured menu items.
     *
     * @param string $text The raw OCR text.
     * @return array Array of parsed menu items.
     */
    public function parseMenuText(string $text): array
    {
        $items = [];
        $lines = preg_split('/[\n\r]+/', $text);
        $currentCategory = null;
        $currentItem = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strlen($line) < 2) {
                continue;
            }

            // Skip common non-menu lines
            if ($this->isNonMenuLine($line)) {
                continue;
            }

            // Check if this is a category header (all caps, no price)
            if ($this->isCategoryHeader($line)) {
                $currentCategory = $this->cleanCategoryName($line);
                continue;
            }

            // Try to extract price from the line
            $price = $this->extractPrice($line);
            $name = $this->extractItemName($line);

            if (!empty($name)) {
                // Save previous item if exists
                if ($currentItem) {
                    $items[] = $currentItem;
                }

                $currentItem = [
                    'name' => $name,
                    'description' => null,
                    'price' => $price,
                    'category' => $currentCategory,
                    'image_url' => null,
                    'raw_text' => $line,
                ];
            } elseif ($currentItem && !$price) {
                // This line might be a description of the previous item
                if (strlen($line) > 10 && strlen($line) < 200) {
                    $currentItem['description'] = $currentItem['description']
                        ? $currentItem['description'] . ' ' . $line
                        : $line;
                }
            }
        }

        // Don't forget the last item
        if ($currentItem) {
            $items[] = $currentItem;
        }

        return $items;
    }

    /**
     * Check if a line is a non-menu line (headers, footers, etc.).
     *
     * @param string $line The text line.
     * @return bool
     */
    private function isNonMenuLine(string $line): bool
    {
        $skipPatterns = [
            '/^(phone|tel|fax|address|hours|open|closed|delivery|pickup)/i',
            '/^(www\.|http|@|email)/i',
            '/^(tax|tip|gratuity|service charge)/i',
            '/^\d{3}[-.]?\d{3}[-.]?\d{4}$/',  // Phone numbers
            '/^[A-Z\s]{2,}\d+\s/',  // Zip codes
        ];

        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a line looks like a category header.
     *
     * @param string $line The text line.
     * @return bool
     */
    private function isCategoryHeader(string $line): bool
    {
        // All uppercase, no numbers that look like prices
        if (preg_match('/^[A-Z\s&\'-]{3,50}$/', $line)) {
            return true;
        }

        // Common category words
        $categories = ['appetizer', 'salad', 'soup', 'entree', 'main', 'dessert',
            'beverage', 'drink', 'side', 'pizza', 'pasta', 'burger', 'sandwich',
            'breakfast', 'lunch', 'dinner', 'special', 'combo', 'kid', 'seafood'];

        $lower = strtolower($line);
        foreach ($categories as $cat) {
            if (str_contains($lower, $cat) && !$this->extractPrice($line)) {
                return strlen($line) < 40;
            }
        }

        return false;
    }

    /**
     * Clean a category name.
     *
     * @param string $name The raw category name.
     * @return string
     */
    private function cleanCategoryName(string $name): string
    {
        $name = trim($name, " \t\n\r\0\x0B*-=_.");
        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Extract price from a text line.
     *
     * @param string $line The text line.
     * @return float|null
     */
    private function extractPrice(string $line): ?float
    {
        foreach (self::PRICE_PATTERNS as $pattern) {
            if (preg_match($pattern, $line, $matches)) {
                return (float) $matches[1];
            }
        }
        return null;
    }

    /**
     * Extract the item name from a line (removing price).
     *
     * @param string $line The text line.
     * @return string|null
     */
    private function extractItemName(string $line): ?string
    {
        // Remove price from the line
        $name = $line;
        foreach (self::PRICE_PATTERNS as $pattern) {
            $name = preg_replace($pattern, '', $name);
        }

        // Remove trailing dots/dashes (price leaders)
        $name = preg_replace('/[\.\-_]{3,}/', '', $name);
        $name = trim($name, " \t\n\r\0\x0B-.");

        // Must have meaningful content
        if (strlen($name) < 2 || strlen($name) > 200) {
            return null;
        }

        // Must start with a letter
        if (!preg_match('/^[A-Za-z]/', $name)) {
            return null;
        }

        return $name;
    }

    /**
     * Check if Tesseract is installed and available.
     *
     * @return array{installed: bool, version: string|null, path: string|null}
     */
    public function checkTesseract(): array
    {
        try {
            $output = shell_exec('tesseract --version 2>&1');
            if ($output && str_contains($output, 'tesseract')) {
                preg_match('/tesseract\s+([\d.]+)/i', $output, $matches);
                return [
                    'installed' => true,
                    'version' => $matches[1] ?? 'unknown',
                    'path' => trim(shell_exec('where tesseract 2>nul') ?: ''),
                ];
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return ['installed' => false, 'version' => null, 'path' => null];
    }
}
