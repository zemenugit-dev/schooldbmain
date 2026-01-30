<?php
session_start();
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
        GROUP_CONCAT(CONCAT_WS('|', student_id, full_name, reg_number, email, created_at) ORDER BY full_name) as student_data
    FROM students
    WHERE grade_level IS NOT NULL AND grade_level != ''
    GROUP BY grade_level, section
    ORDER BY 
        CASE 
            WHEN grade_level LIKE '%12%' THEN 1
            WHEN grade_level LIKE '%11%' THEN 2
            WHEN grade_level LIKE '%10%' THEN 3
            WHEN grade_level LIKE '%9%' THEN 4
            WHEN grade_level LIKE '%8%' THEN 5
            WHEN grade_level LIKE '%7%' THEN 6
            WHEN grade_level LIKE '%6%' THEN 7
            ELSE 8
        END,
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
    $key = $group['grade_level'] . ' - Section ' . $group['section'];
    $section_stats[$key] = $group['total_students'];
}

// Sort grade stats by grade level
uksort($grade_stats, function($a, $b) {
    return strnatcmp($a, $b);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Information - Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
    min-height: 100vh;
}

/* Back to Dashboard Button */
.back-header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    padding: 25px 40px;
    color: white;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 5px 20px rgba(99,102,241,0.3);
}

.back-btn {
    background: rgba(255,255,255,0.1);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 10px 25px;
    border-radius: 10px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
    font-weight: 500;
}

.back-btn:hover {
    background: rgba(255,255,255,0.2);
    color: white;
    transform: translateX(-5px);
    text-decoration: none;
}

/* Main Container */
.container-fluid {
    padding: 0 40px 40px;
}

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--card);
    border-radius: 15px;
    padding: 25px;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.1);
    transition: all 0.3s;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(99,102,241,0.2);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 10px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.stat-label {
    color: #94a3b8;
    font-size: 0.95rem;
}

/* Grade Group Cards */
.grade-card {
    background: var(--card);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    border: 1px solid rgba(255,255,255,0.1);
    box-shadow: 0 15px 35px rgba(0,0,0,0.25);
    transition: all 0.3s;
}

.grade-card:hover {
    border-color: var(--primary);
    box-shadow: 0 20px 45px rgba(99,102,241,0.15);
}

.grade-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 2px solid rgba(99,102,241,0.3);
}

.grade-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    display: flex;
    align-items: center;
    gap: 15px;
}

.grade-title i {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.grade-badge {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 8px 20px;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.95rem;
    box-shadow: 0 5px 15px rgba(99,102,241,0.3);
}

/* Student Table */
.student-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: rgba(30,41,59,0.5);
    border-radius: 12px;
    overflow: hidden;
    margin-top: 20px;
}

.student-table th {
    background: linear-gradient(135deg, rgba(99,102,241,0.3), rgba(139,92,246,0.3));
    color: white;
    padding: 18px 20px;
    text-align: left;
    font-weight: 600;
    border: none;
    font-size: 0.95rem;
}

.student-table td {
    padding: 15px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: #e2e8f0;
    vertical-align: middle;
}

.student-table tr:hover {
    background: rgba(99,102,241,0.1);
}

.student-table tr:last-child td {
    border-bottom: none;
}

/* Action Buttons */
.btn-action {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    transition: all 0.3s;
    margin: 0 3px;
}

.btn-view {
    background: rgba(34, 211, 238, 0.1);
    color: #22d3ee;
    border: 1px solid rgba(34, 211, 238, 0.3);
}

.btn-view:hover {
    background: #22d3ee;
    color: white;
}

.btn-edit {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.btn-edit:hover {
    background: #f59e0b;
    color: white;
}

.btn-delete {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.btn-delete:hover {
    background: #ef4444;
    color: white;
}

/* Filter Controls */
.filter-container {
    background: var(--card);
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 30px;
    border: 1px solid rgba(255,255,255,0.1);
}

.search-box {
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 12px 20px 12px 45px;
    background: rgba(30,41,59,0.7);
    border: 1px solid rgba(99,102,241,0.3);
    border-radius: 10px;
    color: white;
    font-size: 1rem;
}

.search-box i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}

/* Grade Summary */
.grade-summary {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.grade-tab {
    padding: 12px 25px;
    background: rgba(30,41,59,0.5);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    color: #c7d2fe;
    font-weight: 500;
    border: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
}

.grade-tab:hover {
    background: rgba(99,102,241,0.2);
    color: white;
}

.grade-tab.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    box-shadow: 0 5px 15px rgba(99,102,241,0.3);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 4rem;
    color: #475569;
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 1200px) {
    .container-fluid {
        padding: 0 20px 20px;
    }
}

@media (max-width: 768px) {
    .back-header {
        padding: 20px;
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .grade-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .student-table {
        font-size: 0.9rem;
    }
}

@media (max-width: 576px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .grade-summary {
        justify-content: center;
    }
    
    .student-table th,
    .student-table td {
        padding: 12px 15px;
    }
}

/* Print Styles */
@media print {
    .back-header,
    .filter-container,
    .grade-summary,
    .btn-action {
        display: none;
    }
    
    .grade-card {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
</style>
</head>

<body>
<!-- Back to Dashboard Header -->
<div class="back-header">
    <div>
        <h1 class="fw-bold mb-2">🎓 Student Information Dashboard</h1>
        <p class="mb-0 opacity-75">View and manage all students organized by grade and section</p>
    </div>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i>
        Back to Dashboard
    </a>
    
</div>

<div class="container-fluid">
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= number_format($total_students) ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?= count($grade_groups) ?></div>
            <div class="stat-label">Grade Sections</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?= count($grade_stats) ?></div>
            <div class="stat-label">Grade Levels</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number">
                <?php if (count($grade_stats) > 0): ?>
                    <?= number_format($total_students / count($grade_stats), 1) ?>
                <?php else: ?>
                    0
                <?php endif; ?>
            </div>
            <div class="stat-label">Avg per Grade</div>
        </div>
    </div>

    <!-- Filter Controls -->
    <div class="filter-container">
        <div class="row">
            <div class="col-md-8">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchStudents" placeholder="Search students by name, registration number, or email...">
                </div>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" onclick="printPage()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
                <button class="btn btn-success flex-grow-1" onclick="exportToExcel()">
                    <i class="fas fa-download me-2"></i>Export
                </button>
            </div>
        </div>
    </div>

    <!-- Grade Navigation -->
    <div class="grade-summary">
        <?php foreach ($grade_stats as $grade => $count): ?>
            <div class="grade-tab" onclick="scrollToGrade('<?= htmlspecialchars($grade) ?>')">
                <span><?= htmlspecialchars($grade) ?></span>
                <span class="badge bg-white text-dark"><?= $count ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Students by Grade & Section -->
    <?php if (empty($grade_groups)): ?>
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <h3 class="text-muted mb-3">No Students Found</h3>
            <p class="text-muted">Add students to see them organized by grade and section</p>
            <a href="manage_students.php?action=add" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i>Add New Student
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($grade_groups as $group): ?>
            <div class="grade-card" id="grade-<?= htmlspecialchars(str_replace(' ', '-', $group['grade_level'])) ?>">
                <div class="grade-header">
                    <div class="grade-title">
                        <i class="fas fa-graduation-cap"></i>
                        <div>
                            <?= htmlspecialchars($group['grade_level']) ?> - Section <?= htmlspecialchars($group['section']) ?>
                            <div class="text-muted mt-1" style="font-size: 0.9rem;">
                                <i class="fas fa-calendar me-1"></i>
                                Updated: <?= date('M d, Y') ?>
                            </div>
                        </div>
                    </div>
                    <div class="grade-badge">
                        <i class="fas fa-user-graduate me-2"></i>
                        <?= $group['total_students'] ?> Student<?= $group['total_students'] != 1 ? 's' : '' ?>
                    </div>
                </div>

                <!-- Students Table -->
                <div class="table-responsive">
                    <table class="student-table">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="15%">Reg. Number</th>
                                <th width="25%">Full Name</th>
                                <th width="25%">Email</th>
                                <th width="15%">Joined Date</th>
                                <th width="15%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $students = explode(',', $group['student_data']);
                            foreach ($students as $index => $student_data):
                                $student_info = explode('|', $student_data);
                                if (count($student_info) >= 5):
                                    list($student_id, $full_name, $reg_number, $email, $created_at) = $student_info;
                            ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <strong class="text-primary"><?= htmlspecialchars($reg_number) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($full_name) ?></td>
                                    <td>
                                        <?php if ($email): ?>
                                            <a href="mailto:<?= htmlspecialchars($email) ?>" class="text-info">
                                                <?= htmlspecialchars($email) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No email</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('M d, Y', strtotime($created_at)) ?>
                                    </td>
                                    <td>
                                        <button class="btn-action btn-view" onclick="viewStudent(<?= $student_id ?>)"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-action btn-edit" onclick="editStudent(<?= $student_id ?>)"
                                                title="Edit Student">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-action btn-delete" 
                                                onclick="deleteStudent(<?= $student_id ?>, '<?= htmlspecialchars(addslashes($full_name)) ?>')"
                                                title="Delete Student">
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
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
// Initialize DataTables
$(document).ready(function() {
    // Initialize all student tables
    $('.student-table').DataTable({
        responsive: true,
        paging: false,
        searching: false,
        info: false,
        ordering: false
    });
    
    // Global search functionality
    $('#searchStudents').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase().trim();
        
        if (searchTerm.length === 0) {
            $('.grade-card').show();
            return;
        }
        
        $('.grade-card').each(function() {
            const $card = $(this);
            const cardText = $card.text().toLowerCase();
            
            // Check if card contains search term
            if (cardText.indexOf(searchTerm) > -1) {
                $card.show();
                
                // Highlight matching text in the table
                $card.find('td').each(function() {
                    const $td = $(this);
                    const text = $td.text();
                    const html = $td.html();
                    
                    if (text.toLowerCase().indexOf(searchTerm) > -1 && searchTerm.length >= 2) {
                        const regex = new RegExp(`(${searchTerm})`, 'gi');
                        const highlighted = html.replace(regex, '<mark>$1</mark>');
                        $td.html(highlighted);
                    }
                });
            } else {
                $card.hide();
            }
        });
    });
    
    // Clear highlights when search is cleared
    $('#searchStudents').on('search', function() {
        $('mark').each(function() {
            $(this).replaceWith($(this).text());
        });
    });
});

// Navigation functions
function scrollToGrade(grade) {
    const elementId = 'grade-' + grade.replace(/ /g, '-');
    const element = document.getElementById(elementId);
    
    if (element) {
        // Update active tab
        $('.grade-tab').removeClass('active');
        $(`.grade-tab:contains('${grade}')`).addClass('active');
        
        // Scroll to element
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
        
        // Highlight the card
        element.style.boxShadow = '0 0 0 3px rgba(99,102,241,0.5)';
        setTimeout(() => {
            element.style.boxShadow = '';
        }, 2000);
    }
}

// Student actions
function viewStudent(studentId) {
    window.location.href = `student_details.php?id=${studentId}`;
}

function editStudent(studentId) {
    window.location.href = `edit_student.php?id=${studentId}`;
}

function deleteStudent(studentId, studentName) {
    if (confirm(`Are you sure you want to delete "${studentName}"? This action cannot be undone.`)) {
        // AJAX request to delete student
        $.ajax({
            url: 'delete_student.php',
            method: 'POST',
            data: { 
                student_id: studentId,
                _token: '<?= md5(session_id()) ?>' // Simple CSRF protection
            },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        alert(`Student "${studentName}" deleted successfully!`);
                        location.reload();
                    } else {
                        alert(`Error: ${result.message}`);
                    }
                } catch (e) {
                    alert('Success! Page will reload...');
                    location.reload();
                }
            },
            error: function(xhr, status, error) {
                alert('Error deleting student. Please try again.');
                console.error('Delete error:', error);
            }
        });
    }
}

// Export and Print functions
function printPage() {
    window.print();
}

function exportToExcel() {
    // Simple Excel export (you can enhance this with a proper library)
    let csv = 'Grade Level,Section,Registration No,Full Name,Email,Joined Date\n';
    
    $('.grade-card').each(function() {
        const gradeSection = $(this).find('.grade-title').text().trim();
        const gradeParts = gradeSection.split(' - Section ');
        
        $(this).find('.student-table tbody tr').each(function() {
            const cols = $(this).find('td');
            if (cols.length >= 5) {
                const regNumber = $(cols[1]).text().trim();
                const fullName = $(cols[2]).text().trim();
                const email = $(cols[3]).text().trim();
                const joinDate = $(cols[4]).text().trim();
                
                csv += `"${gradeParts[0]}","${gradeParts[1] || ''}","${regNumber}","${fullName}","${email}","${joinDate}"\n`;
            }
        });
    });
    
    // Create and download CSV file
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `students_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+F to focus search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        $('#searchStudents').focus();
    }
    
    // Escape to clear search
    if (e.key === 'Escape') {
        $('#searchStudents').val('').trigger('keyup').blur();
    }
});

// Auto-refresh every 30 minutes
setTimeout(() => {
    if (confirm('Refresh student data?')) {
        location.reload();
    }
}, 1800000);
</script>
</body>
</html>