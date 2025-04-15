<?php
require_once 'config.php';
require_once 'auth_check.php';

// Check if user is a teacher
if ($_SESSION['role'] !== 'teacher') {
    redirect('login.php');
}

// Get teacher's information
$teacher_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ? AND role = 'teacher'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

// Get teacher's assigned subjects
$sql = "SELECT s.name as subject_name, c.name as class_name
        FROM teacher_subject ts
        JOIN subjects s ON ts.subject_id = s.id
        JOIN classes c ON s.class_id = c.id
        WHERE ts.teacher_id = ?
        GROUP BY s.id, c.id";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Campus Core</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Welcome, <?php echo htmlspecialchars($teacher['full_name']); ?>!</h2>
        
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
                                        (<?php echo htmlspecialchars($subject['class_name']); ?>)
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
                            <a href="manage_attendance.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-calendar-check me-2"></i> Manage Attendance
                            </a>
                            <a href="manage_grades.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-journal-text me-2"></i> Manage Grades
                            </a>
                            <a href="view_students.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-people me-2"></i> View Students
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
