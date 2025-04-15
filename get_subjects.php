<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Basic validation and role check
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher') || !isset($_GET['class_id'])) {
    echo json_encode(['error' => 'Unauthorized or missing parameters']);
    exit();
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$class_id = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);

if (!$class_id) {
    echo json_encode(['error' => 'Invalid class ID']);
    exit();
}

$subjects = [];

if ($user_role === 'admin') {
    // Admin gets all active subjects for the selected class
    $sql = "SELECT id, name FROM subjects WHERE class_id = ? AND status = 'active' ORDER BY name";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $stmt->close();
    } else {
        // Log error internally if possible
        error_log("Database prepare error (Admin) in get_subjects.php: " . $conn->error);
        echo json_encode(['error' => 'Database query error']); 
        exit();
    }
} elseif ($user_role === 'teacher') {
    // Teacher gets only the active subjects they are assigned to in the selected class
    $sql = "SELECT DISTINCT s.id, s.name 
            FROM subjects s
            JOIN teacher_subject ts ON s.id = ts.subject_id
            WHERE s.class_id = ? AND ts.teacher_id = ? AND s.status = 'active'
            ORDER BY s.name";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $class_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $stmt->close();
    } else {
         // Log error internally if possible
        error_log("Database prepare error (Teacher) in get_subjects.php: " . $conn->error);
        echo json_encode(['error' => 'Database query error']);
        exit();
    }
}

echo json_encode($subjects);

$conn->close();
?>
