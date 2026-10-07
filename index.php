<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_user = trim($_POST['username'] ?? '');
    $input_pass = trim($_POST['password'] ?? '');

    if (!empty($input_user) && !empty($input_pass)) {
        
        // Step 1: Query the Internal Administrative/Staff Table First
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$input_user]);
        $user_row = $stmt->fetch();
        
        if ($user_row) {
            // Validate via Hashed String or fallback to plain-text match for older test profiles
            if (password_verify($input_pass, $user_row['password']) || $input_pass === $user_row['password']) {
                $_SESSION['user_id']  = $user_row['id'];
                $_SESSION['username'] = $user_row['username'];
                $_SESSION['role']     = $user_row['role']; // e.g., 'Admin' or 'Manager'
                
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Password verification failed for internal staff member.";
            }
        } else {
            // Step 2: Fallback query to find client/distributor profiles if staff row is empty
            $cust_stmt = $pdo->prepare("SELECT * FROM customers WHERE username = ? LIMIT 1");
            $cust_stmt->execute([$input_user]);
            $cust_row = $cust_stmt->fetch();
            
            if ($cust_row) {
                if (password_verify($input_pass, $cust_row['password']) || $input_pass === $cust_row['password']) {
                    $_SESSION['user_id']  = $cust_row['id'];
                    $_SESSION['username'] = $cust_row['username'];
                    $_SESSION['role']     = 'Distributor'; // Enforce clear role separation for clients
                    
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Password verification failed for distributor account.";
                }
            } else {
                $error = "Authorization profile identity could not be located.";
            }
        }
    } else {
        $error = "Please fill in all authorization fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danan Foods CRM - Portal Access</title>
    <link rel="stylesheet" href="assets/css/style.css?v=1.3">
    <style>
        body {
            display: flex; align-items: center; justify-content: center;
            background: #1e293b; min-height: 100vh; padding: 1rem;
            font-family: system-ui, -apple-system, sans-serif;
            margin: 0;
        }
        .login-card {
            background: #ffffff; width: 100%; max-width: 420px;
            padding: 2.5rem; border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        .login-card__brand {
            text-align: center; font-size: 1.5rem; font-weight: 700;
            color: #0f172a; margin-bottom: 0.25rem;
        }
        .login-card__sub {
            text-align: center; color: #64748b; font-size: 0.9rem; margin-bottom: 2rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block; font-size: 0.85rem; font-weight: 600;
            color: #475569; margin-bottom: 0.5rem;
        }
        .form-control {
            width: 100%; padding: 0.65rem 0.75rem; border: 1px solid #cbd5e1;
            border-radius: 6px; font-size: 0.95rem; color: #1e293b;
            box-sizing: border-box; transition: border-color 0.2s ease;
        }
        .form-control:focus {
            outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .btn--primary {
            background: #3b82f6; color: white; border: none; font-weight: 600;
            cursor: pointer; border-radius: 6px; transition: background 0.2s ease;
        }
        .btn--primary:hover {
            background: #2563eb;
        }
        .alert--danger {
            background: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b;
            border-radius: 4px; margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-card__brand">🍇 Danan Foods</div>
    <div class="login-card__sub">Sales Division CRM Portal</div>

    <?php if (!empty($error)): ?>
        <div class="alert alert--danger" style="font-size: 0.9rem; text-align: center; padding: 0.75rem;">
            ⚠️ <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-control" placeholder="admin or username" required autocomplete="username">
        </div>
        <div class="form-group" style="margin-bottom: 1.75rem;">
            <label>Password</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn--primary" style="width: 100%; padding: 0.75rem;">Authorize Entry</button>
    </form>
</div>

</body>
</html>