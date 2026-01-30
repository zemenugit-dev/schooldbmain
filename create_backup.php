<?php
session_start();
require '../config/pdo_connect.php';

if(!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Simple backup creation
header('Content-Type: application/json');

$type = $_POST['type'] ?? 'full';
$includeUsers = isset($_POST['users']) ? $_POST['users'] === 'true' : true;
$includeLogs = isset($_POST['logs']) ? $_POST['logs'] === 'true' : true;
$includeSettings = isset($_POST['settings']) ? $_POST['settings'] === 'true' : true;

try {
    // Create backup directory if it doesn't exist
    $backupDir = '../backups/';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // Generate backup filename
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . $filename;
    
    // In a real application, you would use mysqldump or similar
    // This is a simplified version
    $tables = [];
    
    if ($includeUsers) {
        $tables[] = 'admins';
        $tables[] = 'teachers';
        $tables[] = 'students';
    }
    
    if ($includeLogs) {
        $tables[] = 'audit_log';
    }
    
    if ($includeSettings) {
        $tables[] = 'settings';
    }
    
    // Always include these tables
    $tables = array_merge($tables, ['subjects', 'results', 'assignments', 'attendance']);
    
    // Create backup content (simplified)
    $backupContent = "-- Database Backup\n";
    $backupContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $backupContent .= "-- Type: " . $type . "\n\n";
    
    foreach ($tables as $table) {
        $backupContent .= "-- Table: {$table}\n";
        
        // Get table structure
        $stmt = $pdo->query("SHOW CREATE TABLE {$table}");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
        $backupContent .= $createTable['Create Table'] . ";\n\n";
        
        // Get table data
        $stmt = $pdo->query("SELECT * FROM {$table}");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $values = array_map(function($value) use ($pdo) {
                    return $pdo->quote($value);
                }, array_values($row));
                
                $backupContent .= "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            }
            $backupContent .= "\n";
        }
    }
    
    // Save backup file
    file_put_contents($filepath, $backupContent);
    
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'message' => 'Backup created successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>