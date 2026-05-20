<?php
/**
 * AI Contract Analyzer — Uses DeepSeek API to analyze legal documents.
 */

use GuzzleHttp\Client;

function get_english_system_prompt(): string {
    return <<<'PROMPT'
You are a legal AI assistant expert in Indonesian law (KUHPerdata, Company Law, Employment Law, and related regulations). Your task is to analyze the given legal contract.

Analyze the contract carefully and output in JSON format ONLY (no other text outside JSON):

{
  "summary": "Brief contract summary in 2-3 sentences, in English",
  "doc_type": "Document type (NDA, MoU, MoA, Cooperation Agreement, etc)",
  "parties": [
    {"name": "Party name", "role": "First Party / Second Party / etc"}
  ],
  "key_dates": [
    {"date": "date", "description": "description"}
  ],
  "risk_score": 0-100,
  "risk_level": "low|medium|high",
  "risk_flags": [
    {
      "clause": "Clause name or section",
      "risk": "low|medium|high",
      "reason": "Short explanation why this is risky"
    }
  ],
  "missing_clauses": [
    "Standard clauses that should exist but were not found"
  ],
  "strengths": [
    "Positive aspects of the contract"
  ],
  "recommendations": [
    "Concrete improvement recommendations"
  ],
  "governing_law": "Governing law (if mentioned)",
  "dispute_resolution": "Dispute resolution method (if mentioned)"
}

Analysis guidelines:
- Risk score 0-100: 0-30 = low risk (good contract), 31-60 = medium risk, 61-100 = high risk
- Flag clauses that disadvantage either party, are unclear, or deviate from Indonesian legal standards
- Note if governing law, dispute resolution, or confidentiality clauses are missing (for NDA)
- Check compliance with relevant laws (Company Law No. 40/2007, KUHPerdata, etc)
- If text is insufficient for analysis, output with note "limited analysis"
- IMPORTANT: Output ALL text fields in ENGLISH
PROMPT;
}

function get_indonesian_system_prompt(): string {
    return <<<'PROMPT'
Anda adalah asisten legal AI yang ahli dalam hukum Indonesia (KUHPerdata, UU PT, UU Ciptaker, dan peraturan terkait). Tugas Anda adalah menganalisis kontrak hukum yang diberikan.

Analisa kontrak dengan seksama dan berikan output dalam format JSON SAJA (tanpa teks lain di luar JSON):

{
  "summary": "Ringkasan singkat kontrak dalam 2-3 kalimat, bahasa Indonesia",
  "doc_type": "Jenis dokumen (NDA, MoU, MoA, Perjanjian Kerjasama, dll)",
  "parties": [
    {"name": "Nama pihak", "role": "Pihak Pertama / Pihak Kedua / dll"}
  ],
  "key_dates": [
    {"date": "tanggal", "description": "deskripsi"}
  ],
  "risk_score": 0-100,
  "risk_level": "low|medium|high",
  "risk_flags": [
    {
      "clause": "Nama atau bagian klausa",
      "risk": "low|medium|high",
      "reason": "Penjelasan singkat kenapa ini berisiko"
    }
  ],
  "missing_clauses": [
    "Klausa standar yang seharusnya ada tapi tidak ditemukan"
  ],
  "strengths": [
    "Aspek positif dari kontrak"
  ],
  "recommendations": [
    "Rekomendasi perbaikan konkret"
  ],
  "governing_law": "Hukum yang mengatur kontrak (jika disebutkan)",
  "dispute_resolution": "Cara penyelesaian sengketa (jika disebutkan)"
}

Pedoman analisa:
- Risk score 0-100: 0-30 = low risk (kontrak baik), 31-60 = medium risk, 61-100 = high risk
- Flag klausa yang merugikan salah satu pihak, tidak jelas, atau tidak sesuai standar hukum Indonesia
- Catat jika tidak ada klausa governing law, dispute resolution, confidentiality (untuk NDA)
- Perhatikan kepatuhan terhadap UU yang relevan (UU PT No. 40/2007, KUHPerdata, dll)
- Jika teks tidak cukup untuk analisa, tetap beri output dengan catatan "analisis terbatas".
- PENTING: Output semua field teks dalam BAHASA INDONESIA
PROMPT;
}

/**
 * Call DeepSeek API with the given system prompt and user message.
 */
function call_deepseek(string $systemPrompt, string $userMessage): ?array {
    $apiKey = DEEPSEEK_API_KEY;
    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'DEEPSEEK_API_KEY not set'];
    }

    // Truncate if too long
    $maxChars = 30000;
    if (strlen($userMessage) > $maxChars) {
        $userMessage = substr($userMessage, 0, $maxChars) . "\n\n[TRUNCATED — Document too long]";
    }

    try {
        $client = new Client([
            'base_uri' => DEEPSEEK_BASE_URL,
            'timeout' => 60.0,
        ]);

        $startTime = microtime(true);
        $response = $client->post('/chat/completions', [
            'json' => [
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1,
                'max_tokens' => 4096,
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
        $elapsed = round(microtime(true) - $startTime, 1);

        $body = json_decode($response->getBody(), true);
        $content = $body['choices'][0]['message']['content'] ?? '';
        $usage = $body['usage'] ?? [];

        $data = json_decode($content, true);

        return [
            'success' => true,
            'error' => null,
            'data' => $data,
            'metrics' => [
                'processing_time_seconds' => $elapsed,
                'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens' => $usage['total_tokens'] ?? 0,
            ],
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'API Error: ' . $e->getMessage(),
            'data' => null,
            'metrics' => null,
        ];
    }
}

/**
 * Analyze a contract text.
 */
function analyze_contract(string $text, string $language = 'en'): array {
    $systemPrompt = ($language === 'en') ? get_english_system_prompt() : get_indonesian_system_prompt();
    $userMessage = ($language === 'en')
        ? "Analyze the following contract:\n\n{$text}"
        : "Analisa kontrak berikut:\n\n{$text}";

    return call_deepseek($systemPrompt, $userMessage);
}

/**
 * Generate a legal document draft.
 */
function generate_draft(string $docType, array $parties, string $additionalContext = '', string $language = 'en'): array {
    $apiKey = DEEPSEEK_API_KEY;
    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'API key not found'];
    }

    $partiesJson = json_encode($parties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($language === 'en') {
        $prompt = "Create a complete and professional {$docType} in English under Indonesian law (civil law system).\n\nParties:\n{$partiesJson}\n";
        if ($additionalContext) {
            $prompt .= "\nAdditional Context:\n{$additionalContext}\n";
        }
        $prompt .= "\nOutput in JSON format:\n{\n  \"title\": \"Document title\",\n  \"doc_type\": \"Document type\",\n  \"parties\": [...],\n  \"draft_text\": \"Complete document text in markdown format, ready to use\",\n  \"clauses\": [\"List of articles/clauses\"],\n  \"notes\": \"Notes for user (placeholders that need manual filling)\"\n}\nUse standard Indonesian legal templates. Place [MANUAL ENTRY] for sections that need user input.";
        $systemMsg = 'You are a legal drafter expert in Indonesian law. Output JSON only.';
    } else {
        $prompt = "Buatkan draft {$docType} dalam bahasa Indonesia yang lengkap dan profesional.\n\nPihak-Pihak:\n{$partiesJson}\n";
        if ($additionalContext) {
            $prompt .= "\nKonteks Tambahan:\n{$additionalContext}\n";
        }
        $prompt .= "\nOutput dalam format JSON:\n{\n  \"title\": \"Judul dokumen\",\n  \"doc_type\": \"Jenis dokumen\",\n  \"parties\": [...],\n  \"draft_text\": \"Teks dokumen lengkap dalam format markdown yang siap digunakan\",\n  \"clauses\": [\"Daftar pasal/klausa yang ada\"],\n  \"notes\": \"Catatan untuk pengguna (placeholder yang perlu diisi manual)\"\n}\nGunakan template standar hukum Indonesia. Tempatkan [ISI MANUAL] untuk bagian yang perlu diisi pengguna.";
        $systemMsg = 'Anda adalah legal drafter yang ahli dalam hukum Indonesia. Output JSON saja.';
    }

    try {
        $client = new Client([
            'base_uri' => DEEPSEEK_BASE_URL,
            'timeout' => 120.0,
        ]);

        $response = $client->post('/chat/completions', [
            'json' => [
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'system', 'content' => $systemMsg],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
                'max_tokens' => 8192,
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);

        $body = json_decode($response->getBody(), true);
        $content = $body['choices'][0]['message']['content'] ?? '';
        $data = json_decode($content, true);

        return [
            'success' => true,
            'error' => null,
            'data' => $data,
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage(), 'data' => null];
    }
}
