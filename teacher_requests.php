<?php
session_start();
require '../config/pdo_connect.php';

// Admin session check
if (!isset($_SESSION['admin']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$success = '';
$error = '';

// Handle request approval/rejection
if (isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $admin_response = trim($_POST['admin_response'] ?? '');
    
    try {
        // Validate request exists
        $checkStmt = $pdo->prepare("SELECT * FROM teacher_requests WHERE request_id = ?");
        $checkStmt->execute([$request_id]);
        $request = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            $error = "❌ Request not found!";
        } elseif ($request['status'] !== 'pending') {
            $error = "❌ This request has already been processed!";
        } else {
            // Update the request status
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE teacher_requests SET status = 'approved', admin_response = ?, updated_at = CURRENT_TIMESTAMP WHERE request_id = ?");
                $stmt->execute([$admin_response, $request_id]);
                $success = "✅ Request approved successfully!";
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare("UPDATE teacher_requests SET status = 'rejected', admin_response = ?, updated_at = CURRENT_TIMESTAMP WHERE request_id = ?");
                $stmt->execute([$admin_response, $request_id]);
                $success = "✅ Request rejected successfully!";
            } else {
                $error = "❌ Invalid action!";
            }
        }
    } catch (PDOException $e) {
        $error = "❌ Database error: " . $e->getMessage();
    }
    
    // Refresh page to show updated status
    if ($success || $error) {
        header("Location: teacher_requests.php?success=" . urlencode($success) . "&error=" . urlencode($error));
        exit;
    }
}

// Check for success/error messages from URL
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Fetch all teacher requests
try {
    $query = "
        SELECT tr.*, t.full_name as teacher_name, t.email as teacher_email, t.specialization as teacher_specialization
        FROM teacher_requests tr
        JOIN teachers t ON tr.teacher_id = t.teacher_id
        ORDER BY 
            CASE WHEN tr.status = 'pending' THEN 1 ELSE 2 END,
            tr.created_at DESC
    ";
    $stmt = $pdo->query($query);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "❌ Error fetching requests: " . $e->getMessage();
    $requests = [];
}

// Count requests by status
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;
$total_count = count($requests);

foreach ($requests as $req) {
    if ($req['status'] === 'pending') $pending_count++;
    elseif ($req['status'] === 'approved') $approved_count++;
    elseif ($req['status'] === 'rejected') $rejected_count++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Teacher Requests - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #4f46e5;
    --secondary: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --dark: #1f2937;
    --light: #f9fafb;
}

body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    color: var(--dark);
    margin: 0;
    padding: 0;
}

.container {
    max-width: 1400px;
    margin: 30px auto;
    padding: 0 20px;
}

/* Header */
.header {
    text-align: center;
    margin-bottom: 40px;
}

.header h1 {
    color: var(--primary);
    font-weight: 700;
    font-size: 2.5rem;
    margin-bottom: 10px;
}

/* Stats Cards */
.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-number {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.stat-label {
    color: #6b7280;
    font-size: 1rem;
    font-weight: 600;
}

/* Request Cards */
.request-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
}

.teacher-info {
    background: #f9fafb;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
    border-left: 4px solid var(--primary);
}

.request-details {
    margin: 20px 0;
}

/* Status Badges */
.badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
    display: inline-block;
}

.badge-pending {
    background: rgba(245, 158, 11, 0.15);
    color: #d97706;
}

.badge-approved {
    background: rgba(34, 197, 94, 0.15);
    color: #16a34a;
}

.badge-rejected {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
}

/* Buttons */
.btn {
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-approve {
    background: linear-gradient(135deg, var(--secondary), #22c55e);
    color: white;
}

.btn-approve:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(34, 197, 94, 0.3);
    color: white;
}

.btn-reject {
    background: linear-gradient(135deg, var(--danger), #f87171);
    color: white;
}

.btn-reject:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
    color: white;
}

/* Alert Messages */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: rgba(34, 197, 94, 0.15);
    color: #16a34a;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.alert-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

/* Text Areas */
.form-control {
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 1rem;
    transition: all 0.3s;
    background: #f9fafb;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    outline: none;
    background: white;
}

/* Responsive */
@media (max-width: 768px) {
    .container {
        padding: 15px;
    }
    
    .stats-cards {
        grid-template-columns: 1fr;
    }
}

/* Status-specific styling */
.pending-card {
    border-left: 4px solid #f59e0b;
}

.approved-card {
    border-left: 4px solid #10b981;
}

.rejected-card {
    border-left: 4px solid #ef4444;
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-tasks me-2"></i>Manage Teacher Requests</h1>
        <p>Review and respond to teacher permission requests</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    
    <!-- Statistics -->
    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-number"><?= $total_count ?></div>
            <div class="stat-label">Total Requests</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #f59e0b;"><?= $pending_count ?></div>
            <div class="stat-label">Pending Requests</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #10b981;"><?= $approved_count ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #ef4444;"><?= $rejected_count ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>
    
    <!-- Requests List -->
    <?php if (empty($requests)): ?>
        <div class="text-center py-5">
            <i class="fas fa-inbox fa-3x mb-3" style="color: #9ca3af;"></i>
            <h3 class="mb-2">No Requests Found</h3>
            <p class="text-muted">No teacher requests have been submitted yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($requests as $request): ?>
            <div class="request-card <?= $request['status'] ?>-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4 style="color: var(--dark);"><?= htmlspecialchars($request['specialization']) ?></h4>
                        <span class="badge badge-<?= $request['status'] ?>">
                            <?= ucfirst($request['status']) ?>
                        </span>
                        <div class="text-muted mt-1">
                            <small>Request ID: #<?= $request['request_id'] ?> | Submitted: <?= date('M d, Y H:i', strtotime($request['created_at'])) ?></small>
                        </div>
                    </div>
                    <div>
                        <span class="badge" style="background: #e0e7ff; color: var(--primary);">
                            <i class="fas fa-tag me-1"></i><?= ucfirst($request['request_type']) ?>
                        </span>
                    </div>
                </div>
                
                <!-- Teacher Information -->
                <div class="teacher-info">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong><i class="fas fa-user me-2"></i>Teacher:</strong> <?= htmlspecialchars($request['teacher_name']) ?></p>
                            <p class="mb-1"><strong><i class="fas fa-envelope me-2"></i>Email:</strong> <?= htmlspecialchars($request['teacher_email']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong><i class="fas fa-graduation-cap me-2"></i>Specialization:</strong> <?= htmlspecialchars($request['teacher_specialization'] ?: 'Not set') ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Request Description -->
                <div class="request-details">
                    <h5><i class="fas fa-file-alt me-2"></i>Request Details</h5>
                    <div style="background: #f9fafb; padding: 15px; border-radius: 10px; border-left: 3px solid var(--primary);">
                        <p class="mb-0"><?= nl2br(htmlspecialchars($request['description'])) ?></p>
                    </div>
                </div>
                
                <!-- Admin Response (if exists) -->
                <?php if (!empty($request['admin_response'])): ?>
                    <div class="request-details">
                        <h5><i class="fas fa-reply me-2"></i>Admin Response</h5>
                        <div style="background: rgba(16, 185, 129, 0.1); padding: 15px; border-radius: 10px; border-left: 3px solid var(--secondary);">
                            <p class="mb-0"><?= nl2br(htmlspecialchars($request['admin_response'])) ?></p>
                        </div>
                        <small class="text-muted">Last updated: <?= date('M d, Y H:i', strtotime($request['updated_at'])) ?></small>
                    </div>
                <?php endif; ?>
                
                <!-- Action Form (only for pending requests) -->
                <?php if ($request['status'] === 'pending'): ?>
                    <div class="mt-4">
                        <form method="POST" onsubmit="return confirmAction('<?= $request['request_type'] ?>')">
                            <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                            
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-comment me-2"></i>Response Message (Optional)</label>
                                <textarea name="admin_response" class="form-control" rows="3" 
                                          placeholder="Enter your response to the teacher..."><?= htmlspecialchars($request['admin_response'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="d-flex gap-3">
                                <button type="submit" name="action" value="approve" class="btn btn-approve">
                                    <i class="fas fa-check me-1"></i>Approve Request
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-reject">
                                    <i class="fas fa-times me-1"></i>Reject Request
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Back Button -->
    <div class="text-center mt-4">
        <a href="dashboard.php" class="btn" style="background: #e5e7eb; color: #374151;">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
</div>

<script>
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

// Confirm before taking action
function confirmAction(requestType) {
    const form = event.target;
    const action = event.submitter.value;
    
    if (action === 'approve') {
        return confirm(`Are you sure you want to APPROVE this ${requestType} request?`);
    } else if (action === 'reject') {
        return confirm(`Are you sure you want to REJECT this ${requestType} request?`);
    }
    return true;
}

// Attach event listeners to reject buttons
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('button[name="action"][value="reject"]').forEach(button => {
        button.addEventListener('click', function(e) {
            const requestType = this.closest('.request-card').querySelector('h4').textContent;
            if(!confirm(`Are you sure you want to reject this "${requestType}" request?`)) {
                e.preventDefault();
            }
        });
    });
});
</script>
</body>
</html>