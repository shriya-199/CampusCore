<?php
require_once 'config.php';

if (!isset($_GET['class_id']) || !isset($_GET['date'])) {
    exit('Missing parameters');
}

$class_id = (int)$_GET['class_id'];
$date = $_GET['date'];

// Get attendance records for the class and date
$sql = "SELECT u.full_name, a.present 
        FROM users u 
        LEFT JOIN student_class sc ON u.id = sc.student_id 
        LEFT JOIN attendance a ON u.id = a.student_id AND a.date = ? AND a.class_id = ?
        WHERE sc.class_id = ? AND u.role = 'student'
        ORDER BY u.full_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $date, $class_id, $class_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '<div class="alert alert-info">No records found for this date.</div>';
    exit();
}

// Calculate statistics
$total = $present = 0;
$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
    $total++;
    if ($row['present']) $present++;
}
$attendance_rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;
?>

<div class="mb-3">
    <h6>Attendance Summary</h6>
    <p>Total Students: <?php echo $total; ?><br>
       Present: <?php echo $present; ?><br>
       Absent: <?php echo $total - $present; ?><br>
       Attendance Rate: <?php echo $attendance_rate; ?>%</p>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $record): ?>
            <tr>
                <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                <td>
                    <?php if ($record['present'] === null): ?>
                        <span class="badge bg-warning">Not Marked</span>
                    <?php elseif ($record['present']): ?>
                        <span class="badge bg-success">Present</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Absent</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
