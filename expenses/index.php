<?php
require_once __DIR__ . '/../includes/auth.php';
$error = '';
$editId = getInt('edit');
$editing = null;
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM expenses WHERE id=?');
    $st->execute([$editId]);
    $editing = $st->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        $action = $_POST['action'] ?? 'save';
        if ($action === 'delete') {
            $id = postInt('id', 1);
            $st = $pdo->prepare('SELECT asset_id,category,amount FROM expenses WHERE id=?');
            $st->execute([$id]);
            $ex = $st->fetch();
            if (!$ex)
                throw new RuntimeException('Expense not found.');
            $pdo->prepare('DELETE FROM expenses WHERE id=?')->execute([$id]);
            logActivity(
                $pdo,
                (int) $ex['asset_id'],
                'Edit',
                'Expense record deleted: ' . $ex['category'] . ' (' . number_format((float) $ex['amount'], 2) . ').'
            );
            flash('success', 'Expense deleted.');
            redirect('expenses/index.php');
        }
        $asset = postInt('asset_id', 1);
        $category = postString('category', 100, true);
        $person = postString('involved_person', 150);
        $amount = postMoney('amount', 0.01);
        $date = postString('expense_date', 10, true);
        if (!validDate($date))
            throw new InvalidArgumentException('Enter a valid expense date.');
        $desc = postString('description', 3000);
        $check = $pdo->prepare('SELECT COUNT(*) FROM assets WHERE id=?');
        $check->execute([$asset]);
        if (!$check->fetchColumn())
            throw new InvalidArgumentException('Select a valid asset.');
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('UPDATE expenses SET asset_id=?,category=?,involved_person=?,amount=?,expense_date=?,description=? WHERE id=?')->execute([$asset, $category, $person ?: null, $amount, $date, $desc, $id]);
        } else {
            $pdo->prepare('INSERT INTO expenses(asset_id,category,involved_person,amount,expense_date,description) VALUES(?,?,?,?,?,?)')->execute([$asset, $category, $person ?: null, $amount, $date, $desc]);
        }logActivity($pdo, $asset, 'Edit', 'Expense recorded/updated: ' . $category . ' on ' . $date . '.');
        flash('success', $id ? 'Expense updated.' : 'Expense recorded.');
        redirect('expenses/index.php');
    } catch (Throwable $e) {
        $error = $e instanceof PDOException ? 'Could not save expense record.' : $e->getMessage();
    }
}
$assets = $pdo->query('SELECT id,asset_code,name FROM assets ORDER BY asset_code')->fetchAll();
$records = $pdo->query('SELECT e.*,a.asset_code,a.name asset_name FROM expenses e JOIN assets a ON a.id=e.asset_id ORDER BY e.expense_date DESC,e.id DESC LIMIT 200')->fetchAll();
$total = (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM expenses')->fetchColumn();
$pageTitle = 'Expenses';
$activePage = 'expenses';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-area">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Expenses</h2>
                <div class="topbar-subtitle">Track asset-related costs and spending</div>
            </div>
        </div>
    </header>
    <div class="content-wrap">
        <div class="page-heading">
            <div>
                <h1>Asset Expenses</h1>
                <p>Record repairs, fuel, supplies, and other asset-related costs.</p>
            </div>
            <div class="text-end">
                <div class="small-muted">Total recorded expenses</div>
                <div class="h4 fw-bold mb-0"><?= money($total) ?></div>
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
                <h3 class="card-title-sm"><?= $editing ? 'Edit Expense' : 'Add Expense' ?></h3>
            </div>
            <div class="card-body"><?= csrfField() ?>
                <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label required">Asset</label>
                        <select class="form-select" name="asset_id" required>
                            <option value="">Select asset</option>
                            <?php foreach ($assets as $a): ?>
                                <option value="<?= $a['id'] ?>" <?= selected($editing['asset_id'] ?? '', $a['id']) ?>><?= e($a['asset_code'] . ' · ' . $a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Expense Category</label>
                        <select class="form-select" name="category" required>
                            <?php
                            $cats = ['Repair', 'Fuel', 'Supplies', 'Parts', 'Transportation', 'Inspection', 'Other'];
                            foreach ($cats as $c):
                            ?>
                                <option<?= selected($editing['category'] ?? 'Repair', $c) ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label required">Amount (₱)</label>
                        <input type="number" min="0.01" step="0.01" class="form-control" name="amount" value="<?= e($editing['amount'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Expense Date</label>
                        <input type="date" class="form-control" name="expense_date" value="<?= e($editing['expense_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Person / Vendor Involved</label>
                        <input class="form-control" name="involved_person" value="<?= e($editing['involved_person'] ?? '') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Description</label>
                        <input class="form-control" name="description" value="<?= e($editing['description'] ?? '') ?>">
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <?php if ($editing): ?>
                        <a href="index.php" class="btn btn-light">Cancel</a>
                    <?php endif; ?>
                    <button class="btn btn-primary">Save Expense</button>
                </div>
            </div>
        </form>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title-sm">Expense History</h3>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Asset</th>
                            <th>Category</th>
                            <th>Person / Vendor</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td><?= e($r['expense_date']) ?></td>
                                <td><strong><?= e($r['asset_code']) ?></strong>
                                    <div><?= e($r['asset_name']) ?></div>
                                </td>
                                <td><?= e($r['category']) ?></td>
                                <td><?= e($r['involved_person'] ?? '—') ?></td>
                                <td><?= money($r['amount']) ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>">Edit</a>
                                        <form method="post" onsubmit="return confirm('Delete this expense?')"><?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$records): ?>
                            <tr>
                                <td colspan="6" class="empty-state">No expenses recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
