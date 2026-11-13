<?php
require_once __DIR__ . '/../includes/auth.php';
$id = getInt('id');
$st = $pdo->prepare('SELECT * FROM assets WHERE id=?');
$st->execute([$id]);
$asset = $st->fetch();
if (!$asset) {
    http_response_code(404);
    exit('Asset not found.');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        if ($asset['status'] === 'Disposed')
            throw new InvalidArgumentException('Disposed assets cannot be edited.');
        $name = postString('name', 150, true);
        $purchaseDate = postString('purchase_date', 10, true);
        if (!validDate($purchaseDate))
            throw new InvalidArgumentException('Enter a valid purchase date.');
        $cost = postMoney('purchase_cost');
        $months = postInt('warranty_months', 0, 600);
        $condition = postString('condition_status', 20);
        if (!in_array($condition, ['New', 'Good', 'Fair', 'Poor', 'Damaged'], true))
            throw new InvalidArgumentException('Invalid condition.');
        $pdo->beginTransaction();
        $catName = postString('new_category', 100);
        $catId = $catName !== '' ? addLookup($pdo, 'categories', $catName) : (int) ($_POST['category_id'] ?? 0);
        if (!$catId)
            throw new InvalidArgumentException('Select or add a category.');
        $subName = postString('new_sub_category', 100);
        $subId = null;
        if ($subName !== '')
            $subId = addLookup($pdo, 'sub_categories', $subName, $catId);
        elseif (!empty($_POST['sub_category_id'])) {
            $subId = (int) $_POST['sub_category_id'];
            $v = $pdo->prepare('SELECT COUNT(*) FROM sub_categories WHERE id=? AND category_id=?');
            $v->execute([$subId, $catId]);
            if (!$v->fetchColumn())
                throw new InvalidArgumentException('Invalid sub-category.');
        }
        $brandName = postString('new_brand', 100);
        $brandId = $brandName !== '' ? addLookup($pdo, 'brands', $brandName) : ((int) ($_POST['brand_id'] ?? 0) ?: null);
        $deptName = postString('new_department', 100);
        $deptId = $deptName !== '' ? addLookup($pdo, 'departments', $deptName) : ((int) ($_POST['department_id'] ?? 0) ?: null);
        $locName = postString('new_location', 150);
        $locId = $locName !== '' ? addLookup($pdo, 'locations', $locName) : ((int) ($_POST['location_id'] ?? 0) ?: null);
        $vendorId = (int) ($_POST['vendor_id'] ?? 0) ?: null;
        $vendorName = postString('new_vendor', 150);
        if ($vendorName !== '') {
            $st2 = $pdo->prepare('INSERT INTO vendors(name,contact_person,mobile) VALUES(?,?,?)');
            $st2->execute([
                $vendorName,
                postString('new_vendor_contact', 100, true),
                postString('new_vendor_mobile', 30, true)
            ]);
            $vendorId = (int) $pdo->lastInsertId();
        }
        $photo = uploadAssetPhoto('photo', $asset['photo']);
        $depEnabled = postInt('depreciation_enabled', 0, 1);
        $depMethod = postString('depreciation_method', 30);
        if (!in_array($depMethod, ['Straight Line', 'Written Down Value'], true))
            throw new InvalidArgumentException('Invalid depreciation method.');
        $life = postMoney('useful_life_years');
        $salvage = postMoney('salvage_value');
        $rate = postMoney('wdv_rate', 0, 100);
        if ($depEnabled && ($life <= 0 || $salvage > $cost))
            throw new InvalidArgumentException('Check the useful life and salvage value.');
        $st = $pdo->prepare(
            'UPDATE assets SET name=?,category_id=?,sub_category_id=?,brand_id=?,model_number=?,serial_number=?,purchase_date=?,purchase_cost=?,vendor_id=?,invoice_number=?,warranty_months=?,warranty_expiry=?,department_id=?,location_id=?,condition_status=?,description=?,photo=?,depreciation_enabled=?,depreciation_method=?,useful_life_years=?,salvage_value=?,wdv_rate=? WHERE id=?'
        );
        $st->execute(
            [
                $name,
                $catId,
                $subId,
                $brandId,
                postString('model_number', 100),
                postString('serial_number', 100),
                $purchaseDate,
                $cost,
                $vendorId,
                postString('invoice_number', 100),
                $months,
                calculateWarrantyExpiry($purchaseDate, $months),
                $deptId,
                $locId,
                $condition,
                postString('description', 5000),
                $photo,
                $depEnabled,
                $depMethod,
                $life,
                $salvage,
                $rate,
                $id
            ]
        );
        logActivity($pdo, $id, 'Edit', 'Asset information updated.');
        $pdo->commit();
        flash('success', 'Asset information updated.');
        redirect('assets_pages/index.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $error = $e instanceof PDOException ? 'Could not update the asset. Check the information and try again.' : $e->getMessage();
    }
}
$pageTitle = 'Edit Asset';
$activePage = 'assets-list';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-area">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Edit Asset</h2>
                <div class="topbar-subtitle"><?= e($asset['asset_code']) ?></div>
            </div>
        </div>
    </header>
    <div class="content-wrap">
        <div class="page-heading">
            <div>
                <h1>Edit <?= e($asset['name']) ?></h1>
                <p>Update the asset record and depreciation information.</p>
            </div>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <?php include __DIR__ . '/../includes/asset_form.php'; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
