<?php
/**
 * Mark teleconsultation as completed when ending the video call.
 * POST JSON: consultation_id
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';

requireAuth();
checkRole(['admin', 'doctor', 'nurse']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$consultation_id = isset($input['consultation_id']) ? (int) $input['consultation_id'] : 0;

if ($consultation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid consultation id']);
    exit;
}

try {
    $stmt = $db->prepare("UPDATE teleconsultations SET status = 'completed' WHERE id = :id");
    $stmt->bindParam(':id', $consultation_id, PDO::PARAM_INT);
    $stmt->execute();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
