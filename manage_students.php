<?php
session_start();
require 'auth.php';
require '../config/pdo_connect.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$action = $_GET['action'] ?? 'list';
$student_id = $_GET['id'] ?? 0;
$success = '';
$error = '';
$validation_errors = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_POST['action'] == 'add' || $_POST['action'] == 'edit') {
        $full_name = trim($_POST['full_name']);
        $reg_number = trim($_POST['reg_number']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $grade_level = trim($_POST['grade_level']);
        $section = trim($_POST['section']);
        
        // Reset validation errors
        $validation_errors = [];
        
        // Client-side validation (also done in JavaScript)
        // 1. Full Name Validation
        if (empty($full_name)) {
            $validation_errors['full_name'] = "Full name is required";
        } elseif (strlen($full_name) < 2 || strlen($full_name) > 100) {
            $validation_errors['full_name'] = "Name must be between 2-100 characters";
        } elseif (!preg_match("/^[a-zA-Z\s.'-]+$/", $full_name)) {
            $validation_errors['full_name'] = "Name can only contain letters, spaces, dots, hyphens and apostrophes";
        }
        
        // 2. Registration Number Validation
        if (empty($reg_number)) {
            $validation_errors['reg_number'] = "Registration number is required";
        } elseif (strlen($reg_number) < 3 || strlen($reg_number) > 50) {
            $validation_errors['reg_number'] = "Registration number must be between 3-50 characters";
        } elseif (!preg_match("/^[A-Za-z0-9-]+$/", $reg_number)) {
            $validation_errors['reg_number'] = "Registration number can only contain letters, numbers, and hyphens";
        }
        
        // 3. Email Validation (if provided)
        if (!empty($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validation_errors['email'] = "Invalid email format";
            } elseif (strlen($email) > 100) {
                $validation_errors['email'] = "Email must be less than 100 characters";
            }
        }
        
        // 4. Password Validation (for add or when changing password)
        if ($_POST['action'] == 'add') {
            if (empty($password)) {
                $validation_errors['password'] = "Password is required";
            } elseif (strlen($password) < 6) {
                $validation_errors['password'] = "Password must be at least 6 characters";
            } elseif (!preg_match("/[A-Z]/", $password)) {
                $validation_errors['password'] = "Password must contain at least one uppercase letter";
            } elseif (!preg_match("/[a-z]/", $password)) {
                $validation_errors['password'] = "Password must contain at least one lowercase letter";
            } elseif (!preg_match("/[0-9]/", $password)) {
                $validation_errors['password'] = "Password must contain at least one number";
            }
        } elseif (!empty($password) && strlen($password) < 6) {
            // For edit, password is optional but if provided, must be valid
            $validation_errors['password'] = "Password must be at least 6 characters";
        }
        
        // 5. Grade Level Validation (if provided)
        if (!empty($grade_level) && !preg_match("/^Grade [6-9]|1[0-2]$/", $grade_level)) {
            $validation_errors['grade_level'] = "Invalid grade level selected";
        }
        
        // 6. Section Validation (if provided)
        if (!empty($section) && !in_array($section, ['A', 'B', 'C', 'D', 'E'])) {
            $validation_errors['section'] = "Invalid section selected";
        }
        
        // If no validation errors, proceed with database operations
        if (empty($validation_errors)) {
            try {
                if ($_POST['action'] == 'add') {
                    // Check if reg number or email already exists
                    $check = $pdo->prepare("SELECT student_id FROM students WHERE reg_number = ? OR (email = ? AND email != '')");
                    $check->execute([$reg_number, $email]);
                    
                    if ($check->rowCount() > 0) {
                        $existing = $check->fetch(PDO::FETCH_ASSOC);
                        $check2 = $pdo->prepare("SELECT reg_number, email FROM students WHERE student_id = ?");
                        $check2->execute([$existing['student_id']]);
                        $existing_data = $check2->fetch(PDO::FETCH_ASSOC);
                        
                        if ($existing_data['reg_number'] == $reg_number) {
                            $validation_errors['reg_number'] = "Registration number already exists";
                        } elseif (!empty($email) && $existing_data['email'] == $email) {
                            $validation_errors['email'] = "Email already exists";
                        }
                    }
                    
                    if (empty($validation_errors)) {
                        // Add new student
                        $stmt = $pdo->prepare("
                            INSERT INTO students (full_name, reg_number, email, password, grade_level, section) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt->execute([$full_name, $reg_number, $email, $hashed_password, $grade_level, $section]);
                        $success = "Student added successfully!";
                        $action = 'list'; // Redirect to list after successful addition
                    }
                } else {
                    // Update existing student
                    $student_id = (int)$_POST['student_id'];
                    
                    // Check for duplicate reg number or email (excluding current student)
                    $check = $pdo->prepare("SELECT student_id FROM students WHERE (reg_number = ? OR (email = ? AND email != '')) AND student_id != ?");
                    $check->execute([$reg_number, $email, $student_id]);
                    
                    if ($check->rowCount() > 0) {
                        $existing = $check->fetch(PDO::FETCH_ASSOC);
                        $check2 = $pdo->prepare("SELECT reg_number, email FROM students WHERE student_id = ?");
                        $check2->execute([$existing['student_id']]);
                        $existing_data = $check2->fetch(PDO::FETCH_ASSOC);
                        
                        if ($existing_data['reg_number'] == $reg_number) {
                            $validation_errors['reg_number'] = "Registration number already exists";
                        } elseif (!empty($email) && $existing_data['email'] == $email) {
                            $validation_errors['email'] = "Email already exists";
                        }
                    }
                    
                    if (empty($validation_errors)) {
                        // If password is provided, update it
                        if (!empty($password)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("
                                UPDATE students 
                                SET full_name = ?, reg_number = ?, email = ?, password = ?, grade_level = ?, section = ? 
                                WHERE student_id = ?
                            ");
                            $stmt->execute([$full_name, $reg_number, $email, $hashed_password, $grade_level, $section, $student_id]);
                        } else {
                            $stmt = $pdo->prepare("
                                UPDATE students 
                                SET full_name = ?, reg_number = ?, email = ?, grade_level = ?, section = ? 
                                WHERE student_id = ?
                            ");
                            $stmt->execute([$full_name, $reg_number, $email, $grade_level, $section, $student_id]);
                        }
                        
                        $success = "Student updated successfully!";
                        $action = 'list'; // Redirect to list after successful update
                    }
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
                error_log("Student Management Error: " . $e->getMessage());
            }
        }
    }
}

// Fetch student data for editing
$student = null;
if ($action == 'edit' && $student_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        $error = "Student not found!";
        $action = 'list';
    }
}

// Fetch all students for listing
if ($action == 'list') {
    $students = $pdo->query("SELECT * FROM students ORDER BY grade_level, section, full_name")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Students</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #10b981;
    --secondary: #34d399;
    --dark-green: #059669;
    --light-green: #d1fae5;
    --text-dark: #1f2937;
    --text-light: #6b7280;
    --error-red: #ef4444;
    --warning-yellow: #f59e0b;
}

body {
    background: linear-gradient(135deg, #10b981 0%, #34d399 50%, #d1fae5 100%);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    min-height: 100vh;
    padding-top: 20px;
}

.container-fluid {
    max-width: 1400px;
}

/* Header Styling */
.header-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 15px 35px rgba(16, 185, 129, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.header-title {
    color: var(--dark-green);
    font-weight: 800;
    margin-bottom: 10px;
}

.header-subtitle {
    color: var(--text-light);
    font-size: 1.1rem;
}

/* Card Styling */
.card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(16, 185, 129, 0.15);
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    overflow: hidden;
}

.card:hover {
    transform: translateY(-10px);
    box-shadow: 0 25px 50px rgba(16, 185, 129, 0.25);
}

.card-header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border-radius: 20px 20px 0 0 !important;
    padding: 25px;
    border: none;
}

/* Form Validation Styling */
.form-control.is-invalid, .form-select.is-invalid {
    border-color: var(--error-red) !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc2626'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc2626' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.form-control.is-valid, .form-select.is-valid {
    border-color: var(--primary) !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2310b981' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.invalid-feedback {
    display: block;
    color: var(--error-red);
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

.valid-feedback {
    display: block;
    color: var(--primary);
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

/* Password strength indicator */
.password-strength {
    height: 5px;
    border-radius: 3px;
    margin-top: 5px;
    background: #e5e7eb;
    overflow: hidden;
}

.strength-weak { width: 25%; background: var(--error-red); }
.strength-fair { width: 50%; background: var(--warning-yellow); }
.strength-good { width: 75%; background: #3b82f6; }
.strength-strong { width: 100%; background: var(--primary); }

.password-requirements {
    font-size: 0.85rem;
    color: var(--text-light);
    margin-top: 5px;
}

.requirement {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 2px;
}

.requirement i {
    font-size: 0.8rem;
}

.requirement.met {
    color: var(--primary);
}

.requirement.unmet {
    color: var(--text-light);
}

/* Button Styling */
.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none;
    padding: 12px 30px;
    font-weight: 600;
    border-radius: 12px;
    transition: all 0.3s;
    box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
    background: linear-gradient(135deg, var(--dark-green), var(--primary));
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-outline-primary {
    color: var(--primary);
    border: 2px solid var(--primary);
    font-weight: 600;
    border-radius: 12px;
    transition: all 0.3s;
}

.btn-outline-primary:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
}

.btn-outline-secondary {
    border-radius: 12px;
    font-weight: 600;
}

.btn-success {
    background: linear-gradient(135deg, #059669, #10b981);
    border: none;
    border-radius: 12px;
    font-weight: 600;
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444, #f87171);
    border: none;
    border-radius: 12px;
    font-weight: 600;
}

/* Form Styling */
.form-control, .form-select {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px 20px;
    font-size: 1rem;
    transition: all 0.3s;
    background: rgba(255, 255, 255, 0.9);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    background: white;
}

.form-label {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.required::after {
    content: " *";
    color: var(--error-red);
}

/* Table Styling */
.table {
    border-radius: 15px;
    overflow: hidden;
    margin-bottom: 0;
}

.table thead {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
}

.table th {
    border: none;
    padding: 18px 20px;
    font-weight: 600;
    font-size: 0.95rem;
}

.table td {
    padding: 16px 20px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    color: var(--text-dark);
}

.table tbody tr {
    transition: all 0.3s;
}

.table tbody tr:hover {
    background: rgba(209, 250, 229, 0.3);
    transform: translateX(5px);
}

/* Badge Styling */
.badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
}

.bg-info {
    background: linear-gradient(135deg, #0ea5e9, #38bdf8) !important;
}

.bg-secondary {
    background: linear-gradient(135deg, #6b7280, #9ca3af) !important;
}

/* Action Buttons */
.btn-sm {
    padding: 8px 15px;
    border-radius: 10px;
    font-size: 0.9rem;
}

.btn-outline-info {
    color: #0ea5e9;
    border: 2px solid #0ea5e9;
}

.btn-outline-warning {
    color: #f59e0b;
    border: 2px solid #f59e0b;
}

.btn-outline-danger {
    color: #ef4444;
    border: 2px solid #ef4444;
}

/* Alert Styling */
.alert {
    border-radius: 15px;
    border: none;
    padding: 20px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(52, 211, 153, 0.15));
    color: var(--dark-green);
    border-left: 5px solid var(--primary);
}

.alert-danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(248, 113, 113, 0.15));
    color: #dc2626;
    border-left: 5px solid #ef4444;
}

.alert-warning {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(251, 191, 36, 0.15));
    color: #b45309;
    border-left: 5px solid #f59e0b;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-light);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.3;
}

/* Responsive Design */
@media (max-width: 768px) {
    .header-section {
        padding: 20px;
        text-align: center;
    }
    
    .header-section .d-flex {
        flex-direction: column;
        gap: 15px;
    }
    
    .table-responsive {
        border-radius: 15px;
    }
    
    .card {
        margin: 10px;
    }
}

@media (max-width: 576px) {
    .container-fluid {
        padding: 10px;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .d-flex.gap-3 {
        flex-direction: column;
    }
    
    .table th, .table td {
        padding: 12px 15px;
    }
}

/* Animation for success message */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert {
    animation: slideIn 0.5s ease;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 10px;
}

::-webkit-scrollbar-track {
    background: rgba(209, 250, 229, 0.3);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, var(--dark-green), var(--primary));
}

/* Loading animation */
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
</head>
<body>
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="header-section">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="header-title">
                    <?php if($action=='add'): ?>
                        <i class="fas fa-user-plus me-3"></i>Add New Student
                    <?php elseif($action=='edit'): ?>
                        <i class="fas fa-user-edit me-3"></i>Edit Student
                    <?php else: ?>
                        <i class="fas fa-users me-3"></i>Manage Students
                    <?php endif; ?>
                </h1>
                <p class="header-subtitle">
                    <?php if($action=='add'): ?>
                        Add a new student to the system
                    <?php elseif($action=='edit'): ?>
                        Edit student information
                    <?php else: ?>
                        View and manage all students in the school
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-flex flex-wrap gap-3">
                <?php if($action=='list'): ?>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Add New Student
                    </a>
                <?php else: ?>
                    <a href="manage_students.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to List
                    </a>
                <?php endif; ?>
                <a href="studentinfo.php" class="btn btn-outline-primary">
                    <i class="fas fa-layer-group me-2"></i>View by Grade
                </a>
                <a href="dashboard.php" class="btn btn-outline-success">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Success:</strong> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <script>
                setTimeout(() => {
                    window.location.href = 'manage_students.php';
                }, 1500);
            </script>
        </div>
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <?php if($action=='add'||$action=='edit'): ?>
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-user-circle me-2"></i>
                            <?= $action=='add'?'Student Registration Form':'Edit Student Information' ?>
                        </h4>
                    </div>
                    <div class="card-body p-5">
                        <form method="POST" id="studentForm" novalidate>
                            <input type="hidden" name="action" value="<?= $action ?>">
                            <?php if($action=='edit'): ?>
                                <input type="hidden" name="student_id" value="<?= $student['student_id'] ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <!-- Full Name -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label required">
                                        <i class="fas fa-user me-2 text-primary"></i>Full Name
                                    </label>
                                    <input type="text" name="full_name" id="full_name" class="form-control <?= isset($validation_errors['full_name']) ? 'is-invalid' : (isset($_POST['full_name']) ? 'is-valid' : '') ?>" 
                                           value="<?= htmlspecialchars($student['full_name'] ?? ($_POST['full_name'] ?? '')) ?>" 
                                           placeholder="Enter student's full name" required
                                           minlength="2" maxlength="100">
                                    <div class="invalid-feedback" id="full_name_error">
                                        <?= $validation_errors['full_name'] ?? '' ?>
                                    </div>
                                    <div class="valid-feedback">Looks good!</div>
                                    <small class="text-muted">Enter the complete name of the student (2-100 characters)</small>
                                </div>
                                
                                <!-- Registration Number -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label required">
                                        <i class="fas fa-id-card me-2 text-primary"></i>Registration Number
                                    </label>
                                    <input type="text" name="reg_number" id="reg_number" class="form-control <?= isset($validation_errors['reg_number']) ? 'is-invalid' : (isset($_POST['reg_number']) ? 'is-valid' : '') ?>" 
                                           value="<?= htmlspecialchars($student['reg_number'] ?? ($_POST['reg_number'] ?? '')) ?>" 
                                           placeholder="Enter registration number" required
                                           minlength="3" maxlength="50">
                                    <div class="invalid-feedback" id="reg_number_error">
                                        <?= $validation_errors['reg_number'] ?? '' ?>
                                    </div>
                                    <div class="valid-feedback">Looks good!</div>
                                    <small class="text-muted">Unique identification number (3-50 characters, letters/numbers/hyphens only)</small>
                                </div>
                                
                                <!-- Email Address -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-envelope me-2 text-primary"></i>Email Address
                                    </label>
                                    <input type="email" name="email" id="email" class="form-control <?= isset($validation_errors['email']) ? 'is-invalid' : (isset($_POST['email']) && !empty($_POST['email']) ? 'is-valid' : '') ?>" 
                                           value="<?= htmlspecialchars($student['email'] ?? ($_POST['email'] ?? '')) ?>" 
                                           placeholder="student@example.com"
                                           maxlength="100">
                                    <div class="invalid-feedback" id="email_error">
                                        <?= $validation_errors['email'] ?? '' ?>
                                    </div>
                                    <?php if(isset($_POST['email']) && !empty($_POST['email']) && !isset($validation_errors['email'])): ?>
                                        <div class="valid-feedback">Valid email format</div>
                                    <?php endif; ?>
                                    <small class="text-muted">Optional - for communication</small>
                                </div>
                                
                                <!-- Password -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label <?= $action=='add'?'required':'' ?>">
                                        <i class="fas fa-lock me-2 text-primary"></i>
                                        Password <?= $action=='add'?'':'(leave blank to keep current)' ?>
                                    </label>
                                    <input type="password" name="password" id="password" class="form-control <?= isset($validation_errors['password']) ? 'is-invalid' : '' ?>" 
                                           <?= $action=='add'?'required':'' ?>
                                           placeholder="<?= $action=='add'?'Enter password':'Leave blank to keep current' ?>"
                                           minlength="6"
                                           oninput="checkPasswordStrength(this.value)">
                                    <div class="invalid-feedback" id="password_error">
                                        <?= $validation_errors['password'] ?? '' ?>
                                    </div>
                                    
                                    <!-- Password Strength Indicator -->
                                    <div class="password-strength mt-2">
                                        <div id="passwordStrength" class="strength-weak"></div>
                                    </div>
                                    
                                    <!-- Password Requirements -->
                                    <div class="password-requirements mt-2">
                                        <div class="requirement <?= $action=='add'?'unmet':'met' ?>" id="reqLength">
                                            <i class="fas fa-circle" id="reqLengthIcon"></i>
                                            <span>At least 6 characters</span>
                                        </div>
                                        <div class="requirement unmet" id="reqUppercase">
                                            <i class="fas fa-circle" id="reqUppercaseIcon"></i>
                                            <span>At least one uppercase letter</span>
                                        </div>
                                        <div class="requirement unmet" id="reqLowercase">
                                            <i class="fas fa-circle" id="reqLowercaseIcon"></i>
                                            <span>At least one lowercase letter</span>
                                        </div>
                                        <div class="requirement unmet" id="reqNumber">
                                            <i class="fas fa-circle" id="reqNumberIcon"></i>
                                            <span>At least one number</span>
                                        </div>
                                    </div>
                                    
                                    <small class="text-muted d-block mt-1">
                                        <?= $action=='add'?'Enter a strong password':'Enter new password to change' ?>
                                    </small>
                                </div>
                                
                                <!-- Grade Level -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-graduation-cap me-2 text-primary"></i>Grade Level
                                    </label>
                                    <select name="grade_level" id="grade_level" class="form-select <?= isset($validation_errors['grade_level']) ? 'is-invalid' : (isset($_POST['grade_level']) ? 'is-valid' : '') ?>">
                                        <option value="">Select Grade Level</option>
                                        <?php for($i=6;$i<=12;$i++): ?>
                                            <option value="Grade <?= $i ?>" 
                                                <?= (($student['grade_level']??($_POST['grade_level']??''))=="Grade $i")?'selected':'' ?>>
                                                Grade <?= $i ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="invalid-feedback" id="grade_level_error">
                                        <?= $validation_errors['grade_level'] ?? '' ?>
                                    </div>
                                    <div class="valid-feedback">Selected</div>
                                    <small class="text-muted">Select the appropriate grade level (optional)</small>
                                </div>
                                
                                <!-- Section -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-users me-2 text-primary"></i>Section
                                    </label>
                                    <select name="section" id="section" class="form-select <?= isset($validation_errors['section']) ? 'is-invalid' : (isset($_POST['section']) ? 'is-valid' : '') ?>">
                                        <option value="">Select Section</option>
                                        <?php foreach(['A','B','C','D','E'] as $sec): ?>
                                            <option value="<?= $sec ?>" 
                                                <?= (($student['section']??($_POST['section']??''))==$sec)?'selected':'' ?>>
                                                Section <?= $sec ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback" id="section_error">
                                        <?= $validation_errors['section'] ?? '' ?>
                                    </div>
                                    <div class="valid-feedback">Selected</div>
                                    <small class="text-muted">Select the class section (optional)</small>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-3 mt-5 pt-3">
                                <button type="submit" class="btn btn-primary btn-lg flex-grow-1 py-3" id="submitBtn">
                                    <i class="fas fa-save me-2"></i>
                                    <?= $action=='add'?'Register Student':'Update Student' ?>
                                </button>
                                <a href="manage_students.php" class="btn btn-outline-secondary btn-lg py-3">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    
    <!-- Student List -->
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-list me-2"></i>All Students (<?= count($students) ?>)
                    </h4>
                    <div class="d-flex gap-2">
                        <input type="text" id="searchInput" class="form-control" placeholder="Search students..." 
                               style="width: 250px; border-radius: 10px;">
                        <button class="btn btn-success" onclick="exportStudents()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if(empty($students)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <h4 class="mb-3">No Students Found</h4>
                        <p class="mb-4">Start by adding your first student to the system</p>
                        <a href="?action=add" class="btn btn-primary btn-lg">
                            <i class="fas fa-user-plus me-2"></i>Add First Student
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="20%">Student Name</th>
                                    <th width="15%">Registration No</th>
                                    <th width="20%">Email</th>
                                    <th width="20%">Grade & Section</th>
                                    <th width="10%">Joined</th>
                                    <th width="10%">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="studentTable">
                                <?php foreach($students as $index=>$stu): ?>
                                    <tr>
                                        <td class="fw-bold"><?= $index+1 ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px; margin-right: 12px; font-weight: bold;">
                                                    <?= substr($stu['full_name'], 0, 1) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($stu['full_name']) ?></div>
                                                    <small class="text-muted">ID: #<?= $stu['student_id'] ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark"><?= htmlspecialchars($stu['reg_number']) ?></span>
                                        </td>
                                        <td>
                                            <?php if($stu['email']): ?>
                                                <a href="mailto:<?= htmlspecialchars($stu['email']) ?>" class="text-decoration-none">
                                                    <i class="fas fa-envelope me-2 text-primary"></i>
                                                    <?= htmlspecialchars($stu['email']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($stu['grade_level']): ?>
                                                <div class="d-flex gap-2">
                                                    <span class="badge bg-info"><?= $stu['grade_level'] ?></span>
                                                    <?php if($stu['section']): ?>
                                                        <span class="badge bg-secondary">Section <?= $stu['section'] ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= date('M d, Y', strtotime($stu['created_at'])) ?>
                                            <br>
                                            <small class="text-muted"><?= date('h:i A', strtotime($stu['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="view_student.php?id=<?= $stu['student_id'] ?>" 
                                                   class="btn btn-sm btn-outline-info" 
                                                   title="View Details" data-bs-toggle="tooltip">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?action=edit&id=<?= $stu['student_id'] ?>" 
                                                   class="btn btn-sm btn-outline-warning" 
                                                   title="Edit" data-bs-toggle="tooltip">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="deleteStudent(<?= $stu['student_id'] ?>, '<?= htmlspecialchars(addslashes($stu['full_name'])) ?>')" 
                                                        class="btn btn-sm btn-outline-danger" 
                                                        title="Delete" data-bs-toggle="tooltip">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php if(!empty($students)): ?>
                <div class="card-footer bg-transparent border-top-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted">
                            Showing <?= count($students) ?> student<?= count($students) != 1 ? 's' : '' ?>
                        </div>
                        <div>
                            <small class="text-muted">
                                Last updated: <?= date('F j, Y h:i A') ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#studentTable tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Delete student function
function deleteStudent(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"?\nThis action cannot be undone and will remove all associated data.`)) {
        $.ajax({
            url: 'delete_student.php',
            method: 'POST',
            data: {
                student_id: id,
                _token: '<?= md5(session_id()) ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(`Student "${response.student_name}" deleted successfully!`);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error deleting student. Please try again.');
            }
        });
    }
}

// Export students function
function exportStudents() {
    let csv = 'ID,Full Name,Registration Number,Email,Grade Level,Section,Created At\n';
    
    <?php foreach($students as $stu): ?>
        csv += `<?= $stu['student_id'] ?>,"<?= addslashes($stu['full_name']) ?>","<?= $stu['reg_number'] ?>","<?= $stu['email'] ?>","<?= $stu['grade_level'] ?>","<?= $stu['section'] ?>","<?= $stu['created_at'] ?>"\n`;
    <?php endforeach; ?>
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `students_export_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Auto-hide alerts after 5 seconds
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+F to focus search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        document.getElementById('searchInput').focus();
    }
    
    // Escape to clear search
    if (e.key === 'Escape') {
        document.getElementById('searchInput').value = '';
        document.getElementById('searchInput').dispatchEvent(new Event('keyup'));
    }
    
    // Ctrl+N to add new student (only on list page)
    if (e.ctrlKey && e.key === 'n' && window.location.href.indexOf('action=') === -1) {
        e.preventDefault();
        window.location.href = '?action=add';
    }
});

// ==================== FORM VALIDATION FUNCTIONS ====================

// Password strength checker
function checkPasswordStrength(password) {
    let strength = 0;
    
    // Length check
    if (password.length >= 6) strength++;
    // Uppercase check
    if (/[A-Z]/.test(password)) strength++;
    // Lowercase check
    if (/[a-z]/.test(password)) strength++;
    // Number check
    if (/[0-9]/.test(password)) strength++;
    
    // Update strength indicator
    const strengthBar = document.getElementById('passwordStrength');
    const strengthClasses = ['strength-weak', 'strength-fair', 'strength-good', 'strength-strong'];
    strengthBar.className = strengthClasses[strength - 1] || 'strength-weak';
    
    // Update requirement indicators
    updateRequirement('reqLength', password.length >= 6, 'fa-check-circle', 'fa-circle');
    updateRequirement('reqUppercase', /[A-Z]/.test(password), 'fa-check-circle', 'fa-circle');
    updateRequirement('reqLowercase', /[a-z]/.test(password), 'fa-check-circle', 'fa-circle');
    updateRequirement('reqNumber', /[0-9]/.test(password), 'fa-check-circle', 'fa-circle');
}

function updateRequirement(id, condition, metIcon, unmetIcon) {
    const element = document.getElementById(id);
    const icon = document.getElementById(id + 'Icon');
    
    if (condition) {
        element.classList.remove('unmet');
        element.classList.add('met');
        icon.className = 'fas ' + metIcon;
    } else {
        element.classList.remove('met');
        element.classList.add('unmet');
        icon.className = 'fas ' + unmetIcon;
    }
}

// Real-time form validation
function validateField(fieldId, validationFunction) {
    const field = document.getElementById(fieldId);
    const errorElement = document.getElementById(fieldId + '_error');
    
    field.addEventListener('input', function() {
        const value = this.value.trim();
        const error = validationFunction(value);
        
        if (error) {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
            errorElement.textContent = error;
        } else {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            errorElement.textContent = '';
        }
        validateForm();
    });
    
    field.addEventListener('blur', function() {
        const value = this.value.trim();
        const error = validationFunction(value);
        
        if (error) {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
            errorElement.textContent = error;
        } else if (value !== '') {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            errorElement.textContent = '';
        }
        validateForm();
    });
}

// Validation functions
function validateFullName(name) {
    if (!name) return "Full name is required";
    if (name.length < 2 || name.length > 100) return "Name must be between 2-100 characters";
    if (!/^[a-zA-Z\s.'-]+$/.test(name)) return "Name can only contain letters, spaces, dots, hyphens and apostrophes";
    return '';
}

function validateRegNumber(reg) {
    if (!reg) return "Registration number is required";
    if (reg.length < 3 || reg.length > 50) return "Registration number must be between 3-50 characters";
    if (!/^[A-Za-z0-9-]+$/.test(reg)) return "Registration number can only contain letters, numbers, and hyphens";
    return '';
}

function validateEmail(email) {
    if (email === '') return '';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return "Invalid email format";
    if (email.length > 100) return "Email must be less than 100 characters";
    return '';
}

function validatePassword(password) {
    const action = '<?= $action ?>';
    if (action === 'add') {
        if (!password) return "Password is required";
        if (password.length < 6) return "Password must be at least 6 characters";
        if (!/[A-Z]/.test(password)) return "Password must contain at least one uppercase letter";
        if (!/[a-z]/.test(password)) return "Password must contain at least one lowercase letter";
        if (!/[0-9]/.test(password)) return "Password must contain at least one number";
    } else if (password && password.length < 6) {
        return "Password must be at least 6 characters";
    }
    return '';
}

function validateGradeLevel(grade) {
    if (grade === '') return '';
    if (!/^Grade [6-9]|1[0-2]$/.test(grade)) return "Invalid grade level selected";
    return '';
}

function validateSection(section) {
    if (section === '') return '';
    if (!['A', 'B', 'C', 'D', 'E'].includes(section)) return "Invalid section selected";
    return '';
}

// Initialize field validations if we're on add/edit form
<?php if($action=='add'||$action=='edit'): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize validation for each field
    validateField('full_name', validateFullName);
    validateField('reg_number', validateRegNumber);
    validateField('email', validateEmail);
    validateField('password', validatePassword);
    validateField('grade_level', validateGradeLevel);
    validateField('section', validateSection);
    
    // Special handling for password field
    const passwordField = document.getElementById('password');
    if (passwordField) {
        passwordField.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            const error = validatePassword(this.value);
            const errorElement = document.getElementById('password_error');
            
            if (error) {
                passwordField.classList.remove('is-valid');
                passwordField.classList.add('is-invalid');
                errorElement.textContent = error;
            } else if (this.value !== '') {
                passwordField.classList.remove('is-invalid');
                passwordField.classList.add('is-valid');
                errorElement.textContent = '';
            } else {
                passwordField.classList.remove('is-invalid');
                passwordField.classList.remove('is-valid');
                errorElement.textContent = '';
            }
            validateForm();
        });
    }
    
    // Form submission validation
    document.getElementById('studentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate all fields
        const fields = [
            {id: 'full_name', validator: validateFullName},
            {id: 'reg_number', validator: validateRegNumber},
            {id: 'email', validator: validateEmail},
            {id: 'password', validator: validatePassword},
            {id: 'grade_level', validator: validateGradeLevel},
            {id: 'section', validator: validateSection}
        ];
        
        let isValid = true;
        fields.forEach(field => {
            const element = document.getElementById(field.id);
            if (element) {
                const value = element.value.trim();
                const error = field.validator(value);
                const errorElement = document.getElementById(field.id + '_error');
                
                if (error) {
                    element.classList.remove('is-valid');
                    element.classList.add('is-invalid');
                    errorElement.textContent = error;
                    isValid = false;
                } else if (value !== '' || field.id === 'full_name' || field.id === 'reg_number' || (field.id === 'password' && '<?= $action ?>' === 'add')) {
                    element.classList.remove('is-invalid');
                    element.classList.add('is-valid');
                    errorElement.textContent = '';
                }
            }
        });
        
        if (isValid) {
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="loading"></span> Processing...';
            submitBtn.disabled = true;
            
            // Submit the form
            this.submit();
        } else {
            // Scroll to first error
            const firstError = document.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
            
            // Show alert
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-warning alert-dismissible fade show mt-3';
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Please fix the errors below:</strong> Some fields require your attention.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.card-body').insertBefore(alertDiv, document.querySelector('.card-body').firstChild);
            
            // Auto-remove alert after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    });
    
    // Validate form on load to show initial errors
    validateForm();
});

// Overall form validation
function validateForm() {
    const submitBtn = document.getElementById('submitBtn');
    if (!submitBtn) return;
    
    const requiredFields = ['full_name', 'reg_number'];
    if ('<?= $action ?>' === 'add') {
        requiredFields.push('password');
    }
    
    let isValid = true;
    requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            const value = field.value.trim();
            let error = '';
            
            if (fieldId === 'full_name') error = validateFullName(value);
            else if (fieldId === 'reg_number') error = validateRegNumber(value);
            else if (fieldId === 'password') error = validatePassword(value);
            
            if (error) {
                isValid = false;
            }
        }
    });
    
    submitBtn.disabled = !isValid;
}
<?php endif; ?>
</script>
</body>
</html>