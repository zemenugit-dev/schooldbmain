<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors',1);
require 'auth.php';
require '../config/pdo_connect.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Fetch students grouped by grade_level and section
$students_query = $pdo->query("
    SELECT 
        grade_level,
        section,
        COUNT(*) as total_students,
        GROUP_CONCAT(CONCAT_WS('|', student_id, full_name, reg_number, email) ORDER BY full_name) as student_data
    FROM students
    WHERE grade_level IS NOT NULL AND grade_level != ''
    GROUP BY grade_level, section
    ORDER BY 
        CAST(SUBSTRING(grade_level, 7) AS UNSIGNED) DESC,
        grade_level ASC,
        section ASC
");
$grade_groups = $students_query->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_students = 0;
$grade_stats = [];
$section_stats = [];

foreach ($grade_groups as $group) {
    $total_students += $group['total_students'];
    
    // Grade level stats
    if (!isset($grade_stats[$group['grade_level']])) {
        $grade_stats[$group['grade_level']] = 0;
    }
    $grade_stats[$group['grade_level']] += $group['total_students'];
    
    // Section stats
    $key = $group['grade_level'] . ' - ' . $group['section'];
    $section_stats[$key] = $group['total_students'];
}

// Fetch other dashboard statistics
$teachers_count = $pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'active'")->fetchColumn();
$subjects_count = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
$pending_requests = $pdo->query("SELECT COUNT(*) FROM result_correction_requests WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - Student Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

<style>
:root {
    --bg: #020617;
    --sidebar: #020617;
    --card: rgba(17,24,39,.85);
    --primary: #6366f1;
    --secondary: #22d3ee;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --text: #e5e7eb;
}

* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #020617, #0b1120);
    color: var(--text);
}

/* ===== Sidebar ===== */
.sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    width: 260px;
    padding: 30px 20px;
    background: linear-gradient(180deg, #020617, #020617dd);
    backdrop-filter: blur(12px);
    transition: .3s;
    z-index: 1000;
}

.sidebar.collapsed { width: 85px; }

.sidebar h4 {
    color: #fff;
    font-weight: 700;
    margin-bottom: 40px;
    text-align: center;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    margin-bottom: 12px;
    color: #c7d2fe;
    text-decoration: none;
    border-radius: 14px;
    transition: .3s;
    white-space: nowrap;
}

.sidebar a:hover,
.sidebar a.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #fff;
    transform: translateX(6px);
}

.sidebar.collapsed a span { display: none; }

/* ===== Toggle ===== */
.toggle-btn {
    position: absolute;
    top: 20px;
    right: -18px;
    background: var(--primary);
    border-radius: 50%;
    width: 36px;
    height: 36px;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 1001;
}

/* ===== Main ===== */
.main {
    margin-left: 280px;
    padding: 40px;
    transition: .3s;
}

.main.full { margin-left: 105px; }

/* ===== Cards ===== */
.card-ui {
    position: relative;
    background: var(--card);
    padding: 28px;
    border-radius: 22px;
    backdrop-filter: blur(10px);
    box-shadow: 0 25px 50px rgba(0,0,0,.45);
    transition: .4s;
    border: 1px solid rgba(255,255,255,0.1);
}

.card-ui:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 30px 60px rgba(99,102,241,0.2);
}

.card-title {
    font-size: 1rem;
    opacity: .8;
    color: #94a3b8;
}

.stat {
    font-size: 2.5rem;
    font-weight: 800;
    margin-top: 8px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* Grade Group Cards */
.grade-card {
    background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(139,92,246,0.1));
    border: 1px solid rgba(99,102,241,0.2);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 25px;
    transition: all 0.3s ease;
}

.grade-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
    box-shadow: 0 15px 40px rgba(99,102,241,0.15);
}

.grade-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid rgba(99,102,241,0.2);
}

.grade-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    display: flex;
    align-items: center;
    gap: 10px;
}

.grade-badge {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Section Tabs */
.section-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.section-tab {
    padding: 8px 20px;
    background: rgba(255,255,255,0.05);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    color: #c7d2fe;
    font-weight: 500;
    border: 1px solid rgba(255,255,255,0.1);
}

.section-tab:hover {
    background: rgba(99,102,241,0.2);
    color: white;
}

.section-tab.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
}

/* Student Table */
.student-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: rgba(17,24,39,0.7);
    border-radius: 12px;
    overflow: hidden;
}

.student-table th {
    background: linear-gradient(135deg, rgba(99,102,241,0.3), rgba(139,92,246,0.3));
    color: white;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid rgba(99,102,241,0.3);
}

.student-table td {
    padding: 12px 15px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: #e2e8f0;
}

.student-table tr:hover {
    background: rgba(99,102,241,0.1);
}

.student-table tr:last-child td {
    border-bottom: none;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-item {
    text-align: center;
    padding: 20px;
    background: rgba(17,24,39,0.5);
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.1);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 5px;
}

.stat-label {
    color: #94a3b8;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .main { padding: 20px; }
}

@media (max-width: 768px) {
    .sidebar { width: 85px; }
    .sidebar.collapsed { width: 0; padding: 0; }
    .main { margin-left: 100px; }
    .main.full { margin-left: 20px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .section-tabs { overflow-x: auto; flex-wrap: nowrap; }
}

/* Loading Animation */
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: var(--primary);
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
</head>

<body>
<!-- ===== Sidebar ===== -->
<div class="sidebar" id="sidebar">
    <div class="toggle-btn" onclick="toggleSidebar()">☰</div>
    <h4>Admin Panel</h4>
    <a class="active" href="dashboard.php">📊 <span>Dashboard</span></a>
    <a href="manage_students.php">🎓 <span>Students</span></a>
    <a href="forum.php">📝 <span>Manage Forum</span></a>
    <a href="announcements.php">📢 <span>Announcements</span></a>
    <a href="result_requests.php">📊 <span>Result Requests</span></a>
    <a href="results.php">📈 <span>Results</span></a>
    <a href="admin_user_manage.php">👨‍🏫 <span>Teachers</span></a>
    <a href="teacher_requests.php">📨 <span>Teacher Requests</span></a>
    <a href="../logout.php" class="logout-btn">
        <span>🚪</span>
        <span>Logout</span>
    </a>
</div>

<!-- ===== Main Content ===== -->
<div class="main" id="main">
    <!-- Welcome Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h1 class="fw-bold">Welcome Admin 👋</h1>
            <p class="text-muted mb-0">Manage students, teachers, and academic activities</p>
        </div>
        <div class="text-end">
            <div class="text-muted small">Last updated: <?= date('M d, Y h:i A') ?></div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid mb-5">
        <div class="card-ui">
            <div class="card-title">Total Students</div>
            <div class="stat"><?= number_format($total_students) ?></div>
            <small class="text-muted">Across all grades</small>
        </div>
        
        <div class="card-ui">
            <div class="card-title">Active Teachers</div>
            <div class="stat"><?= number_format($teachers_count) ?></div>
            <small class="text-muted">Teaching staff</small>
        </div>
        
        <div class="card-ui">
            <div class="card-title">Subjects</div>
            <div class="stat"><?= number_format($subjects_count) ?></div>
            <small class="text-muted">Available courses</small>
        </div>
        
        <div class="card-ui">
            <div class="card-title">Pending Requests</div>
            <div class="stat"><?= number_format($pending_requests) ?></div>
            <small class="text-muted">Requiring attention</small>
        </div>
    </div>

    <!-- Grade Level Distribution -->
    <div class="card-ui mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold">📚 Grade Level Distribution</h3>
            <button class="btn btn-sm btn-outline-primary" onclick="exportStudentData()">
                <i class="fas fa-download me-2"></i>Export Data
            </button>
        </div>
        
        <div class="row mb-4">
            <?php foreach ($grade_stats as $grade => $count): ?>
                <div class="col-md-3 mb-3">
                    <div class="stat-item">
                        <div class="stat-value"><?= $count ?></div>
                        <div class="stat-label"><?= htmlspecialchars($grade) ?> Students</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Students by Grade & Section -->
    <div class="card-ui">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold">🎓 Students by Grade & Section</h3>
            <div class="text-muted">
                <i class="fas fa-info-circle me-2"></i>
                Click on section to view students
            </div>
        </div>

        <?php if (empty($grade_groups)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Students Found</h4>
                <p>Add students to see them organized by grade and section</p>
            </div>
        <?php else: ?>
            <?php foreach ($grade_groups as $group): ?>
                <div class="grade-card">
                    <div class="grade-header">
                        <div class="grade-title">
                            <i class="fas fa-graduation-cap"></i>
                            <?= htmlspecialchars($group['grade_level']) ?> - Section <?= htmlspecialchars($group['section']) ?>
                        </div>
                        <div class="grade-badge">
                            <?= $group['total_students'] ?> Students
                        </div>
                    </div>

                    <!-- Students Table -->
                    <div class="table-responsive">
                        <table class="student-table">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="20%">Registration No</th>
                                    <th width="30%">Full Name</th>
                                    <th width="25%">Email</th>
                                    <th width="20%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $students = explode(',', $group['student_data']);
                                foreach ($students as $index => $student_data):
                                    $student_info = explode('|', $student_data);
                                    if (count($student_info) >= 4):
                                        list($student_id, $full_name, $reg_number, $email) = $student_info;
                                ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($reg_number) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($full_name) ?></td>
                                        <td>
                                            <?php if ($email): ?>
                                                <a href="mailto:<?= htmlspecialchars($email) ?>" class="text-primary">
                                                    <?= htmlspecialchars($email) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No email</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info me-2" 
                                                    onclick="viewStudent(<?= $student_id ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning me-2"
                                                    onclick="editStudent(<?= $student_id ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger"
                                                    onclick="deleteStudent(<?= $student_id ?>, '<?= htmlspecialchars($full_name) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Section Summary -->
                    <div class="mt-3 text-end">
                        <small class="text-muted">
                            <i class="fas fa-chart-pie me-1"></i>
                            <?= $group['total_students'] ?> student(s) in <?= htmlspecialchars($group['grade_level']) ?> - Section <?= htmlspecialchars($group['section']) ?>
                        </small>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Quick Stats -->
    <div class="row mt-5">
        <div class="col-md-6">
            <div class="card-ui">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-layer-group me-2"></i>Grade Summary
                </h5>
                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Grade Level</th>
                                <th>Sections</th>
                                <th>Students</th>
                                <th>% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grade_summary = [];
                            foreach ($grade_groups as $group) {
                                if (!isset($grade_summary[$group['grade_level']])) {
                                    $grade_summary[$group['grade_level']] = [
                                        'sections' => 0,
                                        'students' => 0
                                    ];
                                }
                                $grade_summary[$group['grade_level']]['sections']++;
                                $grade_summary[$group['grade_level']]['students'] += $group['total_students'];
                            }
                            
                            foreach ($grade_summary as $grade => $data):
                                $percentage = $total_students > 0 ? round(($data['students'] / $total_students) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($grade) ?></strong></td>
                                <td><?= $data['sections'] ?></td>
                                <td><?= $data['students'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                            <div class="progress-bar bg-primary" 
                                                 style="width: <?= $percentage ?>%"></div>
                                        </div>
                                        <span><?= $percentage ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card-ui">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-chart-bar me-2"></i>Section Distribution
                </h5>
                <div id="sectionChart"></div>
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Showing all active sections with student counts
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Toggle Sidebar
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('main').classList.toggle('full');
}

// View Student Details
function viewStudent(studentId) {
    window.location.href = `student_details.php?id=${studentId}`;
}

// Edit Student
function editStudent(studentId) {
    window.location.href = `edit_student.php?id=${studentId}`;
}

// Delete Student
function deleteStudent(studentId, studentName) {
    if (confirm(`Are you sure you want to delete ${studentName}? This action cannot be undone.`)) {
        // AJAX request to delete student
        $.ajax({
            url: 'delete_student.php',
            method: 'POST',
            data: { student_id: studentId },
            success: function(response) {
                const result = JSON.parse(response);
                if (result.success) {
                    alert(`Student ${studentName} deleted successfully!`);
                    location.reload();
                } else {
                    alert(`Error: ${result.message}`);
                }
            },
            error: function() {
                alert('Error deleting student. Please try again.');
            }
        });
    }
}

// Export Student Data
function exportStudentData() {
    window.location.href = 'export_students.php?format=excel';
}

// Initialize DataTables
$(document).ready(function() {
    // Initialize all student tables
    $('.student-table').DataTable({
        responsive: true,
        paging: true,
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        language: {
            search: "Search students:",
            lengthMenu: "Show _MENU_ students",
            info: "Showing _START_ to _END_ of _TOTAL_ students",
            infoEmpty: "No students found",
            infoFiltered: "(filtered from _MAX_ total students)"
        }
    });
    
    // Create Section Distribution Chart
    const sectionStats = <?= json_encode($section_stats) ?>;
    const sections = Object.keys(sectionStats);
    const studentCounts = Object.values(sectionStats);
    
    const ctx = document.createElement('canvas');
    document.getElementById('sectionChart').appendChild(ctx);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: sections,
            datasets: [{
                label: 'Number of Students',
                data: studentCounts,
                backgroundColor: [
                    'rgba(99, 102, 241, 0.7)',
                    'rgba(139, 92, 246, 0.7)',
                    'rgba(34, 211, 238, 0.7)',
                    'rgba(16, 185, 129, 0.7)',
                    'rgba(245, 158, 11, 0.7)',
                    'rgba(239, 68, 68, 0.7)'
                ],
                borderColor: [
                    'rgb(99, 102, 241)',
                    'rgb(139, 92, 246)',
                    'rgb(34, 211, 238)',
                    'rgb(16, 185, 129)',
                    'rgb(245, 158, 11)',
                    'rgb(239, 68, 68)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Students per Section',
                    color: '#fff'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#94a3b8'
                    },
                    grid: {
                        color: 'rgba(255,255,255,0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#94a3b8',
                        maxRotation: 45
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    // Auto-refresh dashboard every 60 seconds
    setInterval(function() {
        $.ajax({
            url: 'dashboard_stats.php',
            method: 'GET',
            success: function(data) {
                const stats = JSON.parse(data);
                // Update stats dynamically if needed
                console.log('Dashboard stats updated:', stats);
            }
        });
    }, 60000);
    
    // Search functionality
    $('#searchStudents').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.grade-card').each(function() {
            const cardText = $(this).text().toLowerCase();
            if (cardText.indexOf(searchTerm) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + / to search
    if (e.ctrlKey && e.key === '/') {
        e.preventDefault();
        $('#searchStudents').focus();
    }
    
    // Escape to clear search
    if (e.key === 'Escape') {
        $('#searchStudents').val('').trigger('keyup');
    }
});

// Print student list
function printStudentList(grade, section) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Student List - ${grade} Section ${section}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    h1 { color: #333; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                </style>
            </head>
            <body>
                <h1>${grade} - Section ${section} - Student List</h1>
                <p>Printed on: ${new Date().toLocaleDateString()}</p>
                ${$(`.grade-card:contains('${grade} - Section ${section}') .table-responsive`).html()}
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>

<!-- Search Bar (Optional - add if you want a global search) -->
<div class="position-fixed bottom-20 end-20" style="z-index: 1000;">
    <div class="input-group shadow-lg">
        <input type="text" id="searchStudents" class="form-control" 
               placeholder="Search students across all grades...">
        <button class="btn btn-primary" type="button">
            <i class="fas fa-search"></i>
        </button>
    </div>
</div>

</body>
</html>