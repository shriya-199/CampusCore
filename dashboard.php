<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('index.php'); // Redirect to login if not logged in
}

$user_role = $_SESSION['role'] ?? null;
$user_fullname = $_SESSION['full_name'] ?? 'User';

// Include Header
$page_title = 'Dashboard';
include_once 'includes/header.php';

// Include Navbar
include_once 'includes/navbar.php';
?>

<div class="container mt-5 pt-4"> 
    <div class="pt-5">
        <h1>Welcome, <?php echo htmlspecialchars($user_fullname); ?>!</h1>
        <p class="lead">Select an option below or view your child's progress.</p>
        <hr>
        <?php display_flash_message(); // Display flash messages from redirects ?>

        <?php // ----- Parent Specific Section ----- ?>
        <?php if ($user_role === 'parent'): ?>
            <?php
            // Fetch parent's children (wards)
            $parent_id = $_SESSION['user_id'];
            $wards = [];
            // Assuming $conn is available from config.php
            $sql_wards = "SELECT u.id, u.full_name
                          FROM users u
                          JOIN parent_ward pw ON u.id = pw.student_id
                          WHERE pw.parent_id = ? AND u.role = 'student'
                          ORDER BY u.full_name";
            if ($stmt_wards = $conn->prepare($sql_wards)) {
                $stmt_wards->bind_param("i", $parent_id);
                $stmt_wards->execute();
                $result_wards = $stmt_wards->get_result();
                while ($row = $result_wards->fetch_assoc()) {
                    $wards[] = $row;
                }
                $stmt_wards->close();
            } else {
                echo "<div class='alert alert-danger'>Error fetching child information: " . $conn->error . "</div>";
                error_log("Error preparing wards statement dashboard: " . $conn->error);
            }
            ?>
            <div class="card mb-4 bg-light">
                <div class="card-header bg-secondary text-white">
                    <h4 class="mb-0"><i class="bi bi-people-fill me-2"></i>My Children</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($wards)): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($wards as $ward): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap bg-light">
                                    <span class="fw-bold me-3 fs-5"><?php echo htmlspecialchars($ward['full_name']); ?></span>
                                    <div class="mt-2 mt-md-0">
                                        <a href="view_attendance.php?student_id=<?php echo $ward['id']; ?>" class="btn btn-sm btn-outline-primary me-1 mb-1" title="View Attendance">
                                            <i class="bi bi-calendar-check"></i> Attendance
                                        </a>
                                        <a href="view_grades.php?student_id=<?php echo $ward['id']; ?>" class="btn btn-sm btn-outline-info me-1 mb-1" title="View Grades">
                                            <i class="bi bi-clipboard-data"></i> Grades
                                        </a>
                                        <a href="view_activities.php?student_id=<?php echo $ward['id']; ?>" class="btn btn-sm btn-outline-secondary mb-1" title="View Activities">
                                            <i class="bi bi-list-ul"></i> Activities
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted mb-0">No children assigned to your account. Please contact the administrator if this is incorrect.</p>
                    <?php endif; ?>
                </div>
            </div>
            <hr class="my-4">
            <h4 class="mb-3">System Management Options (If Applicable)</h4>
        <?php endif; ?> <?php // End parent section ?>

        <?php // ----- Other Role Cards (Admin, Teacher, Student) ----- ?>
        <div class="row gy-4"> 

            <?php // ----- Admin Specific Cards ----- ?>
            <?php if ($user_role === 'admin'): ?>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-users-cog me-2"></i>Manage Users</h5>
                            <p class="card-text">Add, edit, or remove user accounts (admins, teachers, students, parents).</p>
                            <a href="manage_users.php" class="btn btn-primary mt-auto">Go to Manage Users</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php // ----- Admin & Teacher Cards ----- ?>
            <?php if (in_array($user_role, ['admin', 'teacher'])): ?>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-user-check me-2"></i>Manage Attendance</h5>
                            <p class="card-text">Record student attendance for classes and subjects.</p>
                            <a href="manage_attendance.php" class="btn btn-success mt-auto">Go to Manage Attendance</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-graduation-cap me-2"></i>Manage Grades</h5>
                            <p class="card-text">Enter and update student grades for subjects.</p>
                            <a href="manage_grades.php" class="btn btn-success mt-auto">Go to Manage Grades</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-tasks me-2"></i>Manage Activities</h5>
                            <p class="card-text">Add and track academic or extracurricular activities.</p>
                            <a href="manage_activities.php" class="btn btn-success mt-auto">Go to Manage Activities</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php // ----- Student Cards ----- ?>
            <?php if ($user_role === 'student'): ?>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-calendar-check me-2"></i>View Attendance</h5>
                            <p class="card-text">Check your attendance records.</p>
                            <a href="view_attendance.php" class="btn btn-info mt-auto">Go to View Attendance</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-clipboard-list me-2"></i>View Grades</h5>
                            <p class="card-text">Check your grades and academic performance.</p>
                            <a href="view_grades.php" class="btn btn-info mt-auto">Go to View Grades</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><i class="fas fa-tasks me-2"></i>View Activities</h5>
                            <p class="card-text">View assigned activities and deadlines.</p>
                            <a href="view_activities.php" class="btn btn-info mt-auto">Go to View Activities</a> <?php // TODO: Create view_activities.php ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div> 

    </div>
</div>


<?php
// Include Footer
include_once 'includes/footer.php';
?>
