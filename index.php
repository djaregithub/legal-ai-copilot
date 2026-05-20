<?php
/**
 * AI Contract Copilot — PHP Version
 * Main entry point & router.
 * 
 * Endpoints:
 *   GET  /              -> Serve frontend HTML
 *   POST /analyze       -> Upload & analyze a contract
 *   POST /generate      -> Generate a legal draft
 *   GET  /history       -> Get analysis & draft history
 *   GET  /health        -> Health check
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/parser.php';
require_once __DIR__ . '/analyzer.php';
require_once __DIR__ . '/database.php';

// Parse the request URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Simple router
switch (true) {
    // Health check
    case $uri === '/health' && $method === 'GET':
        json_response([
            'status' => 'ok',
            'timestamp' => date('c'),
        ]);
        break;

    // Frontend
    case $uri === '/' && $method === 'GET':
        $indexPath = __DIR__ . '/templates/index.html';
        if (file_exists($indexPath)) {
            html_response(file_get_contents($indexPath));
        } else {
            html_response('<h1>Frontend not found. Please deploy templates/index.html</h1>', 500);
        }
        break;

    // Upload & Analyze
    case $uri === '/analyze' && $method === 'POST':
        handle_analyze();
        break;

    // Generate Draft
    case $uri === '/generate' && $method === 'POST':
        handle_generate();
        break;

    // History
    case $uri === '/history' && $method === 'GET':
        handle_history();
        break;

    // 404
    default:
        json_response(['error' => 'Not found', 'path' => $uri], 404);
        break;
}

// ==================== HANDLERS ====================

function handle_analyze(): void {
    // Validate file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'error' => 'No file uploaded or upload error'], 400);
        return;
    }

    $file = $_FILES['file'];
    $filename = basename($file['name']);
    $language = $_POST['language'] ?? 'en';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Validate file type
    if (!in_array($ext, ['pdf', 'docx', 'doc'])) {
        json_response(['success' => false, 'error' => 'Unsupported file format. Only PDF and DOCX.'], 400);
        return;
    }

    $tmpPath = $file['tmp_name'];

    // Step 1: Parse document
    $parseResult = parse_document($tmpPath);
    if (!$parseResult['success']) {
        json_response(['success' => false, 'error' => 'Failed to parse: ' . $parseResult['error']], 400);
        return;
    }

    // Step 2: Save initial record
    $db = get_db();
    $analysisId = save_analysis($db, $filename, 'processing');

    // Step 3: Analyze with AI
    $analysisResult = analyze_contract($parseResult['text'], $language);

    if ($analysisResult['success']) {
        $data = $analysisResult['data'];
        $metrics = $analysisResult['metrics'];

        update_analysis($db, $analysisId, 'done', $data, $metrics);

        json_response([
            'success' => true,
            'analysis_id' => $analysisId,
            'filename' => $filename,
            'pages' => $parseResult['pages'],
            'data' => $data,
            'metrics' => $metrics,
        ]);
    } else {
        update_analysis($db, $analysisId, 'error', null, null, $analysisResult['error']);
        json_response(['success' => false, 'error' => $analysisResult['error']], 500);
    }
}

function handle_generate(): void {
    $docType = $_POST['doc_type'] ?? '';
    $party1Name = $_POST['party1_name'] ?? '';
    $party1Role = $_POST['party1_role'] ?? 'First Party';
    $party2Name = $_POST['party2_name'] ?? '';
    $party2Role = $_POST['party2_role'] ?? 'Second Party';
    $context = $_POST['context'] ?? '';
    $language = $_POST['language'] ?? 'en';

    if (empty($docType) || empty($party1Name) || empty($party2Name)) {
        json_response(['success' => false, 'error' => 'Missing required fields'], 400);
        return;
    }

    $parties = [
        'party1' => ['name' => $party1Name, 'role' => $party1Role],
        'party2' => ['name' => $party2Name, 'role' => $party2Role],
    ];

    $result = generate_draft($docType, $parties, $context, $language);

    if ($result['success']) {
        $db = get_db();
        $draftId = save_draft(
            $db,
            $docType,
            "{$docType}: {$party1Name} & {$party2Name}",
            $result['data']['draft_text'] ?? '',
            $parties,
            $result['data']
        );

        json_response([
            'success' => true,
            'draft_id' => $draftId,
            'data' => $result['data'],
        ]);
    } else {
        json_response(['success' => false, 'error' => $result['error']], 500);
    }
}

function handle_history(): void {
    $limit = min(50, intval($_GET['limit'] ?? 20));
    $db = get_db();

    $analyses = get_recent_analyses($db, $limit);
    $drafts = get_recent_drafts($db, $limit);

    // Format analyses
    $formattedAnalyses = array_map(function ($a) {
        return [
            'id' => (int)$a['id'],
            'filename' => $a['filename'],
            'status' => $a['status'],
            'risk_score' => $a['risk_score'] !== null ? (float)$a['risk_score'] : null,
            'risk_level' => $a['risk_level'],
            'doc_type' => $a['doc_type'],
            'summary' => $a['summary'],
            'processing_time' => $a['processing_time'] !== null ? (float)$a['processing_time'] : null,
            'created_at' => $a['created_at'],
        ];
    }, $analyses);

    $formattedDrafts = array_map(function ($d) {
        return [
            'id' => (int)$d['id'],
            'doc_type' => $d['doc_type'],
            'prompt' => $d['prompt'],
            'created_at' => $d['created_at'],
        ];
    }, $drafts);

    json_response([
        'analyses' => $formattedAnalyses,
        'drafts' => $formattedDrafts,
    ]);
}
