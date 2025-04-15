<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'academic_tracker');
define('SITE_NAME', 'Academic Progress Tracker');
define('SITE_URL', 'http://localhost/academic_tracker');
define('BASE_URL', rtrim(SITE_URL, '/') . '/'); // Ensure trailing slash

date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

function require_login() {
    if (!is_logged_in()) {
        redirect('index.php');
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        redirect('dashboard.php');
    }
}

function require_teacher() {
    require_login();
    if ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin') {
        redirect('dashboard.php');
    }
}

function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Displays the flash message using Bootstrap alert format if one exists.
 *
 * @return void
 */
function display_flash_message(): void {
    $flash = get_flash_message(); // Use the existing get_flash_message
    if ($flash) {
        $type = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
        // Use Bootstrap alert classes
        echo "<div class='alert alert-" . $type . " alert-dismissible fade show' role='alert'>";
        echo $message;
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo "</div>";
    }
}

function get_user_classes($user_id, $role) {
    global $conn;
    if ($role === 'teacher') {
        return $conn->query("SELECT * FROM classes WHERE teacher_id = $user_id");
    } elseif ($role === 'student') {
        return $conn->query("SELECT c.* FROM classes c 
                           JOIN student_class sc ON c.id = sc.class_id 
                           WHERE sc.student_id = $user_id AND sc.status = 'active'");
    }
    return $conn->query("SELECT * FROM classes");
}

function get_class_students($class_id) {
    global $conn;
    return $conn->query("SELECT u.* FROM users u 
                        JOIN student_class sc ON u.id = sc.student_id 
                        WHERE sc.class_id = $class_id AND sc.status = 'active' 
                        AND u.role = 'student'");
}

function get_student_grades($student_id, $subject_id = null) {
    global $conn;
    $query = "SELECT g.*, s.name as subject_name FROM grades g 
              JOIN subjects s ON g.subject_id = s.id 
              WHERE g.student_id = $student_id";
    if ($subject_id) {
        $query .= " AND g.subject_id = $subject_id";
    }
    $query .= " ORDER BY g.test_date DESC";
    return $conn->query($query);
}

function get_class_activities($class_id) {
    global $conn;
    return $conn->query("SELECT * FROM activities 
                        WHERE class_id = $class_id 
                        ORDER BY activity_date DESC");
}
?>
