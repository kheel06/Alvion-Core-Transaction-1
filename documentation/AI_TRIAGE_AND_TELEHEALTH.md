# AI-Powered Triage and Telehealth Support

The system includes optional AI features for **ER Triage** and **Telehealth** to support clinical workflow. When no API key is set, **rule-based** suggestions are used so the features work out of the box.

---

## 1. AI-Powered Triage (ER)

**Where:** **ER Triage** (`Modules → ER Triage → Triage`)

**What it does:**
- Nurse enters **chief complaint** and **vital signs** (BP, HR, RR, temp, O2 sat, pain).
- Clicks **"Get AI suggestion"**.
- System returns a **suggested triage level** (resuscitation / emergency / urgent / semi_urgent / non_urgent) and **brief reasoning**.
- Nurse can click **"Use this level"** to fill the Triage Level dropdown, or override with clinical judgment.
- Final triage decision is always made by the clinician.

**Behavior:**
- **With API key:** Uses an OpenAI-compatible LLM to suggest level and reasoning from complaint + vitals.
- **Without API key:** Uses built-in rule-based logic (vital thresholds + complaint keywords) to suggest level and reasoning.

---

## 2. AI Telehealth Support

**Where:** **Teleconsultation** (`Modules → Telehealth → Teleconsultation`)

**What it does:**
- Staff enters **reason for consultation** and **symptoms** in the booking form.
- Clicks **"Generate summary & questions"**.
- System returns a **short clinical summary** and **3 suggested follow-up questions** the doctor can ask during the consult.
- Helps prepare for the call and document consistently.

**Behavior:**
- **With API key:** Uses an OpenAI-compatible LLM to generate summary and questions.
- **Without API key:** Builds a simple summary from reason + symptoms and suggests generic follow-up questions.

---

## 3. Configuration

**File:** `config/ai.php`

| Option | Description |
|--------|-------------|
| `enabled` | Set to `false` to disable all AI/rule-based suggestions. |
| `api_key` | OpenAI (or compatible) API key. Empty = rule-based only. |
| `base_url` | API base URL (e.g. `https://api.openai.com/v1` or Azure/local). |
| `model` | Model name (e.g. `gpt-4o-mini`, `gpt-4o`). |
| `use_rule_based_fallback` | When `true`, use rule-based logic if API is missing or fails. |

**Optional environment variables (e.g. in `.env`):**
- `AI_API_KEY` – API key.
- `AI_BASE_URL` – Base URL for the API.
- `AI_MODEL` – Model name.
- `AI_TIMEOUT` – Request timeout in seconds.
- `AI_MAX_TOKENS` – Max tokens per response.

---

## 4. API Endpoints (internal use)

- **POST** `api/ai/triage_suggestion.php`  
  Body: `chief_complaint`, `blood_pressure`, `heart_rate`, `respiratory_rate`, `temperature`, `oxygen_saturation`, `pain_level`  
  Returns: `suggested_level`, `reasoning`, `source` (ai | rule_based).

- **POST** `api/ai/telehealth_summary.php`  
  Body: `reason`, `symptoms`  
  Returns: `summary`, `suggested_questions`, `source` (ai | rule_based).

Both require an authenticated user with the appropriate role (admin, nurse, doctor).

---

## 5. Compliance and Safety

- AI output is **advisory only**. The clinician is responsible for the final triage level and clinical decisions.
- No patient data is sent to external APIs unless you configure an API key and the user triggers the feature.
- For strict data residency, use a local or on-prem LLM with an OpenAI-compatible endpoint and set `AI_BASE_URL` and `AI_API_KEY` accordingly.
