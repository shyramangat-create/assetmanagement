<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
if (!$categoryId) {
    echo json_encode([]);
    exit;
}
$st = $pdo->prepare('SELECT id,name FROM sub_categories WHERE category_id=? ORDER BY name');
$st->execute([$categoryId]);
echo json_encode($st->fetchAll());
