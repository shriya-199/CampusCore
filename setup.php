<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Setting up Academic Progress Tracker</h2>";

// 1. Test database connection
echo "<h3>1. Testing Database Connection...</h3>";
require_once 'config.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "✓ Database connection successful!<br>";

// 2. Create database and tables
echo "<h3>2. Creating Database and Tables...</h3>";
$sql_file = file_get_contents('database.sql');
$sql_statements = explode(';', $sql_file);

foreach ($sql_statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement)) {
        if ($conn->query($statement)) {
            echo "✓ Executed: " . substr($statement, 0, 50) . "...<br>";
        } else {
            echo "✗ Error executing: " . substr($statement, 0, 50) . "...<br>";
            echo "Error message: " . $conn->error . "<br>";
        }
    }
}

// 3. Verify admin user
echo "<h3>3. Verifying Admin User...</h3>";
$sql = "SELECT * FROM users WHERE username = 'admin'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "✓ Admin user exists<br>";
    echo "Username: admin<br>";
    echo "Role: " . $user['role'] . "<br>";
    echo "Email: " . $user['email'] . "<br>";
} else {
    echo "✗ Admin user not found. Creating admin user...<br>";
    
    // Create admin user
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (username, password, role, full_name, email) 
            VALUES ('admin', ?, 'admin', 'System Administrator', 'admin@school.com')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $hashed_password);
    
    if ($stmt->execute()) {
        echo "✓ Admin user created successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
        echo "Role: admin<br>";
    } else {
        echo "✗ Error creating admin user: " . $stmt->error . "<br>";
    }
}

// 4. Final check
echo "<h3>4. Setup Complete!</h3>";
echo "You can now:<br>";
echo "1. <a href='index.php'>Go to login page</a><br>";
echo "2. Log in with:<br>";
echo "   - Username: admin<br>";
echo "   - Password: admin123<br>";
echo "   - Role: admin<br>";
?>
