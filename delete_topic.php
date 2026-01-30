<?php
session_start();
require '../config/pdo_connect.php';

// Check if user is logged in as admin (based on your forum.php)
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Get topic ID from URL parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: forum.php");
    exit();
}

$topic_id = (int)$_GET['id'];
$admin_id = $_SESSION['admin_id'];

try {
    // First, check if the topic exists and get its details
    $check_query = "SELECT * FROM forum_topics WHERE topic_id = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$topic_id]);
    $topic = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$topic) {
        $_SESSION['error'] = "የውይይት ርዕሱ አልተገኘም";
        header("Location: forum.php");
        exit();
    }
    
    // Check if admin is authorized to delete (admins can delete any topic)
    // Optionally, you could check if the admin created this topic or has special privileges
    
    // Begin transaction to delete both topic and its replies
    $pdo->beginTransaction();
    
    // First, delete all replies associated with this topic
    $delete_replies = "DELETE FROM forum_replies WHERE topic_id = ?";
    $replies_stmt = $pdo->prepare($delete_replies);
    $replies_stmt->execute([$topic_id]);
    
    // Then delete the topic itself
    $delete_topic = "DELETE FROM forum_topics WHERE topic_id = ?";
    $topic_stmt = $pdo->prepare($delete_topic);
    $topic_stmt->execute([$topic_id]);
    
    // Commit the transaction
    $pdo->commit();
    
    // Log the deletion (optional)
 /*   $log_query = "INSERT INTO activity_logs (admin_id, action, details, created_at) 
                  VALUES (?, 'delete_topic', ?, NOW())";
    $log_stmt = $pdo->prepare($log_query);
    $log_stmt->execute([
        $admin_id,
        "Deleted topic: " . $topic['title'] . " (ID: $topic_id)"
    ]);
    */
    $_SESSION['success'] = "የውይይት ርዕሱ በተሳካ ሁኔታ ተሰርዟል";
    
} catch (PDOException $e) {
    // Rollback if error occurs
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error'] = "የውይይት ርዕሱን ለመሰረዝ ስህተት ተከስቷል: " . $e->getMessage();
    error_log("Delete Topic Error: " . $e->getMessage());
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back to forum page
header("Location: forum.php");
exit();
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የውይይት መድረክ - መምህር</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #10b981; 
            --primary-light: #34d399;
            --secondary: #059669; 
            --dark: #064e3b; 
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
        
        .forum-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .forum-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.1);
        }
        
        .topic-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .topic-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.15);
            border-color: var(--primary);
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
        
        .btn-create {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }
        
        .reply-count {
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .action-buttons .btn {
            padding: 6px 12px;
            font-size: 0.85rem;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-chalkboard-teacher me-2"></i>መምህር ፖርታል
        </a>
        <div class="d-flex align-items-center">
            <span class="me-3"><?= htmlspecialchars($teacher_name) ?></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">ውጣ</a>
        </div>
    </div>
</nav>

<div class="forum-container">
    <div class="forum-header">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-2"><i class="fas fa-comments me-2"></i>የውይይት መድረክ</h1>
                <p class="text-muted mb-0">ሁሉንም የውይይት ርዕሶች ይመልከቱ እና ይሳተፉ</p>
            </div>
            <a href="create_topic.php" class="btn-create">
                <i class="fas fa-plus-circle me-2"></i>አዲስ ርዕስ ፍጠር
            </a>
        </div>
    </div>

    <div class="topics-list">
        <?php if (empty($topics)): ?>
            <div class="text-center py-5">
                <i class="fas fa-comments-slash fa-3x text-muted mb-3"></i>
                <h4>እስካሁን ምንም የውይይት ርዕስ አልተፈጠረም</h4>
                <p class="text-muted">የመጀመሪያውን የውይይት ርዕስ ይፍጠሩ</p>
            </div>
        <?php else: ?>
            <?php foreach ($topics as $topic): ?>
                <div class="topic-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h5 class="mb-2">
                                <a href="view_topic.php?id=<?= $topic['topic_id'] ?>" class="text-decoration-none text-dark">
                                    <?= htmlspecialchars($topic['title']) ?>
                                </a>
                            </h5>
                            <p class="text-muted mb-2" style="font-size: 0.95rem;">
                                <?= substr(htmlspecialchars($topic['content']), 0, 150) ?>...
                            </p>
                            <div class="d-flex align-items-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i>
                                    <?= htmlspecialchars($topic['author_name']) ?>
                                    <span class="author-badge badge-<?= $topic['author_type'] ?>">
                                        <?= $topic['author_type'] ?>
                                    </span>
                                </small>
                                <small class="text-muted ms-3">
                                    <i class="far fa-clock me-1"></i>
                                    <?= date('M d, Y', strtotime($topic['created_at'])) ?>
                                </small>
                                <div class="reply-count ms-3">
                                    <i class="fas fa-reply me-1"></i>
                                    <?= $reply_counts[$topic['topic_id']] ?> መልሶች
                                </div>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a href="view_topic.php?id=<?= $topic['topic_id'] ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($topic['teacher_id'] == $teacher_id): ?>
                                <a href="edit_topic.php?id=<?= $topic['topic_id'] ?>" class="btn btn-outline-warning btn-sm ms-1">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete_topic.php?id=<?= $topic['topic_id'] ?>" 
                                   class="btn btn-outline-danger btn-sm ms-1"
                                   onclick="return confirm('ይህን የውይይት ርዕስ መሰረዝ እርግጠኛ ነዎት?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>