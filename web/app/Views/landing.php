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
    <title>Mpesa Analyzer — AI-Powered SMS Financial Intelligence Platform</title>
    <meta name="description" content="Mpesa Analyzer automatically syncs all your SMS, uses local GGUF ML models to detect finance-related messages, classifies M-Pesa & bank transactions, and visualizes your spending. Privacy-first financial intelligence.">
    <meta name="keywords" content="Mpesa analyzer, M-Pesa transaction analyzer, SMS financial intelligence, ML SMS classification, spend tracker Kenya, mobile money analysis, local LLM, Qwen GGUF">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= base_url() ?>">

    <!-- Favicon & PWA Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('favicon.png?v=' . $systemVersion) ?>">
    <link rel="icon" type="image/x-icon" href="<?= base_url('favicon.ico?v=' . $systemVersion) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= base_url('apple-touch-icon.png?v=' . $systemVersion) ?>">
    <link rel="manifest" href="<?= base_url('site.webmanifest?v=' . $systemVersion) ?>">

    <meta property="og:title" content="Mpesa Analyzer — AI-Powered SMS Financial Intelligence">
    <meta property="og:description" content="Sync all SMS, local GGUF LLMs detect financial messages, classify transactions, and visualize spending.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= base_url() ?>">
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
        :root {
            --primary: #438EB9;
            --primary-dark: #222A2D;
            --secondary: #E8F2F8;
            --dark: #1A1A2E;
            --light: #F8F9FA;
            --radius: 6px;
        }

        body { font-family: 'Outfit', sans-serif; background-color: var(--light); color: var(--dark); }

        .navbar { padding: 1.25rem 0; background: transparent; transition: all 0.3s ease; }
        .navbar.scrolled { background: rgba(255,255,255,0.92); backdrop-filter: blur(12px); padding: 0.75rem 0; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        [data-bs-theme="dark"] .navbar.scrolled { background: rgba(15, 23, 42, 0.92); }
        .navbar-brand { font-weight: 800; font-size: 1.5rem; letter-spacing: -0.5px; }

        .nav-link { font-weight: 500; transition: color 0.2s; position: relative; }
        .nav-link:hover { color: var(--primary); }
        .nav-link.active { color: var(--primary) !important; }
        .nav-link.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 2px; background: var(--primary); border-radius: 1px; }

        .btn-primary { background-color: var(--primary); border-color: var(--primary); padding: 0.7rem 1.8rem; font-weight: 600; border-radius: var(--radius); transition: all 0.3s; }
        .btn-primary:hover { background-color: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(67,142,185,0.35); }
        .btn-outline-primary { color: var(--primary); border-color: var(--primary); padding: 0.7rem 1.8rem; font-weight: 600; border-radius: var(--radius); }
        .btn-outline-primary:hover { background-color: var(--primary); color: #fff; transform: translateY(-2px); }

        .hero-section { padding: 140px 0 100px; background: linear-gradient(135deg, #fff 0%, var(--secondary) 100%); position: relative; overflow: hidden; }
        [data-bs-theme="dark"] .hero-section { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
        .hero-section::before { content: ''; position: absolute; top: -50%; right: -20%; width: 600px; height: 600px; background: radial-gradient(circle, rgba(67,142,185,0.08) 0%, transparent 70%); border-radius: 50%; }
        .hero-title { font-size: 3.25rem; font-weight: 800; line-height: 1.15; margin-bottom: 1.5rem; letter-spacing: -1px; background: linear-gradient(135deg, var(--primary), #2a6f97); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .hero-img-placeholder { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.5); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; height: 420px; }
        [data-bs-theme="dark"] .hero-img-placeholder { background: rgba(30,41,59,0.6); border-color: rgba(255,255,255,0.1); }

        .section-title { font-weight: 700; letter-spacing: -0.3px; }
        .glass-card { background: rgba(255,255,255,0.8); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.4); border-radius: var(--radius); padding: 2.5rem; transition: all 0.3s; height: 100%; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        [data-bs-theme="dark"] .glass-card { background: rgba(30, 41, 59, 0.8); border-color: rgba(255,255,255,0.1); }
        .glass-card:hover { transform: translateY(-6px); box-shadow: 0 12px 32px rgba(0,0,0,0.08); }
        .icon-box { width: 56px; height: 56px; background: var(--secondary); color: var(--primary); display: flex; align-items: center; justify-content: center; border-radius: var(--radius); font-size: 1.4rem; margin-bottom: 1.25rem; }
        [data-bs-theme="dark"] .icon-box { background: rgba(67, 142, 185, 0.2); }

        .feature-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.85rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .footer { background: var(--dark); color: #fff; padding: 4rem 0 2rem; }
        .footer a { color: rgba(255,255,255,0.7); text-decoration: none; transition: color 0.2s; }
        .footer a:hover { color: #fff; }

        @media (max-width: 768px) { .hero-title { font-size: 2.25rem; } .hero-section { padding: 100px 0 60px; } }
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
                    <li class="nav-item"><a class="nav-link active" href="<?= base_url() ?>">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('android-app') ?>">Android App</a></li>
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

    <header class="hero-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 mb-3 fw-semibold">
                        <i class="fa-solid fa-microchip me-1"></i> Local LLM & GGUF Financial Intelligence
                    </span>
                    <h1 class="hero-title">Financial Intelligence From Every SMS You Receive</h1>
                    <p class="lead mb-4 text-muted" style="font-size: 1.15rem; line-height: 1.7;">
                        Mpesa Analyzer synchronizes your mobile messages securely and processes them using a local Large Language Model (Qwen 2.5 via Llama.cpp). Our system semantic-classifies financial senders across M-Pesa, bank notifications, SACCOs, and utilities without brittle regex patterns that break when SMS templates update.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?= url_to('register') ?>" class="btn btn-primary btn-lg px-4">
                            <i class="fa-solid fa-rocket me-2"></i>Get Started Free
                        </a>
                        <a href="<?= base_url('setup') ?>" class="btn btn-outline-primary btn-lg px-4">
                            <i class="fa-solid fa-book me-2"></i>Setup Guide
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-img-placeholder shadow-sm">
                        <div class="text-center p-4">
                            <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">
                                <span class="badge bg-primary bg-opacity-10 text-primary border px-3 py-2"><i class="fa-brands fa-android me-1"></i> CameraX QR Pairing</span>
                                <span class="badge bg-success bg-opacity-10 text-success border px-3 py-2"><i class="fa-solid fa-brain me-1"></i> Qwen 2.5 / DeepSeek</span>
                                <span class="badge bg-warning bg-opacity-10 text-warning border px-3 py-2"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> "Ask My M-Pesa" AI Chat</span>
                                <span class="badge bg-danger bg-opacity-10 text-danger border px-3 py-2" title="Sub-millisecond in-memory caching with automatic MySQL session failover"><i class="fa-solid fa-bolt me-1"></i> Redis 7 + Zero-Downtime Fallback</span>
                                <span class="badge bg-secondary bg-opacity-10 text-body border px-3 py-2" title="MinIO S3-compatible encrypted object storage for payloads, backups, and ML models"><i class="fa-solid fa-server me-1"></i> MinIO S3 Storage</span>
                                <span class="badge bg-info bg-opacity-10 text-info border px-3 py-2"><i class="fa-solid fa-chart-line me-1"></i> Live Telemetry</span>
                            </div>
                            <i class="fa-solid fa-chart-pie fa-5x text-primary opacity-50 mb-3"></i>
                            <h5 class="fw-bold mb-1">Autonomous Financial Ledger</h5>
                            <p class="text-muted small mb-0">From raw encrypted SMS to structured ledger, charts, and conversational AI insights.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section class="py-5">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="section-title h1 mb-3">Why Semantic AI Instead of Regex?</h2>
                <p class="text-muted mx-auto" style="max-width: 680px;">Traditional budget trackers rely on manual entry or rigid text parsers that break whenever Safaricom, banks, or utility providers change wording.</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="glass-card">
                        <div class="icon-box"><i class="fa-solid fa-brain"></i></div>
                        <h5 class="fw-bold">Local GGUF LLM Reasoning</h5>
                        <p class="text-muted small mb-0">Powered by quantized local LLMs running via Llama.cpp with CPU AVX2 and GPU support. The model extracts amounts, fees, counterparties, balances, and categories accurately.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="glass-card">
                        <div class="icon-box"><i class="fa-solid fa-shield-halved"></i></div>
                        <h5 class="fw-bold">Privacy-First Architecture</h5>
                        <p class="text-muted small mb-0">Your financial data remains strictly self-hosted in your Docker deployment. Tokens use SHA-256 hashing, loot payloads are AES-256 encrypted, and zero data is sent to cloud APIs.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="glass-card">
                        <div class="icon-box"><i class="fa-solid fa-server"></i></div>
                        <h5 class="fw-bold">MinIO S3 Object Storage</h5>
                        <p class="text-muted small mb-0">Production-grade S3-compatible storage. Features automated 90-day ILM retention on encrypted payloads (<code>mpesa-payloads</code>), point-in-time database backups (<code>mpesa-backups</code>), and centralized GGUF model distribution.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="glass-card">
                        <div class="icon-box"><i class="fa-solid fa-arrows-split-up-and-left"></i></div>
                        <h5 class="fw-bold">Dual-Engine Fault Tolerance</h5>
                        <p class="text-muted small mb-0">Sub-millisecond Redis 7 in-memory acceleration under peak load, with automatic, transparent fallback to MySQL sessions and disk cache if Redis restarts. Zero 500 errors, zero dropped logins.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="glass-card">
                        <div class="icon-box"><i class="fa-solid fa-gauge-high"></i></div>
                        <h5 class="fw-bold">Live Container Observability</h5>
                        <p class="text-muted small mb-0">Built-in real-time telemetry monitors memory RSS headroom, CPU load, MySQL connection pools, slow queries, and active LLM footprint with pure-SVG sparklines for zero front-end overhead.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="glass-card">
                        <div class="icon-box"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                        <h5 class="fw-bold">Conversational Financial Chat</h5>
                        <p class="text-muted small mb-0">"Ask My M-Pesa" AI Assistant injects real-time financial aggregates into Qwen 2.5/DeepSeek, providing intelligent budget guidance and affordability checks in English and Sheng.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="section-title h1 mb-3">Three Unified Components</h2>
                <p class="text-muted mx-auto" style="max-width: 620px;">An end-to-end ecosystem engineered for automation, reliability, and precision.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <a href="<?= base_url('android-app') ?>" class="text-decoration-none">
                        <div class="glass-card">
                            <div class="icon-box"><i class="fa-brands fa-android"></i></div>
                            <h4 class="fw-bold text-dark mb-2">Android Companion</h4>
                            <p class="text-muted small mb-3">Runs quietly on your phone. Features vertical CameraX QR pairing, theme-aware vector drawables, SQLite local queuing, and battery-efficient WorkManager background synchronization.</p>
                            <span class="text-primary fw-semibold small">Explore Companion App <i class="fa-solid fa-arrow-right ms-1"></i></span>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="<?= base_url('ml-backend') ?>" class="text-decoration-none">
                        <div class="glass-card">
                            <div class="icon-box"><i class="fa-solid fa-microchip"></i></div>
                            <h4 class="fw-bold text-dark mb-2">ML Microservice</h4>
                            <p class="text-muted small mb-3">FastAPI service on port 9050. Executes local GGUF models via Llama.cpp, exposes live process telemetry, manages Hugging Face model presets, and offers two-stage heuristic + semantic parsing.</p>
                            <span class="text-primary fw-semibold small">Explore ML Engine <i class="fa-solid fa-arrow-right ms-1"></i></span>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="<?= url_to('register') ?>" class="text-decoration-none">
                        <div class="glass-card">
                            <div class="icon-box"><i class="fa-solid fa-display"></i></div>
                            <h4 class="fw-bold text-dark mb-2">Web Dashboard</h4>
                            <p class="text-muted small mb-3">CodeIgniter 4 full-stack portal. Interactive spending analytics, searchable transaction ledgers, automated data retention policies, one-click database backups, and dark/light Ace UI.</p>
                            <span class="text-primary fw-semibold small">Create Account <i class="fa-solid fa-arrow-right ms-1"></i></span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Spotlight: "Ask My M-Pesa" AI Financial Assistant -->
    <section class="py-5 bg-light border-top border-bottom">
        <div class="container py-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 mb-3 fw-semibold">
                        <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Conversational Financial Intelligence
                    </span>
                    <h2 class="section-title h1 mb-3">"Ask My M-Pesa" — In-App & Web AI Assistant</h2>
                    <p class="lead text-muted mb-4" style="font-size: 1.1rem; line-height: 1.7;">
                        Ask questions about your transactions in plain English or Sheng. The assistant automatically injects your real spending aggregates, Fuliza fees, recipient breakdowns, and budget goals to deliver instant personalized advice.
                    </p>
                    <div class="d-flex flex-column gap-2 mb-4">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-check text-success mt-1"></i>
                            <span><strong>Multi-Turn Memory:</strong> Remembers context across questions and syncs persistent history between Android and WebApp.</span>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-check text-success mt-1"></i>
                            <span><strong>English & Sheng Native:</strong> Natural communication tailored for Kenyan mobile money phrasing.</span>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-check text-success mt-1"></i>
                            <span><strong>Affordability Check:</strong> Real-time assessment of upcoming purchases against expected cashflow.</span>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <a href="<?= url_to('login') ?>" class="btn btn-primary">Try AI Assistant</a>
                        <a href="<?= base_url('android-app') ?>" class="btn btn-outline-primary">Android Companion</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                        <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                    <i class="fa-solid fa-robot" style="font-size: 0.85rem;"></i>
                                </div>
                                <span class="fw-bold small">M-Pesa Smart Advisor</span>
                            </div>
                            <span class="badge bg-success-subtle text-success font-monospace small"><i class="fa-solid fa-circle me-1" style="font-size: 0.5rem;"></i>Active LLM</span>
                        </div>
                        <div class="card-body p-4 bg-light" style="font-size: 0.9rem;">
                            <div class="d-flex justify-content-end mb-3">
                                <div class="bg-primary text-white p-3 rounded-3 shadow-xs" style="max-width: 85%;">
                                    Can I afford a KES 15,000 purchase this weekend based on my bills due?
                                </div>
                            </div>
                            <div class="d-flex mb-2">
                                <div class="bg-white text-dark p-3 rounded-3 shadow-xs border" style="max-width: 90%;">
                                    <div class="fw-semibold text-primary mb-1 small">M-Pesa Smart Advisor</div>
                                    Based on your current M-Pesa balance of <strong>KES 24,500</strong> and upcoming recurring utility payments totaling <strong>KES 6,200</strong>, you will have approximately <strong>KES 3,300</strong> remaining after a KES 15,000 expense. It fits within your budget, but leaves tight liquidity for unexpected Fuliza charges.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container py-5 text-center">
            <div class="bg-primary p-5 rounded-4 shadow-lg text-white" style="border-radius: var(--radius) !important;">
                <h2 class="fw-bold mb-3">Ready to master your financial intelligence?</h2>
                <p class="mb-4 opacity-75 fs-5">Deploy your private instance or start managing your transactions with local AI precision.</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="<?= url_to('register') ?>" class="btn btn-light btn-lg px-5 text-primary fw-bold shadow-sm">
                        <i class="fa-solid fa-user-plus me-2"></i>Create Account
                    </a>
                    <a href="<?= url_to('login') ?>" class="btn btn-outline-light btn-lg px-5 fw-bold">
                        <i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Sign In
                    </a>
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
                        <span class="badge bg-light text-dark">Bootstrap 5</span>
                        <span class="badge bg-light text-dark">CameraX</span>
                    </div>
                </div>
            </div>
            <hr class="mt-4 mb-4 opacity-25">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0 opacity-75 small">
                        &copy; <?= date('Y') ?> Mpesa Analyzer
                        <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#changelogModal" class="badge bg-success-subtle text-success border border-success-subtle ms-1 text-decoration-none font-monospace" title="Click to view v<?= esc($systemVersion) ?> release notes">
                            <i class="fa-solid fa-code-commit me-1"></i>v<?= esc($systemVersion) ?>
                        </a>
                        . All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                    <p class="mb-0 opacity-75 small">Built with <i class="fa-solid fa-heart text-danger"></i> for financial freedom</p>
                </div>
            </div>
        </div>
    </footer>

    <?= view('Layouts/_changelog_modal') ?>

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