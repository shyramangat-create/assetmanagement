<?php
require_once __DIR__ . '/../includes/auth.php';
$columns = [
    'name',
    'category',
    'sub_category',
    'brand',
    'model_number',
    'serial_number',
    'purchase_date',
    'purchase_cost',
    'vendor',
    'invoice_number',
    'warranty_months',
    'department',
    'location',
    'condition_status',
    'description',
    'depreciation_enabled',
    'depreciation_method',
    'useful_life_years',
    'salvage_value',
    'wdv_rate'
];
$templateColumns = array_map(static fn(string $column): string => str_replace('_', ' ', $column), $columns);
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="asset_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $templateColumns);
    fputcsv(
        $out,
        [
            'Sample Laptop',
            'Computer Equipment',
            'Laptop',
            'Dell',
            'Latitude 5420',
            'SN-0001',
            date('m/d/Y'),
            '45000',
            'ABC Computer Supplies',
            'INV-001',
            '12',
            'IT Department',
            'Main Office',
            'Good',
            'Sample description',
            '1',
            'Straight Line',
            '5',
            '5000',
            '20'
        ]
    );
    fclose($out);
    exit;
}
$error = '';
$summary = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK)
            throw new RuntimeException('Choose a spreadsheet file to import.');
        $file = $_FILES['csv_file'];
        if ($file['size'] > 10 * 1024 * 1024)
            throw new RuntimeException('CSV file must be 10 MB or smaller.');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx', 'xls'], true))
            throw new RuntimeException('Upload a CSV, XLSX, or XLS file.');
        $dataRows = [];
        if ($ext === 'csv') {
            $fh = fopen($file['tmp_name'], 'r');
            if (!$fh)
                throw new RuntimeException('Could not read the uploaded file.');
            while (($csvRow = fgetcsv($fh)) !== false)
                $dataRows[] = $csvRow;
            fclose($fh);
        } elseif (in_array($ext, ['xlsx', 'xls'], true)) {
            $autoload = ROOT_PATH . '/vendor/autoload.php';
            if (!is_file($autoload))
                throw new RuntimeException('Excel import needs PhpSpreadsheet. Run composer install in the project folder, or save the workbook as CSV UTF-8.');
            require_once $autoload;
            $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $dataRows = $book->getActiveSheet()->toArray(null, true, true, false);
        } else throw new RuntimeException('Upload a CSV, XLSX, or XLS file.');
        $header = array_shift($dataRows);
        if (!$header)
            throw new RuntimeException('Import file is empty.');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        if (array_map('normalizeImportHeader', $header) !== $columns)
            throw new RuntimeException('The import columns do not match the required template. Download the template and keep the exact column order.');
        $pdo->beginTransaction();
        $imported = 0;
        $failed = [];
        $line = 1;
        foreach ($dataRows as $row) {
            $line++;
            if (count($row) === 1 && trim((string) $row[0]) === '')
                continue;
            if (count($row) !== count($columns)) {
                $failed[] = "Row {$line}: incorrect number of columns.";
                continue;
            }$r = array_combine($columns, $row);
            try {
                $name = trim((string) $r['name']);
                $category = trim((string) $r['category']);
                $purchase = normalizeImportDate((string) $r['purchase_date']);
                $cost = trim((string) $r['purchase_cost']);
                if ($name === '' || $category === '' || !validDate($purchase) || !is_numeric($cost) || (float) $cost < 0)
                    throw new InvalidArgumentException('Name, category, valid purchase date, and non-negative cost are required.');
                $cat = addLookup($pdo, 'categories', $category);
                $sub = null;
                if (trim((string) $r['sub_category']) !== '')
                    $sub = addLookup($pdo, 'sub_categories', trim((string) $r['sub_category']), $cat);
                $brand = trim((string) $r['brand']) !== '' ? addLookup($pdo, 'brands', trim((string) $r['brand'])) : null;
                $dept = trim((string) $r['department']) !== '' ? addLookup($pdo, 'departments', trim((string) $r['department'])) : null;
                $loc = trim((string) $r['location']) !== '' ? addLookup($pdo, 'locations', trim((string) $r['location'])) : null;
                $vendor = null;
                $vn = trim((string) $r['vendor']);
                if ($vn !== '') {
                    $s = $pdo->prepare('SELECT id FROM vendors WHERE name=? LIMIT 1');
                    $s->execute([$vn]);
                    $vendor = $s->fetchColumn();
                    if (!$vendor) {
                        $s = $pdo->prepare("INSERT INTO vendors(name,contact_person,mobile) VALUES(?,'Not provided','Not provided')");
                        $s->execute([$vn]);
                        $vendor = (int) $pdo->lastInsertId();
                    }
                }
                $months = max(0, min(600, (int) $r['warranty_months']));
                $condition = trim((string) $r['condition_status']) ?: 'New';
                if (!in_array($condition, ['New', 'Good', 'Fair', 'Poor', 'Damaged'], true))
                    $condition = 'New';
                $enabled = (int) $r['depreciation_enabled'] === 1 ? 1 : 0;
                $method = in_array(trim((string) $r['depreciation_method']), ['Straight Line', 'Written Down Value'], true) ? trim((string) $r['depreciation_method']) : 'Straight Line';
                $life = max(0, (float) $r['useful_life_years']);
                $salvage = max(0, (float) $r['salvage_value']);
                $rate = max(0, min(100, (float) $r['wdv_rate']));
                if ($enabled && ($life <= 0 || $salvage > (float) $cost))
                    throw new InvalidArgumentException('Invalid depreciation life or salvage value.');
                $warranty = calculateWarrantyExpiry($purchase, $months);
                $s = $pdo->prepare(
                    "INSERT INTO assets(asset_code,name,category_id,sub_category_id,brand_id,model_number,serial_number,purchase_date,purchase_cost,vendor_id,invoice_number,warranty_months,warranty_expiry,department_id,location_id,condition_status,description,depreciation_enabled,depreciation_method,useful_life_years,salvage_value,wdv_rate,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Available')"
                );
                $s->execute(
                    [
                        'TMP-' . bin2hex(random_bytes(5)),
                        $name,
                        $cat,
                        $sub,
                        $brand,
                        trim((string) $r['model_number']),
                        trim((string) $r['serial_number']),
                        $purchase,
                        (float) $cost,
                        $vendor,
                        trim((string) $r['invoice_number']),
                        $months,
                        $warranty,
                        $dept,
                        $loc,
                        $condition,
                        trim((string) $r['description']),
                        $enabled,
                        $method,
                        $life,
                        $salvage,
                        $rate
                    ]
                );
                $id = (int) $pdo->lastInsertId();
                $code = 'AST-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
                $pdo->prepare('UPDATE assets SET asset_code=? WHERE id=?')->execute([$code, $id]);
                logActivity($pdo, $id, 'Purchase', "Asset {$code} ({$name}) imported from CSV.");
                $imported++;
            } catch (Throwable $rowError) {
                $failed[] = "Row {$line}: " . $rowError->getMessage();
            }
        }
        $pdo->commit();
        $summary = ['imported' => $imported, 'failed' => $failed];
    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $error = $e instanceof PDOException ? 'Import failed because of a database constraint. Review the CSV rows and try again.' : $e->getMessage();
    }
}
$pageTitle = 'Import Assets';
$activePage = 'assets-import';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-area">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light mobile-menu" id="openSidebar"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h2 class="topbar-title">Import Assets</h2>
                <div class="topbar-subtitle">Import asset records using the fixed CSV template</div>
            </div>
        </div>
    </header>
    <div class="content-wrap">
        <div class="page-heading">
            <div>
                <h1>Import Assets from Spreadsheet</h1>
                <p>Keep the template column order. Headers may use spaces or underscores.</p>
            </div>
            <a class="btn btn-outline-primary" href="?template=1"><i class="fa-solid fa-download me-1"></i> Download CSV Template</a>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($summary): ?>
            <div class="alert alert-success"><strong><?= number_format($summary['imported']) ?> asset(s) imported.</strong>
                <?php if ($summary['failed']): ?>
                    <div class="mt-2">Some rows were skipped:</div>
                    <ul class="mb-0 mt-1">
                        <?php foreach (array_slice($summary['failed'], 0, 30) as $f): ?>
                            <li><?= e($f) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="row g-3">
            <div class="col-lg-7">
                <form method="post" enctype="multipart/form-data" class="card">
                    <div class="card-header">
                        <h3 class="card-title-sm">Upload CSV / Excel File</h3>
                    </div>
                    <div class="card-body"><?= csrfField() ?><label class="form-label required">CSV / Excel File</label>
                        <input type="file" class="form-control" name="csv_file" accept=".csv,.xlsx,.xls,text/csv" required>
                        <div class="form-text">Maximum file size: 10 MB. The file must use the exact template column order.</div>
                        <div class="d-flex justify-content-end mt-4">
                            <button class="btn btn-primary">Import Assets</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title-sm">Required CSV Column Order</h3>
                    </div>
                    <div class="card-body">
                        <p class="small-muted">Keep every column, even when a value is blank. Headers accept spaces or underscores. You may also use "condition" instead of "condition status". Purchase dates accept MM/DD/YYYY or YYYY-MM-DD.</p>
                        <ol class="small mb-0">
                            <?php foreach ($templateColumns as $c): ?>
                                <li><code><?= e($c) ?></code></li>
                            <?php endforeach; ?>
                        </ol>
                        <hr>
                        <div class="alert alert-warning mb-0">CSV imports work without extra packages. To import .xlsx/.xls directly, run <code>composer install</code> to install PhpSpreadsheet. You can also save Excel workbooks as <strong>CSV UTF-8</strong>.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
