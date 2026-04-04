-- Migration: Seed platforms registry
-- Date: 2026-04-03

INSERT INTO `platforms` (`name`, `slug`, `category`, `tier`, `scrape_method`, `url_pattern`, `engine_class`, `is_active`, `notes`) VALUES
-- Tier 1: Online Ordering & POS
('Toast', 'toast', 'ordering_pos', 'high', 'selenium', 'toasttab\\.com|toast\\.restaurant', 'App\\Services\\Engines\\ToastEngine', 1, 'Largest restaurant POS; Cloudflare protected, needs Selenium'),
('Square Online', 'square', 'ordering_pos', 'high', 'dom', 'squareup\\.com|square\\.site', 'App\\Services\\Engines\\SquareEngine', 0, 'Square-hosted restaurant storefronts'),
('Clover', 'clover', 'ordering_pos', 'medium', 'dom', 'clover\\.com', 'App\\Services\\Engines\\CloverEngine', 0, 'Clover online ordering pages'),
('ChowNow', 'chownow', 'ordering_pos', 'high', 'api', 'chownow\\.com|direct\\.chownow\\.com', 'App\\Services\\Engines\\ChowNowEngine', 0, 'Direct-ordering; clean JSON endpoints'),
('Olo', 'olo', 'ordering_pos', 'medium', 'api', 'olo\\.com|ordering\\.app', 'App\\Services\\Engines\\OloEngine', 0, 'Powers ordering for chains'),
('BentoBox', 'bentobox', 'website_builder', 'medium', 'dom', 'bentobox\\.com|getbento\\.com', 'App\\Services\\Engines\\BentoBoxEngine', 0, 'Restaurant website builder with menu pages'),
('Popmenu', 'popmenu', 'website_builder', 'medium', 'api_dom', 'popmenu\\.com', 'App\\Services\\Engines\\PopmenuEngine', 0, 'Menu hosting with structured data'),
('Owner.com', 'owner', 'ordering_pos', 'low', 'dom', 'owner\\.com', 'App\\Services\\Engines\\OwnerEngine', 0, 'Newer entrant'),
('SpotOn', 'spoton', 'ordering_pos', 'low', 'dom', 'spoton\\.com', 'App\\Services\\Engines\\SpotOnEngine', 0, 'POS with online ordering'),
('Lightspeed', 'lightspeed', 'ordering_pos', 'low', 'dom', 'lightspeed\\.com|lsku\\.io', 'App\\Services\\Engines\\LightspeedEngine', 0, 'Restaurant POS/ordering'),

-- Tier 2: Delivery / Marketplace
('DoorDash', 'doordash', 'delivery_marketplace', 'high', 'api', 'doordash\\.com', 'App\\Services\\Engines\\DoorDashEngine', 0, 'Menu JSON in page source'),
('Uber Eats', 'ubereats', 'delivery_marketplace', 'high', 'api', 'ubereats\\.com', 'App\\Services\\Engines\\UberEatsEngine', 0, 'GraphQL-backed; hydration state'),
('Grubhub', 'grubhub', 'delivery_marketplace', 'high', 'api', 'grubhub\\.com', 'App\\Services\\Engines\\GrubhubEngine', 0, 'REST API endpoints'),
('Postmates', 'postmates', 'delivery_marketplace', 'low', 'api', 'postmates\\.com', 'App\\Services\\Engines\\UberEatsEngine', 0, 'Same backend as Uber Eats'),
('Seamless', 'seamless', 'delivery_marketplace', 'low', 'api', 'seamless\\.com', 'App\\Services\\Engines\\GrubhubEngine', 0, 'Same backend as Grubhub'),
('Caviar', 'caviar', 'delivery_marketplace', 'low', 'api', 'trycaviar\\.com', 'App\\Services\\Engines\\DoorDashEngine', 0, 'Same backend as DoorDash'),

-- Tier 3: Website Builders / Menu Hosts
('SinglePlatform', 'singleplatform', 'website_builder', 'medium', 'api', 'singleplatform\\.com', 'App\\Services\\Engines\\SinglePlatformEngine', 0, 'Tripadvisor-owned menu data'),
('Menufy', 'menufy', 'website_builder', 'medium', 'dom', 'menufy\\.com', 'App\\Services\\Engines\\MenufyEngine', 0, 'Online ordering + menu hosting'),
('GloriaFood', 'gloriafood', 'website_builder', 'low', 'dom', 'gloriafood\\.com', 'App\\Services\\Engines\\GloriaFoodEngine', 0, 'Free restaurant ordering'),
('9Fold', 'ninefold', 'website_builder', 'low', 'dom', '9fold\\.me', 'App\\Services\\Engines\\NineFoldEngine', 0, 'Menu digitization'),
('Menulog', 'menulog', 'website_builder', 'low', 'dom', 'menulog\\.com\\.au', 'App\\Services\\Engines\\MenulogEngine', 0, 'AU/NZ market'),

-- Tier 4: Data Aggregators
('Yelp', 'yelp', 'data_aggregator', 'medium', 'dom', 'yelp\\.com', 'App\\Services\\Engines\\YelpEngine', 0, 'Menu tabs on business pages'),
('Google Maps', 'google', 'data_aggregator', 'medium', 'dom', 'google\\.com/maps|maps\\.google', 'App\\Services\\Engines\\GoogleMapsEngine', 0, 'Menu data from business profiles'),
('TripAdvisor', 'tripadvisor', 'data_aggregator', 'low', 'dom', 'tripadvisor\\.com', 'App\\Services\\Engines\\TripAdvisorEngine', 0, 'Menu sections on restaurant pages'),
('Allmenus', 'allmenus', 'data_aggregator', 'low', 'dom', 'allmenus\\.com', 'App\\Services\\Engines\\AllmenusEngine', 0, 'Aggregated menu database')

ON DUPLICATE KEY UPDATE name = VALUES(name);
