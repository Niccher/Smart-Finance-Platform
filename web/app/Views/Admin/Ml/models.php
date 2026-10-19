<?= $this->extend('Layouts/superadmin') ?>
<?= $this->section('title') ?> ML Models - Mpesa Analyzer <?= $this->endSection() ?>
<?= $this->section('styles') ?>
<style>
    .settings-card { border: none; border-radius: 4px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
    .meta-chip { background:#f4f6fb; border:1px solid #e4e8f0; border-radius:6px; padding:4px 8px; font-size:.78rem; }
</style>
<?= $this->endSection() ?>
<?= $this->section('page_header') ?>
<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
    <div>
        <h2 class="fw-bold mb-1" style="color: var(--primary);"><i class="fa-solid fa-box-open me-2"></i> ML Models</h2>
        <p class="text-secondary mb-0">Upload, inspect and activate LLM models served by the backend.</p>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('content') ?>

<?= view('Admin/Ml/_status', ['status' => $status]) ?>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card settings-card h-100">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-cloud-arrow-up me-2" style="color: var(--primary);"></i> Upload Model</h6>
                    <p class="text-muted small mb-3">Upload a <code>.gguf</code> or <code>.bin</code> file from your computer.</p>
                    <?php if (!$status['reachable']): ?>
                        <div class="alert alert-warning small mb-0">Backend offline — cannot upload.</div>
                    <?php else: ?>
                        <div class="mb-3">
                            <input type="file" id="modelFile" class="form-control" accept=".gguf,.bin">
                        </div>
                        <div id="uploadProgress" class="progress mb-3 d-none" style="height:8px; border-radius:4px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width:0%"></div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($status['reachable']): ?>
                    <button type="button" id="uploadBtn" class="btn btn-primary rounded-pill px-4 fw-semibold w-100 mt-2">
                        <i class="fa-solid fa-upload me-1"></i> Upload File
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card settings-card h-100">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-cloud-arrow-down me-2 text-info"></i> Download from URL</h6>
                    <p class="text-muted small mb-3">Download directly from Hugging Face or any public <code>.gguf</code> URL.</p>
                    <?php if (!$status['reachable']): ?>
                        <div class="alert alert-warning small mb-0">Backend offline — cannot download.</div>
                    <?php else: ?>
                        <?php $existingFilenames = array_column($status['models'] ?? [], 'filename'); ?>
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-1"><i class="fa-solid fa-wand-magic-sparkles me-1 text-primary"></i> Quick Presets (Recommended)</label>
                            <select id="presetSelect" class="form-select form-select-sm">
                                <option value="" selected>-- Select a tested Hugging Face model --</option>
                                <option value="https://huggingface.co/Qwen/Qwen2.5-1.5B-Instruct-GGUF/resolve/main/qwen2.5-1.5b-instruct-q4_k_m.gguf" data-filename="qwen2.5-1.5b-instruct-q4_k_m.gguf">Qwen 2.5 1.5B Instruct Q4_K_M (~1.0 GB) <?= in_array('qwen2.5-1.5b-instruct-q4_k_m.gguf', $existingFilenames) ? '✓ [Downloaded]' : '[Fast & Accurate]' ?></option>
                                <option value="https://huggingface.co/Qwen/Qwen2.5-3B-Instruct-GGUF/resolve/main/qwen2.5-3b-instruct-q4_k_m.gguf" data-filename="qwen2.5-3b-instruct-q4_k_m.gguf">Qwen 2.5 3B Instruct Q4_K_M (~2.0 GB) <?= in_array('qwen2.5-3b-instruct-q4_k_m.gguf', $existingFilenames) ? '✓ [Downloaded]' : '[Higher Intelligence]' ?></option>
                                <option value="https://huggingface.co/bartowski/Llama-3.2-1B-Instruct-GGUF/resolve/main/Llama-3.2-1B-Instruct-Q4_K_M.gguf" data-filename="Llama-3.2-1B-Instruct-Q4_K_M.gguf">Llama 3.2 1B Instruct Q4_K_M (~800 MB) <?= in_array('Llama-3.2-1B-Instruct-Q4_K_M.gguf', $existingFilenames) ? '✓ [Downloaded]' : '[Ultralight]' ?></option>
                                <option value="https://huggingface.co/bartowski/Llama-3.2-3B-Instruct-GGUF/resolve/main/Llama-3.2-3B-Instruct-Q4_K_M.gguf" data-filename="Llama-3.2-3B-Instruct-Q4_K_M.gguf">Llama 3.2 3B Instruct Q4_K_M (~2.0 GB) <?= in_array('Llama-3.2-3B-Instruct-Q4_K_M.gguf', $existingFilenames) ? '✓ [Downloaded]' : '[Meta Llama]' ?></option>
                                <option value="https://huggingface.co/bartowski/DeepSeek-R1-Distill-Qwen-1.5B-GGUF/resolve/main/DeepSeek-R1-Distill-Qwen-1.5B-Q4_K_M.gguf" data-filename="DeepSeek-R1-Distill-Qwen-1.5B-Q4_K_M.gguf">DeepSeek R1 Distill Qwen 1.5B Q4_K_M (~1.1 GB) <?= in_array('DeepSeek-R1-Distill-Qwen-1.5B-Q4_K_M.gguf', $existingFilenames) ? '✓ [Downloaded]' : '[Reasoning]' ?></option>
                                <option value="https://huggingface.co/HuggingFaceTB/SmolLM2-1.7B-Instruct-GGUF/resolve/main/smollm2-1.7b-instruct-q4_k_m.gguf" data-filename="smollm2-1.7b-instruct-q4_k_m.gguf">SmolLM2 1.7B Instruct Q4_K_M (~1.1 GB) <?= in_array('smollm2-1.7b-instruct-q4_k_m.gguf', $existingFilenames) ? '✓ [Downloaded]' : '[Compact]' ?></option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <input type="url" id="downloadUrl" class="form-control form-control-sm" placeholder="https://huggingface.co/.../model.gguf">
                        </div>
                        <div class="mb-2">
                            <input type="text" id="downloadFilename" class="form-control form-control-sm" placeholder="Filename (optional, e.g. model.gguf)">
                        </div>
                        <div class="mb-2">
                            <input type="password" id="downloadHfToken" class="form-control form-control-sm" placeholder="Hugging Face Token (optional for gated models)">
                        </div>
                        <div id="downloadProgressContainer" class="d-none mt-2 mb-2">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span id="downloadStatusText">Downloading...</span>
                                <span id="downloadProgressPct">0%</span>
                            </div>
                            <div class="progress" style="height:8px; border-radius:4px;">
                                <div id="downloadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-info" role="progressbar" style="width:0%"></div>
                            </div>
                            <div id="downloadBytesText" class="small text-muted text-end mt-1" style="font-size:0.75rem;"></div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($status['reachable']): ?>
                    <button type="button" id="btnStartDownload" class="btn btn-outline-info rounded-pill px-4 fw-semibold w-100 mt-2">
                        <i class="fa-solid fa-download me-1"></i> Start Download
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card settings-card h-100">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-microchip me-2" style="color: var(--primary);"></i> Active Model</h6>
                    <?php
                        $active = null;
                        foreach (($status['models'] ?? []) as $m) { if (!empty($m['active'])) { $active = $m; break; } }
                        if ($active): $md = $active['metadata'] ?? [];
                    ?>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                            <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Active</span>
                            <span class="fw-semibold text-truncate" style="max-width:200px;" title="<?= esc($active['filename']) ?>"><?= esc($active['filename']) ?></span>
                            <span class="badge bg-light text-dark border"><?= $active['size_mb'] ?> MB</span>
                        </div>
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <span class="meta-chip"><i class="fa-solid fa-cube me-1"></i><?= esc($md['n_params_label'] ?? '—') ?></span>
                            <span class="meta-chip"><i class="fa-solid fa-sliders me-1"></i><?= esc($md['quantization'] ?? '—') ?></span>
                            <span class="meta-chip"><i class="fa-solid fa-arrows-left-right me-1"></i>ctx <?= esc($md['context_length'] ?? '—') ?></span>
                            <span class="meta-chip"><?= esc($md['architecture'] ?? '—') ?></span>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info small mb-0">
                            <i class="fa-solid fa-info-circle me-1"></i> No local model is currently active. Upload or download a model to activate.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <a href="<?= base_url('admin/ml/config') ?>" class="btn btn-sm btn-outline-secondary w-100 rounded-pill">
                        <i class="fa-solid fa-gear me-1"></i> Configure Engine Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card settings-card">
    <div class="card-body p-4">
        <?= view('Admin/Ml/_nav', ['active' => 'models', 'status' => $status]) ?>

        <?php if (!$status['reachable']): ?>
            <div class="alert alert-warning mb-0">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                The ML backend is not reachable, so models cannot be listed.
            </div>
        <?php elseif (empty($status['models'])): ?>
            <div class="alert alert-info mb-0">No model files reported by the backend. Upload or download one above.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table id="modelTable" class="table table-sm table-striped align-middle">
                    <thead>
                        <tr><th style="width:28%">Model</th><th>Params</th><th>Quant</th><th>Context</th><th>Size</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($status['models'] as $m): $md = $m['metadata'] ?? []; ?>
                        <tr>
                            <td class="fw-semibold">
                                <?= esc($m['filename']) ?>
                                <?php if (!empty($md['name']) && $md['name'] !== $m['filename']): ?>
                                    <div class="text-muted small"><?= esc($md['name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($md['n_params_label'] ?? '—') ?></td>
                            <td><?= esc($md['quantization'] ?? '—') ?></td>
                            <td><?= esc($md['context_length'] ?? '—') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= $m['size_mb'] ?> MB</span></td>
                            <td>
                                <?php if (!empty($m['active'])): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php if (isset($m['cached']) && !$m['cached']): ?>
                                        <span class="badge bg-info-subtle text-info border ms-1" title="Stored in MinIO object storage; will be downloaded automatically when activated"><i class="fa-solid fa-cloud me-1"></i>MinIO</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#meta-<?= md5($m['filename']) ?>">
                                    <i class="fa-solid fa-circle-info"></i> Details
                                </button>
                                <?php if (empty($m['active'])): ?>
                                    <button class="btn btn-sm btn-outline-primary activate-model" data-filename="<?= esc($m['filename']) ?>" data-llm-model="<?= esc(pathinfo($m['filename'], PATHINFO_FILENAME)) ?>">
                                        <i class="fa-solid fa-play me-1"></i> Activate
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger delete-model" data-filename="<?= esc($m['filename']) ?>">
                                        <i class="fa-solid fa-trash me-1"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-success small fw-semibold ms-2"><i class="fa-solid fa-circle-check me-1"></i>Active Model</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="collapse" id="meta-<?= md5($m['filename']) ?>" data-bs-parent="#modelTable">
                            <td colspan="7" class="bg-light">
                                <div class="d-flex flex-wrap gap-2 py-2">
                                    <?php foreach (['architecture','block_count','embedding_length','file_type','params_raw'] as $k): ?>
                                        <?php if (!empty($md[$k])): ?>
                                            <span class="meta-chip"><strong><?= esc($k) ?>:</strong> <?= esc(is_array($md[$k]) ? json_encode($md[$k]) : $md[$k]) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php if (empty($md)): ?><span class="text-muted small">No metadata available.</span><?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="alert alert-info mt-3 mb-0 small">
                <i class="fa-solid fa-circle-info me-1"></i>
                Activating a model updates the backend configuration. A llama.cpp restart applies the new model (and context/batch/gpu changes).
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
async function safeFetchJson(response) {
    const text = await response.text();
    if (!text || !text.trim()) {
        throw new Error(`Server returned empty response (HTTP ${response.status}).`);
    }
    try {
        return JSON.parse(text);
    } catch (e) {
        const clean = text.replace(/<[^>]*>?/gm, ' ').replace(/\s+/g, ' ').trim();
        throw new Error(clean.substring(0, 250) || `Server error (HTTP ${response.status}).`);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Upload Handler
    const uploadBtn = document.getElementById('uploadBtn');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', function() {
            const input = document.getElementById('modelFile');
            const file = input.files && input.files[0];
            if (!file) { Swal.fire('Upload', 'Please choose a model file.', 'warning'); return; }
            if (!/\.(gguf|bin)$/i.test(file.name)) { Swal.fire('Upload', 'Only .gguf and .bin files are allowed.', 'error'); return; }

            const data = new FormData();
            data.append('model', file);
            data.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading...';
            document.getElementById('uploadProgress').classList.remove('d-none');
            const bar = document.querySelector('#uploadProgress .progress-bar');
            bar.style.width = '60%';

            fetch('<?= base_url('admin/ml/models/upload') ?>', { method: 'POST', body: data })
                .then(r => safeFetchJson(r))
                .then(res => {
                    bar.style.width = '100%';
                    if (res.status === 'ok') {
                        Swal.fire('Uploaded!', res.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Upload Failed', res.message, 'error');
                    }
                })
                .catch(err => Swal.fire('Error', err.message, 'error'))
                .finally(() => {
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = '<i class="fa-solid fa-upload me-1"></i> Upload File';
                    document.getElementById('uploadProgress').classList.add('d-none');
                    bar.style.width = '0%';
                });
        });
    }

    // Preset Dropdown Handler
    const presetSelect = document.getElementById('presetSelect');
    if (presetSelect) {
        presetSelect.addEventListener('change', function() {
            const urlInput = document.getElementById('downloadUrl');
            const filenameInput = document.getElementById('downloadFilename');
            if (this.value) {
                if (urlInput) urlInput.value = this.value;
                const opt = this.options[this.selectedIndex];
                const fn = opt.getAttribute('data-filename');
                if (filenameInput && fn) filenameInput.value = fn;
            }
        });
    }

    // Download from URL Handler
    const btnStartDownload = document.getElementById('btnStartDownload');
    let downloadPollTimer = null;

    if (btnStartDownload) {
        btnStartDownload.addEventListener('click', function() {
            const urlInput = document.getElementById('downloadUrl');
            const filenameInput = document.getElementById('downloadFilename');
            const hfTokenInput = document.getElementById('downloadHfToken');
            const url = urlInput ? urlInput.value.trim() : '';

            if (!url) {
                Swal.fire('URL Required', 'Please enter a valid model download URL.', 'warning');
                return;
            }

            btnStartDownload.disabled = true;
            btnStartDownload.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Starting...';

            const progressContainer = document.getElementById('downloadProgressContainer');
            const progressBar = document.getElementById('downloadProgressBar');
            const progressPct = document.getElementById('downloadProgressPct');
            const statusText = document.getElementById('downloadStatusText');
            const bytesText = document.getElementById('downloadBytesText');

            progressContainer?.classList.remove('d-none');
            if (progressBar) progressBar.style.width = '5%';
            if (progressPct) progressPct.innerText = '0%';
            if (statusText) statusText.innerText = 'Connecting...';

            const data = new FormData();
            data.append('url', url);
            if (filenameInput && filenameInput.value.trim()) data.append('filename', filenameInput.value.trim());
            if (hfTokenInput && hfTokenInput.value.trim()) data.append('hf_token', hfTokenInput.value.trim());
            data.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('admin/ml/models/download') ?>', { method: 'POST', body: data })
                .then(r => safeFetchJson(r))
                .then(res => {
                    if (res.status === 'started') {
                        const taskId = res.task_id;
                        btnStartDownload.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Downloading...';
                        
                        downloadPollTimer = setInterval(() => {
                            fetch('<?= base_url('admin/ml/models/download-status') ?>/' + encodeURIComponent(taskId))
                                .then(r => safeFetchJson(r))
                                .then(pollRes => {
                                    if (pollRes.status === 'downloading') {
                                        const pct = pollRes.progress_pct || 0;
                                        if (progressBar) progressBar.style.width = pct + '%';
                                        if (progressPct) progressPct.innerText = pct + '%';
                                        if (statusText) statusText.innerText = 'Downloading ' + (pollRes.filename || '');
                                        if (bytesText && pollRes.total_bytes) {
                                            const recMb = (pollRes.bytes_received / 1048576).toFixed(1);
                                            const totMb = (pollRes.total_bytes / 1048576).toFixed(1);
                                            bytesText.innerText = `${recMb} MB / ${totMb} MB`;
                                        }
                                    } else if (pollRes.status === 'done') {
                                        clearInterval(downloadPollTimer);
                                        if (progressBar) progressBar.style.width = '100%';
                                        if (progressPct) progressPct.innerText = '100%';
                                        if (statusText) statusText.innerText = 'Completed!';
                                        Swal.fire({
                                            title: 'Download Completed!',
                                            text: pollRes.message || 'Model downloaded successfully.',
                                            icon: 'success',
                                            confirmButtonText: 'Great'
                                        }).then(() => location.reload());
                                    } else if (pollRes.status === 'error') {
                                        clearInterval(downloadPollTimer);
                                        btnStartDownload.disabled = false;
                                        btnStartDownload.innerHTML = '<i class="fa-solid fa-download me-1"></i> Start Download';
                                        progressContainer?.classList.add('d-none');
                                        Swal.fire('Download Failed', pollRes.message || pollRes.error || 'Download encountered an error.', 'error');
                                    }
                                })
                                .catch(err => {
                                    console.warn('Poll error:', err);
                                });
                        }, 2000);
                    } else {
                        btnStartDownload.disabled = false;
                        btnStartDownload.innerHTML = '<i class="fa-solid fa-download me-1"></i> Start Download';
                        progressContainer?.classList.add('d-none');
                        Swal.fire('Error', res.message || 'Could not start download.', 'error');
                    }
                })
                .catch(err => {
                    btnStartDownload.disabled = false;
                    btnStartDownload.innerHTML = '<i class="fa-solid fa-download me-1"></i> Start Download';
                    progressContainer?.classList.add('d-none');
                    Swal.fire('Error', err.message, 'error');
                });
        });
    }

    // Model Activation
    document.querySelectorAll('.activate-model').forEach(btn => {
        btn.addEventListener('click', function() {
            const filename = this.dataset.filename;
            const llmModel = this.dataset.llmModel;
            Swal.fire({
                title: 'Activate Model?',
                text: `Set "${filename}" as active model? A llama.cpp restart is required to serve it.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, activate',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (!result.isConfirmed) return;
                Swal.fire({
                    title: 'Activating Model...',
                    text: `Applying "${filename}" and reloading llama.cpp...`,
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

            const data = new FormData();
            data.append('filename', filename);
            data.append('llm_model', llmModel);
            data.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
            fetch('<?= base_url('admin/ml/models/activate') ?>', { method: 'POST', body: data })
                .then(r => safeFetchJson(r))
                .then(res => {
                    if (res.status === 'ok') {
                        Swal.fire('Model Activated', res.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Activation Failed', res.message, 'error');
                    }
                })
                .catch(err => Swal.fire('Error', err.message, 'error'));
            });
        });
    });

    // Model Deletion
    document.querySelectorAll('.delete-model').forEach(btn => {
        btn.addEventListener('click', function() {
            const filename = this.dataset.filename;
            Swal.fire({
                title: 'Delete Model?',
                text: `Permanently delete "${filename}" from the backend?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (!result.isConfirmed) return;
                const data = new FormData();
                data.append('filename', filename);
                data.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
                fetch('<?= base_url('admin/ml/models/delete') ?>', { method: 'POST', body: data })
                    .then(r => safeFetchJson(r))
                    .then(res => {
                        if (res.status === 'ok') {
                            Swal.fire('Deleted', res.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Delete Failed', res.message, 'error');
                        }
                    })
                    .catch(err => Swal.fire('Error', err.message, 'error'));
            });
        });
    });
});
</script>
<?= $this->endSection() ?>