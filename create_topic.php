<?php
session_start();
require '../config/pdo_connect.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_topic'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    
    if (empty($title) || empty($content)) {
        $error = "ርዕስ እና ይዘት መሙላት አለባቸው";
    } else {
        try {
            $sql = "INSERT INTO forum_topics (title, content, admin_id) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $content, $admin_id]);
            
            $topic_id = $pdo->lastInsertId();
            $success = "የውይይት ርዕስ በተሳካ ሁኔታ ተፈጥሯል";
            
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
    <title>አዲስ ርዕስ ፍጠር - አስተዳዳሪ</title>
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
        
        .create-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .create-card {
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
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
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
            <span class="me-3"><?= htmlspecialchars($admin_name) ?></span>
            <a href="forum.php" class="btn btn-outline-secondary btn-sm me-2">
                <i class="fas fa-arrow-left me-1"></i>ወደ ውይይት
            </a>
        </div>
    </div>
</nav>

<div class="create-container">
    <div class="create-card">
        <h2 class="mb-4"><i class="fas fa-plus-circle me-2"></i>አዲስ የውይይት ርዕስ ፍጠር</h2>
        
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
                       placeholder="የውይይት ርዕስ ያስገቡ" 
                       required maxlength="255"
                       value="<?= isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '' ?>">
                <small class="text-muted">ከ255 ፊደላት ያንሱ</small>
            </div>
            
            <div class="mb-4">
                <label class="form-label">ይዘት *</label>
                <textarea name="content" class="form-control form-textarea" 
                          placeholder="የውይይት ይዘት ይጻፉ..." 
                          required><?= isset($_POST['content']) ? htmlspecialchars($_POST['content']) : '' ?></textarea>
            </div>
            
            <div class="d-flex gap-3">
                <button type="submit" name="create_topic" class="btn-submit">
                    <i class="fas fa-paper-plane me-2"></i>ርዕስ ፍጠር
                </button>
                <a href="forum.php" class="btn-cancel text-decoration-none">
                    <i class="fas fa-times me-2"></i>ሰርዝ
                </a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Character counter for title
    document.querySelector('input[name="title"]').addEventListener('input', function(e) {
        const maxLength = 255;
        const currentLength = e.target.value.length;
        const counter = e.target.nextElementSibling;
        
        if (counter && counter.classList.contains('text-muted')) {
            counter.textContent = `${currentLength}/${maxLength} ፊደላት`;
            
            if (currentLength > maxLength * 0.8) {
                counter.style.color = '#f59e0b';
            } else {
                counter.style.color = '#6b7280';
            }
        }
    });
</script>
</body>
</html>