<?php
require_once 'config.php';
require_once 'auth_check.php';

// Check if user is a parent
if ($_SESSION['role'] !== 'parent') {
    redirect('login.php');
}

// Get parent's information and their wards
$parent_id = $_SESSION['user_id'];
$sql = "SELECT u.full_name as parent_name, u.id as parent_user_id, 
        s.id as student_id, s.full_name as student_name,
        c.name as class_name, 
        pw.relationship
        FROM users u
        JOIN parent_ward pw ON u.id = pw.parent_id
        JOIN users s ON pw.student_id = s.id
        LEFT JOIN student_class sc ON s.id = sc.student_id AND sc.status = 'active'
        LEFT JOIN classes c ON sc.class_id = c.id
        WHERE u.id = ? AND u.role = 'parent'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$result = $stmt->get_result();
$parent_info = array();
$wards = array();
$row_data = $result->fetch_assoc();
if ($row_data) {
    if (empty($parent_info)) { 
         $parent_info['full_name'] = $row_data['parent_name'];
    }
    $wards[] = array(
        'id' => $row_data['student_id'],
        'name' => $row_data['student_name'],
        'class' => $row_data['class_name'] ?? 'N/A', 
        'relationship' => $row_data['relationship']
    );
}
do {
    if ($row_data) { 
        $wards[] = array(
            'id' => $row_data['student_id'],
            'name' => $row_data['student_name'],
            'class' => $row_data['class_name'] ?? 'N/A', 
            'relationship' => $row_data['relationship']
        );
    }
} while ($row_data = $result->fetch_assoc()); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - Campus Core</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Welcome, <?php echo isset($parent_info['full_name']) ? htmlspecialchars($parent_info['full_name']) : 'Parent'; ?>!</h2>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">My Wards</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($wards) > 0): ?>
                            <ul class="list-group">
                                <?php foreach ($wards as $ward): ?>
                                    <li class="list-group-item">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($ward['name']); ?></h6>
                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($ward['class']); ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">No wards assigned.</p>
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
                            <?php foreach ($wards as $ward): ?>
                                <a href="view_attendance.php?student_id=<?php echo $ward['id']; ?>" class="list-group-item list-group-item-action">
                                    <i class="bi bi-calendar-check me-2"></i> View <?php echo htmlspecialchars($ward['name']); ?>'s Attendance
                                </a>
                                <a href="view_grades.php?student_id=<?php echo $ward['id']; ?>" class="list-group-item list-group-item-action">
                                    <i class="bi bi-journal-text me-2"></i> View <?php echo htmlspecialchars($ward['name']); ?>'s Grades
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
