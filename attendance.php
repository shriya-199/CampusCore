<?php
session_start();
require_once 'config.php';

// Only teachers and admins can manage attendance
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    $date = sanitize_input($_POST['date']);
    $student_ids = $_POST['student_id'];
    $statuses = $_POST['status'];
    
    foreach ($student_ids as $index => $student_id) {
        $status = $statuses[$index];
        
        // Check if attendance record exists
        $check_sql = "SELECT id FROM attendance WHERE student_id = ? AND class_id = ? AND date = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("iis", $student_id, $class_id, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing record
            $sql = "UPDATE attendance SET status = ? WHERE student_id = ? AND class_id = ? AND date = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siis", $status, $student_id, $class_id, $date);
        } else {
            // Insert new record
            $sql = "INSERT INTO attendance (student_id, class_id, date, status) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiss", $student_id, $class_id, $date, $status);
        }
        $stmt->execute();
    }
    
    set_flash_message('success', 'Attendance recorded successfully!');
    header("Location: attendance.php?class_id=" . $class_id);
    exit();
}

// Get class details
$class_sql = "SELECT c.* FROM classes c 
              WHERE c.id = ? AND " . 
              ($role === 'admin' ? "1=1" : "c.teacher_id = " . $user_id);
$stmt = $conn->prepare($class_sql);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();

if (!$class) {
    set_flash_message('error', 'Invalid class or insufficient permissions.');
    header("Location: dashboard.php");
    exit();
}

// Get students in the class
$students_sql = "SELECT u.id, u.name 
                 FROM users u 
                 JOIN student_class sc ON u.id = sc.student_id 
                 WHERE sc.class_id = ? AND sc.status = 'active' 
                 ORDER BY u.name";
$stmt = $conn->prepare($students_sql);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$students = $stmt->get_result();

// Get attendance for selected date
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$attendance_sql = "SELECT student_id, status 
                  FROM attendance 
                  WHERE class_id = ? AND date = ?";
$stmt = $conn->prepare($attendance_sql);
$stmt->bind_param("is", $class_id, $selected_date);
$stmt->execute();
$attendance_result = $stmt->get_result();
$attendance = [];
while ($row = $attendance_result->fetch_assoc()) {
    $attendance[$row['student_id']] = $row['status'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <?php $flash = get_flash_message(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        Attendance for Class <?php echo htmlspecialchars($class['grade_level'] . '-' . $class['section']); ?>
                    </h5>
                    <div>
                        <a href="attendance_report.php?class_id=<?php echo $class_id; ?>" class="btn btn-info">
                            <i class="bi bi-file-earmark-text"></i> View Report
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Select Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo $selected_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary">View Attendance</button>
                        </div>
                    </div>
                </form>

                <form method="POST">
                    <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($student = $students->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td>
                                            <input type="hidden" name="student_id[]" value="<?php echo $student['id']; ?>">
                                            <select name="status[]" class="form-select form-select-sm" style="width: auto;">
                                                <option value="present" <?php echo ($attendance[$student['id']] ?? '') === 'present' ? 'selected' : ''; ?>>Present</option>
                                                <option value="absent" <?php echo ($attendance[$student['id']] ?? '') === 'absent' ? 'selected' : ''; ?>>Absent</option>
                                                <option value="late" <?php echo ($attendance[$student['id']] ?? '') === 'late' ? 'selected' : ''; ?>>Late</option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-3">
                        <button type="submit" name="submit_attendance" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Save Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
