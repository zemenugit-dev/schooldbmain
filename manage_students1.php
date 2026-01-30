<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors',1);


if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require '../config/pdo_connect.php';

/* ================= DELETE ================= */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM students WHERE student_id=?")->execute([$id]);
    header("Location: manage_students.php");
    exit;
}

/* ================= ADD / UPDATE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name  = trim($_POST['full_name']);
    $reg_number = strtoupper(trim($_POST['reg_number']));
    $email      = strtolower(trim($_POST['email']));
    $grade      = $_POST['grade_level'];
    $section    = trim($_POST['section']);

    if (isset($_POST['student_id']) && $_POST['student_id'] != '') {
        // UPDATE
        $id = (int)$_POST['student_id'];
        $stmt = $pdo->prepare(
            "UPDATE students 
             SET full_name=?, reg_number=?, email=?, grade_level=?, section=? 
             WHERE student_id=?"
        );
        $stmt->execute([$full_name,$reg_number,$email,$grade,$section,$id]);
    } else {
        // CREATE
        $password = password_hash("123456", PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO students 
            (full_name, reg_number, email, password, grade_level, section)
            VALUES (?,?,?,?,?,?)"
        );
        $stmt->execute([$full_name,$reg_number,$email,$password,$grade,$section]);
    }

    header("Location: manage_students.php");
    exit;
}

/* ================= READ ================= */
$students = $pdo->query("SELECT * FROM students ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Manage Students</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
  background:linear-gradient(135deg,#020617,#0b1120);
  color:#e5e7eb;
  font-family:Poppins,sans-serif;
}
.main{
  margin-left:280px;
  padding:40px;
}
.card{
  background:rgba(17,24,39,.9);
  border:none;
  border-radius:18px;
}
.table{
  color:#e5e7eb;
}
.table tr:hover{
  background:rgba(99,102,241,.15);
}
.btn-main{
  background:linear-gradient(135deg,#6366f1,#22d3ee);
  border:none;
  color:white;
  border-radius:12px;
}
.modal-content{
  background:#020617;
  color:white;
  border-radius:16px;
}
</style>
</head>

<body>

<div class="main">
<h2 class="mb-4">🎓 Student Management</h2>

<button class="btn btn-main mb-3" onclick="openAdd()">➕ Add Student</button>

<div class="card p-4">
<table class="table table-hover">
<thead>
<tr>
<th>ID</th>
<th>Name</th>
<th>Reg No</th>
<th>Email</th>
<th>Grade</th>
<th>Section</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach($students as $s): ?>
<tr>
<td><?= $s['student_id'] ?></td>
<td><?= htmlspecialchars($s['full_name']) ?></td>
<td><?= $s['reg_number'] ?></td>
<td><?= $s['email'] ?></td>
<td><?= $s['grade_level'] ?></td>
<td><?= $s['section'] ?></td>
<td>
<button class="btn btn-sm btn-warning"
onclick='editStudent(<?= json_encode($s) ?>)'>✏ Edit</button>

<a href="?delete=<?= $s['student_id'] ?>"
onclick="return confirm('Delete this student?')"
class="btn btn-sm btn-danger">🗑 Delete</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<a href="dashboard.php" class="btn btn-secondary mt-4">⬅ Dashboard</a>
</div>

<!-- ================= MODAL ================= -->
<div class="modal fade" id="studentModal">
<div class="modal-dialog">
<div class="modal-content p-4">
<h4 id="modalTitle">Add Student</h4>

<form method="POST">
<input type="hidden" name="student_id" id="student_id">

<input class="form-control mb-2" name="full_name" id="full_name" placeholder="Full Name" required>
<input class="form-control mb-2" name="reg_number" id="reg_number" placeholder="Reg Number" required>
<input class="form-control mb-2" name="email" id="email" placeholder="Email" required>

<select class="form-control mb-2" name="grade_level" id="grade_level">
<option>Grade 9</option>
<option>Grade 10</option>
<option>Grade 11</option>
<option>Grade 12</option>
</select>

<input class="form-control mb-3" name="section" id="section" placeholder="Section">

<button class="btn btn-main w-100">Save</button>
</form>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const modal = new bootstrap.Modal(document.getElementById('studentModal'));

function openAdd(){
  document.getElementById('modalTitle').innerText = "Add Student";
  document.querySelector('form').reset();
  document.getElementById('student_id').value = '';
  modal.show();
}

function editStudent(s){
  document.getElementById('modalTitle').innerText = "Edit Student";
  student_id.value = s.student_id;
  full_name.value = s.full_name;
  reg_number.value = s.reg_number;
  email.value = s.email;
  grade_level.value = s.grade_level;
  section.value = s.section;
  modal.show();
}
</script>

</body>
</html>
