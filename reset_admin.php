<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "DELETE FROM users WHERE username = 'admin'";
$conn->query($sql);

$sql = "INSERT INTO users (username, password, role, full_name, email) VALUES (?, ?, 'admin', 'System Admin', 'admin@school.com')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $hashed_password);
$username = 'admin';

if ($stmt->execute()) {
    echo "Admin user created successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
    echo "Role: admin<br>";
} else {
    echo "Error creating admin user: " . $conn->error;
}
?>
