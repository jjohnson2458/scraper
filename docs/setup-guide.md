# Claude Scraper - Setup Guide

## Prerequisites

- PHP 8.1 or higher
- MySQL/MariaDB 5.7+
- Apache with mod_rewrite
- Composer
- Node.js 18+ and npm
- Tesseract OCR (for photo scanning feature)

## Local Development Setup (XAMPP)

### 1. Clone the Repository

```bash
cd C:\xampp\htdocs
git clone https://github.com/jjohnson2458/scraper.git claude_scraper
cd claude_scraper
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Environment Configuration

Copy the `.env` file and configure:

```bash
cp .env.example .env
```

For local XAMPP, the defaults should work:
- `DB_HOST=localhost`
- `DB_DATABASE=scraper`
- `DB_USERNAME=root`
- `DB_PASSWORD=` (blank)

### 4. Database Setup

Create the database and run migrations:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS scraper CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run each migration file
for f in database/migrations/*.sql; do
    mysql -u root scraper < "$f"
done
```

### 5. Seed the Database

```bash
php database/seeds/seed.php
```

This creates the default admin user:
- Email: email4johnson@gmail.com
- Password: 24AdaPlace

### 6. Apache Virtual Host

Add to `httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/claude_scraper/public"
    ServerName scraper.local
    <Directory "C:/xampp/htdocs/claude_scraper/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add to `C:\Windows\System32\drivers\etc\hosts`:
```
127.0.0.1 scraper.local
```

Restart Apache.

### 7. Install Tesseract OCR (Optional - for photo scanning)

Download from: https://github.com/UB-Mannheim/tesseract/wiki

After installing, ensure `tesseract` is in your PATH.

### 8. Build Frontend Assets

```bash
npm run build
```

For development with hot reload:
```bash
npm run dev
```

### 9. Verify Installation

Visit http://scraper.local and log in with the admin credentials.

## Running Tests

### PHPUnit
```bash
vendor/bin/phpunit --testdox
```

### Playwright E2E
```bash
npx playwright install chromium
npx playwright test
```

### Selenium (requires Selenium Server)
```bash
# Start Selenium Server first
java -jar selenium-server.jar standalone

# Run tests
vendor/bin/phpunit tests/Selenium
```

## Production Deployment

1. Push to GitHub: `git push origin main`
2. SSH into production server
3. Pull changes: `cd /var/www/html/scraper && git pull`
4. Copy `.env.production` to `.env`
5. Run `composer install --no-dev`
6. Run migrations
7. Set appropriate file permissions

## Project Structure

```
claude_scraper/
├── app/
│   ├── Controllers/     # Request handlers
│   ├── Core/            # Router, BaseController, BaseModel
│   ├── Helpers/         # Global helper functions
│   ├── Middleware/       # Auth, CSRF, Security headers
│   ├── Models/          # Database models
│   └── Services/        # Business logic (Scraper, OCR, Import)
├── config/              # App and database configuration
├── database/
│   ├── migrations/      # SQL migration files
│   └── seeds/           # Seed data
├── public/              # Web root (index.php, assets, uploads)
├── resources/
│   ├── js/              # React components
│   └── views/           # PHP view templates
├── routes/              # Route definitions
├── storage/             # Logs, cache, sessions
├── tests/               # PHPUnit, Playwright, Selenium
├── docs/                # Documentation
└── scripts/             # Commercial scripts, deployment
```
