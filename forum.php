<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config/pdo_connect.php';
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>' . htmlspecialchars($_SESSION['success']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>' . htmlspecialchars($_SESSION['error']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['error']);
}

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? '';
$error = '';
$topics = [];
$reply_counts = [];

try {
    // First, check if forum_topics table exists
    $check_table = $pdo->query("SHOW TABLES LIKE 'forum_topics'");
    if ($check_table->rowCount() == 0) {
        throw new Exception("Forum topics table not found. Please create the forum_topics table.");
    }
    
    // Check if students, teachers, admins tables exist
    $check_students = $pdo->query("SHOW TABLES LIKE 'students'");
    $check_teachers = $pdo->query("SHOW TABLES LIKE 'teachers'");
    $check_admins = $pdo->query("SHOW TABLES LIKE 'admins'");
    
    // Fetch all forum topics with author information
    $topics_query = "
        SELECT 
            ft.*,
            COALESCE(s.full_name, t.full_name, a.full_name) as author_name,
            CASE 
                WHEN ft.student_id IS NOT NULL THEN 'student'
                WHEN ft.teacher_id IS NOT NULL THEN 'teacher'
                WHEN ft.admin_id IS NOT NULL THEN 'admin'
                ELSE 'unknown'
            END as author_type
        FROM forum_topics ft
        LEFT JOIN students s ON ft.student_id = s.student_id
        LEFT JOIN teachers t ON ft.teacher_id = t.teacher_id
        LEFT JOIN admins a ON ft.admin_id = a.admin_id
        ORDER BY ft.created_at DESC
    ";
    
    $stmt = $pdo->prepare($topics_query);
    $stmt->execute();
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check if we got any topics
    error_log("Found " . count($topics) . " forum topics");
    
    // Count replies for each topic
    foreach ($topics as $topic) {
        try {
            $reply_count = $pdo->prepare("SELECT COUNT(*) as count FROM forum_replies WHERE topic_id = ?");
            $reply_count->execute([$topic['topic_id']]);
            $count_result = $reply_count->fetch(PDO::FETCH_ASSOC);
            $reply_counts[$topic['topic_id']] = $count_result['count'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error counting replies for topic {$topic['topic_id']}: " . $e->getMessage());
            $reply_counts[$topic['topic_id']] = 0;
        }
    }
    
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
    error_log("Forum Error: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Forum Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የውይይት መድረክ - አስተዳዳሪ</title>
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
        .badge-unknown { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
        
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
        
        .error-alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .debug-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 0.9rem;
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
            <a href="logout.php" class="btn btn-outline-danger btn-sm">ውጣ</a>
        </div>
        <!-- In forum.php, update the delete button section: -->
<div class="action-buttons">
    <a href="view_topic.php?id=<?= $topic['topic_id'] ?>" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-eye"></i>
    </a>
    <a href="edit_topic.php?id=<?= $topic['topic_id'] ?>" class="btn btn-outline-warning btn-sm ms-1">
        <i class="fas fa-edit"></i>
    </a>
    <?php
    // Check if admin is authorized to delete this topic
    // Admins can delete any topic, but you might want to add restrictions
    $can_delete = true; // By default, admin can delete
    
    // Optional: Restrict deletion to topic owner or specific admin roles
    // $can_delete = ($topic['admin_id'] == $admin_id) || ($_SESSION['admin_role'] == 'super_admin');
    
    if ($can_delete): 
    ?>
        <a href="delete_topic.php?id=<?= $topic['topic_id'] ?>" 
           class="btn btn-outline-danger btn-sm ms-1"
           onclick="return confirm('ይህን የውይይት ርዕስ መሰረዝ እርግጠኛ ነዎት? ይህ እርምጃ መመለስ አይቻልም!')">
            <i class="fas fa-trash"></i>
        </a>
    <?php endif; ?>
</div>
    </div>
</nav>

<div class="forum-container">
    <!-- Error Display -->
    <?php if ($error): ?>
        <div class="error-alert">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>ስህተት ተከስቷል</h5>
            <p class="mb-2"><?= htmlspecialchars($error) ?></p>
            <small>ይህን ስህተት ለመፍታት የቴክኒክ ቡድን ያነጋግሩ</small>
            
            <!-- Debug info for development -->
            <div class="mt-3">
                <button class="btn btn-sm btn-outline-info" onclick="toggleDebug()">
                    <i class="fas fa-bug me-1"></i>የማስተካከያ መረጃ አሳይ
                </button>
                <div id="debugInfo" style="display: none; margin-top: 10px;">
                    <div class="debug-info">
                        <strong>Session Info:</strong><br>
                        Admin ID: <?= $admin_id ?><br>
                        Admin Name: <?= htmlspecialchars($admin_name) ?><br>
                        Session Status: <?= session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive' ?>
                    </div>
                    <div class="debug-info">
                        <strong>Database Info:</strong><br>
                        PDO Connected: <?= isset($pdo) ? 'Yes' : 'No' ?><br>
                        Topics Found: <?= count($topics) ?><br>
                        PHP Version: <?= phpversion() ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="forum-header">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-2"><i class="fas fa-comments me-2"></i>የውይይት መድረክ</h1>
                <p class="text-muted mb-0">ሁሉንም የውይይት ርዕሶች ይመልከቱ እና ያስተዳድሩ</p>
            </div>
            <a href="create_topic.php" class="btn-create">
                <i class="fas fa-plus-circle me-2"></i>አዲስ ርዕስ ፍጠር
            </a>
        </div>
    </div>

    <!-- Topics List -->
    <div class="topics-list">
        <?php if (empty($topics) && !$error): ?>
            <div class="text-center py-5">
                <i class="fas fa-comments-slash fa-3x text-muted mb-3"></i>
                <h4>እስካሁን ምንም የውይይት ርዕስ አልተፈጠረም</h4>
                <p class="text-muted">የመጀመሪያውን የውይይት ርዕስ ይፍጠሩ</p>
            </div>
        <?php elseif (!empty($topics)): ?>
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
                                    <?= htmlspecialchars($topic['author_name'] ?? 'Unknown Author') ?>
                                    <span class="author-badge badge-<?= $topic['author_type'] ?>">
                                        <?= htmlspecialchars($topic['author_type']) ?>
                                    </span>
                                </small>
                                <small class="text-muted ms-3">
                                    <i class="far fa-clock me-1"></i>
                                    <?= date('M d, Y', strtotime($topic['created_at'])) ?>
                                </small>
                                <div class="reply-count ms-3">
                                    <i class="fas fa-reply me-1"></i>
                                    <?= $reply_counts[$topic['topic_id']] ?? 0 ?> መልሶች
                                </div>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a href="view_topic.php?id=<?= $topic['topic_id'] ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit_topic.php?id=<?= $topic['topic_id'] ?>" class="btn btn-outline-warning btn-sm ms-1">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="delete_topic.php?id=<?= $topic['topic_id'] ?>" 
                               class="btn btn-outline-danger btn-sm ms-1"
                               onclick="return confirm('ይህን የውይይት ርዕስ መሰረዝ እርግጠኛ ነዎት?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleDebug() {
        const debugInfo = document.getElementById('debugInfo');
        debugInfo.style.display = debugInfo.style.display === 'none' ? 'block' : 'none';
    }
    
    // Auto-hide alerts after 10 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.error-alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 1s ease';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 1000);
        });
    }, 10000);
</script>
</body>
</html>