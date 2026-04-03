# Claude Scraper - User Guide

## Overview

Claude Scraper is a utility platform that extracts restaurant menu items from websites and photographs. It can identify item names, prices, descriptions, and images, then export or import the data into other VisionQuest platforms.

## Getting Started

### Logging In

1. Navigate to your Claude Scraper instance (e.g., http://scraper.local)
2. Enter your admin email and password
3. Click "Sign In"

### Dashboard

The dashboard shows:
- **Total Scans** - Number of scans performed
- **Completed** - Successfully completed scans
- **Imported** - Scans that have been imported to a platform
- **Quick Actions** - Start a URL scan or photo scan
- **Recent Scans** - Your latest scan activity

## Scanning a URL

1. Click **New Scan** in the sidebar (or "Scan a URL" on the dashboard)
2. Enter the restaurant's menu page URL
3. Optionally click **Preview** to see results before saving
4. Click **Scrape Menu** to run the full scan
5. Review the extracted items on the results page

### Tips for URL Scanning
- Use the direct menu page URL, not the restaurant's homepage
- Pages with structured data (JSON-LD) produce the best results
- For JavaScript-heavy sites, the Selenium WebDriver will be used automatically

## Scanning a Photo

1. Click **New Scan** > **Scan Photo** tab
2. Either:
   - **Upload** an image file (JPG, PNG, etc.)
   - **Take a photo** using your device camera (on mobile)
   - **Drag and drop** an image onto the upload area
3. Click **Process with OCR**
4. Review and edit the extracted items

### Tips for Photo Scanning
- Use well-lit, straight-on photos of menus
- Higher resolution images produce better results
- Printed menus work better than handwritten ones
- Crop the image to show only the menu items

## Reviewing & Editing Items

After a scan completes, you'll see a table of extracted items. You can:

- **Edit inline** - Click any cell (name, description, price, category) to edit
- **Select/deselect items** - Use checkboxes to choose which items to keep
- **Save changes** - Click "Save Changes" after editing
- **Export to CSV** - Download items as a spreadsheet

## Importing to a Platform

1. From a completed scan, click **Import to Platform**
2. Select the target platform (e.g., Claude Takeout)
3. Choose a store/location within the platform
4. Click **Import**
5. Review the import results (success/failure per item)

### Available Platforms
- **Claude Takeout** - Restaurant ordering platform
- **Claude Tool Rental** - Equipment rental platform
- Additional platforms can be configured

## Managing Scans

### Scan History
- View all past scans at **Scan History** in the sidebar
- Filter by status (pending, complete, failed, imported)
- Filter by type (URL or photo)
- Search by title or URL

### Deleting a Scan
- Open the scan detail page
- Scroll to the bottom and click **Delete Scan**
- This removes the scan and all associated items

## Keyboard Shortcuts

The interface is fully navigable by keyboard:
- `Tab` to move between fields
- `Enter` to submit forms
- Standard browser navigation for all links

## Mobile Usage

Claude Scraper is fully responsive:
- The sidebar collapses on mobile devices
- Tap the menu button (bottom-left) to toggle the sidebar
- Photo capture uses your device's camera on mobile
- Tables scroll horizontally on small screens

## Troubleshooting

### "No items could be extracted"
- Try a different URL (the direct menu page, not homepage)
- Some sites block scrapers — try taking a photo instead
- Check if the site requires JavaScript to render (Selenium may help)

### "Tesseract OCR not detected"
- Tesseract must be installed on the server for photo scanning
- Contact your administrator to install Tesseract

### Import fails
- Ensure the target platform is installed and its database is accessible
- Check that the platform's item table structure matches the expected schema
- View error details on the import detail page
