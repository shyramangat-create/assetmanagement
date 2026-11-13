<?php
require_once __DIR__ . '/includes/auth.php';
$success = flash('success');
$error = flash('error');
$preset = $_GET['range'] ?? 'monthly';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
try {
    [$dateFrom,$dateTo] = dateRange($preset, $from, $to);
} catch (Throwable $e) {
    $preset = 'monthly';
    [$dateFrom,$dateTo] = dateRange('monthly');
}
$assetWhere = '1=1';
$assetParams = [];
if ($dateFrom) {
    $assetWhere .= ' AND purchase_date>=?';
    $assetParams[] = $dateFrom;
}
if ($dateTo) {
    $assetWhere .= ' AND purchase_date<=?';
    $assetParams[] = $dateTo;
}
$scalar = function (string $sql, array $params = []) use ($pdo) {
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return (float) $s->fetchColumn();
};
$cards = [];
$cards[] = [
    'Total Assets',
    (int) $scalar("SELECT COUNT(*) FROM assets WHERE {$assetWhere}", $assetParams),
    'fa-boxes-stacked',
    'blue'
];
$cards[] = [
    'Available Assets',
    (int) $scalar("SELECT COUNT(*) FROM assets WHERE status='Available' AND {$assetWhere}", $assetParams),
    'fa-circle-check',
    'green'
];
$cards[] = [
    'Under Maintenance',
    (int) $scalar("SELECT COUNT(*) FROM assets WHERE status='Under Maintenance' AND {$assetWhere}", $assetParams),
    'fa-screwdriver-wrench',
    'orange'
];
$cards[] = [
    'Upcoming Maintenance',
    (int) $scalar(
        "SELECT COUNT(DISTINCT a.id) FROM assets a JOIN maintenance m ON m.asset_id=a.id WHERE a.status<>'Disposed' AND m.next_service_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND m.id=(SELECT MAX(m2.id) FROM maintenance m2 WHERE m2.asset_id=a.id)"
    ),
    'fa-calendar-days',
    'purple'
];
$cards[] = [
    'Disposed',
    (int) $scalar("SELECT COUNT(*) FROM assets WHERE status='Disposed' AND {$assetWhere}", $assetParams),
    'fa-ban',
    'gray'
];
$fetchSeries = function (string $sql, array $params = []) use ($pdo) {
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return $s->fetchAll();
};
$statusRows = $fetchSeries("SELECT status AS label,COUNT(*) AS total FROM assets WHERE {$assetWhere} GROUP BY status", $assetParams);
$categoryRows = $fetchSeries(
    "SELECT COALESCE(c.name,'Uncategorized') AS label,COUNT(a.id) AS total FROM assets a LEFT JOIN categories c ON c.id=a.category_id WHERE {$assetWhere} GROUP BY c.id,c.name ORDER BY total DESC LIMIT 8",
    $assetParams
);
$deptRows = $fetchSeries(
    "SELECT COALESCE(d.name,'Unassigned') AS label,COUNT(a.id) AS total FROM assets a LEFT JOIN departments d ON d.id=a.department_id WHERE {$assetWhere} GROUP BY d.id,d.name ORDER BY total DESC LIMIT 8",
    $assetParams
);
$valueRows = $fetchSeries(
    "SELECT COALESCE(c.name,'Uncategorized') AS label,SUM(a.purchase_cost) AS total FROM assets a LEFT JOIN categories c ON c.id=a.category_id WHERE {$assetWhere} GROUP BY c.id,c.name ORDER BY total DESC LIMIT 8",
    $assetParams
);
$conditionRows = $fetchSeries("SELECT condition_status AS label,COUNT(*) AS total FROM assets WHERE {$assetWhere} GROUP BY condition_status", $assetParams);
$locationRows = $fetchSeries(
    "SELECT COALESCE(l.name,'Unassigned') AS label,COUNT(a.id) AS total FROM assets a LEFT JOIN locations l ON l.id=a.location_id WHERE {$assetWhere} GROUP BY l.id,l.name ORDER BY total DESC LIMIT 8",
    $assetParams
);
$assetRows = $fetchSeries(
    "SELECT purchase_cost,purchase_date,salvage_value,useful_life_years,depreciation_enabled,depreciation_method,wdv_rate FROM assets WHERE {$assetWhere}",
    $assetParams
);
$depCurrent = 0;
$depAccum = 0;
$depAnnual = 0;
foreach ($assetRows as $a) {
    $d = calculateDepreciation($a);
    $depCurrent += $d['current_value'];
    $depAccum += $d['accumulated_depreciation'];
    $depAnnual += $d['annual_depreciation'];
}
$utilTotal = (int) $scalar("SELECT COUNT(*) FROM assets WHERE status<>'Disposed' AND {$assetWhere}", $assetParams);
$utilAssigned = (int) $scalar("SELECT COUNT(*) FROM assets WHERE status='Assigned' AND {$assetWhere}", $assetParams);
$utilPct = $utilTotal ? round($utilAssigned / $utilTotal * 100, 1) : 0;
$expenseWhere = '1=1';
$expenseParams = [];
if ($dateFrom) {
    $expenseWhere .= ' AND expense_date>=?';
    $expenseParams[] = $dateFrom;
}if ($dateTo) {
    $expenseWhere .= ' AND expense_date<=?';
    $expenseParams[] = $dateTo;
}
$expenseRows = $fetchSeries(
    "SELECT DATE_FORMAT(expense_date,'%Y-%m') AS label,SUM(amount) AS total FROM expenses WHERE {$expenseWhere} GROUP BY DATE_FORMAT(expense_date,'%Y-%m') ORDER BY label",
    $expenseParams
);
$topDept = $fetchSeries(
    "SELECT COALESCE(d.name,'Unassigned') name,COUNT(a.id) total FROM assets a LEFT JOIN departments d ON d.id=a.department_id WHERE a.status<>'Disposed' GROUP BY d.id,d.name ORDER BY total DESC LIMIT 1"
);
$topCats = $fetchSeries(
    "SELECT COALESCE(c.name,'Uncategorized') name,COUNT(a.id) total FROM assets a LEFT JOIN categories c ON c.id=a.category_id GROUP BY c.id,c.name ORDER BY total DESC LIMIT 5"
);
$recentAssets = $fetchSeries(
    "SELECT a.id,a.asset_code,a.name,a.purchase_date,a.status,COALESCE(c.name,'—') category FROM assets a LEFT JOIN categories c ON c.id=a.category_id WHERE {$assetWhere} ORDER BY a.created_at DESC LIMIT 6",
    $assetParams
);
$recentAssignments = $fetchSeries(
    "SELECT a.id,a.assignment_date,a.assigned_to,COUNT(ai.id) assets_count FROM assignments a LEFT JOIN assignment_items ai ON ai.assignment_id=a.id GROUP BY a.id ORDER BY a.created_at DESC LIMIT 5"
);
$recentReturns = $fetchSeries("SELECT r.return_date,r.holder_name,a.asset_code,a.name FROM returns r JOIN assets a ON a.id=r.asset_id ORDER BY r.created_at DESC LIMIT 5");
$activityType = $_GET['activity_type'] ?? 'all';
$activityAsset = (int) ($_GET['activity_asset'] ?? 0);
$activitySql = "SELECT al.*,a.asset_code,a.name FROM activity_log al LEFT JOIN assets a ON a.id=al.asset_id";
$activityParams = [];
$activityConditions = [];
if (in_array($activityType, ['Purchase', 'Assignment', 'Return', 'Transfer', 'Maintenance', 'Disposal'], true)) {
    $activityConditions[] = 'al.activity_type=?';
    $activityParams[] = $activityType;
}if ($activityAsset > 0) {
    $activityConditions[] = 'al.asset_id=?';
    $activityParams[] = $activityAsset;
}if ($activityConditions)
    $activitySql .= ' WHERE ' . implode(' AND ', $activityConditions);
$activitySql .= ' ORDER BY al.activity_date DESC LIMIT 8';
$activityRows = $fetchSeries($activitySql, $activityParams);
$activityAssetOptions = $pdo->query('SELECT id,asset_code,name FROM assets ORDER BY asset_code')->fetchAll();
$warrantyRows = $fetchSeries(
    "SELECT asset_code,name,warranty_expiry FROM assets WHERE warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND status<>'Disposed' ORDER BY warranty_expiry LIMIT 6"
);
$maintenanceRows = $fetchSeries(
    "SELECT a.asset_code,a.name,m.maintenance_type,m.status,m.next_service_date FROM maintenance m JOIN assets a ON a.id=m.asset_id WHERE m.status='In Progress' OR (m.next_service_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND m.id=(SELECT MAX(m2.id) FROM maintenance m2 WHERE m2.asset_id=m.asset_id)) ORDER BY m.status DESC,m.next_service_date LIMIT 8"
);
$chartData = [
    'status' => $statusRows,
    'category' => $categoryRows,
    'department' => $deptRows,
    'value' => $valueRows,
    'condition' => $conditionRows,
    'location' => $locationRows,
    'expenses' => $expenseRows,
    'depreciation' => [
        ['label' => 'Current Value', 'total' => round($depCurrent, 2)],
        ['label' => 'Accumulated Depreciation', 'total' => round($depAccum, 2)]
    ],
    'utilization' => $utilPct
];
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="main-area">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Dashboard</h2>
                <div class="topbar-subtitle">Overview of your company's assets and operations</div>
            </div>
        </div>
        <div class="topbar-user">
            <div class="avatar">A</div>
            <div class="d-none d-sm-block"><strong class="d-block small"><?= e($_SESSION['admin_username'] ?? 'Admin') ?></strong><span class="small-muted">Administrator</span></div>
        </div>
    </header>
    <div class="content-wrap">

        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <div class="page-heading">
            <div>
                <h1>Good day, <?= e($_SESSION['admin_username'] ?? 'Admin') ?>!</h1>
                <p>Here is what's happening with your assets today.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary" href="<?= BASE_URL ?>assets_pages/entry.php"><i class="fa-solid fa-plus me-1"></i> Add Asset</a>
                <a class="btn btn-outline-primary" href="<?= BASE_URL ?>assets_pages/assignment.php">Assign Asset</a>
                <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>assets_pages/return.php">Return</a>
                <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>assets_pages/transfer.php">Transfer</a>
                <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>assets_pages/maintenance.php">Maintenance</a>
                <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>assets_pages/disposal.php">Disposal</a>
            </div>
        </div>
        <form class="filter-panel row g-2 align-items-end no-print" method="get">
            <div class="col-sm-4 col-md-3">
                <label class="form-label">Dashboard date range</label>
                <select class="form-select" name="range" id="rangeSelect">
                    <option value="today" <?= selected($preset, 'today') ?>>Today</option>
                    <option value="weekly" <?= selected($preset, 'weekly') ?>>Weekly</option>
                    <option value="monthly" <?= selected($preset, 'monthly') ?>>Monthly</option>
                    <option value="quarterly" <?= selected($preset, 'quarterly') ?>>Quarterly</option>
                    <option value="financial_year" <?= selected($preset, 'financial_year') ?>>Financial Year (Apr–Mar)</option>
                    <option value="last_financial_year" <?= selected($preset, 'last_financial_year') ?>>Last Financial Year</option>
                    <option value="all" <?= selected($preset, 'all') ?>>All Time</option>
                    <option value="custom" <?= selected($preset, 'custom') ?>>Custom Date Range</option>
                </select>
            </div>
            <div class="col-sm-3 col-md-2 custom-range">
                <label class="form-label">From</label>
                <input type="date" class="form-control" name="from" value="<?= e($from) ?>">
            </div>
            <div class="col-sm-3 col-md-2 custom-range">
                <label class="form-label">To</label>
                <input type="date" class="form-control" name="to" value="<?= e($to) ?>">
            </div>
            <div class="col-auto"><button class="btn btn-primary">Apply Filter</button><a class="btn btn-light" href="dashboard.php">Reset</a></div>
            <div class="col text-end small-muted">Asset cards and asset charts use purchase date.</div>
        </form>
        <div class="row g-3 mb-4">
            <?php foreach ($cards as [$label,$value,$icon,$color]): ?>
                <div class="col-6 col-xl">
                    <div class="card summary-card summary-<?= $color ?>">
                        <div class="summary-label"><?= e($label) ?></div>
                        <div class="summary-value"><?= number_format((int) $value) ?></div>
                        <div class="summary-note"><?= $label === 'Upcoming Maintenance' ? 'Due within 30 days' : 'Current records in selected period' ?></div>
                        <div class="summary-icon"><i class="fa-solid <?= e($icon) ?>"></i></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Asset Status Overview</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrap">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Assets by Category</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrap">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Assets by Department</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrap">
                            <canvas id="departmentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Asset Value by Category</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrap">
                            <canvas id="valueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Depreciation Overview</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrap">
                            <canvas id="depreciationChart"></canvas>
                        </div>
                        <div class="small-muted mt-2">Annual depreciation estimate: <strong><?= money($depAnnual) ?></strong></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Expenses Overview</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrap">
                            <canvas id="expensesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Asset Utilization</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrap">
                            <canvas id="utilizationChart"></canvas>
                        </div>
                        <div class="text-center fw-bold fs-4 mt-1"><?= number_format($utilPct, 1) ?>%</div>
                        <div class="small-muted text-center">Assets currently assigned</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Asset Condition Breakdown</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrap">
                            <canvas id="conditionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Assets by Location</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrap">
                            <canvas id="locationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Department with Most Assets</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($topDept): ?>
                            <div class="h5 fw-bold mb-1"><?= e($topDept[0]['name']) ?></div>
                            <div class="small-muted"><?= number_format((int) $topDept[0]['total']) ?> registered assets</div>
                        <?php else: ?>
                            <div class="empty-state">No asset data yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Most-used Asset Categories</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Asset Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topCats as $r): ?>
                                    <tr>
                                        <td><?= e($r['name']) ?></td>
                                        <td class="text-end fw-semibold"><?= number_format((int) $r['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$topCats): ?>
                                    <tr>
                                        <td colspan="2" class="empty-state">No categories yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">
                        <h3 class="card-title-sm">Recent Assets</h3>
                        <a class="small" href="<?= BASE_URL ?>assets_pages/index.php">View all</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAssets as $r): ?>
                                    <tr>
                                        <td><strong><?= e($r['asset_code']) ?></strong>
                                            <div><?= e($r['name']) ?></div>
                                        </td>
                                        <td><?= e($r['category']) ?></td>
                                        <td><?= statusBadge($r['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$recentAssets): ?>
                                    <tr>
                                        <td colspan="3" class="empty-state">No assets in this date range.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Recent Assignments</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Holder</th>
                                    <th>Assets</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAssignments as $r): ?>
                                    <tr>
                                        <td><?= e($r['assignment_date']) ?></td>
                                        <td><?= e($r['assigned_to']) ?></td>
                                        <td><?= number_format((int) $r['assets_count']) ?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$recentAssignments): ?>
                                    <tr>
                                        <td colspan="3" class="empty-state">No assignments recorded.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Recent Returns</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Holder</th>
                                    <th>Asset</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentReturns as $r): ?>
                                    <tr>
                                        <td><?= e($r['return_date']) ?></td>
                                        <td><?= e($r['holder_name']) ?></td>
                                        <td><?= e($r['asset_code']) ?> · <?= e($r['name']) ?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$recentReturns): ?>
                                    <tr>
                                        <td colspan="3" class="empty-state">No returns recorded.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
                        <h3 class="card-title-sm">Recent Activity Log</h3>
                        <form method="get" class="no-print d-flex gap-1">
                            <input type="hidden" name="range" value="<?= e($preset) ?>">
                            <input type="hidden" name="from" value="<?= e($from) ?>">
                            <input type="hidden" name="to" value="<?= e($to) ?>">
                            <select class="form-select form-select-sm" name="activity_type" data-auto-submit>
                                <option value="all">All activity types</option>
                                <?php foreach (['Purchase', 'Assignment', 'Return', 'Transfer', 'Maintenance', 'Disposal'] as $t): ?>
                                    <option value="<?= $t ?>" <?= selected($activityType, $t) ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select form-select-sm" name="activity_asset" data-auto-submit>
                                <option value="0">All assets</option>
                                <?php foreach ($activityAssetOptions as $ao): ?>
                                    <option value="<?= $ao['id'] ?>" <?= selected($activityAsset, $ao['id']) ?>><?= e($ao['asset_code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($activityRows as $r): ?>
                                <div class="timeline-item">
                                    <div class="timeline-date"><?= e($r['activity_date']) ?></div>
                                    <div class="timeline-title"><?= e($r['activity_type']) ?> · <?= e($r['asset_code'] ?? 'Asset removed') ?></div>
                                    <div class="timeline-desc"><?= e($r['description']) ?></div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (!$activityRows): ?>
                                <div class="empty-state">No activity found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Warranty Expiring Soon</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Expiry Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($warrantyRows as $r): ?>
                                    <tr>
                                        <td><?= e($r['asset_code']) ?> · <?= e($r['name']) ?></td>
                                        <td><?= e($r['warranty_expiry']) ?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$warrantyRows): ?>
                                    <tr>
                                        <td colspan="2" class="empty-state">No warranties expiring within 30 days.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Maintenance Alerts</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Status</th>
                                    <th>Next Service</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maintenanceRows as $r): ?>
                                    <tr>
                                        <td><?= e($r['asset_code']) ?> · <?= e($r['name']) ?></td>
                                        <td><?= statusBadge($r['status']) ?></td>
                                        <td><?= e($r['next_service_date'] ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$maintenanceRows): ?>
                                    <tr>
                                        <td colspan="3" class="empty-state">No maintenance alerts.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
    const D = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const labels = rows => rows.map(x => x.label);
    const values = rows => rows.map(x => Number(x.total));
    const common = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 10,
                    font: {
                        size: 10
                    }
                }
            }
        }
    };

    function chart(id, type, rows, opts = {}) {
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type,
            data: {
                labels: labels(rows),
                datasets: [{
                    label: opts.label || 'Assets',
                    data: values(rows),
                    backgroundColor: opts.colors || ['#4777e7', '#23a477', '#f0a23b', '#8b68cf', '#e26b7a', '#58a9bf', '#8b9bad'],
                    borderWidth: 1,
                    borderRadius: type === 'bar' ? 5 : 0
                }]
            },
            options: {
                ...common,
                ...opts.options
            }
        })
    }
    chart('statusChart', 'doughnut', D.status);
    chart('categoryChart', 'bar', D.category, {
        options: {
            ...common,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    chart('departmentChart', 'bar', D.department, {
        options: {
            ...common,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    chart('valueChart', 'bar', D.value, {
        label: 'Purchase value',
        options: {
            ...common,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => '₱' + Number(v).toLocaleString()
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: c => '₱' + Number(c.raw).toLocaleString()
                    }
                }
            }
        }
    });
    chart('depreciationChart', 'doughnut', D.depreciation);
    chart('expensesChart', 'line', D.expenses, {
        options: {
            ...common,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => '₱' + Number(v).toLocaleString()
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    chart('utilizationChart', 'doughnut', [{
        label: 'Assigned',
        total: D.utilization
    }, {
        label: 'Other active',
        total: 100 - D.utilization
    }], {
        colors: ['#4777e7', '#e8edf5'],
        options: {
            ...common,
            cutout: '72%',
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    chart('conditionChart', 'pie', D.condition);
    chart('locationChart', 'bar', D.location, {
        options: {
            ...common,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    document.querySelectorAll('.custom-range').forEach(el => el.style.display = document.getElementById('rangeSelect').value === 'custom' ? 'block' : 'none');
    document.getElementById('rangeSelect').addEventListener('change', e => document.querySelectorAll('.custom-range').forEach(el => el.style.display = e.target.value === 'custom' ? 'block' : 'none'));
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
