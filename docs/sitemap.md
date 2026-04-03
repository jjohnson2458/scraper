# Claude Scraper - Sitemap

## Public Routes (No Auth Required)
- `GET /login` - Login page
- `GET /terms` - Terms of Service
- `GET /privacy` - Privacy Policy

## Admin Routes (Auth Required)
- `GET /` - Dashboard
- `GET /dashboard` - Dashboard (alias)

### Scans
- `GET /scans` - Scan history list
- `GET /scans/new` - New scan form (URL + Photo tabs)
- `POST /scans/url` - Process URL scan
- `POST /scans/photo` - Process photo/OCR scan
- `GET /scans/{id}` - Scan detail with editable items
- `POST /scans/{id}/save` - Save edited items (JSON)
- `DELETE /scans/{id}` - Delete a scan
- `GET /scans/{id}/export` - Export items as CSV

### Imports
- `GET /imports` - Import history list
- `GET /imports/new/{scanId}` - Import form for a scan
- `POST /imports` - Process import
- `GET /imports/{id}` - Import detail

### API (Internal JSON Endpoints)
- `POST /api/scans/preview` - Preview scrape without saving
- `POST /api/ocr/process` - Process image via OCR
- `GET /api/scans/{id}/items` - Get scan items
- `PUT /api/scans/{id}/items` - Update scan items
- `GET /api/platforms` - List available import platforms
- `GET /api/platforms/{slug}/stores` - List stores for a platform

## Authentication
- `GET /logout` - Logout (destroys session)
