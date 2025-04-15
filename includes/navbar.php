<?php
// Ensure session is started on pages including this navbar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_role = $_SESSION['role'] ?? null;
$user_fullname = $_SESSION['full_name'] ?? 'Guest';
$current_page = basename($_SERVER['PHP_SELF']); // Get the current file name

?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Campus Core</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if ($user_role): // Show dashboard only if logged in ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">Dashboard</a>
                </li>
                <?php endif; ?>

                <?php if ($user_role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>" href="manage_users.php">Manage Users</a>
                    </li>
                <?php endif; ?>

                <?php if (in_array($user_role, ['admin', 'teacher'])) : ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'manage_attendance.php') ? 'active' : ''; ?>" href="manage_attendance.php">Manage Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'manage_grades.php') ? 'active' : ''; ?>" href="manage_grades.php">Manage Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'manage_activities.php') ? 'active' : ''; ?>" href="manage_activities.php">Manage Activities</a>
                    </li>
                <?php endif; ?>
                
                <?php if ($user_role === 'student'): ?>
                     <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'view_attendance.php') ? 'active' : ''; ?>" href="view_attendance.php">View Attendance</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'view_grades.php') ? 'active' : ''; ?>" href="view_grades.php">View Grades</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'view_activities.php') ? 'active' : ''; ?>" href="view_activities.php">Activities</a>
                    </li>
                <?php endif; ?>
                
                 <?php if ($user_role === 'parent'): ?>
                     <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'view_child_progress.php') ? 'active' : ''; ?>" href="view_child_progress.php">View Child Progress</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'view_activities.php') ? 'active' : ''; ?>" href="view_activities.php">Ward Activities</a>
                    </li>
                <?php endif; ?>

                <!-- Add other navigation links as needed -->

            </ul>
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                 <?php if ($user_role): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_fullname); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                     <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'login.php') ? 'active' : ''; ?>" href="login.php">Login</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div style="padding-top: 70px;"> <!-- Add padding to main content to avoid overlap with fixed navbar -->
    <!-- Page content starts here -->
</div>
