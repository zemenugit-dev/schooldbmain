<?php
session_start();
require '../config/pdo_connect.php';
require '../config/audit_logger.php';

// Admin check
if(!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// ✅ AUDIT LOG: Admin accessed audit dashboard
logActivity($pdo, [
    'action_type' => 'VIEW',
    'action_details' => 'Accessed audit dashboard',
    'description' => 'Admin accessed the audit dashboard',
    'module' => 'audit_dashboard'
]);

// Get filters from request
$filters = [];
if(isset($_GET['role'])) $filters['user_role'] = $_GET['role'];
if(isset($_GET['action'])) $filters['action_type'] = $_GET['action'];
if(isset($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
if(isset($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
if(isset($_GET['status'])) $filters['status'] = $_GET['status'];

// Get logs with filters
$where = ['1=1'];
$params = [];

if(!empty($filters['user_role'])) {
    $where[] = 'al.user_role = ?';
    $params[] = $filters['user_role'];
}

if(!empty($filters['action_type'])) {
    $where[] = 'al.action_type = ?';
    $params[] = $filters['action_type'];
}

if(!empty($filters['date_from'])) {
    $where[] = 'DATE(al.created_at) >= ?';
    $params[] = $filters['date_from'];
}

if(!empty($filters['date_to'])) {
    $where[] = 'DATE(al.created_at) <= ?';
    $params[] = $filters['date_to'];
}

$whereClause = implode(' AND ', $where);
$sql = "SELECT al.*,
               COALESCE(
                   a.full_name,
                   t.full_name,
                   s.full_name,
                   'System'
               ) as user_name
        FROM audit_log al
        LEFT JOIN admins a ON al.user_role = 'admin' AND al.user_id = a.admin_id
        LEFT JOIN teachers t ON al.user_role = 'teacher' AND al.user_id = t.teacher_id
        LEFT JOIN students s ON al.user_role = 'student' AND al.user_id = s.student_id
        WHERE {$whereClause}
        ORDER BY al.created_at DESC
        LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'total_logs' => $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn(),
    'today_logs' => $pdo->query("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'admin_logs' => $pdo->query("SELECT COUNT(*) FROM audit_log WHERE user_role = 'admin'")->fetchColumn(),
    'teacher_logs' => $pdo->query("SELECT COUNT(*) FROM audit_log WHERE user_role = 'teacher'")->fetchColumn(),
    'student_logs' => $pdo->query("SELECT COUNT(*) FROM audit_log WHERE user_role = 'student'")->fetchColumn(),
    'failed_logins' => $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action_type = 'LOGIN_FAILED'")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-green: #10b981;
            --secondary-green: #34d399;
            --dark-green: #059669;
            --light-green: #d1fae5;
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-dark: #1f2937;
            --text-light: #6b7280;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%);
            min-height: 100vh;
            padding: 20px;
            color: var(--text-dark);
        }

        .container-fluid {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            margin-top: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header-section {
            background: linear-gradient(135deg, var(--dark-green), var(--primary-green));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }

        .header-section h1 {
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header-section p {
            opacity: 0.9;
            margin-bottom: 0;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.1);
            transition: all 0.3s ease;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(16, 185, 129, 0.15);
            border-color: var(--primary-green);
        }

        .stat-card h3 {
            color: var(--dark-green);
            font-weight: 800;
            font-size: 2.5rem;
            margin: 10px 0;
        }

        .stat-card p {
            color: var(--text-light);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0;
        }

        .filter-card {
            background: var(--card-bg);
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.1);
            overflow: hidden;
        }

        .filter-card .card-header {
            background: linear-gradient(135deg, var(--dark-green), var(--primary-green));
            color: white;
            border: none;
            padding: 20px 25px;
            font-weight: 600;
        }

        .filter-card .card-body {
            padding: 25px;
        }

        .form-control, .form-select {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            outline: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
            background: linear-gradient(135deg, var(--dark-green), var(--primary-green));
        }

        .btn-secondary {
            background: #6b7280;
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
        }

        .btn-outline-primary {
            border-color: var(--primary-green);
            color: var(--primary-green);
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
        }

        .btn-outline-primary:hover {
            background: var(--primary-green);
            border-color: var(--primary-green);
        }

        .table-container {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 15px;
            margin-top: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.1);
        }

        .table-container h5 {
            color: var(--dark-green);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-green);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: var(--light-green);
            color: var(--dark-green);
            font-weight: 600;
            border-bottom: 2px solid var(--secondary-green);
            padding: 15px;
        }

        .table tbody tr {
            transition: all 0.2s;
        }

        .table tbody tr:hover {
            background: rgba(209, 250, 229, 0.3);
            transform: translateX(5px);
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(209, 250, 229, 0.5);
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .badge-admin { 
            background: linear-gradient(135deg, #dc3545, #e35d6a);
            color: white;
        }
        
        .badge-teacher { 
            background: linear-gradient(135deg, #28a745, #34d399);
            color: white;
        }
        
        .badge-student { 
            background: linear-gradient(135deg, #ffc107, #fbbf24);
            color: #1f2937;
        }
        
        .badge-guest { 
            background: linear-gradient(135deg, #6c757d, #9ca3af);
            color: white;
        }

        .badge-success {
            background: linear-gradient(135deg, #10b981, #34d399);
            color: white;
        }

        .badge-danger {
            background: linear-gradient(135deg, #ef4444, #f87171);
            color: white;
        }

        .badge-warning {
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            color: #1f2937;
        }

        .dataTables_wrapper {
            padding: 0;
        }

        .dataTables_filter input {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 8px 15px;
            margin-left: 10px;
        }

        .dataTables_length select {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 6px 10px;
        }

        @media (max-width: 768px) {
            .container-fluid {
                padding: 15px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .table-container {
                padding: 20px;
            }
            
            .header-section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <!-- Header Section -->
    <div class="header-section">
        <h1><i class="fas fa-clipboard-list me-2"></i> Audit Log Dashboard</h1>
        <p>Monitor and track all system activities in real-time</p>
    </div>
    
    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-card">
                <i class="fas fa-database fa-2x text-dark-green mb-3"></i>
                <h3><?= number_format($stats['total_logs']) ?></h3>
                <p>Total Logs</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <i class="fas fa-calendar-day fa-2x text-dark-green mb-3"></i>
                <h3><?= number_format($stats['today_logs']) ?></h3>
                <p>Today's Logs</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <i class="fas fa-user-shield fa-2x text-dark-green mb-3"></i>
                <h3><?= number_format($stats['admin_logs']) ?></h3>
                <p>Admin Activities</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <i class="fas fa-chalkboard-teacher fa-2x text-dark-green mb-3"></i>
                <h3><?= number_format($stats['teacher_logs']) ?></h3>
                <p>Teacher Activities</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <i class="fas fa-user-graduate fa-2x text-dark-green mb-3"></i>
                <h3><?= number_format($stats['student_logs']) ?></h3>
                <p>Student Activities</p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <i class="fas fa-exclamation-triangle fa-2x text-dark-green mb-3"></i>
                <h3><?= number_format($stats['failed_logins']) ?></h3>
                <p>Failed Logins</p>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filter-card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Activity Logs</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-medium">User Role</label>
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="admin" <?= isset($_GET['role']) && $_GET['role']=='admin'?'selected':'' ?>>Admin</option>
                        <option value="teacher" <?= isset($_GET['role']) && $_GET['role']=='teacher'?'selected':'' ?>>Teacher</option>
                        <option value="student" <?= isset($_GET['role']) && $_GET['role']=='student'?'selected':'' ?>>Student</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Action Type</label>
                    <select name="action" class="form-select">
                        <option value="">All Actions</option>
                        <option value="LOGIN">Login</option>
                        <option value="LOGOUT">Logout</option>
                        <option value="CREATE">Create</option>
                        <option value="UPDATE">Update</option>
                        <option value="DELETE">Delete</option>
                        <option value="VIEW">View</option>
                        <option value="LOGIN_FAILED">Failed Login</option>
                        <option value="SYSTEM_ACTIVITY">System Activity</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $_GET['date_from'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $_GET['date_to'] ?? '' ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>Apply Filters
                    </button>
                    <a href="audit_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-redo me-1"></i>Clear Filters
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-primary float-end">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Activity Logs -->
    <div class="table-container">
        <h5><i class="fas fa-history me-2"></i>Activity Logs (<?= count($logs) ?> records)</h5>
        <div class="table-responsive">
            <table class="table table-hover" id="logsTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-clock me-1"></i>Time</th>
                        <th><i class="fas fa-user me-1"></i>User</th>
                        <th><i class="fas fa-user-tag me-1"></i>Role</th>
                        <th><i class="fas fa-bolt me-1"></i>Action</th>
                        <th><i class="fas fa-info-circle me-1"></i>Details</th>
                        <th><i class="fas fa-cube me-1"></i>Module</th>
                        <th><i class="fas fa-flag me-1"></i>Status</th>
                        <th><i class="fas fa-network-wired me-1"></i>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($logs as $log): ?>
                    <tr>
                        <td>
                            <div class="fw-medium"><?= date('M d, Y', strtotime($log['created_at'])) ?></div>
                            <small class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                        </td>
                        <td class="fw-medium"><?= htmlspecialchars($log['user_name'] ?? 'N/A') ?></td>
                        <td>
                            <span class="badge badge-<?= $log['user_role'] ?? 'guest' ?>">
                                <i class="fas fa-<?= 
                                    $log['user_role'] === 'admin' ? 'user-shield' : 
                                    ($log['user_role'] === 'teacher' ? 'chalkboard-teacher' : 
                                    ($log['user_role'] === 'student' ? 'user-graduate' : 'user')) 
                                ?> me-1"></i>
                                <?= strtoupper($log['user_role'] ?? 'GUEST') ?>
                            </span>
                        </td>
                        <td>
                            <span class="fw-medium"><?= htmlspecialchars($log['action_type']) ?></span>
                        </td>
                        <td>
                            <div class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($log['description'] ?? '') ?>">
                                <?= htmlspecialchars($log['description'] ?? '') ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">
                                <?= htmlspecialchars($log['module'] ?? '') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?= 
                                $log['status'] == 'SUCCESS' ? 'success' : 
                                ($log['status'] == 'FAILED' ? 'danger' : 'warning') ?>">
                                <i class="fas fa-<?= 
                                    $log['status'] == 'SUCCESS' ? 'check-circle' : 
                                    ($log['status'] == 'FAILED' ? 'times-circle' : 'exclamation-triangle') 
                                ?> me-1"></i>
                                <?= $log['status'] ?>
                            </span>
                        </td>
                        <td>
                            <code class="text-muted"><?= htmlspecialchars($log['ip_address']) ?></code>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if(empty($logs)): ?>
            <div class="text-center py-5">
                <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No activity logs found</h4>
                <p class="text-muted">Try adjusting your filters or check back later</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#logsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>tip',
        language: {
            search: "Search logs:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ logs",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    });
    
    // Add tooltips for truncated text
    $('[title]').tooltip({
        placement: 'top',
        trigger: 'hover'
    });
});

// Auto-refresh every 2 minutes
setTimeout(() => {
    window.location.reload();
}, 120000);
</script>
</body>
</html>