<?php
/**
 * AI Configuration - Triage & Telehealth
 * Uses OpenAI-compatible API (OpenAI, Azure OpenAI, or local LLM with OpenAI-compatible endpoint).
 *
 * Optional: set in .env or environment:
 *   AI_API_KEY=sk-...
 *   AI_BASE_URL=https://api.openai.com/v1   (or Azure/local endpoint)
 *   AI_MODEL=gpt-4o-mini
 *   AI_TIMEOUT=15
 *   AI_MAX_TOKENS=500
 *
 * If AI_API_KEY is empty, triage and telehealth use rule-based suggestions (no external API).
 */
return [
    'enabled'       => true,
    'provider'      => 'openai', // openai | azure | local
    'api_key'       => getenv('AI_API_KEY') ?: '', // Set in .env or leave empty for rule-based only
    'base_url'      => getenv('AI_BASE_URL') ?: 'https://api.openai.com/v1', // Override for Azure/local
    'model'         => getenv('AI_MODEL') ?: 'gpt-4o-mini', // gpt-4o-mini, gpt-4o, or your model name
    'timeout'       => (int)(getenv('AI_TIMEOUT') ?: 15),
    'max_tokens'    => (int)(getenv('AI_MAX_TOKENS') ?: 500),
    // When API key is empty, triage/telehealth use rule-based suggestions (no external call).
    'use_rule_based_fallback' => true,
];
