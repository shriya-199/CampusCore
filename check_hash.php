<?php
require_once 'config.php';

$sql = "SELECT username, password FROM users WHERE username = 'admin'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Current admin password hash in database: " . $row['password'] . "\n";
    echo "Expected hash from SQL file: \$2y\$10\$8WkWUbQyQHzHWGUTqGmRJOEUKEFBkGAEWqrHBKxGwgzpBkP9JMdVi\n";
    
    // Test if 'admin123' verifies against the stored hash
    echo "Testing 'admin123' against stored hash: " . 
        (password_verify('admin123', $row['password']) ? "MATCHES" : "DOES NOT MATCH") . "\n";
} else {
    echo "Admin user not found in database!";
}
?>
