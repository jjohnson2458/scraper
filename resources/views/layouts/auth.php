<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Login - Claude Scraper') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">
    <?php $flash = flash_messages(); foreach ($flash as $type => $message): ?>
    <div class="alert alert-<?= $type === 'error' ? 'danger' : e($type) ?> alert-dismissible fade show mx-3 mt-3" role="alert" style="max-width: 400px; margin: 0 auto;">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endforeach; ?>
    <?= $content ?? '' ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
