<?php
session_start();
require '../config/pdo_connect.php';

if (!isset($_SESSION['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['student_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$student_id = (int)$_POST['student_id'];

try {
    // Check if student exists
    $check_stmt = $pdo->prepare("SELECT full_name FROM students WHERE student_id = ?");
    $check_stmt->execute([$student_id]);
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    // Get student name for response
    $student = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Delete student (with foreign key constraints handled by ON DELETE CASCADE)
    $delete_stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
    $delete_stmt->execute([$student_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Student deleted successfully',
        'student_name' => $student['full_name']
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}