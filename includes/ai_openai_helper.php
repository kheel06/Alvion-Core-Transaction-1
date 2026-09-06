<?php
/**
 * OpenAI-compatible API helper for AI features.
 * Used by triage suggestion and telehealth summary when API key is set.
 */

if (!function_exists('aiChatCompletion')) {
    /**
     * Call OpenAI-compatible chat completion API.
     *
     * @param array $config From config/ai.php
     * @param string $systemPrompt
     * @param string $userPrompt
     * @return array { success: bool, message?: string, content?: string }
     */
    function aiChatCompletion(array $config, $systemPrompt, $userPrompt) {
        $apiKey = $config['api_key'] ?? '';
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'AI API key not configured.'];
        }

        $url = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/') . '/chat/completions';
        $model = $config['model'] ?? 'gpt-4o-mini';
        $timeout = $config['timeout'] ?? 15;
        $maxTokens = $config['max_tokens'] ?? 500;

        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ]
        ];

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' =>
                    "Content-Type: application/json\r\n" .
                    "Authorization: Bearer " . $apiKey . "\r\n",
                'content' => json_encode($body),
                'timeout' => (float)$timeout
            ]
        ]);

        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            return ['success' => false, 'message' => 'AI service unavailable or timeout.'];
        }

        $data = json_decode($response, true);
        if (isset($data['error'])) {
            return ['success' => false, 'message' => $data['error']['message'] ?? 'API error.'];
        }
        $content = $data['choices'][0]['message']['content'] ?? '';
        return ['success' => true, 'content' => trim($content)];
    }
}
