<div class="page-header">
    <h1><i class="bi bi-box-arrow-in-right me-2"></i>Imports</h1>
</div>

<div class="content-area">
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($imports)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No imports yet. Complete a scan first, then import items into a platform.</p>
                <a href="/scans" class="btn btn-outline-primary btn-sm">View Scans</a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" aria-label="Imports list">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Scan</th>
                            <th>Platform</th>
                            <th>Store</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($imports as $import): ?>
                        <tr>
                            <td><?= (int)$import['id'] ?></td>
                            <td><?= e($import['scan_title'] ?? 'Scan #' . $import['scan_id']) ?></td>
                            <td><?= e(str_replace('claude_', '', $import['target_platform'])) ?></td>
                            <td><?= e($import['target_store_slug'] ?? '-') ?></td>
                            <td>
                                <span class="text-success"><?= (int)$import['imported_items'] ?></span>
                                <?php if ($import['failed_items'] > 0): ?>
                                / <span class="text-danger"><?= (int)$import['failed_items'] ?> failed</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= e($import['status']) ?>"><?= e(ucfirst($import['status'])) ?></span></td>
                            <td><?= format_date($import['created_at'], 'M j, Y') ?></td>
                            <td>
                                <a href="/imports/<?= (int)$import['id'] ?>" class="btn btn-outline-secondary btn-sm" aria-label="View import details">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
