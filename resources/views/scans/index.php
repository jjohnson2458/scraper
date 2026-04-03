<div class="page-header">
    <h1><i class="bi bi-clock-history me-2"></i>Scan History</h1>
    <a href="/scans/new" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> New Scan
    </a>
</div>

<div class="content-area">
    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="/scans" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= e($currentSearch ?? '') ?>" aria-label="Search scans">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm" aria-label="Filter by status">
                        <option value="">All Statuses</option>
                        <?php foreach (['pending', 'processing', 'complete', 'failed', 'imported'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($currentStatus ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select form-select-sm" aria-label="Filter by type">
                        <option value="">All Types</option>
                        <option value="url" <?= ($currentType ?? '') === 'url' ? 'selected' : '' ?>>URL</option>
                        <option value="photo" <?= ($currentType ?? '') === 'photo' ? 'selected' : '' ?>>Photo</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">Filter</button>
                    <a href="/scans" class="btn btn-outline-secondary btn-sm">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Scans Table -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($scans)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No scans found.</p>
                <a href="/scans/new" class="btn btn-primary btn-sm">Create your first scan</a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" aria-label="Scans list">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scans as $scan): ?>
                        <tr>
                            <td><?= (int)$scan['id'] ?></td>
                            <td>
                                <a href="/scans/<?= (int)$scan['id'] ?>" class="text-decoration-none">
                                    <?= e($scan['title'] ?: 'Untitled Scan') ?>
                                </a>
                            </td>
                            <td>
                                <i class="bi bi-<?= $scan['source_type'] === 'url' ? 'globe' : 'camera' ?>"></i>
                                <?= e(ucfirst($scan['source_type'])) ?>
                            </td>
                            <td><?= (int)$scan['item_count'] ?></td>
                            <td><span class="badge badge-<?= e($scan['status']) ?>"><?= e(ucfirst($scan['status'])) ?></span></td>
                            <td><?= format_date($scan['created_at'], 'M j, Y') ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="/scans/<?= (int)$scan['id'] ?>" class="btn btn-outline-secondary" aria-label="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($scan['status'] === 'complete'): ?>
                                    <a href="/scans/<?= (int)$scan['id'] ?>/export" class="btn btn-outline-secondary" aria-label="Export CSV">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <a href="/imports/new/<?= (int)$scan['id'] ?>" class="btn btn-outline-primary" aria-label="Import">
                                        <i class="bi bi-box-arrow-in-right"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (($pagination['lastPage'] ?? 1) > 1): ?>
            <nav class="d-flex justify-content-center py-3" aria-label="Scan pagination">
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $pagination['lastPage']; $i++): ?>
                    <li class="page-item <?= $i === $pagination['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="/scans?page=<?= $i ?>&status=<?= e($currentStatus ?? '') ?>&type=<?= e($currentType ?? '') ?>&search=<?= e($currentSearch ?? '') ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
