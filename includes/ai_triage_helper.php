<?php
/**
 * AI / Rule-based Triage Helper
 * Suggests triage level from chief complaint and vital signs.
 * Used when no AI API key is set (fallback) or as validation layer.
 */

if (!function_exists('getAITriageSuggestion')) {
    /**
     * Get suggested triage level and reasoning (rule-based).
     * Levels: resuscitation, emergency, urgent, semi_urgent, non_urgent
     *
     * @param string $chiefComplaint
     * @param array $vitals ['blood_pressure' => '120/80', 'heart_rate' => 72, 'respiratory_rate' => 16, 'temperature' => 36.6, 'oxygen_saturation' => 98, 'pain_level' => 0]
     * @return array { suggested_level: string, reasoning: string }
     */
    function getAITriageSuggestion($chiefComplaint, array $vitals = []) {
        $complaint = strtolower(trim($chiefComplaint));
        $bp = $vitals['blood_pressure'] ?? '';
        $hr = (int)($vitals['heart_rate'] ?? 0);
        $rr = (int)($vitals['respiratory_rate'] ?? 0);
        $temp = (float)($vitals['temperature'] ?? 0);
        $o2 = (int)($vitals['oxygen_saturation'] ?? 0);
        $pain = (int)($vitals['pain_level'] ?? 0);

        $reasons = [];

        // Parse systolic from BP (e.g. 120/80 or 180/110)
        $systolic = null;
        if (preg_match('/^(\d+)\s*\/\s*\d+/', trim($bp), $m)) {
            $systolic = (int)$m[1];
        } elseif (preg_match('/^(\d+)\s*\/\s*\d+/', str_replace(',', '.', $bp), $m)) {
            $systolic = (int)$m[1];
        }

        // Resuscitation / Emergency indicators (vitals)
        if ($o2 > 0 && $o2 < 90) {
            $reasons[] = "O2 saturation {$o2}% indicates hypoxia.";
        }
        if ($systolic !== null && $systolic >= 200) {
            $reasons[] = "Severe hypertension (systolic {$systolic} mmHg).";
        }
        if ($hr > 0 && ($hr > 140 || $hr < 40)) {
            $reasons[] = "Heart rate {$hr} outside normal range.";
        }
        if ($rr > 0 && $rr > 30) {
            $reasons[] = "Respiratory rate {$rr}/min suggests respiratory distress.";
        }
        if ($temp >= 40.0) {
            $reasons[] = "High fever ({$temp}°C).";
        }

        // Chief complaint keywords (resuscitation / emergency)
        $resuscitation_keywords = ['unresponsive', 'cardiac arrest', 'not breathing', 'no pulse', 'severe bleeding', 'stroke', 'chest pain radiating', 'severe allergic', 'anaphylaxis', 'seizure', 'coma'];
        $emergency_keywords = ['chest pain', 'difficulty breathing', 'shortness of breath', 'severe pain', 'head injury', 'stroke symptoms', 'severe bleeding', 'overdose', 'suicide', 'child not breathing', 'severe burn', 'major trauma', 'altered mental', 'severe headache', 'dka', 'hypoglycemia', 'severe asthma'];
        $urgent_keywords = ['high fever', 'dehydration', 'moderate pain', 'laceration', 'possible fracture', 'abdominal pain', 'vomiting blood', 'urinary retention', 'eye injury', 'severe migraine'];
        $semi_urgent_keywords = ['rash', 'mild fever', 'ear pain', 'sore throat', 'sprain', 'minor cut', 'back pain', 'uti'];
        $non_urgent_keywords = ['refill', 'check-up', 'paperwork', 'mild cold', 'minor complaint'];

        foreach ($resuscitation_keywords as $kw) {
            if (strpos($complaint, $kw) !== false) {
                $reasons[] = "Chief complaint suggests life-threatening condition ({$kw}).";
                return [
                    'suggested_level' => 'resuscitation',
                    'reasoning' => implode(' ', $reasons) ?: 'Immediate intervention required based on presentation.'
                ];
            }
        }

        if (count($reasons) >= 2) {
            return [
                'suggested_level' => 'resuscitation',
                'reasoning' => implode(' ', $reasons)
            ];
        }

        foreach ($emergency_keywords as $kw) {
            if (strpos($complaint, $kw) !== false) {
                $reasons[] = "Chief complaint suggests emergency ({$kw}).";
                return [
                    'suggested_level' => 'emergency',
                    'reasoning' => implode(' ', $reasons) ?: 'Should be seen within 10 minutes.'
                ];
            }
        }

        if (count($reasons) >= 1) {
            return [
                'suggested_level' => 'emergency',
                'reasoning' => implode(' ', $reasons)
            ];
        }

        foreach ($urgent_keywords as $kw) {
            if (strpos($complaint, $kw) !== false) {
                return [
                    'suggested_level' => 'urgent',
                    'reasoning' => "Chief complaint suggests urgent care ({$kw}). Target within 30 minutes."
                ];
            }
        }

        if ($pain >= 7) {
            return [
                'suggested_level' => 'urgent',
                'reasoning' => "Pain level {$pain}/10 suggests urgent assessment."
            ];
        }

        foreach ($semi_urgent_keywords as $kw) {
            if (strpos($complaint, $kw) !== false) {
                return [
                    'suggested_level' => 'semi_urgent',
                    'reasoning' => "Presentation suggests semi-urgent ({$kw}). Target within 1 hour."
                ];
            }
        }

        foreach ($non_urgent_keywords as $kw) {
            if (strpos($complaint, $kw) !== false) {
                return [
                    'suggested_level' => 'non_urgent',
                    'reasoning' => "Likely non-urgent ({$kw}). Can wait up to 2 hours."
                ];
            }
        }

        return [
            'suggested_level' => 'semi_urgent',
            'reasoning' => 'Insufficient indicators for higher urgency. Default semi-urgent; use clinical judgment.'
        ];
    }
}
