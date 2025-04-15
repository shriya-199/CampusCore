<?php
session_start();
require_once 'config.php';

// Only students and parents can view grades
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'parent'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$student_name = $_SESSION['full_name'] ?? 'User'; // For student's own view initially
$student_id = 0; // Initialize
$page_title = "My Grades";

// Get student ID (either directly for student or via ward for parent)
if ($role === 'student') {
    $student_id = $user_id;
} elseif ($role === 'parent') {
    $page_title = "Ward Grades"; // Default for parent
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
                $page_title = "Grades for " . htmlspecialchars($student_name);
            } else {
                set_flash_message('error', 'You are not authorized to view grades for this student.');
                $student_id = 0;
            }
            $stmt_validate->close();
        } else {
            set_flash_message('error', 'Database error validating access.');
            error_log("Prepare failed (parent_ward validate grades): (" . $conn->errno . ") " . $conn->error);
            $student_id = 0;
        }
    } else {
        // No student_id provided for parent
        set_flash_message('warning', 'Please select a child from your dashboard to view their grades.');
        redirect(BASE_URL . 'dashboard.php');
    }
} else {
    // Should not happen based on initial check
    set_flash_message('error', 'Access Denied.');
    redirect(BASE_URL . 'index.php');
}

if (!$student_id) {
    // If $student_id is still 0 here, access was denied or validation failed.
    // Flash message should be set already.
    if (!isset($_SESSION['flash'])) {
        set_flash_message('error', 'Cannot determine student to view grades for.');
    }
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

// Get student's subjects
$subjects_sql = "SELECT id, name FROM subjects WHERE class_id = ? ORDER BY name";
$stmt = $conn->prepare($subjects_sql);
$stmt->bind_param("i", $class['id']);
$stmt->execute();
$subjects = $stmt->get_result();

// Get student's grades if subject is selected
$selected_subject = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$grades = [];
if ($selected_subject) {
    $grades_sql = "SELECT * FROM grades 
                   WHERE student_id = ? AND subject_id = ? 
                   ORDER BY test_date DESC, test_name";
    $stmt = $conn->prepare($grades_sql);
    $stmt->bind_param("ii", $student_id, $selected_subject);
    $stmt->execute();
    $grades = $stmt->get_result();
}

// Get student info
$student_sql = "SELECT full_name FROM users WHERE id = ?";
$stmt = $conn->prepare($student_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
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
                        <i class="bi bi-clipboard-data me-2"></i><?php echo htmlspecialchars($page_title); ?>
                        <small class="text-muted">
                            (Class <?php echo htmlspecialchars($class['grade_level'] . '-' . $class['section']); ?>)
                        </small>
                    </h5>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <?php if ($role === 'parent' && $student_id > 0): // Pass student ID for parents ?>
                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Select Subject</label>
                            <select name="subject_id" class="form-select" onchange="this.form.submit()">
                                <option value="">Select Subject</option>
                                <?php while ($subject = $subjects->fetch_assoc()): ?>
                                    <option value="<?php echo $subject['id']; ?>" <?php echo $selected_subject == $subject['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </form>

                <?php if ($selected_subject && $grades->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Test Name</th>
                                    <th>Date</th>
                                    <th>Score</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_percentage = 0;
                                $grade_count = 0;
                                while ($grade = $grades->fetch_assoc()): 
                                    $percentage = ($grade['score'] / $grade['max_score']) * 100;
                                    $grade_class = $percentage >= 90 ? 'text-success' : 
                                                 ($percentage >= 70 ? 'text-primary' : 
                                                 ($percentage >= 50 ? 'text-warning' : 'text-danger'));
                                    $total_percentage += $percentage;
                                    $grade_count++;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($grade['test_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($grade['test_date'])); ?></td>
                                        <td>
                                            <?php 
                                            echo number_format($grade['score'], 1) . " / " . 
                                                 number_format($grade['max_score'], 1); 
                                            ?>
                                        </td>
                                        <td class="<?php echo $grade_class; ?>">
                                            <?php echo number_format($percentage, 1); ?>%
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if ($grade_count > 0): ?>
                                    <tr class="table-secondary">
                                        <td colspan="3"><strong>Average</strong></td>
                                        <td>
                                            <strong>
                                                <?php 
                                                $average = $total_percentage / $grade_count;
                                                $avg_class = $average >= 90 ? 'text-success' : 
                                                            ($average >= 70 ? 'text-primary' : 
                                                            ($average >= 50 ? 'text-warning' : 'text-danger'));
                                                echo "<span class='{$avg_class}'>" . 
                                                     number_format($average, 1) . "%</span>";
                                                ?>
                                            </strong>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($selected_subject): ?>
                    <div class="alert alert-info">
                        No grades recorded for this subject yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
