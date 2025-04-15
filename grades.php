<?php
session_start();
require_once 'config.php';

// Only teachers and admins can manage grades
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_grades'])) {
    $test_name = sanitize_input($_POST['test_name']);
    $test_date = sanitize_input($_POST['test_date']);
    $subject_id = sanitize_input($_POST['subject_id']);
    $student_ids = $_POST['student_id'];
    $scores = $_POST['score'];
    $max_score = floatval($_POST['max_score']);
    
    foreach ($student_ids as $index => $student_id) {
        $score = floatval($scores[$index]);
        
        // Check if grade record exists
        $check_sql = "SELECT id FROM grades WHERE student_id = ? AND subject_id = ? AND test_name = ? AND test_date = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("iiss", $student_id, $subject_id, $test_name, $test_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing record
            $sql = "UPDATE grades SET score = ?, max_score = ? WHERE student_id = ? AND subject_id = ? AND test_name = ? AND test_date = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ddiiss", $score, $max_score, $student_id, $subject_id, $test_name, $test_date);
        } else {
            // Insert new record
            $sql = "INSERT INTO grades (student_id, subject_id, test_name, test_date, score, max_score) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iissdd", $student_id, $subject_id, $test_name, $test_date, $score, $max_score);
        }
        $stmt->execute();
    }
    
    set_flash_message('success', 'Grades recorded successfully!');
    header("Location: grades.php?class_id=" . $class_id . "&subject_id=" . $subject_id);
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

// Get subjects for this class
$subjects_sql = "SELECT id, name FROM subjects WHERE class_id = ? ORDER BY name";
$stmt = $conn->prepare($subjects_sql);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$subjects = $stmt->get_result();

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

// Get grades if subject is selected
$selected_subject = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$grades = [];
if ($selected_subject) {
    $grades_sql = "SELECT g.* FROM grades g 
                   WHERE g.subject_id = ? 
                   ORDER BY g.test_date DESC, g.test_name";
    $stmt = $conn->prepare($grades_sql);
    $stmt->bind_param("i", $selected_subject);
    $stmt->execute();
    $grades_result = $stmt->get_result();
    while ($grade = $grades_result->fetch_assoc()) {
        $grades[$grade['test_name'] . '_' . $grade['test_date']][$grade['student_id']] = [
            'score' => $grade['score'],
            'max_score' => $grade['max_score']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades - <?php echo SITE_NAME; ?></title>
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
                        Grades for Class <?php echo htmlspecialchars($class['grade_level'] . '-' . $class['section']); ?>
                    </h5>
                    <div>
                        <a href="grades_report.php?class_id=<?php echo $class_id; ?>" class="btn btn-info">
                            <i class="bi bi-file-earmark-text"></i> View Report
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
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

                <?php if ($selected_subject): ?>
                    <div class="mt-4">
                        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addGradesModal">
                            <i class="bi bi-plus-circle"></i> Add New Grades
                        </button>

                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <?php foreach ($grades as $test_key => $test_grades): 
                                            list($test_name, $test_date) = explode('_', $test_key);
                                        ?>
                                            <th><?php echo htmlspecialchars($test_name) . '<br>' . date('M d, Y', strtotime($test_date)); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $students->data_seek(0);
                                    while ($student = $students->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                                            <?php foreach ($grades as $test_grades): ?>
                                                <td>
                                                    <?php 
                                                    if (isset($test_grades[$student['id']])) {
                                                        $grade = $test_grades[$student['id']];
                                                        $percentage = ($grade['score'] / $grade['max_score']) * 100;
                                                        $grade_class = $percentage >= 90 ? 'text-success' : 
                                                                     ($percentage >= 70 ? 'text-primary' : 
                                                                     ($percentage >= 50 ? 'text-warning' : 'text-danger'));
                                                        echo "<span class='{$grade_class}'>" . 
                                                             number_format($grade['score'], 1) . "/" . number_format($grade['max_score'], 1) . 
                                                             " (" . number_format($percentage, 1) . "%)</span>";
                                                    } else {
                                                        echo "-";
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Grades Modal -->
    <div class="modal fade" id="addGradesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Grades</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Test Name</label>
                                <input type="text" name="test_name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Test Date</label>
                                <input type="date" name="test_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Maximum Score</label>
                                <input type="number" name="max_score" class="form-control" step="0.1" required>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $students->data_seek(0);
                                    while ($student = $students->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($student['name']); ?>
                                                <input type="hidden" name="student_id[]" value="<?php echo $student['id']; ?>">
                                            </td>
                                            <td>
                                                <input type="number" name="score[]" class="form-control" step="0.1" required>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_grades" class="btn btn-primary">Save Grades</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
