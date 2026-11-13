<?php
$isEdit = isset($asset) && is_array($asset);
$categories = fetchOptions($pdo, 'categories');
$brands = fetchOptions($pdo, 'brands');
$vendors = fetchOptions($pdo, 'vendors');
$departments = fetchOptions($pdo, 'departments');
$locations = fetchOptions($pdo, 'locations');
$subcats = [];
if ($isEdit && !empty($asset['category_id'])) {
    $st = $pdo->prepare('SELECT id,name FROM sub_categories WHERE category_id=? ORDER BY name');
    $st->execute([$asset['category_id']]);
    $subcats = $st->fetchAll();
}
?>
<form method="post" enctype="multipart/form-data" class="card">
    <div class="card-body p-4">
        <?= csrfField() ?>
        <div class="form-section">
            <div class="form-section-title">Basic Information</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Asset Name</label>
                    <input class="form-control" name="name" maxlength="150" value="<?= e($asset['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Asset ID</label>
                    <input class="form-control" value="<?= e($asset['asset_code'] ?? 'Auto-generated after save') ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Condition</label>
                    <select class="form-select" name="condition_status">
                        <?php foreach (['New', 'Good', 'Fair', 'Poor', 'Damaged'] as $v): ?>
                            <option<?= selected($asset['condition_status'] ?? 'New', $v) ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">Category</label>
                    <select class="form-select" name="category_id">
                        <option value="">Select category</option>
                        <?php foreach ($categories as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= selected($asset['category_id'] ?? '', $o['id']) ?>><?= e($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-control form-control-sm mt-2" name="new_category" placeholder="Add new category (optional)">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sub-category</label>
                    <select class="form-select" name="sub_category_id" data-dependent="sub-category">
                        <option value="">Select sub-category</option>
                        <?php foreach ($subcats as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= selected($asset['sub_category_id'] ?? '', $o['id']) ?>><?= e($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-control form-control-sm mt-2" name="new_sub_category" placeholder="Add new sub-category (optional)">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Brand</label>
                    <select class="form-select" name="brand_id">
                        <option value="">Select brand</option>
                        <?php foreach ($brands as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= selected($asset['brand_id'] ?? '', $o['id']) ?>><?= e($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-control form-control-sm mt-2" name="new_brand" placeholder="Add new brand (optional)">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Model Number</label>
                    <input class="form-control" name="model_number" value="<?= e($asset['model_number'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Serial Number</label>
                    <input class="form-control" name="serial_number" value="<?= e($asset['serial_number'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Invoice Number</label>
                    <input class="form-control" name="invoice_number" value="<?= e($asset['invoice_number'] ?? '') ?>">
                </div>
            </div>
        </div>
        <div class="form-section">
            <div class="form-section-title">Purchase Information</div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label required">Purchase Date</label>
                    <input type="date" class="form-control" name="purchase_date" value="<?= e($asset['purchase_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label required">Purchase Cost (₱)</label>
                    <input type="number" min="0" step="0.01" class="form-control" name="purchase_cost" value="<?= e($asset['purchase_cost'] ?? '0') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Warranty (Months)</label>
                    <input type="number" min="0" max="600" class="form-control" name="warranty_months" value="<?= e($asset['warranty_months'] ?? 0) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Warranty Expiry</label>
                    <input type="date" class="form-control" data-warranty-expiry value="<?= e($asset['warranty_expiry'] ?? '') ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Vendor</label>
                    <select class="form-select" name="vendor_id">
                        <option value="">Select vendor</option>
                        <?php foreach ($vendors as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= selected($asset['vendor_id'] ?? '', $o['id']) ?>><?= e($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-control form-control-sm mt-2" name="new_vendor" placeholder="Add new vendor name (optional)">
                </div>
                <div class="col-md-3">
                    <label class="form-label">New Vendor Contact</label>
                    <input class="form-control" name="new_vendor_contact" placeholder="Contact person">
                </div>
                <div class="col-md-3">
                    <label class="form-label">New Vendor Mobile</label>
                    <input class="form-control" name="new_vendor_mobile" placeholder="Mobile number">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <select class="form-select" name="department_id">
                        <option value="">Select department</option>
                        <?php foreach ($departments as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= selected($asset['department_id'] ?? '', $o['id']) ?>><?= e($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-control form-control-sm mt-2" name="new_department" placeholder="Add new department (optional)">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Location</label>
                    <select class="form-select" name="location_id">
                        <option value="">Select location</option>
                        <?php foreach ($locations as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= selected($asset['location_id'] ?? '', $o['id']) ?>><?= e($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-control form-control-sm mt-2" name="new_location" placeholder="Add new location (optional)">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Asset Photo</label>
                    <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png,.webp">
                    <div class="form-text">JPG, PNG or WEBP; maximum 5 MB.</div>
                    <?php if (!empty($asset['photo'])): ?>
                        <div class="small-muted mt-1">Current photo: <?= e($asset['photo']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="form-section">
            <div class="form-section-title">Description</div><textarea class="form-control" rows="3" name="description" maxlength="5000" placeholder="Additional asset details..."><?= e($asset['description'] ?? '') ?></textarea>
        </div>
        <div class="form-section">
            <div class="form-section-title">Depreciation <span class="small-muted fw-normal">(Optional)</span></div>
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Enable Depreciation</label>
                    <select class="form-select" name="depreciation_enabled" id="depEnabled">
                        <option value="0" <?= selected($asset['depreciation_enabled'] ?? 0, 0) ?>>No</option>
                        <option value="1" <?= selected($asset['depreciation_enabled'] ?? 0, 1) ?>>Yes</option>
                    </select>
                </div>
                <div class="col-md-3 dep-fields">
                    <label class="form-label">Method</label>
                    <select class="form-select" name="depreciation_method">
                        <option<?= selected($asset['depreciation_method'] ?? 'Straight Line', 'Straight Line') ?>>Straight Line</option>
                            <option<?= selected($asset['depreciation_method'] ?? '', 'Written Down Value') ?>>Written Down Value</option>
                    </select>
                </div>
                <div class="col-md-2 dep-fields">
                    <label class="form-label">Useful Life (Years)</label>
                    <input type="number" min="0" max="100" step="0.1" class="form-control" name="useful_life_years" value="<?= e($asset['useful_life_years'] ?? '5') ?>">
                </div>
                <div class="col-md-2 dep-fields">
                    <label class="form-label">Salvage Value (₱)</label>
                    <input type="number" min="0" step="0.01" class="form-control" name="salvage_value" value="<?= e($asset['salvage_value'] ?? '0') ?>">
                </div>
                <div class="col-md-2 dep-fields">
                    <label class="form-label">WDV Rate (%)</label>
                    <input type="number" min="0" max="100" step="0.01" class="form-control" name="wdv_rate" value="<?= e($asset['wdv_rate'] ?? '20') ?>">
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-end">
            <a href="<?= BASE_URL ?>assets_pages/index.php" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i><?= $isEdit ? 'Save Changes' : 'Save Asset' ?></button>
        </div>
    </div>
</form>
<script>
    const dep = document.getElementById('depEnabled');

    function toggleDep() {
        document.querySelectorAll('.dep-fields').forEach(x => x.style.display = dep.value === '1' ? 'block' : 'none')
    }
    dep?.addEventListener('change', toggleDep);
    toggleDep();
</script>
