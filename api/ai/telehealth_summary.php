<?php
/**
 * AI Telehealth Support API
 * POST: reason, symptoms
 * Returns: { success, summary, suggested_questions, source: "ai"|"rule_based" }
 */
header('Content-Type: application/json');
require_once '../../config/config.php';

requireAuth();
checkRole(['admin', 'doctor', 'nurse']);

$config = file_exists(__DIR__ . '/../../config/ai.php') ? require __DIR__ . '/../../config/ai.php' : ['api_key' => '', 'enabled' => true];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$reason = trim((string)($input['reason'] ?? ''));
$symptoms = trim((string)($input['symptoms'] ?? ''));

if (!empty($config['api_key']) && ($config['enabled'] ?? true)) {
    require_once __DIR__ . '/../../includes/ai_openai_helper.php';
    $systemPrompt = "You are a telehealth assistant for doctors. Given the reason for consultation and symptoms, provide: (1) A brief 2-3 sentence clinical summary. (2) Three suggested follow-up questions the doctor might ask the patient. Reply in this exact format:\nSUMMARY:\n[your summary]\nQUESTIONS:\n1. [question]\n2. [question]\n3. [question]";
    $userPrompt = "Reason for consultation: " . ($reason ?: 'Not provided') . "\nSymptoms: " . ($symptoms ?: 'None listed') . "\nProvide summary and suggested questions.";

    $result = aiChatCompletion($config, $systemPrompt, $userPrompt);
    if ($result['success'] && !empty($result['content'])) {
        $content = $result['content'];
        $summary = '';
        $questions = [];
        if (preg_match('/SUMMARY:\s*(.+?)(?=QUESTIONS:|$)/is', $content, $m)) {
            $summary = trim(preg_replace('/\s+/', ' ', $m[1]));
        } else {
            $summary = trim(preg_replace('/\s+/', ' ', $content));
        }
        if (preg_match('/QUESTIONS:\s*(.+)/is', $content, $m)) {
            preg_match_all('/\d+\.\s*(.+?)(?=\d+\.|$)/s', $m[1], $q);
            if (!empty($q[1])) {
                $questions = array_map('trim', array_slice($q[1], 0, 3));
            }
        }
        if (empty($questions)) {
            $questions = [
                'How long have these symptoms been present?',
                'Has the patient tried any medications or home remedies?',
                'Any other relevant medical history or allergies?'
            ];
        }
        echo json_encode([
            'success' => true,
            'summary' => $summary,
            'suggested_questions' => $questions,
            'source' => 'ai'
        ]);
        exit;
    }
}

// Rule-based fallback
$summary = '';
if ($reason) $summary .= "Reason for consultation: " . $reason . ". ";
if ($symptoms) $summary .= "Symptoms reported: " . $symptoms . ". ";
if (!$summary) $summary = "No reason or symptoms provided yet.";
$summary .= " Consider asking about duration, severity, and any prior treatments.";

$suggested_questions = [
    'How long have you had these symptoms?',
    'Have you taken any medication or tried anything at home?',
    'Any other symptoms (e.g. fever, pain level)?'
];

echo json_encode([
    'success' => true,
    'summary' => $summary,
    'suggested_questions' => $suggested_questions,
    'source' => 'rule_based'
]);
