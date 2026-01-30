<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config/pdo_connect.php';

// ✅ Admin session check
if(!isset($_SESSION['admin'])){
    header("Location: login.php");
    exit;
}

// Initialize messages
$success = '';
$error = '';

// --- Handle Add/Edit/Delete ---
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $student_id    = (int)$_POST['student_id'];
    $subject_id    = (int)$_POST['subject_id'];
    $marks         = (float)$_POST['marks'];
    $semester      = (int)$_POST['semester'];
    $academic_year = trim($_POST['academic_year']);
    $edit_id       = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

    if($student_id <=0 || $subject_id <=0 || $marks <0 || $marks >100 || $semester <=0 || empty($academic_year)){
        $error = "❌ Please fill all fields correctly (marks: 0-100)";
    } else {
        try {
            if($edit_id > 0){
                // Edit existing result
                $stmt = $pdo->prepare("UPDATE results SET student_id=?, subject_id=?, marks=?, semester=?, academic_year=? WHERE result_id=?");
                $stmt->execute([$student_id, $subject_id, $marks, $semester, $academic_year, $edit_id]);
                $_SESSION['success'] = "✅ Result updated successfully!";
            } else {
                // Insert new result
                $stmt = $pdo->prepare("INSERT INTO results (student_id, subject_id, marks, semester, academic_year) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $subject_id, $marks, $semester, $academic_year]);
                $_SESSION['success'] = "✅ Result added successfully!";
            }

            header("Location: results.php");
            exit();
        } catch(PDOException $e){
            $error = "❌ Database error: ".$e->getMessage();
        }
    }
}

// Handle Delete
if(isset($_GET['delete_id'])){
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM results WHERE result_id=?");
        $stmt->execute([$delete_id]);
        $_SESSION['success'] = "✅ Result deleted successfully!";
        header("Location: results.php");
        exit();
    } catch(PDOException $e){
        $error = "❌ Database error: ".$e->getMessage();
    }
}

// --- Fetch data for forms ---
$students_stmt = $pdo->query("SELECT student_id, full_name, reg_number FROM students ORDER BY full_name ASC");
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

$subjects_stmt = $pdo->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC");
$subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

$results_stmt = $pdo->query("
    SELECT r.*, s.full_name AS student_name, s.reg_number, sub.subject_name
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    JOIN subjects sub ON r.subject_id = sub.subject_id
    ORDER BY r.result_id DESC
");
$results = $results_stmt->fetchAll(PDO::FETCH_ASSOC);

// Display session message (PRG)
if(isset($_SESSION['success'])){
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Results Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{
    font-family:'Poppins',sans-serif;
    background: linear-gradient(135deg,#020617,#0b1120);
    color:#fff;
    min-height:100vh;
}
.container{margin-top:50px;}
.card{
    background: rgba(17,24,39,0.95);
    padding:20px;
    border-radius:15px;
    margin-bottom:20px;
    box-shadow:0 15px 40px rgba(0,0,0,0.5);
}
h2{text-align:center;color:#6366f1;margin-bottom:20px;}
label{display:block;margin-bottom:6px;color:#e5e7eb;}
input, select{background:#1f2937;color:#fff;border:none;border-radius:6px;padding:10px;width:100%;margin-bottom:12px;}
select option{color:#000;}
input:focus, select:focus{outline:none;box-shadow:0 0 0 2px #6366f1;}
button{
    background: linear-gradient(135deg,#6366f1,#22d3ee);
    border:none;width:100%;padding:12px;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;transition:all 0.3s ease;margin-bottom:12px;
}
button:hover{background: linear-gradient(135deg,#4f46e5,#06b6d4);}
.msg{text-align:center;margin-bottom:10px;}
.success{color:#22c55e;}
.error{color:#ef4444;}
.table thead{background:#111827;color:#fff;}
.table tbody tr{background:#1f2937;color:#fff;}
.table tbody tr td, .table thead th{vertical-align:middle;}
.action-btn{margin-right:5px;}
</style>
<script>
function populateEdit(result){
    document.getElementById('student_id').value = result.student_id;
    document.getElementById('subject_id').value = result.subject_id;
    document.getElementById('marks').value = result.marks;
    document.getElementById('semester').value = result.semester;
    document.getElementById('academic_year').value = result.academic_year;
    document.getElementById('edit_id').value = result.result_id;
    window.scrollTo({top:0, behavior:'smooth'});
}
function confirmDelete(){
    return confirm('Are you sure you want to delete this result?');
}
</script>
</head>
<body>
<div class="container">
    <h2>📝 Manage Student Results</h2>

    <?php if($error): ?><p class="msg error"><?= $error ?></p><?php endif; ?>
    <?php if($success): ?><p class="msg success"><?= $success ?></p><?php endif; ?>

    <!-- Add/Edit Form -->
    <div class="card">
        <form method="POST">
            <input type="hidden" name="edit_id" id="edit_id" value="0">
            <label>Student</label>
            <select name="student_id" id="student_id" required>
                <option value="">Select Student</option>
                <?php foreach($students as $s): ?>
                    <option value="<?= $s['student_id'] ?>"><?= htmlspecialchars($s['full_name'].' ('.$s['reg_number'].')') ?></option>
                <?php endforeach; ?>
            </select>

            <label>Subject</label>
            <select name="subject_id" id="subject_id" required>
                <option value="">Select Subject</option>
                <?php foreach($subjects as $sub): ?>
                    <option value="<?= $sub['subject_id'] ?>"><?= htmlspecialchars($sub['subject_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Marks (0-100)</label>
            <input type="number" name="marks" id="marks" min="0" max="100" step="0.01" required>

            <label>Semester</label>
            <select name="semester" id="semester" required>
                <option value="">Select Semester</option>
                <option value="1">Semester 1</option>
                <option value="2">Semester 2</option>
            </select>

            <label>Academic Year</label>
            <input type="text" name="academic_year" id="academic_year" placeholder="2025-2026" required>

            <button type="submit">Save Result</button>
        </form>
    </div>

    <!-- Results Table -->
    <div class="card">
        <table class="table table-striped table-hover text-white">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Subject</th>
                    <th>Marks</th>
                    <th>Semester</th>
                    <th>Year</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($results as $r): ?>
                <tr>
                    <td><?= $r['result_id'] ?></td>
                    <td><?= htmlspecialchars($r['student_name'].' ('.$r['reg_number'].')') ?></td>
                    <td><?= htmlspecialchars($r['subject_name']) ?></td>
                    <td><?= $r['marks'] ?></td>
                    <td><?= $r['semester'] ?></td>
                    <td><?= htmlspecialchars($r['academic_year']) ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-warning action-btn" 
                            onclick='populateEdit(<?= json_encode($r) ?>)'>Edit</button>
                        <a href="?delete_id=<?= $r['result_id'] ?>" class="btn btn-sm btn-danger action-btn" onclick="return confirmDelete()">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($results)): ?>
                <tr><td colspan="7" class="text-center">No results found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
