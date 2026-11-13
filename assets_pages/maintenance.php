<?php
require_once __DIR__ . '/../includes/auth.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        $action = $_POST['action'] ?? 'save';
        if ($action === 'complete') {
            $id = postInt('maintenance_id', 1);
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT m.*,a.asset_code,a.status asset_status FROM maintenance m JOIN assets a ON a.id=m.asset_id WHERE m.id=? FOR UPDATE");
            $st->execute([$id]);
            $m = $st->fetch();
            if (!$m || $m['status'] !== 'In Progress')
                throw new RuntimeException('Maintenance record is not in progress.');
            $pdo->prepare("UPDATE maintenance SET status='Completed' WHERE id=?")->execute([$id]);
            if ($m['asset_status'] === 'Under Maintenance') {
                $restore = $m['previous_asset_status'] ?: 'Available';
                $pdo->prepare('UPDATE assets SET status=?,maintenance_previous_status=NULL WHERE id=?')->execute([$restore, $m['asset_id']]);
            }logActivity($pdo, (int) $m['asset_id'], 'Maintenance', "Maintenance #{$id} completed for {$m['asset_code']}. Asset status restored.");
            $pdo->commit();
            flash('success', 'Maintenance marked as completed and asset status restored.');
            redirect('assets_pages/maintenance.php');
        }
        $assetId = postInt('asset_id', 1);
        $date = postString('maintenance_date', 10, true);
        if (!validDate($date))
            throw new InvalidArgumentException('Enter a valid maintenance date.');
        $type = postString('maintenance_type', 30, true);
        if (!in_array($type, ['Upgrade', 'Inspection', 'Corrective'], true))
            throw new InvalidArgumentException('Invalid maintenance type.');
        $cost = postMoney('cost');
        $next = postString('next_service_date', 10);
        if ($next !== '' && !validDate($next))
            throw new InvalidArgumentException('Enter a valid next service date.');
        $status = postString('status', 20);
        if (!in_array($status, ['In Progress', 'Completed'], true))
            throw new InvalidArgumentException('Invalid maintenance status.');
        $vendor = (int) ($_POST['vendor_id'] ?? 0) ?: null;
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM assets WHERE id=? FOR UPDATE');
        $st->execute([$assetId]);
        $a = $st->fetch();
        if (!$a || !in_array($a['status'], ['Available', 'Assigned'], true))
            throw new RuntimeException('Choose an available or assigned asset that is not disposed or already under maintenance.');
        $previous = $a['status'];
        $st = $pdo->prepare(
            'INSERT INTO maintenance(asset_id,vendor_id,maintenance_date,maintenance_type,cost,next_service_date,status,previous_asset_status,notes) VALUES(?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $assetId,
            $vendor,
            $date,
            $type,
            $cost,
            $next ?: null,
            $status,
            $previous,
            postString('notes', 3000)
        ]);
        $mid = (int) $pdo->lastInsertId();
        if ($status === 'In Progress')
            $pdo->prepare("UPDATE assets SET status='Under Maintenance',maintenance_previous_status=? WHERE id=?")->execute([$previous, $assetId]);
        logActivity($pdo, $assetId, 'Maintenance', "{$a['asset_code']} maintenance ({$type}) recorded on {$date}; status: {$status}.");
        $pdo->commit();
        flash('success', 'Maintenance record saved.');
        redirect('assets_pages/maintenance.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $error = $e instanceof PDOException ? 'Could not save maintenance record.' : $e->getMessage();
    }
}
$assets = $pdo->query("SELECT id,asset_code,name,status FROM assets WHERE status IN ('Available','Assigned') ORDER BY name")->fetchAll();
$vendors = fetchOptions($pdo, 'vendors');
$records = $pdo->query(
    'SELECT m.*,a.asset_code,a.name,v.name vendor FROM maintenance m JOIN assets a ON a.id=m.asset_id LEFT JOIN vendors v ON v.id=m.vendor_id ORDER BY m.created_at DESC LIMIT 100'
)->fetchAll();
$pageTitle = 'Maintenance';
$activePage = 'assets-maintenance';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-area">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Maintenance</h2>
                <div class="topbar-subtitle">Schedule and track asset servicing</div>
            </div>
        </div>
    </header>
    <div class="content-wrap">
        <div class="page-heading">
            <div>
                <h1>Maintenance Records</h1>
                <p>Maintenance can be recorded for Available or Assigned assets.</p>
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
                <h3 class="card-title-sm">Add Maintenance Record</h3>
            </div>
            <div class="card-body"><?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required">Asset</label>
                        <select name="asset_id" class="form-select" required>
                            <option value="">Select active asset</option>
                            <?php foreach ($assets as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= e($a['asset_code'] . ' · ' . $a['name'] . ' (' . $a['status'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Maintenance Date</label>
                        <input type="date" name="maintenance_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Maintenance Type</label>
                        <select name="maintenance_type" class="form-select">
                            <option>Inspection</option>
                            <option>Upgrade</option>
                            <option>Corrective</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Vendor</label>
                        <select name="vendor_id" class="form-select">
                            <option value="">Select vendor</option>
                            <?php foreach ($vendors as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= e($v['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cost (₱)</label>
                        <input type="number" min="0" step="0.01" name="cost" class="form-control" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Next Service Date</label>
                        <input type="date" name="next_service_date" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option>In Progress</option>
                            <option>Completed</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button class="btn btn-primary" <?= !$assets ? 'disabled' : '' ?>>Save Maintenance</button>
                </div>
            </div>
        </form>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title-sm">Maintenance History</h3>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Asset</th>
                            <th>Type</th>
                            <th>Vendor</th>
                            <th>Cost</th>
                            <th>Next Service</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td><?= e($r['maintenance_date']) ?></td>
                                <td><strong><?= e($r['asset_code']) ?></strong>
                                    <div><?= e($r['name']) ?></div>
                                </td>
                                <td><?= e($r['maintenance_type']) ?></td>
                                <td><?= e($r['vendor'] ?? '—') ?></td>
                                <td><?= money($r['cost']) ?></td>
                                <td><?= e($r['next_service_date'] ?? '—') ?></td>
                                <td><?= statusBadge($r['status']) ?></td>
                                <td>
                                    <?php if ($r['status'] === 'In Progress'): ?>
                                        <form method="post" onsubmit="return confirm('Mark this maintenance as completed?')"><?= csrfField() ?>
                                            <input type="hidden" name="action" value="complete">
                                            <input type="hidden" name="maintenance_id" value="<?= $r['id'] ?>">
                                            <button class="btn btn-sm btn-success">Complete</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="small-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$records): ?>
                            <tr>
                                <td colspan="8" class="empty-state">No maintenance records yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
