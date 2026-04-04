# Menuscaping — Test URLs by Platform

Use these URLs to test each engine. Pick one per platform and run a scan.

## Tier 1 — Online Ordering & POS

| # | Platform | Test URL | Notes |
|---|----------|----------|-------|
| 1 | **Toast** | `https://order.toasttab.com/online/say-cheese-pizza-1771-love-rd` | Already tested — Say Cheese Pizza |
| 2 | **Toast** | `https://order.toasttab.com/online/dinosaur-bar-b-que-246-west-willow-street` | Dinosaur BBQ, Syracuse |
| 3 | **Square Online** | `https://square.site/book/LBFP7BYMHP4XA/gorditos-mexican-food-seattle-wa` | Gorditos, Seattle |
| 4 | **ChowNow** | `https://direct.chownow.com/order/restaurant/the-halal-guys/37373` | The Halal Guys |
| 5 | **Clover** | `https://www.clover.com/online-ordering/demo` | Clover demo store |
| 6 | **Olo** | `https://order.sweetgreen.com/sweetgreen` | Sweetgreen (Olo-powered) |

## Tier 2 — Delivery / Marketplace

| # | Platform | Test URL | Notes |
|---|----------|----------|-------|
| 7 | **DoorDash** | `https://www.doordash.com/store/mcdonald's-buffalo-23614/` | McDonald's Buffalo |
| 8 | **DoorDash** | `https://www.doordash.com/store/chipotle-mexican-grill-buffalo-915/` | Chipotle Buffalo |
| 9 | **Uber Eats** | `https://www.ubereats.com/store/five-guys-burgers-and-fries-transit-rd/X6z9FQlYQ_qbXxW0PAQ8dw` | Five Guys |
| 10 | **Grubhub** | `https://www.grubhub.com/restaurant/mighty-taco-3599-union-rd-buffalo/267992` | Mighty Taco, Buffalo |
| 11 | **Seamless** | `https://www.seamless.com/menu/joe-and-pats-pizzeria-168-1st-ave-new-york/283631` | Joe & Pat's, NYC |

## Tier 3 — Website Builders / Menu Hosts

| # | Platform | Test URL | Notes |
|---|----------|----------|-------|
| 12 | **BentoBox** | `https://www.themeatball.com/menus/` | The Meatball Shop (BentoBox site) |
| 13 | **Popmenu** | `https://popmenu.com/restaurants` | Pick any restaurant from directory |
| 14 | **Menufy** | `https://www.menufy.com/restaurants` | Pick any restaurant from directory |
| 15 | **SinglePlatform** | Test via Yelp/TripAdvisor menu tabs | Menu data often sourced from SP |

## Tier 4 — Data Aggregators

| # | Platform | Test URL | Notes |
|---|----------|----------|-------|
| 16 | **Yelp** | `https://www.yelp.com/menu/duffs-famous-wings-buffalo` | Duff's Famous Wings, Buffalo |
| 17 | **Yelp** | `https://www.yelp.com/menu/anchor-bar-buffalo-2` | Anchor Bar, Buffalo |
| 18 | **Google Maps** | `https://www.google.com/maps/place/La+Nova+Pizzeria/@42.9078,-78.8745` | La Nova, Buffalo |
| 19 | **TripAdvisor** | `https://www.tripadvisor.com/Restaurant_Review-g60974-d478223-Reviews-Anchor_Bar-Buffalo_New_York.html` | Anchor Bar |
| 20 | **Allmenus** | `https://www.allmenus.com/ny/buffalo/` | Pick any Buffalo restaurant |

## Buffalo-Area Restaurants (for demo purposes)

These are good candidates for demoing the scraper → Buffalo Eats import flow:

| Restaurant | Platform | URL |
|-----------|----------|-----|
| Say Cheese Pizza | Toast | `https://order.toasttab.com/online/say-cheese-pizza-1771-love-rd` |
| Duff's Famous Wings | Yelp | `https://www.yelp.com/menu/duffs-famous-wings-buffalo` |
| Anchor Bar | Yelp/TA | `https://www.yelp.com/menu/anchor-bar-buffalo-2` |
| Mighty Taco | Grubhub | `https://www.grubhub.com/restaurant/mighty-taco-3599-union-rd-buffalo/267992` |
| McDonald's Buffalo | DoorDash | `https://www.doordash.com/store/mcdonald's-buffalo-23614/` |
| La Nova Pizzeria | Google | Search "La Nova Pizzeria Buffalo" on Google Maps |
| Chipotle Buffalo | DoorDash | `https://www.doordash.com/store/chipotle-mexican-grill-buffalo-915/` |

## Testing Checklist

For each platform test:
- [ ] Auto-detect identifies the correct platform
- [ ] Items extracted with names and prices
- [ ] Categories assigned correctly
- [ ] Images captured (where available)
- [ ] Restaurant name detected
- [ ] No duplicate items
- [ ] Import to Buffalo Eats creates proper demo store
