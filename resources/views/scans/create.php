<div class="page-header">
    <h1><i class="bi bi-plus-circle me-2"></i>New Scan</h1>
</div>

<div class="content-area">
    <!-- Scan Type Tabs -->
    <ul class="nav nav-tabs scan-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($activeTab ?? 'url') === 'url' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#url-tab" type="button" role="tab" aria-selected="<?= ($activeTab ?? 'url') === 'url' ? 'true' : 'false' ?>">
                <i class="bi bi-globe me-1"></i> Scan URL
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($activeTab ?? '') === 'photo' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#photo-tab" type="button" role="tab" aria-selected="<?= ($activeTab ?? '') === 'photo' ? 'true' : 'false' ?>">
                <i class="bi bi-camera me-1"></i> Scan Photo
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- URL Scan Tab -->
        <div class="tab-pane fade <?= ($activeTab ?? 'url') === 'url' ? 'show active' : '' ?>" id="url-tab" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Scrape a Restaurant Menu from URL</h5>
                    <p class="text-muted small mb-3">
                        Enter the URL of a restaurant menu page. The scraper will extract item names, descriptions, prices, and images.
                    </p>
                    <form method="POST" action="/scans/url" id="url-scan-form">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="url" class="form-label">Menu URL</label>
                            <input type="url" class="form-control scan-url-input" id="url" name="url"
                                   placeholder="https://example-restaurant.com/menu"
                                   required aria-label="Restaurant menu URL">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="btn-scrape-url">
                                <i class="bi bi-search me-1"></i> Scrape Menu
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="btn-preview-url">
                                <i class="bi bi-eye me-1"></i> Preview
                            </button>
                        </div>
                    </form>

                    <!-- Preview Results (populated by React/JS) -->
                    <div id="url-preview-results" class="mt-4" style="display:none;">
                        <hr>
                        <h6>Preview Results</h6>
                        <div id="preview-content"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Photo Scan Tab -->
        <div class="tab-pane fade <?= ($activeTab ?? '') === 'photo' ? 'show active' : '' ?>" id="photo-tab" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Scan Menu from Photo (OCR)</h5>

                    <?php if (empty($tesseractInstalled)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Tesseract OCR not detected.</strong> Photo scanning requires Tesseract to be installed on this system.
                        OCR features may not work until Tesseract is installed.
                    </div>
                    <?php endif; ?>

                    <p class="text-muted small mb-3">
                        Upload or take a photo of a restaurant menu. OCR will extract text and parse it into individual items.
                    </p>

                    <form method="POST" action="/scans/photo" enctype="multipart/form-data" id="photo-scan-form">
                        <?= csrf_field() ?>

                        <div class="photo-capture-area mb-3" id="drop-zone" role="button" tabindex="0"
                             aria-label="Click or drag to upload a menu photo"
                             ondragover="event.preventDefault(); this.classList.add('dragover')"
                             ondragleave="this.classList.remove('dragover')"
                             ondrop="handleDrop(event)">
                            <i class="bi bi-cloud-upload" style="font-size: 2rem; color: #94a3b8;"></i>
                            <p class="mt-2 mb-1">Drag & drop a menu image here</p>
                            <p class="text-muted small">or click to browse / take a photo</p>
                            <input type="file" name="photo" id="photo-input" accept="image/*" capture="environment"
                                   class="d-none" required aria-label="Upload menu photo">
                        </div>

                        <div id="photo-preview" class="mb-3" style="display:none;">
                            <img id="photo-preview-img" src="" alt="Selected menu photo" class="img-fluid rounded" style="max-height: 300px;">
                            <p class="text-muted small mt-1" id="photo-filename"></p>
                        </div>

                        <button type="submit" class="btn btn-success" id="btn-scan-photo" disabled>
                            <i class="bi bi-camera me-1"></i> Process with OCR
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Photo upload handling
document.getElementById('drop-zone').addEventListener('click', function() {
    document.getElementById('photo-input').click();
});

document.getElementById('photo-input').addEventListener('change', function(e) {
    handleFile(e.target.files[0]);
});

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('drop-zone').classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        document.getElementById('photo-input').files = e.dataTransfer.files;
        handleFile(e.dataTransfer.files[0]);
    }
}

function handleFile(file) {
    if (!file || !file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('photo-preview-img').src = e.target.result;
        document.getElementById('photo-preview').style.display = 'block';
        document.getElementById('photo-filename').textContent = file.name;
        document.getElementById('btn-scan-photo').disabled = false;
    };
    reader.readAsDataURL(file);
}

// URL Preview
document.getElementById('btn-preview-url')?.addEventListener('click', async function() {
    const url = document.getElementById('url').value;
    if (!url) { alert('Please enter a URL'); return; }

    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Scanning...';

    try {
        const resp = await fetch('/api/scans/preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN },
            body: JSON.stringify({ url })
        });
        const data = await resp.json();

        const results = document.getElementById('url-preview-results');
        const content = document.getElementById('preview-content');
        results.style.display = 'block';

        if (data.success && data.items.length) {
            let html = '<p class="text-success"><strong>' + data.items.length + ' items found</strong>';
            if (data.title) html += ' from "' + data.title + '"';
            html += '</p><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Name</th><th>Price</th><th>Category</th></tr></thead><tbody>';
            data.items.forEach(function(item) {
                html += '<tr><td>' + (item.name || '') + '</td><td>' + (item.price ? '$' + parseFloat(item.price).toFixed(2) : '-') + '</td><td>' + (item.category || '-') + '</td></tr>';
            });
            html += '</tbody></table></div>';
            content.innerHTML = html;
        } else {
            content.innerHTML = '<p class="text-danger">No items found. ' + (data.error || 'Try a different URL.') + '</p>';
        }
    } catch (err) {
        document.getElementById('preview-content').innerHTML = '<p class="text-danger">Error: ' + err.message + '</p>';
    }

    this.disabled = false;
    this.innerHTML = '<i class="bi bi-eye me-1"></i> Preview';
});

// Show spinner on form submit
document.getElementById('url-scan-form')?.addEventListener('submit', function() {
    document.getElementById('btn-scrape-url').disabled = true;
    document.getElementById('btn-scrape-url').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Scraping...';
});

document.getElementById('photo-scan-form')?.addEventListener('submit', function() {
    document.getElementById('btn-scan-photo').disabled = true;
    document.getElementById('btn-scan-photo').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
});
</script>
