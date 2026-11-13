<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">AM</div>
        <div><strong>Asset Manager</strong><small>ADMIN PORTAL</small></div>
        <button class="btn btn-sm text-white d-lg-none ms-auto" id="closeSidebar" aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <nav class="side-nav">
        <div class="nav-caption">WORKSPACE</div>
        <a class="side-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>dashboard.php"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></a>
        <div class="nav-caption mt-3">ASSET MANAGEMENT</div>
        <button class="side-link side-toggle <?= str_starts_with($activePage, 'assets') ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#assetMenu" aria-expanded="<?= str_starts_with($activePage, 'assets') ? 'true' : 'false' ?>"><i class="fa-solid fa-boxes-stacked"></i><span>Assets</span><i class="fa-solid fa-chevron-down ms-auto toggle-chevron"></i></button>
        <div class="collapse <?= str_starts_with($activePage, 'assets') ? 'show' : '' ?>" id="assetMenu">
            <a class="side-sublink <?= $activePage === 'assets-list' ? 'active' : '' ?>" href="<?= BASE_URL ?>assets_pages/index.php">Asset List</a>
            <a class="side-sublink <?= $activePage === 'assets-entry' ? 'active' : '' ?>" href="<?= BASE_URL ?>assets_pages/entry.php">Asset Entry</a>
            <a class="side-sublink <?= $activePage === 'assets-assignment' ? 'active' : '' ?>" href="<?= BASE_URL ?>assets_pages/assignment.php">Assignment</a>
            <a class="side-sublink <?= $activePage === 'assets-return' ? 'active' : '' ?>" href="<?= BASE_URL ?>assets_pages/return.php">Return</a>
            <a class="side-sublink <?= $activePage === 'assets-transfer' ? 'active' : '' ?>" href="<?= BASE_URL ?>assets_pages/transfer.php">Transfer</a>
            <a class="side-sublink <?= $activePage === 'assets-maintenance' ? 'active' : '' ?>" href="<?= BASE_URL ?>assets_pages/maintenance.php">Maintenance</a>
            <a class="side-sublink <?= $activePage === 'assets-disposal' ? 'active' : '' ?>" href="<?= BASE_URL ?>assets_pages/disposal.php">Disposal</a>
            <a class="side-sublink <?= $activePage === 'assets-import' ? 'active' : '' ?>" href="<?= BASE_URL ?>assets_pages/import.php">Import CSV</a>
        </div>
        <a class="side-link <?= $activePage === 'vendors' ? 'active' : '' ?>" href="<?= BASE_URL ?>vendors/index.php"><i class="fa-solid fa-truck-field"></i><span>Vendors</span></a>
        <a class="side-link <?= $activePage === 'expenses' ? 'active' : '' ?>" href="<?= BASE_URL ?>expenses/index.php"><i class="fa-solid fa-receipt"></i><span>Expenses</span></a>
        <a class="side-link <?= $activePage === 'reports' ? 'active' : '' ?>" href="<?= BASE_URL ?>reports/index.php"><i class="fa-solid fa-file-lines"></i><span>Reports</span></a>
        <div class="nav-caption mt-3">ACCOUNT</div>
        <a class="side-link logout-link" href="<?= BASE_URL ?>logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
    </nav>
    <div class="sidebar-bottom">
        <div class="admin-avatar">A</div>
        <div class="min-w-0"><strong><?= e($_SESSION['admin_username'] ?? 'Admin') ?></strong><small>Administrator</small></div>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
