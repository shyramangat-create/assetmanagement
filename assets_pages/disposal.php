<?php
require_once __DIR__ . '/../includes/auth.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        $assetId = postInt('asset_id', 1);
        $date = postString('disposal_date', 10, true);
        if (!validDate($date))
            throw new InvalidArgumentException('Enter a valid disposal date.');
        $reason = postString('reason', 3000, true);
        $value = postMoney('disposal_value');
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM assets WHERE id=? FOR UPDATE');
        $st->execute([$assetId]);
        $a = $st->fetch();
        if (!$a || $a['status'] === 'Disposed')
            throw new RuntimeException('Asset is not available for disposal.');
        $pending = $pdo->prepare("SELECT COUNT(*) FROM maintenance WHERE asset_id=? AND status='In Progress'");
        $pending->execute([$assetId]);
        if ((int) $pending->fetchColumn() > 0)
            throw new RuntimeException('Complete the in-progress maintenance before disposing of this asset.');
        $st = $pdo->prepare('INSERT INTO disposals(asset_id,disposal_date,reason,disposal_value,disposed_by) VALUES(?,?,?,?,\'Admin\')');
        $st->execute([$assetId, $date, $reason, $value]);
        $pdo->prepare(
            "UPDATE assets SET status='Disposed',current_holder=NULL,holder_mobile=NULL,holder_department_id=NULL,maintenance_previous_status=NULL WHERE id=?"
        )->execute([$assetId]);
        logActivity(
            $pdo,
            $assetId,
            'Disposal',
            "{$a['asset_code']} disposed on {$date}. Reason: {$reason}; disposal value: " . number_format($value, 2) . '.'
        );
        $pdo->commit();
        flash('success', 'Asset disposed successfully.');
        redirect('assets_pages/disposal.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $error = $e instanceof PDOException ? 'Could not save disposal record.' : $e->getMessage();
    }
}
$assets = $pdo->query("SELECT id,asset_code,name,status FROM assets WHERE status<>'Disposed' ORDER BY name")->fetchAll();
$records = $pdo->query('SELECT d.*,a.asset_code,a.name FROM disposals d JOIN assets a ON a.id=d.asset_id ORDER BY d.disposal_date DESC')->fetchAll();
$pageTitle = 'Disposal';
$activePage = 'assets-disposal';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-area">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Asset Disposal</h2>
                <div class="topbar-subtitle">Deactivate assets that are no longer in service</div>
            </div>
        </div>
    </header>
    <div class="content-wrap">
        <div class="page-heading">
            <div>
                <h1>Dispose Asset</h1>
                <p>Disposed assets are excluded from assignment, return, transfer, and maintenance.</p>
            </div>
        </div>
        <?php if ($s = flash('success')): ?>
            <div class="alert alert-success"><?= e($s) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" class="card mb-4">
            <div class="card-header">
                <h3 class="card-title-sm">New Disposal Record</h3>
            </div>
            <div class="card-body"><?= csrfField() ?><div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required">Asset</label>
                        <select name="asset_id" class="form-select" required>
                            <option value="">Select asset</option>
                            <?php foreach ($assets as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= e($a['asset_code'] . ' · ' . $a['name'] . ' (' . $a['status'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Disposal Date</label>
                        <input type="date" name="disposal_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Disposal Value (₱)</label>
                        <input type="number" min="0" step="0.01" name="disposal_value" class="form-control" value="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label required">Reason</label><textarea name="reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button class="btn btn-danger" <?= !$assets ? 'disabled' : '' ?> data-confirm="Dispose this asset? This action will deactivate it.">Confirm Disposal</button>
                </div>
            </div>
        </form>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title-sm">Disposal History</h3>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Asset</th>
                            <th>Reason</th>
                            <th>Value</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td><?= e($r['disposal_date']) ?></td>
                                <td><strong><?= e($r['asset_code']) ?></strong>
                                    <div><?= e($r['name']) ?></div>
                                </td>
                                <td><?= e($r['reason']) ?></td>
                                <td><?= money($r['disposal_value']) ?></td>
                                <td><?= e($r['disposed_by']) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$records): ?>
                            <tr>
                                <td colspan="5" class="empty-state">No disposed assets.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
