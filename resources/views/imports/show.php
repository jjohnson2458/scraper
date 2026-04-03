<div class="page-header">
    <h1><i class="bi bi-box-arrow-in-right me-2"></i>Import #<?= (int)$import['id'] ?></h1>
    <a href="/imports" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<div class="content-area">
    <div class="card">
        <div class="card-header">Import Details</div>
        <div class="card-body">
            <div class="row mb-2">
                <div class="col-sm-3 text-muted">Status</div>
                <div class="col-sm-9"><span class="badge badge-<?= e($import['status']) ?>"><?= e(ucfirst($import['status'])) ?></span></div>
            </div>
            <hr>
            <div class="row mb-2">
                <div class="col-sm-3 text-muted">Source Scan</div>
                <div class="col-sm-9">
                    <a href="/scans/<?= (int)$import['scan_id'] ?>"><?= e($scan['title'] ?? 'Scan #' . $import['scan_id']) ?></a>
                </div>
            </div>
            <hr>
            <div class="row mb-2">
                <div class="col-sm-3 text-muted">Target Platform</div>
                <div class="col-sm-9"><?= e($import['target_platform']) ?></div>
            </div>
            <hr>
            <div class="row mb-2">
                <div class="col-sm-3 text-muted">Target Store</div>
                <div class="col-sm-9"><?= e($import['target_store_slug'] ?? 'Default') ?></div>
            </div>
            <hr>
            <div class="row mb-2">
                <div class="col-sm-3 text-muted">Items Imported</div>
                <div class="col-sm-9">
                    <span class="text-success fw-bold"><?= (int)$import['imported_items'] ?></span>
                    / <?= (int)$import['total_items'] ?>
                    <?php if ($import['failed_items'] > 0): ?>
                    (<span class="text-danger"><?= (int)$import['failed_items'] ?> failed</span>)
                    <?php endif; ?>
                </div>
            </div>
            <hr>
            <div class="row mb-2">
                <div class="col-sm-3 text-muted">Date</div>
                <div class="col-sm-9"><?= format_date($import['created_at']) ?></div>
            </div>

            <?php if ($import['error_log']): ?>
            <hr>
            <div class="row mb-2">
                <div class="col-sm-3 text-muted">Errors</div>
                <div class="col-sm-9">
                    <?php $errors = json_decode($import['error_log'], true) ?? []; ?>
                    <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-1 px-2 mb-1 small">
                        <?php if (is_array($err)): ?>
                        <strong><?= e($err['item'] ?? '') ?>:</strong> <?= e($err['error'] ?? '') ?>
                        <?php else: ?>
                        <?= e($err) ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
