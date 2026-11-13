<?php
declare(strict_types=1);

function e(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never {
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

function flash(string $key, ?string $message = null): ?string {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function validDate(string $date): bool {
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

function normalizeImportHeader(string $header): string {
    $header = strtolower(trim($header));
    $header = preg_replace('/[\s_]+/', '_', $header);
    return $header === 'condition' ? 'condition_status' : $header;
}

function normalizeImportDate(string $date): string {
    $date = trim($date);
    if (validDate($date))
        return $date;
    if (preg_match('/^([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{4})$/D', $date, $parts)) {
        $month = (int) $parts[1];
        $day = (int) $parts[2];
        $year = (int) $parts[3];
        if ($year >= 1000 && checkdate($month, $day, $year))
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
    throw new InvalidArgumentException('Purchase date must be a valid date in MM/DD/YYYY or YYYY-MM-DD format.');
}

function postString(string $key, int $max = 255, bool $required = false): string {
    $raw = $_POST[$key] ?? '';
    if (!is_scalar($raw))
        throw new InvalidArgumentException('Invalid input for ' . str_replace('_', ' ', $key) . '.');
    $v = trim((string) $raw);
    if ($required && $v === '')
        throw new InvalidArgumentException(ucwords(str_replace('_', ' ', $key)) . ' is required.');
    $length = function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
    if ($length > $max)
        throw new InvalidArgumentException(ucwords(str_replace('_', ' ', $key)) . " is too long.");
    return $v;
}

function postInt(string $key, int $min = 0, int $max = PHP_INT_MAX): int {
    $raw = $_POST[$key] ?? $min;
    $v = false;
    if (is_string($raw) || is_int($raw)) {
        $raw = trim((string) $raw);
        // HTML number inputs can submit leading zeros; interpret these as decimal.
        if (preg_match('/^[+-]?[0-9]+$/D', $raw)) {
            $raw = preg_replace('/^([+-]?)0+(?=[0-9])/', '$1', $raw);
            $v = filter_var($raw, FILTER_VALIDATE_INT);
        }
    }
    if ($v === false || $v < $min || $v > $max)
        throw new InvalidArgumentException('Invalid value for ' . str_replace('_', ' ', $key) . '.');
    return (int) $v;
}

function postMoney(string $key, float $min = 0, float $max = 9999999999): float {
    $raw = $_POST[$key] ?? '0';
    if (!is_numeric($raw))
        throw new InvalidArgumentException('Invalid amount for ' . str_replace('_', ' ', $key) . '.');
    $v = (float) $raw;
    if (!is_finite($v) || $v < $min || $v > $max)
        throw new InvalidArgumentException('Invalid amount for ' . str_replace('_', ' ', $key) . '.');
    return round($v, 2);
}

function logActivity(PDO $pdo, ?int $assetId, string $type, string $description): void {
    $allowed = ['Purchase', 'Assignment', 'Return', 'Transfer', 'Maintenance', 'Disposal', 'Edit'];
    if (!in_array($type, $allowed, true))
        throw new InvalidArgumentException('Invalid activity type.');
    $st = $pdo->prepare('INSERT INTO activity_log(asset_id,activity_type,description,performed_by) VALUES(?,?,?,\'Admin\')');
    $st->execute([$assetId, $type, $description]);
}

function calculateWarrantyExpiry(string $purchaseDate, int $months): ?string {
    if (!validDate($purchaseDate) || $months < 0)
        return null;
    $d = new DateTime($purchaseDate);
    $d->modify('+' . $months . ' months');
    return $d->format('Y-m-d');
}

function calculateDepreciation(array $a, ?DateTimeImmutable $asOf = null): array {
    $cost = (float) $a['purchase_cost'];
    $salvage = min($cost, (float) $a['salvage_value']);
    $life = (float) $a['useful_life_years'];
    $asOf ??= new DateTimeImmutable('today');
    $purchase = new DateTimeImmutable($a['purchase_date']);
    $days = max(0, (int) $purchase->diff($asOf)->format('%r%a'));
    if (empty($a['depreciation_enabled']) || $life <= 0 || $cost <= $salvage)
        return ['current_value' => $cost, 'annual_depreciation' => 0.0, 'accumulated_depreciation' => 0.0];
    $acc = 0.0;
    $annual = 0.0;
    if (($a['depreciation_method'] ?? '') === 'Straight Line') {
        $annual = ($cost - $salvage) / $life;
        $acc = min($cost - $salvage, $annual * ($days / 365.25));
    } else {
        $rate = max(0.0, min(1.0, (float) ($a['wdv_rate'] ?? 20) / 100));
        $value = $cost;
        $remaining = (float) $days;
        while ($remaining > 0.0001 && $value > $salvage) {
            $periodDays = min(365.25, $remaining);
            $dep = min($value - $salvage, $value * $rate * ($periodDays / 365.25));
            $value -= $dep;
            $acc += $dep;
            $remaining -= $periodDays;
        }
        $annual = $cost * $rate;
    }
    return [
        'current_value' => max($salvage, $cost - $acc),
        'annual_depreciation' => $annual,
        'accumulated_depreciation' => $acc
    ];
}

function fetchOptions(PDO $pdo, string $table): array {
    $allowed = ['categories', 'brands', 'vendors', 'departments', 'locations'];
    if (!in_array($table, $allowed, true))
        return [];
    return $pdo->query("SELECT id,name FROM {$table} ORDER BY name")->fetchAll();
}

function addLookup(PDO $pdo, string $table, string $name, ?int $parentId = null): int {
    $allowed = ['categories', 'brands', 'departments', 'locations', 'sub_categories'];
    if (!in_array($table, $allowed, true))
        throw new InvalidArgumentException('Invalid lookup type.');
    if ($table === 'sub_categories') {
        $st = $pdo->prepare('SELECT id FROM sub_categories WHERE category_id=? AND name=?');
        $st->execute([$parentId, $name]);
    } else {
        $st = $pdo->prepare("SELECT id FROM {$table} WHERE name=?");
        $st->execute([$name]);
    }
    $existing = $st->fetchColumn();
    if ($existing)
        return (int) $existing;
    if ($table === 'sub_categories') {
        $st = $pdo->prepare('INSERT INTO sub_categories(category_id,name) VALUES(?,?)');
        $st->execute([$parentId, $name]);
    } else {
        $st = $pdo->prepare("INSERT INTO {$table}(name) VALUES(?)");
        $st->execute([$name]);
    }
    return (int) $pdo->lastInsertId();
}

function uploadAssetPhoto(string $field, ?string $old = null): ?string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE)
        return $old;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK)
        throw new RuntimeException('Photo upload failed.');
    if ($f['size'] > 5 * 1024 * 1024)
        throw new RuntimeException('Photo must be 5 MB or smaller.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($map[$mime]))
        throw new RuntimeException('Only JPG, PNG, and WEBP photos are allowed.');
    $dir = ROOT_PATH . '/assets/uploads/';
    if (!is_dir($dir))
        throw new RuntimeException('Upload folder is missing.');
    $file = bin2hex(random_bytes(16)) . '.' . $map[$mime];
    // On Windows, is_writable() can report false for a directory even when
    // creating files succeeds. Use the actual upload operation as the check.
    if (!move_uploaded_file($f['tmp_name'], $dir . $file))
        throw new RuntimeException('Could not save uploaded photo. Check upload folder permissions.');
    if ($old && is_file($dir . basename($old)))
        @unlink($dir . basename($old));
    return $file;
}

function dateRange(string $preset, string $from = '', string $to = ''): array {
    $today = new DateTimeImmutable('today');
    $start = null;
    $end = $today;
    switch ($preset) {
        case 'today':
            $start = $today;
            break;
        case 'weekly':
            $start = $today->modify('monday this week');
            break;
        case 'monthly':
            $start = $today->modify('first day of this month');
            break;
        case 'quarterly':
            $month = (int) $today->format('n');
            $qMonth = (int) (floor(($month - 1) / 3) * 3 + 1);
            $start = $today->setDate((int) $today->format('Y'), $qMonth, 1);
            break;
        case 'financial_year':
            $y = (int) $today->format('Y');
            $start = $today->setDate(((int) $today->format('n') >= 4 ? $y : $y - 1), 4, 1);
            break;
        case 'last_financial_year':
            $y = (int) $today->format('Y');
            $fy = ((int) $today->format('n') >= 4 ? $y : $y - 1) - 1;
            $start = $today->setDate($fy, 4, 1);
            $end = $today->setDate($fy + 1, 3, 31);
            break;
        case 'custom':
            if ($from && validDate($from))
                $start = new DateTimeImmutable($from);
            if ($to && validDate($to))
                $end = new DateTimeImmutable($to);
            break;
        case 'all':
        default:
            return [null, null];
    }
    if ($start && $end && $start > $end)
        throw new InvalidArgumentException('Start date cannot be after end date.');
    return [$start?->format('Y-m-d'), $end?->format('Y-m-d')];
}

function money(mixed $amount): string {
    return '₱' . number_format((float) $amount, 2);
}

function statusBadge(string $status): string {
    $class = match ($status) {
        'Available' => 'success',
        'Assigned' => 'primary',
        'Under Maintenance' => 'warning',
        'Disposed' => 'secondary',
        'In Progress' => 'warning',
        'Completed' => 'success',
        default => 'secondary'
    };
    return '<span class="badge text-bg-' . $class . '">' . e($status) . '</span>';
}

function selected(mixed $a, mixed $b): string {
    return (string) $a === (string) $b ? ' selected' : '';
}

function requirePost(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
    verifyCsrf();
}

function getInt(string $key): int {
    return max(0, (int) ($_GET[$key] ?? 0));
}
