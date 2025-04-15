<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Setting up Academic Progress Tracker\n\n";

echo "1. Testing Database Connection...\n";

$host = "localhost";
$username = "root";
$password = "";

try {
    $conn = new mysqli($host, $username, $password);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    echo "✓ Initial database connection successful!\n";
    
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS academic_tracker";
    if ($conn->query($sql)) {
        echo "✓ Database 'academic_tracker' created or already exists\n";
    } else {
        throw new Exception("Error creating database: " . $conn->error);
    }
    
    // Select the database
    $conn->select_db("academic_tracker");
    echo "✓ Database selected successfully!\n";
    
    // Set charset
    $conn->set_charset("utf8mb4");
    echo "✓ Character set configured\n";
    
} catch (Exception $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}

// 2. Create tables
echo "\n2. Creating Tables...\n";
$sql_file = file_get_contents('database.sql');
$sql_statements = explode(';', $sql_file);

foreach ($sql_statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement)) {
        try {
            if ($conn->query($statement)) {
                echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
            } else {
                echo "✗ Error executing: " . substr($statement, 0, 50) . "...\n";
                echo "Error message: " . $conn->error . "\n";
            }
        } catch (Exception $e) {
            // Skip errors for "already exists" to allow rerunning the script
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "✗ Error: " . $e->getMessage() . "\n";
            }
        }
    }
}

// 3. Verify admin user
echo "\n3. Verifying Admin User...\n";
$sql = "SELECT * FROM users WHERE username = 'admin'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "✓ Admin user exists\n";
    echo "Username: admin\n";
    echo "Role: " . $user['role'] . "\n";
    echo "Email: " . $user['email'] . "\n";
} else {
    echo "✗ Admin user not found. Creating admin user...\n";
    
    // Create admin user
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (username, password, role, full_name, email) 
            VALUES ('admin', ?, 'admin', 'System Administrator', 'admin@school.com')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $hashed_password);
    
    if ($stmt->execute()) {
        echo "✓ Admin user created successfully!\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
        echo "Role: admin\n";
    } else {
        echo "✗ Error creating admin user: " . $stmt->error . "\n";
    }
}

// 4. Final check
echo "\n4. Setup Complete!\n";
echo "You can now:\n";
echo "1. Go to index.php in your web browser\n";
echo "2. Log in with:\n";
echo "   - Username: admin\n";
echo "   - Password: admin123\n";
echo "   - Role: admin\n";

$conn->close();
?>
