<?php
require_once __DIR__ . '/../includes/auth.php';
$error = '';
$created = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        $date = postString('assignment_date', 10, true);
        if (!validDate($date))
            throw new InvalidArgumentException('Enter a valid assignment date.');
        $holder = postString('assigned_to', 150, true);
        $dept = (int) ($_POST['department_id'] ?? 0) ?: null;
        $mobile = postString('mobile', 30);
        $condition = postString('condition_at_assignment', 20);
        if (!in_array($condition, ['New', 'Good', 'Fair', 'Poor', 'Damaged'], true))
            throw new InvalidArgumentException('Invalid condition.');
        $assetIds = array_values(array_unique(array_filter(array_map('intval', $_POST['asset_ids'] ?? []))));
        if (!$assetIds)
            throw new InvalidArgumentException('Select at least one available asset.');
        $pdo->beginTransaction();
        $marks = implode(',', array_fill(0, count($assetIds), '?'));
        $st = $pdo->prepare("SELECT id,asset_code,name,status FROM assets WHERE id IN ({$marks}) FOR UPDATE");
        $st->execute($assetIds);
        $selectedAssets = $st->fetchAll();
        if (count($selectedAssets) !== count($assetIds))
            throw new RuntimeException('One or more selected assets no longer exist.');
        foreach ($selectedAssets as $a)
            if ($a['status'] !== 'Available')
                throw new RuntimeException($a['asset_code'] . ' is no longer available. Refresh the page and try again.');
        $st = $pdo->prepare(
            'INSERT INTO assignments(assignment_date,assigned_to,department_id,mobile,condition_at_assignment,notes,assigned_by) VALUES(?,?,?,?,?,? ,\'Admin\')'
        );
        $st->execute([$date, $holder, $dept, $mobile ?: null, $condition, postString('notes', 3000)]);
        $assignmentId = (int) $pdo->lastInsertId();
        $item = $pdo->prepare('INSERT INTO assignment_items(assignment_id,asset_id) VALUES(?,?)');
        $upd = $pdo->prepare("UPDATE assets SET status='Assigned',current_holder=?,holder_mobile=?,holder_department_id=?,department_id=? WHERE id=?");
        foreach ($selectedAssets as $a) {
            $item->execute([$assignmentId, $a['id']]);
            $upd->execute([$holder, $mobile ?: null, $dept, $dept, $a['id']]);
            logActivity($pdo, (int) $a['id'], 'Assignment', "{$a['asset_code']} assigned to {$holder} on {$date} by Admin.");
        }
        $pdo->commit();
        flash('success', 'Assignment saved for ' . count($selectedAssets) . ' asset(s).');
        redirect('assets_pages/assignment.php?created=' . $assignmentId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $error = $e instanceof PDOException ? 'Could not complete assignment. Please check the information.' : $e->getMessage();
    }
}
$available = $pdo->query(
    "SELECT a.id,a.asset_code,a.name,c.name category FROM assets a LEFT JOIN categories c ON c.id=a.category_id WHERE a.status='Available' ORDER BY a.name"
)->fetchAll();
$departments = fetchOptions($pdo, 'departments');
$createdId = getInt('created');
$wa = null;
if ($createdId) {
    $s = $pdo->prepare('SELECT assigned_to,mobile FROM assignments WHERE id=?');
    $s->execute([$createdId]);
    $record = $s->fetch();
    if ($record && $record['mobile']) {
        $s = $pdo->prepare('SELECT a.asset_code,a.name FROM assignment_items ai JOIN assets a ON a.id=ai.asset_id WHERE ai.assignment_id=?');
        $s->execute([$createdId]);
        $lines = [];
        foreach ($s->fetchAll() as $x)
            $lines[] = $x['asset_code'] . ' - ' . $x['name'];
        $phone = preg_replace('/\D/', '', $record['mobile']);
        if (strlen($phone) === 11 && str_starts_with($phone, '0'))
            $phone = '63' . substr($phone, 1);
        $wa = 'https://wa.me/' . $phone . '?text=' . rawurlencode("Hello {$record['assigned_to']}, the following asset(s) have been assigned to you:\n- " . implode("\n- ", $lines));
    }
}
$pageTitle = 'Asset Assignment';
$activePage = 'assets-assignment';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-area">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Asset Assignment</h2>
                <div class="topbar-subtitle">Assign one or more available assets to a holder</div>
            </div>
        </div>
    </header>
    <div class="content-wrap">
        <div class="page-heading">
            <div>
                <h1>New Assignment</h1>
                <p>The system records the administrator as the person who assigned the assets.</p>
            </div>
        </div>
        <?php if ($s = flash('success')): ?>
            <div class="alert alert-success"><?= e($s) ?>
                <?php if ($wa): ?>
                    <a class="alert-link ms-2" target="_blank" rel="noopener" href="<?= e($wa) ?>"><i class="fa-brands fa-whatsapp"></i> Send WhatsApp message</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" class="card">
            <div class="card-body p-4"><?= csrfField() ?><div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label required">Assignment Date</label>
                        <input type="date" name="assignment_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Assigned To (Name)</label>
                        <input name="assigned_to" class="form-control" maxlength="150" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-select">
                            <option value="">Select department</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mobile Number (Optional)</label>
                        <input name="mobile" class="form-control" maxlength="30" placeholder="e.g. 09XXXXXXXXX">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Condition at Assignment</label>
                        <select name="condition_at_assignment" class="form-select">
                            <?php foreach (['New', 'Good', 'Fair', 'Poor', 'Damaged'] as $c): ?>
                                <option><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Assigned By</label>
                        <input class="form-control" value="Admin" readonly>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h3 class="form-section-title mb-0">Select Available Assets</h3><span class="small-muted"><?= count($available) ?> available</span>
                </div>
                <div class="table-responsive border rounded-3">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" data-select-all=".asset-check">
                                </th>
                                <th>Asset ID</th>
                                <th>Asset Name</th>
                                <th>Category</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available as $a): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="asset-check" name="asset_ids[]" value="<?= $a['id'] ?>">
                                    </td>
                                    <td class="fw-semibold"><?= e($a['asset_code']) ?></td>
                                    <td><?= e($a['name']) ?></td>
                                    <td><?= e($a['category'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$available): ?>
                                <tr>
                                    <td colspan="4" class="empty-state">No available assets to assign.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <button class="btn btn-primary" <?= !$available ? 'disabled' : '' ?>><i class="fa-solid fa-user-plus me-1"></i> Save Assignment</button>
                </div>
            </div>
        </form>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
