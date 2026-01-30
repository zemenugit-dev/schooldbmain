<?php
session_start();
require '../config/pdo_connect.php';

// ✅ Admin session check
if(!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$success = '';
$error = '';

// Handle Add Teacher
if(isset($_POST['add_teacher'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $phone_number = trim($_POST['phone_number']);
    $specialization = trim($_POST['specialization']);
    $assigned_grade = trim($_POST['assigned_grade']);
    $assigned_section = trim($_POST['assigned_section']);
    $subject_id = (int)$_POST['subject_id'];

    // Validation
    $errors = [];
    
    if(empty($full_name)) $errors[] = "Full name is required";
     elseif (!preg_match("/^[a-zA-Z\s.'-]+$/", $full_name)) {
            $errors['full_name'] = "Name can only contain letters, spaces, dots, hyphens and apostrophes";
        }
    if(empty($email)) $errors[] = "Email is required";
    elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if(empty($username)) $errors[] = "Username is required";
    if(empty($password)) $errors[] = "Password is required";
    elseif(strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    
    if(!empty($phone_number) && !preg_match('/^[0-9+\-\s()]{10,20}$/', $phone_number)) {
        $errors[] = "Invalid phone number format";
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE email = ?");
    $stmt->execute([$email]);
    if($stmt->fetchColumn() > 0) {
        $errors[] = "Email already exists";
    }

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE username = ?");
    $stmt->execute([$username]);
    if($stmt->fetchColumn() > 0) {
        $errors[] = "Username already exists";
    }

    if(empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO teachers (full_name, email, username, password, phone_number, specialization, assigned_grade, assigned_section, subject_id) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$full_name, $email, $username, $hashed_password, $phone_number, $specialization, $assigned_grade, $assigned_section, $subject_id]);
            $success = "✅ Teacher added successfully!";
        } catch(PDOException $e) {
            $error = "❌ Database error: " . $e->getMessage();
        }
    } else {
        $error = "❌ " . implode("<br>❌ ", $errors);
    }
}

// Handle Delete Teacher
if(isset($_GET['delete_teacher'])) {
    $teacher_id = (int)$_GET['delete_teacher'];
    try {
        $stmt = $pdo->prepare("UPDATE teachers SET status = 'inactive' WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        $success = "✅ Teacher deactivated successfully!";
    } catch(PDOException $e) {
        $error = "❌ Database error: " . $e->getMessage();
    }
}

// Handle Update Teacher
if(isset($_POST['update_teacher'])) {
    $teacher_id = (int)$_POST['edit_teacher_id'];
    $full_name = trim($_POST['edit_full_name']);
    $email = trim($_POST['edit_email']);
    $username = trim($_POST['edit_username']);
    $phone_number = trim($_POST['edit_phone']);
    $specialization = trim($_POST['edit_specialization']);
    $assigned_grade = trim($_POST['edit_grade']);
    $assigned_section = trim($_POST['edit_section']);
    $subject_id = (int)$_POST['edit_subject'];
    $status = $_POST['edit_status'];
    $new_password = trim($_POST['edit_password']);

    // Validation
    $errors = [];
    
    if(empty($full_name)) $errors[] = "Full name is required";
    if(empty($email)) $errors[] = "Email is required";
    elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if(empty($username)) $errors[] = "Username is required";
    
    if(!empty($new_password) && strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }

    if(empty($errors)) {
        try {
            if(!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE teachers SET full_name=?, email=?, username=?, phone_number=?, specialization=?, assigned_grade=?, assigned_section=?, subject_id=?, status=?, password=? WHERE teacher_id=?");
                $stmt->execute([$full_name, $email, $username, $phone_number, $specialization, $assigned_grade, $assigned_section, $subject_id, $status, $hashed_password, $teacher_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE teachers SET full_name=?, email=?, username=?, phone_number=?, specialization=?, assigned_grade=?, assigned_section=?, subject_id=?, status=? WHERE teacher_id=?");
                $stmt->execute([$full_name, $email, $username, $phone_number, $specialization, $assigned_grade, $assigned_section, $subject_id, $status, $teacher_id]);
            }
            $success = "✅ Teacher updated successfully!";
        } catch(PDOException $e) {
            $error = "❌ Database error: " . $e->getMessage();
        }
    } else {
        $error = "❌ " . implode("<br>❌ ", $errors);
    }
}

// Fetch all teachers
$teachers = $pdo->query("
    SELECT t.*, s.subject_name 
    FROM teachers t 
    LEFT JOIN subjects s ON t.subject_id = s.subject_id 
    ORDER BY t.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch subjects for dropdown
$subjects = $pdo->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #4f46e5;
    --secondary: #10b981;
    --danger: #ef4444;
    --dark: #1f2937;
}

body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    color: var(--dark);
}

.container {
    max-width: 1400px;
    margin: 30px auto;
    padding: 0 20px;
}

.header {
    text-align: center;
    margin-bottom: 40px;
}

.header h1 {
    color: var(--primary);
    font-weight: 700;
    font-size: 2.5rem;
}

.form-container, .table-container {
    background: white;
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.form-title, .table-title {
    color: var(--dark);
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-control {
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px 16px;
    transition: all 0.3s;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.table th {
    background: #f9fafb;
    color: #374151;
    font-weight: 600;
    border-bottom: 2px solid #e5e7eb;
    padding: 15px;
}

.table td {
    padding: 15px;
    vertical-align: middle;
    border-bottom: 1px solid #e5e7eb;
}

.badge-success {
    background: rgba(34, 197, 94, 0.15);
    color: #16a34a;
}

.badge-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
}

.btn {
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), #6366f1);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(79, 70, 229, 0.3);
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger), #f87171);
    color: white;
}

.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    animation: slideIn 0.5s ease;
}

.alert-success {
    background: rgba(34, 197, 94, 0.15);
    color: #16a34a;
}

.alert-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
}

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

@media (max-width: 768px) {
    .container {
        padding: 15px;
    }
    
    .form-container, .table-container {
        padding: 15px;
    }
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-chalkboard-teacher me-2"></i>Teacher Management</h1>
        <p class="text-muted">Manage teachers in the school system</p>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    
    <!-- Add Teacher Form -->
    <div class="form-container">
        <h3 class="form-title">
            <i class="fas fa-plus-circle text-success"></i>
            Add New Teacher
        </h3>
        <form method="POST" class="row g-3" id="teacherForm">
            <div class="col-md-6">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" placeholder="Enter full name" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" placeholder="teacher@school.com" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Username *</label>
                <input type="text" name="username" class="form-control" placeholder="Choose username" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Password * (min 6 characters)</label>
                <input type="password" name="password" class="form-control" placeholder="Enter password" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone_number" class="form-control" placeholder="+251 XXX XXX XXX">
            </div>
            <div class="col-md-4">
                <label class="form-label">Specialization</label>
                <input type="text" name="specialization" class="form-control" placeholder="e.g., Mathematics, Physics">
            </div>
            <div class="col-md-4">
                <label class="form-label">Assigned Grade</label>
                <select name="assigned_grade" class="form-control">
                    <option value="">Select Grade</option>
                    <?php for($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>">Grade <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Assigned Section</label>
                <input type="text" name="assigned_section" class="form-control" placeholder="e.g., A, B, C">
            </div>
            <div class="col-md-12">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-control">
                    <option value="">Select Subject</option>
                    <?php foreach($subjects as $subject): ?>
                        <option value="<?= $subject['subject_id'] ?>"><?= htmlspecialchars($subject['subject_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" name="add_teacher" class="btn btn-success">
                    <i class="fas fa-plus-circle me-1"></i>Add Teacher
                </button>
            </div>
        </form>
    </div>
    
    <!-- Teachers Table -->
    <div class="table-container">
        <h3 class="table-title">
            <i class="fas fa-list text-primary"></i>
            All Teachers (<?= count($teachers) ?>)
        </h3>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Specialization</th>
                        <th>Class</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($teachers as $teacher): ?>
                    <tr>
                        <td>#<?= $teacher['teacher_id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($teacher['full_name']) ?></strong>
                            <?php if($teacher['phone_number']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($teacher['phone_number']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($teacher['email']) ?></td>
                        <td><?= htmlspecialchars($teacher['username']) ?></td>
                        <td><?= htmlspecialchars($teacher['specialization'] ?: 'Not set') ?></td>
                        <td>
                            <?php if($teacher['assigned_grade']): ?>
                                Grade <?= $teacher['assigned_grade'] ?><?= $teacher['assigned_section'] ? ' - ' . $teacher['assigned_section'] : '' ?>
                            <?php else: ?>
                                Not assigned
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $teacher['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                <?= ucfirst($teacher['status']) ?>
                            </span>
                        </td>
                        <td><?= date('d M Y', strtotime($teacher['created_at'])) ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editTeacherModal"
                                onclick="fillEditTeacherForm(
                                    '<?= $teacher['teacher_id'] ?>',
                                    '<?= addslashes($teacher['full_name']) ?>',
                                    '<?= addslashes($teacher['email']) ?>',
                                    '<?= addslashes($teacher['username']) ?>',
                                    '<?= addslashes($teacher['phone_number']) ?>',
                                    '<?= addslashes($teacher['specialization']) ?>',
                                    '<?= $teacher['assigned_grade'] ?>',
                                    '<?= addslashes($teacher['assigned_section']) ?>',
                                    '<?= $teacher['subject_id'] ?>',
                                    '<?= $teacher['status'] ?>'
                                )">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <a href="?delete_teacher=<?= $teacher['teacher_id'] ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to deactivate this teacher?')">
                                <i class="fas fa-ban"></i> Deactivate
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Teacher Modal -->
<div class="modal fade" id="editTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="edit_teacher_id" id="edit_teacher_id">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="edit_full_name" id="edit_full_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" name="edit_email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username *</label>
                        <input type="text" name="edit_username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="edit_phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Specialization</label>
                        <input type="text" name="edit_specialization" id="edit_specialization" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Grade</label>
                        <select name="edit_grade" id="edit_grade" class="form-control">
                            <option value="">Select Grade</option>
                            <?php for($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>">Grade <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Section</label>
                        <input type="text" name="edit_section" id="edit_section" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subject</label>
                        <select name="edit_subject" id="edit_subject" class="form-control">
                            <option value="">Select Subject</option>
                            <?php foreach($subjects as $subject): ?>
                                <option value="<?= $subject['subject_id'] ?>"><?= htmlspecialchars($subject['subject_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="edit_status" id="edit_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">New Password (Leave blank to keep current)</label>
                        <input type="password" name="edit_password" class="form-control" placeholder="Enter new password">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_teacher" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>Save Changes
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Fill edit teacher form
function fillEditTeacherForm(id, name, email, username, phone, specialization, grade, section, subject, status) {
    document.getElementById('edit_teacher_id').value = id;
    document.getElementById('edit_full_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_phone').value = phone || '';
    document.getElementById('edit_specialization').value = specialization || '';
    document.getElementById('edit_grade').value = grade;
    document.getElementById('edit_section').value = section || '';
    document.getElementById('edit_subject').value = subject || '';
    document.getElementById('edit_status').value = status;
}

// Auto-hide alerts
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.opacity = '0';
        alert.style.transition = 'opacity 0.5s ease';
        setTimeout(() => {
            if(alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 500);
    });
}, 5000);
</script>
</body>
</html>