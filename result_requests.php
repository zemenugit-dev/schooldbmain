<?php
session_start();
require '../config/pdo_connect.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Fetch all correction requests
$requests_stmt = $pdo->query("
    SELECT rcr.*,
           stu.full_name as student_name,
           stu.reg_number,
           tea.full_name as teacher_name,
           sub.subject_name,
           r.semester,
           r.academic_year
    FROM result_correction_requests rcr
    JOIN students stu ON rcr.student_id = stu.student_id
    JOIN teachers tea ON rcr.teacher_id = tea.teacher_id
    JOIN results r ON rcr.result_id = r.result_id
    JOIN subjects sub ON r.subject_id = sub.subject_id
    ORDER BY 
        CASE WHEN rcr.status = 'pending' THEN 1 ELSE 2 END,
        rcr.created_at DESC
");
$requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$stats = [
    'total' => count($requests),
    'pending' => 0,
    'corrected' => 0,
    'rejected' => 0,
    'approved' => 0
];

foreach ($requests as $req) {
    $stats[$req['status']]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result Correction Requests - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .stat-card { 
            border-radius: 12px; 
            color: white;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .table th { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .badge { font-size: 0.8em; padding: 5px 10px; }
        .modal-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <h2 class="mb-4 text-primary">
        <i class="fas fa-exchange-alt me-2"></i>Result Correction Requests
    </h2>
    
    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h1 class="display-5"><?= $stats['total'] ?></h1>
                <p class="mb-0">Total Requests</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);">
                <h1 class="display-5"><?= $stats['pending'] ?></h1>
                <p class="mb-0">Pending</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%);">
                <h1 class="display-5"><?= $stats['corrected'] ?></h1>
                <p class="mb-0">Corrected</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);">
                <h1 class="display-5"><?= $stats['rejected'] ?></h1>
                <p class="mb-0">Rejected</p>
            </div>
        </div>
    </div>
    
    <!-- Requests Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Teacher</th>
                            <th>Subject</th>
                            <th>Current</th>
                            <th>Expected</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No requests found</h5>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td>#<?= $req['request_id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($req['student_name']) ?></strong><br>
                                        <small class="text-muted"><?= $req['reg_number'] ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($req['teacher_name']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($req['subject_name']) ?><br>
                                        <small>Sem <?= $req['semester'] ?> | <?= $req['academic_year'] ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= $req['current_marks'] ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= $req['expected_marks'] ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_color = match($req['status']) {
                                            'pending' => 'warning',
                                            'corrected' => 'success',
                                            'approved' => 'primary',
                                            'rejected' => 'danger',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $status_color ?>">
                                            <?= ucfirst($req['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($req['created_at'])) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailsModal<?= $req['request_id'] ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                
                                <!-- Modal for details -->
                                <div class="modal fade" id="detailsModal<?= $req['request_id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Request Details #<?= $req['request_id'] ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row mb-4">
                                                    <div class="col-md-6">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <h6><i class="fas fa-user-graduate me-2"></i>Student</h6>
                                                                <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($req['student_name']) ?></p>
                                                                <p class="mb-0"><strong>Reg No:</strong> <?= $req['reg_number'] ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <h6><i class="fas fa-chalkboard-teacher me-2"></i>Teacher</h6>
                                                                <p class="mb-0"><strong>Name:</strong> <?= htmlspecialchars($req['teacher_name']) ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Marks Info -->
                                                <div class="row mb-4">
                                                    <div class="col-md-4 text-center">
                                                        <div class="card bg-secondary text-white">
                                                            <div class="card-body">
                                                                <h6>Current</h6>
                                                                <h2><?= $req['current_marks'] ?></h2>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 text-center">
                                                        <div class="card bg-primary text-white">
                                                            <div class="card-body">
                                                                <h6>Expected</h6>
                                                                <h2><?= $req['expected_marks'] ?></h2>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 text-center">
                                                        <div class="card bg-<?= $req['status'] == 'corrected' ? 'success' : 'light' ?>">
                                                            <div class="card-body">
                                                                <h6>Corrected</h6>
                                                                <h2><?= $req['corrected_marks'] ?: 'N/A' ?></h2>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Reason -->
                                                <div class="mb-3">
                                                    <h6><i class="fas fa-comment me-2"></i>Student's Reason</h6>
                                                    <div class="alert alert-light">
                                                        <p class="mb-0"><?= nl2br(htmlspecialchars($req['reason'])) ?></p>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($req['teacher_notes']): ?>
                                                    <div class="mb-3">
                                                        <h6><i class="fas fa-sticky-note me-2"></i>Teacher Notes</h6>
                                                        <div class="alert alert-info">
                                                            <p class="mb-0"><?= nl2br(htmlspecialchars($req['teacher_notes'])) ?></p>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($req['admin_notes']): ?>
                                                    <div class="mb-3">
                                                        <h6><i class="fas fa-user-shield me-2"></i>Admin Notes</h6>
                                                        <div class="alert alert-warning">
                                                            <p class="mb-0"><?= nl2br(htmlspecialchars($req['admin_notes'])) ?></p>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <a href="dashboard.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>