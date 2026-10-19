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
    <title>ML Backend — Mpesa Analyzer</title>
    <meta name="description" content="The Mpesa Analyzer ML backend runs local GGUF LLMs (Qwen 2.5 via Llama.cpp) with CPU AVX2 and GPU support. FastAPI microservice on port 9050 with telemetry, model presets, and semantic extraction.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= base_url('ml-backend') ?>">

    <!-- Favicon & PWA Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('favicon.png?v=' . $systemVersion) ?>">
    <link rel="icon" type="image/x-icon" href="<?= base_url('favicon.ico?v=' . $systemVersion) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= base_url('apple-touch-icon.png?v=' . $systemVersion) ?>">
    <link rel="manifest" href="<?= base_url('site.webmanifest?v=' . $systemVersion) ?>">

    <meta property="og:title" content="ML Backend — Mpesa Analyzer">
    <meta property="og:description" content="Local GGUF LLM inference engine running via Llama.cpp and FastAPI on port 9050.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= base_url('ml-backend') ?>">
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
                    <li class="nav-item"><a class="nav-link active" href="<?= base_url('ml-backend') ?>">ML Backend</a></li>
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
                        <i class="fa-solid fa-microchip me-1"></i> Autonomous AI Microservice (v<?= esc($systemVersion) ?>)
                    </span>
                    <h1 class="fw-800 mb-3" style="font-size: 2.75rem; font-weight: 800; letter-spacing: -0.5px;">ML Backend Engine</h1>
                    <p class="lead text-muted mb-4" style="line-height: 1.7;">
                        High-performance FastAPI service running local GGUF Large Language Models (<strong>Qwen 2.5 3B Instruct</strong> via <code>llama.cpp</code>) alongside agile cloud LLMs (<strong>DeepSeek, Google Gemini</strong>). Powers the conversational AI financial advisor (<code>/api/v1/chat</code>) with live financial history aggregation.
                    </p>
                    <div class="d-flex gap-2 flex-wrap mb-4">
                        <span class="badge bg-light text-dark border px-3 py-2"><i class="fa-brands fa-python me-1"></i>FastAPI 0.110+</span>
                        <span class="badge bg-light text-dark border px-3 py-2"><i class="fa-solid fa-server me-1"></i>MinIO S3 Models</span>
                        <span class="badge bg-light text-dark border px-3 py-2"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Conversational AI</span>
                        <span class="badge bg-light text-dark border px-3 py-2"><i class="fa-solid fa-brain me-1"></i>Llama.cpp / DeepSeek</span>
                        <span class="badge bg-light text-dark border px-3 py-2"><i class="fa-solid fa-gauge-high me-1"></i>Live Telemetry</span>
                    </div>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?= base_url('setup') ?>" class="btn btn-primary btn-lg px-4">
                            <i class="fa-solid fa-download me-2"></i>Model Setup Guide
                        </a>
                        <a href="<?= base_url('faq') ?>" class="btn btn-outline-primary btn-lg px-4">
                            <i class="fa-solid fa-circle-question me-2"></i>Hardware Specs
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="glass-card text-center p-5 shadow-sm">
                        <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">
                            <span class="badge bg-primary bg-opacity-10 text-primary border px-2.5 py-1.5"><i class="fa-solid fa-server me-1"></i> Port 9050</span>
                            <span class="badge bg-warning bg-opacity-10 text-warning border px-2.5 py-1.5"><i class="fa-solid fa-comments me-1"></i> /api/v1/chat</span>
                            <span class="badge bg-success bg-opacity-10 text-success border px-2.5 py-1.5"><i class="fa-solid fa-memory me-1"></i> GGUF Quantized</span>
                            <span class="badge bg-info bg-opacity-10 text-info border px-2.5 py-1.5"><i class="fa-solid fa-shield me-1"></i> 100% On-Premise</span>
                        </div>
                        <i class="fa-solid fa-microchip fa-6x text-primary opacity-50 mb-3"></i>
                        <h5 class="fw-bold mb-1">Local Inference & Financial Chat</h5>
                        <p class="text-muted small mb-0">FastAPI Worker + llama-server Daemon + Dynamic LLM Router</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold h1 mb-3">Why Local LLMs Beat Regex Rules</h2>
                <p class="text-muted mx-auto" style="max-width: 650px;">Traditional regex parsers fail as formats change. A localized LLM understands human syntax, slang, and evolving banking formats.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="glass-card">
                        <div class="d-flex gap-3 mb-3">
                            <div class="icon-box" style="width: 48px; height: 48px; font-size: 1.1rem;"><i class="fa-solid fa-ban text-danger"></i></div>
                            <div>
                                <h5 class="fw-bold">Brittle Regex Parsers</h5>
                                <ul class="text-muted small mb-0 mt-2">
                                    <li class="mb-1.5">Crash when Safaricom alters spacing, words, or adds new disclosures.</li>
                                    <li class="mb-1.5">Incapable of parsing new financial institutions (e.g. SACCOs, micro-lenders).</li>
                                    <li class="mb-1.5">Zero context awareness — cannot distinguish transaction notes from amounts.</li>
                                    <li class="mb-1.5">High maintenance burden with dozens of error-prone regular expressions.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="glass-card">
                        <div class="d-flex gap-3 mb-3">
                            <div class="icon-box" style="width: 48px; height: 48px; font-size: 1.1rem;"><i class="fa-solid fa-wand-magic-sparkles text-primary"></i></div>
                            <div>
                                <h5 class="fw-bold">Semantic GGUF LLM Engine</h5>
                                <ul class="text-muted small mb-0 mt-2">
                                    <li class="mb-1.5">Infers semantic intent across English, Swahili, and Sheng terminology.</li>
                                    <li class="mb-1.5">Adapts seamlessly to new templates and unexpected transaction types.</li>
                                    <li class="mb-1.5">Extracts structured JSON: counterparty, amount, fees, balance, and timestamps.</li>
                                    <li class="mb-1.5">100% private and on-premise — zero API keys or per-token fees.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold h1 mb-3">Two-Stage Processing Architecture</h2>
                <p class="text-muted mx-auto" style="max-width: 600px;">Maximizing throughput by pairing lightning-fast heuristics with deep semantic reasoning.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-3 col-6">
                    <div class="glass-card text-center">
                        <div class="icon-box mx-auto"><i class="fa-solid fa-inbox"></i></div>
                        <h6 class="fw-bold">1. Raw Loot Batch</h6>
                        <p class="text-muted small mb-0">Encrypted SMS batches are ingested from the Android queue into <code>tbl_Loot</code>.</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="glass-card text-center">
                        <div class="icon-box mx-auto"><i class="fa-solid fa-filter"></i></div>
                        <h6 class="fw-bold">2. Pre-Classification</h6>
                        <p class="text-muted small mb-0">Verified financial senders (MPESA, banks) are identified instantly in sub-milliseconds.</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="glass-card text-center">
                        <div class="icon-box mx-auto"><i class="fa-solid fa-microchip"></i></div>
                        <h6 class="fw-bold">3. GGUF Extraction</h6>
                        <p class="text-muted small mb-0">The local Qwen model extracts structured fields into standard JSON under strict temperature controls.</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="glass-card text-center">
                        <div class="icon-box mx-auto"><i class="fa-solid fa-database"></i></div>
                        <h6 class="fw-bold">4. Normalized Ledger</h6>
                        <p class="text-muted small mb-0">Transactions are committed into <code>tbl_Transactions</code> with full audit logs and job metrics.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold h1 mb-3">Enterprise Control & Telemetry</h2>
                <p class="text-muted mx-auto" style="max-width: 650px;">SuperAdmins manage models, tune prompts, and monitor container diagnostics in real time.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="glass-card">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-chart-line text-primary me-2"></i>Live Telemetry Endpoint (<code>/admin/telemetry</code>)</h5>
                        <p class="text-muted small mb-2">Exposes real-time container vitals for high-availability production monitoring:</p>
                        <ul class="text-muted small mb-0">
                            <li class="mb-1"><strong>Process RSS Headroom:</strong> Tracks exact memory footprints of FastAPI and <code>llama-server</code>.</li>
                            <li class="mb-1"><strong>Active Model Metrics:</strong> Displays model parameters, quantization level, and context size.</li>
                            <li class="mb-1"><strong>Health & Uptime:</strong> Instant heartbeat alerts for process crashes or memory pressure.</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="glass-card">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-server text-primary me-2"></i>MinIO S3 Model Repository & Presets</h5>
                        <p class="text-muted small mb-2">Centralized GGUF model storage (<code>mpesa-models</code>) with automated local caching:</p>
                        <ul class="text-muted small mb-0">
                            <li class="mb-1"><strong>Central S3 Bucket:</strong> Stores tested GGUF weights without bloating Docker container layers.</li>
                            <li class="mb-1"><strong>Automated Local Sync:</strong> On container boot or model activation, pulls missing weights to <code>/models/</code> with non-blocking threads.</li>
                            <li class="mb-1"><strong>Hot-Switching & Presets:</strong> Switch active GGUF models on the fly (Qwen 2.5 1.5B/3B, Llama 3.2, DeepSeek) with graceful daemon restarts.</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="glass-card">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-sliders text-primary me-2"></i>Dynamic Prompt Versioning</h5>
                        <p class="text-muted small mb-2">Tune system prompts and output schemas without modifying application code:</p>
                        <ul class="text-muted small mb-0">
                            <li class="mb-1"><strong>Versioned History:</strong> Every prompt edit creates a traceable snapshot in MySQL.</li>
                            <li class="mb-1"><strong>Fallback Safeguard:</strong> Automatically falls back to verified defaults if no active DB prompt is set.</li>
                            <li class="mb-1"><strong>Prompt Optimization:</strong> Adjust instruction framing for specific East African banking formats.</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="glass-card">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-rotate text-primary me-2"></i>Batch Rescan & Full Reset</h5>
                        <p class="text-muted small mb-2">Reprocess historical data safely with interactive modal dialogs:</p>
                        <ul class="text-muted small mb-0">
                            <li class="mb-1"><strong>Incremental Rescan:</strong> Analyzes newly synced or unclassified SMS batches.</li>
                            <li class="mb-1"><strong>Full Reset:</strong> Safely clears previous inferences and re-runs current models across all records.</li>
                            <li class="mb-1"><strong>Live Progress Polling:</strong> Real-time progress bars prevent timeouts during heavy multi-thousand SMS batches.</li>
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
                        <span class="badge bg-light text-dark">FastAPI</span>
                        <span class="badge bg-light text-dark">MinIO S3</span>
                        <span class="badge bg-light text-dark">Redis 7</span>
                        <span class="badge bg-light text-dark">Llama.cpp</span>
                        <span class="badge bg-light text-dark">Qwen 2.5</span>
                        <span class="badge bg-light text-dark">AVX2 / CUDA</span>
                        <span class="badge bg-light text-dark">MySQL 8.4</span>
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