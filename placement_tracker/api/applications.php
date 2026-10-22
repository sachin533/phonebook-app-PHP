<?php
/**
 * api/applications.php - the search endpoint behind the search box.
 *
 * It returns the same rows the page would show, already rendered, so the
 * browser and the page never drift apart. Only the current page of rows is
 * sent back - the full list never reaches the browser.
 *
 * GET /api/applications.php?q=&status=&company=&date_from=&date_to=&sort=&dir=&page=&per_page=
 */
declare(strict_types=1);

require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/helpers.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/list.php';
require __DIR__ . '/../inc/table.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** An API answer is JSON, even when something goes wrong. */
function api_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $user = current_user();
} catch (Throwable $e) {
    api_fail('The server could not read the session.', 500);
}

if ($user === null) {
    api_fail('Please sign in to continue.', 401);
}

$userId = (int) $user['id'];

$params = list_params();
$result = fetch_applications_page($userId, $params);

echo json_encode([
    'ok'              => true,
    'total'           => $result['total'],
    'page'            => $result['page'],
    'pages'           => $result['pages'],
    'from'            => $result['from'],
    'to'              => $result['to'],
    'summary_html'    => render_result_summary($result, $params),
    'table_html'      => render_applications_table($result['rows'], ((int) $result['page'] - 1) * (int) $params['per_page'], $params),
    'pagination_html' => render_pagination($result, $params),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
