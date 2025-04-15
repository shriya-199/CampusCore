<?php
// includes/header.php

// Start session if not already started (needed for session vars in header/nav)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php'; // Use absolute path based on current file location

// Default page title if not set before including header
$current_page_title = $page_title ?? 'Campus Core';

?>
<!DOCTYPE html>
<html lang="en" class="h-100"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($current_page_title) . ' - ' . SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <!-- Font Awesome CSS (For icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Custom CSS (Optional) -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css"> <?php // Adjust path if needed. Make sure /css/style.css exists. ?>

    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh; /* Ensure body takes full viewport height */
        }
        .content-wrap {
            flex: 1; /* Allows main content area to grow */
            padding-top: 56px; /* Adjust based on fixed navbar height */
        }
        .navbar {
             z-index: 1030; /* Ensure navbar stays on top */
        }
        .container.mt-5.pt-4 { /* Ensure content below fixed navbar is visible */
            padding-top: 3rem !important; 
        }
        .card {
             margin-bottom: 1.5rem; /* Add some space between cards */
        }
        /* Add more custom styles here */
    </style>

</head>
<body class="d-flex flex-column h-100">

    <?php // Navbar will be included in the specific page files after the header ?>

    <!-- Start of main page content wrapper -->
    <main class="flex-shrink-0 content-wrap">

        <?php // Display flash messages if available
        // You might have a function like display_flash_message() here or in functions.php
        // Example:
        // if (function_exists('display_flash_message')) { display_flash_message(); } 
        ?>
