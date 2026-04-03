<div class="page-header">
    <h1><i class="bi bi-box-arrow-in-right me-2"></i>Import Items</h1>
    <a href="/scans/<?= (int)$scan['id'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Scan
    </a>
</div>

<div class="content-area">
    <div class="row g-4">
        <!-- Items Preview -->
        <div class="col-md-7">
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

        <!-- Import Settings -->
        <div class="col-md-5">
            <form method="POST" action="/imports" id="import-form">
                <?= csrf_field() ?>
                <input type="hidden" name="scan_id" value="<?= (int)$scan['id'] ?>">

                <!-- Platform & Store -->
                <div class="card mb-3">
                    <div class="card-header">Target Platform</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="platform" class="form-label">Platform</label>
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
                                <option value="_new_demo">+ Create New Demo Store</option>
                            </select>
                            <div class="form-text">Demo stores use a "-demo" suffix and can be wiped on re-import.</div>
                        </div>
                    </div>
                </div>

                <!-- Store Info -->
                <div class="card mb-3" id="store-info-card">
                    <div class="card-header">Store Info</div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label for="store_name" class="form-label">Store Name</label>
                            <input type="text" name="store_name" id="store_name" class="form-control form-control-sm"
                                   value="<?= e($scan['title'] ?? '') ?>" placeholder="e.g. Say Cheese Pizza">
                        </div>

                        <div class="mb-2">
                            <label for="store_tagline" class="form-label">Tagline</label>
                            <input type="text" name="store_tagline" id="store_tagline" class="form-control form-control-sm"
                                   placeholder="e.g. Best pizza in town">
                        </div>

                        <div class="mb-2">
                            <label for="banner_url" class="form-label">
                                Banner / Hero Image URL
                                <i class="bi bi-info-circle text-muted" title="URL to the restaurant's hero banner image"></i>
                            </label>
                            <input type="url" name="banner_url" id="banner_url" class="form-control form-control-sm"
                                   placeholder="https://example.com/banner.jpg">
                            <div id="banner-preview" class="mt-2" style="display:none;">
                                <img id="banner-preview-img" src="" alt="Banner preview" class="img-fluid rounded" style="max-height: 120px;">
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="website_url" class="form-label">Website URL</label>
                            <input type="url" name="website_url" id="website_url" class="form-control form-control-sm"
                                   value="<?= e($scan['source_type'] === 'url' ? $scan['source_value'] : '') ?>"
                                   placeholder="https://restaurant.com">
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-8">
                                <label for="address_street" class="form-label">Street</label>
                                <input type="text" name="address_street" id="address_street" class="form-control form-control-sm" placeholder="123 Main St">
                            </div>
                            <div class="col-4">
                                <label for="address_zip" class="form-label">Zip</label>
                                <input type="text" name="address_zip" id="address_zip" class="form-control form-control-sm" placeholder="14201">
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-7">
                                <label for="address_city" class="form-label">City</label>
                                <input type="text" name="address_city" id="address_city" class="form-control form-control-sm" placeholder="Buffalo">
                            </div>
                            <div class="col-5">
                                <label for="address_state" class="form-label">State</label>
                                <input type="text" name="address_state" id="address_state" class="form-control form-control-sm" placeholder="NY">
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" name="phone" id="phone" class="form-control form-control-sm" placeholder="(716) 555-1234">
                            </div>
                            <div class="col-6">
                                <label for="store_email" class="form-label">Email</label>
                                <input type="email" name="store_email" id="store_email" class="form-control form-control-sm" placeholder="info@restaurant.com">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100" id="btn-import">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Import <?= count($items) ?> Items as Demo Store
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Load stores when platform changes
document.getElementById('platform')?.addEventListener('change', async function() {
    const slug = this.value;
    const storeSelect = document.getElementById('store_slug');
    storeSelect.innerHTML = '<option value="_new_demo">Loading...</option>';

    if (!slug) {
        storeSelect.innerHTML = '<option value="_new_demo">+ Create New Demo Store</option>';
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
        storeSelect.innerHTML = '<option value="_new_demo">+ Create New Demo Store</option>';
    }

    updateButtonLabel();
});

// Update button label based on store selection
document.getElementById('store_slug')?.addEventListener('change', updateButtonLabel);

function updateButtonLabel() {
    const slug = document.getElementById('store_slug').value;
    const btn = document.getElementById('btn-import');
    const count = <?= count($items) ?>;
    if (slug === '_new_demo') {
        btn.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Import ' + count + ' Items as Demo Store';
    } else if (slug.endsWith('-demo')) {
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Re-import ' + count + ' Items (replaces menu)';
    } else {
        btn.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Import ' + count + ' Items';
    }
}

// Banner URL preview
document.getElementById('banner_url')?.addEventListener('change', function() {
    const url = this.value;
    const preview = document.getElementById('banner-preview');
    const img = document.getElementById('banner-preview-img');
    if (url) {
        img.src = url;
        img.onerror = function() { preview.style.display = 'none'; };
        img.onload = function() { preview.style.display = 'block'; };
    } else {
        preview.style.display = 'none';
    }
});

// Submit spinner
document.getElementById('import-form')?.addEventListener('submit', function() {
    const btn = document.getElementById('btn-import');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Importing...';
});
</script>
