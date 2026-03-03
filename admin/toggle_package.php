<?php
// ============================================================
//  admin/toggle_package.php  — AJAX: toggle package availability
//  Place in: admin/toggle_package.php
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$id           = (int)($_POST['id'] ?? 0);
$isAvailable  = (int)($_POST['is_available'] ?? 0); // 1 = available, 0 = full

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid package ID.']);
    exit;
}

try {
    $pdo  = getDB();
    $pdo->prepare("UPDATE packages SET is_available = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$isAvailable, $id]);

    $label = $isAvailable ? 'Available' : 'Full';
    logActivity("Toggled package #$id availability to: $label", 'package', $id);

    echo json_encode(['success' => true, 'is_available' => $isAvailable]);
} catch (PDOException $e) {
    error_log('Toggle package error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
exit;
