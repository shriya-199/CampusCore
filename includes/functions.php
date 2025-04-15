<?php

// functions.php

if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Ensure session is started needed for is_logged_in
}

/**
 * Checks if the user is logged in based on session variable.
 *
 * @return boolean True if user_id is set in session, false otherwise.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirects the browser to a specified URL.
 *
 * @param string $url The URL to redirect to.
 * @return void
 */
function redirect($url) {
    header("Location: " . $url);
    exit(); // Important to prevent further script execution after redirect
}

/**
 * Sanitize output to prevent XSS attacks.
 *
 * @param string|null $data The data to sanitize.
 * @return string Sanitized data.
 */
function sanitize_output($data) {
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

 // Add other common functions here as needed

?>
