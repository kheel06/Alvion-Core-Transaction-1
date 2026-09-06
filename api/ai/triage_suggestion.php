<?php
/**
 * AI-Powered Triage Suggestion API
 * POST: chief_complaint, [blood_pressure, heart_rate, respiratory_rate, temperature, oxygen_saturation, pain_level]
 * Returns: { success, suggested_level, reasoning, source: "ai"|"rule_based" }
 */
header('Content-Type: application/json');
require_once '../../config/config.php';
require_once '../../includes/ai_triage_helper.php';
require_once '../../includes/ai_openai_helper.php';

requireAuth();
checkRole(['admin', 'nurse', 'doctor']);

$config = file_exists(__DIR__ . '/../../config/ai.php') ? require __DIR__ . '/../../config/ai.php' : ['api_key' => '', 'enabled' => true];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$chiefComplaint = trim((string)($input['chief_complaint'] ?? ''));
$vitals = [
    'blood_pressure'      => $input['blood_pressure'] ?? '',
    'heart_rate'          => $input['heart_rate'] ?? '',
    'respiratory_rate'    => $input['respiratory_rate'] ?? '',
    'temperature'         => $input['temperature'] ?? '',
    'oxygen_saturation'   => $input['oxygen_saturation'] ?? '',
    'pain_level'          => $input['pain_level'] ?? ''
];

if ($chiefComplaint === '') {
    echo json_encode(['success' => false, 'message' => 'Chief complaint is required']);
    exit;
}

$levels = ['resuscitation', 'emergency', 'urgent', 'semi_urgent', 'non_urgent'];

if (!empty($config['api_key']) && ($config['enabled'] ?? true)) {
    $vitalsStr = "BP: " . ($vitals['blood_pressure'] ?: 'not recorded') .
        ", HR: " . ($vitals['heart_rate'] ?: 'N/R') .
        ", RR: " . ($vitals['respiratory_rate'] ?: 'N/R') .
        ", Temp: " . ($vitals['temperature'] ?: 'N/R') . "°C" .
        ", O2: " . ($vitals['oxygen_saturation'] ?: 'N/R') . "%" .
        ", Pain: " . ($vitals['pain_level'] !== '' ? $vitals['pain_level'] : 'N/R') . "/10";
    $systemPrompt = "You are an ER triage assistant. Based on chief complaint and vital signs, suggest ONE triage level: resuscitation, emergency, urgent, semi_urgent, or non_urgent. Reply with exactly two lines: Line 1 = only the level (one of those 5 words). Line 2 = brief clinical reasoning (one or two sentences). No other text.";
    $userPrompt = "Chief complaint: " . $chiefComplaint . "\nVital signs: " . $vitalsStr;

    $result = aiChatCompletion($config, $systemPrompt, $userPrompt);
    if ($result['success'] && !empty($result['content'])) {
        $lines = preg_split('/\r\n|\r|\n/', $result['content'], 2);
        $level = strtolower(trim($lines[0]));
        $reasoning = isset($lines[1]) ? trim($lines[1]) : $result['content'];
        if (!in_array($level, $levels, true)) {
            $level = 'semi_urgent';
        }
        echo json_encode([
            'success' => true,
            'suggested_level' => $level,
            'reasoning' => $reasoning,
            'source' => 'ai'
        ]);
        exit;
    }
}

// Rule-based fallback
$suggestion = getAITriageSuggestion($chiefComplaint, $vitals);
echo json_encode([
    'success' => true,
    'suggested_level' => $suggestion['suggested_level'],
    'reasoning' => $suggestion['reasoning'],
    'source' => 'rule_based'
]);
