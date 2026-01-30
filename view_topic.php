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

// Fetch topic details
$topic_query = "
    SELECT 
        ft.*,
        COALESCE(s.full_name, t.full_name, a.full_name) as author_name,
        CASE 
            WHEN ft.student_id IS NOT NULL THEN 'student'
            WHEN ft.teacher_id IS NOT NULL THEN 'teacher'
            WHEN ft.admin_id IS NOT NULL THEN 'admin'
        END as author_type
    FROM forum_topics ft
    LEFT JOIN students s ON ft.student_id = s.student_id
    LEFT JOIN teachers t ON ft.teacher_id = t.teacher_id
    LEFT JOIN admins a ON ft.admin_id = a.admin_id
    WHERE ft.topic_id = ?
";

$topic_stmt = $pdo->prepare($topic_query);
$topic_stmt->execute([$topic_id]);
$topic = $topic_stmt->fetch(PDO::FETCH_ASSOC);

if (!$topic) {
    header("Location: forum.php");
    exit();
}

// Fetch replies
$replies_query = "
    SELECT 
        fr.*,
        COALESCE(s.full_name, t.full_name, a.full_name) as author_name,
        CASE 
            WHEN fr.student_id IS NOT NULL THEN 'student'
            WHEN fr.teacher_id IS NOT NULL THEN 'teacher'
            WHEN fr.admin_id IS NOT NULL THEN 'admin'
        END as author_type
    FROM forum_replies fr
    LEFT JOIN students s ON fr.student_id = s.student_id
    LEFT JOIN teachers t ON fr.teacher_id = t.teacher_id
    LEFT JOIN admins a ON fr.admin_id = a.admin_id
    WHERE fr.topic_id = ?
    ORDER BY fr.created_at ASC
";

$replies_stmt = $pdo->prepare($replies_query);
$replies_stmt->execute([$topic_id]);
$replies = $replies_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {
    $reply_text = trim($_POST['reply_text']);
    
    if (!empty($reply_text)) {
        try {
            $insert_reply = "INSERT INTO forum_replies (topic_id, admin_id, reply_text) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($insert_reply);
            $stmt->execute([$topic_id, $admin_id, $reply_text]);
            
            header("Location: view_topic.php?id=$topic_id");
            exit();
        } catch (PDOException $e) {
            $error = "ስህተት: " . $e->getMessage();
        }
    }
}

// Handle delete reply
if (isset($_GET['delete_reply'])) {
    $reply_id = (int)$_GET['delete_reply'];
    
    // Check if admin owns the reply or has permission
    $check_reply = $pdo->prepare("SELECT * FROM forum_replies WHERE reply_id = ?");
    $check_reply->execute([$reply_id]);
    $reply_to_delete = $check_reply->fetch(PDO::FETCH_ASSOC);
    
    if ($reply_to_delete && ($reply_to_delete['admin_id'] == $admin_id || true)) { // Admin can delete any
        $delete_stmt = $pdo->prepare("DELETE FROM forum_replies WHERE reply_id = ?");
        $delete_stmt->execute([$reply_id]);
        
        header("Location: view_topic.php?id=$topic_id");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($topic['title']) ?> - አስተዳዳሪ</title>
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
        
        .topic-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .topic-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.1);
        }
        
        .reply-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .reply-form {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(16, 185, 129, 0.2);
            margin-top: 30px;
        }
        
        .author-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .badge-student { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .badge-teacher { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .badge-admin { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        
        .btn-reply {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .topic-content {
            line-height: 1.8;
            color: #333;
        }
        
        .reply-textarea {
            min-height: 120px;
            border: 2px solid rgba(16, 185, 129, 0.2);
            border-radius: 10px;
            padding: 15px;
            resize: vertical;
        }
        
        .reply-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            outline: none;
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
            <a href="forum.php" class="btn btn-outline-secondary btn-sm me-2">
                <i class="fas fa-arrow-left me-1"></i>ወደ ውይይት
            </a>
        </div>
    </div>
</nav>

<div class="topic-container">
    <!-- Topic Header -->
    <div class="topic-header">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1 class="h2 mb-3"><?= htmlspecialchars($topic['title']) ?></h1>
                <div class="d-flex align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-user me-1"></i>
                        <?= htmlspecialchars($topic['author_name']) ?>
                        <span class="author-badge badge-<?= $topic['author_type'] ?>">
                            <?= $topic['author_type'] ?>
                        </span>
                    </small>
                    <small class="text-muted ms-3">
                        <i class="far fa-clock me-1"></i>
                        <?= date('M d, Y h:i A', strtotime($topic['created_at'])) ?>
                    </small>
                </div>
            </div>
            <div>
                <a href="edit_topic.php?id=<?= $topic['topic_id'] ?>" class="btn btn-outline-warning btn-sm">
                    <i class="fas fa-edit me-1"></i>አርትዕ
                </a>
            </div>
        </div>
        
        <div class="topic-content">
            <?= nl2br(htmlspecialchars($topic['content'])) ?>
        </div>
    </div>

    <!-- Replies Section -->
    <h4 class="mb-4">
        <i class="fas fa-reply me-2"></i>መልሶች (<?= count($replies) ?>)
    </h4>
    
    <?php if (empty($replies)): ?>
        <div class="text-center py-4">
            <i class="fas fa-comment-slash fa-2x text-muted mb-3"></i>
            <p class="text-muted">እስካሁን ምንም መልስ የለም። የመጀመሪያው መልስ ይስጡ</p>
        </div>
    <?php else: ?>
        <?php foreach ($replies as $reply): ?>
            <div class="reply-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-2">
                            <small class="text-muted">
                                <i class="fas fa-user me-1"></i>
                                <?= htmlspecialchars($reply['author_name']) ?>
                                <span class="author-badge badge-<?= $reply['author_type'] ?>">
                                    <?= $reply['author_type'] ?>
                                </span>
                            </small>
                            <small class="text-muted ms-3">
                                <i class="far fa-clock me-1"></i>
                                <?= date('M d, Y h:i A', strtotime($reply['created_at'])) ?>
                            </small>
                        </div>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($reply['reply_text'])) ?></p>
                    </div>
                    <?php if ($reply['admin_id'] == $admin_id || true): // Admin can delete any ?>
                        <a href="view_topic.php?id=<?= $topic_id ?>&delete_reply=<?= $reply['reply_id'] ?>" 
                           class="btn btn-outline-danger btn-sm"
                           onclick="return confirm('ይህን መልስ መሰረዝ እርግጠኛ ነዎት?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Reply Form -->
    <div class="reply-form">
        <h5 class="mb-3"><i class="fas fa-reply me-2"></i>መልስ ይስጡ</h5>
        <form method="POST" action="">
            <div class="mb-3">
                <textarea name="reply_text" class="form-control reply-textarea" 
                          placeholder="መልስዎን ይጻፉ..." required></textarea>
            </div>
            <div class="d-flex justify-content-end">
                <button type="submit" name="submit_reply" class="btn-reply">
                    <i class="fas fa-paper-plane me-2"></i>መልስ ላክ
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>