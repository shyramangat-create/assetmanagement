<?php
require_once __DIR__ . '/../includes/auth.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        $assetId = postInt('asset_id', 1);
        $date = postString('return_date', 10, true);
        if (!validDate($date))
            throw new InvalidArgumentException('Enter a valid return date.');
        $condition = postString('condition_on_return', 20);
        if (!in_array($condition, ['New', 'Good', 'Fair', 'Poor', 'Damaged'], true))
            throw new InvalidArgumentException('Invalid return condition.');
        $damaged = postInt('is_damaged', 0, 1);
        $notes = postString('notes', 3000);
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT * FROM assets WHERE id=? FOR UPDATE");
        $st->execute([$assetId]);
        $a = $st->fetch();
        if (!$a || $a['status'] !== 'Assigned')
            throw new RuntimeException('This asset is not currently assigned.');
        $st = $pdo->prepare(
            'SELECT a.id,a.assignment_date FROM assignments a JOIN assignment_items ai ON ai.assignment_id=a.id WHERE ai.asset_id=? AND a.assigned_to=? ORDER BY a.assignment_date DESC,a.id DESC LIMIT 1'
        );
        $st->execute([$assetId, $a['current_holder']]);
        $assignment = $st->fetch();
        if ($assignment && $date < $assignment['assignment_date'])
            throw new InvalidArgumentException('Return date cannot be earlier than the assignment date (' . $assignment['assignment_date'] . ').');
        $st = $pdo->prepare(
            'INSERT INTO returns(assignment_id,asset_id,holder_name,return_date,condition_on_return,is_damaged,notes,returned_by) VALUES(?,?,?,?,?,?,?,\'Admin\')'
        );
        $st->execute([
            $assignment['id'] ?? null,
            $assetId,
            $a['current_holder'],
            $date,
            $condition,
            $damaged,
            $notes
        ]);
        $pdo->prepare("UPDATE assets SET status='Available',current_holder=NULL,holder_mobile=NULL,holder_department_id=NULL,condition_status=? WHERE id=?")->execute([$condition, $assetId]);
        logActivity(
            $pdo,
            $assetId,
            'Return',
            "{$a['asset_code']} returned by {$a['current_holder']} on {$date}. Condition: {$condition}; damaged: " . ($damaged ? 'Yes' : 'No') . '.'
        );
        $pdo->commit();
        flash('success', 'Asset return recorded.');
        redirect('assets_pages/return.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $error = $e instanceof PDOException ? 'Could not save return record.' : $e->getMessage();
    }
}
$holders = $pdo->query("SELECT DISTINCT current_holder FROM assets WHERE status='Assigned' AND current_holder IS NOT NULL ORDER BY current_holder")->fetchAll();
$holder = $_GET['holder'] ?? '';
$assigned = [];
if ($holder !== '') {
    $st = $pdo->prepare("SELECT id,asset_code,name FROM assets WHERE status='Assigned' AND current_holder=? ORDER BY name");
    $st->execute([$holder]);
    $assigned = $st->fetchAll();
}
$pageTitle = 'Asset Return';
$activePage = 'assets-return';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-area">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Asset Return</h2>
                <div class="topbar-subtitle">Record assets returned by current holders</div>
            </div>
        </div>
    </header>
    <div class="content-wrap">
        <div class="page-heading">
            <div>
                <h1>Return Asset</h1>
                <p>Choose a current holder, then select the asset being returned.</p>
            </div>
        </div>
        <?php if ($s = flash('success')): ?>
            <div class="alert alert-success"><?= e($s) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <div class="card mb-3">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Current Holder</label>
                        <select name="holder" class="form-select" required>
                            <option value="">Select holder</option>
                            <?php foreach ($holders as $h): ?>
                                <option<?= selected($holder, $h['current_holder']) ?>><?= e($h['current_holder']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-primary">Find Assets</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($assigned): ?>
            <form method="post" class="card">
                <div class="card-body p-4"><?= csrfField() ?><div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Asset</label>
                            <select name="asset_id" class="form-select" required>
                                <option value="">Select asset</option>
                                <?php foreach ($assigned as $a): ?>
                                    <option value="<?= $a['id'] ?>"><?= e($a['asset_code'] . ' · ' . $a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Return Date</label>
                            <input type="date" name="return_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Condition on Return</label>
                            <select name="condition_on_return" class="form-select">
                                <?php foreach (['New', 'Good', 'Fair', 'Poor', 'Damaged'] as $c): ?>
                                    <option><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Damaged?</label>
                            <select name="is_damaged" class="form-select">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Returned By</label>
                            <input class="form-control" value="Admin" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button class="btn btn-primary">Save Return</button>
                    </div>
                </div>
            </form>
        <?php elseif ($holder !== ''): ?>
            <div class="alert alert-info">No assigned assets were found for this holder.</div>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
