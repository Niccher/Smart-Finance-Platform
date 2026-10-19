<?php
$versionData = [];
if (file_exists(APPPATH . 'Config/version.json')) {
    $versionData = json_decode(file_get_contents(APPPATH . 'Config/version.json'), true);
}
$systemVersion = $versionData['version'] ?? '3.5.0';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Android App — Mpesa Analyzer</title>
    <meta name="description" content="Download the Mpesa Analyzer Android companion app. Vertical CameraX QR pairing, vector assets, SQLite queue, and AES-256 encrypted batch sync to /api/v1/upload.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= base_url('android-app') ?>">

    <!-- Favicon & PWA Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('favicon.png?v=' . $systemVersion) ?>">
    <link rel="icon" type="image/x-icon" href="<?= base_url('favicon.ico?v=' . $systemVersion) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= base_url('apple-touch-icon.png?v=' . $systemVersion) ?>">
    <link rel="manifest" href="<?= base_url('site.webmanifest?v=' . $systemVersion) ?>">

    <meta property="og:title" content="Android App — Mpesa Analyzer">
    <meta property="og:description" content="Sync SMS securely with CameraX QR pairing, AES encryption, and battery-efficient WorkManager.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= base_url('android-app') ?>">
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
        .glass-card { background: rgba(255,255,255,0.8); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.4); border-radius: var(--radius); padding: 2.5rem; transition: all 0.3s; height: 100%; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        [data-bs-theme="dark"] .glass-card { background: rgba(30, 41, 59, 0.8); border-color: rgba(255,255,255,0.1); }
        .glass-card:hover { transform: translateY(-6px); box-shadow: 0 12px 32px rgba(0,0,0,0.08); }
        .icon-box { width: 56px; height: 56px; background: var(--secondary); color: var(--primary); display: flex; align-items: center; justify-content: center; border-radius: var(--radius); font-size: 1.4rem; margin-bottom: 1.25rem; flex-shrink: 0; }
        [data-bs-theme="dark"] .icon-box { background: rgba(67, 142, 185, 0.2); }
        .footer { background: var(--dark); color: #fff; padding: 4rem 0 2rem; }
        .footer a { color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.2s; }
        .footer a:hover { color: #fff; }
        pre code { background: transparent; color: inherit; padding: 0; }
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
                    <li class="nav-item"><a class="nav-link active" href="<?= base_url('android-app') ?>">Android App</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('ml-backend') ?>">ML Backend</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('setup') ?>">Setup</a></li>
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
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 mb-3 fw-semibold">
                        <i class="fa-brands fa-android me-1"></i> Native Android Companion (v<?= esc($systemVersion) ?>)
                    </span>
                    <h1 class="fw-800 mb-3" style="font-size: 2.75rem; font-weight: 800; letter-spacing: -0.5px;">Android Companion App</h1>
                    <p class="lead text-muted mb-4" style="line-height: 1.7;">
                        The native companion app securely bridges your device SMS to the Mpesa Analyzer cloud. Equipped with <strong>CameraX vertical QR pairing</strong>, on-device SQLite staging, <strong>"Ask My M-Pesa" AI Financial Assistant</strong>, and <strong>AES-256 encrypted batch uploads</strong>, it ensures zero transaction loss with minimal battery overhead.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?= base_url('setup') ?>" class="btn btn-primary btn-lg px-4">
                            <i class="fa-solid fa-qrcode me-2"></i>Link Device via QR
                        </a>
                        <a href="<?= base_url('faq') ?>" class="btn btn-outline-primary btn-lg px-4">
                            <i class="fa-solid fa-shield me-2"></i>Security & Permissions
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="glass-card text-center p-5 shadow-sm">
                        <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">
                            <span class="badge bg-primary bg-opacity-10 text-primary border px-2.5 py-1.5"><i class="fa-solid fa-camera me-1"></i> CameraX QR</span>
                            <span class="badge bg-warning bg-opacity-10 text-warning border px-2.5 py-1.5"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> In-App AI Chat</span>
                            <span class="badge bg-info bg-opacity-10 text-info border px-2.5 py-1.5"><i class="fa-solid fa-fingerprint me-1"></i> Biometric Auth</span>
                            <span class="badge bg-success bg-opacity-10 text-success border px-2.5 py-1.5"><i class="fa-solid fa-lock me-1"></i> AES-256 CBC</span>
                        </div>
                        <i class="fa-solid fa-mobile-screen-button fa-6x text-primary opacity-50 mb-3"></i>
                        <h5 class="fw-bold mb-1">Zero-Loss Financial Assistant & Sync</h5>
                        <p class="text-muted small mb-0">Engineered for Android 8.0 through Android 15+ (API 26–36)</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold h1 mb-3">End-to-End Pipeline Architecture</h2>
                <p class="text-muted mx-auto" style="max-width: 620px;">Designed to guarantee delivery even during network dropouts and strict OEM background constraints.</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="glass-card text-center">
                        <div class="icon-box mx-auto"><i class="fa-solid fa-qrcode"></i></div>
                        <h5 class="fw-bold">1. Scan & Handshake</h5>
                        <p class="text-muted small mb-0">Use the vertical CameraX scanner to capture the pairing QR code from the WebApp. Instantly establishes mutual token authentication without manual text entry.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="glass-card text-center">
                        <div class="icon-box mx-auto"><i class="fa-solid fa-inbox"></i></div>
                        <h5 class="fw-bold">2. SMS Interception</h5>
                        <p class="text-muted small mb-0">A background broadcast receiver listens for incoming SMS. Messages are deduplicated by sender, timestamp, and body hash to ensure idempotency.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="glass-card text-center">
                        <div class="icon-box mx-auto"><i class="fa-solid fa-database"></i></div>
                        <h5 class="fw-bold">3. SQLite Staging</h5>
                        <p class="text-muted small mb-0">Transactions are committed into an on-device SQLite queue. If the device is offline or roaming, records accumulate safely without data loss.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="glass-card text-center">
                        <div class="icon-box mx-auto"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                        <h5 class="fw-bold">4. Encrypted Batch Sync</h5>
                        <p class="text-muted small mb-0">WorkManager compresses and AES-256 encrypts loot batches, transmitting them to <code>/api/v1/upload</code> where they are archived into MinIO S3 (<code>mpesa-payloads</code> bucket) with automated 90-day ILM retention.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container py-4">
            <div class="row g-4 align-items-center">
                <div class="col-lg-6">
                    <h2 class="fw-bold h1 mb-3">Engineered for Modern Android</h2>
                    <p class="text-muted mb-4" style="line-height: 1.7;">
                        Built with modern Android Architecture Components, Kotlin, and Jetpack libraries. No obsolete background services or excessive wake locks.
                    </p>
                    <div class="d-flex gap-3 mb-4">
                        <div class="icon-box" style="width: 48px; height: 48px; font-size: 1.1rem;"><i class="fa-solid fa-camera"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Vertical CameraX Scanner</h6>
                            <p class="text-muted small mb-0">Integrated barcode analyzer with automatic vertical orientation locking and tap-to-focus for effortless pairing under any lighting.</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-4">
                        <div class="icon-box" style="width: 48px; height: 48px; font-size: 1.1rem;"><i class="fa-solid fa-palette"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Adaptive Vector Drawables</h6>
                            <p class="text-muted small mb-0">Fully revamped asset pipeline using crisp XML vector drawables that scale losslessly to any screen density and automatically inherit system light/dark themes.</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="icon-box" style="width: 48px; height: 48px; font-size: 1.1rem;"><i class="fa-solid fa-battery-full"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">WorkManager & Doze Resilience</h6>
                            <p class="text-muted small mb-0">Complies with Android 10+ battery optimization standards. Synchronizations are batched and scheduled during active network windows, consuming &lt; 2% battery daily.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="glass-card">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-list-check text-primary me-2"></i>Companion Capabilities</h5>
                        <ul class="list-unstyled">
                            <li class="d-flex gap-2 mb-3"><i class="fa-solid fa-circle-check text-primary mt-1"></i><span><strong>Instant QR Linking</strong> — Pair with the WebApp in seconds via CameraX barcode scanning.</span></li>
                            <li class="d-flex gap-2 mb-3"><i class="fa-solid fa-circle-check text-primary mt-1"></i><span><strong>Offline Staging Queue</strong> — SQLite persistence guarantees zero dropped transactions when out of coverage.</span></li>
                            <li class="d-flex gap-2 mb-3"><i class="fa-solid fa-circle-check text-primary mt-1"></i><span><strong>End-to-End Encryption</strong> — Payloads are encrypted with AES-256 CBC using your device token key before transmission.</span></li>
                            <li class="d-flex gap-2 mb-3"><i class="fa-solid fa-circle-check text-primary mt-1"></i><span><strong>Historical Batch Import</strong> — Scan and backfill months of existing SMS on initial setup.</span></li>
                            <li class="d-flex gap-2 mb-3"><i class="fa-solid fa-circle-check text-primary mt-1"></i><span><strong>Multi-Device Fleet</strong> — Link separate personal and business phones into a unified web ledger.</span></li>
                            <li class="d-flex gap-2"><i class="fa-solid fa-circle-check text-primary mt-1"></i><span><strong>Automatic Heartbeats</strong> — Reports device sync timestamps, batch counts, and connectivity state to WebApp control center.</span></li>
                        </ul>
                        <a href="<?= base_url('setup') ?>" class="btn btn-primary w-100 mt-2"><i class="fa-brands fa-android me-2"></i>Configure Device Now</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold h1 mb-3">Developer & Integration Specifications</h2>
                <p class="text-muted mx-auto" style="max-width: 600px;">Precise API contracts and cryptographic standards for power users and auditors.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="glass-card">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-code text-primary me-2"></i>Batch Upload Endpoint</h5>
                        <p class="text-muted small mb-2">Android sync workers dispatch batches to the unified REST endpoint:</p>
                        <div class="bg-dark p-3 rounded-3 text-light small mb-3">
                            <code>POST <?= base_url('api/v1/upload') ?></code><br>
                            <span class="text-secondary opacity-75">Headers: Authorization: Bearer &lt;TOKEN_HASH&gt;</span>
                        </div>
                        <p class="text-muted small mb-0">Payload contains timestamped AES-encrypted message arrays. Companion endpoints (<code>financial/*</code>, <code>settings/*</code>) operate via standard OpenAPI 3.0 REST specifications with dual GET/POST support.</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="glass-card">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-shield-halved text-primary me-2"></i>Cryptographic Identity</h5>
                        <p class="text-muted small mb-2">Each mobile instance is bound to the owner account through non-reversible cryptographic secrets:</p>
                        <ul class="text-muted small mb-0">
                            <li class="mb-1"><strong>Secret Hashing:</strong> WebApp stores only SHA-256 hashes of generated device tokens.</li>
                            <li class="mb-1"><strong>Payload Integrity:</strong> Upload chunks are validated against message digests before insertion.</li>
                            <li class="mb-1"><strong>Instant Revocation:</strong> One click in the WebApp immediately invalidates any compromised device token.</li>
                        </ul>
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
                        <span class="badge bg-light text-dark">Kotlin</span>
                        <span class="badge bg-light text-dark">CameraX</span>
                        <span class="badge bg-light text-dark">WorkManager</span>
                        <span class="badge bg-light text-dark">SQLite</span>
                        <span class="badge bg-light text-dark">AES-256</span>
                        <span class="badge bg-light text-dark">MinIO S3</span>
                        <span class="badge bg-light text-dark">CodeIgniter 4</span>
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