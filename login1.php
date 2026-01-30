<?php
session_start();
require '../config/pdo_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Updated query to select all necessary fields
    $stmt = $pdo->prepare("
        SELECT admin_id, username, password, full_name, role 
        FROM admins 
        WHERE username = ? 
        LIMIT 1
    ");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        // Set all session variables
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin'] = true; // This is important for the dashboard auth check

        header("Location: dashboard.php");
        exit();
    } else {
        $error = "❌ Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --primary: #4f46e5;
    --secondary: #3b82f6;
    --success: #10b981;
    --dark: #0f172a;
    --light: #f8fafc;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Poppins', sans-serif;
    padding: 20px;
}

.login-container {
    width: 100%;
    max-width: 450px;
    animation: fadeIn 0.8s ease;
}

.login-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    position: relative;
    overflow: hidden;
}

.login-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
}

.login-header {
    text-align: center;
    margin-bottom: 30px;
}

.logo {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 15px;
    display: inline-block;
}

.login-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: white;
    margin-bottom: 10px;
}

.login-subtitle {
    color: #94a3b8;
    font-size: 0.95rem;
}

.form-label {
    color: #e2e8f0;
    font-weight: 500;
    margin-bottom: 8px;
    display: block;
}

.form-control {
    background: rgba(30, 41, 59, 0.8);
    border: 2px solid rgba(99, 102, 241, 0.3);
    border-radius: 12px;
    color: white;
    padding: 14px 20px;
    font-size: 1rem;
    transition: all 0.3s;
    width: 100%;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
    outline: none;
}

.btn-login {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border: none;
    border-radius: 12px;
    padding: 16px;
    font-size: 1.1rem;
    font-weight: 600;
    width: 100%;
    transition: all 0.3s;
    margin-top: 10px;
    cursor: pointer;
}

.btn-login:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(79, 70, 229, 0.4);
}

.alert-error {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    padding: 14px;
    border-radius: 12px;
    border: 1px solid rgba(239, 68, 68, 0.3);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideIn 0.5s ease;
}

.alert-success {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    padding: 14px;
    border-radius: 12px;
    border: 1px solid rgba(34, 197, 94, 0.3);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.back-link {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #94a3b8;
    text-decoration: none;
    margin-top: 20px;
    justify-content: center;
    transition: color 0.3s;
}

.back-link:hover {
    color: white;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Responsive */
@media (max-width: 576px) {
    .login-card {
        padding: 30px 20px;
    }
    
    .login-title {
        font-size: 1.5rem;
    }
}
</style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-user-shield"></i>
            </div>
            <h2 class="login-title">Admin Login</h2>
            <p class="login-subtitle">Access your administration panel</p>
        </div>

        <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-4">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required 
                       placeholder="Enter your username">
            </div>

            <div class="mb-4">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required 
                       placeholder="Enter your password">
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt me-2"></i>
                Login
            </button>
        </form>

        <a href="../index.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Home
        </a>
    </div>
</div>

<script>
// Add focus animation to inputs
document.querySelectorAll('.form-control').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.style.transform = 'translateY(-2px)';
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.style.transform = 'translateY(0)';
    });
});

// Add enter key submit
document.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        const form = document.querySelector('form');
        if (form) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.click();
            }
        }
    }
});

// Add loading state to button
document.querySelector('form').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Logging in...';
        submitBtn.disabled = true;
    }
});
</script>

</body>
</html>