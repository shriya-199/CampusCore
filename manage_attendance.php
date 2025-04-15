<?php
session_start();
require_once 'config.php';

// Check if user is admin or teacher
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher')) {
    header("Location: index.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id']; // Assuming user_id is stored in session

$success_message = null;
$error_message = null;

// --- Handle POST request for marking/updating attendance ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_attendance'])) {
    $posted_class_id = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
    $posted_subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
    $posted_date = $_POST['attendance_date'] ?? null; // Basic check, further validation below
    $attendance_data = $_POST['attendance'] ?? [];

    // Validate date format again on POST
    $valid_date = false;
    if ($posted_date && DateTime::createFromFormat('Y-m-d', $posted_date)) {
        $valid_date = true;
    }

    if ($posted_class_id && $posted_subject_id && $valid_date && !empty($attendance_data)) {
        $conn->begin_transaction();
        try {
            // Prepare the statement for inserting/updating attendance
            // Assumes attendance table has a UNIQUE KEY on (student_id, class_id, subject_id, date)
            $sql_upsert = "INSERT INTO attendance (student_id, class_id, subject_id, date, status, marked_by) 
                           VALUES (?, ?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)";
            $stmt_upsert = $conn->prepare($sql_upsert);

            if (!$stmt_upsert) {
                throw new Exception("Prepare statement failed: " . $conn->error);
            }

            $allowed_statuses = ['present', 'absent', 'late'];
            $current_marked_by = $user_id;

            foreach ($attendance_data as $student_id => $status) {
                $student_id_int = filter_var($student_id, FILTER_VALIDATE_INT);
                // Sanitize status
                if ($student_id_int && in_array($status, $allowed_statuses)) {
                    $stmt_upsert->bind_param("iiissi", $student_id_int, $posted_class_id, $posted_subject_id, $posted_date, $status, $current_marked_by);
                    if (!$stmt_upsert->execute()) {
                         throw new Exception("Execute failed for student ID {$student_id_int}: " . $stmt_upsert->error);
                    }
                } else {
                     // Optional: Log or report invalid data
                     // echo "Skipping invalid data for student ID: $student_id";
                }
            }

            $stmt_upsert->close();
            $conn->commit();
            $_SESSION['success_message'] = "Attendance successfully recorded for " . $posted_date . ".";

            // Redirect to prevent form re-submission (PRG)
            // Append GET parameters to reload the same view
            header("Location: manage_attendance.php?class_id={$posted_class_id}&subject_id={$posted_subject_id}&attendance_date={$posted_date}");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error recording attendance: " . $e->getMessage();
            // Keep the POSTed values to repopulate the form (optional, might be complex)
        }
    } else {
        $error_message = "Invalid form submission. Please check all fields.";
        if (!$valid_date) $error_message .= " Invalid date format.";
    }
}

// --- Retrieve messages from session after redirect ---
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// --- Fetch Classes based on role (for the GET request part) ---
$classes = [];
if ($user_role === 'admin') {
    // Admin sees all active classes
    $sql_classes = "SELECT id, name, grade_level, section FROM classes WHERE status = 'active' ORDER BY grade_level, name";
    $result_classes = $conn->query($sql_classes);
    if ($result_classes) {
        while ($row = $result_classes->fetch_assoc()) {
            $classes[] = $row;
        }
    }
} elseif ($user_role === 'teacher') {
    // Teacher sees classes where they teach at least one subject
    $sql_classes = "SELECT DISTINCT c.id, c.name, c.grade_level, c.section 
                    FROM classes c
                    JOIN subjects s ON c.id = s.class_id
                    JOIN teacher_subject ts ON s.id = ts.subject_id
                    WHERE ts.teacher_id = ? AND c.status = 'active' AND s.status = 'active'
                    ORDER BY c.grade_level, c.name";
    $stmt_classes = $conn->prepare($sql_classes);
    if ($stmt_classes) {
        $stmt_classes->bind_param("i", $user_id);
        $stmt_classes->execute();
        $result_classes = $stmt_classes->get_result();
        while ($row = $result_classes->fetch_assoc()) {
            $classes[] = $row;
        }
        $stmt_classes->close();
    } else {
        // Handle prepare error if necessary
        $error_message = "Error preparing classes query: " . $conn->error;
    }
}

// Variables for the selected criteria and fetched data
$selected_class_id = null;
$selected_subject_id = null;
$selected_date = null;
$students = [];
$existing_attendance = [];
$show_attendance_form = false;

// Check if the form to load students was submitted
if (isset($_GET['class_id']) && isset($_GET['subject_id']) && isset($_GET['attendance_date'])) {
    $selected_class_id = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
    $selected_subject_id = filter_input(INPUT_GET, 'subject_id', FILTER_VALIDATE_INT);
    // Basic date validation (can be enhanced)
    $date_input = $_GET['attendance_date'];
    if (DateTime::createFromFormat('Y-m-d', $date_input) !== false) {
        $selected_date = $date_input;
    }

    if ($selected_class_id && $selected_subject_id && $selected_date) {
        $show_attendance_form = true;

        // 1. Fetch students enrolled in the selected class
        $sql_students = "SELECT u.id, u.full_name, sc.roll_number 
                         FROM users u
                         JOIN student_class sc ON u.id = sc.student_id 
                         WHERE sc.class_id = ? AND u.status = 'active' AND sc.status = 'active'
                         ORDER BY sc.roll_number, u.full_name";
        $stmt_students = $conn->prepare($sql_students);
        if ($stmt_students) {
            $stmt_students->bind_param("i", $selected_class_id);
            $stmt_students->execute();
            $result_students = $stmt_students->get_result();
            while ($row = $result_students->fetch_assoc()) {
                $students[] = $row;
            }
            $stmt_students->close();
        } else {
            $error_message = "Error fetching students: " . $conn->error;
            $show_attendance_form = false; // Don't show form if student fetch fails
        }

        // 2. Fetch existing attendance records for this class, subject, date
        if ($show_attendance_form) {
            $sql_attendance = "SELECT student_id, status 
                               FROM attendance 
                               WHERE class_id = ? AND subject_id = ? AND date = ?";
            $stmt_attendance = $conn->prepare($sql_attendance);
            if ($stmt_attendance) {
                $stmt_attendance->bind_param("iis", $selected_class_id, $selected_subject_id, $selected_date);
                $stmt_attendance->execute();
                $result_attendance = $stmt_attendance->get_result();
                while ($row = $result_attendance->fetch_assoc()) {
                    $existing_attendance[$row['student_id']] = $row['status']; // Key by student_id for easy lookup
                }
                $stmt_attendance->close();
            } else {
                $error_message = "Error fetching existing attendance: " . $conn->error;
                // Decide if you still want to show the form or not
            }
        }
    } else {
        if (!$selected_date) {
            $error_message = "Invalid date format selected.";
        } else {
            $error_message = "Invalid selection criteria.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendance - Academic Progress Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Academic Progress Tracker</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <?php if ($user_role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">Manage Users</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_attendance.php">Manage Attendance</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="manage_grades.php">Manage Grades</a>
                    </li>
                    <!-- Add other relevant nav items based on role -->
                </ul>
                <div class="navbar-nav">
                    <span class="navbar-text me-3">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</span>
                    <a class="nav-link" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Manage Attendance</h2>
        <hr>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Attendance Selection Form -->
        <form method="GET" action="manage_attendance.php" class="row g-3 mb-4 align-items-end">
            <div class="col-md-3">
                <label for="class_id" class="form-label">Select Class</label>
                <select class="form-select" id="class_id" name="class_id" required>
                    <option value="" disabled selected>-- Select Class --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>"
                                <?php echo (isset($_GET['class_id']) && $_GET['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['name']) . " (" . htmlspecialchars($class['section']) . ")"; ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (empty($classes)): ?>
                        <option disabled>No classes available or assigned.</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="subject_id" class="form-label">Select Subject</label>
                <select class="form-select" id="subject_id" name="subject_id" required>
                    <option value="" disabled selected>-- Select Class First --</option>
                    <!-- TODO: Populate subjects dynamically based on class selection -->
                    <?php 
                    // If reloading after POST error or successful GET, keep subject selected
                    if (isset($_GET['subject_id'])) {
                        // Subject options are loaded by JS, but we can hint the selected value
                        // The JS already handles selecting this if the option exists when loaded
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="attendance_date" class="form-label">Date</label>
                <input type="date" class="form-control" id="attendance_date" name="attendance_date" value="<?php echo htmlspecialchars($_GET['attendance_date'] ?? date('Y-m-d')); ?>" required>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-info w-100">Load Students</button>
            </div>
        </form>

        <!-- Student List for Attendance Marking -->
        <?php if ($show_attendance_form): ?>
            <?php if (!empty($students)): ?>
                <form method="POST" action="manage_attendance.php">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                    <input type="hidden" name="subject_id" value="<?php echo $selected_subject_id; ?>">
                    <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Roll Number</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $count = 1; foreach ($students as $student): ?>
                                <?php $student_id = $student['id']; ?>
                                <?php $current_status = $existing_attendance[$student_id] ?? 'present'; // Default to 'present' ?>
                                <tr>
                                    <td><?php echo $count++; ?></td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['roll_number'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="attendance[<?php echo $student_id; ?>]" id="present_<?php echo $student_id; ?>" value="present" <?php echo ($current_status === 'present') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="present_<?php echo $student_id; ?>">Present</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="attendance[<?php echo $student_id; ?>]" id="absent_<?php echo $student_id; ?>" value="absent" <?php echo ($current_status === 'absent') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="absent_<?php echo $student_id; ?>">Absent</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="attendance[<?php echo $student_id; ?>]" id="late_<?php echo $student_id; ?>" value="late" <?php echo ($current_status === 'late') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="late_<?php echo $student_id; ?>">Late</label>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" name="mark_attendance" class="btn btn-primary">Submit Attendance</button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">No students found enrolled in the selected class.</div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-muted">Please select a class, subject, and date to load students for attendance.</p>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fetch subjects for the selected class using AJAX
        document.getElementById('class_id').addEventListener('change', function() {
            const classId = this.value;
            const subjectSelect = document.getElementById('subject_id');
            subjectSelect.innerHTML = '<option value="" disabled selected>Loading...</option>'; // Indicate loading

            if (!classId) {
                subjectSelect.innerHTML = '<option value="" disabled selected>-- Select Class First --</option>';
                return;
            }
            
            fetch('get_subjects.php?class_id=' + classId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    subjectSelect.innerHTML = '<option value="" disabled selected>-- Select Subject --</option>';
                    if (data.error) {
                        console.error('Error from server:', data.error);
                        subjectSelect.innerHTML += '<option disabled>Error loading subjects</option>';
                    } else if (data.length === 0) {
                        subjectSelect.innerHTML += '<option disabled>No subjects found/assigned for this class</option>';
                    } else {
                        data.forEach(subject => {
                            // Check if this subject was previously selected (e.g., on page reload)
                            const isSelected = (subjectSelect.dataset.selectedValue == subject.id) ? 'selected' : '';
                            subjectSelect.innerHTML += `<option value="${subject.id}" ${isSelected}>${escapeHtml(subject.name)}</option>`;
                        });
                    }
                    // Clear the saved selected value after attempting to restore it
                    delete subjectSelect.dataset.selectedValue;
                })
                .catch(error => {
                    console.error('Error fetching subjects:', error);
                    subjectSelect.innerHTML = '<option value="" disabled selected>Error loading subjects</option>';
                });
        });

        // Function to escape HTML special characters
        function escapeHtml(unsafe) {
            return unsafe
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;");
        }

        // Trigger change event on page load if class_id is already selected (e.g., from GET request)
        window.addEventListener('DOMContentLoaded', (event) => {
            const classSelect = document.getElementById('class_id');
            const subjectSelect = document.getElementById('subject_id');
            
            // Store the pre-selected subject ID if it exists (from GET)
            const urlParams = new URLSearchParams(window.location.search);
            const preselectedSubjectId = urlParams.get('subject_id');
            if (preselectedSubjectId) {
                subjectSelect.dataset.selectedValue = preselectedSubjectId;
            }

            if (classSelect.value) {
                classSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>
