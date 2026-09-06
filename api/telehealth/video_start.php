<?php
/**
 * Mark teleconsultation as ongoing and store video link when starting a call.
 * POST JSON: consultation_id, video_link
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
$video_link = trim((string)($input['video_link'] ?? ''));

if ($consultation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid consultation id']);
    exit;
}

try {
    $stmt = $db->prepare("UPDATE teleconsultations SET status = 'ongoing', video_link = :video_link WHERE id = :id");
    $stmt->bindParam(':video_link', $video_link, PDO::PARAM_STR);
    $stmt->bindParam(':id', $consultation_id, PDO::PARAM_INT);
    $stmt->execute();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
