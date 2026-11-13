<?php
require_once __DIR__ . '/../includes/auth.php';
$id = getInt('id');
$st = $pdo->prepare('SELECT * FROM assets WHERE id=?');
$st->execute([$id]);
$asset = $st->fetch();
if (!$asset) {
    http_response_code(404);
    exit('Asset not found.');
}$st = $pdo->prepare('SELECT * FROM activity_log WHERE asset_id=? ORDER BY activity_date DESC,id DESC');
$st->execute([$id]);
$history = $st->fetchAll();
$pageTitle = 'Asset History';
$activePage = 'assets-list';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-area">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Asset History</h2>
                <div class="topbar-subtitle">Complete activity timeline</div>
            </div>
        </div>
    </header>
    <div class="content-wrap">
        <div class="page-heading">
            <div>
                <h1><?= e($asset['asset_code']) ?> · <?= e($asset['name']) ?></h1>
                <p>Purchase, assignment, return, transfer, maintenance, and disposal records.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary">Back to Assets</a>
        </div>
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <div class="asset-thumb rounded mb-3">
                            <?php if ($asset['photo']): ?>
                                <img src="<?= BASE_URL ?>assets/uploads/<?= e(rawurlencode($asset['photo'])) ?>" alt="Asset photo">
                            <?php else: ?>
                                <i class="fa-solid fa-box-open"></i>
                            <?php endif; ?>
                        </div>
                        <div class="asset-code"><?= e($asset['asset_code']) ?></div>
                        <h5 class="fw-bold mt-1"><?= e($asset['name']) ?></h5>
                        <div class="mb-2"><?= statusBadge($asset['status']) ?></div>
                        <div class="asset-meta">Purchase date: <?= e($asset['purchase_date']) ?><br>Purchase cost: <?= money($asset['purchase_cost']) ?><br>Serial number: <?= e($asset['serial_number'] ?: '—') ?><br>Current holder: <?= e($asset['current_holder'] ?: '—') ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title-sm">Activity Timeline</h3>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($history as $h): ?>
                                <div class="timeline-item">
                                    <div class="timeline-date"><?= e($h['activity_date']) ?></div>
                                    <div class="timeline-title"><?= e($h['activity_type']) ?></div>
                                    <div class="timeline-desc"><?= e($h['description']) ?></div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (!$history): ?>
                                <div class="empty-state">No activity recorded.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
