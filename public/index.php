<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}

function appendAudit(string $path, array $record): bool
{
    $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($line === false) {
        return false;
    }

    return file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
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
$timeoutBin = getenv('NETHANDLE_TIMEOUT_BIN') ?: '/usr/bin/timeout';
$auditLog = getenv('NETHANDLE_AUDIT_LOG') ?: '/var/log/nethandle-api/audit.log';
$timeoutSeconds = (int) (getenv('NETHANDLE_TIMEOUT_SECONDS') ?: '10');
$timeoutSeconds = max(1, min(60, $timeoutSeconds));

$requestId = bin2hex(random_bytes(8));
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$clientId = trim((string) ($_SERVER['HTTP_X_CLIENT_ID'] ?? ''));
if ($clientId !== '' && !preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $clientId)) {
    respond(400, ['ok' => false, 'error' => 'invalid_client_id']);
}

$isMutation = in_array($action, ['on', 'off'], true);
$auditBase = [
    'timestamp' => gmdate('c'),
    'request_id' => $requestId,
    'requester_ip' => $remoteAddress,
    'client_id' => $clientId !== '' ? $clientId : null,
    'target' => $target,
    'action' => $action,
];

if ($isMutation && !appendAudit($auditLog, $auditBase + ['event' => 'intent'])) {
    error_log(sprintf('nethandle audit write failed before execution request_id=%s', $requestId));
    respond(500, [
        'ok' => false,
        'error' => 'audit_unavailable',
        'request_id' => $requestId,
    ]);
}

$command = sprintf(
    '%s --signal=TERM --kill-after=2s %ds %s %s %s %s 2>&1',
    escapeshellarg($timeoutBin),
    $timeoutSeconds,
    escapeshellarg($sudoBin),
    escapeshellarg($nethandleBin),
    escapeshellarg($target),
    escapeshellarg($action)
);

$output = [];
$exitCode = 1;
exec($command, $output, $exitCode);
$timedOut = $exitCode === 124;

if ($isMutation) {
    $resultRecord = array_merge($auditBase, [
        'timestamp' => gmdate('c'),
        'event' => 'result',
        'exit_code' => $exitCode,
        'timed_out' => $timedOut,
        'output' => array_slice($output, 0, 50),
    ]);

    if (!appendAudit($auditLog, $resultRecord)) {
        error_log(sprintf('nethandle audit result write failed request_id=%s exit_code=%d', $requestId, $exitCode));
        respond(500, [
            'ok' => false,
            'error' => 'audit_write_failed',
            'operation_executed' => true,
            'request_id' => $requestId,
            'target' => $target,
            'action' => $action,
            'exit_code' => $exitCode,
            'timed_out' => $timedOut,
        ]);
    }
}

$statusCode = $timedOut ? 504 : ($exitCode === 0 ? 200 : 500);

respond($statusCode, [
    'ok' => $exitCode === 0,
    'request_id' => $requestId,
    'target' => $target,
    'action' => $action,
    'exit_code' => $exitCode,
    'timed_out' => $timedOut,
    'output' => $output,
]);
