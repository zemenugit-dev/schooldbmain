<?php
//session_start();
error_reporting(E_ALL);
ini_set('display_errors',1);
require 'auth.php';
require '../config/pdo_connect.php';
require '../config/audit_logger.php'; // ADD THIS LINE

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// ADD AUDIT LOGGING FOR DASHBOARD ACCESS
logActivity($pdo, [
    'action_type' => 'VIEW',
    'action_details' => 'Accessed admin dashboard',
    'description' => 'Admin accessed the dashboard page',
    'module' => 'dashboard'
]);

// Fetch dashboard statistics
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$teachers_count = $pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'active'")->fetchColumn();
$subjects_count = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
$pending_requests = $pdo->query("SELECT COUNT(*) FROM result_correction_requests WHERE status = 'pending'")->fetchColumn();

// Fetch recent activities from audit_log instead
$recent_activities = $pdo->query("
    SELECT 
        al.*,
        CASE 
            WHEN al.user_role = 'admin' THEN a.full_name
            WHEN al.user_role = 'teacher' THEN t.full_name
            WHEN al.user_role = 'student' THEN s.full_name
            ELSE 'System'
        END as user_name
    FROM audit_log al
    LEFT JOIN admins a ON al.user_role = 'admin' AND al.user_id = a.admin_id
    LEFT JOIN teachers t ON al.user_role = 'teacher' AND al.user_id = t.teacher_id
    LEFT JOIN students s ON al.user_role = 'student' AND al.user_id = s.student_id
    ORDER BY al.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root{
  --bg:#020617;
  --sidebar:#020617;
  --card:rgba(17,24,39,.85);
  --primary:#6366f1;
  --secondary:#22d3ee;
  --text:#e5e7eb;
}

*{box-sizing:border-box;}

body{
  margin:0;
  font-family:'Poppins',sans-serif;
  background:linear-gradient(135deg,#020617,#0b1120);
  color:var(--text);
  min-height: 100vh;
}

/* ===== Fixed Sidebar ===== */
.sidebar{
  position:fixed;
  top:0; left:0; bottom:0;
  width:280px; /* Increased width for better visibility */
  padding:30px 20px;
  background:linear-gradient(180deg,#020617,#020617dd);
  backdrop-filter:blur(12px);
  border-right: 1px solid rgba(99,102,241,0.2);
  overflow-y: auto;
  z-index: 1000;
}

.sidebar h4{
  color:#fff;
  font-weight:700;
  margin-bottom:40px;
  text-align:center;
  font-size: 1.5rem;
  padding-bottom: 20px;
  border-bottom: 2px solid rgba(99,102,241,0.3);
}

.sidebar a{
  display:flex;
  align-items:center;
  gap:15px;
  padding:15px 20px;
  margin-bottom:10px;
  color:#c7d2fe;
  text-decoration:none;
  border-radius:12px;
  transition:.3s;
  white-space:nowrap;
  font-weight: 500;
  border: 1px solid transparent;
}

.sidebar a:hover{
  background:linear-gradient(135deg,var(--primary),var(--secondary));
  color:#fff;
  transform:translateX(10px);
  border-color: rgba(255,255,255,0.1);
}

.sidebar a.active{
  background:linear-gradient(135deg,var(--primary),var(--secondary));
  color:#fff;
  transform:translateX(10px);
  box-shadow: 0 5px 15px rgba(99,102,241,0.3);
}

.sidebar a i {
  width: 20px;
  text-align: center;
  font-size: 1.2rem;
}

.logout-btn {
  margin-top: 30px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.logout-btn:hover {
  background: linear-gradient(135deg, #ef4444, #dc2626);
}

/* ===== Main Content ===== */
.main{
  margin-left:300px; /* Adjusted for wider sidebar */
  padding:40px;
  min-height: 100vh;
}

/* ===== Cards ===== */
.card-ui{
  position:relative;
  background:var(--card);
  padding:30px;
  border-radius:20px;
  backdrop-filter:blur(10px);
  box-shadow:0 20px 40px rgba(0,0,0,.45);
  transition:.4s;
  border: 1px solid rgba(255,255,255,0.1);
  height: 100%;
}

.card-ui:hover{
  transform:translateY(-10px);
  box-shadow:0 30px 60px rgba(99,102,241,0.2);
}

.card-title{
  font-size:1rem;
  opacity:.8;
  color: #94a3b8;
  margin-bottom: 10px;
}

.stat{
  font-size:2.8rem;
  font-weight:800;
  margin-top:8px;
  background:linear-gradient(90deg,var(--primary),var(--secondary));
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
}

.card-icon {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
  margin-bottom: 20px;
}

/* Welcome Header */
.welcome-header {
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  padding: 30px;
  border-radius: 20px;
  margin-bottom: 40px;
  color: white;
  box-shadow: 0 10px 30px rgba(99,102,241,0.3);
}

/* Quick Actions */
.quick-actions {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-top: 30px;
}

.action-btn {
  background: var(--card);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 15px;
  padding: 20px;
  text-align: center;
  color: var(--text);
  text-decoration: none;
  transition: all 0.3s;
  display: block;
}

.action-btn:hover {
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  transform: translateY(-5px);
  color: white;
  text-decoration: none;
}

.action-btn i {
  font-size: 2rem;
  margin-bottom: 10px;
  display: block;
}

/* Activity List */
.activity-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.activity-item {
  padding: 15px 0;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  display: flex;
  align-items: center;
  gap: 15px;
}

.activity-item:last-child {
  border-bottom: none;
}

.activity-icon {
  width: 40px;
  height: 40px;
  background: rgba(99,102,241,0.1);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--primary);
}

.activity-text {
  flex: 1;
}

.activity-time {
  font-size: 0.85rem;
  color: #94a3b8;
}

/* Status Badges */
.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  margin-right: 5px;
}

.badge-login { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.badge-logout { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.badge-create { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.badge-update { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.badge-delete { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.badge-view { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }

/* Responsive */
@media (max-width: 1200px) {
  .sidebar {
    width: 250px;
  }
  .main {
    margin-left: 270px;
    padding: 30px;
  }
}

@media (max-width: 768px) {
  .sidebar {
    width: 70px;
    padding: 20px 10px;
  }
  .sidebar h4,
  .sidebar a span {
    display: none;
  }
  .sidebar a {
    justify-content: center;
    padding: 15px 10px;
  }
  .sidebar a i {
    font-size: 1.4rem;
    margin: 0;
  }
  .main {
    margin-left: 90px;
    padding: 20px;
  }
}

@media (max-width: 576px) {
  .sidebar {
    width: 60px;
  }
  .main {
    margin-left: 80px;
    padding: 15px;
  }
  .card-ui {
    padding: 20px;
  }
}
</style>
</head>

<body>
<!-- ===== Sidebar ===== -->
<div class="sidebar">
  <h4>Admin Panel</h4>
  
  <a class="active" href="dashboard.php">
    <i class="fas fa-tachometer-alt"></i>
    <span>Dashboard</span>
  </a>
  
  <a href="studentinfo.php">
    <i class="fas fa-users"></i>
    <span>Student Info</span>
  </a>
  
  <a href="manage_students.php">
    <i class="fas fa-user-graduate"></i>
    <span>Manage Students</span>
  </a>
  
  <a href="admin_user_manage.php">
    <i class="fas fa-chalkboard-teacher"></i>
    <span>Manage Teachers</span>
  </a>
  
  <a href="results.php">
    <i class="fas fa-chart-line"></i>
    <span>Results</span>
  </a>
  
  <a href="result_requests.php">
    <i class="fas fa-exchange-alt"></i>
    <span>Correction Requests</span>
  </a>
  
  <a href="teacher_requests.php">
    <i class="fas fa-tasks"></i>
    <span>Teacher Requests</span>
  </a>
  
  <a href="announcements.php">
    <i class="fas fa-bullhorn"></i>
    <span>Announcements</span>
  </a>
  
  <a href="forum.php">
    <i class="fas fa-comments"></i>
    <span>Manage Forum</span>
  </a>
  
  <a href="audit_dashboard.php">
    <i class="fas fa-cogs"></i>
    <span>Audit_Control</span>
  </a>
  
  <a href="../logout.php" class="logout-btn">
    <i class="fas fa-sign-out-alt"></i>
    <span>Logout</span>
  </a>
</div>

<!-- ===== Main Content ===== -->
<div class="main">
  <!-- Welcome Header -->
  <div class="welcome-header">
    <h1 class="fw-bold mb-3">Welcome Admin 👋</h1>
    <p class="mb-0 opacity-75">Manage your school system efficiently</p>
    <small class="opacity-50">Last login: 
        <?php 
        $last_login = $pdo->query("
            SELECT created_at FROM audit_log 
            WHERE user_id = {$_SESSION['admin_id']} AND action_type = 'LOGIN' 
            ORDER BY created_at DESC LIMIT 1,1
        ")->fetchColumn();
        echo $last_login ? date('M d, Y h:i A', strtotime($last_login)) : 'First login';
        ?>
    </small>
  </div>

  <!-- Statistics Cards -->
  <div class="row g-4 mb-5">
    <div class="col-md-3">
      <div class="card-ui">
        <div class="card-icon">
          <i class="fas fa-users"></i>
        </div>
        <div class="card-title">Total Students</div>
        <div class="stat"><?= number_format($total_students) ?></div>
        <small class="text-muted">Registered students</small>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card-ui">
        <div class="card-icon">
          <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="card-title">Active Teachers</div>
        <div class="stat"><?= number_format($teachers_count) ?></div>
        <small class="text-muted">Teaching staff</small>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card-ui">
        <div class="card-icon">
          <i class="fas fa-book"></i>
        </div>
        <div class="card-title">Subjects</div>
        <div class="stat"><?= number_format($subjects_count) ?></div>
        <small class="text-muted">Available courses</small>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card-ui">
        <div class="card-icon">
          <i class="fas fa-history"></i>
        </div>
        <div class="card-title">Activity Logs</div>
        <div class="stat">
            <?= number_format($pdo->query("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = CURDATE()")->fetchColumn()) ?>
        </div>
        <small class="text-muted">Today's activities</small>
      </div>
    </div>
  </div>

  <!-- Quick Actions & Recent Activities -->
  <div class="row g-4">
    <div class="col-md-6">
      <div class="card-ui">
        <h5 class="fw-bold mb-4">
          <i class="fas fa-bolt me-2"></i>Quick Actions
        </h5>
        <div class="quick-actions">
          <a href="studentinfo.php" class="action-btn">
            <i class="fas fa-users"></i>
            View All Students
          </a>
          
          <a href="manage_students.php?action=add" class="action-btn">
            <i class="fas fa-user-plus"></i>
            Add New Student
          </a>
          
          <a href="admin_user_manage.php?action=add" class="action-btn">
            <i class="fas fa-user-plus"></i>
            Add New Teacher
          </a>
          <a href="audit_dashboard.php" class="action-btn"> audit control</a>
          <a href="results.php?action=add" class="action-btn">
            <i class="fas fa-plus-circle"></i>
            Add Results
          </a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6">
      <div class="card-ui">
        <h5 class="fw-bold mb-4">
          <i class="fas fa-history me-2"></i>Recent System Activities
        </h5>
        <ul class="activity-list">
          <?php if (empty($recent_activities)): ?>
            <li class="text-center py-3 text-muted">
              No recent activities
            </li>
          <?php else: ?>
            <?php foreach ($recent_activities as $activity): ?>
              <li class="activity-item">
                <div class="activity-icon">
                  <?php 
                  $icon = 'fas fa-cog';
                  $badgeClass = 'badge-view';
                  if ($activity['action_type'] == 'LOGIN') {
                      $icon = 'fas fa-sign-in-alt';
                      $badgeClass = 'badge-login';
                  } elseif ($activity['action_type'] == 'LOGOUT') {
                      $icon = 'fas fa-sign-out-alt';
                      $badgeClass = 'badge-logout';
                  } elseif ($activity['action_type'] == 'CREATE') {
                      $icon = 'fas fa-plus-circle';
                      $badgeClass = 'badge-create';
                  } elseif ($activity['action_type'] == 'UPDATE') {
                      $icon = 'fas fa-edit';
                      $badgeClass = 'badge-update';
                  } elseif ($activity['action_type'] == 'DELETE') {
                      $icon = 'fas fa-trash';
                      $badgeClass = 'badge-delete';
                  }
                  ?>
                  <i class="<?= $icon ?>"></i>
                </div>
                <div class="activity-text">
                  <div class="fw-medium">
                    <span class="status-badge <?= $badgeClass ?>">
                      <?= $activity['action_type'] ?>
                    </span>
                    <?= htmlspecialchars($activity['user_name'] ?: 'System') ?>
                  </div>
                  <div class="text-muted small">
                    <?= htmlspecialchars(substr($activity['description'] ?? '', 0, 50)) ?>...
                  </div>
                  <div class="activity-time">
                    <?= date('M d, Y h:i A', strtotime($activity['created_at'])) ?>
                    <?php if($activity['user_role']): ?>
                      | <span class="text-info"><?= strtoupper($activity['user_role']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
        <div class="text-center mt-3">
          <a href="audit_dashboard.php" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-external-link-alt me-1"></i>View All Activities
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- System Status -->
  <div class="row mt-4">
    <div class="col-12">
      <div class="card-ui">
        <h5 class="fw-bold mb-4">
          <i class="fas fa-server me-2"></i>System Status
        </h5>
        <div class="row">
          <div class="col-md-4">
            <div class="d-flex align-items-center mb-3">
              <div class="me-3">
                <div class="rounded-circle bg-success" style="width: 12px; height: 12px;"></div>
              </div>
              <div>
                <div class="fw-medium">Database</div>
                <small class="text-muted">Connected and running</small>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="d-flex align-items-center mb-3">
              <div class="me-3">
                <div class="rounded-circle bg-success" style="width: 12px; height: 12px;"></div>
              </div>
              <div>
                <div class="fw-medium">Audit Logging</div>
                <small class="text-muted">
                  <?= $pdo->query("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?> logs today
                </small>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="d-flex align-items-center mb-3">
              <div class="me-3">
                <div class="rounded-circle bg-success" style="width: 12px; height: 12px;"></div>
              </div>
              <div>
                <div class="fw-medium">Last Backup</div>
                <small class="text-muted"><?= date('M d, Y') ?> 02:00 AM</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-refresh dashboard every 5 minutes
setTimeout(() => {
    window.location.reload();
}, 300000);

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});

// Current time display
function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit'
    });
    if(document.getElementById('currentTime')) {
        document.getElementById('currentTime').textContent = timeString;
    }
}

setInterval(updateTime, 1000);
updateTime(); // Initial call
</script>
</body>
</html>