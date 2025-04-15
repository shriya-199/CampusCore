<?php
session_start();
require_once 'config.php';

// Only students and parents can view attendance
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'parent'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$student_name = $_SESSION['full_name'] ?? 'User'; // For student's own view initially
$student_id = 0; // Initialize
$page_title = "My Attendance";

// Get student ID (either directly for student or via ward for parent)
if ($role === 'student') {
    $student_id = $user_id;
} elseif ($role === 'parent') {
    $page_title = "Ward Attendance"; // Default for parent
    if (isset($_GET['student_id']) && filter_var($_GET['student_id'], FILTER_VALIDATE_INT)) {
        $requested_student_id = (int)$_GET['student_id'];

        // Validate link
        $sql_validate = "SELECT pw.student_id, u.full_name 
                         FROM parent_ward pw
                         JOIN users u ON pw.student_id = u.id
                         WHERE pw.parent_id = ? AND pw.student_id = ?";
        $stmt_validate = $conn->prepare($sql_validate);
        if ($stmt_validate) {
            $stmt_validate->bind_param("ii", $user_id, $requested_student_id);
            $stmt_validate->execute();
            $result_validate = $stmt_validate->get_result();

            if ($valid_ward = $result_validate->fetch_assoc()) {
                $student_id = $valid_ward['student_id'];
                $student_name = $valid_ward['full_name']; // Get the specific ward's name
                $page_title = "Attendance for " . htmlspecialchars($student_name);
            } else {
                set_flash_message('error', 'You are not authorized to view attendance for this student.');
                $student_id = 0;
            }
            $stmt_validate->close();
        } else {
            set_flash_message('error', 'Database error validating access.');
            error_log("Prepare failed (parent_ward validate): (" . $conn->errno . ") " . $conn->error);
            $student_id = 0;
        }
    } else {
        // No student_id provided for parent
        set_flash_message('warning', 'Please select a child from your dashboard to view their attendance.');
        redirect(BASE_URL . 'dashboard.php');
    }
} else {
    // Should not happen based on initial check, but safety first
    set_flash_message('error', 'Access Denied.');
    redirect(BASE_URL . 'index.php');
}

if (!$student_id) {
    set_flash_message('error', 'No student record found or access denied.');
    header("Location: dashboard.php");
    exit();
}

// Get student's class
$class_sql = "SELECT c.* 
              FROM classes c 
              JOIN student_class sc ON c.id = sc.class_id 
              WHERE sc.student_id = ? AND sc.status = 'active'";
$stmt = $conn->prepare($class_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();

if (!$class) {
    set_flash_message('error', 'No class assignment found.');
    header("Location: dashboard.php");
    exit();
}

// Get student info
$student_sql = "SELECT full_name FROM users WHERE id = ?";
$stmt = $conn->prepare($student_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Get attendance records
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$attendance_sql = "SELECT * FROM attendance 
                  WHERE student_id = ? AND class_id = ? 
                  AND MONTH(date) = ? AND YEAR(date) = ?
                  ORDER BY date DESC";
$stmt = $conn->prepare($attendance_sql);
$stmt->bind_param("iiii", $student_id, $class['id'], $month, $year);
$stmt->execute();
$attendance = $stmt->get_result();

// Calculate attendance statistics
$stats_sql = "SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
              FROM attendance 
              WHERE student_id = ? AND class_id = ? 
              AND MONTH(date) = ? AND YEAR(date) = ?";
$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("iiii", $student_id, $class['id'], $month, $year);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

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
                        <i class="bi bi-calendar-check me-2"></i><?php echo htmlspecialchars($page_title); ?>
                        <small class="text-muted">
                            (Class <?php echo htmlspecialchars($class['grade_level'] . '-' . $class['section']); ?>)
                        </small>
                    </h5>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Month</label>
                            <select name="month" class="form-select" onchange="this.form.submit()">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $month == $i ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Year</label>
                            <select name="year" class="form-select" onchange="this.form.submit()">
                                <?php 
                                $current_year = date('Y');
                                for ($i = $current_year; $i >= $current_year - 1; $i--): 
                                ?>
                                    <option value="<?php echo $i; ?>" <?php echo $year == $i ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </form>

                <?php if ($stats['total_days'] > 0): ?>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h3><?php echo $stats['present_days']; ?></h3>
                                    <p class="mb-0">Present Days</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h3><?php echo $stats['absent_days']; ?></h3>
                                    <p class="mb-0">Absent Days</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-dark">
                                <div class="card-body text-center">
                                    <h3><?php echo $stats['late_days']; ?></h3>
                                    <p class="mb-0">Late Days</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h3><?php echo number_format(($stats['present_days'] / $stats['total_days']) * 100, 1); ?>%</h3>
                                    <p class="mb-0">Attendance Rate</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($record = $attendance->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y (D)', strtotime($record['date'])); ?></td>
                                        <td>
                                            <?php 
                                            $status_class = match($record['status']) {
                                                'present' => 'text-success',
                                                'absent' => 'text-danger',
                                                'late' => 'text-warning'
                                            };
                                            echo "<span class='{$status_class}'>" . 
                                                 ucfirst($record['status']) . "</span>";
                                            ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No attendance records found for this month.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
