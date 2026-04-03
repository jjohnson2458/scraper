<div class="page-header">
    <h1><i class="bi bi-box-arrow-in-right me-2"></i>Import Items</h1>
    <a href="/scans/<?= (int)$scan['id'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Scan
    </a>
</div>

<div class="content-area">
    <div class="row g-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Items to Import (<?= count($items) ?>)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" aria-label="Items to import">
                            <thead>
                                <tr><th>Name</th><th>Price</th><th>Category</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= e($item['name']) ?></td>
                                    <td><?= $item['price'] ? format_price((float)$item['price']) : '-' ?></td>
                                    <td><?= e($item['category'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Import Settings</div>
                <div class="card-body">
                    <form method="POST" action="/imports" id="import-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="scan_id" value="<?= (int)$scan['id'] ?>">

                        <div class="mb-3">
                            <label for="platform" class="form-label">Target Platform</label>
                            <select name="platform" id="platform" class="form-select" required aria-label="Select target platform">
                                <option value="">Choose a platform...</option>
                                <?php foreach ($platforms as $p): ?>
                                <option value="<?= e($p['slug']) ?>" <?= !$p['available'] ? 'disabled' : '' ?>>
                                    <?= e($p['name']) ?>
                                    <?= !$p['available'] ? ' (not installed)' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="store_slug" class="form-label">Target Store</label>
                            <select name="store_slug" id="store_slug" class="form-select" aria-label="Select target store">
                                <option value="default">Default Store</option>
                            </select>
                            <div class="form-text">Select a store/location within the platform.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" id="btn-import">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Import <?= count($items) ?> Items
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load stores when platform changes
document.getElementById('platform')?.addEventListener('change', async function() {
    const slug = this.value;
    const storeSelect = document.getElementById('store_slug');
    storeSelect.innerHTML = '<option value="default">Loading...</option>';

    if (!slug) {
        storeSelect.innerHTML = '<option value="default">Default Store</option>';
        return;
    }

    try {
        const resp = await fetch('/api/platforms/' + slug + '/stores', {
            headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN }
        });
        const data = await resp.json();
        storeSelect.innerHTML = '';
        (data.stores || []).forEach(function(store) {
            const opt = document.createElement('option');
            opt.value = store.slug;
            opt.textContent = store.name;
            storeSelect.appendChild(opt);
        });
    } catch (err) {
        storeSelect.innerHTML = '<option value="default">Default Store</option>';
    }
});

document.getElementById('import-form')?.addEventListener('submit', function() {
    document.getElementById('btn-import').disabled = true;
    document.getElementById('btn-import').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Importing...';
});
</script>
