<div class="login-container">
    <div class="login-card">
        <div class="text-center mb-4">
            <i class="bi bi-search" style="font-size: 2.5rem; color: #3b82f6;"></i>
        </div>
        <h2>Claude Scraper</h2>
        <p class="text-center text-muted mb-4">Sign in to your account</p>

        <form method="POST" action="/login" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email"
                       placeholder="admin@example.com" required autofocus
                       aria-label="Email address">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password"
                       placeholder="Enter your password" required
                       aria-label="Password">
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>
        </form>
    </div>

    <p class="text-center mt-3" style="color: rgba(255,255,255,0.5); font-size: 0.75rem;">
        VisionQuest Services LLC
    </p>
</div>
