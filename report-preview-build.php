<?php
function e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function selected($a, $b) { return $a == $b ? ' selected' : ''; }
function money($value) { return '₱' . number_format((float) $value, 2); }
$type = 'assets'; $title = 'Hardware Asset Report'; $subtitle = 'Purchased items and acquisition costs';
$from = '2026-11-01'; $to = '2026-11-13'; $error = ''; $assetOptions = [];
$headers = ['Date Purchased', 'Asset ID', 'Asset Name', 'Quantity', 'Purchase Cost', 'Status'];
$rows = [
 ['2026-11-12', 'AST-003', 'Office Monitor', '1', money(8500), 'Available'],
 ['2026-11-06', 'AST-002', 'Laser Printer', '1', money(12000), 'Under Maintenance'],
 ['2026-11-03', 'AST-001', 'Office Laptop', '1', money(35000), 'Assigned'],
];
$records = [['purchase_cost' => 8500], ['purchase_cost' => 12000], ['purchase_cost' => 35000]];
$totalRows = count($rows);
$source = file_get_contents(__DIR__ . '/reports/view.php');
$start = strpos($source, '<main class="main-area">');
$end = strpos($source, '<?php include __DIR__', $start);
ob_start();
eval('?>' . substr($source, $start, $end - $start));
$body = ob_get_clean();
$css = file_get_contents(__DIR__ . '/assets/css/style.css');
$css = str_replace('@media print {', '@media all {', $css);
$html = '<!doctype html><html><head><meta charset="utf-8"><title>Asset report — sample preview</title><style>' . $css . 'body{margin:0;padding:30px;background:#e5e7eb;font-family:Arial,sans-serif}.business-report{background:white;box-sizing:border-box;width:210mm;max-width:none!important;min-height:297mm;margin:auto;padding:18mm 15mm!important}.main-area{margin:0!important}.card-header{display:flex;justify-content:space-between}.card-title-sm{margin:0}.table{width:100%}.table td,.table th{text-align:left;line-height:1.5}.preview-note{width:210mm;margin:0 auto 14px;font-size:13px;color:#374151}</style></head><body><p class="preview-note">DESIGN PREVIEW · Sample records only</p>' . $body . '</body></html>';
file_put_contents(__DIR__ . '/report-preview.html', $html);

