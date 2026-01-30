<?php
session_start();
require '../config/pdo_connect.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$topic_id) {
    header("Location: forum.php");
    exit();
}

// Fetch topic
$topic_query = "SELECT * FROM forum_topics WHERE topic_id = ?";
$topic_stmt = $pdo->prepare($topic_query);
$topic_stmt->execute([$topic_id]);
$topic = $topic_stmt->fetch(PDO::FETCH_ASSOC);

if (!$topic) {
    header("Location: forum.php");
    exit();
}

// Check if admin owns the topic or has permission
if ($topic['admin_id'] != $admin_id && false) { // Set to false to allow admin to edit any
    header("Location: view_topic.php?id=$topic_id");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_topic'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    
    if (empty($title) || empty($content)) {
        $error = "ርዕስ እና ይዘት መሙላት አለባቸው";
    } else {
        try {
            $update_sql = "UPDATE forum_topics SET title = ?, content = ? WHERE topic_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$title, $content, $topic_id]);
            
            $success = "የውይይት ርዕስ በተሳካ ሁኔታ ተሻሽሏል";
            
            // Redirect after 2 seconds
            header("refresh:2;url=view_topic.php?id=" . $topic_id);
        } catch (PDOException $e) {
            $error = "ስህተት: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ርዕስ አርትዕ - አስተዳዳሪ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #10b981; 
            --secondary: #059669; 
            --light: #f0fdf4; 
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--light);
            padding-top: 70px;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .edit-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .edit-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.1);
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-control, .form-textarea {
            border: 2px solid rgba(16, 185, 129, 0.2);
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .form-textarea {
            min-height: 200px;
            resize: vertical;
        }
        
        .btn-update {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }
        
        .btn-cancel {
            border: 2px solid rgba(16, 185, 129, 0.3);
            color: var(--primary);
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: rgba(16, 185, 129, 0.1);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-chalkboard-teacher me-2"></i>አስተዳዳሪ ፖርታል
        </a>
        <div class="d-flex align-items-center">
            <a href="view_topic.php?id=<?= $topic_id ?>" class="btn btn-outline-secondary btn-sm me-2">
                <i class="fas fa-arrow-left me-1"></i>ወደ ርዕስ
            </a>
        </div>
    </div>
</nav>

<div class="edit-container">
    <div class="edit-card">
        <h2 class="mb-4"><i class="fas fa-edit me-2"></i>የውይይት ርዕስ አርትዕ</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?= $success ?>
                <p class="mb-0 mt-2">ወደ የውይይት ርዕስ እየተዛወርን ነው...</p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-4">
                <label class="form-label">ርዕስ *</label>
                <input type="text" name="title" class="form-control" 
                       value="<?= htmlspecialchars($topic['title']) ?>"
                       required maxlength="255">
                <small class="text-muted">ከ255 ፊደላት ያንሱ</small>
            </div>
            
            <div class="mb-4">
                <label class="form-label">ይዘት *</label>
                <textarea name="content" class="form-control form-textarea" 
                          required><?= htmlspecialchars($topic['content']) ?></textarea>
            </div>
            
            <div class="d-flex gap-3">
                <button type="submit" name="update_topic" class="btn-update">
                    <i class="fas fa-save me-2"></i>ለውጦችን አስቀምጥ
                </button>
                <a href="view_topic.php?id=<?= $topic_id ?>" class="btn-cancel text-decoration-none">
                    <i class="fas fa-times me-2"></i>ሰርዝ
                </a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>