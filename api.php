<?php
// api.php — JSON API для работы с рекордами

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

// Ограничиваем только POST/GET
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            echo json_encode([
                'success' => true,
                'records' => getTopRecords(5),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'add':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                break;
            }

            // Ограничение размера тела запроса
            $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($len > 65536) {
                http_response_code(413);
                echo json_encode(['success' => false, 'error' => 'Payload too large']);
                break;
            }

            // Rate limit по IP
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            if (!checkRateLimit($ip, 10)) {
                http_response_code(429);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Слишком часто. Подождите 10 секунд.',
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            // Принимаем и JSON, и form-data
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                $data = $_POST;
            }

            $name  = isset($data['name'])  ? (string) $data['name']  : 'Аноним';
            $score = isset($data['score']) ? (int) $data['score']    : 0;

            if ($score <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Score must be > 0']);
                break;
            }

            $result = addRecord($name, $score);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'ping':
            echo json_encode(['success' => true, 'time' => date('c')]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    error_log('[Babble API] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Internal server error',
    ], JSON_UNESCAPED_UNICODE);
}