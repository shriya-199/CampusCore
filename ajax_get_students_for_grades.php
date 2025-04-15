<?php
session_start();
require_once 'config.php';

// Role check: Allow Admin and Teacher
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    // Silently exit or return an error message?
    // For security, maybe just exit or return minimal error.
    http_response_code(403); // Forbidden
    echo '<div class="alert alert-danger">Access Denied.</div>';
    exit();
}

// Validate input
$class_id = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
$subject_id = filter_input(INPUT_GET, 'subject_id', FILTER_VALIDATE_INT);
$test_name = trim($_GET['test_name'] ?? '');

if (!$class_id || !$subject_id || empty($test_name)) {
    http_response_code(400); // Bad Request
    echo '<div class="alert alert-warning">Missing Class ID, Subject ID, or Test Name.</div>';
    exit();
}

$students = [];
$existing_scores = [];

// Fetch students enrolled in the class
// Join users table to get names and student_class for roll number
$sql_students = "SELECT u.id, u.username, u.full_name, sc.roll_number 
                 FROM users u 
                 JOIN student_class sc ON u.id = sc.student_id 
                 WHERE sc.class_id = ? AND u.role = 'student' AND u.status = 'active' AND sc.status = 'active' 
                 ORDER BY u.username";

$stmt_students = $conn->prepare($sql_students);
if ($stmt_students) {
    $stmt_students->bind_param("i", $class_id);
    $stmt_students->execute();
    $result_students = $stmt_students->get_result();
    while ($row = $result_students->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt_students->close();
} else {
    // Handle error - maybe log it
    error_log("Error preparing student fetch query in ajax_get_students_for_grades: " . $conn->error);
    echo '<div class="alert alert-danger">Error fetching student list.</div>';
    exit(); 
}

// Fetch existing scores for these students, subject, and test name
if (!empty($students)) {
    $student_ids = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($student_ids), '?')); // Creates ?,?,?...
    $types = str_repeat('i', count($student_ids)); // Creates iii...
    
    $sql_scores = "SELECT student_id, score 
                   FROM grades 
                   WHERE subject_id = ? AND test_name = ? AND student_id IN ($placeholders)";
                   
    $stmt_scores = $conn->prepare($sql_scores);
    if ($stmt_scores) {
        // Bind subject_id, test_name, then all student IDs
        $stmt_scores->bind_param("is" . $types, $subject_id, $test_name, ...$student_ids);
        $stmt_scores->execute();
        $result_scores = $stmt_scores->get_result();
        while ($row = $result_scores->fetch_assoc()) {
            $existing_scores[$row['student_id']] = $row['score'];
        }
        $stmt_scores->close();
    } else {
        error_log("Error preparing scores fetch query in ajax_get_students_for_grades: " . $conn->error);
        // Don't necessarily exit, just proceed without existing grades
    }
}

// --- Output the HTML --- 

if (empty($students)) {
    echo '<div class="alert alert-info">No active students found enrolled in this class.</div>';
    exit();
}
?>

<form method="POST" action="manage_grades.php">
    <input type="hidden" name="class_id_hidden" value="<?php echo htmlspecialchars($class_id); ?>">
    <input type="hidden" name="subject_id_hidden" value="<?php echo htmlspecialchars($subject_id); ?>">
    <input type="hidden" name="test_name" value="<?php echo htmlspecialchars($test_name); // Keep test name consistent ?>">

    <div class="card">
        <div class="card-header">
            Enter Scores for Test/Assignment: <strong><?php echo htmlspecialchars($test_name); ?></strong>
        </div>
        <div class="card-body">
            <p class="text-muted small">Enter the marks obtained. Leave blank to clear an existing grade. Non-numeric values will be ignored.</p>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Student Name</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): 
                            $current_score = $existing_scores[$student['id']] ?? ''; 
                            // Format marks if they are numeric (e.g., remove trailing .00 if integer)
                            $display_marks = '';
                            if ($current_score !== null && is_numeric($current_score)) { 
                                $display_marks = number_format((float)$current_score, 2); 
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['username']); ?></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td>
                                <input type="text" 
                                       class="form-control form-control-sm grade-input" 
                                       name="scores[<?php echo $student['id']; ?>]" 
                                       value="<?php echo htmlspecialchars($display_marks); ?>" 
                                       placeholder="e.g., 85 or 7.5">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="submit" name="save_grades" class="btn btn-success">Save Scores</button>
        </div>
    </div>
</form>
