# Claude Scraper - Nightly Configuration

## Enabled Tasks
- [x] PHPUnit tests
- [x] Playwright E2E tests
- [x] Cleanup (logs, temp files)
- [ ] Docs rebuild
- [ ] Security audit
- [ ] Database backup
- [ ] Auto git push

## Schedule
- Run nightly at 2:00 AM
- Email results to email4johnson@gmail.com

## PHPUnit
- Command: `vendor/bin/phpunit --testdox`
- Fail threshold: any failure triggers email alert

## Playwright
- Command: `npx playwright test`
- Browsers: chromium
- Include mobile viewports: yes

## Cleanup
- Delete storage/logs/*.log older than 30 days
- Delete storage/cache/* older than 7 days
- Delete public/uploads/ocr/* older than 90 days
