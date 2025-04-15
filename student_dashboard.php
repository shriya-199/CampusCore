<?php
require_once 'config.php';
require_once 'auth_check.php';

// Check if user is a student
if ($_SESSION['role'] !== 'student') {
    redirect('login.php');
}

// Get student's information
$student_id = $_SESSION['user_id'];
$sql = "SELECT u.*, c.name as class_name 
        FROM users u
        LEFT JOIN student_class sc ON u.id = sc.student_id
        LEFT JOIN classes c ON sc.class_id = c.id
        WHERE u.id = ? AND u.role = 'student' AND sc.status = 'active'"; // Assuming student has one active class
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Get student's subjects
$sql = "SELECT s.name as subject_name
        FROM student_class sc
        JOIN subjects s ON s.class_id = sc.class_id
        WHERE sc.student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Campus Core</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Welcome, <?php echo htmlspecialchars($student['full_name']); ?>!</h2>
        <p class="text-muted"><?php echo htmlspecialchars($student['class_name']); ?></p>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">My Subjects</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($subjects) > 0): ?>
                            <ul class="list-group">
                                <?php foreach ($subjects as $subject): ?>
                                    <li class="list-group-item">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">No subjects assigned yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="view_attendance.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-calendar-check me-2"></i> View Attendance
                            </a>
                            <a href="view_grades.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-journal-text me-2"></i> View Grades
                            </a>
                            <a href="view_timetable.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-clock me-2"></i> View Timetable
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
