<?php
require_once __DIR__ . '/../includes/auth.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
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
            $st = $pdo->prepare('SELECT COUNT(*) FROM sub_categories WHERE id=? AND category_id=?');
            $st->execute([$subId, $catId]);
            if (!$st->fetchColumn())
                throw new InvalidArgumentException('Sub-category does not match the selected category.');
        }
        $brandName = postString('new_brand', 100);
        $brandId = $brandName !== '' ? addLookup($pdo, 'brands', $brandName) : ((int) ($_POST['brand_id'] ?? 0) ?: null);
        $deptName = postString('new_department', 100);
        $deptId = $deptName !== '' ? addLookup($pdo, 'departments', $deptName) : ((int) ($_POST['department_id'] ?? 0) ?: null);
        $locName = postString('new_location', 150);
        $locId = $locName !== '' ? addLookup($pdo, 'locations', $locName) : ((int) ($_POST['location_id'] ?? 0) ?: null);
        $vendorName = postString('new_vendor', 150);
        $vendorId = (int) ($_POST['vendor_id'] ?? 0) ?: null;
        if ($vendorName !== '') {
            $contact = postString('new_vendor_contact', 100, true);
            $mobile = postString('new_vendor_mobile', 30, true);
            $st = $pdo->prepare('INSERT INTO vendors(name,contact_person,mobile) VALUES(?,?,?)');
            $st->execute([$vendorName, $contact, $mobile]);
            $vendorId = (int) $pdo->lastInsertId();
        }
        $photo = uploadAssetPhoto('photo');
        $warranty = calculateWarrantyExpiry($purchaseDate, $months);
        $depEnabled = postInt('depreciation_enabled', 0, 1);
        $depMethod = postString('depreciation_method', 30);
        if (!in_array($depMethod, ['Straight Line', 'Written Down Value'], true))
            throw new InvalidArgumentException('Invalid depreciation method.');
        $life = postMoney('useful_life_years');
        $salvage = postMoney('salvage_value');
        $rate = postMoney('wdv_rate', 0, 100);
        if ($depEnabled && ($life <= 0 || $salvage > $cost))
            throw new InvalidArgumentException('Depreciation requires a useful life greater than zero and salvage value not greater than cost.');
        $st = $pdo->prepare(
            "INSERT INTO assets(asset_code,name,category_id,sub_category_id,brand_id,model_number,serial_number,purchase_date,purchase_cost,vendor_id,invoice_number,warranty_months,warranty_expiry,department_id,location_id,condition_status,description,photo,depreciation_enabled,depreciation_method,useful_life_years,salvage_value,wdv_rate,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Available')"
        );
        $st->execute(
            [
                'TMP-' . bin2hex(random_bytes(5)),
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
                $warranty,
                $deptId,
                $locId,
                $condition,
                postString('description', 5000),
                $photo,
                $depEnabled,
                $depMethod,
                $life,
                $salvage,
                $rate
            ]
        );
        $id = (int) $pdo->lastInsertId();
        $code = 'AST-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE assets SET asset_code=? WHERE id=?')->execute([$code, $id]);
        logActivity($pdo, $id, 'Purchase', "Asset {$code} ({$name}) registered.");
        $pdo->commit();
        flash('success', 'Asset registered successfully. Asset ID: ' . $code);
        redirect('assets_pages/index.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $error = $e instanceof PDOException ? 'Could not save asset. Check the entered information and try again.' : $e->getMessage();
    }
}
$pageTitle = 'Asset Entry';
$activePage = 'assets-entry';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-area">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Asset Entry</h2>
                <div class="topbar-subtitle">Register a new company or office asset</div>
            </div>
        </div>
    </header>
    <div class="content-wrap">
        <div class="page-heading">
            <div>
                <h1>Add New Asset</h1>
                <p>Enter the purchase, location, condition, and depreciation details.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-list me-1"></i> Asset List</a>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <?php include __DIR__ . '/../includes/asset_form.php'; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
