<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Claude Scraper - Restaurant Menu Scraping Utility">
    <meta name="csrf-token" content="<?= e($csrfToken ?? '') ?>">
    <meta property="og:title" content="Claude Scraper">
    <meta property="og:description" content="Scrape restaurant menus from URLs or photos">
    <meta property="og:type" content="website">
    <title><?= e($pageTitle ?? 'Claude Scraper') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
    <div class="d-flex" id="app-wrapper">
        <?php if (is_authenticated()): ?>
        <nav id="sidebar" class="sidebar" role="navigation" aria-label="Main navigation">
            <div class="sidebar-header">
                <h4 class="mb-0">
                    <i class="bi bi-search"></i>
                    <span class="sidebar-text">Scraper</span>
                </h4>
            </div>
            <ul class="sidebar-nav list-unstyled" role="menubar">
                <li role="none">
                    <a href="/" class="sidebar-link <?= ($_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/dashboard') ? 'active' : '' ?>" role="menuitem" aria-label="Dashboard">
                        <i class="bi bi-speedometer2"></i>
                        <span class="sidebar-text">Dashboard</span>
                    </a>
                </li>
                <li role="none">
                    <a href="/scans/new" class="sidebar-link <?= str_starts_with($_SERVER['REQUEST_URI'], '/scans/new') ? 'active' : '' ?>" role="menuitem" aria-label="New Scan">
                        <i class="bi bi-plus-circle"></i>
                        <span class="sidebar-text">New Scan</span>
                    </a>
                </li>
                <li role="none">
                    <a href="/scans" class="sidebar-link <?= ($_SERVER['REQUEST_URI'] === '/scans') ? 'active' : '' ?>" role="menuitem" aria-label="Scan History">
                        <i class="bi bi-clock-history"></i>
                        <span class="sidebar-text">Scan History</span>
                    </a>
                </li>
                <li role="none">
                    <a href="/imports" class="sidebar-link <?= str_starts_with($_SERVER['REQUEST_URI'], '/imports') ? 'active' : '' ?>" role="menuitem" aria-label="Imports">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span class="sidebar-text">Imports</span>
                    </a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <div class="sidebar-user" aria-label="Current user">
                    <i class="bi bi-person-circle"></i>
                    <span class="sidebar-text"><?= e(current_user()['username'] ?? 'Admin') ?></span>
                </div>
                <a href="/logout" class="sidebar-link" role="menuitem" aria-label="Log out">
                    <i class="bi bi-box-arrow-left"></i>
                    <span class="sidebar-text">Logout</span>
                </a>
            </div>
        </nav>
        <?php endif; ?>

        <main class="main-content <?= is_authenticated() ? 'with-sidebar' : 'full-width' ?>" role="main">
            <?php $flash = flash_messages(); foreach ($flash as $type => $message): ?>
            <div class="alert alert-<?= $type === 'error' ? 'danger' : e($type) ?> alert-dismissible fade show m-3" role="alert">
                <?= e($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endforeach; ?>
            <?= $content ?? '' ?>
        </main>
    </div>

    <?php if (is_authenticated()): ?>
    <button class="btn btn-dark sidebar-toggle d-lg-none" type="button" aria-label="Toggle navigation" onclick="document.getElementById('sidebar').classList.toggle('show')">
        <i class="bi bi-list"></i>
    </button>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>window.CSRF_TOKEN = '<?= e($csrfToken ?? '') ?>';</script>
    <?php if (isset($useReact) && $useReact): ?>
    <script src="/assets/js/app.js" type="module"></script>
    <?php endif; ?>
    <?php if (isset($pageScripts)): foreach ((array)$pageScripts as $script): ?>
    <script src="<?= e($script) ?>"></script>
    <?php endforeach; endif; ?>
</body>
</html>
