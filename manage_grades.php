<?php
session_start();
require_once 'config.php';

// Role check: Allow Admin and Teacher
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    $_SESSION['error_message'] = "Access denied. You must be an admin or teacher to manage grades.";
    header("Location: index.php");
    exit();
}

$logged_in_user_id = $_SESSION['user_id'];
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch active classes
$classes = [];
$sql_classes = "SELECT id, name FROM classes WHERE status = 'active' ORDER BY name";
$result_classes = $conn->query($sql_classes);
if ($result_classes) {
    while ($row = $result_classes->fetch_assoc()) {
        $classes[] = $row;
    }
    $result_classes->free();
} else {
    $error_message = "Error fetching classes: " . $conn->error;
}

// --- Handle Grade Submission --- 
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_grades'])) {
    $class_id = filter_input(INPUT_POST, 'class_id_hidden', FILTER_VALIDATE_INT);
    $subject_id = filter_input(INPUT_POST, 'subject_id_hidden', FILTER_VALIDATE_INT);
    $test_name = trim($_POST['test_name'] ?? ''); 
    $scores = $_POST['scores'] ?? []; // Changed from grades
    $logged_in_user_id = $_SESSION['user_id'];

    if (!$class_id || !$subject_id || empty($test_name) || empty($scores)) {
        $_SESSION['error_message'] = "Missing required data to save grades.";
    } else {
        $conn->begin_transaction();
        $all_successful = true;

        try {
            // Use test_name, score, marked_by, and also include class_id and test_date (use current date for now)
            // Assuming max_score is 100 by default as per schema
            $sql_upsert = "INSERT INTO grades (student_id, subject_id, class_id, test_name, score, test_date, marked_by) 
                       VALUES (?, ?, ?, ?, ?, CURDATE(), ?) 
                       ON DUPLICATE KEY UPDATE score = VALUES(score), marked_by = VALUES(marked_by), updated_at = NOW()";
            
            $stmt_upsert = $conn->prepare($sql_upsert);
            if (!$stmt_upsert) {
                 throw new Exception("Error preparing grade update statement: " . $conn->error);
            }

            foreach ($scores as $student_id => $score_input) {
                $student_id = filter_var($student_id, FILTER_VALIDATE_INT);
                $score_input = trim($score_input);

                if ($student_id === false) continue; // Skip invalid student ID

                $score_to_save = null; // Default to null (though schema says NOT NULL, how to handle clearing? Needs discussion/adjustment)
                                       // For now, let's skip empty values to avoid error, but ideally, allow clearing.
                if ($score_input !== '' && is_numeric($score_input)) {
                    $score_to_save = (float)$score_input;
                } elseif ($score_input !== '') {
                    // Invalid non-empty, non-numeric value - skip or throw error?
                    // For now, we skip to avoid saving invalid data, but log/notify?
                    error_log("Invalid score input skipped for student ID $student_id: $score_input");
                    continue;
                }

                // Skip saving if score is empty/invalid, as the column is NOT NULL
                if ($score_to_save === null) {
                    // Potential future: Add DELETE logic here if score is empty and record exists
                    continue; 
                }
                
                // Parameters: student_id, subject_id, class_id, test_name, score, marked_by
                $stmt_upsert->bind_param("iiisdi", $student_id, $subject_id, $class_id, $test_name, $score_to_save, $logged_in_user_id);
                 
                if (!$stmt_upsert->execute()) {
                    error_log("Error saving grade for student ID $student_id: " . $stmt_upsert->error);
                    $all_successful = false; // Mark as failed but continue trying others
                }
            }
            $stmt_upsert->close();

            if ($all_successful) {
                $conn->commit();
                $_SESSION['success_message'] = "Grades saved successfully for Subject ID $subject_id.";
            } else {
                $conn->rollback(); // Rollback if any single grade failed
                $_SESSION['error_message'] = "An error occurred while saving some grades. Please check and try again.";
            }

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Grade Save Error: " . $e->getMessage());
            $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
        }

        // Redirect back to the page, potentially with the selections pre-filled?
        // For simplicity, just redirecting to the base page.
        header("Location: manage_grades.php"); 
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grades</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css"> 
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="page-header">
            <h1 class="mb-3">Manage Student Scores</h1>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">Select Class and Subject</div>
            <div class="card-body">
                <form id="select-class-subject-form">
                     <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="class_id" class="form-label">Class *</label>
                            <select class="form-select" id="class_id" name="class_id" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if (empty($classes)): ?>
                                    <option disabled>No active classes found</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="subject_id" class="form-label">Subject *</label>
                            <select class="form-select" id="subject_id" name="subject_id" required disabled>
                                <option value="">-- Select Class First --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                             <label for="test_name_select" class="form-label">Assessment Type *</label>
                            <select class="form-select" id="test_name_select" name="test_name" required>
                                <option value="" selected disabled>Select Type</option>
                                <option value="Midterm">Midterm</option>
                                <option value="End Term">End Term</option>
                                <option value="CA">CA (Continuous Assessment)</option>
                                <!-- Add other types if needed -->
                            </select>
                        </div>
                        <div class="col-md-12">
                            <button type="button" id="load-students-btn" class="btn btn-primary mt-3">Load Students & Scores</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div id="student-scores-list">
            <!-- Student list and score inputs will be loaded here via AJAX -->
             <div class="text-center text-muted">Please select a class and subject, then click 'Load Students & Scores'.</div>
        </div>

    </div> 

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // 1. Load Subjects on Class Change
            $('#class_id').change(function() {
                const classId = $(this).val();
                const subjectSelect = $('#subject_id');
                subjectSelect.prop('disabled', true).html('<option value="">Loading...</option>');
                $('#student-scores-list').html('<div class="text-center text-muted">Please select a subject and click \'Load Students & Scores\'.</div>'); // Reset list

                if (classId) {
                    $.ajax({
                        url: 'get_subjects.php',
                        type: 'GET',
                        data: { class_id: classId },
                        dataType: 'json',
                        success: function(subjects) {
                            subjectSelect.prop('disabled', false);
                            subjectSelect.html('<option value="">-- Select Subject --</option>');
                            if (subjects.length > 0) {
                                subjects.forEach(function(subject) {
                                    subjectSelect.append(`<option value="${subject.id}">${subject.name}</option>`);
                                });
                            } else {
                                subjectSelect.html('<option value="">-- No subjects found --</option>');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("Error fetching subjects:", status, error, xhr.responseText);
                            subjectSelect.prop('disabled', true).html('<option value="">-- Error Loading --</option>');
                            alert('Error loading subjects. Please check console.');
                        }
                    });
                } else {
                    subjectSelect.prop('disabled', true).html('<option value="">-- Select Class First --</option>');
                }
            });

             // Reset student list if subject changes or assessment type changes
            $('#subject_id, #test_name_select').change(function() {
                 $('#student-scores-list').html('<div class="text-center text-muted">Please click \'Load Students & Scores\'.</div>');
            });

            // 2. Load Students and Scores on Button Click
            $('#load-students-btn').click(function() {
                const classId = $('#class_id').val();
                const subjectId = $('#subject_id').val();
                const testName = $('#test_name_select').val(); // Read from select
                const studentListDiv = $('#student-scores-list');

                if (!classId || !subjectId || !testName) {
                    alert('Please select a Class, Subject, and Assessment Type.');
                    return;
                }

                studentListDiv.html('<div class="text-center">Loading students... <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div></div>');

                $.ajax({
                    url: 'ajax_get_students_for_grades.php', // Corrected filename
                    type: 'GET',
                    data: { 
                        class_id: classId,
                        subject_id: subjectId,
                        test_name: testName
                    },
                    dataType: 'html', // Expecting HTML response containing the form
                    success: function(response) {
                        studentListDiv.html(response);
                    },
                    error: function(xhr, status, error) {
                        console.error("Error fetching students/scores:", status, error, xhr.responseText);
                        studentListDiv.html('<div class="alert alert-danger">Error loading student data. Please try again. Check console for details.</div>');
                    }
                });
            });

        });
    </script>
</body>
</html>
