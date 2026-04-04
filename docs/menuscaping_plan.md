# Menuscaping - Platform Planning Document

**Project:** claude_scraper (scraper.local)
**Concept:** Per-platform restaurant menu scraping engine with dropdown-based UI
**Date:** 2026-04-03
**Status:** Planning

---

## 1. Vision

Menuscaping is a feature within `claude_scraper` that scrapes restaurant menus from all major ordering/delivery platforms. Rather than a single general-purpose scraper, we build **dedicated engines per platform** — each tuned to that platform's DOM structure, API patterns, and data format. The user selects a platform from a dropdown, pastes a restaurant URL, and gets structured menu data back.

---

## 2. Architecture Decision: Per-Platform Engines

**Why per-platform instead of general-purpose:**
- Each platform has unique HTML/JS structure — a universal parser would be fragile and slow
- Per-platform engines can be tested, versioned, and updated independently
- When a platform redesigns, only that one engine needs updating
- Some platforms expose semi-public APIs (JSON endpoints) that are far more reliable than DOM scraping
- Enables platform-specific features (e.g., DoorDash has nutrition data, Toast has modifier trees)

**Engine interface (standardized output):**
Every engine returns the same normalized data structure regardless of source platform.

---

## 3. Platform Registry (25 Platforms, 4 Categories)

### Tier 1 — Online Ordering & POS Systems (10)
| # | Platform | Priority | Scrape Method | Notes |
|---|----------|----------|---------------|-------|
| 1 | **Toast** | HIGH | API + DOM | Largest restaurant POS; embedded online ordering widgets |
| 2 | **Square Online** | HIGH | DOM | Square-hosted restaurant storefronts |
| 3 | **Clover** | MEDIUM | DOM | Clover online ordering pages |
| 4 | **ChowNow** | HIGH | API (JSON) | Direct-ordering platform; clean JSON endpoints |
| 5 | **Olo** | MEDIUM | API | Powers ordering for chains (Sweetgreen, Shake Shack) |
| 6 | **BentoBox** | MEDIUM | DOM | Restaurant website builder with menu pages |
| 7 | **Popmenu** | MEDIUM | DOM + API | Menu hosting with structured data |
| 8 | **Owner.com** | LOW | DOM | Newer entrant, growing fast |
| 9 | **SpotOn** | LOW | DOM | POS with online ordering |
| 10 | **Lightspeed** | LOW | DOM | Restaurant POS/ordering |

### Tier 2 — Third-Party Delivery / Marketplace (6)
| # | Platform | Priority | Scrape Method | Notes |
|---|----------|----------|---------------|-------|
| 11 | **DoorDash** | HIGH | API (JSON) | Huge market share; menu JSON in page source |
| 12 | **Uber Eats** | HIGH | API (JSON) | GraphQL-backed; menu data in hydration state |
| 13 | **Grubhub** | HIGH | API (JSON) | REST API endpoints for menu data |
| 14 | **Postmates** | LOW | merged w/ Uber Eats | Same backend as Uber Eats |
| 15 | **Seamless** | LOW | merged w/ Grubhub | Same backend as Grubhub |
| 16 | **Caviar** | LOW | merged w/ DoorDash | Same backend as DoorDash |

### Tier 3 — Restaurant Website Builders / Menu Hosts (5)
| # | Platform | Priority | Scrape Method | Notes |
|---|----------|----------|---------------|-------|
| 17 | **SinglePlatform** | MEDIUM | API | Tripadvisor-owned menu data provider |
| 18 | **Menufy** | MEDIUM | DOM | Online ordering + menu hosting |
| 19 | **GloriaFood** | LOW | DOM | Free restaurant ordering system |
| 20 | **9Fold** | LOW | DOM | Menu digitization service |
| 21 | **Menulog** | LOW | DOM | AU/NZ market |

### Tier 4 — Data Aggregators (4)
| # | Platform | Priority | Scrape Method | Notes |
|---|----------|----------|---------------|-------|
| 22 | **Yelp** | MEDIUM | DOM | Menu tabs on business pages |
| 23 | **Google Maps/Business** | MEDIUM | DOM | Menu data from business profiles |
| 24 | **TripAdvisor** | LOW | DOM | Menu sections on restaurant pages |
| 25 | **Allmenus.com** | LOW | DOM | Aggregated menu database |

---

## 4. Implementation Phases

### Phase 1 — Foundation (Week 1-2)
- [ ] Design normalized menu data schema (DB tables: `platforms`, `restaurants`, `menu_categories`, `menu_items`, `modifiers`, `scrape_jobs`)
- [ ] Build the Engine Interface (PHP abstract class / interface that all platform engines implement)
- [ ] Create the UI: platform dropdown + URL input + scrape button + results view
- [ ] Build the auto-detect feature (paste any URL, system identifies the platform automatically)
- [ ] Set up job queue for async scraping (scrape can take 5-30 seconds depending on platform)

### Phase 2 — Priority Engines (Week 3-5)
Build the 7 HIGH-priority engines first:
- [ ] **Toast** engine
- [ ] **DoorDash** engine
- [ ] **Uber Eats** engine
- [ ] **Grubhub** engine
- [ ] **ChowNow** engine
- [ ] **Square Online** engine
- [ ] Auto-detect URL patterns for all 7

### Phase 3 — Medium Priority Engines (Week 6-8)
- [ ] Clover, Olo, BentoBox, Popmenu engines
- [ ] SinglePlatform, Menufy engines
- [ ] Yelp, Google Maps engines

### Phase 4 — Low Priority & Polish (Week 9-10)
- [ ] Remaining engines (Owner.com, SpotOn, Lightspeed, GloriaFood, 9Fold, Menulog, TripAdvisor, Allmenus)
- [ ] Merged platform deduplication (Postmates→Uber Eats, Seamless→Grubhub, Caviar→DoorDash)
- [ ] Bulk scrape mode (multiple restaurants at once)
- [ ] Scheduled re-scrape for tracked restaurants (detect menu changes)
- [ ] Export formats: CSV, JSON, PDF menu printout

---

## 5. Normalized Menu Data Schema

```
restaurants
  - id, platform_id, external_id, name, address, phone, website, logo_url, cuisine_type

menu_categories
  - id, restaurant_id, name, sort_order, description, available_start, available_end

menu_items
  - id, category_id, name, description, price, image_url, is_available,
    calories, dietary_tags (JSON), sort_order

modifiers (add-ons, sizes, options)
  - id, item_id, group_name, option_name, price_adjustment, is_default, is_required

scrape_jobs
  - id, restaurant_id, platform_id, url, status, started_at, completed_at, items_found, error_log
```

---

## 6. UI Design

**Scanner Page (`/scrape` or `/menuscaping`):**
1. **Platform dropdown** — All 25 platforms listed, grouped by category, plus "Auto-Detect"
2. **URL input** — Paste restaurant URL
3. **Scrape button** — Triggers async job
4. **Results panel** — Shows structured menu with categories, items, prices, images
5. **Export bar** — CSV / JSON / PDF download buttons
6. **History sidebar** — Previously scraped restaurants with re-scrape option

**Dashboard integration:**
- Widget on claude_scraper homepage showing recent scrapes, success rates per platform
- Platform health status (green/yellow/red) indicating if engines are working

---

## 7. Technical Considerations

- **Headless browser:** GeckoDriver/Selenium for JS-heavy platforms (DoorDash, Uber Eats)
- **Direct HTTP:** cURL + DOM parsing for simpler platforms (BentoBox, Menufy)
- **Rate limiting:** Per-platform request throttling to avoid blocks
- **Caching:** Cache scraped menus for 24 hours to avoid redundant hits
- **Error handling:** If a platform engine fails, log the error and suggest the user try auto-detect or a different platform listing for the same restaurant
- **Testing:** Each engine gets a Playwright test with a known restaurant URL to verify extraction

---

## 8. Priority Build Order

```
1. Toast        — largest POS, most restaurant sites
2. DoorDash     — largest delivery platform
3. Uber Eats    — second largest delivery
4. Grubhub      — third largest delivery
5. Square       — very common for small restaurants
6. ChowNow      — clean API, easy win
7. Olo          — powers many chains
8. Popmenu      — growing market share
9. BentoBox     — popular website builder
```

---

## 9. Success Metrics

- Engine count: 25 platforms covered
- Accuracy: 95%+ menu item extraction rate per engine
- Speed: < 15 seconds average scrape time
- Uptime: Engine health monitoring with auto-alerts when a platform changes structure

---

*This plan is a living document. Update as engines are built and platforms evolve.*
