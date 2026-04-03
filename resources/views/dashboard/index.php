<div class="page-header">
    <h1><i class="bi bi-speedometer2 me-2"></i>Dashboard</h1>
    <div>
        <a href="/scans/new" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i> New Scan
        </a>
    </div>
</div>

<div class="content-area">
    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(59,130,246,0.1); color: #3b82f6;">
                    <i class="bi bi-search"></i>
                </div>
                <div>
                    <div class="stat-value"><?= (int)($totalScans ?? 0) ?></div>
                    <div class="stat-label">Total Scans</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: #10b981;">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <div class="stat-value"><?= (int)($completedScans ?? 0) ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(139,92,246,0.1); color: #8b5cf6;">
                    <i class="bi bi-box-arrow-in-right"></i>
                </div>
                <div>
                    <div class="stat-value"><?= (int)($importedScans ?? 0) ?></div>
                    <div class="stat-label">Imported</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body text-center py-4">
                    <i class="bi bi-globe" style="font-size: 2rem; color: #3b82f6;"></i>
                    <h5 class="mt-2 mb-1">Scan a URL</h5>
                    <p class="text-muted small mb-3">Enter a restaurant website URL to extract menu items</p>
                    <a href="/scans/new?tab=url" class="btn btn-outline-primary btn-sm">Start URL Scan</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body text-center py-4">
                    <i class="bi bi-camera" style="font-size: 2rem; color: #10b981;"></i>
                    <h5 class="mt-2 mb-1">Scan a Photo</h5>
                    <p class="text-muted small mb-3">Take or upload a photo of a menu to extract items via OCR</p>
                    <a href="/scans/new?tab=photo" class="btn btn-outline-success btn-sm">Start Photo Scan</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Scans -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Recent Scans</span>
            <a href="/scans" class="btn btn-link btn-sm p-0">View All</a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recentScans)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No scans yet. Start by scanning a URL or photo.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" aria-label="Recent scans">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentScans as $scan): ?>
                        <tr>
                            <td><?= e($scan['title'] ?: 'Untitled Scan') ?></td>
                            <td>
                                <i class="bi bi-<?= $scan['source_type'] === 'url' ? 'globe' : 'camera' ?>"></i>
                                <?= e(ucfirst($scan['source_type'])) ?>
                            </td>
                            <td><?= (int)$scan['item_count'] ?></td>
                            <td>
                                <span class="badge badge-<?= e($scan['status']) ?>"><?= e(ucfirst($scan['status'])) ?></span>
                            </td>
                            <td><?= format_date($scan['created_at']) ?></td>
                            <td>
                                <a href="/scans/<?= (int)$scan['id'] ?>" class="btn btn-outline-secondary btn-sm" aria-label="View scan details">
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
