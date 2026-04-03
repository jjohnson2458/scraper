<div class="page-header">
    <h1>
        <i class="bi bi-<?= ($scan['source_type'] ?? 'url') === 'url' ? 'globe' : 'camera' ?> me-2"></i>
        <?= e($scan['title'] ?: 'Scan #' . $scan['id']) ?>
    </h1>
    <div class="d-flex gap-2">
        <?php if ($scan['status'] === 'complete'): ?>
        <a href="/scans/<?= (int)$scan['id'] ?>/export" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
        <a href="/imports/new/<?= (int)$scan['id'] ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-box-arrow-in-right me-1"></i> Import to Platform
        </a>
        <?php endif; ?>
        <a href="/scans" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="content-area">
    <!-- Scan Info -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Scan Details</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-3 text-muted">Source</div>
                        <div class="col-sm-9">
                            <?php if ($scan['source_type'] === 'url'): ?>
                            <a href="<?= e($scan['source_value']) ?>" target="_blank" rel="noopener">
                                <?= e(truncate($scan['source_value'], 60)) ?>
                                <i class="bi bi-box-arrow-up-right ms-1"></i>
                            </a>
                            <?php else: ?>
                            <img src="<?= e($scan['source_value']) ?>" alt="Scanned photo" class="img-thumbnail" style="max-height: 150px;">
                            <?php endif; ?>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-3 text-muted">Status</div>
                        <div class="col-sm-9"><span class="badge badge-<?= e($scan['status']) ?>"><?= e(ucfirst($scan['status'])) ?></span></div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-3 text-muted">Items Found</div>
                        <div class="col-sm-9"><?= (int)$scan['item_count'] ?></div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-3 text-muted">Scanned</div>
                        <div class="col-sm-9"><?= format_date($scan['created_at']) ?></div>
                    </div>
                    <?php if ($scan['error_message']): ?>
                    <hr>
                    <div class="row">
                        <div class="col-sm-3 text-muted">Error</div>
                        <div class="col-sm-9 text-danger"><?= e($scan['error_message']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Categories</div>
                <div class="card-body">
                    <?php if (empty($categories)): ?>
                    <p class="text-muted small">No categories detected.</p>
                    <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($categories as $cat): ?>
                        <li class="mb-1"><i class="bi bi-tag me-1 text-muted"></i><?= e($cat) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Extracted Items (<?= count($items) ?>)</span>
            <button class="btn btn-outline-primary btn-sm" id="btn-save-items" style="display:none;">
                <i class="bi bi-save me-1"></i> Save Changes
            </button>
        </div>
        <div class="card-body p-0">
            <?php if (empty($items)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No items extracted.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="items-table" aria-label="Extracted menu items">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="select-all" checked aria-label="Select all items">
                            </th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Price</th>
                            <th>Category</th>
                            <th>Image</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr data-item-id="<?= (int)$item['id'] ?>">
                            <td>
                                <input type="checkbox" class="item-select" <?= $item['is_selected'] ? 'checked' : '' ?>
                                       aria-label="Select <?= e($item['name']) ?>">
                            </td>
                            <td class="editable-cell" contenteditable="true" data-field="name"><?= e($item['name']) ?></td>
                            <td class="editable-cell" contenteditable="true" data-field="description"><?= e($item['description'] ?? '') ?></td>
                            <td class="editable-cell" contenteditable="true" data-field="price"><?= $item['price'] !== null ? number_format((float)$item['price'], 2) : '' ?></td>
                            <td class="editable-cell" contenteditable="true" data-field="category"><?= e($item['category'] ?? '') ?></td>
                            <td>
                                <?php if ($item['image_url'] || $item['image_path']): ?>
                                <img src="<?= e($item['image_path'] ?: $item['image_url']) ?>" alt="<?= e($item['name']) ?>" class="img-thumbnail" style="max-height: 50px;">
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Scan -->
    <div class="mt-3 text-end">
        <form method="POST" action="/scans/<?= (int)$scan['id'] ?>" onsubmit="return confirm('Delete this scan and all its items?')">
            <?= csrf_field() ?>
            <?= method_field('DELETE') ?>
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash me-1"></i> Delete Scan
            </button>
        </form>
    </div>
</div>

<script>
// Track edits
let hasEdits = false;
document.querySelectorAll('.editable-cell').forEach(function(cell) {
    cell.addEventListener('input', function() {
        hasEdits = true;
        document.getElementById('btn-save-items').style.display = '';
    });
});

// Select All
document.getElementById('select-all')?.addEventListener('change', function() {
    document.querySelectorAll('.item-select').forEach(cb => cb.checked = this.checked);
    hasEdits = true;
    document.getElementById('btn-save-items').style.display = '';
});

// Save changes
document.getElementById('btn-save-items')?.addEventListener('click', async function() {
    const rows = document.querySelectorAll('#items-table tbody tr');
    const items = [];
    rows.forEach(function(row) {
        items.push({
            name: row.querySelector('[data-field="name"]').textContent.trim(),
            description: row.querySelector('[data-field="description"]').textContent.trim() || null,
            price: parseFloat(row.querySelector('[data-field="price"]').textContent.trim()) || null,
            category: row.querySelector('[data-field="category"]').textContent.trim() || null,
            is_selected: row.querySelector('.item-select').checked ? 1 : 0,
        });
    });

    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

    try {
        const resp = await fetch('/scans/<?= (int)$scan['id'] ?>/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN },
            body: JSON.stringify({ _csrf_token: window.CSRF_TOKEN, items })
        });
        const data = await resp.json();
        if (data.success) {
            this.innerHTML = '<i class="bi bi-check me-1"></i> Saved!';
            setTimeout(() => {
                this.innerHTML = '<i class="bi bi-save me-1"></i> Save Changes';
                this.style.display = 'none';
                this.disabled = false;
            }, 2000);
        }
    } catch (err) {
        alert('Save failed: ' + err.message);
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-save me-1"></i> Save Changes';
    }
});
</script>
