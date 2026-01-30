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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_POST['action'] == 'add' || $_POST['action'] == 'edit') {
        $full_name = trim($_POST['full_name']);
        $reg_number = trim($_POST['reg_number']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $grade_level = trim($_POST['grade_level']);
        $section = trim($_POST['section']);
        
        // Validation
        if (empty($full_name) || empty($reg_number)) {
            $error = "Full name and registration number are required!";
        } else {
            try {
                if ($_POST['action'] == 'add') {
                    // Check if reg number or email already exists
                    $check = $pdo->prepare("SELECT student_id FROM students WHERE reg_number = ? OR email = ?");
                    $check->execute([$reg_number, $email]);
                    
                    if ($check->rowCount() > 0) {
                        $error = "Registration number or email already exists!";
                    } else {
                        // Add new student
                        $stmt = $pdo->prepare("
                            INSERT INTO students (full_name, reg_number, email, password, grade_level, section) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $hashed_password = password_hash($password ?: 'student123', PASSWORD_DEFAULT);
                        $stmt->execute([$full_name, $reg_number, $email, $hashed_password, $grade_level, $section]);
                        $success = "Student added successfully!";
                    }
                } else {
                    // Update existing student
                    $student_id = (int)$_POST['student_id'];
                    $update_data = [
                        'full_name' => $full_name,
                        'reg_number' => $reg_number,
                        'email' => $email,
                        'grade_level' => $grade_level,
                        'section' => $section,
                        'student_id' => $student_id
                    ];
                    
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
                }
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
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
:root{--primary:#6366f1;--secondary:#22d3ee;}
body{background:#f8fafc;}
.card{border:none;border-radius:15px;box-shadow:0 10px 30px rgba(0,0,0,0.08);}
.btn-primary{background:linear-gradient(135deg,var(--primary),var(--secondary));border:none;}
.table th{background:#f1f5f9;color:#475569;font-weight:600;}
</style>
</head>
<body>
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold">
                <?php if($action=='add'): ?>➕ Add New Student
                <?php elseif($action=='edit'): ?>✏️ Edit Student
                <?php else: ?>👥 Manage Students<?php endif; ?>
            </h1>
            <p class="text-muted">
                <?php if($action=='add'): ?>Add a new student to the system
                <?php elseif($action=='edit'): ?>Edit student information
                <?php else: ?>View and manage all students<?php endif; ?>
            </p>
        </div>
        <div>
            <?php if($action=='list'): ?>
                <a href="?action=add" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i>Add New Student
                </a>
            <?php else: ?>
                <a href="manage_students.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
            <?php endif; ?>
            <a href="studentinfo.php" class="btn btn-outline-primary ms-2">
                <i class="fas fa-users me-2"></i>View by Grade
            </a>
        </div>
    </div>

    <!-- Alerts -->
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <?php if($action=='add'||$action=='edit'): ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card p-4">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $action ?>">
                        <?php if($action=='edit'): ?>
                            <input type="hidden" name="student_id" value="<?= $student['student_id'] ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?= $student['full_name'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Registration Number *</label>
                                <input type="text" name="reg_number" class="form-control" 
                                       value="<?= $student['reg_number'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= $student['email'] ?? '' ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Password <?= $action=='add'?'*':'(leave blank to keep current)' ?>
                                </label>
                                <input type="password" name="password" class="form-control" 
                                       <?= $action=='add'?'required':'' ?>>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Grade Level</label>
                                <select name="grade_level" class="form-select">
                                    <option value="">Select Grade</option>
                                    <?php for($i=6;$i<=12;$i++): ?>
                                        <option value="Grade <?= $i ?>" 
                                            <?= ($student['grade_level']??'')=="Grade $i"?'selected':'' ?>>
                                            Grade <?= $i ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Section</label>
                                <select name="section" class="form-select">
                                    <option value="">Select Section</option>
                                    <?php foreach(['A','B','C','D','E'] as $sec): ?>
                                        <option value="<?= $sec ?>" 
                                            <?= ($student['section']??'')==$sec?'selected':'' ?>>
                                            Section <?= $sec ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-3 mt-4">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-save me-2"></i>
                                <?= $action=='add'?'Add Student':'Update Student' ?>
                            </button>
                            <a href="manage_students.php" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    
    <!-- Student List -->
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Reg Number</th>
                                <th>Email</th>
                                <th>Grade & Section</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($students)): ?>
                                <tr><td colspan="7" class="text-center py-5 text-muted">No students found</td></tr>
                            <?php else: foreach($students as $index=>$stu): ?>
                                <tr>
                                    <td><?= $index+1 ?></td>
                                    <td><?= htmlspecialchars($stu['full_name']) ?></td>
                                    <td><?= htmlspecialchars($stu['reg_number']) ?></td>
                                    <td><?= htmlspecialchars($stu['email']?:'N/A') ?></td>
                                    <td>
                                        <?php if($stu['grade_level']): ?>
                                            <span class="badge bg-info"><?= $stu['grade_level'] ?></span>
                                            <?php if($stu['section']): ?>
                                                <span class="badge bg-secondary">Sec <?= $stu['section'] ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y',strtotime($stu['created_at'])) ?></td>
                                    <td>
                                        <a href="view_student.php?id=<?= $stu['student_id'] ?>" 
                                           class="btn btn-sm btn-outline-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="?action=edit&id=<?= $stu['student_id'] ?>" 
                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="deleteStudent(<?= $stu['student_id'] ?>,'<?= htmlspecialchars(addslashes($stu['full_name'])) ?>')" 
                                                class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function deleteStudent(id, name) {
    if(confirm('Delete student "' + name + '"? This action cannot be undone.')) {
        $.post('delete_student.php', {student_id: id}, function() {
            location.reload();
        });
    }
}
</script>
</body>
</html>