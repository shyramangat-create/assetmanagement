<?php
require_once __DIR__ . '/../includes/auth.php';

$type = $reportType ?? 'assets';
$meta = [
    'assets' => ['Hardware Asset Report', 'Purchased items and acquisition costs'],
    'depreciation' => ['Depreciation Report', 'Asset value and depreciation calculations'],
    'assignments' => ['Assignment Report', 'Asset assignment history'],
    'returns' => ['Return Report', 'Asset return history'],
    'transfers' => ['Transfer Report', 'Asset transfer history'],
    'maintenance' => ['Maintenance Report', 'Maintenance records and service dates'],
    'vendors' => ['Vendor Report', 'Registered supplier and service provider details'],
    'expenses' => ['Expense Report', 'Asset-related expenses'],
];

if (!isset($meta[$type])) {
    http_response_code(404);
    exit('Report not found.');
}

$title = $meta[$type][0];
$subtitle = $meta[$type][1];
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$error = '';

if (($from !== '' && !validDate($from)) || ($to !== '' && !validDate($to)))
    $error = 'Enter valid dates using the date pickers.';

if ($from && $to && $from > $to)
    $error = 'Start date cannot be after end date.';

if ($error) {
    $from = '';
    $to = '';
}

$headers = [];
$rows = [];
$filters = [];
$params = [];
$stmt = null;

if ($type === 'assets') {
    $assetId = getInt('asset_id');
    $where = '1=1';

    if ($from) {
        $where .= ' AND a.purchase_date>=?';
        $params[] = $from;
    }

    if ($to) {
        $where .= ' AND a.purchase_date<=?';
        $params[] = $to;
    }

    if ($assetId) {
        $where .= ' AND a.id=?';
        $params[] = $assetId;
    }

    $stmt = $pdo->prepare(
        "SELECT a.asset_code,a.name,a.purchase_date,a.purchase_cost,a.status FROM assets a WHERE {$where} ORDER BY a.purchase_date DESC,a.asset_code"
    );
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    $headers = [
        'Date Purchased',
        'Asset ID',
        'Asset Name',
        'Quantity',
        'Purchase Cost',
        'Status'
    ];

    foreach ($records as $r)
        $rows[] = [
            $r['purchase_date'],
            $r['asset_code'],
            $r['name'],
            '1',
            money($r['purchase_cost']),
            $r['status']
        ];
} elseif ($type === 'depreciation') {
    $year = (int) ($_GET['year'] ?? date('Y'));

    if ($year < 2000 || $year > 2100)
        $year = (int) date('Y');

    $category = (int) ($_GET['category_id'] ?? 0);
    $department = (int) ($_GET['department_id'] ?? 0);
    $method = $_GET['method'] ?? 'all';
    $status = $_GET['status'] ?? 'all';
    $where = '1=1';
    $p = [];

    if ($category) {
        $where .= ' AND a.category_id=?';
        $p[] = $category;
    }

    if ($department) {
        $where .= ' AND a.department_id=?';
        $p[] = $department;
    }

    if (in_array($method, ['Straight Line', 'Written Down Value'], true)) {
        $where .= ' AND a.depreciation_method=?';
        $p[] = $method;
    }

    if (in_array($status, ['Available', 'Assigned', 'Under Maintenance', 'Disposed'], true)) {
        $where .= ' AND a.status=?';
        $p[] = $status;
    }

    $s = $pdo->prepare(
        "SELECT a.*,c.name category,d.name department FROM assets a LEFT JOIN categories c ON c.id=a.category_id LEFT JOIN departments d ON d.id=a.department_id WHERE {$where} ORDER BY a.asset_code"
    );
    $s->execute($p);
    $asOf = ($year >= (int) date('Y'))
        ? new DateTimeImmutable('today')
        : new DateTimeImmutable($year . '-12-31');
    $headers = [
        'Asset ID',
        'Asset Name',
        'Category',
        'Department',
        'Status',
        'Method',
        'Purchase Cost',
        'Salvage Value',
        'Annual Depreciation',
        'Accumulated Depreciation',
        'Value as of ' . $year
    ];

    foreach ($s->fetchAll() as $a) {
        $d = calculateDepreciation($a, $asOf);
        $rows[] = [
            $a['asset_code'],
            $a['name'],
            $a['category'] ?? '—',
            $a['department'] ?? '—',
            $a['status'],
            $a['depreciation_enabled'] ? $a['depreciation_method'] : 'Disabled',
            money($a['purchase_cost']),
            money($a['salvage_value']),
            money($d['annual_depreciation']),
            money($d['accumulated_depreciation']),
            money($d['current_value'])
        ];
    }
} elseif ($type === 'assignments') {
    $where = '1=1';

    if ($from) {
        $where .= ' AND DATE(x.assignment_date)>=?';
        $params[] = $from;
    }

    if ($to) {
        $where .= ' AND DATE(x.assignment_date)<=?';
        $params[] = $to;
    }

    $stmt = $pdo->prepare(
        "SELECT x.*,d.name department,GROUP_CONCAT(CONCAT(a.asset_code,' - ',a.name) ORDER BY a.asset_code SEPARATOR '; ') assets FROM assignments x LEFT JOIN departments d ON d.id=x.department_id LEFT JOIN assignment_items ai ON ai.assignment_id=x.id LEFT JOIN assets a ON a.id=ai.asset_id WHERE {$where} GROUP BY x.id ORDER BY x.assignment_date DESC"
    );
    $stmt->execute($params);
    $headers = [
        'Date',
        'Holder',
        'Department',
        'Mobile',
        'Assets',
        'Condition',
        'Assigned By',
        'Notes'
    ];

    foreach ($stmt->fetchAll() as $r)
        $rows[] = [
            $r['assignment_date'],
            $r['assigned_to'],
            $r['department'] ?? '—',
            $r['mobile'] ?? '—',
            $r['assets'] ?? '—',
            $r['condition_at_assignment'],
            $r['assigned_by'],
            $r['notes'] ?? ''
        ];
} elseif ($type === 'returns') {
    $where = '1=1';

    if ($from) {
        $where .= ' AND DATE(r.return_date)>=?';
        $params[] = $from;
    }

    if ($to) {
        $where .= ' AND DATE(r.return_date)<=?';
        $params[] = $to;
    }

    $stmt = $pdo->prepare(
        "SELECT r.*,a.asset_code,a.name FROM returns r JOIN assets a ON a.id=r.asset_id WHERE {$where} ORDER BY r.return_date DESC"
    );
    $stmt->execute($params);
    $headers = [
        'Return Date',
        'Asset ID',
        'Asset Name',
        'Holder',
        'Condition',
        'Damaged',
        'Returned By',
        'Notes'
    ];

    foreach ($stmt->fetchAll() as $r)
        $rows[] = [
            $r['return_date'],
            $r['asset_code'],
            $r['name'],
            $r['holder_name'],
            $r['condition_on_return'],
            $r['is_damaged'] ? 'Yes' : 'No',
            $r['returned_by'],
            $r['notes'] ?? ''
        ];
} elseif ($type === 'transfers') {
    $where = '1=1';

    if ($from) {
        $where .= ' AND DATE(t.transfer_date)>=?';
        $params[] = $from;
    }

    if ($to) {
        $where .= ' AND DATE(t.transfer_date)<=?';
        $params[] = $to;
    }

    $stmt = $pdo->prepare(
        "SELECT t.*,a.asset_code,a.name,d.name department,l.name location FROM transfers t JOIN assets a ON a.id=t.asset_id LEFT JOIN departments d ON d.id=t.new_department_id LEFT JOIN locations l ON l.id=t.new_location_id WHERE {$where} ORDER BY t.transfer_date DESC"
    );
    $stmt->execute($params);
    $headers = [
        'Date',
        'Asset ID',
        'Asset Name',
        'Old Holder',
        'New Holder',
        'New Department',
        'New Location',
        'Reason',
        'Transferred By'
    ];

    foreach ($stmt->fetchAll() as $r)
        $rows[] = [
            $r['transfer_date'],
            $r['asset_code'],
            $r['name'],
            $r['old_holder'],
            $r['new_holder'],
            $r['department'] ?? '—',
            $r['location'] ?? '—',
            $r['reason'] ?? '',
            $r['transferred_by']
        ];
} elseif ($type === 'maintenance') {
    $where = '1=1';

    if ($from) {
        $where .= ' AND DATE(m.maintenance_date)>=?';
        $params[] = $from;
    }

    if ($to) {
        $where .= ' AND DATE(m.maintenance_date)<=?';
        $params[] = $to;
    }

    $stmt = $pdo->prepare(
        "SELECT m.*,a.asset_code,a.name,v.name vendor FROM maintenance m JOIN assets a ON a.id=m.asset_id LEFT JOIN vendors v ON v.id=m.vendor_id WHERE {$where} ORDER BY m.maintenance_date DESC"
    );
    $stmt->execute($params);
    $headers = [
        'Date',
        'Asset ID',
        'Asset Name',
        'Type',
        'Vendor',
        'Cost',
        'Next Service',
        'Status',
        'Notes'
    ];

    foreach ($stmt->fetchAll() as $r)
        $rows[] = [
            $r['maintenance_date'],
            $r['asset_code'],
            $r['name'],
            $r['maintenance_type'],
            $r['vendor'] ?? '—',
            money($r['cost']),
            $r['next_service_date'] ?? '—',
            $r['status'],
            $r['notes'] ?? ''
        ];
} elseif ($type === 'vendors') {
    $where = '1=1';

    if ($from) {
        $where .= ' AND DATE(v.created_at)>=?';
        $params[] = $from;
    }

    if ($to) {
        $where .= ' AND DATE(v.created_at)<=?';
        $params[] = $to;
    }

    $stmt = $pdo->prepare(
        "SELECT v.*,COUNT(a.id) asset_count FROM vendors v LEFT JOIN assets a ON a.vendor_id=v.id WHERE {$where} GROUP BY v.id ORDER BY v.name"
    );
    $stmt->execute($params);
    $headers = [
        'Vendor',
        'Contact Person',
        'Mobile',
        'Email',
        'Address',
        'Assets',
        'Date Added'
    ];

    foreach ($stmt->fetchAll() as $r)
        $rows[] = [
            $r['name'],
            $r['contact_person'],
            $r['mobile'],
            $r['email'] ?? '—',
            $r['address'] ?? '—',
            (string) $r['asset_count'],
            $r['created_at']
        ];
} elseif ($type === 'expenses') {
    $where = '1=1';

    if ($from) {
        $where .= ' AND DATE(e.expense_date)>=?';
        $params[] = $from;
    }

    if ($to) {
        $where .= ' AND DATE(e.expense_date)<=?';
        $params[] = $to;
    }

    $stmt = $pdo->prepare(
        "SELECT e.*,a.asset_code,a.name asset_name FROM expenses e JOIN assets a ON a.id=e.asset_id WHERE {$where} ORDER BY e.expense_date DESC"
    );
    $stmt->execute($params);
    $headers = [
        'Date',
        'Asset ID',
        'Asset Name',
        'Category',
        'Person / Vendor',
        'Amount',
        'Description'
    ];

    foreach ($stmt->fetchAll() as $r)
        $rows[] = [
            $r['expense_date'],
            $r['asset_code'],
            $r['asset_name'],
            $r['category'],
            $r['involved_person'] ?? '—',
            money($r['amount']),
            $r['description'] ?? ''
        ];
}

$assetOptions = $pdo->query('SELECT id,asset_code,name FROM assets ORDER BY asset_code')->fetchAll();
$categories = fetchOptions($pdo, 'categories');
$departments = fetchOptions($pdo, 'departments');
$totalRows = count($rows);
$sumAmount = 0;

if ($type === 'expenses')
    foreach ($rows as $r)
        $sumAmount += (float) str_replace(['₱', ','], '', $r[5]);

$pageTitle = $title;
$activePage = 'reports';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-area">
    <header class="topbar no-print">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Reports</h2>
                <div class="topbar-subtitle"><?= e($title) ?></div>
            </div>
        </div>
        <a class="btn btn-outline-secondary" href="index.php"><i class="fa-solid fa-arrow-left me-1"></i> All Reports</a>
    </header>

    <div class="content-wrap business-report">
        <div class="page-heading no-print">
            <div>
                <h1><?= e($title) ?></h1>
                <p><?= e($subtitle) ?></p>
            </div>
            <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Print Report</button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="get" class="filter-panel row g-2 align-items-end no-print">
            <?php if ($type === 'depreciation'): ?>
                <div class="col-sm-3">
                    <label class="form-label">Year</label>
                    <input type="number" class="form-control" name="year" min="2000" max="2100" value="<?= e($_GET['year'] ?? date('Y')) ?>">
                </div>

                <div class="col-sm-3">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category_id">
                        <option value="0">All categories</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"<?= selected($_GET['category_id'] ?? 0, $c['id']) ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-3">
                    <label class="form-label">Department</label>
                    <select class="form-select" name="department_id">
                        <option value="0">All departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"<?= selected($_GET['department_id'] ?? 0, $d['id']) ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-3">
                    <label class="form-label">Method</label>
                    <select class="form-select" name="method">
                        <option value="all">All methods</option>
                        <option<?= selected($_GET['method'] ?? 'all', 'Straight Line') ?>>Straight Line</option>
                        <option<?= selected($_GET['method'] ?? 'all', 'Written Down Value') ?>>Written Down Value</option>
                    </select>
                </div>

                <div class="col-sm-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="all">All statuses</option>
                        <?php foreach (['Available', 'Assigned', 'Under Maintenance', 'Disposed'] as $s): ?>
                            <option<?= selected($_GET['status'] ?? 'all', $s) ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="col-sm-3">
                    <label class="form-label">From</label>
                    <input type="date" class="form-control" name="from" value="<?= e($from) ?>">
                </div>

                <div class="col-sm-3">
                    <label class="form-label">To</label>
                    <input type="date" class="form-control" name="to" value="<?= e($to) ?>">
                </div>

                <?php if ($type === 'assets'): ?>
                    <div class="col-sm-4">
                        <label class="form-label">Asset</label>
                        <select class="form-select" name="asset_id">
                            <option value="0">All assets</option>
                            <?php foreach ($assetOptions as $a): ?>
                                <option value="<?= $a['id'] ?>"<?= selected($_GET['asset_id'] ?? 0, $a['id']) ?>><?= e($a['asset_code'] . ' · ' . $a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="col-auto">
                <button class="btn btn-primary">Apply</button><a class="btn btn-light" href="<?= e(basename($_SERVER['PHP_SELF'])) ?>">Reset</a>
            </div>
        </form>

        <?php $reportPages = $type === 'assets' ? (array_chunk($rows, 11) ?: [[]]) : [$rows]; ?>
        <?php foreach ($reportPages as $pageIndex => $pageRows): ?>
        <section class="report-sheet<?= $type === 'assets' ? ' hardware-sheet' : '' ?>">
        <div class="report-print-header">
            <h2>Asset Management System</h2>
            <p class="company-contact">123 Sample Street, Sample City, Philippines<br>Contact number: (000) 000-0000</p>
            <h3 class="report-document-title"><?= e($title) ?></h3>
        </div>
        <div class="card report-register">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title-sm">Detailed records</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <?php foreach ($headers as $h): ?>
                                    <th scope="col"><?= e($h) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pageRows as $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): ?>
                                        <td><?= e($cell) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="<?= count($headers) ?>" class="empty-state">No records match the selected filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($type === 'expenses' && $rows): ?>
                    <div class="text-end fw-bold mt-3">Total in this report: <?= money($sumAmount) ?></div>
                <?php endif; ?>
                <?php if ($type === 'assets' && $pageIndex === count($reportPages) - 1): ?>
                    <div class="report-purchase-total">
                        <strong>Total items purchased: <?= number_format($totalRows) ?></strong>
                        <strong>Total purchase cost: <?= money(array_sum(array_column($records, 'purchase_cost'))) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="report-document-footer"><span>Internal use · <?= e($title) ?></span><span><?= $pageIndex === count($reportPages) - 1 ? 'End of report' : 'Continued' ?><?= $type === 'assets' ? ' · Page ' . ($pageIndex + 1) . ' of ' . count($reportPages) : '' ?></span></div>
        </section>
        <?php endforeach; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
