<?php
require_once __DIR__ . '/../includes/auth.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        $assetId = postInt('asset_id', 1);
        $date = postString('transfer_date', 10, true);
        if (!validDate($date))
            throw new InvalidArgumentException('Enter a valid transfer date.');
        $newHolder = postString('new_holder', 150, true);
        $dept = (int) ($_POST['new_department_id'] ?? 0) ?: null;
        $loc = (int) ($_POST['new_location_id'] ?? 0) ?: null;
        $reason = postString('reason', 3000, true);
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM assets WHERE id=? FOR UPDATE');
        $st->execute([$assetId]);
        $a = $st->fetch();
        if (!$a || $a['status'] !== 'Assigned')
            throw new RuntimeException('Only currently assigned assets can be transferred.');
        $old = $a['current_holder'];
        if ($old === $newHolder)
            throw new InvalidArgumentException('New holder must be different from the current holder.');
        $st = $pdo->prepare(
            'INSERT INTO transfers(asset_id,old_holder,new_holder,new_department_id,new_location_id,transfer_date,reason,transferred_by) VALUES(?,?,?,?,?,?,?,\'Admin\')'
        );
        $st->execute([$assetId, $old, $newHolder, $dept, $loc, $date, $reason]);
        $pdo->prepare(
            'UPDATE assets SET current_holder=?,holder_department_id=COALESCE(?,holder_department_id),department_id=COALESCE(?,department_id),location_id=COALESCE(?,location_id) WHERE id=?'
        )->execute([$newHolder, $dept, $dept, $loc, $assetId]);
        logActivity(
            $pdo,
            $assetId,
            'Transfer',
            "{$a['asset_code']} transferred from {$old} to {$newHolder} on {$date}. Reason: {$reason}"
        );
        $pdo->commit();
        flash('success', 'Asset transferred successfully.');
        redirect('assets_pages/transfer.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $error = $e instanceof PDOException ? 'Could not save transfer.' : $e->getMessage();
    }
}
$assigned = $pdo->query("SELECT id,asset_code,name,current_holder,holder_department_id FROM assets WHERE status='Assigned' ORDER BY current_holder,name")->fetchAll();
$departments = fetchOptions($pdo, 'departments');
$locations = fetchOptions($pdo, 'locations');
$pageTitle = 'Asset Transfer';
$activePage = 'assets-transfer';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-area">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Asset Transfer</h2>
                <div class="topbar-subtitle">Transfer an assigned asset directly to a new holder</div>
            </div>
        </div>
    </header>
    <div class="content-wrap">
        <div class="page-heading">
            <div>
                <h1>Transfer Asset</h1>
                <p>The current holder is updated immediately; no separate return is needed.</p>
            </div>
        </div>
        <?php if ($s = flash('success')): ?>
            <div class="alert alert-success"><?= e($s) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" class="card">
            <div class="card-body p-4"><?= csrfField() ?><div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required">Asset / Current Holder</label>
                        <select name="asset_id" class="form-select" required>
                            <option value="">Select assigned asset</option>
                            <?php foreach ($assigned as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= e($a['asset_code'] . ' · ' . $a['name'] . ' — ' . $a['current_holder']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Transfer Date</label>
                        <input type="date" name="transfer_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">New Holder Name</label>
                        <input name="new_holder" class="form-control" maxlength="150" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">New Department</label>
                        <select name="new_department_id" class="form-select">
                            <option value="">Select department</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">New Location</label>
                        <select name="new_location_id" class="form-select">
                            <option value="">Keep current location</option>
                            <?php foreach ($locations as $l): ?>
                                <option value="<?= $l['id'] ?>"><?= e($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Transferred By</label>
                        <input class="form-control" value="Admin" readonly>
                    </div>
                    <div class="col-12">
                        <label class="form-label required">Reason</label><textarea name="reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <button class="btn btn-primary" <?= !$assigned ? 'disabled' : '' ?>>Confirm Transfer</button>
                </div>
                <?php if (!$assigned): ?>
                    <div class="small-muted text-end mt-2">There are no currently assigned assets available for transfer.</div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
