<?php
session_start();
require '../config/pdo_connect.php';

// ✅ Admin session check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Initialize messages
$success = '';
$error = '';

// Handle add announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_announcement'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $posted_by = $_SESSION['admin_id'];

    if (empty($title) || empty($message)) {
        $error = "❌ Title and Message cannot be empty";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, message, posted_by) VALUES (?, ?, ?)");
            $stmt->execute([$title, $message, $posted_by]);
            $success = "✅ Announcement posted successfully!";
        } catch (PDOException $e) {
            $error = "❌ Database Error: " . $e->getMessage();
        }
    }
}

// Handle delete announcement
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id = ?");
        $stmt->execute([$delete_id]);
        $success = "✅ Announcement deleted successfully!";
    } catch (PDOException $e) {
        $error = "❌ Database Error: " . $e->getMessage();
    }
}

// Fetch all announcements
$stmt = $pdo->query("
    SELECT a.*, ad.full_name AS admin_name 
    FROM announcements a
    LEFT JOIN admins ad ON a.posted_by = ad.admin_id
    ORDER BY a.created_at DESC
");
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Announcements</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{
    font-family:'Poppins',sans-serif;
    background: linear-gradient(135deg,#020617,#0b1120);
    min-height:100vh;
    color:#fff;
}
.container{
    margin-top:50px;
}
.card{
    background: rgba(17,24,39,0.95);
    padding:20px;
    border-radius:12px;
    margin-bottom:15px;
    color:#e5e7eb; /* Make text visible */
}
h2{
    color:#6366f1;
    text-align:center;
    margin-bottom:25px;
}
input, textarea{
    background:#1f2937;
    color:#fff; /* Visible text */
    border:none;
    border-radius:6px;
    padding:10px;
    width:100%;
}
input::placeholder, textarea::placeholder{
    color:#cbd5e1; /* Light placeholder */
}
input:focus, textarea:focus{
    outline:none;
    box-shadow:0 0 0 2px #6366f1;
}
button{
    background: linear-gradient(135deg,#6366f1,#22d3ee);
    border:none;
    padding:10px 15px;
    border-radius:8px;
    color:white;
    font-weight:500;
    cursor:pointer;
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
.delete-btn{
    background:#ef4444;
    font-size:0.85rem;
    padding:3px 8px;
    color:#fff;
    text-decoration:none;
    border-radius:5px;
}
.delete-btn:hover{
    background:#dc2626;
    color:#fff;
}
.card h5{
    color:#facc15; /* Bright title color */
    margin-bottom:5px;
}
.card p{
    color:#e5e7eb; /* Message color */
}
.card small{
    color:#9ca3af; /* Light gray for meta info */
}
</style>
</head>
<body>

<div class="container">
    <h2>📢 Manage Announcements</h2>

    <?php if($error): ?>
        <p class="msg error"><?= $error ?></p>
    <?php endif; ?>
    <?php if($success): ?>
        <p class="msg success"><?= $success ?></p>
    <?php endif; ?>

    <!-- Add Announcement Form -->
    <div class="card">
        <form method="POST">
            <div class="mb-3">
                <input type="text" name="title" placeholder="Title" required>
            </div>
            <div class="mb-3">
                <textarea name="message" placeholder="Message" rows="4" required></textarea>
            </div>
            <button type="submit" name="add_announcement">Post Announcement</button>
        </form>
    </div>

    <!-- Announcements List -->
    <?php foreach($announcements as $a): ?>
        <div class="card">
            <h5><?= htmlspecialchars($a['title']) ?></h5>
            <p><?= nl2br(htmlspecialchars($a['message'])) ?></p>
            <small>Posted by: <?= $a['admin_name'] ?? 'Admin' ?> | <?= date('d M Y H:i', strtotime($a['created_at'])) ?></small>
            <a class="delete-btn float-end" href="?delete_id=<?= $a['announcement_id'] ?>" onclick="return confirm('Are you sure to delete this announcement?');">Delete</a>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>
