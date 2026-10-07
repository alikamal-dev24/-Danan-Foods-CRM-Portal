<?php
require_once 'config/db.php';

try {
    // This fetches the name of the database your PDO connection is actually using
    $current_db = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "<h3>Active Database Connection: <span style='color:blue;'>$current_db</span></h3>";

    // This lists out the true columns of the customers table in THAT active database
    echo "<h4>Columns found in 'customers' table:</h4><ul>";
    $q = $pdo->query("DESCRIBE customers");
    while($row = $q->fetch(PDO::FETCH_ASSOC)) {
        echo "<li>" . $row['Field'] . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<h3 style='color:red;'>Connection Error: " . $e->getMessage() . "</h3>";
}