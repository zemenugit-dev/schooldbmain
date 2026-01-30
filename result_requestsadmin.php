<?php
session_start();
require '../config/pdo_connect.php';

// ✅ Admin session check
if(!isset($_SESSION['admin'])){
    header("Location: login.php");
    exit;
}

$success = '';
$error = '';

// Handle Approve/Reject/Update actions
if(isset($_POST['action'])){
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $new_marks = isset($_POST['new_marks']) ? (float)$_POST['new_marks'] : null;

    try {
        if($action == 'approve' && $new_marks !== null){
            // Update results table
            $stmt = $pdo->prepare("UPDATE results r 
                                   JOIN result_requests rr 
                                   ON r.student_id=rr.student_id AND r.subject_id=rr.subject_id
                                   SET r.marks=? 
                                   WHERE rr.request_id=?");
            $stmt->execute([$new_marks, $request_id]);

            // Update request status
            $stmt = $pdo->prepare("UPDATE result_requests SET status='Approved', marks=? WHERE request_id=?");
            $stmt->execute([$new_marks, $request_id]);

            $success = "✅ Request approved and marks updated successfully!";
        }
        elseif($action == 'reject'){
            $stmt = $pdo->prepare("UPDATE result_requests SET status='Rejected' WHERE request_id=?");
            $stmt->execute([$request_id]);
            $success = "❌ Request rejected successfully!";
        }
    } catch(PDOException $e){
        $error = "❌ Database Error: ".$e->getMessage();
    }
}

// Fetch all requests
$stmt = $pdo->query("
    SELECT rr.*, s.full_name, sub.subject_name
    FROM result_requests rr
    JOIN students s ON rr.student_id = s.student_id
    JOIN subjects sub ON rr.subject_id = sub.subject_id
    ORDER BY rr.created_at DESC
");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Result Correction Requests</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* ===== Body & Container ===== */
body{
    font-family:'Poppins',sans-serif;
    background:#f5f7fa;
    min-height:100vh;
    margin:0;
    padding:0;
    color:#1f2937;
}
.container{
    margin-top:50px;
    max-width:1000px;
}

/* ===== Heading ===== */
h2{
    text-align:center;
    margin-bottom:30px;
    font-weight:700;
    color:#4f46e5;
}

/* ===== Card ===== */
.card{
    background:#fff;
    padding:20px 25px;
    border-radius:12px;
    box-shadow:0 8px 20px rgba(0,0,0,0.1);
    margin-bottom:20px;
    transition:transform 0.3s ease;
}
.card:hover{
    transform:translateY(-3px);
}

/* ===== Status Labels ===== */
.status{
    font-weight:600;
    padding:5px 10px;
    border-radius:8px;
    font-size:0.9rem;
    display:inline-block;
}
.status.Pending{ background:#fbbf24; color:#000; }
.status.Approved{ background:#22c55e; color:#fff; }
.status.Rejected{ background:#ef4444; color:#fff; }

/* ===== Form Inputs ===== */
input[type=number]{
    width:100px;
    border-radius:6px;
    border:1px solid #d1d5db;
    padding:5px 8px;
    margin-right:10px;
}
button{
    border:none;
    border-radius:8px;
    padding:7px 15px;
    font-weight:600;
    cursor:pointer;
    transition:all 0.3s ease;
}
button.btn-success{ background:#22c55e; color:#fff; }
button.btn-success:hover{ background:#16a34a; }
button.btn-danger{ background:#ef4444; color:#fff; }
button.btn-danger:hover{ background:#dc2626; }

/* ===== Messages ===== */
.msg{
    text-align:center;
    margin-bottom:15px;
    padding:10px;
    border-radius:8px;
}
.success{ background:#d1fae5; color:#065f46; }
.error{ background:#fee2e2; color:#b91c1c; }

/* ===== Responsive ===== */
@media(max-width:768px){
    .card{ padding:15px; }
    input[type=number]{ width:80px; margin-bottom:5px; }
    button{ margin-bottom:5px; }
}
</style>
</head>
<body>
<div class="container">
    <h2>📝 Result Correction Requests</h2>

    <?php if($error): ?>
        <div class="msg error"><?= $error ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="msg success"><?= $success ?></div>
    <?php endif; ?>

    <?php foreach($requests as $r): ?>
    <div class="card">
        <div class="d-flex justify-content-between flex-wrap">
            <div>
                <p><strong>Student:</strong> <?= htmlspecialchars($r['full_name']) ?></p>
                <p><strong>Subject:</strong> <?= htmlspecialchars($r['subject_name']) ?></p>
                <p><strong>Requested Marks:</strong> <?= $r['marks'] ?></p>
                <p><strong>Reason:</strong> <?= nl2br(htmlspecialchars($r['reason'])) ?></p>
                <p><strong>Status:</strong> <span class="status <?= $r['status'] ?>"><?= $r['status'] ?></span></p>
            </div>
            <div>
                <?php if($r['status']=='Pending'): ?>
                <form method="POST" class="d-flex align-items-center mt-2">
                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                    <input type="number" name="new_marks" value="<?= $r['marks'] ?>" min="0" max="100" step="0.01" required>
                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm me-2">Approve & Update</button>
                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <small class="text-muted">Requested on: <?= date('d M Y H:i', strtotime($r['created_at'])) ?></small>
    </div>
    <?php endforeach; ?>
</div>
</body>
</html>
