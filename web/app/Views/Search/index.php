<?= $this->extend('Layouts/admin') ?>

<?= $this->section('title') ?> Search & Filtering - Mpesa Analyzer <?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<style>
    .form-control, .form-select {
        border-radius: 4px;
        padding: 0.6rem 1rem;
        border: 1px solid #e0e0e0;
    }
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.25rem rgba(93, 95, 239, 0.1);
    }
    .btn-search {
        padding: 0.6rem 2rem;
        border-radius: 4px;
        font-weight: 600;
    }
    .filter-btn.active { box-shadow: 0 4px 12px rgba(93, 95, 239, 0.25); }
    .page-item.active .page-link {
        background-color: var(--primary);
        border-color: var(--primary);
    }
    .page-link { color: var(--primary); border-radius: 4px !important; margin: 0 2px; }
    .pagination { margin: 0; }
    .badge-dir { font-size: 0.65rem; padding: 0.2em 0.5em; }
    .table tbody tr:hover { background: rgba(93,95,239,0.03); }
</style>
<?= $this->endSection() ?>

<?= $this->section('page_header') ?>
<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
    <div>
        <h2 class="fw-bold mb-1" style="color: var(--primary);">Search & Filtering</h2>
        <p class="text-secondary mb-0">Deep dive into your transaction history with advanced filters.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-1">
        <a href="<?= base_url('dashboard/search?category=finances') ?>" class="btn btn-outline-dark filter-btn btn-sm rounded-pill px-3 <?= ($filters['category'] ?? '') === 'finances' ? 'active' : '' ?>"><i class="fa-solid fa-coins me-1"></i> All Finances</a>
        <a href="<?= base_url('dashboard/search?category=money_in') ?>" class="btn btn-outline-primary filter-btn btn-sm rounded-pill px-3 <?= ($filters['category'] ?? '') === 'money_in' ? 'active' : '' ?>"><i class="fa-solid fa-arrow-down me-1"></i> Money In</a>
        <a href="<?= base_url('dashboard/search?category=money_out') ?>" class="btn btn-outline-danger filter-btn btn-sm rounded-pill px-3 <?= ($filters['category'] ?? '') === 'money_out' ? 'active' : '' ?>"><i class="fa-solid fa-arrow-up me-1"></i> Money Out</a>
        <a href="<?= base_url('dashboard/search?category=notifications') ?>" class="btn btn-outline-secondary filter-btn btn-sm rounded-pill px-3 <?= ($filters['category'] ?? '') === 'notifications' ? 'active' : '' ?>"><i class="fa-solid fa-bell me-1"></i> Notifications</a>
        
        <div class="vr mx-1 d-none d-md-block"></div>
        
        <button onclick="exportTableToPDF('searchTable', 'search_results', this)" class="btn btn-dark filter-btn btn-sm rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-file-pdf me-1"></i> PDF
        </button>
        <button onclick="exportTableToCSV('searchTable', 'search_results')" class="btn btn-dark filter-btn btn-sm rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-file-csv me-1"></i> CSV
        </button>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="GET" action="<?= base_url('dashboard/search') ?>" class="row g-3">
            <input type="hidden" name="category" value="<?= htmlspecialchars($filters['category'] ?? '') ?>">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-uppercase text-secondary">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Keyword, recipient or sender..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-uppercase text-secondary">Sender</label>
                <select name="sender" class="form-select">
                    <option value="">All Senders</option>
                    <?php foreach ($senders as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= ($filters['sender'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-uppercase text-secondary">Date Range</label>
                <input type="text" id="reportrange" class="form-control" placeholder="Select dates...">
                <input type="hidden" name="date_from" id="date_from" value="<?= esc($filters['date_from'] ?? '', 'attr') ?>">
                <input type="hidden" name="date_to" id="date_to" value="<?= esc($filters['date_to'] ?? '', 'attr') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-uppercase text-secondary">Amount Range (Ksh)</label>
                <div class="input-group">
                    <input type="number" name="min_amount" class="form-control" placeholder="Min" value="<?= esc($filters['min_amount'] ?? '', 'attr') ?>">
                    <input type="number" name="max_amount" class="form-control" placeholder="Max" value="<?= esc($filters['max_amount'] ?? '', 'attr') ?>">
                </div>
            </div>
            <div class="col-12 text-end mt-4">
                <a href="<?= base_url('dashboard/search') ?>" class="btn btn-light me-2 px-4">Reset</a>
                <button type="submit" class="btn btn-primary btn-search shadow-sm">
                    <i class="fa-solid fa-magnifying-glass me-2"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="searchTable" class="table table-bordered table-hover table-striped mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Date &amp; Time</th>
                        <th>Category / Sender</th>
                        <th>Amount</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                        <?php 
                            $bodyStr = base64_decode($tx->sms_body);
                            $dir = strtolower($tx->cl_direction ?? '');
                            $cat = $tx->cl_category ?? 'Unclassified';
                            $amt = (float)($tx->analyzed_amount ?? 0);
                            
                            if ($dir === 'incoming') {
                                $badgeClass = 'bg-success';
                                $dirLabel = '↓ Money In';
                            } elseif ($dir === 'outgoing') {
                                $badgeClass = 'bg-danger';
                                $dirLabel = '↑ Money Out';
                            } else {
                                $badgeClass = 'bg-secondary';
                                $dirLabel = '— Notification';
                            }

                            $ts = is_numeric($tx->sms_time) && $tx->sms_time > 1000000000000
                                ? (int)($tx->sms_time / 1000)
                                : (is_numeric($tx->sms_time) ? (int)$tx->sms_time : strtotime($tx->sms_time));
                            $datePart = date('D, M d, Y', $ts);
                            $timePart = date('h:i A', $ts);
                        ?>
                        <tr>
                            <td class="ps-4" style="min-width:120px;">
                                <div class="fw-semibold small lh-1"><?= $datePart ?></div>
                                <div class="text-muted" style="font-size:0.7rem; line-height:1;"><?= $timePart ?></div>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <span class="badge rounded-pill <?= $badgeClass ?> badge-dir w-auto"><?= $dirLabel ?></span>
                                    <span class="small text-secondary" style="font-size:0.7rem;"><?= htmlspecialchars($cat) ?></span>
                                    <span class="font-monospace small text-primary" style="font-size:0.72rem;"><?= htmlspecialchars($tx->sms_number) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="fw-bold small <?= $dir === 'incoming' ? 'text-success' : ($dir === 'outgoing' ? 'text-danger' : 'text-muted') ?>">
                                    <?php if ($amt > 0): ?>
                                        <?= $dir === 'incoming' ? '+' : ($dir === 'outgoing' ? '-' : '') ?>
                                        <?= currency_symbol() ?> <?= number_format($amt, 2) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-muted small" style="word-break: break-word;">
                                        <?= htmlspecialchars($bodyStr) ?>
                                    </span>
                                    <button class="btn btn-sm btn-light rounded-circle shadow-sm view-sms-btn flex-shrink-0"
                                            data-time="<?= $datePart ?> <?= $timePart ?>"
                                            data-body="<?= htmlspecialchars($bodyStr) ?>"
                                            title="View details">
                                        <i class="fa-solid fa-eye text-primary"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-inbox fa-2x mb-3 d-block opacity-25"></i>
                                No transactions found matching your filters
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top bg-light mt-3">
            <div class="text-muted small">
                Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
            </div>
            <?php
            $preserveParams = [];
            foreach (['search', 'sender', 'category', 'date_from', 'date_to', 'min_amount', 'max_amount'] as $p) {
                if (!empty($filters[$p])) $preserveParams[$p] = $filters[$p];
            }
            $buildUrl = function($pageNum) use ($preserveParams) {
                $params = array_merge($preserveParams, ['page' => $pageNum]);
                return base_url('dashboard/search?' . http_build_query($params));
            };
            ?>
            <nav>
                <ul class="pagination pagination-sm mb-0 gap-1">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link shadow-none" href="<?= $buildUrl($page - 1) ?>">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php
                    $start = max(1, $page - 3);
                    $end   = min($totalPages, $page + 3);
                    if ($start > 1): ?>
                        <li class="page-item">
                            <a class="page-link shadow-none" href="<?= $buildUrl(1) ?>">1</a>
                        </li>
                        <?php if ($start > 2): ?>
                            <li class="page-item disabled"><span class="page-link shadow-none">…</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link shadow-none" href="<?= $buildUrl($i) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link shadow-none">…</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link shadow-none" href="<?= $buildUrl($totalPages) ?>"><?= $totalPages ?></a>
                        </li>
                    <?php endif; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link shadow-none" href="<?= $buildUrl($page + 1) ?>">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="text-muted small">
                <?= number_format($total) ?> total records
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- SMS Detail Modal -->
<div class="modal fade" id="smsDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="text-secondary small fw-bold text-uppercase">Time</label>
                    <p id="modal-sms-time" class="mb-0 fw-semibold"></p>
                </div>
                <div>
                    <label class="text-secondary small fw-bold text-uppercase">Message Content</label>
                    <div class="p-3 bg-light mt-1" id="modal-sms-body" style="white-space: pre-wrap; font-size: 0.9rem;"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script>
    $(document).ready(function() {
        const detailModal = new bootstrap.Modal(document.getElementById('smsDetailModal'));
        const modalTime = document.getElementById('modal-sms-time');
        const modalBody = document.getElementById('modal-sms-body');

        $(document).on('click', '.view-sms-btn', function() {
            modalTime.textContent = $(this).attr('data-time');
            modalBody.innerHTML = $(this).attr('data-body').replace(/\n/g, '<br>');
            detailModal.show();
        });

        // Date Range Picker
        var start = <?= !empty($filters['date_from']) ? "moment('".$filters['date_from']."')" : "moment().subtract(29, 'days')" ?>;
        var end = <?= !empty($filters['date_to']) ? "moment('".$filters['date_to']."')" : "moment()" ?>;

        function cb(start, end) {
            $('#reportrange').val(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
            $('#date_from').val(start.format('YYYY-MM-DD'));
            $('#date_to').val(end.format('YYYY-MM-DD'));
        }

        $('#reportrange').daterangepicker({
            startDate: start,
            endDate: end,
            ranges: {
               'Today': [moment(), moment()],
               'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
               'Last 7 Days': [moment().subtract(6, 'days'), moment()],
               'Last 30 Days': [moment().subtract(29, 'days'), moment()],
               'This Month': [moment().startOf('month'), moment().endOf('month')],
               'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, cb);

        cb(start, end);

        // Make filter buttons persist the hidden category field
        $('.filter-btn').on('click', function(e) {
            e.preventDefault();
            const url = new URL(this.href);
            const cat = url.searchParams.get('category') || '';
            $('input[name="category"]').val(cat);
            $('form').submit();
        });
    });

    // CSV Export (client-side from table data)
    window.exportTableToCSV = function(tableId, filename) {
        const table = document.getElementById(tableId);
        if (!table) return;
        let csv = [];
        const rows = table.querySelectorAll('tr');
        rows.forEach(row => {
            const cols = row.querySelectorAll('td, th');
            const vals = Array.from(cols).map(c => {
                let txt = c.textContent.trim().replace(/,/g, ' ').replace(/\n/g, ' ');
                return `"${txt}"`;
            });
            csv.push(vals.join(','));
        });
        const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename + '.csv';
        a.click();
        URL.revokeObjectURL(url);
    };

    // PDF Export — preserves table styling via html2canvas
    window.exportTableToPDF = function(tableId, filename, btnEl) {
        const btn = btnEl || document.querySelector(`button[onclick*="${tableId}"]`);
        if (!btn) return;
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Exporting...';

        const table = document.getElementById(tableId);
        if (!table) { btn.disabled = false; btn.innerHTML = orig; return; }

        // Clone table into a wrapper to capture full width with all CSS
        const wrapper = document.createElement('div');
        wrapper.style.padding = '20px';
        wrapper.style.background = '#fff';
        wrapper.style.width = table.offsetWidth + 'px';
        const clone = table.cloneNode(true);
        wrapper.appendChild(clone);
        document.body.appendChild(wrapper);

        html2canvas(wrapper, {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff'
        }).then(canvas => {
            document.body.removeChild(wrapper);
            const imgData = canvas.toDataURL('image/png');
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('l', 'mm', 'a4');
            const pdfW = pdf.internal.pageSize.getWidth();
            const pdfH = (canvas.height * pdfW) / canvas.width;
            pdf.addImage(imgData, 'PNG', 0, 0, pdfW, pdfH);
            pdf.save(filename + '.pdf');
            btn.disabled = false;
            btn.innerHTML = orig;
        }).catch(err => {
            document.body.removeChild(wrapper);
            console.error('PDF export error:', err);
            if (typeof showAlert === 'function') showAlert('Export Failed', 'Could not generate PDF.', 'danger');
            btn.disabled = false;
            btn.innerHTML = orig;
        });
    }
</script>
<?= $this->endSection() ?>
