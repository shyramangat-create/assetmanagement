<?php
require_once __DIR__ . '/../includes/auth.php';
$reports = [
    [
        'Assets & History',
        'assets.php',
        'fa-boxes-stacked',
        'Asset register and per-asset activity timeline.'
    ],
    [
        'Depreciation',
        'depreciation.php',
        'fa-chart-line',
        'Current value, annual depreciation, and accumulated depreciation.'
    ],
    ['Assignments', 'assignments.php', 'fa-user-check', 'Asset assignment records and holders.'],
    ['Returns', 'returns.php', 'fa-rotate-left', 'Returned asset records and condition.'],
    ['Transfers', 'transfers.php', 'fa-arrow-right-arrow-left', 'Asset holder and location transfers.'],
    [
        'Maintenance',
        'maintenance.php',
        'fa-screwdriver-wrench',
        'Maintenance costs, dates, and service schedules.'
    ],
    ['Vendors', 'vendors.php', 'fa-truck-field', 'Vendor directory and contact details.'],
    ['Expenses', 'expenses.php', 'fa-receipt', 'Asset expenses and cost breakdown.']
];
$pageTitle = 'Reports';
$activePage = 'reports';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-area">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Reports</h2>
                <div class="topbar-subtitle">Review, filter, and print system reports</div>
            </div>
        </div>
    </header>
    <div class="content-wrap">
        <div class="page-heading">
            <div>
                <h1>Reports Center</h1>
                <p>Select a report to view records, apply filters, and print a clean copy.</p>
            </div>
        </div>
        <div class="row g-3">
            <?php foreach ($reports as [$name,$file,$icon,$desc]): ?>
                <div class="col-sm-6 col-xl-3">
                    <a href="<?= $file ?>" class="card text-decoration-none h-100">
                        <div class="card-body">
                            <div class="summary-icon position-static mb-3"><i class="fa-solid <?= $icon ?>"></i></div>
                            <h3 class="h6 fw-bold text-dark"><?= $name ?></h3>
                            <p class="small-muted mb-0"><?= $desc ?></p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
