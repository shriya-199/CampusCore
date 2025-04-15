<?php
require_once 'config.php';
require_teacher();

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

$start_date = $month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

$where_clause = $_SESSION['role'] === 'admin' ? '1' : 'c.teacher_id = ' . $_SESSION['user_id'];
if ($class_id) {
    $where_clause .= ' AND c.id = ' . $class_id;
}

$sql = "SELECT 
            a.date,
            c.name as class_name,
            c.grade_level,
            c.section,
            GROUP_CONCAT(
                CONCAT(u.full_name, ': ', a.status)
                ORDER BY u.full_name
                SEPARATOR '\n'
            ) as attendance_details,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
            COUNT(*) as total_students
        FROM attendance a
        JOIN classes c ON a.class_id = c.id
        JOIN users u ON a.student_id = u.id
        WHERE $where_clause
            AND a.date BETWEEN ? AND ?
        GROUP BY a.date, c.id
        ORDER BY a.date DESC, c.name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '<div class="alert alert-info">No attendance records found for the selected period.</div>';
    exit();
}
?>

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Class</th>
                <th>Present</th>
                <th>Absent</th>
                <th>Late</th>
                <th>Percentage</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                <td><?php echo htmlspecialchars($row['class_name'] . ' - Grade ' . $row['grade_level'] . '-' . $row['section']); ?></td>
                <td class="text-success"><?php echo $row['present_count']; ?></td>
                <td class="text-danger"><?php echo $row['absent_count']; ?></td>
                <td class="text-warning"><?php echo $row['late_count']; ?></td>
                <td>
                    <?php 
                    $attendance_rate = ($row['present_count'] + ($row['late_count'] * 0.5)) / $row['total_students'] * 100;
                    $color_class = $attendance_rate >= 90 ? 'success' : ($attendance_rate >= 75 ? 'warning' : 'danger');
                    ?>
                    <div class="progress">
                        <div class="progress-bar bg-<?php echo $color_class; ?>" 
                             role="progressbar" 
                             style="width: <?php echo $attendance_rate; ?>%"
                             aria-valuenow="<?php echo $attendance_rate; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <?php echo number_format($attendance_rate, 1); ?>%
                        </div>
                    </div>
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-info" 
                            data-bs-toggle="popover" 
                            data-bs-trigger="click"
                            data-bs-placement="left"
                            data-bs-html="true"
                            title="Attendance Details"
                            data-bs-content="<?php echo nl2br(htmlspecialchars($row['attendance_details'])); ?>">
                        <i class="bi bi-info-circle"></i>
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function(popover) {
    new bootstrap.Popover(popover);
});
</script>
