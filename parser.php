<?php
/**
 * Document Parser — Extract text from PDF and DOCX files.
 */

/**
 * Parse a PDF file and extract text.
 */
function parse_pdf(string $path): array {
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($path);
        $text = $pdf->getText();
        $pages = count($pdf->getPages());

        return [
            'success' => true,
            'text' => $text,
            'pages' => $pages,
            'error' => null,
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'text' => '',
            'pages' => 0,
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Parse a DOCX file and extract text.
 * DOCX is a ZIP file containing XML. We extract the main document part.
 */
function parse_docx(string $path): array {
    try {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new Exception('Could not open DOCX file');
        }

        // Read the main document content from word/document.xml
        $content = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($content === false) {
            throw new Exception('Could not read document content');
        }

        // Parse XML and extract text
        $xml = simplexml_load_string($content);
        $namespaces = $xml->getNamespaces(true);
        
        // Register the main namespace
        $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // Extract all text from <w:t> elements
        $textParts = [];
        $textElements = $xml->xpath('//w:t');
        if ($textElements) {
            foreach ($textElements as $t) {
                $textParts[] = (string)$t;
            }
        }

        $fullText = implode('', $textParts);

        // Add paragraph breaks where <w:p> exists
        $paragraphs = $xml->xpath('//w:p');
        $paraCount = $paragraphs ? count($paragraphs) : 0;

        return [
            'success' => true,
            'text' => $fullText,
            'pages' => max(1, intval($paraCount / 40) + 1),
            'error' => null,
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'text' => '',
            'pages' => 0,
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Auto-detect file type and parse accordingly.
 */
function parse_document(string $path): array {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        return parse_pdf($path);
    } elseif (in_array($ext, ['docx', 'doc'])) {
        return parse_docx($path);
    } else {
        return [
            'success' => false,
            'text' => '',
            'pages' => 0,
            'error' => 'Unsupported file format. Only PDF and DOCX are supported.',
        ];
    }
}
