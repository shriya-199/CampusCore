<?php
require_once 'config.php';
require_once 'includes/functions.php'; // Include the functions file

if (is_logged_in()) {
    redirect('dashboard.php');
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    redirect('index.php');
}

$required_fields = ['username', 'password'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        set_flash_message('danger', 'Username and password are required.');
        redirect('index.php');
    }
}

$username = sanitize_input($_POST['username']);
$password = $_POST['password']; 

try {
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            $update_sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
            if ($update_stmt = $conn->prepare($update_sql)) {
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            error_log("Successful login: User {$user['username']} ({$user['role']})");
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    redirect('admin_dashboard.php');
                    break;
                case 'teacher':
                    redirect('teacher_dashboard.php');
                    break;
                case 'student':
                    redirect('student_dashboard.php');
                    break;
                case 'parent':
                    redirect('parent_dashboard.php');
                    break;
                default:
                    redirect('index.php');
            }
        } else {
            error_log("Failed login attempt: Invalid password for user {$username}");
            set_flash_message('danger', 'Invalid username or password.');
            redirect('index.php');
        }
    } else {
        error_log("Failed login attempt: User not found - {$username}");
        set_flash_message('danger', 'Invalid username or password.');
        redirect('index.php');
    }
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    set_flash_message('danger', 'An error occurred. Please try again later.');
    redirect('index.php');
}
?>
