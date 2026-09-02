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

function parseNethandleStatus(array $output): array
{
    $users = [];
    $currentUser = null;
    $inDevices = false;

    foreach ($output as $line) {
        $trimmed = trim((string) $line);

        if (preg_match('/^User:\s+([A-Za-z0-9_.-]{1,64})$/', $trimmed, $matches) === 1) {
            $currentUser = $matches[1];
            $users[$currentUser] = [
                'name' => $currentUser,
                'mode' => null,
                'profile' => null,
                'devices' => [],
            ];
            $inDevices = false;
            continue;
        }

        if ($currentUser === null) {
            continue;
        }

        if (preg_match('/^Mode:\s+(.+)$/', $trimmed, $matches) === 1) {
            $users[$currentUser]['mode'] = strtoupper(trim($matches[1]));
            $inDevices = false;
            continue;
        }

        if (preg_match('/^Profile:\s+(.+)$/', $trimmed, $matches) === 1) {
            $users[$currentUser]['profile'] = strtoupper(trim($matches[1]));
            $inDevices = false;
            continue;
        }

        if ($trimmed === 'Devices:') {
            $inDevices = true;
            continue;
        }

        if ($trimmed === 'Usage:' || $trimmed === 'Users:' || $trimmed === 'Devices:') {
            $currentUser = null;
            $inDevices = false;
            continue;
        }

        if ($inDevices && preg_match('/^-\s+([A-Za-z0-9_.-]{1,64})\s+\(([^)]+)\)$/', $trimmed, $matches) === 1) {
            $users[$currentUser]['devices'][] = [
                'name' => $matches[1],
                'ip' => trim($matches[2]),
            ];
        }
    }

    return array_values($users);
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

$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';
if (!in_array($action, ['on', 'off', 'status'], true)) {
    respond(400, ['ok' => false, 'error' => 'invalid_action']);
}

$target = isset($input['target']) ? trim((string) $input['target']) : '';
if ($action !== 'status' && ($target === '' || !preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $target))) {
    respond(400, ['ok' => false, 'error' => 'invalid_target']);
}
if ($target !== '' && !preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $target)) {
    respond(400, ['ok' => false, 'error' => 'invalid_target']);
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
    'target' => $target !== '' ? $target : null,
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

$commandParts = [
    $timeoutBin,
    '--signal=TERM',
    '--kill-after=2s',
    $timeoutSeconds . 's',
    $sudoBin,
    $nethandleBin,
];
if ($action !== 'status') {
    $commandParts[] = $target;
    $commandParts[] = $action;
}
$command = implode(' ', array_map('escapeshellarg', $commandParts)) . ' 2>&1';

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
$response = [
    'ok' => $exitCode === 0,
    'request_id' => $requestId,
    'target' => $target !== '' ? $target : null,
    'action' => $action,
    'exit_code' => $exitCode,
    'timed_out' => $timedOut,
    'output' => $output,
];

if ($action === 'status' && $exitCode === 0) {
    $response['users'] = parseNethandleStatus($output);
}

respond($statusCode, $response);
