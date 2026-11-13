<?php
require_once __DIR__ . '/../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        verifyCsrf();
        $id = postInt('id', 1);
        $st = $pdo->prepare('SELECT asset_code,photo FROM assets WHERE id=?');
        $st->execute([$id]);
        $a = $st->fetch();
        if (!$a)
            throw new RuntimeException('Asset not found.');
        $check = $pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM assignment_items WHERE asset_id=?)+(SELECT COUNT(*) FROM returns WHERE asset_id=?)+(SELECT COUNT(*) FROM transfers WHERE asset_id=?)+(SELECT COUNT(*) FROM maintenance WHERE asset_id=?)+(SELECT COUNT(*) FROM disposals WHERE asset_id=?)+(SELECT COUNT(*) FROM expenses WHERE asset_id=?)+(SELECT COUNT(*) FROM activity_log WHERE asset_id=?)'
        );
        $check->execute(array_fill(0, 7, $id));
        if ((int) $check->fetchColumn() > 0)
            throw new RuntimeException('This asset has transaction history and cannot be deleted. Use Disposal to deactivate it.');
        $pdo->prepare('DELETE FROM assets WHERE id=?')->execute([$id]);
        if ($a['photo'] && is_file(__DIR__ . '/../assets/uploads/' . basename($a['photo'])))
            @unlink(__DIR__ . '/../assets/uploads/' . basename($a['photo']));
        flash('success', 'Asset deleted.');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }redirect('assets_pages/index.php');
}
$q = trim((string) ($_GET['q'] ?? ''));
$status = $_GET['status'] ?? 'all';
$allowed = ['all', 'Available', 'Assigned', 'Under Maintenance', 'Disposed'];
if (!in_array($status, $allowed, true))
    $status = 'all';
$per = (int) ($_GET['per'] ?? 10);
if (!in_array($per, [10, 20, 25, 50], true))
    $per = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$where = '1=1';
$params = [];
if ($status !== 'all') {
    $where .= ' AND a.status=?';
    $params[] = $status;
}if ($q !== '') {
    $where .= ' AND (a.asset_code LIKE ? OR a.name LIKE ? OR a.serial_number LIKE ? OR a.current_holder LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$st = $pdo->prepare("SELECT COUNT(*) FROM assets a WHERE {$where}");
$st->execute($params);
$total = (int) $st->fetchColumn();
$pages = max(1, (int) ceil($total / $per));
$page = min($page, $pages);
$offset = ($page - 1) * $per;
$sql = "SELECT a.*,c.name category,l.name location,d.name department FROM assets a LEFT JOIN categories c ON c.id=a.category_id LEFT JOIN locations l ON l.id=a.location_id LEFT JOIN departments d ON d.id=a.department_id WHERE {$where} ORDER BY a.created_at DESC LIMIT {$per} OFFSET {$offset}";
$st = $pdo->prepare($sql);
$st->execute($params);
$assets = $st->fetchAll();
$pageTitle = 'Asset List';
$activePage = 'assets-list';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-area">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Assets</h2>
                <div class="topbar-subtitle">Browse and manage registered assets</div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-primary" href="<?= BASE_URL ?>assets_pages/entry.php"><i class="fa-solid fa-plus me-1"></i> Add Asset</a>
        </div>
    </header>
    <div class="content-wrap">

        <?php if ($s = flash('success')): ?>
            <div class="alert alert-success"><?= e($s) ?></div>
        <?php endif; ?>

        <?php if ($er = flash('error')): ?>
            <div class="alert alert-danger"><?= e($er) ?></div>
        <?php endif; ?>
        <div class="page-heading">
            <div>
                <h1>Asset Inventory</h1>
                <p><?= number_format($total) ?> asset record(s) found.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= BASE_URL ?>assets_pages/import.php"><i class="fa-solid fa-file-import me-1"></i> Import CSV</a>
        </div>
        <form class="filter-panel row g-2 align-items-end" method="get">
            <div class="col-md-5">
                <label class="form-label">Search assets</label>
                <input class="form-control" name="q" placeholder="Asset ID, name, serial number, or holder" value="<?= e($q) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="all">All statuses</option>
                    <?php foreach (array_slice($allowed, 1) as $s): ?>
                        <option value="<?= e($s) ?>" <?= selected($status, $s) ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Rows per page</label>
                <select class="form-select" name="per">
                    <?php foreach ([10, 20, 25, 50] as $n): ?>
                        <option value="<?= $n ?>" <?= selected($per, $n) ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1">Filter</button>
                <a class="btn btn-light" href="index.php">Reset</a>
            </div>
        </form>
        <div class="row g-3">
            <?php foreach ($assets as $a): ?>
                <div class="col-sm-6 col-xl-4 col-xxl-3">
                    <div class="card asset-card">
                        <div class="asset-thumb">
                            <?php if ($a['photo']): ?>
                                <img src="<?= BASE_URL ?>assets/uploads/<?= e(rawurlencode($a['photo'])) ?>" alt="Asset photo">
                            <?php else: ?>
                                <i class="fa-solid fa-box-open"></i>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center gap-2"><span class="asset-code"><?= e($a['asset_code']) ?></span><?= statusBadge($a['status']) ?></div>
                            <div class="asset-name"><?= e($a['name']) ?></div>
                            <div class="asset-meta">
                                <div><i class="fa-solid fa-layer-group me-1"></i><?= e($a['category'] ?? 'Uncategorized') ?></div>
                                <div><i class="fa-solid fa-location-dot me-1"></i><?= e($a['location'] ?? 'No location') ?></div>
                                <div><i class="fa-solid fa-building me-1"></i><?= e($a['department'] ?? 'No department') ?></div>
                                <?php if ($a['status'] === 'Assigned'): ?>
                                    <div class="text-primary fw-semibold"><i class="fa-solid fa-user me-1"></i><?= e($a['current_holder']) ?><?= $a['holder_department_id'] ? ' · Assigned' : '' ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3"><strong><?= money($a['purchase_cost']) ?></strong><span class="small-muted">Purchased <?= e($a['purchase_date']) ?></span></div>
                            <div class="d-flex flex-wrap gap-1 mt-3">
                                <a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?= $a['id'] ?>">Edit</a>
                                <a class="btn btn-sm btn-outline-secondary" href="history.php?id=<?= $a['id'] ?>">History</a>
                                <?php if ($a['status'] !== 'Disposed'): ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this asset only if it has no transaction history?');"><?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$assets): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="empty-state"><i class="fa-solid fa-box-open d-block fs-3 mb-2"></i>No assets found. <a href="entry.php">Register your first asset</a>.</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination pagination-sm justify-content-center">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(['q' => $q, 'status' => $status, 'per' => $per, 'page' => $i]) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
