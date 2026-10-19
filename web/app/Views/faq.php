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
    <title>FAQ — Mpesa Analyzer</title>
    <meta name="description" content="Frequently asked questions about Mpesa Analyzer. Covers CameraX QR pairing, local GGUF LLMs, AES encryption, telemetry diagnostics, and troubleshooting.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= base_url('faq') ?>">

    <!-- Favicon & PWA Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('favicon.png?v=' . $systemVersion) ?>">
    <link rel="icon" type="image/x-icon" href="<?= base_url('favicon.ico?v=' . $systemVersion) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= base_url('apple-touch-icon.png?v=' . $systemVersion) ?>">
    <link rel="manifest" href="<?= base_url('site.webmanifest?v=' . $systemVersion) ?>">

    <meta property="og:title" content="FAQ — Mpesa Analyzer">
    <meta property="og:description" content="Frequently asked questions about QR pairing, local GGUF LLMs, security, and container telemetry.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= base_url('faq') ?>">
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
        .glass-card { background: rgba(255,255,255,0.8); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.4); border-radius: var(--radius); padding: 1.75rem 2rem; transition: all 0.3s; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        [data-bs-theme="dark"] .glass-card { background: rgba(30, 41, 59, 0.8); border-color: rgba(255,255,255,0.1); }
        .glass-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,0.08); }
        .faq-question { cursor: pointer; user-select: none; }
        .faq-question:hover { color: var(--primary); }
        .faq-question .icon { transition: transform 0.3s; }
        .faq-question[aria-expanded="true"] .icon { transform: rotate(45deg); }
        .faq-answer { font-size: 0.95rem; line-height: 1.7; }
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
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('setup') ?>">Setup</a></li>
                    <li class="nav-item"><a class="nav-link active" href="<?= base_url('faq') ?>">FAQ</a></li>
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
                    <i class="fa-solid fa-circle-question me-1"></i> Knowledge Base
                </span>
                <h1 class="fw-800 mb-3" style="font-size: 2.75rem; font-weight: 800; letter-spacing: -0.5px;">Frequently Asked Questions</h1>
                <p class="lead text-muted mx-auto mb-0" style="max-width: 620px;">Architecture insights, CameraX pairing, local GGUF model operations, and security protocols.</p>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container py-4">
            <div class="row justify-content-center">
                <div class="col-lg-8">

                    <!-- Section: System & AI -->
                    <h4 class="fw-bold mb-4 text-primary"><i class="fa-solid fa-brain me-2"></i>Machine Learning & Semantic Extraction</h4>

                    <div class="glass-card mb-3">
                        <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq_sem" aria-expanded="false">
                            <h5 class="fw-bold mb-0">How does semantic ML differ from regular regex parsers?</h5>
                            <span class="icon fs-4 text-primary"><i class="fa-solid fa-circle-plus"></i></span>
                        </div>
                        <div class="collapse" id="faq_sem">
                            <div class="faq-answer text-muted pt-3 border-top mt-3">
                                Regex parsers rely on hardcoded character patterns. When Safaricom or banks change sentence structures, insert ads, or alter currency labels, regex breaks completely. Mpesa Analyzer uses a quantized local LLM (such as Qwen 2.5 3B) running via Llama.cpp. It reads the full semantic context of messages, accurately extracting transaction codes, counterparties, fees, and new balances regardless of template variations.
                            </div>
                        </div>
                    </div>

                    <div class="glass-card mb-3">
                        <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq_hw" aria-expanded="false">
                            <h5 class="fw-bold mb-0">What are the hardware requirements to run the ML microservice?</h5>
                            <span class="icon fs-4 text-primary"><i class="fa-solid fa-circle-plus"></i></span>
                        </div>
                        <div class="collapse" id="faq_hw">
                            <div class="faq-answer text-muted pt-3 border-top mt-3">
                                The ML service runs efficiently on standard x86_64 CPUs thanks to AVX2 / OpenMP vectorization. A standard 4-core virtual server with 4 GB of RAM is sufficient to run <code>Qwen2.5-3B-Instruct-Q4_K_M</code>. If an NVIDIA GPU is available, the service automatically leverages CUDA acceleration to process hundreds of transactions per second.
                            </div>
                        </div>
                    </div>

                    <div class="glass-card mb-4">
                        <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq_rescan" aria-expanded="false">
                            <h5 class="fw-bold mb-0">What is the difference between "Rescan" and "Full Reset"?</h5>
                            <span class="icon fs-4 text-primary"><i class="fa-solid fa-circle-plus"></i></span>
                        </div>
                        <div class="collapse" id="faq_rescan">
                            <div class="faq-answer text-muted pt-3 border-top mt-3">
                                <strong>Rescan</strong> is an incremental analysis cycle that processes newly arrived or unclassified SMS in <code>tbl_Loot</code> without touching existing transactions. <strong>Full Reset</strong> cleans out previous model inferences in <code>tbl_Transactions</code> and re-runs the active LLM across all historical SMS records. Both actions open an interactive progress dialog that streams live processing status.
                            </div>
                        </div>
                    </div>

                    <!-- Section: Android App -->
                    <h4 class="fw-bold mb-4 mt-5 text-primary"><i class="fa-brands fa-android me-2"></i>Android Companion & QR Pairing</h4>

                    <div class="glass-card mb-3">
                        <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq_qr" aria-expanded="false">
                            <h5 class="fw-bold mb-0">How does CameraX vertical QR pairing work?</h5>
                            <span class="icon fs-4 text-primary"><i class="fa-solid fa-circle-plus"></i></span>
                        </div>
                        <div class="collapse" id="faq_qr">
                            <div class="faq-answer text-muted pt-3 border-top mt-3">
                                When you click <strong>Pair New Device</strong> in the WebApp (under <code>Control Center &rarr; Devices</code>), the system displays an encrypted QR code containing your server endpoint and a temporary device token. In the Android app, opening the vertical CameraX scanner instantly decodes the code, completes the cryptographic handshake, and saves the connection parameters without typing.
                            </div>
                        </div>
                    </div>

                    <div class="glass-card mb-3">
                        <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq_offline" aria-expanded="false">
                            <h5 class="fw-bold mb-0">What happens if my phone loses internet connection?</h5>
                            <span class="icon fs-4 text-primary"><i class="fa-solid fa-circle-plus"></i></span>
                        </div>
                        <div class="collapse" id="faq_offline">
                            <div class="faq-answer text-muted pt-3 border-top mt-3">
                                Incoming SMS messages are staged in an on-device local SQLite database. Even during prolonged offline periods or airplane mode, messages accumulate safely. Once network connectivity is restored, Android WorkManager automatically batches and AES-encrypts the queue, delivering it to <code>/api/v1/upload</code> without duplicate insertions.
                            </div>
                        </div>
                    </div>

                    <div class="glass-card mb-4">
                        <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq_bat" aria-expanded="false">
                            <h5 class="fw-bold mb-0">Does the Android app impact battery life?</h5>
                            <span class="icon fs-4 text-primary"><i class="fa-solid fa-circle-plus"></i></span>
                        </div>
                        <div class="collapse" id="faq_bat">
                            <div class="faq-answer text-muted pt-3 border-top mt-3">
                                No. The app uses broadcast receivers and Jetpack WorkManager adhering to strict Android Doze standards. It wakes up only when an SMS arrives, stages it locally, and schedules uploads during opportunistic network windows. Field tests report less than 1.5% battery consumption per 24 hours.
                            </div>
                        </div>
                    </div>

                    <!-- Section: Security & Telemetry -->
                    <h4 class="fw-bold mb-4 mt-5 text-primary"><i class="fa-solid fa-shield-halved me-2"></i>Security, Telemetry & Operations</h4>

                    <div class="glass-card mb-3">
                        <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq_sec" aria-expanded="false">
                            <h5 class="fw-bold mb-0">How is my financial data protected?</h5>
                            <span class="icon fs-4 text-primary"><i class="fa-solid fa-circle-plus"></i></span>
                        </div>
                        <div class="collapse" id="faq_sec">
                            <div class="faq-answer text-muted pt-3 border-top mt-3">
                                Security is enforced at every layer:
                                <ul class="mt-2 mb-0 small">
                                    <li><strong>In Transit:</strong> All HTTP traffic is protected by TLS/HTTPS, and mobile sync batches are encrypted with AES-256 CBC.</li>
                                    <li><strong>Authentication:</strong> Device tokens are hashed using SHA-256 before database insertion; plaintext tokens are never stored.</li>
                                    <li><strong>Privacy:</strong> The entire stack (WebApp, ML, MySQL) is 100% self-hosted in Docker/Railway. Zero financial data is ever shared with external commercial AI APIs.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card mb-3">
                        <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq_tel" aria-expanded="false">
                            <h5 class="fw-bold mb-0">What does the Container Telemetry monitor?</h5>
                            <span class="icon fs-4 text-primary"><i class="fa-solid fa-circle-plus"></i></span>
                        </div>
                        <div class="collapse" id="faq_tel">
                            <div class="faq-answer text-muted pt-3 border-top mt-3">
                                The telemetry console (<code>/admin/telemetry</code>) provides live visibility into system health. It monitors WebApp container CPU/RAM/Disk headroom, MySQL 8.4 pool utilization, slow queries, and the exact RSS memory footprints of the FastAPI worker and <code>llama-server</code> daemon. All metrics render using pure-SVG sparklines with zero external charting overhead.
                            </div>
                        </div>
                    </div>

                    <div class="glass-card mb-4">
                        <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq_bkp" aria-expanded="false">
                            <h5 class="fw-bold mb-0">How do database backups and data retention policies work?</h5>
                            <span class="icon fs-4 text-primary"><i class="fa-solid fa-circle-plus"></i></span>
                        </div>
                        <div class="collapse" id="faq_bkp">
                            <div class="faq-answer text-muted pt-3 border-top mt-3">
                                In <strong>Admin &rarr; System &rarr; Maintenance</strong>, admins can configure automated retention periods (e.g. purging non-financial SMS or logs older than 90 days). In <strong>Admin &rarr; System &rarr; Backups</strong>, full SQL dumps of all application and transaction tables are generated, streamed directly to MinIO (<code>mpesa-backups</code> bucket), and can be downloaded securely via 15-minute S3 pre-signed URLs or streaming with pre-execution disk safeguards.
                            </div>
                        </div>
                    </div>

                    <div class="glass-card mb-4">
                        <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq_minio" aria-expanded="false">
                            <h5 class="fw-bold mb-0">How does MinIO S3 object storage protect my data and backups?</h5>
                            <span class="icon fs-4 text-primary"><i class="fa-solid fa-circle-plus"></i></span>
                        </div>
                        <div class="collapse" id="faq_minio">
                            <div class="faq-answer text-muted pt-3 border-top mt-3">
                                The platform integrates an on-premise MinIO S3 cluster configured with isolated buckets:
                                <ul class="mt-2 mb-2 small">
                                    <li><code>mpesa-payloads</code>: Encrypted incoming SMS batches with an automated 90-day Information Lifecycle Management (ILM) expiration rule.</li>
                                    <li><code>mpesa-backups</code>: Secure point-in-time database snapshots downloadable via time-limited S3 pre-signed URLs or backend streaming.</li>
                                    <li><code>mpesa-models</code>: Central repository for local GGUF models, synced automatically to worker nodes.</li>
                                    <li><code>mpesa-exports</code>: Temporary user transaction CSV/JSON exports with automated 7-day expiration.</li>
                                </ul>
                                All storage traffic is authenticated using native AWS SigV4 inside the private Docker network, with zero external cloud dependencies.
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
                        <span class="badge bg-light text-dark">CameraX</span>
                        <span class="badge bg-light text-dark">MySQL 8.4</span>
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