<?php
require_once 'config.php';

// First, drop all existing tables in the correct order to avoid foreign key constraints
$drop_tables = [
    "attendance",
    "grades",
    "activities",
    "parent_student",
    "student_class",
    "subjects",
    "classes",
    "users"
];

foreach ($drop_tables as $table) {
    $conn->query("DROP TABLE IF EXISTS $table");
}

$sql = file_get_contents('database.sql');

// Split SQL into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

$success = true;
$messages = [];

foreach ($statements as $statement) {
    if (empty($statement)) continue;
    
    if ($conn->query($statement)) {
        $messages[] = "Success: " . substr($statement, 0, 50) . "...";
    } else {
        $success = false;
        $messages[] = "Error: " . $conn->error . " in statement: " . substr($statement, 0, 50) . "...";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Academic Progress Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Database Setup Results</h3>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">Database setup completed successfully!</div>
                <?php else: ?>
                    <div class="alert alert-danger">There were some errors during database setup.</div>
                <?php endif; ?>

                <h4>Execution Log:</h4>
                <div class="list-group mt-3">
                    <?php foreach ($messages as $message): ?>
                        <div class="list-group-item <?php echo strpos($message, 'Error') === 0 ? 'list-group-item-danger' : 'list-group-item-success'; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4">
                    <a href="index.php" class="btn btn-primary">Go to Login Page</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
