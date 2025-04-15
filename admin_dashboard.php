<?php
require_once 'config.php';
require_once 'auth_check.php';

// Check if user is an admin
if ($_SESSION['role'] !== 'admin') {
    redirect('login.php');
}

// Get counts
$counts = array();

$sql = "SELECT 
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as student_count,
            SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as teacher_count,
            SUM(CASE WHEN role = 'parent' THEN 1 ELSE 0 END) as parent_count
        FROM users";
$result = $conn->query($sql);
$counts = $result->fetch_assoc();

$sql = "SELECT COUNT(*) as class_count FROM classes";
$result = $conn->query($sql);
$class_count = $result->fetch_assoc()['class_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Campus Core</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Admin Dashboard</h2>
        
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Students</h5>
                        <p class="card-text display-6"><?php echo $counts['student_count']; ?></p>
                        <a href="manage_users.php" class="text-white text-decoration-none">View Details <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Teachers</h5>
                        <p class="card-text display-6"><?php echo $counts['teacher_count']; ?></p>
                        <a href="manage_users.php" class="text-white text-decoration-none">View Details <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Parents</h5>
                        <p class="card-text display-6"><?php echo $counts['parent_count']; ?></p>
                        <a href="manage_users.php" class="text-white text-decoration-none">View Details <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">B.Tech Years</h5>
                        <p class="card-text display-6"><?php echo $class_count; ?></p>
                        <a href="manage_classes.php" class="text-white text-decoration-none">View Details <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="manage_users.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-people me-2"></i> Manage Users
                            </a>
                            <a href="manage_classes.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-book me-2"></i> Manage B.Tech Years
                            </a>
                            <a href="manage_subjects.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-journal me-2"></i> Manage Subjects
                            </a>
                            <a href="manage_attendance.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-calendar-check me-2"></i> View Attendance Reports
                            </a>
                            <a href="manage_grades.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-graph-up me-2"></i> View Grade Reports
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
