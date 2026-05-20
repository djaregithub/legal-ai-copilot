<?php
/**
 * Database — SQLite via PDO for storing analysis & draft history.
 */

function get_db(): PDO {
    $dbPath = DB_PATH;
    $isNew = !file_exists($dbPath);
    
    $db = new PDO("sqlite:{$dbPath}");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    if ($isNew) {
        init_db($db);
    }
    
    return $db;
}

function init_db(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS analyses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            risk_score REAL,
            risk_level TEXT,
            doc_type TEXT,
            summary TEXT,
            full_result TEXT,
            processing_time REAL,
            token_count INTEGER,
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS generated_drafts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doc_type TEXT NOT NULL,
            prompt TEXT,
            draft_text TEXT,
            parties TEXT,
            extra_data TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
}

/**
 * Save an analysis record.
 */
function save_analysis(PDO $db, string $filename, string $status, ?array $data = null, ?array $metrics = null, ?string $error = null): int {
    $stmt = $db->prepare("INSERT INTO analyses (filename, status, risk_score, risk_level, doc_type, summary, full_result, processing_time, token_count, error_message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $filename,
        $status,
        $data['risk_score'] ?? null,
        $data['risk_level'] ?? null,
        $data['doc_type'] ?? null,
        $data['summary'] ?? null,
        isset($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
        $metrics['processing_time_seconds'] ?? null,
        $metrics['total_tokens'] ?? null,
        $error,
    ]);
    
    return (int)$db->lastInsertId();
}

/**
 * Update analysis record status.
 */
function update_analysis(PDO $db, int $id, string $status, ?array $data = null, ?array $metrics = null, ?string $error = null): void {
    $stmt = $db->prepare("UPDATE analyses SET status=?, risk_score=?, risk_level=?, doc_type=?, summary=?, full_result=?, processing_time=?, token_count=?, error_message=? WHERE id=?");
    
    $stmt->execute([
        $status,
        $data['risk_score'] ?? null,
        $data['risk_level'] ?? null,
        $data['doc_type'] ?? null,
        $data['summary'] ?? null,
        isset($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
        $metrics['processing_time_seconds'] ?? null,
        $metrics['total_tokens'] ?? null,
        $error,
        $id,
    ]);
}

/**
 * Save a generated draft record.
 */
function save_draft(PDO $db, string $docType, string $prompt, string $draftText, array $parties, ?array $extraData = null): int {
    $stmt = $db->prepare("INSERT INTO generated_drafts (doc_type, prompt, draft_text, parties, extra_data) VALUES (?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $docType,
        $prompt,
        $draftText,
        json_encode($parties, JSON_UNESCAPED_UNICODE),
        $extraData ? json_encode($extraData, JSON_UNESCAPED_UNICODE) : null,
    ]);
    
    return (int)$db->lastInsertId();
}

/**
 * Get recent analyses.
 */
function get_recent_analyses(PDO $db, int $limit = 20): array {
    $stmt = $db->prepare("SELECT id, filename, status, risk_score, risk_level, doc_type, summary, processing_time, created_at FROM analyses ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Get recent drafts.
 */
function get_recent_drafts(PDO $db, int $limit = 20): array {
    $stmt = $db->prepare("SELECT id, doc_type, prompt, created_at FROM generated_drafts ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}
