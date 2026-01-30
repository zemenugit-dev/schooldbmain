<?php
session_start();
require '../config/pdo_connect.php';

if (!isset($_SESSION['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Fetch updated statistics
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$teachers_count = $pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'active'")->fetchColumn();
$pending_requests = $pdo->query("SELECT COUNT(*) FROM result_correction_requests WHERE status = 'pending'")->fetchColumn();

header('Content-Type: application/json');
echo json_encode([
    'total_students' => $total_students,
    'teachers_count' => $teachers_count,
    'pending_requests' => $pending_requests,
    'updated_at' => date('Y-m-d H:i:s')
]);