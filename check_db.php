<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Database Check</h2>";

$result = $conn->query("SHOW TABLES LIKE 'users'");
echo "Users table exists: " . ($result->num_rows > 0 ? "Yes" : "No") . "<br>";

if ($result->num_rows > 0) {
    $result = $conn->query("SELECT * FROM users");
    echo "Number of users in database: " . $result->num_rows . "<br><br>";
    
    echo "<h3>Users in database:</h3>";
    while ($row = $result->fetch_assoc()) {
        echo "Username: " . $row['username'] . "<br>";
        echo "Role: " . $row['role'] . "<br>";
        echo "Password Hash: " . $row['password'] . "<br><br>";
    }
}
?>
