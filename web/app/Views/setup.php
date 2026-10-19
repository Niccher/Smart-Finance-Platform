<?php
$versionData = [];
if (file_exists(APPPATH . 'Config/version.json')) {
    $versionData = json_decode(file_get_contents(APPPATH . 'Config/version.json'), true);
}
$systemVersion = $versionData['version'] ?? '3.2.0';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Setup Guide — Mpesa Analyzer</title>
    <meta name="description" content="Step-by-step setup guide for Mpesa Analyzer. Deploy web dashboard, pair Android app via CameraX QR scanner, activate local GGUF model presets, and sync SMS.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= base_url('setup') ?>">

    <!-- Favicon & PWA Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('favicon.png?v=' . $systemVersion) ?>">
    <link rel="icon" type="image/x-icon" href="<?= base_url('favicon.ico?v=' . $systemVersion) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= base_url('apple-touch-icon.png?v=' . $systemVersion) ?>">
    <link rel="manifest" href="<?= base_url('site.webmanifest?v=' . $systemVersion) ?>">

    <meta property="og:title" content="Setup Guide — Mpesa Analyzer">
    <meta property="og:description" content="Deploy web app, pair Android companion with CameraX QR, configure local GGUF models.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= base_url('setup') ?>">
    <meta property="og:image" content="<?= base_url('assets/img/logo.png') ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="<?= base_url('assets/img/logo.png') ?>">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('ace_theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= base_url('assets/ace/css/ace-theme.css') ?>" rel="stylesheet" />

    <style>
        :root { --primary: #438EB9; --primary-dark: #222A2D; --secondary: #E8F2F8; --dark: #1A1A2E; --light: #F8F9FA; --radius: 6px; }
        body { font-family: 'Outfit', sans-serif; background-color: var(--light); color: var(--dark); }
        .navbar { padding: 1.25rem 0; background: transparent; transition: all 0.3s ease; }
        .navbar.scrolled { background: rgba(255,255,255,0.92); backdrop-filter: blur(12px); padding: 0.75rem 0; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        [data-bs-theme="dark"] .navbar.scrolled { background: rgba(15, 23, 42, 0.92); }
        .navbar-brand { font-weight: 800; font-size: 1.5rem; letter-spacing: -0.5px; }
        .nav-link { font-weight: 500; transition: color 0.2s; }
        .nav-link:hover, .nav-link.active { color: var(--primary) !important; }
        .nav-link.active { position: relative; }
        .nav-link.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 2px; background: var(--primary); border-radius: 1px; }
        .btn-primary { background-color: var(--primary); border-color: var(--primary); padding: 0.7rem 1.8rem; font-weight: 600; border-radius: var(--radius); transition: all 0.3s; }
        .btn-primary:hover { background-color: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(67,142,185,0.35); }
        .btn-outline-primary { color: var(--primary); border-color: var(--primary); padding: 0.7rem 1.8rem; font-weight: 600; border-radius: var(--radius); }
        .btn-outline-primary:hover { background-color: var(--primary); color: #fff; transform: translateY(-2px); }
        .page-header { padding: 120px 0 60px; background: linear-gradient(135deg, #fff 0%, var(--secondary) 100%); }
        [data-bs-theme="dark"] .page-header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
        .glass-card { background: rgba(255,255,255,0.8); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.4); border-radius: var(--radius); padding: 2.5rem; transition: all 0.3s; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        [data-bs-theme="dark"] .glass-card { background: rgba(30, 41, 59, 0.8); border-color: rgba(255,255,255,0.1); }
        .glass-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.08); }
        .step-number { width: 44px; height: 44px; background: var(--primary); color: #fff; border-radius: var(--radius); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.25rem; flex-shrink: 0; }
        .footer { background: var(--dark); color: #fff; padding: 4rem 0 2rem; }
        .footer a { color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.2s; }
        .footer a:hover { color: #fff; }
        @media (max-width: 768px) { .page-header { padding: 100px 0 40px; } }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand text-primary" href="<?= base_url() ?>">
                <i class="fa-solid fa-wallet me-2"></i>Mpesa Analyzer
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="<?= base_url() ?>">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('android-app') ?>">Android App</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('ml-backend') ?>">ML Backend</a></li>
                    <li class="nav-item"><a class="nav-link active" href="<?= base_url('setup') ?>">Setup</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('faq') ?>">FAQ</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-secondary btn-sm px-2.5 py-1" id="themeToggleBtn" type="button" title="Toggle Light/Dark Theme">
                        <i class="fa-solid fa-moon"></i>
                    </button>
                    <a href="<?= url_to('login') ?>" class="btn btn-outline-primary btn-sm">Sign In</a>
                    <a href="<?= url_to('register') ?>" class="btn btn-primary btn-sm">Get Started</a>
                </div>
            </div>
        </div>
    </nav>

    <section class="page-header">
        <div class="container">
            <div class="text-center">
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 mb-3 fw-semibold">
                    <i class="fa-solid fa-book-open me-1"></i> Fast-Track Implementation Guide
                </span>
                <h1 class="fw-800 mb-3" style="font-size: 2.75rem; font-weight: 800; letter-spacing: -0.5px;">Setup Guide</h1>
                <p class="lead text-muted mx-auto mb-0" style="max-width: 620px;">Deploy the WebApp, pair your Android device via CameraX QR, configure local GGUF models, and unlock automated financial intelligence in 4 easy steps.</p>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container py-4">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="glass-card p-4 p-lg-5">
                        
                        <!-- Step 1 -->
                        <div class="d-flex gap-4 mb-5">
                            <span class="step-number">1</span>
                            <div>
                                <h4 class="fw-bold mb-2">Deploy & Register Web Dashboard</h4>
                                <p class="text-muted mb-3">Launch the platform on your infrastructure (Docker Compose or cloud host such as Railway) and register your administrative account.</p>
                                <ol class="text-muted small" style="line-height: 2;">
                                    <li>If running self-hosted, clone repository and configure your <code>.env</code> database credentials.</li>
                                    <li>Run <code>docker compose up -d</code> to spin up WebApp (port <code>80</code>), MySQL 8.4 (port <code>3306</code>), ML Service (port <code>8001</code> / <code>9050</code>), Redis 7 (port <code>6379</code>), and MinIO Object Storage (API port <code>9000</code>, Console port <code>9001</code>).</li>
                                    <li>Verify the MinIO Web Console at <a href="http://localhost:9001" target="_blank" class="fw-semibold">http://localhost:9001</a> (default: <code>admin_mpesa</code>) where the 4 isolated buckets (<code>mpesa-payloads</code>, <code>mpesa-backups</code>, <code>mpesa-models</code>, <code>mpesa-exports</code>) are automatically provisioned.</li>
                                    <li>Navigate to <a href="<?= url_to('register') ?>" class="fw-semibold">the Registration Page</a> and create your master account.</li>
                                    <li>Log into the dashboard to access the Control Center.</li>
                                </ol>
                                <div class="bg-light p-3 rounded-3 small">
                                    <i class="fa-solid fa-circle-info text-primary me-2"></i>
                                    <strong>Tip:</strong> The web dashboard includes automatic schema migration on startup, and MinIO automatically applies a 90-day ILM retention policy to incoming SMS payloads.
                                </div>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div class="d-flex gap-4 mb-5">
                            <span class="step-number">2</span>
                            <div>
                                <h4 class="fw-bold mb-2">Install Companion App & Pair via QR Code</h4>
                                <p class="text-muted mb-3">Install the Android companion APK and pair it seamlessly with your account using the vertical CameraX barcode scanner.</p>
                                <ol class="text-muted small" style="line-height: 2;">
                                    <li>Download and install the APK on your Android device (Android 8.0+ supported).</li>
                                    <li>In the WebApp dashboard, navigate to <strong>Control Center &rarr; Devices</strong>.</li>
                                    <li>Click <strong>Pair New Device</strong> to generate a unique pairing QR code with your encrypted server token.</li>
                                    <li>Open the Android app, tap <strong>Scan Pairing QR</strong>, and align the camera view with your screen.</li>
                                    <li>The app automatically saves the server URL, performs cryptographic handshake, and stores the token securely.</li>
                                </ol>
                                <div class="bg-light p-3 rounded-3 small">
                                    <i class="fa-solid fa-shield-halved text-success me-2"></i>
                                    <strong>Security:</strong> The server stores only the SHA-256 hash of your device token. You can revoke any device instantly from the dashboard.
                                </div>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div class="d-flex gap-4 mb-5">
                            <span class="step-number">3</span>
                            <div>
                                <h4 class="fw-bold mb-2">Activate Local GGUF Model Preset</h4>
                                <p class="text-muted mb-3">Enable local semantic reasoning via the ML management console without needing external LLM APIs.</p>
                                <ol class="text-muted small" style="line-height: 2;">
                                    <li>In the WebApp sidebar, go to <strong>Admin &rarr; ML Management &rarr; Models</strong>.</li>
                                    <li>Review available model presets (e.g. <strong>Qwen 2.5 3B Instruct Q4_K_M</strong>).</li>
                                    <li>Click <strong>Download / Activate</strong> — the system verifies disk space and memory headroom before initializing.</li>
                                    <li>Verify model status in <strong>Admin &rarr; Telemetry</strong> to confirm <code>llama-server</code> process health and RSS memory usage.</li>
                                </ol>
                                <div class="bg-light p-3 rounded-3 small">
                                    <i class="fa-solid fa-bolt text-warning me-2"></i>
                                    <strong>Performance:</strong> The ML microservice uses AVX2 CPU vectorization automatically, running efficiently even on standard 4-core VPS instances.
                                </div>
                            </div>
                        </div>

                        <!-- Step 4 -->
                        <div class="d-flex gap-4">
                            <span class="step-number">4</span>
                            <div>
                                <h4 class="fw-bold mb-2">Sync SMS & Run Semantic Analysis</h4>
                                <p class="text-muted mb-3">Initiate your initial SMS batch upload and generate your structured financial ledger.</p>
                                <ol class="text-muted small" style="line-height: 2;">
                                    <li>On the Android app, tap <strong>Sync SMS History</strong> to queue existing mobile money messages into local SQLite.</li>
                                    <li>WorkManager batches and AES-256 encrypts the payload, transmitting it to <code>/api/v1/upload</code>, which stores blobs in MinIO S3 (<code>mpesa-payloads</code> bucket with automated 90-day ILM retention) alongside local redundancy.</li>
                                    <li>In the WebApp dashboard, click <strong>Rescan / Analyze</strong> to trigger the semantic classification cycle.</li>
                                    <li>Monitor the live modal progress dialog as messages are categorized into sent, received, utilities, airtime, and Fuliza.</li>
                                    <li>Explore your interactive spending charts, financial health score, and exportable ledgers.</li>
                                </ol>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-5">
                    <h4 class="text-white fw-bold mb-3"><i class="fa-solid fa-wallet me-2"></i>Mpesa Analyzer</h4>
                    <p class="opacity-75 small">AI-powered financial intelligence platform. Android app, local GGUF classification microservice, and interactive web dashboard.</p>
                </div>
                <div class="col-md-2">
                    <h6 class="text-white fw-bold mb-3">Platform</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="<?= base_url('android-app') ?>">Android App</a></li>
                        <li class="mb-2"><a href="<?= base_url('ml-backend') ?>">ML Backend</a></li>
                        <li class="mb-2"><a href="<?= base_url('setup') ?>">Setup Guide</a></li>
                        <li class="mb-2"><a href="<?= base_url('faq') ?>">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h6 class="text-white fw-bold mb-3">Account</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="<?= url_to('login') ?>">Sign In</a></li>
                        <li class="mb-2"><a href="<?= url_to('register') ?>">Register</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6 class="text-white fw-bold mb-3">Tech Stack</h6>
                    <div class="d-flex flex-wrap gap-1 small opacity-75">
                        <span class="badge bg-light text-dark">CodeIgniter 4</span>
                        <span class="badge bg-light text-dark">FastAPI</span>
                        <span class="badge bg-light text-dark">MinIO S3</span>
                        <span class="badge bg-light text-dark">Redis 7</span>
                        <span class="badge bg-light text-dark">Llama.cpp</span>
                        <span class="badge bg-light text-dark">Qwen 2.5</span>
                        <span class="badge bg-light text-dark">MySQL 8.4</span>
                        <span class="badge bg-light text-dark">CameraX</span>
                        <span class="badge bg-light text-dark">Docker</span>
                    </div>
                </div>
            </div>
            <hr class="mt-4 mb-4 opacity-25">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start"><p class="mb-0 opacity-75 small">&copy; <?= date('Y') ?> Mpesa Analyzer v<?= esc($systemVersion) ?>. All rights reserved.</p></div>
                <div class="col-md-6 text-center text-md-end mt-2 mt-md-0"><p class="mb-0 opacity-75 small">Built with <i class="fa-solid fa-heart text-danger"></i> for financial freedom</p></div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.addEventListener('scroll', function() { document.querySelector('.navbar').classList.toggle('scrolled', window.scrollY > 50); });

        document.addEventListener('DOMContentLoaded', function() {
            const themeBtn = document.getElementById('themeToggleBtn');
            const html = document.documentElement;

            function updateIcon(theme) {
                if (themeBtn) {
                    const icon = themeBtn.querySelector('i');
                    if (icon) {
                        icon.className = theme === 'dark' ? 'fa-solid fa-sun text-warning' : 'fa-solid fa-moon';
                    }
                }
            }

            const currentTheme = localStorage.getItem('ace_theme') || 'light';
            updateIcon(currentTheme);

            if (themeBtn) {
                themeBtn.addEventListener('click', function() {
                    const newTheme = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                    html.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('ace_theme', newTheme);
                    updateIcon(newTheme);
                });
            }
        });
    </script>
</body>
</html>\n