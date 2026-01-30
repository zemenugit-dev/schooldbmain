<?php
session_start();
require 'auth.php';
require '../config/pdo_connect.php';

// Check admin session
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Validate student ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: studentinfo.php");
    exit();
}

$student_id = (int)$_GET['id'];
$error = '';
$student = null;
$results = [];
$correction_requests = [];

try {
    // Fetch student details
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        header("Location: studentinfo.php");
        exit();
    }

    // Fetch student's results - FIXED: removed subject_code
    $results_stmt = $pdo->prepare("
        SELECT r.*, s.subject_name 
        FROM results r 
        JOIN subjects s ON r.subject_id = s.subject_id 
        WHERE r.student_id = ? 
        ORDER BY r.academic_year DESC, r.semester DESC
    ");
    $results_stmt->execute([$student_id]);
    $results = $results_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if result_correction_requests table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'result_correction_requests'")->fetch();
    
    if ($table_check) {
        // Fetch correction requests if table exists
        $requests_stmt = $pdo->prepare("
            SELECT rcr.*, s.subject_name 
            FROM result_correction_requests rcr
            JOIN results res ON rcr.result_id = res.result_id
            JOIN subjects s ON res.subject_id = s.subject_id
            WHERE rcr.student_id = ?
            ORDER BY rcr.created_at DESC
        ");
        $requests_stmt->execute([$student_id]);
        $correction_requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Table doesn't exist, show empty array
        $correction_requests = [];
        $error = "Correction requests table not found. Some features may be unavailable.";
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("View Student Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Details - <?= htmlspecialchars($student['full_name'] ?? 'Unknown') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #6366f1;
    --secondary: #22d3ee;
    --dark: #1e293b;
    --light: #f8fafc;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
}

body {
    background: #f1f5f9;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    padding-top: 20px;
}

.header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(99, 102, 241, 0.2);
}

.info-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: none;
    transition: transform 0.3s ease;
}

.info-card:hover {
    transform: translateY(-5px);
}

.badge {
    font-size: 0.85rem;
    padding: 6px 12px;
    font-weight: 600;
}

.table th {
    background: #f8fafc;
    font-weight: 600;
    color: #475569;
    border-top: none;
}

.table td {
    vertical-align: middle;
}

.student-id {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
    padding: 4px 10px;
    border-radius: 6px;
    font-weight: 600;
}

.btn-light {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
}

.btn-light:hover {
    background: rgba(255, 255, 255, 0.3);
    color: white;
}

.stats-number {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 10px;
}

.text-primary { color: var(--primary) !important; }
.text-warning { color: var(--warning) !important; }
.text-success { color: var(--success) !important; }
.text-danger { color: var(--danger) !important; }

.marks-badge {
    min-width: 70px;
    text-align: center;
}

.alert-custom {
    border-radius: 10px;
    border: none;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: #64748b;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.action-buttons .btn {
    min-width: 120px;
}

@media (max-width: 768px) {
    .header {
        padding: 20px;
        text-align: center;
    }
    
    .header .d-flex {
        flex-direction: column;
        gap: 15px;
    }
    
    .stats-number {
        font-size: 2.5rem;
    }
}
</style>
</head>
<body>
<div class="container py-4">
    <!-- Error Alert -->
    <?php if ($error): ?>
        <div class="alert alert-warning alert-custom alert-dismissible fade show mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-bold mb-2">👤 Student Details</h1>
                <p class="mb-0 opacity-90">Complete information about <?= htmlspecialchars($student['full_name']) ?></p>
            </div>
            <a href="studentinfo.php" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i>Back to Students
            </a>
        </div>
    </div>

    <!-- Student Information -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="info-card">
                <h4 class="fw-bold mb-4">
                    <i class="fas fa-id-card me-2 text-primary"></i>Personal Information
                </h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted small">Full Name</label>
                        <p class="fs-5 fw-semibold mb-1"><?= htmlspecialchars($student['full_name']) ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted small">Registration Number</label>
                        <p class="fs-5 fw-semibold mb-1"><?= htmlspecialchars($student['reg_number']) ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted small">Email Address</label>
                        <p class="fs-5 mb-1">
                            <?php if ($student['email']): ?>
                                <a href="mailto:<?= htmlspecialchars($student['email']) ?>" class="text-decoration-none">
                                    <i class="fas fa-envelope me-2 text-primary"></i><?= htmlspecialchars($student['email']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Not provided</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted small">Grade & Section</label>
                        <p class="fs-5 mb-1">
                            <?php if ($student['grade_level']): ?>
                                <span class="badge bg-primary"><?= htmlspecialchars($student['grade_level']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">Not set</span>
                            <?php endif; ?>
                            
                            <?php if ($student['section']): ?>
                                <span class="badge bg-secondary ms-2">Section <?= htmlspecialchars($student['section']) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted small">Account Created</label>
                        <p class="fs-5 mb-1">
                            <i class="fas fa-calendar me-2 text-primary"></i>
                            <?= date('F j, Y', strtotime($student['created_at'])) ?>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted small">Student ID</label>
                        <p class="fs-5 mb-1">
                            <span class="student-id">#<?= $student['student_id'] ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="info-card h-100">
                <h4 class="fw-bold mb-4">
                    <i class="fas fa-chart-bar me-2 text-primary"></i>Quick Stats
                </h4>
                <div class="text-center">
                    <div class="mb-4">
                        <div class="stats-number text-primary"><?= count($results) ?></div>
                        <p class="text-muted mb-0">
                            <i class="fas fa-file-alt me-1"></i>Total Results
                        </p>
                    </div>
                    <div class="mb-4">
                        <div class="stats-number text-warning"><?= count($correction_requests) ?></div>
                        <p class="text-muted mb-0">
                            <i class="fas fa-edit me-1"></i>Correction Requests
                        </p>
                    </div>
                    <div class="d-grid gap-2 action-buttons mt-4">
                        <a href="manage_students.php?action=edit&id=<?= $student['student_id'] ?>" 
                           class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Edit Student
                        </a>
                        <button onclick="deleteStudent()" class="btn btn-outline-danger">
                            <i class="fas fa-trash me-2"></i>Delete Student
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Academic Results -->
    <div class="info-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold">
                <i class="fas fa-chart-line me-2 text-primary"></i>Academic Results
            </h4>
            <?php if (!empty($results)): ?>
                <a href="results.php?student_id=<?= $student['student_id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-external-link-alt me-1"></i>View All Results
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($results)): ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <h5 class="text-muted mb-3">No Results Found</h5>
                <p class="text-muted">This student hasn't been assigned any results yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Marks</th>
                            <th>Semester</th>
                            <th>Academic Year</th>
                            <th>Exam Type</th>
                            <th>Remarks</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): 
                            $marks_class = '';
                            if ($result['marks'] >= 80) {
                                $marks_class = 'success';
                            } elseif ($result['marks'] >= 60) {
                                $marks_class = 'warning';
                            } else {
                                $marks_class = 'danger';
                            }
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($result['subject_name']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $marks_class ?> marks-badge">
                                        <?= number_format($result['marks'], 2) ?>%
                                    </span>
                                </td>
                                <td>Sem <?= $result['semester'] ?></td>
                                <td><?= htmlspecialchars($result['academic_year']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?= htmlspecialchars($result['exam_type'] ?? 'Final') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($result['remarks'])): ?>
                                        <small class="text-muted" title="<?= htmlspecialchars($result['remarks']) ?>">
                                            <i class="fas fa-comment"></i>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($result['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Correction Requests -->
    <div class="info-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold">
                <i class="fas fa-exchange-alt me-2 text-primary"></i>Correction Requests
            </h4>
            <?php if (!empty($correction_requests)): ?>
                <span class="badge bg-warning">
                    <?= count($correction_requests) ?> request<?= count($correction_requests) != 1 ? 's' : '' ?>
                </span>
            <?php endif; ?>
        </div>
        
        <?php if (empty($correction_requests)): ?>
            <div class="empty-state">
                <i class="fas fa-exchange-alt"></i>
                <h5 class="text-muted mb-3">No Correction Requests</h5>
                <p class="text-muted">This student hasn't submitted any correction requests.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Current Marks</th>
                            <th>Expected Marks</th>
                            <th>Difference</th>
                            <th>Status</th>
                            <th>Teacher Notes</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($correction_requests as $request): 
                            $difference = $request['expected_marks'] - $request['current_marks'];
                            $diff_class = $difference > 0 ? 'success' : ($difference < 0 ? 'danger' : 'secondary');
                            
                            $status_class = [
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'corrected' => 'primary'
                            ][$request['status']] ?? 'secondary';
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($request['subject_name']) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= number_format($request['current_marks'], 2) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= number_format($request['expected_marks'], 2) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $diff_class ?>">
                                        <?= $difference > 0 ? '+' : '' ?><?= number_format($difference, 2) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $status_class ?>">
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($request['teacher_notes'])): ?>
                                        <small class="text-muted" title="<?= htmlspecialchars($request['teacher_notes']) ?>">
                                            <i class="fas fa-sticky-note"></i>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($request['created_at'])) ?></td>
                                <td>
                                    <?php if (file_exists('result_requests.php')): ?>
                                        <a href="result_requests.php?highlight=<?= $request['request_id'] ?>" 
                                           class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function deleteStudent() {
    if (confirm('Are you sure you want to delete <?= htmlspecialchars(addslashes($student['full_name'])) ?>? This action cannot be undone.')) {
        $.ajax({
            url: 'delete_student.php',
            method: 'POST',
            data: {
                student_id: <?= $student['student_id'] ?>,
                _token: '<?= md5(session_id()) ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Student "' + response.student_name + '" deleted successfully!');
                    window.location.href = 'studentinfo.php';
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete error:', error);
                alert('Error deleting student. Please try again.');
            }
        });
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Highlight row if coming from result_requests.php
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('highlight')) {
    const requestId = urlParams.get('highlight');
    const row = document.querySelector(`tr:has(a[href*="highlight=${requestId}"])`);
    if (row) {
        row.style.backgroundColor = 'rgba(255, 193, 7, 0.1)';
        setTimeout(() => {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 500);
    }
}
</script>
</body>
</html>