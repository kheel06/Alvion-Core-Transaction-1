<?php
// Simple script to refresh the session
// Accessing this file will start the session (if not started) and update the last activity time
session_start();

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Session refreshed', 'timestamp' => time()]);
?>
