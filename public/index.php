<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$expectedToken = getenv('NETHANDLE_API_TOKEN') ?: '';
$providedToken = $_SERVER['HTTP_X_API_TOKEN'] ?? '';

if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    respond(401, ['ok' => false, 'error' => 'unauthorized']);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$input = [];

if (str_starts_with(strtolower($contentType), 'application/json')) {
    $decoded = json_decode(file_get_contents('php://input') ?: '', true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
} else {
    $input = $_POST;
}

$target = isset($input['target']) ? trim((string) $input['target']) : '';
$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';

if ($target === '' || !preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $target)) {
    respond(400, ['ok' => false, 'error' => 'invalid_target']);
}

if (!in_array($action, ['on', 'off', 'status'], true)) {
    respond(400, ['ok' => false, 'error' => 'invalid_action']);
}

$nethandleBin = getenv('NETHANDLE_BIN') ?: '/usr/local/bin/nethandle';
$sudoBin = getenv('NETHANDLE_SUDO_BIN') ?: '/usr/bin/sudo';

$command = sprintf(
    '%s %s %s %s 2>&1',
    escapeshellarg($sudoBin),
    escapeshellarg($nethandleBin),
    escapeshellarg($target),
    escapeshellarg($action)
);

$output = [];
$exitCode = 1;
exec($command, $output, $exitCode);

respond($exitCode === 0 ? 200 : 500, [
    'ok' => $exitCode === 0,
    'target' => $target,
    'action' => $action,
    'exit_code' => $exitCode,
    'output' => $output,
]);
