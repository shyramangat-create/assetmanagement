<?php
require_once __DIR__ . '/../includes/auth.php';
$error = '';
$editId = getInt('edit');
$editing = null;
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM vendors WHERE id=?');
    $st->execute([$editId]);
    $editing = $st->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        $action = $_POST['action'] ?? 'save';
        if ($action === 'delete') {
            $id = postInt('id', 1);
            $pdo->prepare('DELETE FROM vendors WHERE id=?')->execute([$id]);
            flash('success', 'Vendor deleted.');
            redirect('vendors/index.php');
        }
        $name = postString('name', 150, true);
        $contact = postString('contact_person', 100, true);
        $mobile = postString('mobile', 30, true);
        $email = postString('email', 150);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            throw new InvalidArgumentException('Enter a valid email address.');
        $address = postString('address', 2000);
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $st = $pdo->prepare('UPDATE vendors SET name=?,contact_person=?,mobile=?,email=?,address=? WHERE id=?');
            $st->execute([$name, $contact, $mobile, $email ?: null, $address ?: null, $id]);
            flash('success', 'Vendor updated.');
        } else {
            $st = $pdo->prepare('INSERT INTO vendors(name,contact_person,mobile,email,address) VALUES(?,?,?,?,?)');
            $st->execute([$name, $contact, $mobile, $email ?: null, $address ?: null]);
            flash('success', 'Vendor added.');
        }redirect('vendors/index.php');
    } catch (Throwable $e) {
        $error = $e instanceof PDOException ? 'Could not save vendor. A vendor with this information may already exist.' : $e->getMessage();
    }
}
$vendors = $pdo->query('SELECT v.*, (SELECT COUNT(*) FROM assets a WHERE a.vendor_id=v.id) asset_count FROM vendors v ORDER BY v.name')->fetchAll();
$pageTitle = 'Vendors';
$activePage = 'vendors';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-area">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Vendors</h2>
                <div class="topbar-subtitle">Manage suppliers and service providers</div>
            </div>
        </div>
    </header>
    <div class="content-wrap">
        <div class="page-heading">
            <div>
                <h1>Vendor Directory</h1>
                <p>Maintain supplier contact information for purchases and maintenance.</p>
            </div>
        </div>
        <?php if ($s = flash('success')): ?>
            <div class="alert alert-success"><?= e($s) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <div class="row g-3">
            <div class="col-xl-4">
                <form method="post" class="card">
                    <div class="card-header">
                        <h3 class="card-title-sm"><?= $editing ? 'Edit Vendor' : 'Add Vendor' ?></h3>
                    </div>
                    <div class="card-body"><?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
                        <div class="mb-3">
                            <label class="form-label required">Vendor Name</label>
                            <input class="form-control" name="name" maxlength="150" value="<?= e($editing['name'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Contact Person</label>
                            <input class="form-control" name="contact_person" maxlength="100" value="<?= e($editing['contact_person'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Mobile Number</label>
                            <input class="form-control" name="mobile" maxlength="30" value="<?= e($editing['mobile'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= e($editing['email'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label><textarea class="form-control" name="address" rows="3"><?= e($editing['address'] ?? '') ?></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary flex-grow-1">Save Vendor</button>
                            <?php if ($editing): ?>
                                <a class="btn btn-light" href="index.php">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title-sm">Registered Vendors <span class="text-secondary fw-normal">(<?= count($vendors) ?>)</span></h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Vendor</th>
                                    <th>Contact</th>
                                    <th>Mobile</th>
                                    <th>Assets</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendors as $v): ?>
                                    <tr>
                                        <td><strong><?= e($v['name']) ?></strong>
                                            <div class="small-muted"><?= e($v['email'] ?? '') ?></div>
                                        </td>
                                        <td><?= e($v['contact_person']) ?></td>
                                        <td><?= e($v['mobile']) ?></td>
                                        <td><?= number_format((int) $v['asset_count']) ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $v['id'] ?>">Edit</a>
                                                <form method="post" onsubmit="return confirm('Delete this vendor? Existing asset references will be cleared.');"><?= csrfField() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$vendors): ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">No vendors registered yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
