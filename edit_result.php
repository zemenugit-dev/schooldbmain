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

$success = '';
$error = '';

// Fetch all students
$students_stmt = $pdo->query("SELECT student_id, full_name, reg_number FROM students ORDER BY full_name ASC");
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all subjects
$subjects_stmt = $pdo->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC");
$subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Add Result
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_result'])){
    $student_id    = (int)$_POST['student_id'];
    $subject_id    = (int)$_POST['subject_id'];
    $marks         = (float)$_POST['marks'];
    $semester      = (int)$_POST['semester'];
    $academic_year = trim($_POST['academic_year']);

    if($student_id <=0 || $subject_id <=0 || $marks <0 || $marks >100 || $semester <=0 || empty($academic_year)){
        $error = "❌ Please fill all fields correctly (marks: 0-100)";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO results (student_id, subject_id, marks, semester, academic_year) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $subject_id, $marks, $semester, $academic_year]);
            $success = "✅ Result added successfully!";
        } catch(PDOException $e){
            $error = "❌ Database error: ".$e->getMessage();
        }
    }
}

// Handle Delete Result
if(isset($_GET['delete_id'])){
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM results WHERE result_id = ?");
        $stmt->execute([$delete_id]);
        $success = "✅ Result deleted successfully!";
    } catch(PDOException $e){
        $error = "❌ Database error: ".$e->getMessage();
    }
}

// Handle Update Result (via modal form)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_result'])){
    $result_id     = (int)$_POST['result_id'];
    $student_id    = (int)$_POST['student_id'];
    $subject_id    = (int)$_POST['subject_id'];
    $marks         = (float)$_POST['marks'];
    $semester      = (int)$_POST['semester'];
    $academic_year = trim($_POST['academic_year']);

    if($student_id <=0 || $subject_id <=0 || $marks <0 || $marks >100 || $semester <=0 || empty($academic_year)){
        $error = "❌ Please fill all fields correctly (marks: 0-100)";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE results SET student_id=?, subject_id=?, marks=?, semester=?, academic_year=? WHERE result_id=?");
            $stmt->execute([$student_id, $subject_id, $marks, $semester, $academic_year, $result_id]);
            $success = "✅ Result updated successfully!";
        } catch(PDOException $e){
            $error = "❌ Database error: ".$e->getMessage();
        }
    }
}

// Fetch all results
$results_stmt = $pdo->query("
    SELECT r.*, s.full_name, s.reg_number, sub.subject_name
    FROM results r
    LEFT JOIN students s ON r.student_id = s.student_id
    LEFT JOIN subjects sub ON r.subject_id = sub.subject_id
    ORDER BY r.created_at DESC
");
$results = $results_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Student Results</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{
    font-family:'Poppins',sans-serif;
    background: linear-gradient(135deg,#020617,#0b1120);
    color:#fff;
    min-height:100vh;
}
.container{
    margin-top:60px;
    max-width:950px;
}
.card{
    background: rgba(17,24,39,0.95);
    padding:20px;
    border-radius:15px;
    box-shadow:0 20px 50px rgba(0,0,0,.5);
    margin-bottom:20px;
}
h2{
    text-align:center;
    margin-bottom:25px;
    color:#6366f1;
}
label{
    display:block;
    margin-bottom:6px;
    font-weight:500;
    color:#e5e7eb;
}
input, select{
    background:#1f2937;
    color:#fff;
    border:none;
    border-radius:6px;
    padding:10px;
    width:100%;
    margin-bottom:12px;
}
select option{
    color:#000;
}
input:focus, select:focus{
    outline:none;
    box-shadow:0 0 0 2px #6366f1;
}
button{
    background: linear-gradient(135deg,#6366f1,#22d3ee);
    border:none;
    padding:10px 15px;
    border-radius:8px;
    color:#fff;
    font-weight:600;
    cursor:pointer;
    transition:all 0.3s ease;
}
button:hover{
    background: linear-gradient(135deg,#4f46e5,#06b6d4);
}
.msg{
    text-align:center;
    margin-bottom:10px;
}
.success{color:#22c55e;}
.error{color:#ef4444;}
.table td, .table th{
    vertical-align: middle;
    color:#fff;
}
.table thead{
    background: #1f2937;
}
.delete-btn, .edit-btn{
    font-size:0.85rem;
    padding:4px 8px;
    border:none;
    border-radius:5px;
    cursor:pointer;
}
.delete-btn{background:#ef4444;color:#fff;}
.delete-btn:hover{background:#dc2626;}
.edit-btn{background:#6366f1;color:#fff;}
.edit-btn:hover{background:#4f46e5;}
/* Modal CSS */
.modal{
    display:none;
    position:fixed;
    z-index:1050;
    left:0; top:0;
    width:100%; height:100%;
    overflow:auto;
    background-color: rgba(0,0,0,0.6);
}
.modal-content{
    background:#111827;
    margin:10% auto;
    padding:20px;
    border-radius:10px;
    width:400px;
    color:#fff;
}
.modal-header h4{
    margin:0;
    color:#6366f1;
}
.modal-close{
    float:right;
    font-size:22px;
    cursor:pointer;
}
</style>
</head>
<body>

<div class="container">
    <div class="card">
        <h2>📝 Add Student Result</h2>

        <?php if($error): ?><p class="msg error"><?= $error ?></p><?php endif; ?>
        <?php if($success): ?><p class="msg success"><?= $success ?></p><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="add_result" value="1">
            <label>Student</label>
            <select name="student_id" required>
                <option value="">Select Student</option>
                <?php foreach($students as $s): ?>
                    <option value="<?= $s['student_id'] ?>"><?= htmlspecialchars($s['full_name'].' ('.$s['reg_number'].')') ?></option>
                <?php endforeach; ?>
            </select>

            <label>Subject</label>
            <select name="subject_id" required>
                <option value="">Select Subject</option>
                <?php foreach($subjects as $sub): ?>
                    <option value="<?= $sub['subject_id'] ?>"><?= htmlspecialchars($sub['subject_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Marks (0-100)</label>
            <input type="number" name="marks" min="0" max="100" step="0.01" required>

            <label>Semester</label>
            <select name="semester" required>
                <option value="">Select Semester</option>
                <option value="1">Semester 1</option>
                <option value="2">Semester 2</option>
            </select>

            <label>Academic Year</label>
            <input type="text" name="academic_year" placeholder="2025-2026" required>

            <button type="submit">Add Result</button>
        </form>
    </div>

    <div class="card">
        <h2>📊 All Results</h2>
        <table class="table table-striped table-dark">
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
                <tr data-id="<?= $r['result_id'] ?>" 
                    data-student="<?= $r['student_id'] ?>" 
                    data-subject="<?= $r['subject_id'] ?>" 
                    data-marks="<?= $r['marks'] ?>" 
                    data-semester="<?= $r['semester'] ?>" 
                    data-year="<?= $r['academic_year'] ?>">
                    <td><?= $r['result_id'] ?></td>
                    <td><?= htmlspecialchars($r['full_name'].' ('.$r['reg_number'].')') ?></td>
                    <td><?= htmlspecialchars($r['subject_name']) ?></td>
                    <td><?= $r['marks'] ?></td>
                    <td><?= $r['semester'] ?></td>
                    <td><?= htmlspecialchars($r['academic_year']) ?></td>
                    <td>
                        <button class="edit-btn btn-sm">Edit</button>
                        <a class="delete-btn btn-sm" href="?delete_id=<?= $r['result_id'] ?>" onclick="return confirm('Are you sure?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-close">&times;</span>
            <h4>Edit Result</h4>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="update_result" value="1">
            <input type="hidden" name="result_id" id="editResultId">
            <label>Student</label>
            <select name="student_id" id="editStudent" required>
                <option value="">Select Student</option>
                <?php foreach($students as $s): ?>
                    <option value="<?= $s['student_id'] ?>"><?= htmlspecialchars($s['full_name'].' ('.$s['reg_number'].')') ?></option>
                <?php endforeach; ?>
            </select>

            <label>Subject</label>
            <select name="subject_id" id="editSubject" required>
                <option value="">Select Subject</option>
                <?php foreach($subjects as $sub): ?>
                    <option value="<?= $sub['subject_id'] ?>"><?= htmlspecialchars($sub['subject_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Marks (0-100)</label>
            <input type="number" name="marks" id="editMarks" min="0" max="100" step="0.01" required>

            <label>Semester</label>
            <select name="semester" id="editSemester" required>
                <option value="">Select Semester</option>
                <option value="1">Semester 1</option>
                <option value="2">Semester 2</option>
            </select>

            <label>Academic Year</label>
            <input type="text" name="academic_year" id="editYear" required>

            <button type="submit">Update Result</button>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('editModal');
const closeModal = document.querySelector('.modal-close');
const editForm = document.getElementById('editForm');

document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function(){
        const row = this.closest('tr');
        document.getElementById('editResultId').value = row.dataset.id;
        document.getElementById('editStudent').value = row.dataset.student;
        document.getElementById('editSubject').value = row.dataset.subject;
        document.getElementById('editMarks').value = row.dataset.marks;
        document.getElementById('editSemester').value = row.dataset.semester;
        document.getElementById('editYear').value = row.dataset.year;
        modal.style.display = 'block';
    });
});

closeModal.onclick = function(){ modal.style.display='none'; }
window.onclick = function(e){ if(e.target==modal) modal.style.display='none'; }
</script>

</body>
</html>
