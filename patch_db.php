<?php
require_once 'config/db.php';

try {
    // 1. Expand the password column sizes so hashes aren't cut off
    $pdo->exec("ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NOT NULL");
    $pdo->exec("ALTER TABLE customers MODIFY COLUMN password VARCHAR(255) NOT NULL");
    echo "✅ Password column lengths updated to VARCHAR(255).<br>";

    // 2. Safely encrypt your existing 'admin' account if it's plain text
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($admin && substr($admin['password'], 0, 1) !== '$') {
        $new_hash = password_hash('admin', PASSWORD_BCRYPT);
        $up = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        $up->execute([$new_hash]);
        echo "✅ Existing plain-text 'admin' password converted to secure hash.<br>";
    }

    // 3. Safely encrypt your existing 'multan' account if it's plain text
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = 'multan' LIMIT 1");
    $stmt->execute();
    $multan = $stmt->fetch();

    if ($multan && substr($multan['password'], 0, 1) !== '$') {
        $new_hash = password_hash('multan', PASSWORD_BCRYPT);
        $up = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'multan'");
        $up->execute([$new_hash]);
        echo "✅ Existing plain-text 'multan' password converted to secure hash.<br>";
    }

    echo "<br><strong>Database patch successful! Delete this file from your server now.</strong>";

} catch (Exception $e) {
    die("❌ Patch failed: " . $e->getMessage());
}
?>